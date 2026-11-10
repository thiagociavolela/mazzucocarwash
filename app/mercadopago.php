<?php
declare(strict_types=1);
function mp_request(string $method,string $path,?array $body=null,?string $key=null): array {
 $token=credential('MP_ACCESS_TOKEN');check($token!=='','O pagamento está aguardando a configuração do Mercado Pago.');
 $curl=curl_init('https://api.mercadopago.com'.$path);
 $headers=['Authorization: Bearer '.$token,'Content-Type: application/json'];if($key)$headers[]='X-Idempotency-Key: '.$key;
 curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true]);
 if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body===[]?new stdClass():$body));$raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
 if($raw===false || $status<200 || $status>=300){audit('payment.api_failure',['status'=>$status,'method'=>$method,'path'=>preg_replace('~/\d+~','/[id]',$path)]);throw new RuntimeException('Não foi possível consultar o Mercado Pago. Tente novamente.');}
 $result=json_decode($raw,true);if(!is_array($result))throw new RuntimeException('Resposta inválida do provedor.');return $result;
}
function checkout(array $booking,array $u): string {
 return transaction(function()use($booking,$u){
 query('SELECT id FROM payments WHERE booking_id=? ORDER BY id FOR UPDATE',[$booking['id']]);
 $fresh=one('SELECT * FROM bookings WHERE id=?',[$booking['id']]);
 return create_checkout($fresh,$u);
 });
}
function create_checkout(array $booking,array $u): string {
 check($booking['status']==='reserved' && strtotime($booking['expires_at'])>time(),'Reserva expirada. Faça um novo agendamento.');
 $existing=one('SELECT * FROM payments WHERE booking_id=? ORDER BY id LIMIT 1',[$booking['id']]);if(!empty($existing['checkout_url']))return $existing['checkout_url'];
 $url=rtrim(env('APP_URL'),'/');check(str_starts_with($url,'https://'),'Configure uma URL pública HTTPS para ativar o checkout.');
 $pref=mp_request('POST','/checkout/preferences',[
  'items'=>[['id'=>$booking['reference'],'title'=>'Mazzuco CarWash · Serviços agendados','quantity'=>1,'currency_id'=>'BRL','unit_price'=>(int)$booking['total_cents']/100]],
  'payer'=>['name'=>$u['name'],'email'=>$u['email']], 'external_reference'=>$booking['reference'],
  'back_urls'=>['success'=>$url.'/?payment=success','pending'=>$url.'/?payment=pending','failure'=>$url.'/?payment=failure'],
  'auto_return'=>'approved','notification_url'=>$url.'/api/webhook',
  'expires'=>true,'expiration_date_from'=>(new DateTimeImmutable())->format(DateTimeInterface::ATOM),'expiration_date_to'=>(new DateTimeImmutable($booking['expires_at']))->format(DateTimeInterface::ATOM)
 ],'preference-'.$booking['reference']);
 $target=setting('mp_environment','sandbox')==='production'?($pref['init_point']??''):($pref['sandbox_init_point']??'');
 check((bool)filter_var($target,FILTER_VALIDATE_URL) && str_starts_with($target,'https://'),'Checkout indisponível no ambiente selecionado.');
 query('UPDATE payments SET preference_id=?,checkout_url=? WHERE id=?',[$pref['id'],$target,$existing['id']]);audit('payment.preference_created',['booking'=>$booking['id']]);return $target;
}
function verify_webhook(string $id,string $requestId,string $signature,string $secret): bool {
 if($secret==='' || $id==='' || $requestId==='')return false;$parts=[];foreach(explode(',',$signature)as $part){$p=explode('=',trim($part),2);if(count($p)===2)$parts[$p[0]]=$p[1];}
 if(empty($parts['ts'])||empty($parts['v1'])||!ctype_digit($parts['ts']))return false;
 $manifest='id:'.strtolower($id).';request-id:'.$requestId.';ts:'.$parts['ts'].';';
 return hash_equals(hash_hmac('sha256',$manifest,$secret),$parts['v1']);
}
function reconcile_payment(string $paymentId): array {
 check(preg_match('/^[0-9]+$/',$paymentId)===1,'Identificador de pagamento inválido.');$p=mp_request('GET','/v1/payments/'.rawurlencode($paymentId));
 return apply_verified_payment($p,$paymentId);
}
// Internal only: caller must obtain the payload directly from the authenticated provider API.
function apply_verified_payment(array $p,string $paymentId): array {
 return transaction(function()use($p,$paymentId){
  rows('SELECT id FROM resources ORDER BY id FOR UPDATE');
  $b=one('SELECT * FROM bookings WHERE reference=? FOR UPDATE',[$p['external_reference']??'']);check((bool)$b,'Pagamento sem pedido correspondente.');
  $expectedProduction=setting('mp_environment','sandbox')==='production';check(($p['live_mode']??null)===$expectedProduction,'Ambiente de pagamento divergente.');
  check(($p['currency_id']??'')==='BRL' && (int)round((float)($p['transaction_amount']??0)*100)===(int)$b['total_cents'],'Valor ou moeda de pagamento divergente.');
  $event=hash('sha256',$paymentId.'|'.($p['date_last_updated']??'').'|'.($p['status']??''));
  if(one('SELECT id FROM payment_events WHERE event_key=?',[$event]))return ['status'=>'duplicate'];
  $existing=one('SELECT * FROM payments WHERE provider_id=?',[$paymentId]);
  if(!$existing){$pending=one('SELECT * FROM payments WHERE booking_id=? AND provider_id IS NULL ORDER BY id LIMIT 1',[$b['id']]);if($pending){query('UPDATE payments SET provider_id=? WHERE id=?',[$paymentId,$pending['id']]);}else{query('INSERT INTO payments(booking_id,provider_id,amount_cents) VALUES(?,?,?)',[$b['id'],$paymentId,$b['total_cents']]);}}
  $status=$p['status']??'pending';$updated=$p['date_last_updated']??'';$date=$updated?(new DateTimeImmutable($updated))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'):date('Y-m-d H:i:s');
  if($existing && $existing['event_at'] && $date<$existing['event_at'])return ['status'=>'older_event'];
  query('UPDATE payments SET status=?,event_at=? WHERE provider_id=?',[$status,$date,$paymentId]);
  $bookingStatus=$b['status'];$financial=$b['payment_status'];
  if($status==='approved'){
   $otherApproved=(int)query("SELECT COUNT(*) FROM payments WHERE booking_id=? AND provider_id<>? AND status='approved'",[$b['id'],$paymentId])->fetchColumn();
   if($otherApproved>0){$bookingStatus='review';audit('payment.duplicate_approval',['booking'=>$b['id']]);}
   elseif($b['status']==='reserved'){$bookingStatus=strtotime($b['expires_at'])>time()?'confirmed':'review';}
   elseif(in_array($b['status'],['expired','cancelled'],true))$bookingStatus='review';
   $financial='approved';
  }elseif(in_array($status,['refunded','charged_back'],true)){$financial=$status;if(!in_array($bookingStatus,['completed','cancelled'],true))$bookingStatus='review';}
  elseif($financial!=='approved')$financial=$status;
  query('UPDATE bookings SET status=?,payment_status=? WHERE id=?',[$bookingStatus,$financial,$b['id']]);
  query('INSERT INTO payment_events(payment_id,event_key,result) VALUES(?,?,?)',[$paymentId,$event,$status]);audit('payment.reconciled',['booking'=>$b['id'],'status'=>$status]);return ['status'=>$status,'booking_status'=>$bookingStatus];
 });
}
function refund_booking(int $id): array {
 $b=one('SELECT * FROM bookings WHERE id=?',[$id]);check($b && $b['payment_status']==='approved','Não há pagamento aprovado para reembolso.');
 $payments=rows("SELECT * FROM payments WHERE booking_id=? AND status='approved'",[$id]);check(count($payments)===1,'Múltiplos pagamentos: reconcilie individualmente antes de reembolsar.');
 $p=$payments[0];$result=mp_request('POST','/v1/payments/'.$p['provider_id'].'/refunds',[], 'refund-'.$b['reference']);audit('payment.refund_requested',['booking'=>$id]);reconcile_payment($p['provider_id']);return ['ok'=>true,'refund_id'=>$result['id']??null];
}
