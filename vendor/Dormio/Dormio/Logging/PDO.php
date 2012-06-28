<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Tris Forster <tris.701437@tfconsulting.com.au>
 * @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
 * @package Dormio
 * @subpackage Logging
 */

/**
 * PDO wrapper that logs all SQL executed for debugging
 * @package Dormio
 * @subpackage Logging
 */
class Dormio_Logging_PDO extends PDO {

	/**
	 * SQL stack
	 * @var array
	 */
	public $stack = array();

	private $id = 0;

	/**
	 * Pluggable logger
	 * @var Dormio_Logger
	 */
	public static $logger = null;


	function __construct($dsn, $username=null, $password=null, $driver_options=null) {
		parent::__construct($dsn, $username, $password, $driver_options);
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * Prepare a statement and log
	 * @param string $sql the SQL to prepare
	 * @return Dormio_Logging_PDOStatement a wrapped PDOStatement
	 */
	function prepare($sql, $driver_options=array()) {
		$stmt = parent::prepare($sql, $driver_options);
		//$id = count($this->stack);
		self::$logger && self::$logger->log("<PREPARE:{$this->id}>: {$sql}", LOG_DEBUG);
		$mock = new Dormio_Logging_PDOStatement($stmt, $this->id++, $this);
		//array_push($this->stack, array($sql, &$mock->stack));
		return $mock;
	}

	function exec($sql) {
		$id = count($this->stack);
		$sql = trim($sql);
		array_push($this->stack, array($sql, array()));
		self::$logger && self::$logger->log("<EXEC:{$id}>: {$sql}", LOG_DEBUG);
		return parent::exec($sql);
	}

	function digest() {
		if(count($this->stack)<1) throw new Dormio_Exception("No undigested queries");
		return array_shift($this->stack);
	}

	function all() {
		return $this->stack();
	}

	function clear() {
		$this->stack = array();
		$this->id = 0;
	}

	function count() {
		return count($this->stack);
	}
	
	function statementsPrepared() {
		return $this->id;
	}

	function getSQL() {
		$result = $this->stack;
		foreach($result as &$pair) {
			$pair[1] = self::formatParams($pair[1]);
		}
		return $result;
	}

	static function formatParams($params) {
		$result = array();
		foreach($params as $p) $result[] = var_export($p, true);
		return '(' . implode(', ', $result) . ')';
	}
}

/**
 * Mock PDOStatement object
 * @package Dormio
 * @subpackage Logging
 */
class Dormio_Logging_PDOStatement {
	//public $stack = array();

	/**
	 * Wrapped statement
	 * @var PDOStatement
	 */
	public $_stmt;

	function __construct($stmt, $id, $parent) {
		$this->_stmt = $stmt;
		$this->id = $id;
		$this->parent = $parent;
	}

	function execute($params=array()) {
		Dormio_Logging_PDO::$logger && Dormio_Logging_PDO::$logger->log("<EXECUTE:{$this->id}>: " . Dormio_Logging_PDO::formatParams($params), LOG_DEBUG);
		$result = $this->_stmt->execute($params);
		//array_push($this->stack, array_map('trim', $params)); // copy the reference
		$this->parent->stack[] = array($this->_stmt->queryString, array_map('trim', $params));
		return $result;
	}

	function __call($method, $args) {
		return call_user_func_array(array($this->_stmt, $method), $args);
	}

	#function run_count() {
	#  return count($this->stack);
	#}

}
