<?php
if($route==='admin/reschedule' && $method==='POST'){reschedule_booking((int)$input['id'],($input['starts_at']??'').':00');json_response(['ok'=>true]);}
if($route==='admin/reserve' && $method==='POST'){
 $customer=one('SELECT id,name,email,phone,role FROM users WHERE id=?',[(int)($input['customer_id']??0)]);check((bool)$customer,'Cliente não encontrado.');check(credential('MP_ACCESS_TOKEN')!=='' && credential('MP_WEBHOOK_SECRET')!=='' && str_starts_with(env('APP_URL'),'https://'),'Configure o pagamento antes de criar reservas.');$b=reserve($input,$customer);$url=checkout($b,$customer);json_response(['booking'=>$b,'url'=>$url]);
}
if($route==='admin/booking' && $method==='GET'){
 $id=(int)($_GET['id']??0);check((bool)one('SELECT id FROM bookings WHERE id=?',[$id]),'Agendamento não encontrado.');json_response(['items'=>rows('SELECT * FROM booking_items WHERE booking_id=?',[$id])]);
}
if($route==='admin/data' && $method==='GET'){
 expire_reservations();$section=$_GET['section']??'dashboard';$data=[];
 switch($section){
 case 'dashboard':$data=['stats'=>one("SELECT COUNT(*) bookings,COALESCE(SUM(CASE WHEN payment_status='approved' THEN total_cents ELSE 0 END),0) revenue,COALESCE(SUM(status='confirmed'),0) confirmed,COALESCE(SUM(payment_status='pending'),0) pending,COALESCE(SUM(status='cancelled'),0) cancelled FROM bookings WHERE starts_at>=DATE_FORMAT(NOW(),'%Y-%m-01')"),'today'=>rows("SELECT b.*,u.name customer,v.model FROM bookings b JOIN users u ON u.id=b.user_id JOIN vehicles v ON v.id=b.vehicle_id WHERE DATE(b.starts_at)=CURDATE() ORDER BY b.starts_at"),'popular'=>rows('SELECT i.name,COUNT(*) total FROM booking_items i JOIN bookings b ON b.id=i.booking_id WHERE b.payment_status=\'approved\' GROUP BY i.name ORDER BY total DESC LIMIT 5')];break;
 case 'services':$data=['services'=>rows('SELECT * FROM services ORDER BY sort_order,id'),'categories'=>rows('SELECT * FROM categories ORDER BY sort_order,id'),'prices'=>rows('SELECT * FROM service_prices')];break;
 case 'promotions':$data=['promotions'=>rows('SELECT * FROM promotions ORDER BY id DESC'),'services'=>rows('SELECT id,name FROM services ORDER BY name')];break;
 case 'agenda':$from=$_GET['from']??date('Y-m-d');$to=$_GET['to']??date('Y-m-d',strtotime('+30 days'));check(valid_date($from)&&valid_date($to),'Período inválido.');$data=['bookings'=>rows('SELECT b.*,u.name customer,u.phone,u.email,v.model,v.plate,v.type,r.name resource FROM bookings b JOIN users u ON u.id=b.user_id JOIN vehicles v ON v.id=b.vehicle_id JOIN resources r ON r.id=b.resource_id WHERE b.starts_at>=? AND b.starts_at<DATE_ADD(?,INTERVAL 1 DAY) ORDER BY b.starts_at',[$from,$to]),'resources'=>rows('SELECT * FROM resources'),'schedule'=>rows('SELECT * FROM schedule_days'),'blocks'=>rows('SELECT * FROM blocks WHERE ends_at>NOW() ORDER BY starts_at')];break;
 case 'payments':owner();$data=['payments'=>rows('SELECT p.*,b.reference,b.status booking_status,u.name customer FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=b.user_id ORDER BY p.id DESC LIMIT 200'),'events'=>rows('SELECT * FROM payment_events ORDER BY id DESC LIMIT 100'),'configured'=>credential('MP_ACCESS_TOKEN')!=='','webhook_configured'=>credential('MP_WEBHOOK_SECRET')!=='','environment'=>setting('mp_environment','sandbox'),'webhook_url'=>rtrim(env('APP_URL'),'/').'/api/webhook'];break;
 case 'customers':$data=['customers'=>rows("SELECT u.id,u.name,u.email,u.phone,u.role,u.created_at,COUNT(b.id) bookings FROM users u LEFT JOIN bookings b ON b.user_id=u.id GROUP BY u.id,u.name,u.email,u.phone,u.role,u.created_at ORDER BY u.id DESC"),'vehicles'=>rows('SELECT v.*,u.name customer FROM vehicles v JOIN users u ON u.id=v.user_id ORDER BY v.id DESC LIMIT 200')];break;
 case 'content':$data=['content'=>rows('SELECT * FROM content ORDER BY kind,sort_order,id')];break;
 case 'settings':owner();$data=['settings'=>rows('SELECT * FROM settings'),'users'=>rows("SELECT id,name,email,role FROM users WHERE role<>'customer'")];break;
 case 'requests':$data=['requests'=>rows('SELECT r.*,u.name customer,u.email,u.phone FROM requests r JOIN users u ON u.id=r.user_id ORDER BY r.id DESC LIMIT 200')];break;
 case 'audit':owner();$data=['logs'=>rows('SELECT a.*,u.name actor FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200')];break;
 default:fail('Seção inválida.',404);
 }
 json_response($data);
}
if($method==='POST' && $route==='admin/upload')fail('Use o formulário de upload.',405);
if($method==='POST' && $route==='admin/save'){
 $entity=$input['entity']??'';$d=$input['data']??[];$id=(int)($d['id']??0);
 $allowed=[
 'services'=>['category_id','name','description','duration','price_cents','mode','vehicle_types','image','featured','active','sort_order'],
 'categories'=>['name','sort_order'],
 'promotions'=>['name','code','type','value','starts_at','ends_at','service_ids','min_cents','max_uses','per_user_limit','active'],
 'resources'=>['name','vehicle_types','active'],
 'blocks'=>['resource_id','starts_at','ends_at','reason'],
 'content'=>['kind','title','body','image','active','sort_order']
 ];
 check(isset($allowed[$entity]),'Entidade inválida.');if(in_array($entity,['services','categories','promotions','resources','blocks'],true))owner();
 $values=[];foreach($allowed[$entity] as $field)if(array_key_exists($field,$d))$values[$field]=$d[$field];
 check(count($values)>0,'Nenhum dado enviado.');
 foreach(['name','title','reason'] as $field)if(isset($values[$field]))check(mb_strlen(trim($values[$field]))>=2 && mb_strlen($values[$field])<=180,'Confira o título ou nome.');
 foreach(['vehicle_types','service_ids'] as $field)if(isset($values[$field])){check(is_array($values[$field]),'Lista inválida.');if($field==='vehicle_types')check(count(array_diff($values[$field],VEHICLE_TYPES))===0 && count($values[$field])>0,'Tipos de veículo inválidos.');$values[$field]=json_encode($values[$field]);}
 if(isset($values['image']))check($values['image']===''||preg_match('~^/(uploads|images)/[a-zA-Z0-9_.-]+\.(png|jpe?g|webp)$~',$values['image'])===1,'Utilize uma imagem enviada pelo painel.');
 foreach(['active','featured'] as $k)if(isset($values[$k]))$values[$k]=(int)(bool)$values[$k];
 if($entity==='services'){
  check(in_array($values['mode']??'', ['fixed','vehicle','quote'],true),'Modalidade inválida.');check((int)($values['duration']??0)>=30&&(int)$values['duration']<=1440,'Duração entre 30 e 1440 minutos.');
  $values['price_cents']=($values['price_cents']??'')===''?null:(int)$values['price_cents'];if($values['mode']==='fixed')check($values['price_cents']>0,'Cadastre um preço maior que zero.');
 }
 if($entity==='promotions'){
  check(in_array($values['type']??'',['percent','fixed'],true),'Tipo de desconto inválido.');check((int)($values['value']??0)>0 && ($values['type']!=='percent'||(int)$values['value']<=100),'Desconto inválido.');
  check(strtotime($values['ends_at']??'')>strtotime($values['starts_at']??''),'Período inválido.');$values['code']=trim(strtoupper($values['code']??''))?:null;check(!$values['code']||preg_match('/^[A-Z0-9_-]{2,40}$/',$values['code'])===1,'Cupom inválido.');$values['max_uses']=($values['max_uses']??'')===''?null:(int)$values['max_uses'];check((int)($values['per_user_limit']??0)>0,'Limite por cliente inválido.');
 }
 if($entity==='blocks'){check(strtotime($values['ends_at']??'')>strtotime($values['starts_at']??''),'Período inválido.');$values['resource_id']=($values['resource_id']??'')===''?null:(int)$values['resource_id'];}
 if($entity==='content')check(in_array($values['kind']??'',['faq','gallery','banner','policy'],true),'Tipo de conteúdo inválido.');
 transaction(function()use($entity,$values,$id,$d){
  if(in_array($entity,['services','promotions','resources','blocks'],true))rows('SELECT id FROM resources ORDER BY id FOR UPDATE');
  if($id){check((bool)one('SELECT id FROM '.$entity.' WHERE id=?',[$id]),'Registro não encontrado.');$assign=implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($values)));query('UPDATE '.$entity.' SET '.$assign.' WHERE id=?',[...array_values($values),$id]);$saved=$id;}
  else{$fields=implode(',',array_map(fn($f)=>'`'.$f.'`',array_keys($values)));query('INSERT INTO '.$entity.'('.$fields.') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));$saved=(int)db()->lastInsertId();}
  if($entity==='services' && isset($d['prices'])){query('DELETE FROM service_prices WHERE service_id=?',[$saved]);foreach($d['prices'] as $type=>$cents){check(in_array($type,VEHICLE_TYPES,true),'Tipo de veículo inválido.');if($cents!==''&&$cents!==null){check((int)$cents>0,'Preço por veículo inválido.');query('INSERT INTO service_prices(service_id,vehicle_type,price_cents) VALUES(?,?,?)',[$saved,$type,(int)$cents]);}}}
  audit($entity.'.saved',['id'=>$saved]);
 });json_response(['ok'=>true]);
}
if($method==='POST' && $route==='admin/delete'){
 owner();$entity=$input['entity']??'';$id=(int)($input['id']??0);check(in_array($entity,['blocks','content'],true),'Para preservar o histórico, desative este registro.');query('DELETE FROM '.$entity.' WHERE id=?',[$id]);audit($entity.'.deleted',['id'=>$id]);json_response(['ok'=>true]);
}
if($method==='POST' && $route==='admin/settings'){
 owner();$allowed=['brand','tagline','hero_title','hero_text','address','phone','whatsapp','instagram','reservation_minutes','advance_hours','booking_horizon_days','pickup_enabled','pickup_regions','pickup_cents','pickup_advance_hours','cancel_hours','privacy','terms','cancellation'];
 transaction(function()use($allowed,$input){foreach($input as $key=>$value){if(!in_array($key,$allowed,true))continue;if(in_array($key,['reservation_minutes','advance_hours','booking_horizon_days','pickup_cents','pickup_advance_hours','cancel_hours'],true)){check(is_numeric($value) && (int)$value>=0,'Configuração numérica inválida.');$value=(int)$value;}if($key==='reservation_minutes')check($value>=5 && $value<=60,'Reserva deve durar de 5 a 60 minutos.');if($key==='booking_horizon_days')check($value>=1 && $value<=180,'Horizonte deve ser de 1 a 180 dias.');if($key==='pickup_regions')check(is_array($value),'Regiões inválidas.');query('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,json_encode($value,JSON_UNESCAPED_UNICODE)]);}audit('settings.saved');});json_response(['ok'=>true]);
}
if($method==='POST' && $route==='admin/schedule'){
 owner();transaction(function()use($input){rows('SELECT id FROM resources ORDER BY id FOR UPDATE');foreach($input['days']??[] as $day){check((int)$day['weekday']>=0 && (int)$day['weekday']<=6,'Dia inválido.');foreach(['opens','closes'] as $f)check(preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$day[$f]??'')===1,'Horário inválido.');check($day['opens']<$day['closes'],'Fechamento deve ser após a abertura.');$bs=$day['break_start']?:null;$be=$day['break_end']?:null;if($bs||$be)check($bs && $be && $bs<$be && $bs>=$day['opens'] && $be<=$day['closes'],'Intervalo inválido.');query('UPDATE schedule_days SET opens=?,closes=?,break_start=?,break_end=?,active=? WHERE weekday=?',[$day['opens'],$day['closes'],$bs,$be,(int)(bool)$day['active'],$day['weekday']]);}audit('schedule.saved');});json_response(['ok'=>true]);
}
if($method==='POST' && $route==='admin/booking-status'){update_booking_status((int)$input['id'],$input['status']??'');json_response(['ok'=>true]);}
if($method==='POST' && $route==='admin/request-status'){$status=$input['status']??'';check(in_array($status,['open','in_progress','closed'],true),'Status inválido.');query('UPDATE requests SET status=? WHERE id=?',[$status,(int)$input['id']]);audit('request.status',['id'=>(int)$input['id'],'status'=>$status]);json_response(['ok'=>true]);}
if($method==='POST' && $route==='admin/credentials'){
 owner();check(in_array($input['environment']??'',['sandbox','production'],true),'Ambiente inválido.');
 transaction(function()use($input){foreach(['MP_ACCESS_TOKEN','MP_WEBHOOK_SECRET'] as $key)if(!empty($input[$key])){check(strlen($input[$key])<2000,'Credencial inválida.');query('INSERT INTO secrets(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,secret_encrypt(trim($input[$key]))]);}query('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['mp_environment',json_encode($input['environment'])]);audit('payment.credentials_updated');});json_response(['ok'=>true]);
}
if($method==='POST' && $route==='admin/reconcile'){owner();json_response(reconcile_payment((string)($input['payment_id']??'')));}
if($method==='POST' && $route==='admin/refund'){owner();check(($input['confirmation']??'')==='REEMBOLSAR','Confirme a operação de reembolso.');json_response(refund_booking((int)$input['id']));}
if($method==='POST' && $route==='admin/user'){
 owner();$id=(int)($input['id']??0);check($id!==((int)$_SESSION['uid']),'Você não pode alterar a própria permissão.');check(in_array($input['role']??'',['customer','staff','admin'],true),'Permissão inválida.');query('UPDATE users SET role=? WHERE id=?',[$input['role'],$id]);audit('user.role_changed',['id'=>$id,'role'=>$input['role']]);json_response(['ok'=>true]);
}
fail('Operação administrativa não encontrada.',404);
