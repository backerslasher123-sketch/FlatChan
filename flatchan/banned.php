<?php
require __DIR__.'/lib.php';
$b=ban_for(ip());
if($b)http_response_code(403);
head($b?'You are banned':'Ban check');
$img=files('assets/banimgs');
if(!$b){echo '<div class="box narrow"><h2>You are not banned</h2><p>No active ban was found for your IP address ('.e(ip()).').</p><form method="get" action="banned.php"><button>Check again</button></form><p><a href="index.php">[Return home]</a></p></div>';foot();exit;}
echo '<h1 class="title err">You are banned!</h1>';
if($img)echo '<p class="center"><img class="banimg" src="assets/banimgs/'.e($img[array_rand($img)]).'" alt=""></p>';
echo '<table class="tbl bantbl"><tr><th>Ban ID</th><td>#'.(int)$b['id'].'</td></tr>'
.'<tr><th>Reason</th><td>'.($b['reason']!==''?e($b['reason']):'<i>No reason given</i>').'</td></tr>'
.'<tr><th>Applies to</th><td>'.($b['board']==='*'?'All boards':'/'.e($b['board']).'/').'</td></tr>'
.'<tr><th>Issued</th><td>'.e(dt($b['time'])).' ('.e(span(time()-$b['time'])).' ago)</td></tr>'
.'<tr><th>Duration</th><td>'.($b['exp']?e(span($b['exp']-$b['time'])):'Permanent').'</td></tr>'
.'<tr><th>Expires</th><td>'.($b['exp']?e(dt($b['exp'])).' (in '.e(span($b['exp']-time())).')':'Never').'</td></tr>'
.'<tr><th>Your IP</th><td>'.e(ip()).'</td></tr></table>'
.'<form class="center" method="get" action="banned.php"><button>Check ban status again</button></form><p class="center"><a href="index.php">[Return home]</a></p>';
foot();
