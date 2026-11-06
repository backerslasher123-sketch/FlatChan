<?php
require __DIR__.'/lib.php';
head('Rules');
echo banner().'<h1 class="title">Rules</h1><table class="tbl home"><tr><th>Site rules</th></tr><tr><td class="rich">'.cfg('rules').'</td></tr></table>';
foot();
