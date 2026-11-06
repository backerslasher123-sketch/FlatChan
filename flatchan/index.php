<?php
require __DIR__.'/lib.php';
function pph($u){$D=db('b_'.$u,['threads'=>[]]);$c=0;$t=time()-3600;foreach($D['threads'] as $th){if($th['time']>$t)$c++;foreach($th['replies'] as $r)if($r['time']>$t)$c++;}return $c;}
head('Home');
echo banner().'<h1 class="title">'.e(cfg('site_name')).'</h1><p class="subtitle">'.e(cfg('site_desc')).'</p>';
echo '<table class="tbl home"><tr><th>About</th></tr><tr><td>'.nl2br(e(cfg('about'))).'</td></tr></table>';
$nb=boards()[cfg('news_board')]??null;$rows='';
if($nb&&($nb['vis']!=='staff'||can('janitor'))){
 $D=db('b_'.$nb['uri'],['threads'=>[]]);$ts=array_filter($D['threads'],fn($t)=>($t['news']??'')==='ok');usort($ts,fn($a,$b)=>$b['time']<=>$a['time']);
 foreach(array_slice($ts,0,10) as $t){$who=!empty($t['role'])?role_html($t['user']?:$t['name'],$t['role']):e($t['name']);
  $rows.='<tr><td>'.e(dt($t['time'])).'</td><td>'.$who.'</td><td><a href="board.php?b='.e($nb['uri']).'&amp;t='.(int)$t['id'].'">'.e($t['subject']!==''?$t['subject']:'Thread #'.$t['id']).'</a></td></tr>';}
}
echo '<table class="tbl home"><tr><th colspan="3">News</th></tr><tr class="sub"><td>Date</td><td>Poster</td><td>Thread</td></tr>'.($rows?:'<tr><td colspan="3" class="center">No approved news yet.</td></tr>').'</table>';
echo '<table class="tbl home"><tr><th>Board</th><th>Description</th><th>PPH</th></tr>';$any=false;
foreach(boards() as $b){if($b['vis']!=='public')continue;$any=true;
 echo '<tr><td><a href="board.php?b='.e($b['uri']).'">/'.e($b['uri']).'/</a></td><td><b>'.e($b['name']).'</b>'.($b['desc']!==''?' &mdash; '.e($b['desc']):'').'</td><td>'.pph($b['uri']).'</td></tr>';}
if(!$any)echo '<tr><td colspan="3" class="center">No public boards.</td></tr>';
echo '</table>';foot();
