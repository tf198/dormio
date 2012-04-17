<?php
/**
* SQL Dialect factory
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
* @subpackage dialect
*/

/**
* Factory for language specific query generation.
* As lightweight as possible  - just takes care of the special cases
* Cached as likely to be many instances
* @package dormio
* @subpackage dialect
*/
class Dormio_Dialect {
  static $_cache = array();
  
  static function factory($lang) {
    switch($lang) {
      case 'sqlsrv':
      case 'dblib':
      case 'odbc':
        $lang = 'mssql';
        break;
      case 'sqlite':
        $lang = 'generic';
        break;
		}
    if(!isset(self::$_cache[$lang])) {
      $klass = "Dormio_Dialect_{$lang}";
      self::$_cache[$lang] = new $klass;
    }
    return self::$_cache[$lang];
    
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Dialect_Exception extends Exception {}

/**
* @package dormio
* @subpackage dialect
*/
class Dormio_Dialect_Generic {

  /**
  * Takes an array and turns it into a statement using simple logic
  * If a field has a value then "$FIELD $value" is appended to the statement
  * All value arrays are concatenated using commas, except 'where' which uses ' AND '
  */
  function compile($spec) {
    if(isset($spec['where'])) $spec['where'] = array(implode(' AND ', $spec['where']));
    foreach($spec as $key=>$value) {
      if($value) $result[] = str_replace("_", " ", strtoupper($key)) . " " . ((is_array($value)) ? implode(', ', $value) : $value);
    }
    return implode(' ', $result);
  }
  
  function select($spec) {
    $spec['select'] = array_unique($spec['select']);
    if(isset($spec['modifiers'])) {
      $spec['select'][0] = implode(' ', $spec['modifiers']) . ' ' . $spec['select'][0];
      $spec['modifiers'] = null;
    }
    if(isset($spec['join'])) {
      $spec['from'] = $spec['from'] . " " . implode(' ',$spec['join']);
      $spec['join'] = null;
    }
    return $this->quoteIdentifiers($this->aliasFields($this->compile($spec), true));
  }
  
  function update($spec, $fields, $custom_fields=array()) {
    $set = array();
    foreach($fields as $field) $set[] = "{$field}=?";
    $set = array_merge($set, $custom_fields);
    $set = implode(', ', $set);
    $base = "UPDATE {$spec['from']} SET {$set} ";
    if(isset($spec['join'])) {
      $spec['where'] = array("{$spec['select'][0]} IN ({$this->select($spec)})");
      $spec['join'] = null;
    }
    $spec['select'] = $spec['from'] = $spec['order_by'] = $spec['offset'] = null; // irrelevant fields
    return $this->quoteIdentifiers($this->aliasFields($base . $this->compile($spec), false));
  }
  
  function insert($spec, $fields) {
    $values = implode(', ', array_fill(0, count($fields), '?'));
    $fields = implode(', ', $fields);
    $sql = "INSERT INTO {$spec['from']} ({$fields}) VALUES ({$values})";
    return $this->quoteIdentifiers($this->aliasFields($sql, false));
  }
  
  function delete($spec) {
    if(isset($spec['join'])){
      $spec['where'] = array("{$spec['select'][0]} IN ({$this->select($spec)})");
      $spec['join'] = null;
    } 
    $spec['select'] = $spec['order_by'] = $spec['offset'] = null; // irrelevant fields
    return $this->quoteIdentifiers($this->aliasFields("DELETE " . $this->compile($spec), false));
  }
  
  function quoteFields($fields) {
    foreach($fields as $field) $result[] = '{' . $field . '}';
    return $result;
  }
  
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '""');
  }
  
  function aliasFields($sql, $should_alias) {
    if($should_alias) return str_replace('<@', '', str_replace('@>', '', $sql));
    return preg_replace('/<@.*?@>/', '', $sql);
  }
  
  /**
   * Get a list of current tables in the database
   * @todo Implement for other flavours
   */
  function tableNames() {
  	return "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence' ORDER BY name";
  }
}

/**
* @package dormio
* @subpackage dialect
*/
class Dormio_Dialect_MySQL extends Dormio_Dialect_Generic {
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '``');
  }
}

/**
* @package dormio
* @subpackage dialect
*/
class Dormio_Dialect_MSSQL extends Dormio_Dialect_Generic {
  function select($spec) {
    if(isset($spec['limit'])) $spec['modifiers'][] = "TOP {$spec['limit']}";
    $spec['limit'] = null;
    if(isset($spec['offset'])) throw new Dormio_Dialect_Exception('Offset not supported by MSSQL');
    return parent::select($spec);
  }
  
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '[]');
  }
}