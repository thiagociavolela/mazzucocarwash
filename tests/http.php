<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='local')exit(1);
$base='http://127.0.0.1:8088';$cookie=tempnam(sys_get_temp_dir(),'mazzuco_http_');$count=0;
function http_test(string $path,?array $data=null,?string $csrf=null):array{global $base,$cookie;$ch=curl_init($base.$path);$headers=['Accept: application/json'];if($csrf)$headers[]='X-CSRF-Token: '.$csrf;curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>10]);if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$status,$body,json_decode($body,true)];}
function http_expect(bool $ok,string $name):void{global $count;if(!$ok)throw new RuntimeException($name);$count++;echo "OK · $name\n";}
try{
 foreach(['/','/admin','/assets/style.css','/assets/app.js','/assets/admin.js','/assets/vendor/bootstrap.min.css','/assets/fonts/manrope.woff2','/images/mazzucocarwash_logo.png'] as $path){[$code]=http_test($path);http_expect($code===200,'HTTP 200 '.$path);}
 foreach(['/.env','/storage/primeiro-acesso.txt','/database/schema.sql','/app/bootstrap.php'] as $path){[$code]=http_test($path);http_expect($code===404,'Arquivo privado bloqueado '.$path);}
 [$code,,$boot]=http_test('/api/bootstrap');http_expect($code===200 && count($boot['services'])===17,'Catálogo público real do MySQL');$csrf=$boot['csrf'];
 [$code]=http_test('/api/admin/data?section=dashboard');http_expect($code===401,'Administração exige autenticação');
 [$code]=http_test('/api/profile',['name'=>'Teste']);http_expect($code===419,'Alteração sem CSRF rejeitada');
 [$code]=http_test('/api/webhook?data.id=123',[]);http_expect($code===401,'Webhook sem assinatura rejeitado');
 $access=file_get_contents(ROOT.'/storage/primeiro-acesso.txt');preg_match('/E-mail: (.+)/u',$access,$m);$email=trim($m[1]);preg_match('/Senha exclusiva: (.+)/u',$access,$m);$pass=trim($m[1]);
 [$code,,$login]=http_test('/api/login',['email'=>$email,'password'=>$pass],$csrf);http_expect($code===200 && $login['user']['role']==='admin','Login administrativo');
 foreach(['dashboard','agenda','services','promotions','payments','customers','requests','content','settings','audit'] as $section){[$code,,$data]=http_test('/api/admin/data?section='.$section);http_expect($code===200 && !isset($data['error']),'Painel / '.$section);}
 [$code,,$payments]=http_test('/api/admin/data?section=payments');http_expect(!isset($payments['MP_ACCESS_TOKEN'])&&!isset($payments['MP_WEBHOOK_SECRET']),'Credenciais não expostas na resposta');
 [$code]=http_test('/api/logout',[],$csrf);http_expect($code===200,'Logout administrativo');
 echo "\n$count verificações HTTP aprovadas.\n";
}finally{if(is_file($cookie))unlink($cookie);}
