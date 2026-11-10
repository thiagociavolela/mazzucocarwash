<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli') exit;
try {
 $name=env('DB_NAME','mazzuco_carwash'); if(!preg_match('/^[a-zA-Z0-9_]+$/',$name))throw new RuntimeException('Nome de banco inválido.');
 try { db(); } catch (PDOException $connectionError) {
  // Existing databases must work with a user that cannot CREATE DATABASE.
  if ((int)($connectionError->errorInfo[1]??0)!==1049) throw $connectionError;
  $pdo=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306'),env('DB_USER','root'),env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 }
 $sql=file_get_contents(ROOT.'/database/schema.sql'); foreach(explode(';',$sql) as $statement) if(trim($statement))db()->exec($statement);
 require ROOT.'/database/seed.php'; seed(); echo "Estrutura e catálogo instalados em MySQL/MariaDB.\n";
}catch(Throwable $e){fwrite(STDERR,"Não foi possível instalar. Verifique a conexão MySQL e as permissões. Código: ".$e->getCode()."\n");exit(1);}
