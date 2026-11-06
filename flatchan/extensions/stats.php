<?php
/*
Name: Statistics page
Desc: Registers a public "Stats" page (page.php?p=stats) with post counts per board. Example of reg_page().
*/
reg_page('stats','Stats',function(){
 echo '<table class="tbl"><tr><th>Board</th><th>Threads</th><th>Posts</th><th>Posts / hour</th></tr>';
 foreach(boards() as $b){if($b['vis']!=='public')continue;$D=db('b_'.$b['uri'],['threads'=>[]]);$p=0;$h=0;
  foreach($D['threads'] as $t){$p+=1+count($t['replies']);if($t['time']>time()-3600)$h++;foreach($t['replies'] as $r)if($r['time']>time()-3600)$h++;}
  echo '<tr><td><a href="board.php?b='.e($b['uri']).'">/'.e($b['uri']).'/</a></td><td>'.count($D['threads']).'</td><td>'.$p.'</td><td>'.$h.'</td></tr>';}
 echo '</table>';
});
