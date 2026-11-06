<?php
/*
Name: CAPTCHA - simple math
Desc: Adds a "Math question" CAPTCHA provider you can select under Site settings. Shows how custom or minigame CAPTCHAs plug in with reg_captcha().
*/
reg_captcha('math','Math question',function($ctx){
 $a=random_int(1,9);$b=random_int(1,9);$_SESSION['cap_'.$ctx]=(string)($a+$b);
 return '<span>'.$a.' + '.$b.' = </span><input type="text" name="cap" size="3" maxlength="3" autocomplete="off" inputmode="numeric" required>';
},fn($ctx,$in)=>($_SESSION['cap_'.$ctx]??'')!==''&&trim($in)===$_SESSION['cap_'.$ctx]);
