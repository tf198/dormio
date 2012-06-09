<?php
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
	 * @var Dormio_Dialect
	 */
	public $dialect;
	
	static $_stmt_cache = array();
	
	public function __construct($pdo, $config) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
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
		if(isset(self::$_stmt_cache[$key])) return self::$_stmt_cache[$key];
		return false;
	}
	
	function set($key, $value) {
		self::$_stmt_cache[$key] = $value;
	}
	
	function getObject($entity, $id=null) {
		
		if(is_string($entity)) $entity = $this->config->getEntity($entity);
		$class_name = (class_exists($entity->name)) ? $entity->name : 'stdClass';
		$obj = new $class_name;
		/*
		$obj->proxy = new Dormio_Proxy($obj, $entity, $this);
		return $obj;
		*/
		
		$key = $entity->name . '_select';
		if(!$stored = $this->get($key)) {
			$query = new Dormio_Manager($entity, $this);
			$query->mapper = 'mapArray';
			$stored = $query->filter('pk', '=', null)->compile(true);
			$this->set($key, $stored);
			var_dump('CREATED');
		}
		
		//$iter = $this->getStoredResultset($stored, array($id));
		//return $iter->current();
		
		$stmt = $stored[0];
		$stmt->execute(array($id));
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$obj = new stdClass;
		return self::mapObject($data[0], $obj, $entity, $stored[3]);
	}
	
	function execute($sql, $params, $row_count=false) {
		return $this->executeQuery(array($sql, $params), $row_count);
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
	
	private $dormio, $entity, $reverse, $mapper, $iter;
	
	/**
	 * @todo Implement PDOStatement iterator
	 * @param multitype:mixed $query
	 * @param Dormio $dormio
	 * @param multitype:string $reverse
	 * @param string $mapper
	 */
	function __construct($query, $dormio, $entity, $reverse, $mapper='mapArray') {
		$this->dormio = $dormio;
		$this->reverse = $reverse;
		$this->entity = $entity;
		//$this->mapper = array($this, $mapper);
		$stmt= $this->dormio->executeQuery($query);
		$this->iter = new ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
		$stmt->closeCursor();
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
		return call_user_func($this->mapper, $this->iter->current());
	}
	
	function next() {
		$this->iter->next();
	}
}

class Dormio_Exception extends Exception {}