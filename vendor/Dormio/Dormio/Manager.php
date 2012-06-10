<?php
class Dormio_Manager extends Dormio_Query implements IteratorAggregate{
	
	const MAP_ARRAY = 1;
	const MAP_OBJECT = 2;
	
	/**
	 * @var Dormio
	 */
	public $dormio;
	
	/**
	 * Map type
	 * @var int
	 */
	public $type;
	
	function __construct($entity, $dormio, $type=self::MAP_ARRAY) {
		$this->dormio = $dormio;
		if(is_string($entity)) $entity = $dormio->config->getEntity($entity);
		$this->type = $type;
		
		parent::__construct($entity, $dormio->dialect);
	}
	
	function compile($prepare=false) {
		$query = $this->select();
		$args = count($query[1]);
		$store = ($prepare) ? $this->dormio->pdo->prepare($query[0]) : $query[0];
		return array($store, $args, $this->entity->name, $this->reverse);
	}
	
	/**
	 * Execute the query
	 * @return Iterator
	 */
	function find() {
		$stmt = $this->dormio->executeQuery($this->select());
		switch($this->type) {
			case self::MAP_ARRAY:
				return array_map(array($this, 'mapArray'), $stmt->fetchAll(PDO::FETCH_ASSOC));
			case self::MAP_OBJECT:
				$iter = new ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
				return new DormioResultSet($iter, $this->dormio, $this->entity, $this->reverse);
			default:
				throw new Dormio_Manager_Exception("Unknown map type [{$this->type}]");
		}
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
		$f = $this->find();
		switch($this->type) {
			case self::MAP_ARRAY:
				return new ArrayIterator($f);
			case self::MAP_OBJECT:
				return $f;
		}
	}
}

class Dormio_Manager_Exception extends Exception{};

class Dormio_Manager_NoResultException extends Dormio_Manager_Exception {}

class Dormio_Manager_MultipleResultsException extends Dormio_Manager_Exception {}