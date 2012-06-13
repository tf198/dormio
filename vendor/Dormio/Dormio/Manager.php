<?php
/**
 * Manager for entities
 * A Query that can execute against a database
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_Manager extends Dormio_Query implements IteratorAggregate{
	
	/**
	 * @var Dormio
	 */
	public $dormio;
	
	/**
	 * @var int
	 */
	private $_count;
	
	/**
	 * Track the filters for related managers
	 * @var unknown_type
	 */
	public $filters = array();
	
	/**
	 * Cache the pdo statement
	 * @var PDOStatement
	 */
	private $_stmt;
	
	/**
	 * Cache of params
	 * @var multitype:mixed
	 */
	private $_params;
	
	function __construct($entity, Dormio $dormio) {
		$this->dormio = $dormio;
		if(is_string($entity)) $entity = $dormio->config->getEntity($entity);
		
		parent::__construct($entity, $dormio->dialect);
	}
	
	function __clone() {
		$this->clear();
	}
	
	function clear() {
		$this->_count = null;
		$this->_stmt = null;
		$this->_params = null;
	}
	
	function compile($prepare=false) {
		$query = $this->select();
		$args = count($query[1]);
		$store = ($prepare) ? $this->dormio->pdo->prepare($query[0]) : $query[0];
		return array($store, $args, $this->entity->name, $this->reverse);
	}
	
	/**
	 * Execute the query and return a multi-dimentional array
	 * @return multitype:multitype:string
	 */
	function findArray() {
		if(!$this->_stmt) {
			$query = $this->select();
			$this->_stmt = $this->dormio->pdo->prepare($query[0]);
			$this->_params = $query[1];
		}
		$this->_stmt->execute($this->_params);
		$data = $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
		return array_map(array($this, 'mapArray'), $data);
	}
	
	/**
	 * Execute the query
	 * @return multitype:multitype:string
	 */
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
		if($id !== null) $query->filter('pk', '=', $id, false);
		$data = $query->findArray();
		if(!$data) throw new Dormio_Manager_NoResultException("Query returned no records");
		if(count($data) > 1) throw new Dormio_Manager_MultipleResultsException("Query returned more than one record");
		return $data[0];
	}
	
	/**
	 * Get an aggregator for SQL methods e.g. COUNT() MAX() AVG() etc...
	 * @return Dormio_Aggregator
	 */
	function getAggregator() {
		return new Dormio_Aggregator($this);
	}
	
	/**
	 * Deletes the records specified by this query
	 * Also deletes related records where on_delete is set to 'cascade'
	 * @return int number of records deleted
	 */
	function delete() {
		$query = parent::delete();
		return $this->dormio->executeQuery($query, true);
	}
	
	/**
	 * Does a batch update
	 * @param multitype:string $params key/values to set
	 * @see Dormio_Query::update()
	 */
	function update($params) {
		$query = parent::update($params);
		return $this->dormio->excuteQuery($query, true);
	}
	
	/**
	 * Make Dormio_Manager objects iteratable
	 * @return Iterator
	 */
	function getIterator() {
		return new ArrayIterator($this->find());
	}
	
	function filterBind($key, $op, &$value, $clone=true) {
		// add the ability for IN to accept Dormio_Manager as well as arrays
		if($op == 'IN' && $value instanceof Dormio_Manager) {
			$o = clone $value;
			$o->selectIdent();
			$stmt = $this->dormio->executeQuery($o->select());
			$value = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		}
		$this->filters[$key] = $value;
		return parent::filterBind($key, $op, $value, $clone);
	}
	
	/**
	 * Return number of results this query has
	 * Will perform a COUNT operation on the database
	 * @return int
	 */
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

/**
 * Extends Manager to add object mapping to results
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_Manager_Object extends Dormio_Manager {
	
	public $related = null;
	
	function __construct($obj) {
		parent::__construct($obj->_entity, $obj->dormio);
		$this->obj = $obj;
	}
	
	function find() {
		return new Dormio_ObjectSet(parent::find(), $this->obj);
	}
	
	function findOne($id=null) {
		$data = parent::findOne($id);
		return Dormio::mapObject($data, $this->obj);
	}
	
	function getIterator() {
		return $this->find();
	}
	
	/**
	 * Add an object to the current queryset
	 * @param unknown_type $obj
	 */
	function add($obj) {
		if(!isset($obj->_is_bound)) throw new Dormio_Manager_Exception("Object not bound to Dormio");
		
		// sanity tests
		if($obj->_entity->name != $this->entity->name) {
			throw new Dormio_Manager_Exception("Can only add entities of type [{$this->entity->name}]");
		}
		if(count($this->params) != count($this->filters)) {
			throw new Dormio_Manager_Exception("Can only add objects to simple filter queries");
		}
		
		// update the passed objects with the filter fields
		foreach($this->filters as $field=>$value) {
			if(strpos($field, '__') !== false) throw new Dormio_Manager_Exception("Cannot add objects to joined queries");
			$obj->{$field} = $value;
		}
		
		return $this->dormio->_insert($obj);
	}
}

class Dormio_Manager_ManyToMany extends Dormio_Manager_Object {
	
	public $source_spec;
	
	public $accessor;
	
	function __construct($obj, $source_spec) {
		parent::__construct($obj);
		$this->accessor = $this->config->getThroughAccessor($source_spec);
		$this->bindRelated($obj, $this->accessor);
		$this->filterBind("{$this->accessor}__{$source_spec['map_local_field']}", '=', $obj->pk, false);
		$this->source_spec = $source_spec;
	}
}

/**
 * @package Dormio/Exception
 *
 */
class Dormio_Manager_Exception extends Exception{};

/**
 * @package Dormio/Exception
 *
 */
class Dormio_Manager_NoResultException extends Dormio_Manager_Exception {}

/**
 * @package Dormio/Exception
 *
 */
class Dormio_Manager_MultipleResultsException extends Dormio_Manager_Exception {}