<?php
/*
Name: Media approval
Desc: Uploaded files and embedded media stay hidden behind a placeholder until a janitor or higher approves them.
*/
on('media_pending',function($cur,$post){
 if($cur||can('janitor'))return $cur;
 return !empty($post['file'])||embeds((string)($post['body']??''))!=='';
});
reg_tab('approvals','Media approval','janitor',function(){
 $rows=[];
 foreach(boards() as $b){$D=db('b_'.$b['uri'],['threads'=>[]]);
  foreach($D['threads'] as $t){foreach(array_merge([$t],$t['replies']) as $p){
   if(empty($p['pending']))continue;
   $thumb=!empty($p['file'])?('<a href="uploads/'.e($p['file']['name']).'" target="_blank">'.(!empty($p['file']['thumb'])?'<img src="thumbs/'.e($p['file']['thumb']).'" alt="" style="max-width:100px">':e($p['file']['name'])).'</a>'):'<i>embedded link</i>';
   $f=fn($do,$l)=>'<form class="inl" method="post" action="board.php">'.csrff().'<input type="hidden" name="back" value="dashboard.php?tab=approvals"><input type="hidden" name="act" value="mod"><input type="hidden" name="b" value="'.e($b['uri']).'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><input type="hidden" name="t" value="'.(int)$t['id'].'"><button name="do" value="'.$do.'">'.$l.'</button></form>';
   $rows[]=['<a href="board.php?b='.e($b['uri']).'&amp;t='.(int)$t['id'].'#p'.(int)$p['id'].'">/'.e($b['uri']).'/ No.'.(int)$p['id'].'</a>',$thumb,e(sub($p['body'],120)),e(dt($p['time'])),$f('approve','Approve').$f('reject','Reject').$f('del','Delete')];
   if(count($rows)>=100)break 3;}}}
 echo '<p>Media from non-staff posters is hidden behind a placeholder until approved here (or inline on the post).</p>';
 echo '<table class="tbl"><tr><th>Post</th><th>Media</th><th>Comment</th><th>Posted</th><th></th></tr>';
 foreach($rows as $r)echo '<tr><td>'.implode('</td><td>',$r).'</td></tr>';if(!$rows)echo '<tr><td colspan="5" class="center">Nothing is waiting for approval.</td></tr>';echo '</table>';
 if(can('admin')){
  $sp=function($k){$i=cfg($k);return $i&&is_file(ROOT.'/'.$i)?'<img src="'.e($i).'" alt="" width="60"> ':'<i>none</i> ';};
  echo '<form class="box" method="post" action="dashboard.php?tab=approvals" enctype="multipart/form-data">'.csrff().'<input type="hidden" name="act" value="save"><h3>Placeholders</h3><table class="formtable">'
  .'<tr><th>Pending text</th><td><input name="pending_text" value="'.e(cfg('pending_text')).'" maxlength="80"></td></tr><tr><th>Pending sprite</th><td>'.$sp('pending_img').'<input type="file" name="pending_img" accept="image/*"> <label><input type="checkbox" name="clr_pending" value="1"> remove</label></td></tr>'
  .'<tr><th>Disapproval text</th><td><input name="rej_text" value="'.e(cfg('rej_text')).'" maxlength="80"></td></tr><tr><th>Disapproval sprite (optional)</th><td>'.$sp('rej_img').'<input type="file" name="rej_img" accept="image/*"> <label><input type="checkbox" name="clr_rej" value="1"> remove</label></td></tr></table><button>Save</button></form>';
 }
},function($act){
 if(!can('admin'))return;$kv=['pending_text'=>trim(sub((string)($_POST['pending_text']??''),80)),'rej_text'=>trim(sub((string)($_POST['rej_text']??''),80))];
 foreach(['pending_img'=>'clr_pending','rej_img'=>'clr_rej'] as $k=>$c){
  if(!empty($_POST[$c]))$kv[$k]='';
  elseif(!empty($_FILES[$k]['tmp_name'])){[$fn,$er]=asset_up('assets/sprites',$_FILES[$k],$k,125);if($er)flash($er);else $kv[$k]='assets/sprites/'.$fn;}}
 setcfg($kv);flash('Approval placeholders saved.');
});
