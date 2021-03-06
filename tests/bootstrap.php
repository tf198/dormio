<?php
require dirname(__DIR__) . "/vendor/Dormio/Dormio/AutoLoader.php";
Dormio_AutoLoader::register();

define('TEST_PATH', dirname(__FILE__));

class Comment extends Dormio_Object {
	function display() {
		return $this->title;
	}
}

class Test_Logger {
	function log($message, $level=LOG_INFO) {
		fputs(STDOUT, $message . PHP_EOL);
	}
}

$GLOBALS['test_entities'] = include('data/entities.php');