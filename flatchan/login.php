<?php
require __DIR__.'/lib.php';
if(isset($_GET['out'])){if(hash_equals(csrf(),(string)($_GET['csrf']??''))){$_SESSION=[];session_regenerate_id(true);}go('index.php');}
if(me())go('dashboard.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
 guard('login','login',8,600); /* same CSRF + rate-limit + CAPTCHA pipeline as the post form */
 $n=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');$f=null;
 foreach(db('users') as $u)if(strcasecmp($u['name'],$n)===0)$f=$u;
 if($f&&password_verify($p,$f['hash'])){session_regenerate_id(true);$_SESSION['uid']=$f['id'];$_SESSION['csrf']=bin2hex(random_bytes(16));unset($GLOBALS['S']);log_act('login');go('dashboard.php');}
 fail('Wrong username or password.');
}
head('Login');
echo banner().'<h1 class="title">Login</h1><form class="box narrow" method="post" action="login.php">'.csrff().'<table class="formtable">'
.'<tr><th>Username</th><td><input type="text" name="username" maxlength="40" autocomplete="username" required autofocus></td></tr>'
.'<tr><th>Password</th><td><input type="password" name="password" autocomplete="current-password" required></td></tr>'.cap_html('login')
.'</table><p><button type="submit">Continue</button></p></form>';
foot();
