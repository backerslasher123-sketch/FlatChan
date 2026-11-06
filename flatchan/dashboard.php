<?php
require __DIR__.'/lib.php';
$u=me();if(!$u)go('login.php');
$tabs=['home'=>['Overview','janitor'],'news'=>['News approval','janitor'],'reports'=>['Reports','mod'],'bans'=>['Bans','janitor'],'apps'=>['Applications','admin'],'users'=>['Users','admin'],'boards'=>['Boards','admin'],'site'=>['Site settings','admin'],'rules'=>['Rules editor','admin'],'themes'=>['Themes','admin'],'assets'=>['Assets & icons','admin'],'ext'=>['Extensions','admin'],'password'=>['My password','janitor']];
foreach($GLOBALS['FC']['tabs'] as $s=>$t)$tabs[$s]=[$t['label'],$t['min']];
$tab=(string)($_GET['tab']??'home');if(!isset($tabs[$tab]))$tab='home';
if(!empty($u['must_change']))$tab='password';
if(!can($tabs[$tab][1]))fail('You do not have permission to open this section.',403);
if($_SERVER['REQUEST_METHOD']==='POST'){chk();handle($tab,(string)($_POST['act']??''));go('dashboard.php?tab='.$tab);}

function F($tab,$in,$act,$multi=false,$cls=''){return '<form method="post" action="dashboard.php?tab='.$tab.'" class="'.$cls.'"'.($multi?' enctype="multipart/form-data"':'').'>'.csrff().'<input type="hidden" name="act" value="'.$act.'">'.$in.'</form>';}
function chkb($n,$v,$l){return '<label><input type="checkbox" name="'.$n.'" value="1"'.($v?' checked':'').'> '.$l.'</label>';}
function sel($n,$opts,$cur){$o='<select name="'.$n.'">';foreach($opts as $k=>$l)$o.='<option value="'.e($k).'"'.((string)$k===(string)$cur?' selected':'').'>'.e($l).'</option>';return $o.'</select>';}
function ask($m){return ' data-confirm="'.e($m).'"';}
function bak($t){return '<input type="hidden" name="back" value="dashboard.php?tab='.$t.'">';}

/* ================= POST handlers ================= */
function handle($tab,$act){
 global $u;$P=$_POST;
 if(!empty($u['must_change'])&&$tab!=='password')return;
 if(isset($GLOBALS['FC']['tabs'][$tab])){if($GLOBALS['FC']['tabs'][$tab]['post'])($GLOBALS['FC']['tabs'][$tab]['post'])($act);return;}
 switch($tab){
 case 'password':
  if(!password_verify((string)($P['old']??''),$u['hash']))return flash('Current password is wrong.');
  $n=(string)($P['new']??'');
  if($n!==(string)($P['new2']??''))return flash('The new passwords do not match.');
  if(strlen($n)<8||strtolower($n)==='password'||strcasecmp($n,$u['name'])===0)return flash('Choose a stronger password (8+ characters, not "password" or your username).');
  dbu('users',function(&$d)use($u,$n){foreach($d as &$x)if($x['id']==$u['id']){$x['hash']=password_hash($n,PASSWORD_DEFAULT);$x['must_change']=false;}});
  session_regenerate_id(true);flash('Password changed.');break;
 case 'boards':
  $uri=strtolower(trim((string)($P['uri']??'')));
  if($act==='bnew'){
   if(!preg_match('/^[a-z0-9]{1,16}$/',$uri)||in_array($uri,['admin','dashboard','login','uploads','thumbs','data','assets','themes','extensions','index','board'],true))return flash('Board URI must be 1-16 lowercase letters/digits (and not a reserved word).');
   if(isset(boards()[$uri]))return flash('That board already exists.');
   dbu('boards',function(&$d)use($uri,$P){$d[$uri]=['name'=>trim(sub((string)($P['name']??''),40))?:$uri,'desc'=>trim(sub((string)($P['desc']??''),200)),'vis'=>'public','locked'=>0,'max_threads'=>100,'archive'=>1,'need_file'=>0,'bump_limit'=>300];});
   dbw('b_'.$uri,['next'=>1,'threads'=>[]]);log_act("board /$uri/ created");flash("Board /$uri/ created.");
  }elseif($act==='bsave'&&isset(boards()[$uri])){
   dbu('boards',function(&$d)use($uri,$P){$d[$uri]=['name'=>trim(sub((string)$P['name'],40))?:$uri,'desc'=>trim(sub((string)$P['desc'],200)),'vis'=>in_array($P['vis']??'',['public','unlisted','staff'],true)?$P['vis']:'public','locked'=>!empty($P['locked'])?1:0,'max_threads'=>max(5,min(1000,(int)$P['max_threads'])),'archive'=>!empty($P['archive'])?1:0,'need_file'=>!empty($P['need_file'])?1:0,'bump_limit'=>max(10,min(5000,(int)$P['bump_limit']))];});
   flash("Board /$uri/ saved.");
  }elseif($act==='bdel'&&isset(boards()[$uri])){
   $D=db('b_'.$uri,['threads'=>[]]);foreach($D['threads'] as $t){unlinks(pfiles($t));foreach($t['replies'] as $r)unlinks(pfiles($r));}
   @unlink(DATA.'/b_'.$uri.'.json.php');dbu('boards',function(&$d)use($uri){unset($d[$uri]);});log_act("board /$uri/ deleted");flash("Board /$uri/ and all its posts were deleted.");}
  break;
 case 'site':
  $F=[];foreach(['name','email','subject','file','spoiler','password'] as $k)$F[$k]=!empty($P['f'][$k])?1:0;
  $caps=$GLOBALS['FC']['caps'];$ct=isset($caps[$P['captcha_type']??''])?$P['captcha_type']:'text';$cl=isset($caps[$P['captcha_login']??''])?$P['captcha_login']:'game';
  setcfg(['site_name'=>trim(sub((string)$P['site_name'],60))?:'FlatChan','site_desc'=>trim(sub((string)$P['site_desc'],200)),'about'=>trim(sub((string)$P['about'],5000)),'news_board'=>preg_replace('/[^a-z0-9]/','',strtolower((string)$P['news_board'])),
   'captcha'=>!empty($P['captcha'])?1:0,'captcha_type'=>$ct,'captcha_login'=>$cl,'fields'=>$F,'max_file_mb'=>max(1,min(64,(int)$P['max_file_mb'])),'post_gap'=>max(0,min(600,(int)$P['post_gap'])),'thread_gap'=>max(0,min(3600,(int)$P['thread_gap'])),
   'max_body'=>max(100,min(20000,(int)$P['max_body'])),'embeds'=>!empty($P['embeds'])?1:0,'bg_image'=>preg_match('~^(https?://|assets/)[^\s\'"()]+$~',(string)$P['bg_image'])?$P['bg_image']:'','proxy_header'=>preg_replace('/[^A-Za-z0-9-]/','',(string)$P['proxy_header']),'tz'=>in_array($P['tz']??'',timezone_identifiers_list(),true)?$P['tz']:'UTC']);
  log_act('site settings saved');flash('Settings saved. The message body field is always enabled.');break;
 case 'rules':setcfg(['rules'=>clean_html((string)($P['rules']??''))]);log_act('rules edited');flash('Rules saved.');break;
 case 'themes':
  $s=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($P['slug']??'')));$dir=ROOT.'/themes/';
  if($act==='tdefault'&&is_file($dir.$s.'.css')){setcfg(['theme'=>$s,'themes_off'=>array_values(array_diff((array)cfg('themes_off'),[$s]))]);flash('Default theme set.');}
  elseif($act==='ttoggle'&&is_file($dir.$s.'.css')){if($s===cfg('theme'))return flash('The default theme cannot be disabled.');$o=(array)cfg('themes_off');setcfg(['themes_off'=>in_array($s,$o,true)?array_values(array_diff($o,[$s])):array_merge($o,[$s])]);flash('Theme visibility changed.');}
  elseif($act==='tdel'&&is_file($dir.$s.'.css')){if($s===cfg('theme'))return flash('Set another default theme before removing this one.');if(count(themes(true))<2)return flash('Keep at least one theme.');@unlink($dir.$s.'.css');flash('Theme removed.');}
  elseif($act==='tnew'||$act==='tup'){
   $name=trim(sub((string)($P['tname']??''),40));$css='';
   if($act==='tup'){$f=$_FILES['cssfile']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>200000||strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='css')return flash('Upload a .css file up to 200 KB.');$css=(string)file_get_contents($f['tmp_name']);if($name==='')$name=pathinfo($f['name'],PATHINFO_FILENAME);}
   else $css=(string)($P['css']??'');
   $slug=trim(preg_replace('/[^a-z0-9_-]/','-',strtolower($name)),'-');if($slug===''||$css===''||strlen($css)>200000)return flash('A theme needs a name and CSS (max 200 KB).');
   $css=str_ireplace(['</style','<?php','<?'],'',$css);if(!preg_match('~^/\*\s*.+?\*/~',$css))$css='/* '.str_replace('*/','',$name)." */\n".$css;
   file_put_contents($dir.$slug.'.css',$css);log_act("theme $slug installed");flash("Theme '$slug' installed.");}
  break;
 case 'assets':
  $kinds=['banners'=>['assets/banners',0],'banimgs'=>['assets/banimgs',0],'reactions'=>['assets/reactions',64]];$k=(string)($P['kind']??'');
  if($act==='aup'&&isset($kinds[$k])){[$dir,$mx]=$kinds[$k];$name=$k==='reactions'?strtolower(preg_replace('/[^a-z0-9_]/','_',(string)($P['rname']??''))):null;if($k==='reactions'&&trim((string)$name,'_')==='')$name=null;
   [$fn,$er]=asset_up($dir,$_FILES['img']??[],$name,$mx);flash($er?:"Uploaded $fn.");}
  elseif($act==='adel'&&isset($kinds[$k])){$fn=basename((string)($P['file']??''));if(in_array($fn,files($kinds[$k][0],['png','jpg','jpeg','gif','webp','svg']),true)){@unlink(ROOT.'/'.$kinds[$k][0].'/'.$fn);flash('File deleted.');}}
  elseif($act==='iup'&&in_array($P['key']??'',['pinned','sticky','locked','archived'],true)){[$fn,$er]=asset_up('assets/icons',$_FILES['img']??[],$P['key'],32);if($er)flash($er);else{$i=cfg('icons');$i[$P['key']]='assets/icons/'.$fn;setcfg(['icons'=>$i]);flash('Icon updated.');}}
  elseif($act==='iclr'&&in_array($P['key']??'',['pinned','sticky','locked','archived'],true)){$i=cfg('icons');unset($i[$P['key']]);setcfg(['icons'=>$i]);flash('Icon reset to default.');}
  break;
 case 'users':
  $id=(int)($P['id']??0);
  if($act==='unew'){$n=trim((string)($P['name']??''));$r=in_array($P['role']??'',['janitor','mod','admin'],true)?$P['role']:'janitor';
   if(!preg_match('/^[A-Za-z0-9_]{3,20}$/',$n))return flash('Username: 3-20 letters, numbers or underscores.');foreach(db('users') as $x)if(strcasecmp($x['name'],$n)===0)return flash('That username is taken.');
   $pw=rand_pw();dbu('users',function(&$d)use($n,$r,$pw){$d[]=['id'=>(int)max(array_column($d,'id')?:[0])+1,'name'=>$n,'hash'=>password_hash($pw,PASSWORD_DEFAULT),'role'=>$r,'must_change'=>true,'created'=>time()];});
   log_act("user $n created ($r)");flash("Account created. Username: $n  Password: $pw  (shown once; the user must change it at first login)");}
  elseif($act==='urole'||$act==='upass'||$act==='udel'){
   if($id==$u['id'])return flash('You cannot modify your own account here. Use "My password" to change your password.');
   if($act==='urole'){$r=in_array($P['role']??'',['janitor','mod','admin'],true)?$P['role']:'janitor';dbu('users',function(&$d)use($id,$r){foreach($d as &$x)if($x['id']==$id)$x['role']=$r;});flash('Role updated.');}
   if($act==='upass'){$pw=rand_pw();dbu('users',function(&$d)use($id,$pw){foreach($d as &$x)if($x['id']==$id){$x['hash']=password_hash($pw,PASSWORD_DEFAULT);$x['must_change']=true;}});flash("New temporary password: $pw (shown once)");}
   if($act==='udel'){dbu('users',function(&$d)use($id){$d=array_values(array_filter($d,fn($x)=>$x['id']!=$id));});flash('User deleted.');}
   log_act("$act user #$id");}
  break;
 case 'apps':
  $id=(int)($P['id']??0);
  if($act==='aapprove'){$n=trim((string)($P['uname']??''));$hash='';
   if(!preg_match('/^[A-Za-z0-9_]{3,20}$/',$n))return flash('Invalid username.');foreach(db('users') as $x)if(strcasecmp($x['name'],$n)===0)return flash('That username is taken.');
   dbu('apps',function(&$d)use($id,$n,&$hash){foreach($d as &$a)if($a['id']==$id&&$a['status']==='pending'){$a['status']='approved';$a['uname']=$n;$hash=(string)($a['phash']??'');unset($a['phash']);}});
   if($hash==='')return flash('Application not found or already handled.');
   dbu('users',function(&$d)use($n,$hash){$d[]=['id'=>(int)max(array_column($d,'id')?:[0])+1,'name'=>$n,'hash'=>$hash,'role'=>'janitor','must_change'=>true,'created'=>time()];});
   log_act("application #$id approved as $n");flash("Janitor account '$n' created. Its random password was sealed when the application was sent; only the applicant can open it with their private code on the application page. You never see it.");}
  elseif($act==='areject'){dbu('apps',function(&$d)use($id,$P){foreach($d as &$a)if($a['id']==$id&&$a['status']==='pending'){$a['status']='rejected';$a['cred']='';unset($a['phash']);$a['note']=trim(sub((string)($P['note']??''),200));}});flash('Application rejected.');}
  elseif($act==='adelete'){dbu('apps',function(&$d)use($id){$d=array_values(array_filter($d,fn($a)=>$a['id']!=$id));});flash('Application removed.');}
  break;
 case 'bans':
  if($act==='bnew'){$ip=trim((string)($P['ip']??''));if(!filter_var($ip,FILTER_VALIDATE_IP)&&!preg_match('/^[0-9a-fA-F:.*?]{3,45}$/',$ip))return flash('Enter a valid IP (wildcards * allowed).');
   $b=strtolower(trim((string)($P['board']??'*')));$id=add_ban($ip,(string)($P['reason']??''),(float)($P['hrs']??0),$u['name'],isset(boards()[$b])?$b:'*');log_act("manual ban #$id $ip");flash("Ban #$id added.");}
  elseif($act==='unban'){$id=(int)($P['id']??0);dbu('bans',function(&$d)use($id){$d=array_values(array_filter($d,fn($x)=>$x['id']!=$id));});log_act("unban #$id");flash('Ban removed.');}
  break;
 case 'reports':
  $id=(int)($P['id']??0);$r=null;foreach(db('reports') as $x)if($x['id']==$id)$r=$x;if(!$r)return;
  if($act==='rres'||$act==='rdis'){$s=$act==='rres'?'resolved':'dismissed';dbu('reports',function(&$d)use($id,$s){foreach($d as &$x)if($x['id']==$id)$x['status']=$s;});flash("Report marked $s.");}
  if($act==='rdel'){if(isset(boards()[$r['board']])){$fl=[];dbu('b_'.$r['board'],function(&$D)use($r,&$fl){$fl=bdel($D,$r['post']);},['next'=>1,'threads'=>[]]);unlinks($fl);}
   dbu('reports',function(&$d)use($id){foreach($d as &$x)if($x['id']==$id)$x['status']='resolved';});flash('Post deleted and report resolved.');}
  if($act==='rban'&&can('janitor')){$bid=add_ban($r['pip'],'Reported: '.$r['cat'],(float)($P['hrs']??24),$u['name'],'*');dbu('reports',function(&$d)use($id){foreach($d as &$x)if($x['id']==$id)$x['status']='resolved';});flash("Poster banned (ban #$bid) and report resolved.");}
  log_act("report #$id $act");break;
 case 'ext':
  $on=[];$all=ext_list();foreach((array)($P['ext']??[]) as $x)if(isset($all[$x]))$on[]=$x;setcfg(['ext_on'=>$on]);flash('Extensions updated.');break;
 }
}

/* ================= views ================= */
function tbl($head,$rows){$o='<table class="tbl"><tr>';foreach($head as $h)$o.='<th>'.$h.'</th>';$o.='</tr>';foreach($rows as $r)$o.='<tr><td>'.implode('</td><td>',$r).'</td></tr>';if(!$rows)$o.='<tr><td colspan="'.count($head).'" class="center">Nothing here.</td></tr>';return $o.'</table>';}
function btn($tab,$act,$hid,$label,$extra=''){$h='';foreach($hid as $k=>$v)$h.='<input type="hidden" name="'.$k.'" value="'.e($v).'">';return F($tab,$h.$extra.'<button'.($act==='udel'||$act==='bdel'||$act==='tdel'||$act==='adel'||$act==='adelete'?ask('This cannot be undone. Continue?'):'').'>'.$label.'</button>',$act,false,'inl');}
function modpost($b,$id,$do,$label,$tab,$tid=0){return '<form class="inl" method="post" action="board.php">'.csrff().bak($tab).'<input type="hidden" name="act" value="mod"><input type="hidden" name="b" value="'.e($b).'"><input type="hidden" name="id" value="'.(int)$id.'"><input type="hidden" name="t" value="'.(int)$tid.'"><button name="do" value="'.$do.'">'.$label.'</button></form>';}

head('Dashboard');
echo '<h1 class="title">Dashboard</h1><div class="dash"><ul class="dnav">';
foreach($tabs as $s=>$t)if(can($t[1])&&(empty($u['must_change'])||$s==='password'))echo '<li'.($s===$tab?' class="on"':'').'><a href="dashboard.php?tab='.e($s).'">'.e($t[0]).'</a></li>';
echo '</ul><section class="dmain"><h2>'.e($tabs[$tab][0]).'</h2>';
if(!empty($u['must_change']))echo '<div class="box err"><b>You must choose a new password before using the dashboard.</b></div>';
if(isset($GLOBALS['FC']['tabs'][$tab])){($GLOBALS['FC']['tabs'][$tab]['render'])();}
else switch($tab){
case 'home':
 $nb=count(boards());$th=0;$po=0;foreach(boards() as $b){$D=db('b_'.$b['uri'],['threads'=>[]]);$th+=count($D['threads']);foreach($D['threads'] as $t)$po+=1+count($t['replies']);}
 $or=count(array_filter(db('reports'),fn($r)=>$r['status']==='open'));$pa=count(array_filter(db('apps'),fn($a)=>$a['status']==='pending'));
 echo '<p>Signed in as <b>'.e($u['name']).'</b> ('.e($u['role']).'). Role permissions: janitors delete posts, ban users, approve media and news; moderators also manage reports and thread flags; administrators manage everything.</p>';
 echo tbl(['Boards','Threads','Posts','Open reports','Pending applications','Active bans'],[[$nb,$th,$po,$or,$pa,count(array_filter(db('bans'),fn($b)=>!$b['exp']||$b['exp']>time()))]]);
 echo '<h3>Recent staff actions</h3>'.tbl(['Time','User','Action'],array_map(fn($l)=>[e(dt($l[0])),e($l[1]),e($l[2])],array_slice(db('log'),0,25)));break;
case 'news':
 $nb=cfg('news_board');$rows=[];$D=db('b_'.$nb,['threads'=>[]]);
 foreach($D['threads'] as $t)if(($t['news']??'')==='pending')$rows[]=['#'.(int)$t['id'].' <a href="board.php?b='.e($nb).'&amp;t='.(int)$t['id'].'">'.e($t['subject']!==''?$t['subject']:'(no subject)').'</a>',e($t['name']),e(sub($t['body'],120)),modpost($nb,$t['id'],'newsok','Approve','news').modpost($nb,$t['id'],'del','Delete','news')];
 echo '<p>Threads on /'.e($nb).'/ appear in the homepage news table only after approval.</p>'.tbl(['Thread','Poster','Excerpt','Actions'],$rows);break;
case 'reports':
 $rows=[];foreach(array_reverse(db('reports')) as $r){$op=$r['status']==='open';
  $rows[]=['#'.(int)$r['id'],'<a href="board.php?b='.e($r['board']).'&amp;t='.(int)$r['tid'].'#p'.(int)$r['post'].'">/'.e($r['board']).'/ No.'.(int)$r['post'].'</a><br><small>'.e(sub($r['excerpt'],100)).'</small>',e($r['cat']).'<br><small>'.e($r['reason']).'</small>',e(dt($r['time'])),e($r['status']),
   $op?btn('reports','rres',['id'=>$r['id']],'Resolve').btn('reports','rdis',['id'=>$r['id']],'Dismiss').btn('reports','rdel',['id'=>$r['id']],'Delete post').btn('reports','rban',['id'=>$r['id']],'Ban poster 24h',''):''];}
 echo tbl(['ID','Post','Reason','Reported','Status','Actions'],$rows);break;
case 'bans':
 echo F('bans','<input type="text" name="ip" placeholder="IP, e.g. 1.2.3.4 or 1.2.3.*" required> <input type="text" name="reason" placeholder="Reason" maxlength="200"> '.sel('hrs',['1'=>'1 hour','24'=>'1 day','168'=>'1 week','720'=>'30 days','0'=>'Permanent'],24).' '.sel('board',['*'=>'All boards']+array_map(fn($b)=>'/'.$b['uri'].'/',boards()),'*').' <button>Add ban</button>','bnew',false,'box');
 $rows=[];foreach(db('bans') as $b){$ex=$b['exp']&&$b['exp']<time();$rows[]=['#'.(int)$b['id'],e($b['ip']),e($b['reason']),$b['board']==='*'?'All':'/'.e($b['board']).'/',e(dt($b['time'])),$b['exp']?e(dt($b['exp'])).($ex?' (expired)':''):'Never',e($b['by']),btn('bans','unban',['id'=>$b['id']],'Unban')];}
 echo tbl(['ID','IP','Reason','Board','Set','Expires','By',''],$rows);break;
case 'apps':
 $rows=[];foreach(array_reverse(db('apps')) as $a){$p=$a['status']==='pending';
  $rows[]=['#'.(int)$a['id'],e($a['name']),nl2br(e($a['reason'])),e(dt($a['time'])),e($a['status']),$p?btn('apps','aapprove',['id'=>$a['id']],'Approve & create account','<input type="text" name="uname" value="'.e($a['name']).'" size="12" maxlength="20"> ').btn('apps','areject',['id'=>$a['id']],'Reject','<input type="text" name="note" placeholder="Note (optional)" maxlength="200"> '):btn('apps','adelete',['id'=>$a['id']],'Remove')];}
 echo '<p>Approving creates the janitor account with a random password sealed for the applicant. They retrieve it once on the application page using their private code.</p>'.tbl(['ID','Name','Why','Sent','Status','Actions'],$rows);break;
case 'users':
 echo F('users','<input type="text" name="name" placeholder="Username" maxlength="20" required> '.sel('role',['janitor'=>'Janitor','mod'=>'Moderator','admin'=>'Administrator'],'janitor').' <button>Create account</button>','unew',false,'box');
 $rows=[];foreach(db('users') as $x){$me=$x['id']==$u['id'];$rows[]=['#'.(int)$x['id'],role_html($x['name'],$x['role']),e($x['role']).(!empty($x['must_change'])?' <small>(must change password)</small>':''),$me?'<i>you</i>':btn('users','urole',['id'=>$x['id']],'Set role',sel('role',['janitor'=>'Janitor','mod'=>'Moderator','admin'=>'Administrator'],$x['role']).' ').btn('users','upass',['id'=>$x['id']],'Reset password').btn('users','udel',['id'=>$x['id']],'Delete')];}
 echo tbl(['ID','User','Role','Actions'],$rows);break;
case 'boards':
 echo F('boards','<input type="text" name="uri" placeholder="uri (e.g. b)" maxlength="16" size="10" required> <input type="text" name="name" placeholder="Board name" maxlength="40"> <input type="text" name="desc" placeholder="Description" maxlength="200" size="30"> <button>Create board</button>','bnew',false,'box');
 foreach(boards() as $b){$h='<input type="hidden" name="uri" value="'.e($b['uri']).'"><table class="formtable"><tr><th>/'.e($b['uri']).'/</th><td><input type="text" name="name" value="'.e($b['name']).'" maxlength="40"> <input type="text" name="desc" value="'.e($b['desc']).'" maxlength="200" size="30"></td></tr>'
  .'<tr><th>Options</th><td>'.sel('vis',['public'=>'Public (listed)','unlisted'=>'Unlisted (link only)','staff'=>'Staff only'],$b['vis']).' '.chkb('locked',$b['locked'],'Locked').' '.chkb('archive',$b['archive'],'Archive pruned threads').' '.chkb('need_file',$b['need_file'],'File required for new threads').'</td></tr>'
  .'<tr><th>Limits</th><td>Max threads <input type="number" name="max_threads" value="'.(int)$b['max_threads'].'" min="5" max="1000"> Bump limit <input type="number" name="bump_limit" value="'.(int)$b['bump_limit'].'" min="10" max="5000"></td></tr></table><button>Save</button>';
  echo F('boards',$h,'bsave',false,'box').btn('boards','bdel',['uri'=>$b['uri']],'Delete /'.e($b['uri']).'/ and all posts');}
 break;
case 'site':
 $F=cfg('fields');$o='<table class="formtable"><tr><th>Site name</th><td><input name="site_name" value="'.e(cfg('site_name')).'" maxlength="60"></td></tr><tr><th>Description</th><td><input name="site_desc" value="'.e(cfg('site_desc')).'" maxlength="200" size="50"></td></tr>'
 .'<tr><th>About text</th><td><textarea name="about" rows="5" cols="60">'.e(cfg('about')).'</textarea></td></tr><tr><th>News board URI</th><td><input name="news_board" value="'.e(cfg('news_board')).'" maxlength="16" size="10"> <small>(create a board with this URI to enable the homepage news table)</small></td></tr>'
 .'<tr><th>Post fields</th><td>';foreach(['name'=>'Name','email'=>'Email','subject'=>'Subject','file'=>'File upload','spoiler'=>'Spoiler checkbox','password'=>'Deletion password'] as $k=>$l)$o.=chkb("f[$k]",!empty($F[$k]),$l).' ';
 $cl=array_map(fn($c)=>$c['label'],$GLOBALS['FC']['caps']);
 $o.='<br><small>The comment body is always enabled and cannot be disabled.</small></td></tr><tr><th>CAPTCHA</th><td>'.chkb('captcha',cfg('captcha'),'Enabled (post form and login form only)').'<br>Post form: '.sel('captcha_type',$cl,cfg('captcha_type')).' Login form: '.sel('captcha_login',$cl,cfg('captcha_login')).'</td></tr>'
 .'<tr><th>Limits</th><td>Max file <input type="number" name="max_file_mb" value="'.(int)cfg('max_file_mb').'" min="1" max="64"> MB &nbsp; Reply gap <input type="number" name="post_gap" value="'.(int)cfg('post_gap').'" min="0"> s &nbsp; Thread gap <input type="number" name="thread_gap" value="'.(int)cfg('thread_gap').'" min="0"> s &nbsp; Max comment <input type="number" name="max_body" value="'.(int)cfg('max_body').'" min="100"> bytes</td></tr>'
 .'<tr><th>Media</th><td>'.chkb('embeds',cfg('embeds'),'Embed YouTube/Vimeo/direct media links').'</td></tr><tr><th>Background image</th><td><input name="bg_image" value="'.e(cfg('bg_image')).'" size="50" placeholder="optional, e.g. assets/bg.png"></td></tr>'
 .'<tr><th>Time zone</th><td><input name="tz" value="'.e(cfg('tz')).'" size="20"> <small>e.g. UTC, America/Sao_Paulo</small></td></tr><tr><th>Proxy IP header</th><td><input name="proxy_header" value="'.e(cfg('proxy_header')).'" size="20"> <small>Leave empty unless behind a trusted proxy (e.g. CF-Connecting-IP)</small></td></tr></table><button>Save settings</button>';
 echo F('site',$o,'save',false,'box');break;
case 'rules':
 echo '<form method="post" action="dashboard.php?tab=rules" class="box" id="rulesform">'.csrff().'<input type="hidden" name="act" value="save"><div class="rte-bar">';
 foreach(['bold'=>'B','italic'=>'I','underline'=>'U','strikeThrough'=>'S','formatBlock:h2'=>'H2','formatBlock:h3'=>'H3','formatBlock:p'=>'P','insertUnorderedList'=>'• List','insertOrderedList'=>'1. List','insertHorizontalRule'=>'—','removeFormat'=>'Clear'] as $c=>$l)echo '<button type="button" data-cmd="'.$c.'">'.$l.'</button>';
 echo '<span class="rte-link"><input type="text" id="rte-url" placeholder="https://…" size="22"><button type="button" data-cmd="link">Link</button></span></div><div id="rte" class="rte rich" contenteditable="true">'.cfg('rules').'</div><textarea name="rules" id="rte-out" hidden></textarea><p><button>Save rules</button></p></form>';break;
case 'themes':
 $rows=[];foreach(themes(true) as $s=>$l){$off=in_array($s,(array)cfg('themes_off'),true);$d=$s===cfg('theme');
  $rows[]=[e($l).' <small>('.e($s).'.css)</small>',$d?'Default':($off?'Hidden':'Visible'),($d?'':btn('themes','tdefault',['slug'=>$s],'Make default')).($d?'':btn('themes','ttoggle',['slug'=>$s],$off?'Show':'Hide')).($d?'':btn('themes','tdel',['slug'=>$s],'Remove'))];}
 echo tbl(['Theme','Status',''],$rows).F('themes','<h3>Install from a .css file</h3><input type="text" name="tname" placeholder="Theme name"> <input type="file" name="cssfile" accept=".css" required> <button>Install</button>','tup',true,'box')
 .F('themes','<h3>Create a theme from CSS</h3><input type="text" name="tname" placeholder="Theme name" required><br><textarea name="css" rows="10" cols="70" placeholder="body{background:#fafcff}" required></textarea><br><button>Save theme</button>','tnew',false,'box');break;
case 'assets':
 foreach(['banners'=>['Banners (shown randomly at the top of home and board pages)','assets/banners'],'banimgs'=>['Ban page images (one picked at random)','assets/banimgs'],'reactions'=>['Reaction images (use as :name:, auto-resized to 64px)','assets/reactions']] as $k=>[$t,$dir]){
  echo '<div class="box"><h3>'.$t.'</h3>';$rows=[];foreach(files($dir,['png','jpg','jpeg','gif','webp','svg']) as $f)$rows[]=['<img class="react" style="max-height:48px" src="'.e("$dir/$f").'" alt="">',e($f).($k==='reactions'?' &rarr; <code>:'.e(pathinfo($f,PATHINFO_FILENAME)).':</code>':''),btn('assets','adel',['kind'=>$k,'file'=>$f],'Delete')];
  echo tbl(['Preview','File',''],$rows).F('assets','<input type="hidden" name="kind" value="'.$k.'">'.($k==='reactions'?'<input type="text" name="rname" placeholder="code name, e.g. smile" maxlength="32"> ':'').'<input type="file" name="img" accept="image/*" required> <button>Upload</button>','aup',true).'</div>';}
 echo '<div class="box"><h3>Thread icons (pinned, sticky, locked, archived)</h3>';
 foreach(['pinned','sticky','locked','archived'] as $k)echo '<div class="icorow"><b>'.ucfirst($k).'</b> '.icon($k).' '.F('assets','<input type="hidden" name="key" value="'.$k.'"><input type="file" name="img" accept="image/*" required> <button>Upload icon</button>','iup',true,'inl').btn('assets','iclr',['key'=>$k],'Reset').'</div>';
 echo '</div>';break;
case 'ext':
 $on=(array)cfg('ext_on');$o='<table class="tbl"><tr><th>On</th><th>Extension</th><th>Description</th></tr>';
 foreach(ext_list() as $s=>[$n,$d])$o.='<tr><td><input type="checkbox" name="ext[]" value="'.e($s).'"'.(in_array($s,$on,true)?' checked':'').'></td><td>'.e($n).' <small>('.e($s).'.php)</small></td><td>'.e($d).'</td></tr>';
 echo F('ext',$o.'</table><button>Save</button>','save',false,'box').'<p>Drop a PHP file into <code>extensions/</code> with a <code>Name:</code> and <code>Desc:</code> header comment and it appears here. See README.md.</p>';break;
case 'password':
 echo F('password','<table class="formtable"><tr><th>Current password</th><td><input type="password" name="old" required autocomplete="current-password"></td></tr><tr><th>New password</th><td><input type="password" name="new" required minlength="8" autocomplete="new-password"></td></tr><tr><th>Repeat</th><td><input type="password" name="new2" required minlength="8" autocomplete="new-password"></td></tr></table><button>Change password</button>','save',false,'box');break;
}
echo '</section></div>';foot();
