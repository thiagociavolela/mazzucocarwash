<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='local')exit;
if(one("SELECT id FROM users WHERE role='admin' LIMIT 1")){echo "Administrador existente preservado.\n";exit;}
$email='admin@mazzuco.local';$pass=bin2hex(random_bytes(12));
query("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin')",['Administrador local',$email,password_hash($pass,PASSWORD_DEFAULT)]);
file_put_contents(ROOT.'/storage/primeiro-acesso.txt',"ACESSO ADMINISTRATIVO LOCAL — MAZZUCO\n\nEndereço: http://127.0.0.1:8088/admin\nE-mail: ".$email."\nSenha exclusiva: ".$pass."\n\nEste arquivo é privado e está fora da pasta pública.\nCrie sua conta definitiva e atribua o perfil de administrador antes de publicar.\n");
echo "Administrador local criado. Consulte storage/primeiro-acesso.txt.\n";
