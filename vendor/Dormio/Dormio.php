<?php
/**
 * Object to store connection and config objects
 * Contains methods for hydration, execution and low level database ops
 * @author Tris Forster
 *
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
	
	private $cache;
	
	private $_stored = array();
	
	public function __construct($pdo, $config, $cache=null) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
		
		// use ourselves as a cache unless a more efficient one is available
		$this->cache = $cache ? $cache : $this;
	}
	
	function save($obj, $entity_name=null) {
		$this->getProxy($obj, $entity_name)->save();
		return $obj;
	}
	
	function load($obj, $id, $entity_name=null) {
		$this->getProxy($obj, $entity_name)->load($id);
		return $obj;
	}
	
	function getProxy($obj, $entity_name=null) {
		if(!isset($obj->proxy)) {
			if(!$entity_name) $entity_name = get_class($obj);
			$entity = $this->config->getEntity($entity_name);
				
			$obj->proxy = new Dormio_Proxy($obj, $entity, $this);
		}
		return $obj->proxy;
	}
	
	function getStoredResultset($stored, $params) {
		list($query, $argc, $entity, $reverse, $mapper) = $stored;
		$entity = $this->config->getEntity($entity);
		return new DormioResultSet($query, $this, $entity, $reverse, $mapper);
	}
	
	function get($key) {
		if(isset($this->_stored[$key])) return $this->_stored[$key];
		return false;
	}
	
	function set($key, $value) {
		$this->_stored[$key] = $value;
		//echo "CREATED {$key}\n";
	}
	
	function getObject($entity, $id=null) {
	
		if(is_string($entity)) $entity = $this->config->getEntity($entity);
		$class_name = (class_exists($entity->name)) ? $entity->name : 'stdClass';
	
		if($id === null) {
			$obj = new $class_name;
			$obj->entity = $entity;
			return $obj;
		}
	
		$stmt = $this->_getSelect($entity);
		$stmt->execute(array($id));
		$obj = $stmt->fetchObject($class_name);
		$obj->entity = $entity;
		return $obj;
	}
	
	function insert($obj, $entity) {
		if(is_string($entity)) $entity = $this->config->getEntity($entity);
		
		$params = array();
		foreach($entity->getFields() as $key=>$spec) {
			if($spec['is_field'] && isset($obj->$key)) {
				$params[$spec['db_column']] = $obj->$key;
			}
		}
		if(!$params) throw new Exception("No fields to update on entity [{$entity->name}]");
		
		$stmt = $this->_getInsert($entity, array_keys($params));
		$stmt->execute(array_values($params));
		$obj->pk = $this->pdo->lastInsertId();
	}
	
	function _getSelect($entity) {
		$key = "_{$entity->name}_SELECT";
		if(!$stored = $this->cache->get($key)) {
			
			$fields = array();
			foreach($entity->getFields() as $field=>$spec) {
				if($spec['is_field']) $fields[] = "{{$spec['db_column']}}";
			}
			
			$query = array(
				'select' => $fields,
				'from' => $entity->table,
				'where' => array($entity->pk['db_column'] . '=?'),
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
		//var_dump($query);
		$stmt = $this->pdo->prepare($query[0]);
		$stmt->execute($query[1]);
		return ($row_count) ? $stmt->rowCount() : $stmt;
	}
	
	static function mapArray($row, $reverse) {
		$result = array();
		foreach($row as $key=>$value) {
			$parts = explode('__', $reverse[$key]);
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
	
	static function mapObject($row, $obj, $entity, $reverse) {
		$data = self::mapArray($row, $reverse);
		foreach($entity->getFields() as $key=>$spec) {
			$obj->$key = $data[$key];
		}
		return $obj;
	}
}

class DormioResultSet implements Iterator {
	
	private $dormio, $entity, $reverse, $iter;
	
	/**
	 * @todo Implement PDOStatement iterator
	 * @param multitype:mixed $query
	 * @param Dormio $dormio
	 * @param multitype:string $reverse
	 * @param string $mapper
	 */
	function __construct($iter, $dormio, $entity, $reverse) {
		$this->dormio = $dormio;
		$this->reverse = $reverse;
		$this->entity = $entity;
		$this->iter = $iter;
		$this->obj = new stdClass;
	}
	
	function rewind() {
		$this->iter->rewind();
	}
	
	function key() {
		return $this->iter->key();
	}
	
	function valid() {
		return $this->iter->valid();
	}
	
	function current() {
		return Dormio::mapObject($this->iter->current(), $this->obj, $this->entity, $this->reverse);
	}
	
	function next() {
		$this->iter->next();
	}
}

class Dormio_Exception extends Exception {}