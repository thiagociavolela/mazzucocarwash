<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
define('ROOT', dirname(__DIR__));
if (!is_file(ROOT.'/vendor/autoload.php')) {
    if (PHP_SAPI !== 'cli') http_response_code(503);
    exit("Dependências não instaladas. Execute composer install.\n");
}
require_once ROOT.'/vendor/autoload.php';
// Preserve externally supplied environment variables, including test database overrides.
Dotenv\Dotenv::createUnsafeImmutable(ROOT)->safeLoad();
function env(string $key, string $default = ''): string { $v=getenv($key); return $v === false ? $default : $v; }
function db(): PDO {
    static $pdo;
    if (!$pdo) { $pdo = new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';dbname='.env('DB_NAME','mazzuco_carwash').';charset=utf8mb4',env('DB_USER','root'),env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); $pdo->exec("SET time_zone = ".$pdo->quote((new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('P'))); }
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($args); return $q; }
function rows(string $sql,array $args=[]): array { return query($sql,$args)->fetchAll(); }
function one(string $sql,array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function json_response($data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail(string $message,int $status=422): never { json_response(['error'=>$message],$status); }
function setting(string $key,$default='') { static $values; if ($values===null) { $values=[]; foreach(rows('SELECT * FROM settings') as $r) $values[$r['name']] = json_decode($r['value'],true); } return $values[$key] ?? $default; }
function user(): ?array { return isset($_SESSION['uid']) ? one('SELECT id,name,email,phone,role FROM users WHERE id=?',[$_SESSION['uid']]) : null; }
function require_user(bool $admin=false): array { $u=user(); if(!$u) fail('Entre na sua conta para continuar.',401); if($admin && !in_array($u['role'],['admin','staff'],true)) fail('Acesso restrito.',403); return $u; }
function owner(): array { $u=require_user(true); if($u['role']!=='admin') fail('Apenas administradores podem alterar esta configuração.',403); return $u; }
function audit(string $action,array $details=[]): void { query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$_SESSION['uid']??null,$action,json_encode($details,JSON_UNESCAPED_UNICODE)]); }
function transaction(callable $fn) { db()->beginTransaction(); try { $v=$fn(); db()->commit(); return $v; } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; } }
function money(int $cents): string { return 'R$ '.number_format($cents/100,2,',','.'); }
function secret_encrypt(string $text): string { $key=env('APP_KEY'); if(strlen($key)<32) throw new RuntimeException('Configure APP_KEY antes de salvar credenciais.'); $iv=random_bytes(12); $cipher=openssl_encrypt($text,'aes-256-gcm',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv,$tag); return base64_encode($iv.$tag.$cipher); }
function secret_decrypt(string $text): string { $raw=base64_decode($text,true); if(!$raw || strlen($raw)<29)return ''; return openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',env('APP_KEY'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)) ?: ''; }
function credential(string $name): string { $v=one('SELECT value FROM secrets WHERE name=?',[$name]); return $v ? secret_decrypt($v['value']) : env($name); }
if(PHP_SAPI!=='cli') {
    ini_set('display_errors','0');
    header('Cache-Control: no-store, private');
    session_name('mazzuco_session');
    $https = Mazzuco\HttpContext::isHttps($_SERVER,env('TRUSTED_PROXIES'));
    session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Lax','path'=>'/']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}
require_once __DIR__.'/domain.php';
require_once __DIR__.'/mercadopago.php';
