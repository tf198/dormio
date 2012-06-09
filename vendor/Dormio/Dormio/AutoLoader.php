<?php
define('DORMIO_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

class Dormio_AutoLoader {
	static function autoload($className) {
		$filename = DORMIO_PATH . str_replace('_', DIRECTORY_SEPARATOR, $className) . ".php";
		if(is_readable($filename)) require $filename;
	}
	
	static function register() {
		spl_autoload_register('Dormio_AutoLoader::autoload');
	}
}