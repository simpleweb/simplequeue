#!/usr/bin/php
<?php
date_default_timezone_set('Europe/London');

require_once 'Zend/Loader/Autoloader.php';
$autoLoder = Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(dirname(__FILE__) . '/'),
    get_include_path(),
)));


require_once('worker.php');

$configFilename = realpath('queue.ini');
$sw = new Simple_Worker($configFilename);
$sw->process();