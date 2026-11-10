'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
let passed = 0;
async function test(name, fn) { await fn(); passed++; console.log('OK · ' + name); }
function installHarness({ ios=false, standalone=false, secure=true }={}) {
 const listeners={}, elements={}, storage=new Map();
 for(const id of ['pwa-install','pwa-install-button','pwa-install-link','pwa-help','pwa-dismiss','pwa-help-close','pwa-help-done','pwa-help-description','pwa-help-steps']) {
  elements[id]={ hidden:true, disabled:false, textContent:'', listeners:{}, addEventListener(event,handler){this.listeners[event]=handler;}, showModal(){this.open=true;}, close(){this.open=false;}, replaceChildren(...children){this.children=children;} };
 }
 const media={matches:standalone,addEventListener(event,handler){this.change=handler;}};
 const window={isSecureContext:secure,matchMedia:()=>media,addEventListener(event,handler){listeners[event]=handler;}};
 const navigator={userAgent:ios?'iPhone':'Chrome',platform:ios?'iPhone':'Win32',maxTouchPoints:0,standalone};
 const document={getElementById:id=>elements[id],createElement:()=>({textContent:''})};
 vm.runInNewContext(read('public/assets/pwa.js'),{window,navigator,document,localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},Date});
 return {listeners,elements,media,storage};
}
function workerHarness() {
 const handlers={},writes=[],removed=[];
 const cache={addAll:async files=>writes.push(...files)};
 let network=async()=>new Response('online');
 const context={URL,Response,Promise,self:{location:{origin:'https://mazzuco.test'},clients:{claim:async()=>{}},addEventListener:(name,fn)=>handlers[name]=fn},caches:{open:async()=>cache,keys:async()=>['other-app-v1','mazzuco-public-v0','mazzuco-public-v1'],delete:async key=>removed.push(key),match:async()=>new Response('offline')},fetch:request=>network(request)};
 vm.runInNewContext(read('public/sw.js'),context);
 return {handlers,writes,removed,setNetwork:fn=>network=fn,fetch:async({pathname='/',mode='cors',method='GET',origin='https://mazzuco.test'}={})=>{let response;handlers.fetch({request:{url:origin+pathname,method,mode},respondWith:r=>response=r});return response?await response:null;}};
}
(async()=>{
 await test('Manifest standalone e ícones PNG existentes',()=>{
  const manifest=JSON.parse(read('public/manifest.webmanifest'));
  assert.equal(manifest.display,'standalone');assert.equal(manifest.scope,'/');assert.equal(manifest.start_url,'/');
  for(const icon of manifest.icons){const bytes=fs.readFileSync(path.join(root,'public',new URL(icon.src,'https://mazzuco.test').pathname));const [width,height]=icon.sizes.split('x').map(Number);assert.equal(bytes.readUInt32BE(16),width);assert.equal(bytes.readUInt32BE(20),height);}
  assert.ok(manifest.icons.some(icon=>icon.purpose==='maskable'));
 });
 await test('Instalação só abre após clique do usuário',async()=>{
  const h=installHarness();let prompts=0,prevented=false;
  h.listeners.beforeinstallprompt({preventDefault(){prevented=true;},prompt:async()=>{prompts++;},userChoice:Promise.resolve({outcome:'accepted'})});
  assert.ok(prevented);assert.equal(prompts,0);assert.equal(h.elements['pwa-install'].hidden,false);
  await h.elements['pwa-install-button'].listeners.click();assert.equal(prompts,1);assert.equal(h.elements['pwa-install'].hidden,true);
 });
 await test('Dispensa salva intervalo de sete dias',()=>{
  const h=installHarness();h.elements['pwa-dismiss'].listeners.click();assert.equal(h.elements['pwa-install'].hidden,true);assert.ok(Number(h.storage.get('mazzuco-install-dismissed-until'))>Date.now()+6*864e5);
 });
 await test('iPhone recebe instruções de tela inicial',async()=>{
  const h=installHarness({ios:true});assert.equal(h.elements['pwa-install'].hidden,false);await h.elements['pwa-install-button'].listeners.click();assert.ok(h.elements['pwa-help'].open);assert.ok(h.elements['pwa-help-steps'].children.some(li=>li.textContent.includes('Tela de Início')));
 });
 await test('App standalone oculta convite',()=>{
  const h=installHarness({ios:true,standalone:true});assert.equal(h.elements['pwa-install'].hidden,true);assert.equal(h.elements['pwa-install-link'].hidden,true);
 });
 await test('Evento appinstalled remove convite',()=>{
  const h=installHarness({ios:true});h.listeners.appinstalled();assert.equal(h.elements['pwa-install'].hidden,true);assert.equal(h.elements['pwa-install-link'].hidden,true);
 });
 await test('HTTP inseguro não oferece instalação',()=>{
  const h=installHarness({ios:true,secure:false});assert.equal(h.elements['pwa-install'].hidden,true);assert.equal(h.elements['pwa-install-link'].hidden,true);
 });
 await test('Cache contém somente tela offline e ícones',async()=>{
  const h=workerHarness();let done;h.handlers.install({waitUntil:p=>done=p});await done;assert.ok(h.writes.includes('/offline.html'));assert.ok(h.writes.every(p=>p==='/offline.html'||p.startsWith('/assets/icons/')));assert.ok(!h.writes.includes('/'));
 });
 await test('Limpeza preserva caches de outras aplicações',async()=>{
  const h=workerHarness();let done;h.handlers.activate({waitUntil:p=>done=p});await done;assert.deepEqual(h.removed,['mazzuco-public-v0','mazzuco-public-v1']);
 });
 await test('API, POST, uploads e terceiros passam direto para rede',async()=>{
  const h=workerHarness();for(const request of [{pathname:'/api/account'},{pathname:'/api/book',method:'POST'},{pathname:'/uploads/customer.webp'},{pathname:'/v1/payments/1',origin:'https://api.mercadopago.com'}])assert.equal(await h.fetch(request),null);
 });
 await test('HTML autenticado é buscado na rede e nunca gravado',async()=>{
  const h=workerHarness();const response=await h.fetch({pathname:'/admin',mode:'navigate'});assert.equal(await response.text(),'online');assert.equal(h.writes.length,0);
 });
 await test('Navegação sem rede recebe apenas aviso offline',async()=>{
  const h=workerHarness();h.setNetwork(async()=>{throw Error('Offline');});const response=await h.fetch({pathname:'/?payment=success',mode:'navigate'});assert.equal(await response.text(),'offline');assert.equal(h.writes.length,0);
 });
 console.log(`\n${passed} verificações PWA aprovadas.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
