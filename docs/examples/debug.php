<?php
/**
 * @package Dormio/Examples
 */

/**
 * Simple logger implementation
 * @package Dormio/Examples
 *
 */

class Logger implements Dormio_Logger{
	function log($message, $level=LOG_INFO) {
		echo $message . "<br/>" . PHP_EOL;
	}
}
$pdo = new Dormio_Logging_PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Dormio::$logger = new Logger;
$pdo::$logger = &Dormio::$logger;