<?php
require dirname(__DIR__) . "/vendor/Dormio/Dormio/AutoLoader.php";
Dormio_AutoLoader::register();

define('TEST_PATH', dirname(__FILE__));

class Comment extends Dormio_Object {
	function __toString() {
		return $this->title;
	}
}

$GLOBALS['test_entities'] = include('data/entities.php');