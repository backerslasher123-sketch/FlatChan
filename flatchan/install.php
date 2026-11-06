<?php
require __DIR__.'/lib.php';
$installed=is_file(DATA.'/settings.json.php');
$base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
if($_SERVER['REQUEST_METHOD']==='POST'){
 chk();
 if(($_POST['act']??'')==='remove'&&$installed){@unlink(__FILE__);go('index.php');}
 if(($_POST['act']??'')==='install'&&!$installed){
  foreach(['data','data/backup','uploads','thumbs','assets/banners','assets/banimgs','assets/reactions','assets/icons','assets/sprites'] as $d){if(!is_dir(ROOT.'/'.$d)&&!@mkdir(ROOT.'/'.$d,0755,true))fail("Cannot create folder $d. Make the project folder writable (chmod 755/775) and retry.");
   if(!is_writable(ROOT.'/'.$d))fail("Folder $d is not writable. Set its permissions to 755 or 775 and retry.");@file_put_contents(ROOT.'/'.$d.'/index.html','');}
  file_put_contents(DATA.'/.htaccess',"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
  if(!is_file(ROOT.'/.htaccess'))@file_put_contents(ROOT.'/.htaccess',"Options -Indexes\nErrorDocument 404 $base/404.php\n");
  $sv=['smile'=>'<circle cx="16" cy="16" r="14" fill="#ffd93b" stroke="#c9a100"/><circle cx="11" cy="13" r="2"/><circle cx="21" cy="13" r="2"/><path d="M8 19q8 9 16 0" fill="none" stroke="#000" stroke-width="2"/>',
   'sad'=>'<circle cx="16" cy="16" r="14" fill="#ffd93b" stroke="#c9a100"/><circle cx="11" cy="13" r="2"/><circle cx="21" cy="13" r="2"/><path d="M9 24q7-8 14 0" fill="none" stroke="#000" stroke-width="2"/>',
   'heart'=>'<path d="M16 28C4 19 3 10 9 6c4-2 7 0 7 3 0-3 3-5 7-3 6 4 5 13-7 22z" fill="#e0245e"/>'];
  foreach($sv as $n=>$p)file_put_contents(ROOT."/assets/reactions/$n.svg",'<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">'.$p.'</svg>');
  $nm=trim(sub((string)($_POST['site_name']??''),60))?:'FlatChan';
  dbw('users',[['id'=>1,'name'=>'admin','hash'=>password_hash('password',PASSWORD_DEFAULT),'role'=>'admin','must_change'=>true,'created'=>time()]]);
  dbw('boards',['b'=>['name'=>'Random','desc'=>'Anything goes (within the rules).','vis'=>'public','locked'=>0,'max_threads'=>100,'archive'=>1,'need_file'=>0,'bump_limit'=>300]]);
  dbw('b_b',['next'=>1,'threads'=>[]]);foreach(['bans','reports','apps','rate','log'] as $n)dbw($n,[]);
  dbw('settings',['site_name'=>$nm,'salt'=>bin2hex(random_bytes(16)),'installed'=>time()]);
  flash('Installation complete.');go('install.php?ok=1');
 }
}
head('Installer');
echo '<h1 class="title">FlatChan installer</h1>';
if($installed){
 echo '<div class="box"><h2>'.(isset($_GET['ok'])?'Installed successfully':'FlatChan is already installed').'</h2>';
 if(isset($_GET['ok']))echo '<p>A single board <b>/b/</b> and one administrator were created. There is no sample content.</p><p>Log in with username <code>admin</code> and password <code>password</code>. You will be forced to choose a new password immediately.</p><p><a href="login.php">[Go to login]</a></p>';
 echo '<p>For safety, delete the installer now:</p><form method="post" action="install.php">'.csrff().'<input type="hidden" name="act" value="remove"><button>Delete install.php</button></form></div>';
}else{
 $chk=[['PHP 8.1 or newer',PHP_VERSION_ID>=80100,PHP_VERSION],['Project folder writable',is_writable(ROOT),''],['JSON extension',function_exists('json_encode'),''],['GD (optional: thumbnails and reaction resizing)',function_exists('imagecreatetruecolor'),'Without GD, images are shown at full size scaled by CSS.']];
 echo '<div class="box"><table class="tbl"><tr><th>Check</th><th>Status</th></tr>';foreach($chk as $c)echo '<tr><td>'.e($c[0]).'</td><td>'.($c[1]?'OK':'<b>Missing</b>').' <small>'.e($c[2]).'</small></td></tr>';
 echo '</table><form method="post" action="install.php">'.csrff().'<input type="hidden" name="act" value="install"><p>Site name: <input type="text" name="site_name" value="FlatChan" maxlength="60"></p><button>Install FlatChan</button></form></div>';
}
foot();
