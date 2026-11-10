<?php
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__);
if(is_file($root.'/.env')){echo "Configuração existente preservada.\n";exit;}
$text=file_get_contents($root.'/.env.example');$text=str_replace('replace-with-64-random-hex-characters',bin2hex(random_bytes(32)),$text);file_put_contents($root.'/.env',$text);echo "Arquivo local .env criado com chave exclusiva.\n";
