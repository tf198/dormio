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
	
	function getStoredResultset($stored) {
		list($query, $entity, $reverse, $mapper) = $stored;
		$entity = $this->config->getEntity($entity);
		return new DormioResultSet($query, $this, $entity, $reverse, $mapper);
	}
	
	function getObject($entity) {
		$class_name = (class_exists($entity->name)) ? $entity->name : 'stdClass';
		$obj = new $class_name;
		$obj->proxy = new Dormio_Proxy($obj, $entity, $this);
		return $obj;
	}
	
	function execute($sql, $params, $row_count=false) {
		return $this->executeQuery(array($sql, $params), $row_count);
	}
	
	function executeQuery($query, $row_count=false) {
		$stmt = $this->pdo->prepare($query[0]);
		$stmt->execute($query[1]);
		return ($row_count) ? $stmt->rowCount() : $stmt;
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
		$this->mapper = array($this, $mapper);
		$stmt= $this->dormio->executeQuery($query);
		$this->iter = new ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
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
	
	function mapArray($row) {
		$result = array();
		foreach($row as $key=>$value) {
			$parts = explode('__', $this->reverse[$key]);
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
	
	function mapObject($row) {
		if(!isset($this->obj)) {
			$this->obj = $this->dormio->getObject($this->entity);
		}
		$this->obj->proxy->hydrate($this->mapArray($row));
		return $this->obj;
	}
}

class Dormio_Exception extends Exception {}