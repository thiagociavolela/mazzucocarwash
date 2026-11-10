<?php
declare(strict_types=1);
// Raster variants of the exact M used by the page favicon.
if(PHP_SAPI!=='cli')exit;
$directory=dirname(__DIR__).'/public/assets/icons';
if(!is_dir($directory))mkdir($directory,0755,true);
$svg=file_get_contents(dirname(__DIR__).'/public/assets/favicon.svg');
// Keep the raster source tied to the favicon; fail rather than silently diverge.
if(!str_contains($svg,'m13 44 7-25h8l4 14 7-14h10l-7 25h-8l4-14-7 14h-5l-3-14-4 14Z'))throw new RuntimeException('Atualize o polígono para corresponder ao novo favicon.');
$size=1024;$image=imagecreatetruecolor($size,$size);
imagefill($image,0,0,imagecolorallocate($image,7,11,16));
$points=[13,44,20,19,28,19,32,33,39,19,49,19,42,44,34,44,38,30,31,44,26,44,23,30,19,44];
imagefilledpolygon($image,array_map(fn($point)=>$point*16,$points),imagecolorallocate($image,0,217,245));
foreach(['app-192.png'=>192,'app-512.png'=>512,'app-maskable-512.png'=>512,'apple-touch-icon.png'=>180] as $name=>$dimension){$output=imagecreatetruecolor($dimension,$dimension);imagecopyresampled($output,$image,0,0,0,0,$dimension,$dimension,$size,$size);imagepng($output,$directory.'/'.$name);imagedestroy($output);echo "$name criado.\n";}
file_put_contents($directory.'/app-icon.svg',$svg);
imagedestroy($image);
