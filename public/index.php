<?php
define('APP_PATH', realpath(__DIR__.'/../'));
$app = new Yaf_Application(APP_PATH.'/conf/application.ini');
$app->bootstrap()->run();