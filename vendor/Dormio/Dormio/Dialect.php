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
 * @package Dormio/Dialect
 */

/**
 * Factory for language specific query generation.
 * As lightweight as possible  - just takes care of the special cases
 * Cached as likely to be many instances
 * @package Dormio/Dialect
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
 * @package Dormio/Exception
 */
class Dormio_Dialect_Exception extends Exception {}

/**
 * @package Dormio/Dialect
 */
class Dormio_Dialect_Generic {

	/**
	 * Takes an array and turns it into a statement using simple logic
	 * If a field has a value then "$FIELD $value" is appended to the statement
	 * All value arrays are concatenated using commas, except 'where' which uses ' AND '
	 * @param multitype:mixed $spec
	 * @return string			SQL statement
	 */
	function compile($spec) {
		if(isset($spec['where'])) $spec['where'] = array(implode(' AND ', $spec['where']));
		foreach($spec as $key=>$value) {
			if($value) $result[] = str_replace("_", " ", strtoupper($key)) . " " . ((is_array($value)) ? implode(', ', $value) : $value);
		}
		return implode(' ', $result);
	}

	/**
	 * Create a SELECT statement
	 * @param multitype:mixed $spec
	 * @return string			SQL statement
	 */
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

	/**
	 * Create an UPDATE statements
	 * @param multitype:mixed $spec
	 * @param multitype:mixed $fields
	 * @param multitype:mixed $custom_fields
	 * @return string					SQL statement
	 */
	function update($spec, $fields, $custom_fields=array()) {
		$set = array();
		foreach($fields as $field) $set[] = "{{$field}}=?";
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

	/**
	 * Create an INSERT statement
	 * @param multitype:mixed $spec
	 * @param multitype:string $fields list of fields
	 * @return string 	SQL statement
	 */
	function insert($spec, $fields) {
		$values = implode(', ', array_fill(0, count($fields), '?'));
		foreach($fields as &$field) $field = "{{$field}}";
		$fields = implode(', ', $fields);
		$sql = "INSERT INTO {$spec['from']} ({$fields}) VALUES ({$values})";
		return $this->quoteIdentifiers($this->aliasFields($sql, false));
	}

	/**
	 * Create a DELETE statement
	 * @param multitype:mixed $spec
	 * @return string 		SQL statement
	 */
	function delete($spec) {
		if(isset($spec['join'])){
			$spec['where'] = array("{$spec['select'][0]} IN ({$this->select($spec)})");
			$spec['join'] = null;
		}
		$spec['select'] = $spec['order_by'] = $spec['offset'] = null; // irrelevant fields
		return $this->quoteIdentifiers($this->aliasFields("DELETE " . $this->compile($spec), false));
	}

	/**
	 * Adds curly brackets around all input strings
	 * @param multitype:string $fields
	 * @return multitype:string
	 */
	function quoteFields($fields) {
		foreach($fields as $field) $result[] = '{' . $field . '}';
		return $result;
	}

	/**
	 * Quotes items in curly brackets
	 * e.g. 'SELECT * FROM {table}' > 'SELECT * FROM "table"'
	 * @param unknown_type $sql
	 * @return string
	 */
	function quoteIdentifiers($sql) {
		return strtr($sql, '{}', '""');
	}

	/**
	 * Removes any aliases in the string
	 * e.g '<@t2.@>field' > 't2.field' or 'field'
	 * @param string $sql  input
	 * @param boolean $should_alias 	remove alias if false
	 * @return string
	 */
	function aliasFields($sql, $should_alias) {
		if($should_alias) return str_replace('<@', '', str_replace('@>', '', $sql));
		return preg_replace('/<@.*?@>/', '', $sql);
	}

	/**
	 * Get a list of current tables in the database
	 * Must return a single column
	 * @return string	 	SQL statement
	 */
	function tableNames() {
		return "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence' ORDER BY name";
	}
}

/**
 * @package Dormio/Dialect
 */
class Dormio_Dialect_MySQL extends Dormio_Dialect_Generic {
	function quoteIdentifiers($sql) {
		return strtr($sql, '{}', '``');
	}

	function tableNames() {
		return "SHOW TABLES";
	}
}

/**
 * @package Dormio/Dialect
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

	function tableNames() {
		return "SELECT name FROM sys.tables";
	}
}