<?php
declare(strict_types=1);
// Creates a separate disposable schema; never deletes data from the application database.
$root=dirname(__DIR__);
foreach(file($root.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(str_starts_with(trim($line),'#')||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);if(getenv(trim($k))===false)putenv(trim($k).'='.trim($v));}
$testName='mazzuco_test_'.bin2hex(random_bytes(5));
$control=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'),getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$control->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");putenv('DB_NAME='.$testName);
require $root.'/app/bootstrap.php';require $root.'/database/seed.php';
$passed=0;
function test(string $name,callable $fn):void{global $passed;$fn();$passed++;echo "OK · $name\n";}
function expect(bool $condition,string $message='Falha na verificação'):void{if(!$condition)throw new RuntimeException($message);}
function rejects(callable $fn):void{try{$fn();}catch(DomainException $e){return;}throw new RuntimeException('A operação inválida foi aceita.');}
try{
 foreach(explode(';',file_get_contents($root.'/database/schema.sql')) as $sql)if(trim($sql))db()->exec($sql);seed();
 test('Catálogo inicial sem preços inventados',function(){expect((int)query('SELECT COUNT(*) FROM services')->fetchColumn()===17);expect((int)query("SELECT COUNT(*) FROM services WHERE mode='quote' AND price_cents IS NULL")->fetchColumn()===17);});
 query("INSERT INTO users(name,email,password) VALUES('Cliente de teste','teste@example.invalid',?)",[password_hash('test-password-123',PASSWORD_DEFAULT)]);$u=one('SELECT * FROM users WHERE email=?',['teste@example.invalid']);
 query("UPDATE services SET mode='fixed',price_cents=20000,duration=60 WHERE id=1");query('UPDATE schedule_days SET active=1,opens=?,closes=?,break_start=?,break_end=?',['08:00','18:00','12:00','13:00']);
 $date=date('Y-m-d',strtotime('+3 days'));$base=['services'=>[1],'vehicle_type'=>'carro','vehicle_model'=>'Veículo de teste','plate'=>'TESTE','starts_at'=>$date.' 09:00:00'];
 test('Preço recalculado no servidor ignora total enviado',function()use($base,$u){$p=price_order($base['services'],'carro');expect($p['total_cents']===20000);});
 test('Serviço sob orçamento não permite compra',function(){rejects(fn()=>price_order([2],'carro'));});
 test('Compatibilidade do veículo é exigida',function(){rejects(fn()=>price_order([1],'aeronave'));});
 test('Intervalo e expediente bloqueiam horários',function()use($date){expect(valid_window($date.' 11:30:00',60)===null);expect(valid_window($date.' 18:00:00',60)===null);});
 $b=reserve($base,$u);
 test('Reserva guarda preço e duração históricos',function()use($b){$i=one('SELECT * FROM booking_items WHERE booking_id=?',[$b['id']]);expect((int)$i['price_cents']===20000 && (int)$i['duration']===60);});
 test('Conflito de agenda é rejeitado',function()use($base,$u){rejects(fn()=>reserve($base,$u));});
 test('Horário ocupado não aparece na disponibilidade',function()use($date){expect(!in_array('09:00',slots($date,60,'carro'),true));});
 test('Leva e traz desativado é rejeitado',function(){rejects(fn()=>price_order([1],'carro','',null,true,'Campestre'));});
 query("INSERT INTO promotions(name,code,type,value,starts_at,ends_at,service_ids,min_cents,max_uses,per_user_limit) VALUES('Cupom teste','TESTE','percent',15,DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(NOW(),INTERVAL 1 DAY),'[]',0,1,1)");
 test('Desconto percentual em centavos',function()use($u){$p=price_order([1],'carro','TESTE',(int)$u['id']);expect($p['discount_cents']===3000 && $p['total_cents']===17000);});
 test('Cupom inválido é rejeitado',function(){rejects(fn()=>price_order([1],'carro','INVALIDO'));});
 $withCoupon=$base;$withCoupon['starts_at']=$date.' 10:00:00';$withCoupon['coupon']='TESTE';$bc=reserve($withCoupon,$u);
 test('Limite de usos inclui reservas ativas',function()use($u){rejects(fn()=>price_order([1],'carro','TESTE',(int)$u['id']));});
 $payload=['external_reference'=>$b['reference'],'live_mode'=>false,'currency_id'=>'BRL','transaction_amount'=>200,'status'=>'approved','date_last_updated'=>date(DATE_ATOM)];
 test('Valor divergente não confirma pedido',function()use($payload){$wrong=$payload;$wrong['transaction_amount']=1;rejects(fn()=>apply_verified_payment($wrong,'1001'));});
 test('Ambiente divergente não confirma pedido',function()use($payload){$wrong=$payload;$wrong['live_mode']=true;rejects(fn()=>apply_verified_payment($wrong,'1001'));});
 test('Pagamento válido confirma reserva',function()use($payload,$b){$r=apply_verified_payment($payload,'1001');expect($r['booking_status']==='confirmed');expect(one('SELECT status FROM bookings WHERE id=?',[$b['id']])['status']==='confirmed');});
 test('Notificação repetida é idempotente',function()use($payload){expect(apply_verified_payment($payload,'1001')['status']==='duplicate');});
 query("UPDATE bookings SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?",[$bc['id']]);
 test('Pagamento tardio encaminha para análise',function()use($bc){$p=['external_reference'=>$bc['reference'],'live_mode'=>false,'currency_id'=>'BRL','transaction_amount'=>170,'status'=>'approved','date_last_updated'=>date(DATE_ATOM)];$r=apply_verified_payment($p,'1002');expect($r['booking_status']==='review');});
 test('Não permite confirmar atendimento manualmente',function()use($b){rejects(fn()=>update_booking_status((int)$b['id'],'confirmed'));});
 test('Transições do atendimento respeitam etapas',function()use($b){rejects(fn()=>update_booking_status((int)$b['id'],'completed'));update_booking_status((int)$b['id'],'in_progress');update_booking_status((int)$b['id'],'ready');update_booking_status((int)$b['id'],'completed');expect(one('SELECT status FROM bookings WHERE id=?',[$b['id']])['status']==='completed');});
 test('Assinatura HMAC válida e adulterada',function(){$secret='segredo-teste';$signature='ts=1234,v1='.hash_hmac('sha256','id:123;request-id:req-test;ts:1234;',$secret);expect(verify_webhook('123','req-test',$signature,$secret));expect(!verify_webhook('124','req-test',$signature,$secret));});
 test('Criptografia das credenciais',function(){$encrypted=secret_encrypt('segredo-apenas-teste');expect($encrypted!=='segredo-apenas-teste');expect(secret_decrypt($encrypted)==='segredo-apenas-teste');});
 test('Duas reservas simultâneas não ocupam o mesmo box',function()use($base,$u,$root){
  $input=$base;$input['starts_at']=substr($base['starts_at'],0,10).' 14:00:00';$workers=[];
  for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,$root.'/tests/reserve-worker.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$root);expect(is_resource($process));fwrite($pipes[0],json_encode(['booking'=>$input,'user'=>$u]));fclose($pipes[0]);$workers[]=[$process,$pipes];}
  $results=[];foreach($workers as [$p,$pipes]){$results[]=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);proc_close($p);}sort($results);expect($results===['conflict','reserved'],'Concorrência não bloqueada: '.json_encode($results));
 });
 echo "\n$passed testes aprovados.\n";
}finally{
 // Exact generated schema identifier, never DB_NAME from user configuration.
 if(preg_match('/^mazzuco_test_[a-f0-9]{10}$/',$testName))$control->exec("DROP DATABASE `$testName`");
}
