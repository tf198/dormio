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

	function __clone() {
		$this->_reset();
	}

	function _reset() {
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
	function findData() {
		if(!$this->_stmt) {
			$query = $this->select();
			//var_dump($query);
			$this->_stmt = $this->dormio->pdo->prepare($query[0]);
			$this->_params = $query[1];
		}
		$this->_stmt->execute($this->_params);
		return $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	function findArray() {
		return array_map(array($this, 'mapArray'), $this->findData());
	}

	/**
	 * Execute the query and return an array of type $obj
	 * @param Object $obj
	 * @return multitype:Object
	 */
	function findObjects($obj) {
		// create a field map
		$map = array();
		foreach($obj->_entity->getFields() as $key=>$spec) {
			if($spec['is_field']) {
				$map[$key] = $this->alias . "_" . $key;
			} 
		}
		return new Dormio_ObjectSet($this->findData(), $obj, $map);
	}

	/**
	 * Execute the query and return the associated Object
	 * @return Dormio_Object
	 */
	function find() {
		return $this->findObjects($this->dormio->getObject($this->entity->name));
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
		//$data = $this->findOneArray($id);
		//return Dormio::mapObject($data, $this->object());
		$all = $this->find();
		if(count($all)!=1) throw new Dormio_Manager_Exception("Expected 1, got " . count($all));
		return $all[0];
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

		return $this->dormio->save($obj);
	}

	function create($arr) {
		$obj = $this->object();
		Dormio::mapObject($arr, $obj);
		$this->add($obj);
		return $obj;
	}
}

/**
 * Manager for OneToMany related entities
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_OneToMany extends Dormio_Manager {

	public $source_spec;

	public $source_obj;

	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $obj, $spec) {
		parent::__construct($entity, $dormio);
		//if(!isset($obj->$local)) $obj->$local = null;
		$this->filterBind($spec['remote_field'], '=', $obj->{$spec['local_field']}, false);
		$this->source_spec = $spec;
		$this->source_obj = $obj;
	}
}

/**
 * Manager for ManyToMany related entities
 * @package Dormio
 * @subpackage Manager
 */
class Dormio_Manager_ManyToMany extends Dormio_Manager {

	public $source_spec;

	public $source_obj;

	function __construct(Dormio_Config_Entity $entity, Dormio $dormio, $obj, $spec) {
		parent::__construct($entity, $dormio);
		$accessor = $this->dormio->config->getThroughAccessor($spec);
		$this->filterBind("{$accessor}__{$spec['map_local_field']}", '=', $obj->pk, false);
		$this->source_spec = $spec;
		$this->source_obj = $obj;
	}

	function add($obj) {
		if(!isset($obj->_is_bound)) throw new Dormio_Manager_Exception("Object not bound to Dormio");
		if(!isset($obj->pk)) $obj->save();

		$o = $this->dormio->getObject($this->source_spec['through']);
		$o->{$this->source_spec['map_local_field']} = $this->source_obj->pk;
		$o->{$this->source_spec['map_remote_field']} = $obj->pk;
		$this->dormio->_insert($o);
	}

	function clear() {
		$q = $this->dormio->getManager($this->source_spec['through']);
		return $q->filter($this->source_spec['map_local_field'], '=', $this->source_obj->pk)->delete();
	}

	function remove($obj) {
		$pk = is_object($obj) ? $obj->pk : $obj;
		$q = $this->dormio->getManager($this->source_spec['through']);
		return $q->filter($this->source_spec['map_remote_field'], '=', $pk)->filter($this->source_spec['map_local_field'], '=', $this->source_obj->pk)->delete();
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
	 * Need to *magic* this so we can act as an object but still get updated
	 * @param string $field
	 */
	function __get($field) {
		if(!$this->obj || $this->obj->pk != $this->source_obj->pk) {
			try {
				$this->obj = $this->findOne();
			} catch(Dormio_Manager_NoResultException $e) {
				var_dump($e);
				$this->obj = new stdClass();
				$this->obj->pk = $this->source_obj->pk;
			}
		}
		return isset($this->obj->$field) ? $this->obj->$field : null;
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