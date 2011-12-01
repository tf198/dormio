<?php
/**
* Dormio PDO Logger
*
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
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
* @package dormio
* @subpackage logger
*/

/**
 * PDO wrapper that logs all SQL executed for debugging
 * @package dormio
 * @subpackage logger
 */
class Dormio_Logging_PDO extends PDO {
  
  /**
   * SQL stack
   * @var array
   */
  public $stack = array();
  

  function __construct($dsn, $username=null, $password=null, $driver_options=null) {
    parent::__construct($dsn, $username, $password, $driver_options);
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
  
  /**
   * Prepare a statement and log 
   * @param string $sql the SQL to prepare
   * @return Dormio_Logging_PDOStatement a wrapped PDOStatement
   */
  function prepare($sql) {
    $stmt = parent::prepare($sql);
    $mock = new Dormio_Logging_PDOStatement($stmt);
    array_push($this->stack, array($sql, $mock));
    return $mock;
  }
  
  function exec($sql) {
    array_push($this->stack, $sql);
    return parent::exec($sql);
  }
  
  function digest() {
    if(count($this->stack)<1) return false;
    $q = array_shift($this->stack);
    return array( $q[0], $q[1]->stack );
  }
  
  function all() {
    return $this->stack();
  }
  
  function clear() {
    $this->stack = array();
  }
  
  function count() {
    return count($this->stack);
  }
  
  function dump() {
    
  }
}

class Dormio_Logging_PDOStatement {
  public $stack = array();

  function __construct($stmt) {
    $this->_stmt = $stmt;
  }
  
  function execute($params=array()) {
    array_push($this->stack, $params);
    return $this->_stmt->execute($params);
  }
  
  function __call($method, $args) {
    return call_user_func_array(array($this->_stmt, $method), $args);
  }
  
  function run_count() {
    return count($this->stack);
  }
  
}
?>