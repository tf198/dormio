<?php
/**
 * Pluggable profiler interface
 * @author Tris Forster
 * @package Dormio/Interface
 */
interface Dormio_Profiler {
	/**
	 * Start profiling a code block
	 * @param string $group group name e.g. 'manager', 'schema' etc...
	 * @param string $identifier action identifier
	 */
	function begin($group, $identifier);
	
	/**
	 * Stop profiling a code block
	 * @param string $group group name e.g. 'manager', 'schema' etc...
	 * @param string $identifier action identifier
	 */
	function end($group, $identifier);
}