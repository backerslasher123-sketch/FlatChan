<?php
require __DIR__.'/lib.php';
$uri=preg_replace('/[^a-z0-9]/','',strtolower((string)($_GET['b']??$_POST['b']??'')));
$B=boards()[$uri]??null;
if(!$B||($B['vis']==='staff'&&!can('janitor')))not_found();
$act=(string)($_POST['act']??$_GET['act']??'');
$me=me();

if($_SERVER['REQUEST_METHOD']==='POST'){
 chk();
 switch($act){case 'post':do_post();case 'del':do_del();case 'mod':do_mod();case 'ban':do_ban();case 'report':do_report();}
 not_found();
}
if(isset($_GET['p'])){$D=db('b_'.$uri,['threads'=>[]]);$f=findp($D,(int)$_GET['p']);if(!$f)not_found();go('board.php?b='.$uri.'&t='.$D['threads'][$f[0]]['id'].'#p'.(int)$_GET['p']);}
if($act==='report')report_form();
elseif(isset($_GET['t']))show_thread((int)$_GET['t']);
elseif(isset($_GET['archive']))show_archive();
else show_board();
exit;

/* ================= actions ================= */
function back($d=''){$b=(string)($_POST['back']??'');if(preg_match('~^dashboard\.php\?tab=[a-z0-9_]+$~',$b))return $b;return $d?:'board.php?b='.$GLOBALS['uri'];}
function turl($t){return 'board.php?b='.$GLOBALS['uri'].($t?'&t='.(int)$t:'');}

function do_post(){
 global $B,$uri;
 if(ban_for(ip(),$uri))go('banned.php');
 $tid=(int)($_POST['t']??0);$F=cfg('fields');
 if(!empty($B['locked'])&&!can('mod'))fail('This board is locked.');
 $g=fn($k,$n)=>!empty($F[$k])?trim(sub((string)($_POST[$k]??''),$n)):'';
 $name=$g('name',60);$email=$g('email',60);$subj=$g('subject',80);
 $body=str_replace("\0",'',trim((string)($_POST['body']??'')));
 if(strlen($body)>cfg('max_body'))fail('Comment too long (max '.cfg('max_body').' bytes).');
 $up=$_FILES['file']??null;$info=null;
 $has=!empty($F['file'])&&$up&&($up['error']??4)!==UPLOAD_ERR_NO_FILE;
 if($has){
  if($up['error']!==UPLOAD_ERR_OK)fail('Upload failed (error '.$up['error'].'). The file may exceed the server limit.');
  if($up['size']>cfg('max_file_mb')*1048576)fail('File too large (max '.cfg('max_file_mb').' MB).');
  $info=sniff($up['tmp_name']);if(!$info)fail('Unsupported file. Allowed: JPG, PNG, GIF, WebP, MP4, WebM, MP3, OGG.');
  if(($info['w']??0)*($info['h']??0)>40000000)fail('Image dimensions are too large.');
 }
 if($body===''&&!$info)fail('Write a comment or attach a file.');
 if(!$tid&&!empty($B['need_file'])&&!$info)fail('A file is required to start a thread on this board.');
 $trip='';$role='';$user='';$m=[];
 if($name!==''&&preg_match('/^([^#]*)#(#?)(.*)$/s',$name,$m)){$name=trim($m[1]);if($m[3]!=='')$trip=$m[2]?'!!'.substr(hash_hmac('sha256',$m[3],(string)cfg('salt')),0,10):'!'.substr(hash('sha256',$m[3].'|trip'),0,10);}
 $u=me();
 if($u&&!empty($_POST['showrole'])){$role=$u['role'];$user=$u['name'];$name=$u['name'];$trip='';}
 elseif($name!==''){foreach(db('users') as $x)if(strcasecmp($x['name'],$name)===0)fail('That name is reserved for staff.');}
 $kw=strtolower($email);$sage=in_array($kw,['sage','supersage'],true);$super=$kw==='supersage';$nonoko=$kw==='nonoko';
 guard('post','post',1,(int)($tid?cfg('post_gap'):cfg('thread_gap')));
 $file=null;
 if($info){
  $n=time().bin2hex(random_bytes(4)).'.'.$info['ext'];
  if(!move_uploaded_file($up['tmp_name'],ROOT.'/uploads/'.$n))fail('Could not save the upload. Check folder permissions.');
  $file=['name'=>$n,'orig'=>sub((string)preg_replace('/[^\w.\- ]/','_',(string)$up['name']),60),'size'=>(int)$up['size'],'kind'=>$info['kind'],'ext'=>$info['ext'],'w'=>$info['w']??0,'h'=>$info['h']??0,'spoiler'=>!empty($F['spoiler'])&&!empty($_POST['spoiler'])];
  if($info['kind']==='image'&&gd_resize(ROOT.'/uploads/'.$n,$info['ext'],ROOT.'/thumbs/'.$n.'.jpg',250))$file['thumb']=$n.'.jpg';
 }
 $post=['time'=>time(),'name'=>$name!==''?$name:'Anonymous','trip'=>$trip,'email'=>$email,'subject'=>$subj,'body'=>$body,'ip'=>ip(),
  'pw'=>(!empty($F['password'])&&!empty($_POST['pw']))?password_hash((string)$_POST['pw'],PASSWORD_DEFAULT):'','role'=>$role,'user'=>$user,'file'=>$file];
 $post['pending']=(bool)fire('media_pending',false,$post);
 $r=fire('before_post',$post,$B);if(is_string($r)){unlinks(pfiles($post));fail($r);}$post=$r;
 $res=dbu('b_'.$uri,function(&$D)use($post,$tid,$sage,$super,$B,$uri){
  $id=(int)$D['next'];$post['id']=$id;
  if($tid){
   foreach($D['threads'] as &$t){if((int)$t['id']===$tid){
    if((!empty($t['locked'])||!empty($t['archived']))&&!can('mod'))return 'This thread is locked.';
    $t['replies'][]=$post;if(!$sage&&count($t['replies'])<$B['bump_limit'])$t['bump']=time();elseif($super)$t['bump']=$t['time'];
    $D['next']=$id+1;return[$id,$tid,[]];}}
   unset($t);return 'That thread no longer exists.';
  }
  $th=$post+['replies'=>[],'bump'=>time(),'sticky'=>0,'pinned'=>0,'locked'=>0,'archived'=>0];
  if($uri===cfg('news_board'))$th['news']=can('janitor')?'ok':'pending';
  $D['threads'][]=$th;$D['next']=$id+1;$old=[];
  $live=[];foreach($D['threads'] as $i=>$t)if(empty($t['archived']))$live[]=$i;
  $over=count($live)-$B['max_threads'];
  if($over>0){
   $c=array_values(array_filter($live,fn($i)=>empty($D['threads'][$i]['sticky'])&&empty($D['threads'][$i]['pinned'])));
   usort($c,fn($x,$y)=>$D['threads'][$x]['bump']<=>$D['threads'][$y]['bump']);$kill=[];
   foreach(array_slice($c,0,$over) as $i){if(!empty($B['archive'])){$D['threads'][$i]['archived']=1;$D['threads'][$i]['locked']=1;}else $kill[]=$D['threads'][$i]['id'];}
   foreach($kill as $k)$old=array_merge($old,bdel($D,$k));
  }
  return[$id,$id,$old];
 },['next'=>1,'threads'=>[]]);
 if(is_string($res)){unlinks(pfiles($post));fail($res);}
 unlinks($res[2]);fire('after_post',null,$res[0],$B);
 go($nonoko?turl(0):turl($res[1]).'#p'.$res[0]);
}

function do_del(){
 global $uri;$ids=array_map('intval',(array)($_POST['del']??[]));$pw=(string)($_POST['pw']??'');
 if(!$ids)fail('Select at least one post to delete.');$n=0;$files=[];
 dbu('b_'.$uri,function(&$D)use($ids,$pw,&$n,&$files){foreach($ids as $id){$f=findp($D,$id);if(!$f)continue;
  $p=$f[1]<0?$D['threads'][$f[0]]:$D['threads'][$f[0]]['replies'][$f[1]];$h=$p['pw']??'';
  if($h!==''&&$pw!==''&&password_verify($pw,$h)){$files=array_merge($files,bdel($D,$id));$n++;}}},['next'=>1,'threads'=>[]]);
 unlinks($files);flash($n?"Deleted $n post(s).":'Nothing deleted: wrong password, or the post has no deletion password.');go(back(turl((int)($_POST['t']??0))));
}

function do_mod(){
 global $uri;$do=(string)($_POST['do']??'');$id=(int)($_POST['id']??0);
 $min=['del'=>'janitor','delip'=>'janitor','approve'=>'janitor','reject'=>'janitor','newsok'=>'janitor','newsno'=>'janitor','pinned'=>'mod','sticky'=>'mod','locked'=>'mod','archived'=>'mod','bump'=>'mod'][$do]??null;
 if(!$min||!can($min))fail('You do not have permission to do that.',403);
 $files=[];
 $r=dbu('b_'.$uri,function(&$D)use($do,$id,&$files){
  $f=findp($D,$id);if(!$f)return 'Post not found.';
  if($do==='del'){$files=bdel($D,$id);return 'Post deleted.';}
  if($do==='delip'){$s=$f[1]<0?$D['threads'][$f[0]]:$D['threads'][$f[0]]['replies'][$f[1]];$ip=$s['ip'];$ids=[];
   foreach($D['threads'] as $t){if($t['ip']===$ip)$ids[]=$t['id'];foreach($t['replies'] as $x)if($x['ip']===$ip)$ids[]=$x['id'];}
   foreach($ids as $i)$files=array_merge($files,bdel($D,$i));return count($ids).' post(s) deleted.';}
  if($do==='approve'||$do==='reject'){
   if($f[1]<0)$P=&$D['threads'][$f[0]];else $P=&$D['threads'][$f[0]]['replies'][$f[1]];
   $P['pending']=false;$P['rej']=($do==='reject');return $do==='approve'?'Media approved.':'Media rejected.';}
  if($f[1]>=0)return 'Thread actions apply to the first post of a thread.';
  $T=&$D['threads'][$f[0]];
  if($do==='bump'){$T['bump']=time();return 'Thread bumped.';}
  if($do==='newsok'){$T['news']='ok';return 'News approved.';}
  if($do==='newsno'){$T['news']='pending';return 'News approval removed.';}
  $T[$do]=empty($T[$do])?1:0;
  if($do==='archived')$T['locked']=$T['archived'];
  return ucfirst($do).' '.($T[$do]?'enabled':'disabled').'.';
 },['next'=>1,'threads'=>[]]);
 unlinks($files);log_act("/$uri/ #$id $do");flash($r);go(back(turl((int)($_POST['t']??0))));
}

function do_ban(){
 global $uri;if(!can('janitor'))fail('You do not have permission to do that.',403);
 $id=(int)($_POST['id']??0);$ip='';$files=[];
 $ok=dbu('b_'.$uri,function(&$D)use($id,&$ip,&$files){$f=findp($D,$id);if(!$f)return false;
  if($f[1]<0)$P=&$D['threads'][$f[0]];else $P=&$D['threads'][$f[0]]['replies'][$f[1]];$ip=$P['ip'];
  if(!empty($_POST['bdel'])){unset($P);$files=bdel($D,$id);}else $P['bmsg']=1;return true;},['next'=>1,'threads'=>[]]);
 if(!$ok)fail('Post not found.');unlinks($files);
 $hrs=(float)($_POST['hrs']??0);$bid=add_ban($ip,trim((string)($_POST['reason']??'')),$hrs,me()['name'],(($_POST['scope']??'')==='*')?'*':$uri);
 log_act("ban #$bid on $ip");flash("User banned (ban #$bid).");go(back(turl((int)($_POST['t']??0))));
}

function do_report(){
 global $uri;$id=(int)($_POST['id']??0);$D=db('b_'.$uri,['threads'=>[]]);$f=findp($D,$id);if(!$f)fail('That post does not exist.');
 if(!rate('report',3,300))fail('Too many reports. Please wait a few minutes.',429);
 $p=$f[1]<0?$D['threads'][$f[0]]:$D['threads'][$f[0]]['replies'][$f[1]];$tid=$D['threads'][$f[0]]['id'];
 $cat=in_array($_POST['cat']??'',['Spam','Illegal content','Rule violation','Other'],true)?$_POST['cat']:'Other';$why=trim(sub((string)($_POST['reason']??''),200));
 dbu('reports',function(&$d)use($uri,$id,$tid,$cat,$why,$p){$n=(int)max(array_column($d,'id')?:[0])+1;
  $d[]=['id'=>$n,'board'=>$uri,'post'=>$id,'tid'=>$tid,'cat'=>$cat,'reason'=>$why,'status'=>'open','time'=>time(),'pip'=>$p['ip'],'excerpt'=>sub($p['body'],160)];});
 flash('Report sent to the moderators. Thank you.');go(turl($tid).'#p'.$id);
}

/* ================= rendering ================= */
function media_html($p,$isop){
 $f=$p['file']??null;if(!$f)return '';$staff=can('janitor');$bad=!empty($p['pending'])||!empty($p['rej']);
 if($bad&&!$staff){$rej=!empty($p['rej']);$img=cfg($rej?'rej_img':'pending_img');
  return '<div class="files"><div class="ph">'.($img&&is_file(ROOT.'/'.$img)?'<img src="'.e($img).'" alt="" width="125">':'').'<span>'.e(cfg($rej?'rej_text':'pending_text')).'</span></div></div>';}
 $u='uploads/'.e($f['name']);$mx=$isop?250:125;$w=(int)($f['w']??0);$h=(int)($f['h']??0);
 $o='<div class="files"><div class="fileinfo">File: <a href="'.$u.'" target="_blank">'.e($f['name']).'</a> <small>('.hsize($f['size']).($w?', '.$w.'x'.$h:'').', '.e($f['orig']).')</small>';
 if($staff&&!empty($p['pending']))$o.=' <span class="badge">PENDING APPROVAL</span>';if($staff&&!empty($p['rej']))$o.=' <span class="badge">REJECTED</span>';$o.='</div>';
 $sp=!empty($f['spoiler'])?' spoilerimg':'';
 if($f['kind']==='image'){$s=min(1,$mx/max(1,max($w,$h)));$th=!empty($f['thumb'])?'thumbs/'.e($f['thumb']):$u;
  $o.='<a class="thumb" href="'.$u.'" target="_blank" data-full="'.$u.'" data-thumb="'.$th.'"><img class="post-image'.$sp.'" src="'.$th.'" width="'.max(1,round($w*$s)).'" height="'.max(1,round($h*$s)).'" alt="" loading="lazy"></a>';}
 elseif($f['kind']==='video')$o.='<video class="post-image'.$sp.'" controls preload="metadata" src="'.$u.'" style="max-width:'.$mx.'px"></video>';
 else $o.='<audio controls preload="none" src="'.$u.'"></audio>';
 return $o.'</div>';
}
function modbar($p,$tid,$isop){
 global $uri;if(!can('janitor'))return '';
 $btn=fn($do,$l)=>'<button name="do" value="'.$do.'">'.$l.'</button>';
 $o='<form class="modbar" method="post" action="board.php">'.csrff().'<input type="hidden" name="act" value="mod"><input type="hidden" name="b" value="'.e($uri).'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><input type="hidden" name="t" value="'.(int)$tid.'">IP '.e($p['ip']).' '.$btn('del','Delete').$btn('delip','Delete all by IP');
 if(!empty($p['file'])||!empty($p['pending'])||!empty($p['rej']))$o.=$btn('approve','Approve media').$btn('reject','Reject media');
 if($isop){
  if(can('mod'))foreach(['pinned'=>'Pin','sticky'=>'Sticky','locked'=>'Lock','archived'=>'Archive','bump'=>'Bump'] as $k=>$l)$o.=$btn($k,$l);
  if($uri===cfg('news_board'))$o.=empty($p['news'])||$p['news']!=='ok'?$btn('newsok','Approve news'):$btn('newsno','Unapprove news');
 }
 $o.='</form><details class="banbox"><summary>Ban poster</summary><form method="post" action="board.php">'.csrff().'<input type="hidden" name="act" value="ban"><input type="hidden" name="b" value="'.e($uri).'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><input type="hidden" name="t" value="'.(int)$tid.'">'
 .'<input type="text" name="reason" maxlength="200" placeholder="Reason"> <select name="hrs"><option value="1">1 hour</option><option value="24" selected>1 day</option><option value="72">3 days</option><option value="168">1 week</option><option value="720">30 days</option><option value="0">Permanent</option></select> '
 .'<select name="scope"><option value="">This board</option><option value="*">All boards</option></select> <label><input type="checkbox" name="bdel" value="1"> Delete post</label> <button>Ban</button></form></details>';
 return $o;
}
function post_html($p,$tid,$isop,$short=false){
 global $uri;$id=(int)$p['id'];
 $h='<div class="post '.($isop?'op':'reply').'" id="p'.$id.'"><p class="intro"><input type="checkbox" name="del[]" value="'.$id.'" form="delform"> ';
 if($p['subject']!=='')$h.='<span class="subject">'.e($p['subject']).'</span> ';
 if(!empty($p['role']))$h.=role_html($p['user']?:$p['name'],$p['role']);
 else{$nm='<span class="name">'.e($p['name']).'</span>';$h.=(!empty($p['email'])&&strpos($p['email'],'@')!==false)?'<a href="mailto:'.e($p['email']).'">'.$nm.'</a>':$nm;if($p['trip']!=='')$h.=' <span class="trip">'.e($p['trip']).'</span>';}
 $h.=' <time>'.e(dt($p['time'])).'</time> <a class="pn" href="'.turl($tid).'#p'.$id.'">No.</a><a class="qr" href="'.turl($tid).'#q'.$id.'" data-q="'.$id.'">'.$id.'</a>';
 if($isop){foreach(['pinned','sticky','locked','archived'] as $k)if(!empty($p[$k]))$h.=' '.icon($k);
  if(isset($p['news'])&&$p['news']!=='ok')$h.=' <span class="badge">NEWS PENDING</span>';if($short)$h.=' <a href="'.turl($tid).'">[Reply]</a>';}
 $h.=' <a class="rep" href="board.php?b='.e($uri).'&amp;act=report&amp;id='.$id.'">[Report]</a></p>';
 $h.=media_html($p,$isop).'<div class="body">'.fmt($p['body'],$uri).'</div>';
 if(empty($p['pending'])&&empty($p['rej'])||can('janitor'))$h.=embeds($p['body']);
 if(!empty($p['bmsg']))$h.='<p class="banmsg">(USER WAS BANNED FOR THIS POST)</p>';
 return $h.modbar($p,$tid,$isop).'</div>';
}
function post_form($tid){
 global $B,$uri;$F=cfg('fields');$u=me();
 if($tid&&!can('mod')){$D=db('b_'.$uri,['threads'=>[]]);$f=findp($D,$tid);if($f&&(!empty($D['threads'][$f[0]]['locked'])))return '<div class="box">This thread is locked. You cannot reply.</div>';}
 if(!empty($B['locked'])&&!can('mod'))return '<div class="box">This board is locked.</div>';
 $o='<form id="postform" class="postform" method="post" action="board.php" enctype="multipart/form-data">'.csrff().'<input type="hidden" name="act" value="post"><input type="hidden" name="b" value="'.e($uri).'">'.($tid?'<input type="hidden" name="t" value="'.$tid.'">':'').'<div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div><table class="formtable">';
 if(!empty($F['name']))$o.='<tr><th>Name</th><td><input type="text" name="name" maxlength="60" placeholder="Anonymous">'.($u?' <label><input type="checkbox" name="showrole" value="1"> Show as '.e($u['role']).'</label>':'').'</td></tr>';
 elseif($u)$o.='<tr><th>Staff</th><td><label><input type="checkbox" name="showrole" value="1"> Show as '.e($u['role']).'</label></td></tr>';
 if(!empty($F['email']))$o.='<tr><th>Email</th><td><input type="text" name="email" maxlength="60" placeholder="sage / supersage / nonoko"></td></tr>';
 $o.='<tr><th>Subject</th><td>'.(!empty($F['subject'])?'<input type="text" name="subject" maxlength="80"> ':'').'<button type="submit">'.($tid?'New Reply':'New Thread').'</button></td></tr>';
 $o.='<tr><th>Comment</th><td><textarea name="body" id="body" rows="5" cols="48" maxlength="'.(int)cfg('max_body').'"></textarea></td></tr>'.cap_html('post');
 if(!empty($F['file']))$o.='<tr><th>File</th><td><input type="file" name="file">'.(!empty($F['spoiler'])?' <label><input type="checkbox" name="spoiler" value="1"> Spoiler</label>':'').'</td></tr>';
 if(!empty($F['password']))$o.='<tr><th>Password</th><td><input type="password" name="pw" id="pw" maxlength="40" data-gen="1"> <small>(for deleting your posts)</small></td></tr>';
 return $o.fire('post_form','').'</table><p class="hint">Formatting: **bold** &nbsp; \'\'italic\'\' &nbsp; __underline__ &nbsp; ~~strike~~ &nbsp; [spoiler]text[/spoiler] &nbsp; [code]code[/code] &nbsp; &gt;greentext &nbsp; &gt;&gt;123 quote &nbsp; :reaction:</p></form>';
}
function del_form($tid){global $uri;
 return '<form id="delform" class="delform" method="post" action="board.php">'.csrff().'<input type="hidden" name="act" value="del"><input type="hidden" name="b" value="'.e($uri).'">'.($tid?'<input type="hidden" name="t" value="'.$tid.'">':'')
 .'Delete selected posts &mdash; Password <input type="password" name="pw" maxlength="40" data-pw="1"> <button>Delete</button></form>';}
function title_html(){global $B,$uri;return banner().'<h1 class="title">/'.e($uri).'/ &mdash; '.e($B['name']).'</h1>'.($B['desc']!==''?'<p class="subtitle">'.e($B['desc']).'</p>':'');}
function show_board(){
 global $B,$uri;$D=db('b_'.$uri,['next'=>1,'threads'=>[]]);
 $ts=array_values(array_filter($D['threads'],fn($t)=>empty($t['archived'])));
 usort($ts,fn($x,$y)=>[!empty($y['pinned']),!empty($y['sticky']),$y['bump']]<=>[!empty($x['pinned']),!empty($x['sticky']),$x['bump']]);
 $per=10;$pages=max(1,(int)ceil(count($ts)/$per));$pg=max(0,min($pages-1,(int)($_GET['page']??0)));
 head('/'.$uri.'/ - '.$B['name']);echo title_html().'<hr>'.post_form(0).'<hr><div class="links">[<a href="board.php?b='.e($uri).'&amp;archive=1">Archive</a>] [<a href="#bottom">Bottom</a>]</div>';
 if(!$ts)echo '<p class="center">No threads yet. Be the first to post!</p>';
 foreach(array_slice($ts,$pg*$per,$per) as $t){
  $n=count($t['replies']);$show=array_slice($t['replies'],-3);$om=$n-count($show);
  echo '<div class="thread" id="thread_'.(int)$t['id'].'">'.post_html($t,$t['id'],true,true);
  if($om>0)echo '<p class="omitted">'.$om.' repl'.($om>1?'ies':'y').' omitted. <a href="'.turl($t['id']).'">Click Reply to view.</a></p>';
  foreach($show as $r)echo post_html($r,$t['id'],false);echo '</div><br class="clear"><hr>';}
 echo '<a id="bottom"></a>'.del_form(0).'<div class="pages">';
 if($pg>0)echo '[<a href="board.php?b='.e($uri).'&amp;page='.($pg-1).'">Previous</a>] ';
 for($i=0;$i<$pages;$i++)echo $i===$pg?'[<b>'.$i.'</b>] ':'[<a href="board.php?b='.e($uri).'&amp;page='.$i.'">'.$i.'</a>] ';
 if($pg<$pages-1)echo '[<a href="board.php?b='.e($uri).'&amp;page='.($pg+1).'">Next</a>]';
 echo '</div>';foot();
}
function show_thread($tid){
 global $B,$uri;$D=db('b_'.$uri,['threads'=>[]]);$f=findp($D,$tid);if(!$f||$f[1]>=0)not_found();$t=$D['threads'][$f[0]];
 head('/'.$uri.'/ - '.($t['subject']!==''?$t['subject']:'Thread '.$tid));
 echo title_html().'<div class="postmode">Posting mode: Reply</div>'.'<div class="links">[<a href="board.php?b='.e($uri).'">Return</a>] [<a href="#bottom">Bottom</a>]</div><hr>'.post_form($tid).'<hr>';
 echo '<div class="thread" id="thread_'.$tid.'">'.post_html($t,$tid,true);foreach($t['replies'] as $r)echo post_html($r,$tid,false);echo '</div><br class="clear"><hr><a id="bottom"></a>'.del_form($tid)
 .'<div class="links">[<a href="board.php?b='.e($uri).'">Return</a>] [<a href="#top">Top</a>] &mdash; '.count($t['replies']).' replies</div>';foot();
}
function show_archive(){
 global $B,$uri;$D=db('b_'.$uri,['threads'=>[]]);$ts=array_values(array_filter($D['threads'],fn($t)=>!empty($t['archived'])));usort($ts,fn($x,$y)=>$y['bump']<=>$x['bump']);
 head('/'.$uri.'/ archive');echo title_html().'<div class="links">[<a href="board.php?b='.e($uri).'">Return</a>]</div><table class="tbl"><tr><th>No.</th><th>Subject / excerpt</th><th>Replies</th><th>Last bump</th></tr>';
 foreach($ts as $t)echo '<tr><td><a href="'.turl($t['id']).'">'.(int)$t['id'].'</a></td><td>'.e($t['subject']!==''?$t['subject'].' &mdash; ':'').e(sub($t['body'],90)).'</td><td>'.count($t['replies']).'</td><td>'.e(dt($t['bump'])).'</td></tr>';
 if(!$ts)echo '<tr><td colspan="4" class="center">The archive is empty.</td></tr>';echo '</table>';foot();
}
function report_form(){
 global $uri;$id=(int)($_GET['id']??0);$D=db('b_'.$uri,['threads'=>[]]);$f=findp($D,$id);if(!$f)not_found();
 $p=$f[1]<0?$D['threads'][$f[0]]:$D['threads'][$f[0]]['replies'][$f[1]];
 head('Report post');echo '<form class="box" method="post" action="board.php"><h2>Report post No.'.$id.' on /'.e($uri).'/</h2><blockquote class="excerpt">'.e(sub($p['body'],200)).'</blockquote>'.csrff().'<input type="hidden" name="act" value="report"><input type="hidden" name="b" value="'.e($uri).'"><input type="hidden" name="id" value="'.$id.'">'
 .'<table class="formtable"><tr><th>Category</th><td><select name="cat"><option>Spam</option><option>Illegal content</option><option>Rule violation</option><option>Other</option></select></td></tr><tr><th>Reason</th><td><input type="text" name="reason" maxlength="200" size="40"></td></tr></table><button>Send report</button> <a href="'.turl($D['threads'][$f[0]]['id']).'#p'.$id.'">[Cancel]</a></form>';foot();
}
