<?php
require dirname(__FILE__) . '/cackle.php';
$widget = new CackleApi();
echo $widget->getWidget($_SERVER['REQUEST_URI']);