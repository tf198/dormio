<?php
/**
 * Plugable logger interface
 * @author Tris Forster
 * @package Dormio/Interface
 */
interface Dormio_Logger {
	/**
	 * Log a message
	 * @param string $message message to log
	 * @param int $level one of the PHP LOG_XXX constants
	 */
	function log($message, $level);
}