<?php
/**
 * Object to store connection and config objects
 * Contains methods for hydration, execution and low level database ops
 * @author Tris Forster
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
	private $config;

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
		$obj->_raw = $stmt->fetch(PDO::FETCH_ASSOC);
		return self::mapObject($obj->_raw, $obj);
	}

	function getManager($name) {
		return new Dormio_Manager($this->config->getEntity($name), $this);
	}

	function getObjectManager($name) {
		$key = "_{$name}_MANAGER";
		//if(!$stored = $this->cache->get($key)) {
			class_exists('Dormio_Manager');
			$obj = $this->getObject($name);
			//$this->bindRelated($obj);
			$stored = new Dormio_Manager_Object($obj);
			//$this->cache->set($key, $stored);
		//}
		return $stored;
	}

	function getObject($entity_name, $id=null) {
		$entity = $this->config->getEntity($entity_name);
		$class_name = $entity->model_class;
		$obj = new $class_name;
		$this->bind($obj, $entity_name);

		if($id !== null) {
			$this->load($obj, $id);
		}

		return $obj;
	}

	function insert($obj, $entity_name) {
		$this->bind($obj, $entity_name);
		return $this->_insert($obj);
	}
	
	function _insert($obj) {
		$params = array();
		foreach($obj->_entity->getFields() as $key=>$spec) {
			// TODO: need to handle related fields
			if($spec['is_field'] && isset($obj->$key)) {
				$params[$spec['db_column']] = $obj->$key;
			}
		}
		if(!$params) throw new Exception("No fields to update on entity [{$obj->_entity->name}]");

		$stmt = $this->_getInsert($obj->_entity, array_keys($params));
		$stmt->execute(array_values($params));
		$obj->pk = $this->pdo->lastInsertId();
	}

	function update($obj) {
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object hasn't been bound to Dormio");

		$params = array();
		foreach($obj->_entity->getFields() as $name=>$spec) {
			if($spec['is_field'] && isset($this->obj->$name)) {
				if($name == 'pk') continue;
				if(isset($obj->_raw[$name]) && $this->_raw[$name]==$obj->$name) continue;
				$params[$name] = $this->obj->$name;
				$this->_raw[$name] = $this->obj->$name;
			}
		}
		if(!$params) return;

		$query = array(
			'from' => $obj->_entity->table,
			'where' => array("{$obj->_entity->pk['db_column']} = ?")
			);

		$sql = $this->dormio->dialect->update($query, array_keys($params));

		$values = array_keys($params);
		array_unshift($values, $this->id);
		$this->dormio->execute($sql, $values);
	}

	function bind($obj, $entity_name) {
		if(!isset($obj->_is_bound)) {
			if(!$entity_name) $entity_name = get_class($obj);
			$obj->_entity = $this->config->getEntity($entity_name);
			$obj->dormio = $this;
			$obj->pk = null;
			$obj->_raw = array();
			$obj->_is_bound = true;
		}
		return $obj;
	}
	
	function bindRelated($obj, $field) {
		if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
		if(isset($obj->is_bound_related)) return $obj;

		try {
			$spec = $obj->_entity->getField($field);
		} catch(Dormio_Config_Exception $e) {
			throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no field [{$field}]");
		}
		if(!isset($spec['entity'])) throw new Dormio_Exception("Entity [{$obj->_entity->name}] has no related field [{$field}]");
		self::$logger && self::$logger->log("BIND {$spec['type']} {$obj->_entity->name}->{$field}");
		$manager = $this->getObjectManager($spec['entity']);
		switch($spec['type']) {
			case 'onetomany':
			case 'onetoone': // reverse end
				$local = $spec['local_field'];
				if(!isset($obj->$local)) $obj->$local = null;
				$manager->filterBind($spec['remote_field'], '=', $obj->$local, false);
				break;
			case 'manytomany':
				// need to find the field that links back
				$accessor = $this->config->getThroughAccessor($spec);
				$this->bindRelated($manager->obj, $accessor);
				$manager->filterBind("{$accessor}__{$spec['map_local_field']}", '=', $obj->pk, false);
				//var_dump($manager->select());
				break;
			default:
				var_dump($spec);
				throw new Dormio_Exception("Field [{$field}] is not a reverse related");
		}
		$obj->$field = $manager;
			
		$obj->_is_bound_related = true;
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
			$query = array('from' => $entity->table);
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

	static function mapObject($row, $obj) {
		self::$logger && self::$logger->log("MAP {$obj->_entity->name} {$row['pk']}");
		//var_dump($row);
		foreach($obj->_entity->getFields() as $key=>$spec) {
			
			// map related with local field
			if($spec['type'] == 'foreignkey' || $spec['type'] == 'onetoone') {
				if(!isset($obj->$key)) {
					$obj->$key = $obj->dormio->getObject($spec['entity']);
				}
				if(is_array($row[$key])) {
					// eager load
					self::mapObject($row[$key], $obj->$key);
				} else {
					// lazy
					self::clearObject($obj->$key);
					$obj->$key->pk = $row[$spec['local_field']];
					//echo "SET {$key} {$obj->$key->pk}\n";
				}
				continue;
			}
				
			// set or clear fields
			if($spec['is_field']) {
				//$obj->$key = (isset($row[$key])) ? $row[$key] : null;
				$obj->$key = $row[$key];
				//echo "SET {$key} {$obj->$key}\n";
			}
				
		}
		//var_dump(array_keys(get_object_vars($obj)));
		return $obj;
	}

	static function clearObject($obj) {
		if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
		foreach($obj->_entity->getFields() as $field=>$spec) unset($obj->$field);
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
	
	private $data, $obj;
	
	private $p, $c;
	
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
		return Dormio::mapObject($this->data[$offset], $this->obj);
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
		if(isset($this->data[$this->p])) Dormio::mapObject($this->data[$this->p], $this->obj);
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