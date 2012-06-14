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
 */

/**
 * Object to store connection and config objects
 * Contains methods for hydration, execution and low level database ops
 * @package Dormio
 */
class Dormio {
	/**
	 * Database object
	 * @var PDO
	 */
	public $pdo;

	/**
	 * Entity configuration
	 * @var Dormio_Config
	 */
	public $config;

	/**
	 * Dialect for the underlying database
	 * @var Dormio_Dialect_Generic
	 */
	public $dialect;

	/**
	 * Generic cache interface implements get($key) and set($key, $value)
	 * @var Object
	 */
	public $cache;
	
	/**
	 * Pluggable logger
	 * @var Dormio_Logger
	 */
	public static $logger;
	
	/**
	 * Pluggable profiler
	 * implements `start($name)` and `stop($name)` methods
	 * @var Dormio_Profiler
	 */
	public static $profiler;

	/**
	 * 
	 * @param unknown_type $pdo
	 * @param unknown_type $config
	 * @param unknown_type $cache
	 */
	public function __construct($pdo, $config, $cache=null) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if(get_class($pdo) == 'Dormio_Logging_PDO') Dormio_Logging_PDO::$logger = &self::$logger;
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

		// use a basic cache unless a better one is available
		if(!$cache) $cache = new Dormio_Cache;
		$this->cache = $cache;
	}

	function save($obj, $entity_name=null) {
		//echo "SAVE {$obj}\n";
		if(isset($obj->pk)) {
			$this->update($obj);
		} else {
			$this->insert($obj, $entity_name);
		}
	}

	function load($obj, $id) {
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object not bound to dormio");

		$stmt = $this->_getSelect($obj->_entity);
		$stmt->execute(array($id));
		
		$obj->_data = $stmt->fetch(PDO::FETCH_ASSOC);
		if(!$obj->_data) throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no record with primary key {$id}");
		
		foreach($obj->_data as $key=>$value) $obj->$key = $value;
		return $obj;
	}

	function getManager($name) {
		$key = "_{$name}_MANAGER";
		if(!$stored = $this->cache->get($key)) {
			$stored = new Dormio_Manager($this->config->getEntity($name), $this);
			$this->cache->set($key, $stored);
		}
		return $stored;
	}

	function getObject($entity_name, $id=null, $lazy=false) {
		$entity = $this->config->getEntity($entity_name);
		$class_name = $entity->model_class;
		$obj = new $class_name;
		$this->bind($obj, $entity_name);

		if($id !== null) {
			if($lazy) {
				$obj->pk = $id;
			} else {
				$this->load($obj, $id);
			}
		}

		return $obj;
	}

	/**
	 * Do an INSERT for an object
	 * Object can be bound or unbound
	 * @param Object $obj
	 * @param string $entity_name
	 * @return boolean
	 */
	function insert($obj, $entity_name=null) {
		$this->bind($obj, $entity_name);
		return $this->_insert($obj);
	}
	
	function _params($obj) {
		if(!isset($obj->_raw)) throw new Dormio_Exception("No raw data to compare to");
		
		$params = array();
		foreach($obj->_entity->getFields() as $name=>$spec) {
			if($spec['is_field'] && isset($obj->$name)) {
				$value = $obj->$name;
				if(is_object($value)) $value = $value->pk;
				
				// ignore unchanged items
				if(isset($obj->_raw[$name]) && $obj->_raw[$name] == $value) continue;
				
				$params[$spec['db_column']] = $value;
				$obj->_raw[$name] = $value;
			}
		}
		return $params;
	}
	
	function _insert($obj) {
		//echo "_INSERT {$obj}\n";
		$obj->_raw = array();
		$params = $this->_params($obj);
		if(!$params) throw new Exception("No fields to update on entity [{$obj->_entity->name}]");

		$stmt = $this->_getInsert($obj->_entity, array_keys($params));
		$stmt->execute(array_values($params));
		$obj->pk = $this->pdo->lastInsertId();
		$obj->_raw['pk'] = $obj->pk;
		return true;
	}

	function update($obj) {
		//echo "UPDATE {$obj}\n";
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object hasn't been bound to Dormio");
		if(!isset($obj->_raw)) throw new Dormio_Exception("Object has no previous data");
		
		$params = $this->_params($obj);
		if(!$params) {
			echo "Nothing to save\n";
			return false;
		}
		if(isset($params[$obj->_entity->pk['db_column']])) throw new Dormio_Exception("Unable to updated PK");

		$query = array(
			'from' => "{{$obj->_entity->table}}",
			'where' => array("{{$obj->_entity->pk['db_column']}} = ?")
			);

		$sql = $this->dialect->update($query, array_keys($params));

		$values = array_values($params);
		$values[] = $obj->pk;
		$this->executeQuery(array($sql, $values));
		return true;
	}
	
	function delete($obj) {
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object not bound to Dormio");
		
		$q = new Dormio_Query($obj->_entity, $this->dialect);
		$sql =$q->deleteById($obj->pk);
		$i = 0;
		foreach($sql as $q) $i += $this->executeQuery($q, true);
		return $i;
	}

	function bind($obj, $entity_name) {
		if(!isset($obj->_is_bound)) {
			if(!$entity_name) $entity_name = get_class($obj);
			$obj->_entity = $this->config->getEntity($entity_name);
			$obj->dormio = $this;
			$obj->pk = null;
			$obj->_is_bound = true;
		}
		return $obj;
	}
	
	function getRelated($obj, $field) {
		if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
		if(isset($obj->is_bound_related)) return $obj;

		try {
			$spec = $obj->_entity->getField($field);
		} catch(Dormio_Config_Exception $e) {
			throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no field [{$field}]");
		}
		if(!isset($spec['entity'])) throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no related field [{$field}]");
		self::$logger && self::$logger->log("BIND {$spec['type']} {$obj->_entity->name}->{$field}", LOG_DEBUG);
		
		$entity = $this->config->getEntity($spec['entity']);
		$type = $spec['type'];
		switch($type) {
			case 'foreignkey':
			case 'onetoone':
				return new Dormio_Manager_OneToOne($entity, $this, $obj, $spec);
			case 'onetomany':
				return new Dormio_Manager_OneToMany($entity, $this, $obj, $spec);
			case 'manytomany':
				return new Dormio_Manager_ManyToMany($entity, $this, $obj, $spec);
			default:
				throw new Dormio_Exception("Field [{$field}] has an unexpected related type [{$type}]");
		}
		
		return $obj->$field;
	}
	
	function _getSelect($entity) {
		$key = "_{$entity->name}_SELECT";
		if(!$stored = $this->cache->get($key)) {
				
			$fields = array();
			foreach($entity->getFields() as $field=>$spec) {
				if($spec['is_field']) {
					$s = "{{$spec['db_column']}}";
					if($spec['db_column'] != $field) $s .= " AS {{$field}}";
					$fields[] = $s;
				}
			}
				
			$query = array(
				'select' => $fields,
				'from' => "{{$entity->table}}",
				'where' => array("{{$entity->pk['db_column']}} = ?"),
			);
			$sql = $this->dialect->select($query);
			$stored = $this->pdo->prepare($sql);
			$this->cache->set($key, $stored);
		}
		return $stored;
	}

	function _getInsert(Dormio_Config_Entity $entity, array $params) {
		$key = "_{$entity->name}_INSERT_" . implode('_', $params);
		if(!$stored = $this->cache->get($key)) {
			$query = array('from' => "{{$entity->table}}");
			$q = $this->dialect->insert($query, $params);
			$stored = $this->pdo->prepare($q);
			$this->cache->set($key, $stored);
				
		}
		return $stored;
	}

	function executeQuery($query, $row_count=false) {
		$stmt = $this->pdo->prepare($query[0]);
		$stmt->execute($query[1]);
		return ($row_count) ? $stmt->rowCount() : $stmt;
	}

	static function mapArray($row, $reverse) {
		$result = array();
		foreach($row as $key=>$value) {
			$parts = isset($reverse[$key]) ? explode('__', $reverse[$key]) : array($key);
			$arr = &$result;
			$p = count($parts)-1;
			for($i=0; $i<$p; $i++) {
				$arr = &$arr[$parts[$i]];
				if(!is_array($arr)) $arr = array('pk' => $arr); // convert id to array
			}
			$arr[$parts[$p]] = $value;
		}
		return $result;
	}
}

/**
 * Simple cache implementation
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_Cache {
	/**
	 * Cache our statements
	 * @var multitype:PDOStatement
	 */
	public $_stored = array();

	/**
	 * Get a value from the cache
	 * Returns false if not in cache
	 * @param string $key
	 * @return mixed
	 */
	function get($key) {
		if(isset($this->_stored[$key])) return $this->_stored[$key];
		return false;
	}

	/**
	 * Set a value
	 * @param string $key
	 * @param mixed $value
	 */
	function set($key, $value) {
		Dormio::$logger && Dormio::$logger->log("SET: {$key}", LOG_DEBUG);
		$this->_stored[$key] = $value;
		//echo "CREATED {$key}\n";
	}

	/**
	 * Remove all items from cache
	 */
	function clear() {
		$this->_stored = array();
	}
}

/**
 * Array-type object for iterating results as Objects
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_ObjectSet implements ArrayAccess, Countable, Iterator {
	
	private $data, $obj, $fields;
	
	private $p, $c;
	
	/**
	 * 
	 * @param multitype:mixed $data
	 * @param Dormio_Object $obj
	 */
	function __construct($data, $obj, $fields) {
		$this->data = $data;
		$this->obj = $obj;
		$this->c = count($data);
		$this->fields = $fields;
		//var_dump($fields);
	}
	
	function offsetExists($offset) {
		return isset($this->data[$offset]);
	}
	
	function offsetGet($offset) {
		$this->obj->_data = $this->data[$offset];
		
		//var_dump($this->obj->_data);
		foreach($this->fields as $key=>$field) {
			$this->obj->$key = $this->obj->_data[$field];
		}
		return $this->obj;
	}
	
	function offsetSet($offset, $value) {
		throw new Dormio_Exeption('Cannot update ObjectSet items');
	}
	
	function offsetUnset($offset) {
		throw new Dormio_Exception('Cannot unset ObjectSet items');
	}
	
	function count() {
		return count($this->data);
	}
	
	function rewind() {
		$this->p = -1;
		$this->next();
	}
	
	function current() {
		return $this->obj;
	}
	
	function key() {
		return $this->p;
	}
	
	function next() {
		$this->p++;
		// need to map here as current() gets called multiple times for each row
		if(isset($this->data[$this->p])) {
			$this->offsetGet($this->p);
		} else {
			$this->obj = null;
		}
		//Dormio::mapObject($this->data[$this->p], $this->obj);
	}
	
	function valid() {
		return ($this->p < $this->c); 
	}
}

/**
 * @package Dormio
 *
 */
class Dormio_Exception extends Exception {}