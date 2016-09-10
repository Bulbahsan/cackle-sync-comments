<?php
require dirname(__FILE__) . '/cackle.php';
echo 'start cron ' . date('d.m.Y H:i:s') . "\n";
$cron = new CackleApi();
$cron->sync();
echo "\n" . 'end cron ' . date('d.m.Y H:i:s') . "\n";