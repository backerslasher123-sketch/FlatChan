<?php
require __DIR__.'/lib.php';
$c=($_GET['c']??'')==='login'?'login':'post';$set='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$a='';
for($i=0;$i<5;$i++)$a.=$set[random_int(0,31)];
$_SESSION['cap_'.$c]=$a;
header('Content-Type: image/svg+xml');header('Cache-Control: no-store');
$s='<svg xmlns="http://www.w3.org/2000/svg" width="160" height="50" viewBox="0 0 160 50"><rect width="160" height="50" fill="#eef2ff"/>';
for($i=0;$i<6;$i++)$s.='<path d="M'.rand(0,160).' '.rand(0,50).' Q'.rand(0,160).' '.rand(0,50).' '.rand(0,160).' '.rand(0,50).'" stroke="hsl('.rand(0,360).',50%,55%)" fill="none" stroke-width="'.rand(1,2).'"/>';
for($i=0;$i<5;$i++){$x=15+$i*29+rand(-3,3);$y=rand(30,38);$r=rand(-25,25);
 $s.='<text x="'.$x.'" y="'.$y.'" font-family="Verdana,Arial,sans-serif" font-size="'.rand(26,32).'" font-weight="bold" fill="hsl('.rand(200,280).',60%,30%)" transform="rotate('.$r.' '.$x.' '.$y.')">'.$a[$i].'</text>';}
echo $s.'</svg>';
