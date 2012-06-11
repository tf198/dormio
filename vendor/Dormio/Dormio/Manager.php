<?php
class Dormio_Manager extends Dormio_Query implements IteratorAggregate{
	
	/**
	 * @var Dormio
	 */
	public $dormio;
	
	/**
	 * @var int
	 */
	private $_count;
	
	function __construct($entity, $dormio) {
		$this->dormio = $dormio;
		if(is_string($entity)) $entity = $dormio->config->getEntity($entity);
		
		parent::__construct($entity, $dormio->dialect);
	}
	
	function __clone() {
		$this->_count = null;
	}
	
	function compile($prepare=false) {
		$query = $this->select();
		$args = count($query[1]);
		$store = ($prepare) ? $this->dormio->pdo->prepare($query[0]) : $query[0];
		return array($store, $args, $this->entity->name, $this->reverse);
	}
	
	/**
	 * Execute the query
	 * @return multitype:multitype:string
	 */
	function findArray() {
		$stmt = $this->dormio->executeQuery($this->select());
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return array_map(array($this, 'mapArray'), $data);
	}
	
	function find() {
		return $this->findArray();
	}
	
	/**
	 * Execute the query and return a single row
	 * @throws Dormio_Manager_NoResultException
	 * @throws Dormio_Manager_MultipleResultsException
	 * @return multitype:string
	 */
	function findOne($id=null) {
		$query = $this->limit(2);
		if($id !== null) $query = $query->filter('pk', '=', $id);
		$data = $query->find();
		if(!$data) throw new Dormio_Manager_NoResultException("Query returned no records");
		if(count($data) > 1) throw new Dormio_Manager_MultipleResultsException("Query returned more than one record");
		return $data[0];
	}
	
	function getAggregator() {
		return new Dormio_Aggregator($this);
	}
	
	function delete() {
		$query = parent::delete();
		return $this->dormio->executeQuery($query, true);
	}
	
	function update($params) {
		$query = parent::update($params);
		return $this->dormio->excuteQuery($query, true);
	}
	
	/**
	 * @TODO: fix this
	 */
	function insert() {
		throw new Exception("Not yet implemented");
	}
	
	/**
	 * Make Dormio_Manager objects iteratable
	 * @return Iterator
	 */
	function getIterator() {
		return new ArrayIterator($this->find());
	}
	
	function filterBind($key, $op, &$value, $clone=true) {
		if($op == 'IN' && $value instanceof Dormio_Manager) {
			$o = clone $value;
			$o->selectIdent();
			$stmt = $this->dormio->executeQuery($o->select());
			$value = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		}
		return parent::filterBind($key, $op, $value, $clone);
	}
	
	function count() {
		if(!isset($this->_count)) {
			$o = clone $this;
			$o->query['select'] = array('COUNT(*) AS count');
			$result = $o->findArray();
			$this->_count = (int)$result[0]['count'];
		}
		return $this->_count;
	}
}

class Dormio_Manager_Object extends Dormio_Manager {
	function __construct($entity, $dormio) {
		parent::__construct($entity, $dormio);
		$this->obj = $this->dormio->getObject($entity->name);
	}
	
	function find() {
		$iter = new ArrayIterator(parent::find());
		return new DormioResultSet($iter, $this->obj);
	}
	
	function findOne() {
		$data = parent::findOne();
		return Dormio::mapObject($data, $this->obj);
	}
	
	function getIterator() {
		return $this->find();
	}
}

class Dormio_Manager_Exception extends Exception{};

class Dormio_Manager_NoResultException extends Dormio_Manager_Exception {}

class Dormio_Manager_MultipleResultsException extends Dormio_Manager_Exception {}