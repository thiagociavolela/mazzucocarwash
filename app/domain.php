<?php
declare(strict_types=1);
const VEHICLE_TYPES=['carro','suv','picape','moto','caminhao','aeronave'];
function check(bool $ok,string $message): void { if(!$ok)throw new DomainException($message); }
function valid_date(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d && $d->format('Y-m-d')===$date; }
function live_promotions(?int $uid=null): array {
 $promotions=rows('SELECT * FROM promotions WHERE active=1 AND starts_at<=NOW() AND ends_at>NOW() ORDER BY id');
 return array_values(array_filter($promotions,function($p)use($uid){
  $condition="promotion_id=? AND status NOT IN ('cancelled','expired') AND (status<>'reserved' OR expires_at>NOW())";
  if($p['max_uses']!==null && (int)query('SELECT COUNT(*) FROM bookings WHERE '.$condition,[$p['id']])->fetchColumn()>=(int)$p['max_uses'])return false;
  if($uid && (int)query('SELECT COUNT(*) FROM bookings WHERE '.$condition.' AND user_id=?',[$p['id'],$uid])->fetchColumn()>=(int)$p['per_user_limit'])return false;
  return true;
 }));
}
function service_price(array $service,string $type): ?int {
 if($service['mode']==='quote')return null;
 if($service['mode']==='vehicle'){ $r=one('SELECT price_cents FROM service_prices WHERE service_id=? AND vehicle_type=?',[$service['id'],$type]);return $r?(int)$r['price_cents']:null; }
 return $service['price_cents']!==null?(int)$service['price_cents']:null;
}
function calculate_discount(int $subtotal,array $items,array $promo): int {
 if($subtotal<(int)$promo['min_cents'])return 0;
 $ids=json_decode($promo['service_ids'],true);$eligible=0;
 foreach($items as $i)if(!$ids || in_array((int)$i['id'],array_map('intval',$ids),true))$eligible+=(int)$i['unit_cents'];
 return min($eligible,$promo['type']==='percent'?intdiv($eligible*(int)$promo['value'],100):(int)$promo['value']);
}
function price_order(array $ids,string $type,string $code='',?int $uid=null,bool $pickup=false,string $region=''): array {
 check(in_array($type,VEHICLE_TYPES,true),'Selecione um tipo de veículo válido.');
 $ids=array_values(array_unique(array_map('intval',$ids)));check(count($ids)>0 && count($ids)<=10,'Escolha de 1 a 10 serviços.');
 $items=[];$subtotal=0;$duration=0;
 foreach($ids as $id){$s=one('SELECT * FROM services WHERE id=? AND active=1',[$id]);check((bool)$s,'Serviço indisponível.');check(in_array($type,json_decode($s['vehicle_types'],true),true),'Serviço incompatível com o veículo.');$price=service_price($s,$type);check($price!==null && $price>0,'Este serviço precisa de orçamento antes do agendamento.');$s['unit_cents']=$price;$items[]=$s;$subtotal+=$price;$duration+=(int)$s['duration'];}
 $best=null;$discount=0;$code=strtoupper(trim($code));
 foreach(live_promotions($uid) as $p){
   if($code!=='' ? strtoupper($p['code']??'')!==$code : !empty($p['code']))continue;
   $uses=(int)query("SELECT COUNT(*) FROM bookings WHERE promotion_id=? AND status NOT IN ('cancelled','expired')",[$p['id']])->fetchColumn();
   if($p['max_uses']!==null && $uses>=(int)$p['max_uses'])continue;
   if($uid && (int)query("SELECT COUNT(*) FROM bookings WHERE promotion_id=? AND user_id=? AND status NOT IN ('cancelled','expired')",[$p['id'],$uid])->fetchColumn()>=(int)$p['per_user_limit'])continue;
   $d=calculate_discount($subtotal,$items,$p);if($d>$discount){$discount=$d;$best=$p;}
 }
 check($code==='' || $best!==null,'Cupom inválido, esgotado ou não aplicável.');
 $pickupFee=0;
 if($pickup){check((bool)setting('pickup_enabled',false),'Leva e traz indisponível.');check(in_array($region,setting('pickup_regions',[]),true),'Região fora da área de atendimento.');check(in_array($type,['carro','suv','picape'],true),'Leva e traz disponível apenas para automóveis.');$pickupFee=(int)setting('pickup_cents',0);}
 $total=$subtotal-$discount+$pickupFee;check($total>0,'O valor do pedido precisa ser maior que zero.');
 return ['items'=>$items,'subtotal_cents'=>$subtotal,'discount_cents'=>$discount,'pickup_cents'=>$pickupFee,'total_cents'=>$total,'duration'=>$duration,'promotion_id'=>$best['id']??null,'promotion_name'=>$best['name']??null];
}
function available_resource(int $resourceId,string $start,string $end): bool {
 $b=one("SELECT id FROM bookings WHERE resource_id=? AND starts_at<? AND ends_at>? AND (status IN ('confirmed','in_progress','ready') OR (status='reserved' AND expires_at>NOW())) LIMIT 1",[$resourceId,$end,$start]);
 if($b)return false;
 return !one('SELECT id FROM blocks WHERE (resource_id IS NULL OR resource_id=?) AND starts_at<? AND ends_at>? LIMIT 1',[$resourceId,$end,$start]);
}
function valid_window(string $start,int $duration,bool $pickup=false): ?string {
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start);if(!$dt || $dt->format('Y-m-d H:i:s')!==$start)return null;
 $advance=(int)setting('advance_hours',4);if($pickup)$advance=max($advance,(int)setting('pickup_advance_hours',24));
 if($dt<new DateTimeImmutable('+'.$advance.' hours') || $dt>new DateTimeImmutable('+'.(int)setting('booking_horizon_days',60).' days'))return null;
 $day=one('SELECT * FROM schedule_days WHERE weekday=? AND active=1',[(int)$dt->format('w')]);if(!$day)return null;
 $end=$dt->modify('+'.$duration.' minutes');$date=$dt->format('Y-m-d');
 if($dt<new DateTimeImmutable($date.' '.$day['opens']) || $end>new DateTimeImmutable($date.' '.$day['closes']))return null;
 if($day['break_start'] && $day['break_end'] && $dt<new DateTimeImmutable($date.' '.$day['break_end']) && $end>new DateTimeImmutable($date.' '.$day['break_start']))return null;
 return $end->format('Y-m-d H:i:s');
}
function slots(string $date,int $duration,string $type,bool $pickup=false): array {
 check(valid_date($date),'Data inválida.');$resources=rows('SELECT * FROM resources WHERE active=1');$result=[];
 for($m=0;$m<1440;$m+=30){$start=$date.' '.sprintf('%02d:%02d:00',intdiv($m,60),$m%60);$end=valid_window($start,$duration,$pickup);if(!$end)continue;
 foreach($resources as $r){if(in_array($type,json_decode($r['vehicle_types'],true),true) && available_resource((int)$r['id'],$start,$end)){$result[]=substr($start,11,5);break;}}}
 return $result;
}
function expire_reservations(): void { query("UPDATE bookings SET status='expired' WHERE status='reserved' AND expires_at<=NOW()"); }
function reserve(array $data,array $u): array {
 check(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:00$/',$data['starts_at']??'')===1,'Escolha uma data e um horário.');
 return transaction(function()use($data,$u){
   // All reservations and promotion allocations use the same ordered resource locks.
   $resources=rows('SELECT * FROM resources WHERE active=1 ORDER BY id FOR UPDATE');
   expire_reservations();$type=$data['vehicle_type']??'';$pickup=(bool)($data['pickup']??false);
   $price=price_order($data['services']??[],$type,$data['coupon']??'',(int)$u['id'],$pickup,$data['region']??'');
   $end=valid_window($data['starts_at'],$price['duration'],$pickup);check($end!==null,'Horário fora da disponibilidade.');
   $resource=null;foreach($resources as $r)if(in_array($type,json_decode($r['vehicle_types'],true),true)&&available_resource((int)$r['id'],$data['starts_at'],$end)){$resource=$r;break;}
   check($resource!==null,'Este horário acabou de ser ocupado. Escolha outro.');
   $model=trim($data['vehicle_model']??'');check(mb_strlen($model)>=2 && mb_strlen($model)<=100,'Informe o modelo do veículo.');$plate=mb_strtoupper(trim($data['plate']??''));check(mb_strlen($plate)<=15,'Placa inválida.');
   $pickupInfo=null;if($pickup){check(mb_strlen(trim($data['pickup_address']??''))>=10 && mb_strlen(trim($data['delivery_address']??''))>=10,'Informe os endereços completos de retirada e entrega.');$pickupInfo=json_encode(['region'=>$data['region'],'pickup'=>mb_substr(trim($data['pickup_address']),0,500),'delivery'=>mb_substr(trim($data['delivery_address']),0,500)],JSON_UNESCAPED_UNICODE);}
   query('INSERT INTO vehicles(user_id,type,model,plate) VALUES(?,?,?,?)',[$u['id'],$type,$model,$plate]);$vehicle=db()->lastInsertId();$ref=bin2hex(random_bytes(12));
   $expiry=(new DateTimeImmutable('+'.(int)setting('reservation_minutes',20).' minutes'))->format('Y-m-d H:i:s');
   query('INSERT INTO bookings(reference,user_id,vehicle_id,resource_id,starts_at,ends_at,expires_at,subtotal_cents,discount_cents,pickup_cents,total_cents,promotion_id,pickup,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$ref,$u['id'],$vehicle,$resource['id'],$data['starts_at'],$end,$expiry,$price['subtotal_cents'],$price['discount_cents'],$price['pickup_cents'],$price['total_cents'],$price['promotion_id'],$pickupInfo,mb_substr(trim($data['notes']??''),0,1000)]);
   $id=(int)db()->lastInsertId();foreach($price['items'] as $s)query('INSERT INTO booking_items(booking_id,service_id,name,price_cents,duration) VALUES(?,?,?,?,?)',[$id,$s['id'],$s['name'],$s['unit_cents'],$s['duration']]);
   query('INSERT INTO payments(booking_id,amount_cents) VALUES(?,?)',[$id,$price['total_cents']]);audit('booking.reserved',['id'=>$id]);
   return one('SELECT * FROM bookings WHERE id=?',[$id]);
 });
}
function update_booking_status(int $id,string $status): void {
 transaction(function()use($id,$status){rows('SELECT id FROM resources ORDER BY id FOR UPDATE');$b=one('SELECT * FROM bookings WHERE id=? FOR UPDATE',[$id]);check((bool)$b,'Agendamento não encontrado.');
 $transitions=['confirmed'=>['in_progress','cancelled'],'in_progress'=>['ready','cancelled'],'ready'=>['completed'],'reserved'=>['cancelled'],'review'=>['cancelled']];
 check(in_array($status,$transitions[$b['status']]??[],true),'Transição de atendimento inválida. Confirmações dependem do pagamento.');
 query('UPDATE bookings SET status=? WHERE id=?',[$status,$id]);audit('booking.status',['id'=>$id,'status'=>$status]);});
}
function reschedule_booking(int $id,string $start): void {
 transaction(function()use($id,$start){rows('SELECT id FROM resources ORDER BY id FOR UPDATE');$b=one('SELECT b.*,v.type FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.id=? FOR UPDATE',[$id]);check($b && $b['status']==='confirmed','Somente atendimentos confirmados podem ser reagendados.');
 $duration=(int)query('SELECT SUM(duration) FROM booking_items WHERE booking_id=?',[$id])->fetchColumn();$end=valid_window($start,$duration,(bool)$b['pickup']);check($end!==null,'Novo horário fora da disponibilidade.');
 // Temporarily remove this booking inside the transaction so it does not conflict with itself.
 query("UPDATE bookings SET status='review' WHERE id=?",[$id]);$resource=null;foreach(rows('SELECT * FROM resources WHERE active=1 ORDER BY id') as $r)if(in_array($b['type'],json_decode($r['vehicle_types'],true),true)&&available_resource((int)$r['id'],$start,$end)){$resource=$r['id'];break;}
 check($resource!==null,'Novo horário indisponível.');query("UPDATE bookings SET starts_at=?,ends_at=?,resource_id=?,status='confirmed' WHERE id=?",[$start,$end,$resource,$id]);audit('booking.rescheduled',['id'=>$id,'starts_at'=>$start]);
 });
}
