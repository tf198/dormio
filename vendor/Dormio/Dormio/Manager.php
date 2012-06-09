<?php
class Dormio_Manager extends Dormio_Query implements IteratorAggregate{
	
	/**
	 * @var Dormio
	 */
	public $dormio;
	
	public $mapper = 'mapArray';
	
	function __construct($entity, $dormio) {
		$this->dormio = $dormio;
		if(is_string($entity)) $entity = $dormio->config->getEntity($entity);
		parent::__construct($entity, $dormio->dialect);
	}
	
	/**
	 * Execute the query
	 * @return Iterator
	 */
	function find() {
		$stmt = $this->dormio->executeQuery($this->select());
		//return array_map(array($this, $this->mapper), $stmt->fetchAll(PDO::FETCH_ASSOC));
		$mapper = array($this, $this->mapper);
		$iter = new ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
		return new MappingIterator($iter, $mapper);
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
		return $this->find();
	}
	
	function mapObject($row) {
		if(!isset($this->obj)) {
			$this->obj = $this->dormio->getObject($this->entity);
		}
		$this->obj->proxy->hydrate($this->mapArray($row));
		return $this->obj;
	}
}

class MappingIterator implements Iterator {
	
	private $mapper;
	
	private $iter;
	
	function __construct($iter, $mapper) {
		$this->mapper = $mapper;
		$this->iter = $iter;
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

class Dormio_Manager_Exception extends Exception{};

class Dormio_Manager_NoResultException extends Dormio_Manager_Exception {}

class Dormio_Manager_MultipleResultsException extends Dormio_Manager_Exception {}