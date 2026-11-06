<?php
require __DIR__.'/lib.php';
$p=$GLOBALS['FC']['pages'][(string)($_GET['p']??'')]??null;
if(!$p)not_found();
head($p['title']);echo '<h1 class="title">'.e($p['title']).'</h1>';($p['fn'])();foot();
