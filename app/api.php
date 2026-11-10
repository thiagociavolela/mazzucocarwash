<?php
declare(strict_types=1);
$route=trim(substr($path,4),'/');$method=$_SERVER['REQUEST_METHOD'];
if($route==='webhook'){
 if($method!=='POST')fail('Método inválido.',405);
 parse_str(str_replace('data.id=','data_id=',$_SERVER['QUERY_STRING']??''),$params);$id=(string)($params['data_id']??'');
 if(!verify_webhook($id,$_SERVER['HTTP_X_REQUEST_ID']??'',$_SERVER['HTTP_X_SIGNATURE']??'',credential('MP_WEBHOOK_SECRET')))fail('Assinatura inválida.',401);
 try{json_response(reconcile_payment($id));}catch(Throwable $e){error_log('Mazzuco webhook: '.get_class($e));json_response(['error'=>'Notificação não processada.'],503);}
}
if($method==='POST'){
 if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail('Sessão expirada. Atualize a página.',419);
 $input=json_decode(file_get_contents('php://input'),true)??[];if(!is_array($input))fail('Dados inválidos.');
}
try{
 if($method==='GET' && $route==='bootstrap'){
  $catalog=rows('SELECT s.*,c.name category FROM services s JOIN categories c ON c.id=s.category_id WHERE s.active=1 ORDER BY c.sort_order,s.sort_order,s.id');
  $currentUser=user();$promotions=array_values(array_filter(live_promotions($currentUser?(int)$currentUser['id']:null),fn($p)=>empty($p['code'])));
  foreach($catalog as &$s){$s['vehicle_types']=json_decode($s['vehicle_types'],true);$s['prices']=rows('SELECT vehicle_type,price_cents FROM service_prices WHERE service_id=?',[$s['id']]);$s['promotion']=null;if($s['price_cents']!==null && $s['mode']==='fixed')foreach($promotions as $p){$d=calculate_discount((int)$s['price_cents'],[['id'=>$s['id'],'unit_cents'=>$s['price_cents']]],$p);if($d>($s['promotion']['discount_cents']??0))$s['promotion']=['name'=>$p['name'],'discount_cents'=>$d,'price_cents'=>(int)$s['price_cents']-$d];}}unset($s);
  $keys=['brand','tagline','hero_title','hero_text','address','phone','whatsapp','instagram','pickup_enabled','pickup_regions','pickup_cents','cancel_hours','privacy','terms','cancellation'];$settings=[];foreach($keys as $k)$settings[$k]=setting($k);
  json_response(['user'=>user(),'csrf'=>$_SESSION['csrf'],'services'=>$catalog,'categories'=>rows('SELECT * FROM categories ORDER BY sort_order,id'),'content'=>rows('SELECT * FROM content WHERE active=1 ORDER BY sort_order,id'),'settings'=>$settings,'promotions'=>$promotions,'schedule'=>rows('SELECT * FROM schedule_days'),'payment_ready'=>credential('MP_ACCESS_TOKEN')!=='' && credential('MP_WEBHOOK_SECRET')!=='' && str_starts_with(env('APP_URL'),'https://')]);
 }
 if($method==='POST' && in_array($route,['login','register','forgot','reset'],true)){
  $email=strtolower(trim($input['email']??''));$ip=Mazzuco\HttpContext::clientIp($_SERVER,env('TRUSTED_PROXIES'));$identity=hash('sha256',$ip);$attempts=(int)query('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$identity])->fetchColumn();check($attempts<15,'Muitas tentativas. Aguarde 15 minutos.');query('INSERT INTO login_attempts(identity_hash) VALUES(?)',[$identity]);
  if($route==='reset'){$token=$input['token']??'';$r=one('SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>NOW()',[hash('sha256',$token)]);check((bool)$r,'Link inválido ou expirado.');check(strlen($input['password']??'')>=12,'Use uma senha de pelo menos 12 caracteres.');transaction(function()use($r,$input){query('SELECT id FROM password_resets WHERE id=? FOR UPDATE',[$r['id']]);check((bool)one('SELECT id FROM password_resets WHERE id=? AND used=0 AND expires_at>NOW()',[$r['id']]),'Link já utilizado.');query('UPDATE users SET password=? WHERE id=?',[password_hash($input['password'],PASSWORD_DEFAULT),$r['user_id']]);query('UPDATE password_resets SET used=1 WHERE user_id=?',[$r['user_id']]);});json_response(['ok'=>true]);}
  check((bool)filter_var($email,FILTER_VALIDATE_EMAIL),'Informe um e-mail válido.');$u=one('SELECT * FROM users WHERE email=?',[$email]);
  if($route==='forgot'){
   if($u){$token=bin2hex(random_bytes(32));query('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[$u['id'],hash('sha256',$token)]);$url=rtrim(env('APP_URL'),'/').'/?reset='.rawurlencode($token);$from=env('MAIL_FROM');if(filter_var($from,FILTER_VALIDATE_EMAIL)){@mail($email,'Recuperar acesso — Mazzuco CarWash',"Acesse o link em até 30 minutos:\n".$url,"From: ".$from);}else{audit('auth.reset_mail_unconfigured');}}
   json_response(['message'=>'Se houver uma conta com este e-mail, enviaremos as instruções.']);
  }
  if($route==='register'){
   check(!$u,'Não foi possível cadastrar este e-mail. Tente entrar ou recuperar seu acesso.');$name=trim($input['name']??'');$phone=trim($input['phone']??'');check(mb_strlen($name)>=2 && mb_strlen($name)<=120,'Informe seu nome completo.');check(strlen($input['password']??'')>=12,'Use uma senha de pelo menos 12 caracteres.');check(!empty($input['terms']),'Aceite os termos e a política de privacidade.');check(mb_strlen($phone)<=30,'Telefone inválido.');query('INSERT INTO users(name,email,phone,password) VALUES(?,?,?,?)',[$name,$email,$phone,password_hash($input['password'],PASSWORD_DEFAULT)]);$u=['id'=>db()->lastInsertId()];
  }else{check($u && password_verify($input['password']??'',$u['password']),'E-mail ou senha incorretos.');}
  session_regenerate_id(true);$_SESSION['uid']=$u['id'];json_response(['user'=>user()]);
 }
 if($route==='logout' && $method==='POST'){$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));json_response(['ok'=>true]);}
 if($route==='quote' && $method==='POST'){$u=user();json_response(price_order($input['services']??[],$input['vehicle_type']??'',$input['coupon']??'',$u?(int)$u['id']:null,(bool)($input['pickup']??false),$input['region']??''));}
 if($route==='slots' && $method==='POST'){$p=price_order($input['services']??[],$input['vehicle_type']??'');json_response(['slots'=>slots($input['date']??'',$p['duration'],$input['vehicle_type'],(bool)($input['pickup']??false))]);}
 if($route==='book' && $method==='POST'){$u=require_user();check(credential('MP_ACCESS_TOKEN')!=='' && credential('MP_WEBHOOK_SECRET')!=='' && str_starts_with(env('APP_URL'),'https://'),'Agendamento online aguardando ativação do pagamento. Solicite atendimento à empresa.');$b=reserve($input,$u);try{$url=checkout($b,$u);json_response(['url'=>$url,'booking'=>$b]);}catch(Throwable $e){json_response(['error'=>'Sua reserva foi criada, mas o checkout não abriu. Tente novamente em Meus agendamentos.','reserved'=>true],503);}}
 if($route==='checkout' && $method==='POST'){$u=require_user();$b=one('SELECT * FROM bookings WHERE id=? AND user_id=?',[(int)($input['id']??0),$u['id']]);check((bool)$b,'Reserva não encontrada.');json_response(['url'=>checkout($b,$u)]);}
 if($route==='account' && $method==='GET'){$u=require_user();expire_reservations();$bookings=rows('SELECT b.*,v.model,v.plate,v.type FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.user_id=? ORDER BY b.starts_at DESC',[$u['id']]);foreach($bookings as &$b)$b['items']=rows('SELECT * FROM booking_items WHERE booking_id=?',[$b['id']]);json_response(['bookings'=>$bookings,'vehicles'=>rows('SELECT * FROM vehicles WHERE user_id=?',[$u['id']]),'requests'=>rows('SELECT * FROM requests WHERE user_id=? ORDER BY id DESC',[$u['id']])]);}
 if($route==='profile' && $method==='POST'){$u=require_user();$name=trim($input['name']??'');$phone=trim($input['phone']??'');check(mb_strlen($name)>=2 && mb_strlen($name)<=120 && mb_strlen($phone)<=30,'Confira seu nome e telefone.');query('UPDATE users SET name=?,phone=? WHERE id=?',[$name,$phone,$u['id']]);json_response(['user'=>user()]);}
 if($route==='request' && $method==='POST'){
  $u=require_user();$kind=$input['kind']??'quote';check(in_array($kind,['quote','cancel','reschedule'],true),'Solicitação inválida.');$bid=(int)($input['booking_id']??0);$message=trim($input['message']??'');check(mb_strlen($message)>=5 && mb_strlen($message)<=2000,'Descreva sua solicitação (5 a 2000 caracteres).');
  if($kind!=='quote'){$b=one('SELECT * FROM bookings WHERE id=? AND user_id=?',[$bid,$u['id']]);check((bool)$b,'Agendamento não encontrado.');check(!in_array($b['status'],['cancelled','completed','expired'],true),'Este atendimento já foi encerrado.');check(strtotime($b['starts_at'])>=time()+(int)setting('cancel_hours',24)*3600,'O prazo online passou. Entre em contato com a empresa.');}
  query('INSERT INTO requests(user_id,booking_id,kind,message) VALUES(?,?,?,?)',[$u['id'],$bid?:null,$kind,$message]);json_response(['ok'=>true]);
 }
 if(str_starts_with($route,'admin')){require_user(true);require __DIR__.'/admin-api.php';}
 fail('Rota não encontrada.',404);
}catch(DomainException $ex){fail($ex->getMessage());}catch(Throwable $ex){error_log('Mazzuco API '.$route.': '.get_class($ex).' '.$ex->getCode());fail('Não foi possível concluir a operação. Verifique a configuração e tente novamente.',500);}
