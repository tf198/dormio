<?
/**
* Based heavily on django models.Manager functionality
* Supports most of the django query language with few modifications.
* This extends Dormio_Queryset giving it the ability to actually interact with
* the database.
*/
class Dormio_Manager extends Dormio_Queryset implements Iterator {
  protected $_db = null;
  protected $_stmt = null;
  
  /**
  * Create a new manager object based on the supplied meta
  */
  function __construct($meta, $db, $dialect=null) {
    $this->_db = $db;
    //if(!$dialect) throw new Exception();
    if(!$dialect) $dialect = Dormio_Dialect::factory($db->getAttribute(PDO::ATTR_DRIVER_NAME));
    parent::__construct($meta, $dialect);
  }
  
  /**
  * Need to clear the stored statement when we are cloned
  */
  function __clone() {
    $this->_stmt = null;
    $this->_model = null;
  }
  
  /**
  * Get a single object based on the current query
  *
  * @param  $pk   int   Optional primary key for object
  * @return Dormio_Model   The model
  * @throws Dormio_Manager_Exception   If the query doesn't return one object
  */
  function get($pk=null) {
    $o = $this->limit(2);
    if($pk) {
      $o = $o->filter('pk', '=', $pk);
    }
    //if(count($o->query['where'])==0) throw new Dormio_Manager_Exception('Need some criteria for get()');
    $o->_evaluate();
    $o->_stmt->execute($o->params);
    $data = $o->_stmt->fetchall(PDO::FETCH_ASSOC);
    if(isset($data[1])) throw new Dormio_Manager_Exception('More than one record returned');
    if(!isset($data[0])) throw new Dormio_Manager_Exception('No record returned');
    $o->_model->_hydrate($data[0], true);
    return $o->_model;
  }
  
  /**
  * Get an Aggregation object
  */
  function aggregate() {
    return new Dormio_Aggregation(clone $this);
  }
  
  /**
  * Get a copy of the current query with no select parameters
  */
  function clear() {
    $o = clone $this;
    $o->query['select'] = array();
    return $o;
  }
  
  /**
  * Bulk Update
  * Updates the values of all rows that match the current query to those in $params
  * e.g $set->filter->('colour', '=', 'Red')->update(array('colour' => 'blue', 'sound' => 'honk'));
  * Will update colour and sound on all rows where colour='Red'
  *
  * @param  $params   A set of key => values to be updated 
  * @return int       The number of rows updated
  */
  function update($params) {
    $sql = parent::update($params);
    return $this->batchExecute(array($sql));
  }
  
  /**
  * Bulk insert
  * Provides a method of efficiently loading a lot of data
  * 
  * $stmt = $blogs->insert(array('title', 'user'));
  * $stmt->execute(array('Test 1', 1));
  * $stmt->execute(array('Test 2', 1));
  *
  * @return PDOStatement    A statement suitable for bulk insertion
  * @throws Dormio_Manager_Exception if the statement can't be created
  */
  function insert($fields) {
    // can just flip as we are discarding the params
    $sql = parent::insert(array_flip($fields));
    $stmt = $this->_db->prepare($sql[0]);
    if(!$stmt) {
      $err = $this->_db->errorInfo();
      throw new Dormio_Manager_Exception($err[2]);
    }
    return $stmt;
  }
  
  /**
  * Bulk delete
  * Deletes rows and related rows based on the current query. Note that this
  * will follow relations where cascade is set.
  * e.g. $set->filter('age', '<', 16)->delete();
  *
  * @param  $preview  Return the statatements that wiould be executed instead of running
  * @return int       The number of rows deleted
  */
  function delete($preview=false) {
    $sql = parent::delete();
    return ($preview) ? $sql : $this->batchExecute($sql);
  }
  
  function query($query, $fetch=PDO::FETCH_ASSOC) {
    try {
      $stmt = $this->_db->prepare($query[0]);
      $stmt->execute($query[1]);
      return $stmt->fetch($fetch);
    } catch (PDOException $e) {
      throw new Dormio_Exception("Failed to execute: {$query[0]}\n{$e}");
    }
  }
  
  /**
  * Execute a single query
  *
  * @param  $query    The query in the form array('SQL', array('param1', ...))
  * @return int       The number of affected rows
  * @throws Dormio_Exception if it cannot execute the query
  */
  function execute($query) {
    try {
      $stmt = $this->_db->prepare($query[0]);
      $stmt->execute($query[1]);
      $c = $stmt->rowCount();
      //$stmt->closeCursor();
      return $c;
    } catch(PDOException $e) {
      throw new Dormio_Exception("Failed to execute: {$query[0]}\n{$e}");
    }
  }
  
  /**
  * Execute many queries in a single transaction
  *
  * @param  $sql    An array of queries suitable for passing to execute()
  * @return int     The total number of affected rows
  */
  function batchExecute($sql) {
    $this->_db->beginTransaction();
    $result = 0;
    foreach($sql as $q) {
      $result += $this->execute($q);
    }
    $this->_db->commit();
    return $result;
  }
  
  /**
  * Compile the current query and store the PDOStatment for execution
  */
  private function _evaluate() {
    if($this->_stmt) throw new Dormio_Manager_Exception('Statement already compiled');
    $query = $this->select();
    //print_r($query);
    $this->_stmt = $this->_db->prepare($query[0]);
    $this->_model = $this->_meta->instance($this->_db, $this->dialect);
  }
  
  /**
  * Rewind the iterator
  * Actual execution is done here
  */
  function rewind() {
    //print "REWIND\n";
    if(!$this->_stmt) $this->_evaluate();
    $this->_stmt->execute($this->params);
    $this->_data = $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
    $this->_iter = new ArrayIterator($this->_data);
    $this->_iter->rewind();
    $this->_model->clear();
    //$this->next();
    //return $this->current();
  }
  
  /**
  * Advance the iterator
  */
  function next() {
    //print "NEXT\n";
    //$data = $this->_stmt->fetch(PDO::FETCH_ASSOC);
    $this->_iter->next();
  }
  
  /**
  * Is there a current model
  */
  function valid() {
    //print "VALID\n";
    //return ($this->_model->ident());
    return $this->_iter->valid();
  }
  
  /**
  * Returns the current model
  */
  function current() {
    //print "CURRENT\n";
    $data = $this->_iter->current();
    if($data) {
      $this->_model->_hydrate($data, true); // dont need to clear as should be the same fields each time
    } else {
      $this->_model->clear();
      $this->_data = null;
      $this->_iter = null;
    }
    return $this->_model;
  }
  
  /**
  * Returns the current model ident, or false
  */
  function key() {
    //print "KEY\n";
    //return $this->_model->ident();
    return $this->_iter->key();
  }
}

/**
* Additional methods where there is a related object
*/
class Dormio_Manager_Related extends Dormio_Manager {
	
	function __construct($meta, $db, $dialect, $model, $field, $through=null) {
    if(!$model->ident()) throw new Dormio_Manager_Exception('Model needs to be saved first');
		$this->_to = $model;
		$this->_field = $field;
    $this->_through = $through;
    parent::__construct($meta, $db, $dialect);
    
    // set the base query
    if($through) $field = "{$through}_set__{$field}";
    $this->query['where'][] = $this->_resolveField($field) . " = ?";
    // set the id by reference so when the iterator advances the model criterial update automatically
    $this->params[] = &$this->_to->_id;
	}

  /**
  * Adds the specified object to the related set
  */
	function add($obj) {
    if($obj->_meta->_klass != $this->_meta->_klass) throw new Dormio_Manager_Exception('Can only add like objects');
    if($this->_through) {
      $obj->save();
      $mid = Dormio_Meta::get($this->_through);
      $intermediate = $mid->instance($this->_db, $this->dialect);
      $intermediate->__set($this->_field, $this->_to->ident());
      $field = $mid->accessorFor($obj);
      $intermediate->__set($field, $obj->ident());
      $intermediate->save();
    } else {
      // update the foreign key on the supplied object
      $obj->__set($this->_field, $this->_to->ident());
      $obj->save();
    }
	}
	
  /**
  * Creates a new instance of the related item.
  * Note that manytomany relations are not automatically added, you need
  * to manually call add()
  * e.g 
  * $tag = $blog->tags->create(array('tag' => 'Grey'));
  * $blog->tags->add($tag);
  */
	function create($params=array()) {
    $obj = $this->_meta->instance($this->_db, $this->dialect);
    if(!$this->_through) $obj->__set($this->_field, $this->_to->ident());
    foreach($params as $key=>$value) $obj->__set($key, $value);
    return $obj;
	}
	
  /**
  * Remove a specific object from the elated set
  * This is only valid for manytomany relations
  */
	function remove($model) {
    if(is_object($model)) $model = $model->pk;
    return $this->clear($model);
	}
	
  /**
  * Remove all objects from the related set
  * This is only valid for manytomany relations
  */
	function clear($pk=null) {
    if($this->_through) {
      $set = new Dormio_Queryset($this->_through);
      if($pk) {
        $field = $set->_meta->accessorFor($this);
        $set = $set->filter($field, '=', $pk);
      }
      $field = $set->_meta->accessorFor($this->_to);
      $sql = $set->filter($field, '=', $this->_to->ident())->delete();
      return $this->batchExecute($sql);
    } else {
      throw new Dormio_Manager_Exception('Unable to clear foreign key sets');
    }
	} 
}

class Dormio_Manager_Exception extends Dormio_Exception {}
?>
