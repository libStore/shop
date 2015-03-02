<?php
$web = dirname(__FILE__)."/lib/web.php";
$config = dirname(__FILE__)."/config/config.php";
require($web);
Web::createWebApp($config)->run();
?>