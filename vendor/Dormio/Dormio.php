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

	/**
	 * Generic cache interface implements get($key) and set($key, $value)
	 * @var Object
	 */
	public $cache;

	public function __construct($pdo, $config, $cache=null) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

		// use a basic cache unless a better one is available
		if(!$cache) $cache = new Dormio_Cache;
		$this->cache = $cache;
	}

	function save($obj, $entity_name=null) {
		if(isset($obj->pk)) {
			$this->update($obj);
		} else {
			$this->insert($obj, $entity_name);
		}
	}

	function load($obj, $id) {
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object not bound to dormio");

		$stmt = $this->_getSelect($obj->_entity);
		$stmt->execute(array($id));
		$obj->_raw = $stmt->fetch(PDO::FETCH_ASSOC);
		//foreach($obj->_raw as $key=>$value) $obj->$key = $value;
		return self::mapObject($obj->_raw, $obj);
	}

	function getManager($name) {
		return new Dormio_Manager($this->config->getEntity($name), $this);
	}

	function getObjectManager($name) {
		$key = "_{$name}_MANAGER";
		if(!$stored = $this->cache->get($key)) {
			class_exists('Dormio_Manager');
			$obj = $this->getObject($name);
			//$this->bindRelated($obj);
			$stored = new Dormio_Manager_Object($obj);
			$this->cache->set($key, $stored);
		}
		return $stored;
	}

	function getObject($entity_name, $id=null) {
		$entity = $this->config->getEntity($entity_name);
		$class_name = $entity->model_class;
		$obj = new $class_name;
		$this->bind($obj, $entity_name);

		if($id !== null) {
			$this->load($obj, $id);
		}

		return $obj;
	}

	function insert($obj, $entity_name) {
		$this->bind($obj, $entity_name);

		$params = array();
		foreach($obj->_entity->getFields() as $key=>$spec) {
			// TODO: need to handle related fields
			if($spec['is_field'] && isset($obj->$key)) {
				$params[$spec['db_column']] = $obj->$key;
			}
		}
		if(!$params) throw new Exception("No fields to update on entity [{$obj->_entity->name}]");

		$stmt = $this->_getInsert($obj->_entity, array_keys($params));
		$stmt->execute(array_values($params));
		$obj->pk = $this->pdo->lastInsertId();
	}

	function update($obj) {
		if(!isset($obj->_is_bound)) throw new Dormio_Exception("Object hasn't been bound to Dormio");

		$params = array();
		foreach($obj->_entity->getFields() as $name=>$spec) {
			if($spec['is_field'] && isset($this->obj->$name)) {
				if($name == 'pk') continue;
				if(isset($obj->_raw[$name]) && $this->_raw[$name]==$obj->$name) continue;
				$params[$name] = $this->obj->$name;
				$this->_raw[$name] = $this->obj->$name;
			}
		}
		if(!$params) return;

		$query = array(
			'from' => $obj->_entity->table,
			'where' => array("{$obj->_entity->pk['db_column']} = ?")
			);

		$sql = $this->dormio->dialect->update($query, array_keys($params));

		$values = array_keys($params);
		array_unshift($values, $this->id);
		$this->dormio->execute($sql, $values);
	}

	function bind($obj, $entity_name) {
		if(!isset($obj->_is_bound)) {
			if(!$entity_name) $entity_name = get_class($obj);
			$obj->_entity = $this->config->getEntity($entity_name);
			$obj->dormio = $this;
			$obj->_raw = array();
			$obj->_is_bound = true;
		}
		return $obj;
	}
	/*
	 function bindRelated($obj) {
	if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
	if(isset($obj->is_bound_related)) return $obj;

	$fields = $obj->_entity->getRelatedFields();
	foreach($obj->_entity->getFields() as $field=>$spec) {
	if($spec['type'] == 'manytomany') $fields[] = $field;
	}

	foreach($fields as $field) {
	$spec = $obj->_entity->getField($field);
	echo "BIND {$spec['type']} {$obj->_entity->name}->{$field}\n";
	$manager = $this->getObjectManager($spec['entity']);
	switch($spec['type']) {
	case 'onetomany':
	$local = $spec['local_field'];
	if(!isset($obj->$local)) $obj->$local = null;
	$manager->filterBind($spec['remote_field'], '=', $obj->$local, false);
	break;
	case 'manytomany':
	var_dump($spec);
	var_dump($manager->obj->_entity->name);
	var_dump($manager->obj->_entity->getFields());
	//$manager->filterBind('through_right__' . $spec['map_local_field'], '=', $obj->pk, false);
	break;
	}
	$obj->$field = $manager;
	}
	$obj->_is_bound_related = true;
	return $obj;
	}
	*/
	function bindRelated($obj, $field) {
		if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
		if(isset($obj->is_bound_related)) return $obj;

		$spec = $obj->_entity->getField($field);
		echo "BIND {$spec['type']} {$obj->_entity->name}->{$field}\n";
		$manager = $this->getObjectManager($spec['entity']);
		switch($spec['type']) {
			case 'onetomany':
				$local = $spec['local_field'];
				if(!isset($obj->$local)) $obj->$local = null;
				$manager->filterBind($spec['remote_field'], '=', $obj->$local, false);
				break;
			case 'manytomany':
				//var_dump($spec);
				//var_dump($manager->obj->_entity->name);
				//var_dump($manager->obj->_entity->getFields());
				$this->bindRelated($manager->obj, 'through_right');
				$manager->filterBind('through_right__' . $spec['map_local_field'], '=', $obj->pk, false);
				//var_dump($manager->select());
				break;
		}
		$obj->$field = $manager;
			
		$obj->_is_bound_related = true;
		return $obj;
	}

	function _getSelect($entity) {
		$key = "_{$entity->name}_SELECT";
		if(!$stored = $this->cache->get($key)) {
				
			$fields = array();
			foreach($entity->getFields() as $field=>$spec) {
				if($spec['is_field']) $fields[] = "{{$spec['db_column']}} AS {{$field}}";
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
			$parts = isset($reverse[$key]) ? explode('__', $reverse[$key]) : array($key);
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

	static function mapObject($row, $obj) {
		//echo "MAP {$obj->_entity->name}\n";
		//var_dump($row);
		foreach($obj->_entity->getFields() as $key=>$spec) {
			// map related with local field
			if($spec['type'] == 'foreignkey' || $spec['type'] == 'onetoone') {
				if(!isset($obj->$key)) {
					$obj->$key = $obj->dormio->getObject($spec['entity']);
				}
				if(is_array($row[$key])) {
					// eager load
					self::mapObject($row[$key], $obj->$key);
				} else {
					// lazy
					self::clearObject($obj->$key);
					$obj->$key->pk = $row[$spec['local_field']];
					//echo "SET {$key} {$obj->$key->pk}\n";
				}
				continue;
			}
				
			// set or clear fields
			if($spec['is_field']) {
				$obj->$key = (isset($row[$key])) ? $row[$key] : null;
				//echo "SET {$key} {$obj->$key}\n";
			}
				
		}
		//var_dump(array_keys(get_object_vars($obj)));
		return $obj;
	}

	static function clearObject($obj) {
		if(!$obj->_is_bound) throw new Dormio_Exception("Object not bound to Dormio");
		foreach($obj->_entity->getFields() as $field=>$spec) $obj->$field = null;
	}
}

class Dormio_Cache {
	/**
	 * Cache our statements
	 * @var multitype:PDOStatement
	 */
	public $_stored = array();

	function get($key) {
		if(isset($this->_stored[$key])) return $this->_stored[$key];
		return false;
	}

	function set($key, $value) {
		$this->_stored[$key] = $value;
		//echo "CREATED {$key}\n";
	}

	function clear() {
		$this->_stored = array();
	}
}

class DormioResultSet implements Iterator {

	private $obj, $iter;

	/**
	 * @todo Implement PDOStatement iterator
	 * @param multitype:mixed $query
	 * @param Dormio $dormio
	 * @param multitype:string $reverse
	 * @param string $mapper
	 */
	function __construct($iter, $obj) {
		$this->iter = $iter;
		$this->obj = $obj;
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
		$data = $this->iter->current();
		$obj = Dormio::mapObject($data, $this->obj);
		return $obj;
	}

	function next() {
		$this->iter->next();
	}
}

class Dormio_Exception extends Exception {}