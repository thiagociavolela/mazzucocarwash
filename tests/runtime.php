<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Mazzuco\HttpContext;
$tests = [
 'HTTPS direto' => [true, ['HTTPS'=>'on'], ''],
 'HTTP local' => [false, ['HTTPS'=>'off'], ''],
 'HTTPS via proxy confiável' => [true, ['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_FORWARDED_PROTO'=>'https'], '127.0.0.1,::1'],
 'Cabeçalho forjado é ignorado' => [false, ['REMOTE_ADDR'=>'198.51.100.2','HTTP_X_FORWARDED_PROTO'=>'https'], '127.0.0.1'],
 'Proxy não configurado é ignorado' => [false, ['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_FORWARDED_PROTO'=>'https'], ''],
 'Protocolo ambíguo é rejeitado' => [false, ['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_FORWARDED_PROTO'=>'https,http'], '127.0.0.1'],
 'HTTPS via IPv6 local' => [true, ['REMOTE_ADDR'=>'::1','HTTP_X_FORWARDED_PROTO'=>'https'], '127.0.0.1,::1'],
];
foreach ($tests as $name=>[$expected,$server,$proxies]) {
 if(HttpContext::isHttps($server,$proxies)!==$expected)throw new RuntimeException($name);
 echo "OK · $name\n";
}
$parsed=Dotenv\Dotenv::parse("TEST_SECRET=\"senha com espacos # e =\"\nTEST_NAME=mazzuco\n");
if($parsed['TEST_SECRET']!=='senha com espacos # e =')throw new RuntimeException('Leitura de valores entre aspas');
echo "OK · Leitura .env com caracteres especiais\n";
$proxy=['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_REAL_IP'=>'198.51.100.20'];
if(HttpContext::clientIp($proxy,'127.0.0.1')!=='198.51.100.20')throw new RuntimeException('IP do cliente via proxy');
if(HttpContext::clientIp($proxy,'')!=='127.0.0.1')throw new RuntimeException('IP forjado');
echo "OK · IP do cliente aceito somente do proxy configurado\n";
