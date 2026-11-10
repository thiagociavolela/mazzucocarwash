<?php
declare(strict_types=1);
// Raster variants of the existing code-native brand-emblem (no generated artwork).
if(PHP_SAPI!=='cli')exit;
$directory=dirname(__DIR__).'/public/assets/icons';
if(!is_dir($directory))mkdir($directory,0755,true);
$size=1024;$image=imagecreatetruecolor($size,$size);
$bg=imagecolorallocate($image,7,11,16);imagefill($image,0,0,$bg);
// Badge gradient and skew match the SVG emblem; draw at 2x for antialiasing.
for($y=224;$y<=800;$y++){
 $t=($y-224)/576;$shift=(int)round(-tan(deg2rad(8))*($y-512));
 $inset=0;$r=136;if($y<360)$inset=(int)round($r-sqrt(max(0,$r*$r-($y-360)**2)));elseif($y>664)$inset=(int)round($r-sqrt(max(0,$r*$r-($y-664)**2)));
 $color=imagecolorallocate($image,(int)round(16-6*$t),(int)round(55-34*$t),(int)round(72-42*$t));
 imageline($image,224+$shift+$inset,$y,800+$shift-$inset,$y,$color);
}
$cyan=imagecolorallocate($image,44,222,242);$scale=24;$origin=224;$width=40;
$line=function(array $points)use($image,$cyan,$scale,$origin,$width):void{imagesetthickness($image,$width);for($i=1;$i<count($points);$i++){[$x1,$y1]=$points[$i-1];[$x2,$y2]=$points[$i];imageline($image,(int)($origin+$x1*$scale),(int)($origin+$y1*$scale),(int)($origin+$x2*$scale),(int)($origin+$y2*$scale),$cyan);}foreach($points as [$x,$y])imagefilledellipse($image,(int)($origin+$x*$scale),(int)($origin+$y*$scale),$width,$width,$cyan);};
foreach([[[5,7],[7,4],[17,4],[19,7],[21,11],[21,18],[3,18],[3,11],[5,7]],[[5,7],[19,7]],[[3,12],[21,12]],[[6,18],[6,21]],[[18,18],[18,21]],[[6,15],[8,15]],[[16,15],[18,15]]] as $points)$line($points);
foreach(['app-192.png'=>192,'app-512.png'=>512,'app-maskable-512.png'=>512,'apple-touch-icon.png'=>180] as $name=>$dimension){$output=imagecreatetruecolor($dimension,$dimension);imagecopyresampled($output,$image,0,0,0,0,$dimension,$dimension,$size,$size);imagepng($output,$directory.'/'.$name);imagedestroy($output);echo "$name criado.\n";}
imagedestroy($image);
