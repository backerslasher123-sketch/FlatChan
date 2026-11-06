<?php
/* FlatChan core: storage, auth, security, rendering helpers and the extension API. */
if(PHP_VERSION_ID<80100){http_response_code(500);exit('FlatChan needs PHP 8.1 or newer.');}
define('ROOT',__DIR__);define('DATA',ROOT.'/data');
@ini_set('display_errors','0');
if(session_status()!==PHP_SESSION_ACTIVE){session_name('fchan');session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);session_start();}
$GLOBALS['FC']=['h'=>[],'pages'=>[],'caps'=>[],'tabs'=>[]];

/* ---------- storage: JSON files wrapped in a PHP guard line, locked, atomic, backed up ---------- */
function db($n,$def=[]){$f=DATA."/$n.json.php";if(!is_file($f))return $def;$s=(string)file_get_contents($f);$s=substr($s,(int)strpos($s,"\n")+1);$d=json_decode($s,true);return is_array($d)?$d:$def;}
function dbw($n,$d){
 $f=DATA."/$n.json.php";$t=$f.'.'.bin2hex(random_bytes(3)).'.tmp.php';
 $j=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
 if(file_put_contents($t,"<?php http_response_code(404);exit;?>\n".$j,LOCK_EX)===false)die('Storage error: the data/ folder is not writable.');
 if($n!=='rate'&&is_file($f)){@mkdir(DATA.'/backup',0755);@copy($f,DATA."/backup/$n.bak.php");$dy=DATA."/backup/$n-".date('Ymd').'.bak.php';
  if(!is_file($dy)){@copy($f,$dy);foreach(array_slice(glob(DATA."/backup/$n-*.bak.php")?:[],0,-7) as $o)@unlink($o);}}
 rename($t,$f);
}
function dbu($n,callable $fn,$def=[]){ /* locked read-modify-write; never nest calls */
 $l=fopen(DATA.'/.lock','c');flock($l,LOCK_EX);
 try{$d=db($n,$def);$r=$fn($d);dbw($n,$d);return $r;}finally{flock($l,LOCK_UN);fclose($l);}
}
function dflt(){return['site_name'=>'FlatChan','site_desc'=>'A lightweight flat-file imageboard.','about'=>'Welcome! Pick a board below to start posting. Administrators can edit this text from the dashboard.',
 'rules'=>'<ol><li>Follow the law.</li><li>No spam or flooding.</li><li>Be civil to staff and users.</li></ol>','news_board'=>'news','captcha'=>1,'captcha_type'=>'text','captcha_login'=>'game',
 'fields'=>['name'=>1,'email'=>1,'subject'=>1,'file'=>1,'spoiler'=>1,'password'=>1],'max_file_mb'=>4,'post_gap'=>15,'thread_gap'=>60,'max_body'=>4000,'embeds'=>1,
 'theme'=>'yotsuba-b','themes_off'=>[],'bg_image'=>'','icons'=>[],'pending_text'=>'Media awaiting approval','pending_img'=>'','rej_text'=>'Media rejected','rej_img'=>'',
 'proxy_header'=>'','tz'=>'UTC','ext_on'=>[],'salt'=>'x'];}
function cfg($k){$s=$GLOBALS['S']??($GLOBALS['S']=array_replace(dflt(),db('settings')));return $s[$k]??null;}
function setcfg(array $kv){dbu('settings',function(&$d)use($kv){foreach($kv as $k=>$v)$d[$k]=$v;});unset($GLOBALS['S']);}
function boards(){$o=[];foreach(db('boards') as $u=>$b)$o[$u]=$b+['uri'=>$u,'desc'=>'','vis'=>'public','locked'=>0,'max_threads'=>100,'archive'=>1,'need_file'=>0,'bump_limit'=>300];return $o;}

/* ---------- small utilities ---------- */
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function sub($s,$n){return function_exists('mb_substr')?mb_substr((string)$s,0,$n):substr((string)$s,0,$n);}
function go($u){header('Location: '.$u);exit;}
function flash($m){$_SESSION['flash'][]=$m;}
function ip(){$h=cfg('proxy_header');if($h){$k='HTTP_'.strtoupper(str_replace('-','_',$h));if(!empty($_SERVER[$k])){$v=trim(explode(',',$_SERVER[$k])[0]);if(filter_var($v,FILTER_VALIDATE_IP))return $v;}}return $_SERVER['REMOTE_ADDR']??'0.0.0.0';}
function hsize($b){return $b>=1048576?round($b/1048576,2).' MB':max(1,round($b/1024)).' KB';}
function span($s){if($s<3600)return max(1,round($s/60)).' minute(s)';if($s<86400)return round($s/3600).' hour(s)';return round($s/86400).' day(s)';}
function dt($t){try{return(new DateTime('@'.(int)$t))->setTimezone(new DateTimeZone(cfg('tz')?:'UTC'))->format('m/d/y (D) H:i:s');}catch(Throwable $x){return gmdate('m/d/y (D) H:i:s',(int)$t);}}
function files($d,$x=['png','jpg','jpeg','gif','webp']){$o=[];foreach(@scandir(ROOT.'/'.$d)?:[] as $f)if($f[0]!=='.'&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),$x,true))$o[]=$f;return $o;}
function log_act($m){$u=me();$n=$u?$u['name']:'-';dbu('log',function(&$d)use($m,$n){array_unshift($d,[time(),$n,$m]);$d=array_slice($d,0,200);});}
function rand_pw($n=12){$c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';$o='';for($i=0;$i<$n;$i++)$o.=$c[random_int(0,strlen($c)-1)];return $o;}
function xs($s,$key){$o='';for($i=0,$n=strlen($s);$i<$n;$i+=32)$o.=substr($s,$i,32)^substr(hash_hmac('sha256',(string)$i,$key,true),0,min(32,$n-$i));return $o;}

/* ---------- extension API ---------- */
function on($h,$f){$GLOBALS['FC']['h'][$h][]=$f;}
function fire($h,$v=null,...$a){foreach($GLOBALS['FC']['h'][$h]??[] as $f)$v=$f($v,...$a);return $v;}
function reg_page($slug,$title,$fn,$nav=true){$GLOBALS['FC']['pages'][$slug]=compact('title','fn','nav');}
function reg_captcha($n,$label,$render,$verify){$GLOBALS['FC']['caps'][$n]=compact('label','render','verify');}
function reg_tab($slug,$label,$min,$render,$post=null){$GLOBALS['FC']['tabs'][$slug]=compact('label','min','render','post');}
function ext_list(){$o=[];foreach(glob(ROOT.'/extensions/*.php')?:[] as $f){$s=basename($f,'.php');$h=(string)file_get_contents($f,false,null,0,700);preg_match('~Name:\s*(.+)~',$h,$n);preg_match('~Desc:\s*(.+)~',$h,$d);$o[$s]=[trim($n[1]??$s),trim($d[1]??'')];}return $o;}

/* ---------- auth, CSRF, rate limit, CAPTCHA ---------- */
function me(){static $m=false;if($m===false){$m=null;if(!empty($_SESSION['uid']))foreach(db('users') as $u)if($u['id']==$_SESSION['uid'])$m=$u;}return $m;}
function rank($r){return['janitor'=>1,'mod'=>2,'admin'=>3][$r]??0;}
function can($min){$u=me();return $u&&rank($u['role'])>=rank($min);}
function csrf(){return $_SESSION['csrf']??=bin2hex(random_bytes(16));}
function csrff(){return '<input type="hidden" name="csrf" value="'.csrf().'">';}
function chk(){if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))fail('Invalid or expired form token. Go back, reload the page and try again.');}
function rate($k,$max,$win){$k.=':'.md5(ip());$now=time();return dbu('rate',function(&$d)use($k,$max,$win,$now){
 foreach($d as $kk=>$v){$v=array_values(array_filter($v,fn($t)=>$t>$now-3600));if($v)$d[$kk]=$v;else unset($d[$kk]);}
 $h=array_filter($d[$k]??[],fn($t)=>$t>$now-$win);if(count($h)>=$max)return false;$d[$k][]=$now;return true;});}
function guard($ctx,$rk,$max,$win){chk();if($ctx==='post'&&!empty($_POST['website']))fail('Spam detected.');if(!rate($rk,$max,$win))fail('You are doing that too fast. Wait a moment and try again.',429);if(!cap_ok($ctx))fail('Wrong CAPTCHA. Go back and try again.');}
function cap_type($c){return $c==='login'?cfg('captcha_login'):cfg('captcha_type');}
function cap_on($c){return cfg('captcha')&&!($c==='post'&&can('janitor'));}
function cap_prov($c){$P=$GLOBALS['FC']['caps'];return $P[cap_type($c)]??$P['text'];}
function cap_html($c){return cap_on($c)?'<tr><th>CAPTCHA</th><td>'.cap_prov($c)['render']($c).'</td></tr>':'';}
function cap_ok($c){if(!cap_on($c))return true;$r=cap_prov($c)['verify']($c,(string)($_POST['cap']??''));unset($_SESSION['cap_'.$c]);return(bool)$r;}
reg_captcha('text','Text image (SVG)',fn($c)=>'<img class="cimg" src="captcha.php?c='.$c.'&amp;r='.mt_rand().'" alt="CAPTCHA" width="160" height="50" title="Click to refresh"><br><input type="text" name="cap" maxlength="8" autocomplete="off" placeholder="Type the characters" required>',
 fn($c,$i)=>($_SESSION['cap_'.$c]??'')!==''&&strcasecmp(trim($i),$_SESSION['cap_'.$c])===0);
reg_captcha('game','Click minigame (no typing)',function($c){$col=['red'=>'#d33','green'=>'#2a2','blue'=>'#36d','orange'=>'#e90','purple'=>'#92c','black'=>'#222'];$k=array_rand($col,4);shuffle($k);$t=$k[array_rand($k)];$h='';
 foreach($k as $n){$v=bin2hex(random_bytes(3));if($n===$t)$_SESSION['cap_'.$c]=$v;$h.='<label class="cg" title="'.$n.'"><input type="radio" name="cap" value="'.$v.'" required><span style="background:'.$col[$n].'"></span></label>';}
 return '<div class="capgame"><b>Click the '.$t.' circle:</b> '.$h.'</div>';},
 fn($c,$i)=>($_SESSION['cap_'.$c]??'')!==''&&hash_equals($_SESSION['cap_'.$c],$i));

/* ---------- uploads ---------- */
function sniff($tmp){$h=(string)file_get_contents($tmp,false,null,0,16);if(strlen($h)<4)return null;
 $i=@getimagesize($tmp);if($i){$m=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_GIF=>'gif',IMAGETYPE_WEBP=>'webp'];return isset($m[$i[2]])?['ext'=>$m[$i[2]],'w'=>$i[0],'h'=>$i[1],'kind'=>'image']:null;}
 if(substr($h,0,4)==="\x1A\x45\xDF\xA3")return['ext'=>'webm','kind'=>'video'];if(substr($h,4,4)==='ftyp')return['ext'=>'mp4','kind'=>'video'];
 if(substr($h,0,4)==='OggS')return['ext'=>'ogg','kind'=>'audio'];if(substr($h,0,3)==='ID3'||(ord($h[0])===0xFF&&(ord($h[1])&0xE0)===0xE0))return['ext'=>'mp3','kind'=>'audio'];return null;}
function gd_resize($src,$ext,$dst,$mx,$fmt='jpg'){if(!function_exists('imagecreatetruecolor'))return false;$f=['jpg'=>'imagecreatefromjpeg','png'=>'imagecreatefrompng','gif'=>'imagecreatefromgif','webp'=>'imagecreatefromwebp'][$ext]??'';
 if(!$f||!function_exists($f))return false;$im=@$f($src);if(!$im)return false;$w=imagesx($im);$h=imagesy($im);$r=min(1,$mx/max($w,$h));$nw=max(1,(int)round($w*$r));$nh=max(1,(int)round($h*$r));$t=imagecreatetruecolor($nw,$nh);
 if($fmt==='png'){imagealphablending($t,false);imagesavealpha($t,true);imagefill($t,0,0,imagecolorallocatealpha($t,0,0,0,127));}else imagefill($t,0,0,imagecolorallocate($t,255,255,255));
 imagecopyresampled($t,$im,0,0,0,0,$nw,$nh,$w,$h);$ok=$fmt==='png'?imagepng($t,$dst):imagejpeg($t,$dst,82);imagedestroy($im);imagedestroy($t);return(bool)$ok;}
function asset_up($dir,$file,$name=null,$mx=0){ /* admin image upload: returns [filename,error] */
 if(empty($file['tmp_name'])||($file['error']??1)!==UPLOAD_ERR_OK)return[null,'Upload failed.'];if($file['size']>3*1048576)return[null,'File too large (3 MB max).'];
 $s=sniff($file['tmp_name']);if(!$s||$s['kind']!=='image')return[null,'Only JPG, PNG, GIF or WebP images are allowed.'];
 $n=$name??trim(preg_replace('/[^a-z0-9_]/','_',strtolower(pathinfo($file['name'],PATHINFO_FILENAME))),'_');if($n==='')$n=bin2hex(random_bytes(3));
 @mkdir(ROOT.'/'.$dir,0755,true);foreach(files($dir) as $o)if(pathinfo($o,PATHINFO_FILENAME)===$n&&$name!==null)@unlink(ROOT."/$dir/$o");
 $fn=$n.'.'.$s['ext'];$dst=ROOT."/$dir/$fn";
 if($mx&&$s['ext']!=='gif'&&max($s['w'],$s['h'])>$mx){$fn=$n.'.png';$dst=ROOT."/$dir/$fn";if(gd_resize($file['tmp_name'],$s['ext'],$dst,$mx,'png'))return[$fn,null];$fn=$n.'.'.$s['ext'];$dst=ROOT."/$dir/$fn";}
 return move_uploaded_file($file['tmp_name'],$dst)?[$fn,null]:[null,'Could not save file (folder permissions?).'];}
function pfiles($p){$o=[];if(!empty($p['file'])){$o[]='uploads/'.$p['file']['name'];if(!empty($p['file']['thumb']))$o[]='thumbs/'.$p['file']['thumb'];}return $o;}
function unlinks($l){foreach($l as $f)@unlink(ROOT.'/'.$f);}
function findp(array $D,$id){foreach($D['threads'] as $ti=>$t){if((int)$t['id']===(int)$id)return[$ti,-1];foreach($t['replies'] as $ri=>$r)if((int)$r['id']===(int)$id)return[$ti,$ri];}return null;}
function bdel(array &$D,$id){$id=(int)$id;foreach($D['threads'] as $ti=>$t){if((int)$t['id']===$id){$o=pfiles($t);foreach($t['replies'] as $r)$o=array_merge($o,pfiles($r));array_splice($D['threads'],$ti,1);return $o;}
 foreach($t['replies'] as $ri=>$r)if((int)$r['id']===$id){$o=pfiles($r);array_splice($D['threads'][$ti]['replies'],$ri,1);return $o;}}return[];}

/* ---------- bans ---------- */
function add_ban($ip,$reason,$hrs,$by,$board){return dbu('bans',function(&$d)use($ip,$reason,$hrs,$by,$board){$id=(int)max(array_column($d,'id')?:[0])+1;$d[]=['id'=>$id,'ip'=>$ip,'reason'=>sub($reason,200),'time'=>time(),'exp'=>$hrs>0?time()+(int)($hrs*3600):0,'by'=>$by,'board'=>$board];return $id;});}
function ban_for($ip,$b=''){foreach(db('bans') as $x){if($x['exp']&&$x['exp']<time())continue;if($b!==''&&$x['board']!=='*'&&$x['board']!==$b)continue;if($x['ip']===$ip||(strpbrk($x['ip'],'*?')!==false&&fnmatch($x['ip'],$ip)))return $x;}return null;}

/* ---------- text formatting ---------- */
function reactions(){static $r=null;if($r===null){$r=[];foreach(files('assets/reactions',['png','jpg','jpeg','gif','webp','svg']) as $f)$r[strtolower(pathinfo($f,PATHINFO_FILENAME))]=$f;}return $r;}
function fmt($b,$u){
 $code=[];$b=e($b);
 $b=preg_replace_callback('~\[code\](.*?)\[/code\]~s',function($m)use(&$code){$code[]='<pre class="code">'.$m[1].'</pre>';return "\x01".(count($code)-1)."\x01";},$b);
 $L=preg_split('~\r\n|\r|\n~',$b);foreach($L as &$l)if(preg_match('~^&gt;(?!&gt;)~',$l))$l='<span class="quote">'.$l.'</span>';unset($l);$b=implode("\n",$L);
 $b=preg_replace('#(?<![\w"=/])(https?://[^\s<]+)#i','<a href="$1" rel="nofollow noopener noreferrer" target="_blank">$1</a>',$b);
 $b=preg_replace('#\*\*(.+?)\*\*#s','<b>$1</b>',$b);$b=preg_replace('#&\#039;&\#039;(.+?)&\#039;&\#039;#s','<i>$1</i>',$b);$b=preg_replace('#~~(.+?)~~#s','<s>$1</s>',$b);$b=preg_replace('#__(.+?)__#s','<u>$1</u>',$b);
 $b=preg_replace('#\[spoiler\](.*?)\[/spoiler\]#s','<span class="spoiler">$1</span>',$b);
 $b=preg_replace('#&gt;&gt;(\d+)#','<a class="ql" href="board.php?b='.e($u).'&amp;p=$1">&gt;&gt;$1</a>',$b);
 $R=reactions();if($R)$b=preg_replace_callback('#:([a-z0-9_]{1,32}):#i',fn($m)=>isset($R[strtolower($m[1])])?'<img class="react" src="assets/reactions/'.e($R[strtolower($m[1])]).'" alt="'.e($m[0]).'" title="'.e($m[0]).'">':$m[0],$b);
 $b=nl2br($b,false);return preg_replace_callback("~\x01(\d+)\x01~",fn($m)=>$code[$m[1]],$b);}
function embeds($body){if(!cfg('embeds'))return '';preg_match_all('#https?://[^\s<>"\']+#i',$body,$m);$o='';
 foreach(array_slice(array_unique($m[0]),0,3) as $u){$q=e($u);
  if(preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\s]*&)?v=|shorts/)|youtu\.be/)([\w-]{11})#i',$u,$x))$o.='<div class="embed"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/'.$x[1].'" allowfullscreen referrerpolicy="no-referrer"></iframe></div>';
  elseif(preg_match('#^https?://(?:www\.)?vimeo\.com/(\d+)#i',$u,$x))$o.='<div class="embed"><iframe loading="lazy" src="https://player.vimeo.com/video/'.$x[1].'" allowfullscreen referrerpolicy="no-referrer"></iframe></div>';
  elseif(preg_match('#\.(mp4|webm)(\?\S*)?$#i',$u))$o.='<div class="embed"><video controls preload="none" src="'.$q.'"></video></div>';
  elseif(preg_match('#\.(mp3|ogg|wav|m4a)(\?\S*)?$#i',$u))$o.='<div class="embed"><audio controls preload="none" src="'.$q.'"></audio></div>';
  elseif(preg_match('#\.(jpe?g|png|gif|webp)(\?\S*)?$#i',$u))$o.='<div class="embed"><img class="post-image" loading="lazy" referrerpolicy="no-referrer" src="'.$q.'" alt=""></div>';}
 return $o;}
function clean_html($h){$h=strip_tags($h,'<p><br><b><strong><i><em><u><s><h2><h3><ul><ol><li><a><hr><blockquote><code>');
 return preg_replace_callback('~<(/?)([a-z0-9]+)([^>]*)>~i',function($m){$t=strtolower($m[2]);if($m[1])return "</$t>";
  if($t==='a'){if(preg_match('~href\s*=\s*["\']?([^"\'\s>]+)~i',$m[3],$x)){$u=html_entity_decode($x[1]);if(preg_match('~^(https?://|/|#|mailto:)~i',$u))return '<a href="'.e($u).'" rel="nofollow noopener">';}return '<a>';}return "<$t>";},$h);}
function icon($k){$d=['pinned'=>['📌','Pinned'],'sticky'=>['⬆','Sticky'],'locked'=>['🔒','Locked'],'archived'=>['🗄','Archived']];$i=cfg('icons')[$k]??'';
 if($i&&is_file(ROOT.'/'.$i))return '<img class="ic" src="'.e($i).'" alt="'.$d[$k][1].'" title="'.$d[$k][1].'">';return '<span class="ic" title="'.$d[$k][1].'">'.$d[$k][0].'</span>';}
function role_html($name,$role){$l=['admin'=>'Admin','mod'=>'Mod','janitor'=>'Janitor'][$role]??'';return '<span class="name role-'.e($role).'">'.e($name).'</span> <span class="rolecap role-'.e($role).'">## '.$l.'</span>';}

/* ---------- themes and layout ---------- */
if(isset($_GET['theme'])&&preg_match('/^[a-z0-9_-]{1,40}$/i',(string)$_GET['theme'])){setcookie('theme',$_GET['theme'],time()+31536000,'/');$_COOKIE['theme']=$_GET['theme'];}
function themes($all=false){$o=[];foreach(glob(ROOT.'/themes/*.css')?:[] as $f){$s=basename($f,'.css');if(!$all&&in_array($s,(array)cfg('themes_off'),true))continue;$l=$s;if(preg_match('~^/\*\s*(.+?)\s*\*/~',(string)file_get_contents($f,false,null,0,200),$m))$l=$m[1];$o[$s]=$l;}return $o;}
function cur_theme(){$t=themes();$c=$_COOKIE['theme']??'';if(isset($t[$c]))return $c;$d=cfg('theme');return isset($t[$d])?$d:(array_key_first($t)?:'yotsuba-b');}
function theme_sel(){$t=themes();$c=cur_theme();$h='<form class="themef" method="get" action="">';foreach($_GET as $k=>$v)if($k!=='theme'&&is_string($v))$h.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
 $h.='<label>Theme <select name="theme" id="theme-sel">';foreach($t as $s=>$l)$h.='<option value="'.e($s).'"'.($s===$c?' selected':'').'>'.e($l).'</option>';return $h.'<option value="__custom">Custom CSS…</option></select></label><noscript><button>Set</button></noscript></form>';}
function banner(){$f=files('assets/banners');return $f?'<div class="banner"><img src="assets/banners/'.e($f[array_rand($f)]).'" alt="" width="300" height="100"></div>':'';}
function nav(){$u=me();$o='<nav class="navbar"><span>[ <a href="index.php">Home</a> ]</span> <span>[ ';$bs=[];foreach(boards() as $b)if($b['vis']==='public'||can('janitor'))$bs[]='<a href="board.php?b='.e($b['uri']).'" title="'.e($b['name']).'">'.e($b['uri']).'</a>';
 $o.=($bs?implode(' / ',$bs):'no boards').' ]</span> <span>[ <a href="rules.php">Rules</a> / <a href="apl.php">Apply</a> / <a href="banned.php">Ban check</a>';
 foreach($GLOBALS['FC']['pages'] as $s=>$p)if($p['nav'])$o.=' / <a href="page.php?p='.e($s).'">'.e($p['title']).'</a>';
 $o.=' ]</span> <span>[ '.($u?'<a href="dashboard.php">Dashboard</a> / <a href="login.php?out=1&amp;csrf='.csrf().'">Logout ('.e($u['name']).')</a>':'<a href="login.php">Login</a>').' ]</span>';return $o.theme_sel().'</nav>';}
function head($title){
 if(!headers_sent()){header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: same-origin');}
 $bg=cfg('bg_image');echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' - '.e(cfg('site_name')).'</title>'
 .'<link rel="stylesheet" href="assets/base.css"><link rel="stylesheet" id="theme-css" href="themes/'.e(cur_theme()).'.css">'
 .'<script>try{var c=localStorage.getItem("fc_css");if(c){var s=document.createElement("style");s.id="ucss";s.textContent=c;document.head.appendChild(s)}}catch(e){}</script>'.fire('head','')
 .'</head><body'.($bg?' style="background-image:url(\''.e($bg).'\')"':'').'>'.nav().'<main class="wrap">';
 if(!empty($_SESSION['flash'])){echo '<div class="box flash">'.implode('<br>',array_map('e',$_SESSION['flash'])).'</div>';unset($_SESSION['flash']);}}
function foot(){echo '</main><footer class="foot">FlatChan 1.0 &mdash; flat-file imageboard engine &mdash; '.e(cfg('site_name')).'</footer><script src="assets/app.js"></script>'.fire('foot','').'</body></html>';}
function fail($m,$code=400){http_response_code($code);head('Error');echo '<div class="box err"><h2>Error</h2><p>'.e($m).'</p><p><a href="javascript:history.back()">[Return]</a></p></div>';foot();exit;}
function not_found(){http_response_code(404);head('404 Not Found');echo '<div class="box err"><h2>404 &mdash; Not Found</h2><p>The page, board or thread you asked for does not exist (or was deleted).</p><p><a href="index.php">[Return home]</a></p></div>';foot();exit;}

/* ---------- boot: installer redirect and extension loading ---------- */
if(!is_file(DATA.'/settings.json.php')&&basename($_SERVER['SCRIPT_NAME']??'')!=='install.php'){header('Location: install.php');exit;}
foreach((array)cfg('ext_on') as $x)if(preg_match('/^[a-z0-9_]+$/',(string)$x)&&is_file(ROOT."/extensions/$x.php"))require_once ROOT."/extensions/$x.php";
