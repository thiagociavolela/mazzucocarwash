<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
expire_reservations();
foreach(rows("SELECT DISTINCT provider_id FROM payments WHERE provider_id IS NOT NULL AND status IN ('pending','in_process','authorized')") as $p){try{reconcile_payment($p['provider_id']);echo "Pagamento reconciliado.\n";}catch(Throwable $e){fwrite(STDERR,"Falha na reconciliação; confira auditoria e configuração.\n");}}
