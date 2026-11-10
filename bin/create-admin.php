<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
echo "Nome: ";$name=trim(fgets(STDIN));echo "E-mail: ";$email=strtolower(trim(fgets(STDIN)));echo "Senha (mínimo 12 caracteres; entrada visível apenas neste terminal): ";$password=trim(fgets(STDIN));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||strlen($name)<2){fwrite(STDERR,"Dados inválidos.\n");exit(1);}
query('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),'admin']);echo "Administrador criado.\n";
