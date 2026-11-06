<?php
require __DIR__.'/lib.php';
$msg='';$code='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 chk();$do=(string)($_POST['act']??'');
 if($do==='apply'){
  if(!rate('apl',2,3600))fail('Too many applications from your connection. Try again later.',429);
  $n=trim((string)($_POST['name']??''));$why=trim((string)($_POST['reason']??''));
  if(!preg_match('/^[A-Za-z0-9_]{3,20}$/',$n))fail('Choose a username with 3-20 letters, numbers or underscores.');
  if(strlen($why)<40||strlen($why)>3000)fail('Please explain in 40 to 3000 characters why you want to be a janitor.');
  $code=bin2hex(random_bytes(6));$pw=rand_pw();$cred=base64_encode(xs($pw,hash('sha256','k|'.$code)));$ph=password_hash($pw,PASSWORD_DEFAULT);
  dbu('apps',function(&$d)use($n,$why,$code,$cred,$ph){$id=(int)max(array_column($d,'id')?:[0])+1;$d[]=['id'=>$id,'name'=>$n,'reason'=>$why,'time'=>time(),'status'=>'pending','ch'=>hash('sha256','v|'.$code),'cred'=>$cred,'phash'=>$ph,'ip'=>ip()];});
 }
 if($do==='check'){
  $c=trim((string)($_POST['code']??''));$ch=hash('sha256','v|'.$c);$found=null;
  dbu('apps',function(&$d)use($ch,$c,&$found){foreach($d as &$a)if(hash_equals($a['ch'],$ch)){$found=$a;
   if($a['status']==='approved'&&$a['cred']!==''){$found['pw']=xs((string)base64_decode($a['cred']),hash('sha256','k|'.$c));$a['cred']='';}
   break;}});
  if(!$found)$msg='<div class="box err">No application matches that code.</div>';
  else{$s=$found['status'];$msg='<div class="box"><h3>Application #'.(int)$found['id'].' &mdash; '.e(ucfirst($s)).'</h3>';
   if($s==='approved'){$msg.=isset($found['pw'])?'<p>Your janitor account is ready. <b>This is shown only once &mdash; write it down now.</b></p><p>Username: <code>'.e($found['uname']).'</code><br>Password: <code>'.e($found['pw']).'</code></p><p>You must change the password at your first login.</p>':'<p>Approved. The credentials were already delivered. If you lost them, ask an administrator to reset your password.</p>';}
   elseif($s==='rejected')$msg.='<p>Your application was not accepted.'.(!empty($found['note'])?' Note: '.e($found['note']):'').'</p>';else $msg.='<p>Your application is still waiting for review.</p>';$msg.='</div>';}
 }
}
head('Janitor application');
echo '<h1 class="title">Janitor application</h1>';
if($code)echo '<div class="box"><h3>Application sent</h3><p>Keep this private code. You need it to check your status and receive your login if you are accepted:</p><p class="big"><code>'.e($code).'</code></p></div>';
echo $msg.'<form class="box" method="post" action="apl.php">'.csrff().'<input type="hidden" name="act" value="apply"><h2>Apply to become a janitor</h2><p>Janitors delete rule-breaking posts, approve media and ban abusive users.</p><table class="formtable">'
.'<tr><th>Username wanted</th><td><input type="text" name="name" maxlength="20" required></td></tr><tr><th>Why you?</th><td><textarea name="reason" rows="7" cols="50" required placeholder="Experience, availability, why you would be a good janitor (min. 40 characters)"></textarea></td></tr></table><button>Send application</button></form>'
.'<form class="box" method="post" action="apl.php">'.csrff().'<input type="hidden" name="act" value="check"><h2>Check an application</h2><input type="text" name="code" maxlength="20" placeholder="Your private code" required> <button>Check status</button></form>';
foot();
