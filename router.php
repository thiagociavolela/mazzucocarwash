<?php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$pwaFiles=['/sw.js'=>'application/javascript; charset=utf-8','/manifest.webmanifest'=>'application/manifest+json; charset=utf-8','/offline.html'=>'text/html; charset=utf-8'];
if(isset($pwaFiles[$path])){header('Content-Type: '.$pwaFiles[$path]);header('Cache-Control: no-cache');if($path==='/sw.js')header('Service-Worker-Allowed: /');readfile(__DIR__.'/public'.$path);return true;}
if (preg_match('~^/(assets|images|uploads)/[a-zA-Z0-9_./-]+$~', $path) && !str_contains($path, '..')) {
    $file = __DIR__.'/public'.$path;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = ['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','woff2'=>'font/woff2'];
        if (isset($types[$ext])) { header('Content-Type: '.$types[$ext]); readfile($file); return true; }
    }
}
require __DIR__.'/public/index.php';
