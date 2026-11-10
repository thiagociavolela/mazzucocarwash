(() => {
 'use strict';
 const banner = document.getElementById('pwa-install');
 const installButton = document.getElementById('pwa-install-button');
 const manualButton = document.getElementById('pwa-install-link');
 const help = document.getElementById('pwa-help');
 const key = 'mazzuco-install-dismissed-until';
 const standalone = window.matchMedia('(display-mode: standalone)');
 const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
 const embedded = /FBAN|FBAV|Instagram|; wv\)/i.test(navigator.userAgent);
 let promptEvent = null;
 let installing = false;
 let installed = standalone.matches || navigator.standalone === true;
 let eligible = false;
 function dismissed() { try { return Number(localStorage.getItem(key) || 0) > Date.now(); } catch { return false; } }
 function refresh() {
  if (banner) banner.hidden = installed || !eligible || dismissed();
  if (manualButton) manualButton.hidden = installed;
 }
 function dismiss() {
  try { localStorage.setItem(key, String(Date.now() + 7 * 86400000)); } catch { /* Banner still closes in this visit. */ }
  if (banner) banner.hidden = true;
 }
 function instructions() {
  if (!help || !help.showModal) return;
  const description = document.getElementById('pwa-help-description');
  const list = document.getElementById('pwa-help-steps');
  let steps;
  if (embedded) {
   description.textContent = 'Abra este site no navegador do dispositivo para instalar.';
   steps = ['Use o menu deste navegador e escolha abrir no Safari ou Chrome.', 'No navegador, volte à opção “Instalar app” no rodapé do site.'];
  } else if (ios) {
   description.textContent = 'No iPhone ou iPad, adicione o app pelo menu do Safari.';
   steps = ['Abra mazzucocarwash.com no Safari.', 'Toque em Compartilhar e escolha “Adicionar à Tela de Início”.', 'Se aparecer “Abrir como App”, mantenha a opção ativada e toque em Adicionar.'];
  } else {
   description.textContent = 'A disponibilidade da instalação depende do seu navegador.';
   steps = ['Abra o menu do navegador (⋮ ou …), ou o ícone de instalação na barra de endereço.', 'Procure “Instalar aplicativo” ou “Adicionar à tela inicial” e confirme.', 'Se a opção não aparecer, tente abrir este site no Chrome ou Edge.'];
  }
  list.replaceChildren(...steps.map(text => { const li = document.createElement('li'); li.textContent = text; return li; }));
  help.showModal();
 }
 async function install() {
  if (installed || installing) return;
  if (!promptEvent) { instructions(); return; }
  installing = true;
  const event = promptEvent;
  promptEvent = null;
  if (installButton) installButton.disabled = true;
  try {
   await event.prompt();
   const choice = await event.userChoice;
   if (choice.outcome === 'accepted') { if (banner) banner.hidden = true; }
   else dismiss();
  } catch { instructions(); }
  finally { installing = false; if (installButton) installButton.disabled = false; }
 }
 window.addEventListener('beforeinstallprompt', event => {
  event.preventDefault(); promptEvent = event; eligible = true;
  if (installButton) installButton.textContent = 'Instalar app';
  refresh();
 });
 window.addEventListener('appinstalled', () => { installed = true; promptEvent = null; refresh(); if (help?.open) help.close(); });
 standalone.addEventListener?.('change', event => { installed = event.matches || navigator.standalone === true; refresh(); });
 document.getElementById('pwa-dismiss')?.addEventListener('click', dismiss);
 installButton?.addEventListener('click', install);
 manualButton?.addEventListener('click', install);
 document.getElementById('pwa-help-close')?.addEventListener('click', () => help.close());
 document.getElementById('pwa-help-done')?.addEventListener('click', () => help.close());
 if ((ios || /Android/i.test(navigator.userAgent)) && !installed && window.isSecureContext) {
  eligible = true;
  if (installButton) installButton.textContent = 'Como instalar';
 }
 if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
   navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' })
    .catch(() => { /* Site and checkout remain usable if registration fails. */ });
  });
 }
 if (!window.isSecureContext && manualButton) manualButton.hidden = true;
 else refresh();
})();
