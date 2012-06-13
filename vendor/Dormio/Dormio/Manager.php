<?php
/**
 * Manager for entities
 * A Query that can execute against a database
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_Manager extends Dormio_Query implements IteratorAggregate, Countable{
	
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
	
	function __construct(Dormio_Config_Entity $entity, Dormio $dormio) {
		$this->dormio = $dormio;
		
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
	 * Execute the query and return an array of type $obj
	 * @param Object $obj
	 * @return multitype:Object
	 */
	function findObjects($obj) {
		return new Dormio_ObjectSet($this->findArray(), $obj);
	}
	
	/**
	 * Execute the query and return the associated Object 
	 * @return Dormio_Object
	 */
	function find() {
		$obj = $this->dormio->getObject($this->entity->name);
		return new Dormio_ObjectSet($this->findArray(), $obj);
	}
	
	/**
	 * Execute the query and return a single row
	 * @throws Dormio_Manager_NoResultException
	 * @throws Dormio_Manager_MultipleResultsException
	 * @return multitype:string
	 */
	function findOneArray($id=null) {
		$query = $this->limit(2);
		if($id !== null) $query->filter('pk', '=', $id, false);
		$data = $query->findArray();
		if(!$data) throw new Dormio_Manager_NoResultException("Query returned no records");
		if(count($data) > 1) throw new Dormio_Manager_MultipleResultsException("Query returned more than one record");
		return $data[0];
	}
	
	/**
	 * Execute the query and return a single row
	 * @throws Dormio_Manager_NoResultException
	 * @throws Dormio_Manager_MultipleResultsException
	 * @return multitype:Dormio_Object
	 */
	function findOne($id=null) {
		$data = $this->findOneArray($id);
		$obj = $this->dormio->getObject($this->entity->name);
		return Dormio::mapObject($data, $obj);
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
		return $this->find();
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

class Dormio_Manager_OneToMany extends Dormio_Manager {
	
	public $source_spec;
	
	public $source_obj;
	
	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $obj, $spec) {
		parent::__construct($entity, $dormio);
		$local = $spec['local_field'];
		if(!isset($obj->$local)) $obj->$local = null;
		$this->filterBind($spec['remote_field'], '=', $obj->$local, false);
		$this->source_spec = $spec;
		$this->source_obj = $obj;
	}
}

class Dormio_Manager_ManyToMany extends Dormio_Manager {
	
	public $source_spec;
	
	public $accessor;
	
	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $obj, $spec) {
		parent::__construct($entity, $dormio);
		$this->accessor = $this->dormio->config->getThroughAccessor($spec);
		$this->filterBind("{$this->accessor}__{$spec['map_local_field']}", '=', $obj->pk, false);
		$this->source_spec = $spec;
	}
}

class Dormio_Manager_OneToOne extends Dormio_Manager_OneToMany {
	
	private $obj;
	
	/**
	 * Need to *magic* this so we can act as an object but still get updated
	 * @param string $field
	 */
	function __get($field) {
		if(!$this->obj || $this->obj->pk != $this->source_obj->pk) {
			$this->obj = $this->findOne();
		}
		return $this->obj->$field;
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