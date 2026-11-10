<?php
require dirname(__DIR__).'/app/bootstrap.php';
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/') ?: '/';
if(str_starts_with($path,'/api/')){try{require ROOT.'/app/api.php';}catch(Throwable $ex){error_log('Mazzuco API initialization: '.get_class($ex));fail('Banco de dados indisponível. Configure e execute a instalação.',503);}exit;}
if($path==='/upload'){
 try{owner();check($_SERVER['REQUEST_METHOD']==='POST','Método inválido.');check(hash_equals($_SESSION['csrf'],$_POST['csrf']??''),'Sessão inválida.');$f=$_FILES['image']??null;check($f && $f['error']===UPLOAD_ERR_OK && $f['size']<=5*1024*1024,'Envie uma imagem de até 5 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];check(isset($types[$mime]),'Use JPG, PNG ou WebP.');$size=getimagesize($f['tmp_name']);check($size && $size[0]<=8000 && $size[1]<=8000,'Dimensões inválidas.');$source=imagecreatefromstring(file_get_contents($f['tmp_name']));check((bool)$source,'Imagem inválida.');$name=bin2hex(random_bytes(16)).'.webp';check(imagewebp($source,ROOT.'/public/uploads/'.$name,85),'Não foi possível salvar.');imagedestroy($source);audit('image.uploaded');json_response(['url'=>'/uploads/'.$name]);}catch(DomainException $e){fail($e->getMessage());}catch(Throwable $e){fail('Upload indisponível.',500);}
}
if(!in_array($path,['/','/admin'],true)){http_response_code(404);echo 'Página não encontrada. <a href="/">Voltar ao início</a>';exit;}
require ROOT.'/app/'.($path==='/admin'?'admin-view.php':'site-view.php');
