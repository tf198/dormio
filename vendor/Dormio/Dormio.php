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

	function getManager($name) {
		$key = "_{$name}_MANAGER";
		if(!$stored = $this->cache->get($key)) {
			$stored = new Dormio_Manager($this->config->getEntity($name), $this);
			$this->cache->set($key, $stored);
		}
		return $stored;
	}
	
	function getObject($entity_name, $id=null) {
		$entity = $this->config->getEntity($entity_name);
		return $this->getObjectFromEntity($entity, $id);
	}
	
	function getObjectFromEntity(Dormio_Config_Entity $entity, $id=null) {
		$class_name = $entity->model_class;
		$obj = new $class_name($this, $entity);
		
		$obj->setPrimaryKey($id);

		return $obj;
	}
	
	function fieldsToColumns(array $fields, Dormio_Config_Entity $entity) {
		$params = array();
		foreach($fields as $key=>$value) {
			$spec = $entity->getField($key);
			if(is_object($value)) $value = $value->getFieldValue($spec['remote_field']);
			$params[$spec['db_column']] = $value;
		}
		//var_dump($params);
		return $params;
	}
	
	function selectEntity($entity, $id) {
		$stmt = $this->_getSelect($entity);
		$stmt->execute(array($id));
		
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		if(!$data) throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no record with primary key {$id}");
		return $data;
	}
	
	function insertEntity($entity, $params) {
		if(!$params) throw new Exception("No fields to insert on entity [{$entity->name}]");
		$params = $this->fieldsToColumns($params, $entity);
		
		$stmt = $this->_getInsert($entity, array_keys($params));
		$stmt->execute(array_values($params));
		$id = $this->pdo->lastInsertId();
		self::$logger && self::$logger->log("Inserted entity [{$entity->name}] with pk {$id}");
		return $id;
	}
	
	function updateEntity($entity, $id, $params) {
		if(!$params) {
			self::$logger && self::$logger->log("Nothing to update");
			return false;
		}
		
		$params = $this->fieldsToColumns($params, $entity);
		
		$query = array(
			'from' => "{{$entity->table}}",
			'where' => array("{{$entity->pk['db_column']}} = ?")
			);

		$sql = $this->dialect->update($query, array_keys($params));

		$values = array_values($params);
		$values[] = $id;
		$this->executeQuery(array($sql, $values));
		self::$logger && self::$logger->log("Updated entity [{$entity->name}] with pk {$id}");
		return true;
	}

	function deleteEntity(Dormio_Config_Entity $entity, $id) {
		$q = new Dormio_Query($entity, $this->dialect);
		$sql =$q->deleteById($id);
		$i = 0;
		foreach($sql as $q) $i += $this->executeQuery($q, true);
		return $i;
	}

	function getRelatedManager(Dormio_Config_Entity $entity, $field) {
		try {
			$spec = $entity->getField($field);
		} catch(Dormio_Config_Exception $e) {
			throw new Dormio_Exception("{$entity} has no field [{$field}]");
		}
		if(!isset($spec['entity'])) throw new Dormio_Exception("{$entity} has no related field [{$field}]");
		self::$logger && self::$logger->log("BIND {$spec['type']} {$entity}->{$field}", LOG_DEBUG);
		
		$related_entity = $this->config->getEntity($spec['entity']);
		$type = $spec['type'];
		class_exists('Dormio_Manager');
		switch($type) {
			case 'onetoone':
				return new Dormio_Manager_OneToOne($related_entity, $this, $spec);
			case 'onetomany':
				return new Dormio_Manager_OneToMany($related_entity, $this, $spec);
			case 'manytomany':
				return new Dormio_Manager_ManyToMany($related_entity, $this, $spec);
			default:
				throw new Dormio_Exception("Field [{$field}] has an unexpected related type [{$type}]");
		}
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

class Dormio_ResultMapper implements ArrayAccess{
	
	private $map, $raw;
	
	function __construct($map) {
		$this->map = $map;
	}
	
	function setRawData($raw) {
		$this->raw = $raw;
	}
	
	function offsetGet($offset) {
		if(!$this->raw) throw new Dormio_Exception("No raw data provided");
		$key = $this->map[$offset];
		return $this->raw[$key];
	}
	
	function offsetSet($offset, $value) {
		throw new Dormio_Exception("Dormio_ResultMapper is not mutable");
	}
	
	function offsetExists($offset) {
		return isset($this->map[$offset]);
	}
	
	function offsetUnset($offset) {
		unset($this->raw[$this->map[$offset]]);
		unset($this->map[$offset]);
	}
	
	function getChildMapper($name, $remote_field) {
		$prefix = $name . '__';
		$l = strlen($prefix);
		$child_map = array();
		foreach($this->map as $path=>$field) {
			if(substr($path, 0, $l) == $prefix) {
				$child_map[substr($path, $l)] = $field;
			}
		}
		// for lazy loading we need to add the related field
		if(!isset($child_map[$remote_field])) $child_map[$remote_field] = $this->map[$name];
		$child = new Dormio_ResultMapper($child_map);
		$child->setRawData($this->raw);
		return $child;
	}
}

/**
 * Array-type object for iterating results as Objects
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_ObjectSet implements ArrayAccess, Countable, Iterator {
	
	private $data, $obj, $mapper;
	
	private $p, $c;
	
	/**
	 * 
	 * @param multitype:mixed $data
	 * @param Dormio_Object $obj
	 */
	function __construct($data, $obj, $reverse) {
		$this->data = $data;
		$this->obj = $obj;
		$this->c = count($data);
		$this->mapper = new Dormio_ResultMapper($reverse);
	}
	
	function offsetExists($offset) {
		return isset($this->data[$offset]);
	}
	
	function offsetGet($offset) {
		$this->mapper->setRawData($this->data[$offset]);
		$this->obj->setData($this->mapper);
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
			$this->mapper->setRawData($this->data[$this->p]);
			$this->obj->setData($this->mapper);
		}
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