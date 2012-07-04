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

	private static $instances = array();
	
	/**
	 * 
	 * @param PDO $pdo
	 * @param Dormio_Config $config
	 * @param Dormio_Cache $cache
	 */
	public function __construct(PDO $pdo, Dormio_Config $config, $cache=null) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if(get_class($pdo) == 'Dormio_Logging_PDO') Dormio_Logging_PDO::$logger = &self::$logger;
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

		// use a basic cache unless a better one is available
		if(!$cache) $cache = new Dormio_Cache;
		$this->cache = $cache;
	}
	
	/**
	 * Act as an instance manager for easy framework integration
	 * @param string $name
	 * @throws Dormio_Exception
	 * @return Dormio
	 */
	public static function instance($name='default') {
		if(!isset(self::$instances[$name])) throw new Dormio_Exception("Instance [{$name}] not configured");
		return self::$instances[$name];
	}
	
	/**
	 * Register an instance
	 * @param string $name
	 * @param PDO $pdo
	 * @param Dormio_Config $config
	 */
	public static function createInstance($name, PDO $pdo, Dormio_Config $config) {
		self::$instances[$name] = new Dormio($pdo, $config);
	}

	/**
	 * Get a manager for the named entity
	 * @param string $name
	 * @return Dormio_Manager
	 */
	function getManager($name) {
		return new Dormio_Manager($this->config->getEntity($name), $this);
	}
	
	/**
	 * Get an object representing an entity
	 * @param string $entity_name
	 * @param string $id
	 * @return Dormio_Object
	 */
	function getObject($entity_name, $id=null) {
		$entity = $this->config->getEntity($entity_name);
		return $this->getObjectFromEntity($entity, $id);
	}
	
	/**
	 * Get an object representing an entity
	 * @param Dormio_Config_Entity $entity
	 * @param string $id
	 * @return Dormio_Object
	 */
	function getObjectFromEntity(Dormio_Config_Entity $entity, $id=null) {
		$class_name = $entity->model_class;
		$obj = new $class_name($this, $entity);
		
		if($id) $obj->setPrimaryKey($id);

		return $obj;
	}
	
	/**
	 * Convert array keys from field names to column names
	 * Also converts objects to their related field
	 * @param multitype:mixed $fields
	 * @param Dormio_Config_Entity $entity
	 * @return multitype:string
	 */
	function fieldsToColumns(array $fields, Dormio_Config_Entity $entity) {
		$params = array();
		foreach($fields as $key=>$value) {
			$spec = $entity->getField($key);
			if($value instanceof Dormio_Object) $value = $value->getFieldValue($spec['remote_field']);
			$params[$spec['db_column']] = $this->dialect->toDB($value, $spec['type']);
		}
		return $params;
	}
	
	/**
	 * Get data for an entity by primary key
	 * @param Dormio_Config_Entity $entity
	 * @param string $id
	 * @return multitype:string
	 */
	function selectEntity(Dormio_Config_Entity $entity, $id) {
		$stmt = $this->_getSelect($entity);
		$stmt->execute(array($id));
		
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		if(!$data) throw new Dormio_Exception("{$entity} has no record with primary key {$id}");
		return $data;
	}
	
	/**
	 * Insert data for an entity and return the new primary key
	 * @param Dormio_Config_Entity $entity
	 * @param multitype:mixed $params
	 * @return string	inserted primary key
	 */
	function insertEntity(Dormio_Config_Entity $entity, $params) {
		if(!$params) throw new Exception("No fields to insert on entity [{$entity->name}]");
		$params = $this->fieldsToColumns($params, $entity);
		
		$stmt = $this->_getInsert($entity, array_keys($params));
		$stmt->execute(array_values($params));
		$id = $this->pdo->lastInsertId();
		self::$logger && self::$logger->log("Inserted entity [{$entity->name}] with pk {$id}");
		return $id;
	}
	
	/**
	 * Update data for an entity by primary key
	 * Can be safely run with no params for no action
	 * Returns whether an UPDATE was required
	 * @param Dormio_Config_Entity $entity
	 * @param string $id
	 * @param multitype:mixed $params
	 * @return bool
	 */
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

	/**
	 * Delete an entity by primary key
	 * Will delete related entities where cascade is set.
	 * Returns the total number of rows deleted
	 * Enter description here ...
	 * @param Dormio_Config_Entity $entity
	 * @param string $id
	 * @return int number of rows deleted
	 */
	function deleteEntity(Dormio_Config_Entity $entity, $id) {
		$q = new Dormio_Query($entity, $this->dialect);
		$sql =$q->deleteById($id);
		$i = 0;
		foreach($sql as $q) $i += $this->executeQuery($q, true);
		return $i;
	}

	/**
	 * Get manager for an entity by field name
	 * @param Dormio_Config_Entity $entity
	 * @param string $field
	 * @throws Dormio_Exception
	 * @return Dormio_Manager_Related
	 */
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
			case 'onetomany': // inverse foreignkey
				return new Dormio_Manager_OneToMany($related_entity, $this, $spec);
			case 'manytomany':
				return new Dormio_Manager_ManyToMany($related_entity, $this, $spec);
			default:
				throw new Dormio_Exception("Field [{$field}] has an unexpected related type [{$type}]");
		}
	}

	/**
	 * Creates and caches a SELECT statement for load by primary key
	 * @param Dormio_Config_Entity $entity
	 * @return PDOStatement
	 */
	function _getSelect(Dormio_Config_Entity $entity) {
		$key = "_SELECT:{$entity->name}";
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

	/**
	 * Creates and caches an INSERT statement for an entity
	 * One query cached for each set of params
	 * @param Dormio_Config_Entity $entity
	 * @param array $params
	 * @return PDOStatement
	 */
	function _getInsert(Dormio_Config_Entity $entity, array $params) {
		$key = "_INSERT:{$entity->name}:" . implode(':', $params);
		if(!$stored = $this->cache->get($key)) {
			$query = array('from' => "{{$entity->table}}");
			$q = $this->dialect->insert($query, $params);
			$stored = $this->pdo->prepare($q);
			$this->cache->set($key, $stored);
				
		}
		return $stored;
	}

	/**
	 * Execute a query pair
	 * Returns an open statement or the number of rows affected
	 * @param multitype:mixed $query array($sql, $params)
	 * @param bool $row_count return row count instead of statement
	 * @return PDOStatement|int
	 */
	function executeQuery($query, $row_count=false) {
		$stmt = $this->pdo->prepare($query[0]);
		$stmt->execute($query[1]);
		return ($row_count) ? $stmt->rowCount() : $stmt;
	}
	
	/**
	 * Filter data for related field
	 * @param multitype:string $arr original data
	 * @param string $name field name
	 * @return multitype:string
	 */
	static function getRelatedData($arr, $name) {
		Dormio::$logger && Dormio::$logger->log("getRelatedData: {$name}");
		$prefix = $name . '__';
		$l = strlen($prefix);
		$related = array();
		foreach($arr as $path=>$field) {
			if(substr($path, 0, $l) == $prefix) {
				$related[substr($path, $l)] = $field;
			}
		}
		return $related;
	}
	
	static function title($input) {
		return ucwords(str_replace('_', ' ', $input));
	}
	
	static  function URL($params=array()) {
	  	$params = array_merge($_GET, $params);
	  	$url = $_SERVER['SCRIPT_NAME'];
	  	if(isset($_SERVER['PATH_INFO'])) $url .= $_SERVER['PATH_INFO'];
	  	
	  	foreach($params as $key=>&$value) $value = urlencode($key) . "=" . urlencode($value);
	  	if($params) $url .= "?" . implode('&', $params);
	  	return $url;
	}
}

/**
 * Simple cache implementation
 * Should be easy to wrap a decent cache (e.g. APC) and swap this out.
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
	
	/**
	 * Source data
	 * @var multitype:multitype:string
	 */
	private $data;
	
	/**
	 * @var Dormio_Object
	 */
	private $obj;
	
	/**
	 * Internal data pointer
	 * @var int
	 */
	private $p;
	
	/**
	 * Number of rows
	 * @var int
	 */
	private $c;
	
	/**
	 * 
	 * @param multitype:mixed $data
	 * @param Dormio_Object $obj
	 */
	function __construct($data, $obj) {
		$this->data = $data;
		$this->obj = $obj;
		$this->c = count($data);
	}
	
	function offsetExists($offset) {
		return isset($this->data[$offset]);
	}
	
	function offsetGet($offset) {
		Dormio::$logger && Dormio::$logger->log('offsetGet: {$offset}');
		$this->obj->setData($this->data[$offset]);
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
			$this->obj->setData($this->data[$this->p]);
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