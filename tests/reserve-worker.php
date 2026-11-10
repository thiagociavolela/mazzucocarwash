<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(!preg_match('/^mazzuco_test_[a-f0-9]{10}$/',env('DB_NAME')))exit(3);
$data=json_decode(stream_get_contents(STDIN),true);
try{reserve($data['booking'],$data['user']);echo 'reserved';}catch(DomainException $e){echo 'conflict';}catch(Throwable $e){echo 'error';exit(2);}
