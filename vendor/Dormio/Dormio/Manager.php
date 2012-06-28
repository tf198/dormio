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
 * @subpackage Manager
 */

/**
 * Manager for entities
 * A Query that can execute against a database
 * @author Tris Forster
 * @package Dormio
 * @subpackage Manager
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

	/**
	 * Resets required fields on clone
	 */
	function __clone() {
		$this->_reset();
	}

	/**
	 * Reset non-cloneable fields
	 */
	function _reset() {
		$this->_count = null;
		$this->_stmt = null;
		$this->_params = null;
	}

	/**
	 * Execute the query and return a multi-dimentional array
	 * @return multitype:multitype:string
	 */
	function findArray() {
		if(!$this->_stmt) {
			$query = $this->select();
			//var_dump($query);
			$this->_stmt = $this->dormio->pdo->prepare($query[0]);
			$this->_params = $query[1];
		}
		$this->_stmt->execute($this->_params);
		return $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	/**
	 * Execute the query and return a single data array
	 * Optionally you can give it a primary key.
	 * 
	 * @param mixed $id value to load
	 * @param string $field use a different field to pk
	 * @throws Dormio_Manager_MultipleResultsException if more than one record matches
	 * @throws Dormio_Manager_NoResultException if no records match
	 * @return multitype:string
	 */
	function findOneArray($id=null, $field='pk') {
		$query = $this->limit(2);
		if($id !== null) $query->filter($field, '=', $id, false);
		$data = $query->findArray();
		if(!$data) throw new Dormio_Manager_NoResultException("Query returned no records");
		if(count($data) > 1) throw new Dormio_Manager_MultipleResultsException("Query returned more than one record");
		return $data[0];
	}

	/**
	 * Execute the query and return an array of type $obj
	 * @param Object $obj
	 * @return multitype:Object
	 */
	function findObjects($obj) {
		return new Dormio_ObjectSet($this->findArray(), $obj, array_flip($this->reverse));
	}

	/**
	 * Execute the query and return the associated Object
	 * @return Dormio_Object
	 */
	function find() {
		return $this->findObjects($this->dormio->getObjectFromEntity($this->entity));
	}

	/**
	 * Execute the query and return a single row
	 * Optionally you can give it a primary key to load.
	 * 
	 * @param mixed $id value to load
	 * @param string $field use a different field to pk
	 * @throws Dormio_Manager_NoResultException if no records match
	 * @throws Dormio_Manager_MultipleResultsException if more than one records match
	 * @return Dormio_Object
	 */
	function findOne($id=null, $field='pk') {
		$obj = $this->dormio->getObjectFromEntity($this->entity);
		$obj->setData($this->findOneArray($id, $field));
		return $obj;
	}
	
	/**
	 * Find a row by any unique field
	 * 
	 * @param string $field unique field
	 * @param mixed $value
	 * @return Dormio_Object
	 */
	function findOneByField($field, $value) {
		return $this->findOne($value, $field);
	}

	/**
	 * Get an aggregator for SQL methods e.g. COUNT() MAX() AVG() etc...
	 * @return Dormio_Aggregator
	 */
	function getAggregator() {
		return new Dormio_Aggregator($this);
	}
	
	function getFields() {
		return $this->entity->getFieldNames();
	}
	
	function getHeadings() {
		return $this->entity->getParams('verbose');
	}

	/**
	 * Deletes the records specified by this query
	 * Also deletes related records where on_delete is set to 'cascade'
	 * @return int number of records deleted
	 */
	function delete() {
		$sql = parent::delete();
		$i = 0;
		foreach($sql as $query) $i += $this->dormio->executeQuery($query, true);
		return $i;
	}

	/**
	 * Does a batch update
	 * @param multitype:string $params key/values to set
	 * @see Dormio_Query::update()
	 */
	function update($params) {
		$query = parent::update($params);
		return $this->dormio->executeQuery($query, true);
	}

	/**
	 * Gets a prepared statement for high performance inserts
	 * @param multitype:string $fields field names
	 * @return PDOStatement
	 */
	function insert($fields) {
		$query = parent::insert(array_flip($fields));
		return $this->dormio->pdo->prepare($query[0]);
	}

	/**
	 * Make Dormio_Manager objects iteratable
	 * @return Iterator
	 */
	function getIterator() {
		return $this->find();
	}

	/**
	 * Tracks the bound fields for add()
	 * @see Dormio_Query::filterBind()
	 */
	function filterBind($key, $op, &$value, $clone=true) {
		// add the ability for IN to accept Dormio_Manager as well as arrays
		if($op == 'IN' && $value instanceof Dormio_Manager) {
			$o = clone $value;
			$o->selectIdent();
			$stmt = $this->dormio->executeQuery($o->select());
			$value = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		}
		$o = parent::filterBind($key, $op, $value, $clone);
		$o->filters[$key] = &$value;
		return $o;
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
	 * @param Dormio_Object $obj
	 */
	function add($obj) {

		// sanity tests
		if($obj->_entity->name != $this->entity->name) {
			throw new Dormio_Manager_Exception("Can only add entities of type [{$this->entity->name}]");
		}
		//var_dump($this->filters);
		if(count($this->params) != count($this->filters)) {
			throw new Dormio_Manager_Exception("Can only add objects to simple filter queries");
		}

		// update the passed objects with the filter fields
		foreach($this->filters as $field=>$value) {
			if(strpos($field, '__') !== false) throw new Dormio_Manager_Exception("Cannot add objects to joined queries");
			$obj->setFieldValue($field, $value);
		}

		return $obj->save();
	}

	/**
	 * Create and add a new object to the current queryset
	 * @param multitype:mixed $arr key/value pairs for object properties
	 */
	function create($arr) {
		$obj = $this->dormio->getObjectFromEntity($this->entity);
		$obj->_data = $arr;
		foreach($obj->_data as $key=>$value) $obj->$key = $value;
		$this->add($obj);
		return $obj;
	}
}

/**
 * Enables binding of managers to source objects
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_Related extends Dormio_Manager {
	
	public $bound_id;
	
	function setBoundId($id) {
		Dormio::$logger && Dormio::$logger->log("Binding {$this->entity} manager to id {$id}");
		$this->bound_id = $id;
	}
}

/**
 * Manager for OneToMany related entities
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_OneToMany extends Dormio_Manager_Related {
	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $spec) {
		parent::__construct($entity, $dormio);
		$this->filterBind($spec['remote_field'], '=', $this->bound_id, false);
	}
}

/**
 * Manager for ManyToMany related entities
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_ManyToMany extends Dormio_Manager_Related {

	public $source_spec;

	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $spec) {
		parent::__construct($entity, $dormio);
		$accessor = $this->dormio->config->getThroughAccessor($spec);
		$this->filterBind("{$accessor}__{$spec['map_local_field']}", '=', $this->bound_id, false);
		$this->source_spec = $spec;
	}

	/**
	 * Creates a link in the mid-table
	 * @see Dormio_Manager::add()
	 */
	function add($obj) {
		if($obj instanceof Dormio_Object) {
			$obj->save();
			$obj = $obj->ident();
		}

		$o = $this->dormio->getObject($this->source_spec['through']);
		$o->{$this->source_spec['map_local_field']} = $this->bound_id;
		$o->{$this->source_spec['map_remote_field']} = $obj;
		$o->save();
	}

	/**
	 * Removes all links from the mid-table
	 */
	function clear() {
		$q = $this->dormio->getManager($this->source_spec['through']);
		return $q->filter($this->source_spec['map_local_field'], '=', $this->bound_id)->delete();
	}

	/**
	 * Remove a specific link from the mid-table
	 * @param Dormio_Object $obj
	 */
	function remove($obj) {
		$pk = is_object($obj) ? $obj->ident() : $obj;
		$q = $this->dormio->getManager($this->source_spec['through']);
		return $q->filter($this->source_spec['map_remote_field'], '=', $pk)->filter($this->source_spec['map_local_field'], '=', $this->bound_id)->delete();
	}
}

/**
 * Manager for OneToOne related entities
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_OneToOne extends Dormio_Manager_OneToMany {

	private $obj;
	
	/**
	 * Loads the related object 
	 * @see Dormio_Manager_Related::setBoundId()
	 */
	function setBoundId($id) {
		if($id != $this->bound_id) {
			parent::setBoundId($id);
			try {
				$this->obj = $this->findOne();
			} catch(Dormio_Manager_NoResultException $e) {
				Dormio::$logger && Dormio::$logger->log("No result - returning empty object");
				$this->obj = $this->dormio->getObjectFromEntity($this->entity);
				foreach($this->filters as $key=>$value) {
					$this->obj->setFieldValue($key, $value);
				}
			}
		}
	}
	
	/**
	 * Get the related object
	 * @return Dormio_Object
	 */
	function getObject() {
		return $this->obj;
	}
}

/**
 * @package Dormio
 * @subpackage Exception
 *
 */
class Dormio_Manager_Exception extends Exception{};

/**
 * @package Dormio
 * @subpackage Exception
 *
 */
class Dormio_Manager_NoResultException extends Dormio_Manager_Exception {}

/**
 * @package Dormio
 * @subpackage Exception
 *
 */
class Dormio_Manager_MultipleResultsException extends Dormio_Manager_Exception {}