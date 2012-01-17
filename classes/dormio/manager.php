<?php

/**
 * Dormio Manager
 *
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
 * @version 0.3
 * @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
 * @package dormio
 */

/**
 * Based heavily on django models.Manager functionality.
 * Supports most of the django query language with few modifications.
 * This extends Dormio_Queryset giving it the ability to actually interact with
 * the database.
 * @see Dormio_Queryset
 * @package dormio
 * @example usage.php Example usage
 */
class Dormio_Manager extends Dormio_Queryset implements IteratorAggregate, Countable {

  protected $_db = null;
  protected $_stmt = null;
  protected $_iter = null;
  protected $_qualified = true;
  public $unbuffered = false;

  /**
   * Create a new manager object based on the supplied meta.
   * @param  string|Dormio_Meta  $meta The meta class to use
   * @param  PDO                 $db   A valid PDO instance
   * @param  Dormio_Dialect      $dialect  The dialect for the $db. If not provided will be created.
   */
  function __construct($meta, $db, $dialect=null) {
    $this->_db = $db;
//if(!$dialect) throw new Exception();
    if (!$dialect)
      $dialect = Dormio_Dialect::factory($db->getAttribute(PDO::ATTR_DRIVER_NAME));
    parent::__construct($meta, $dialect);
  }

  /**
   * Need to clear the stored statement when we are cloned.
   * @access private
   */
  function __clone() {
    $this->_stmt = null;
    $this->_iter = null;
  }

  /**
   * Get a single object based on the current query.
   *
   * @param  int  $pk   Optional primary key for object
   * @return Dormio_Model   The model
   * @throws Dormio_Manager_Exception   If the query doesn't return one object
   */
  function get($pk=null) {
    $o = $this->limit(2);
    if ($pk)
      $o = $o->filter('pk', '=', $pk);
    $o->evaluate();
    $o->_stmt->execute($o->params);
    $data = $o->_stmt->fetchall(PDO::FETCH_ASSOC);
    if (isset($data[1]))
      throw new Dormio_Result_Multiple_Exception('More than one record returned');
    if (!isset($data[0]))
      throw new Dormio_Result_Empty_Exception('No record returned');
    $model = $this->_meta->instance($this->_db, $this->dialect);
    $model->_setAliases($this->aliases);
    $model->_hydrate($data[0], $this->_alias);
    return $model;
  }
  
  /**
   * Create a new instance of the underlying model
   * @param array  $values Default values for fields
   * @return Dormio_Model
   */
  function create($values=array()) {
    $obj = $this->_meta->instance($this->_db, $this->dialect);
    $obj->setBulk($values);
    return $obj;
  }
  
  /**
   * Load a specific model from the database or return a new instance if it doesn't exist
   * Will populate the new model with the given values if a create() is required
   * Will use the current query if $pk is null.
   * @param int $pk Select the given primary key
   * @param array $values Default values for fields
   * @return Dormio_Model
   */
  function getOrCreate($pk=null, $values=array()) {
    try {
      return $this->get($pk);
    } catch(Dormio_Manager_Exception $dme) {
      if($dme->getMessage()=='No record returned') return $this->create($values);
      throw $dme;
    }
  }

  /**
   * Get an Aggregation object.
   * @return Dormio_Aggregation
   */
  function aggregate() {
    return new Dormio_Aggregation(clone $this);
  }

  /**
   * Get a copy of the current query with no select parameters.
   * @return Dormio_Manager
   */
  function clear() {
    $o = clone $this;
    $o->query['select'] = array();
    return $o;
  }

  /**
   * Bulk Update.
   * Updates the values of all rows that match the current query to those in $params
   * e.g <code>$set->filter->('colour', '=', 'Red')->update(array('colour' => 'blue', 'sound' => 'honk'));</code>
   * Will update colour and sound on all rows where colour='Red'
   *
   * @param  array   $params   A set of key => values to be updated 
   * @return int               The number of rows updated
   */
  function update($params, $custom_fields=array(), $custom_params=array()) {
    $sql = parent::updateSQL($params, $custom_fields, $custom_params);
    return $this->batchExecute(array($sql), false);
  }

  /**
   * Bulk insert.
   * Provides a method of efficiently loading a lot of data e.g. from csv
   * <code>
   * $stmt = $blogs->insert(array('title', 'user'));
   * $stmt->execute(array('Test 1', 1));
   * $stmt->execute(array('Test 2', 1));
   * </code>
   * @param  array $fields   The field names to be inserted in the correct order
   * @return PDOStatement    A statement suitable for bulk insertion
   * @throws Dormio_Manager_Exception if the statement can't be created
   */
  function insert($fields) {
// can just flip as we are discarding the params
    $sql = parent::insertSQL(array_flip($fields));
    $stmt = $this->_db->prepare($sql[0]);
    if (!$stmt) {
      $err = $this->_db->errorInfo();
      throw new Dormio_Manager_Exception($err[2]);
    }
    return $stmt;
  }

  /**
   * Bulk delete
   * Deletes rows and related rows based on the current query. Note that this
   * will follow relations where cascade is set.
   * e.g. <code>$set->filter('age', '<', 16)->delete();</code>
   *
   * @param  bool $preview  Return the statatements that wiould be executed instead of running
   * @return int         The number of rows deleted
   */
  function delete($preview=false) {
    $sql = parent::deleteSQL();
    return ($preview) ? $sql : $this->batchExecute($sql);
  }

  /**
   * Runs a query and returns the first row.
   * @param  array $query    Query array
   * @param  int   $fetch    Fetch mode to use
   * @return array
   */
  function query($query, $fetch=PDO::FETCH_ASSOC) {
    try {
      $stmt = $this->_db->prepare($query[0]);
      $stmt->execute($query[1]);
      $result = $stmt->fetch($fetch);
      $stmt->closeCursor();
      return $result;
    } catch (PDOException $e) {
      throw new Dormio_Exception("Failed to execute: {$query[0]}\n{$e}");
    }
  }

  /**
   * Execute a single sql statement that doesn't return a result set
   * e.g. UPDATE, INSERT, DELETE
   * @param  $query    The query in the form array('SQL', array('param1', ...))
   * @return int       The number of affected rows
   * @throws Dormio_Exception If it cannot execute the query
   */
  function execute($query) {
    if (!is_array($query))
      $query = array($query, array());
    try {
      $stmt = $this->_db->prepare($query[0]);
      $stmt->execute($query[1]);
      $c = $stmt->rowCount();
//$stmt->closeCursor();
      return $c;
    } catch (PDOException $e) {
      throw new Dormio_Exception("Failed to execute: {$query[0]}\n{$e}");
    }
  }

  /**
   * Execute many queries in a single transaction.
   * Use for INSERT, UPDATE and DELETE only
   * @param  array   $sql          An array of queries suitable for passing to execute()
   * @param boolean $transaction   Whether to wrap in a transaction or not
   * @return int                   The total number of affected rows
   */
  function batchExecute($sql, $transaction=true) {
    if ($transaction)
      $this->_db->beginTransaction();
    $result = 0;
    foreach ($sql as $q) {
      $result += $this->execute($q);
    }
    if ($transaction)
      $this->_db->commit();
    return $result;
  }

  /**
   * Load some custom SQL into the manager.
   * Will dereference fields e.g. %name% and quote identifiers {name}.
   * SELECT * FROM %table% WHERE %name%=?
   * @param string $sql           SQL Query
   * @param array $params         Query parameters
   * @param boolean $dereference  Whether or not to dereference %fields$
   * @param boolean $qualified    Whether or not the fields are qualified
   */
  public function customSQL($sql, $params, $dereference=true, $alias=null) {
    if ($dereference)
      $sql = $this->_resolveString($sql);
    $sql = $this->dialect->quoteIdentifiers($sql);
    if($alias) $this->_alias = $alias;
    $this->evaluate($sql, $params);
  }

  /**
   * Compile the current query and store the PDOStatment for execution.
   * Can be overriden with custom SQL and parameters.
   * Manager instance cannot be modified after this.
   * @param  string  $sql    Custom sql (schema specific)
   * @param  array   $params Custom parameters
   */
  public function evaluate($sql=false, $params=false) {
    if ($this->_stmt)
      throw new Dormio_Manager_Exception('Statement already compiled');
    if (!$sql) {
      $query = $this->selectSQL();
      $sql = $query[0];
    }
    if ($params)
      $this->params = $params;
//print_r($query);
    $this->_stmt = $this->_db->prepare($sql);
  }
  
  /**
   * Execute the query and return the values
   * @return array
   */
  public function values($fetch=PDO::FETCH_ASSOC) {
    if(!$this->_stmt)
      $this->evaluate();
    $this->_stmt->execute($this->params);
    return $this->_stmt->fetchAll($fetch);
  }

  /**
   * Get an iterator for the current queryset
   * Note: a querset can only be evaluated once
   * @return Iterator
   */
  public function getIterator() {
    if (!$this->_stmt)
      $this->evaluate();
    if (!$this->_iter) {
      $model = $this->_meta->instance($this->_db, $this->dialect);
      $model->_setAliases($this->aliases);
      
      if($this->unbuffered) {
        $this->_iter = new Dormio_Iterator_Unbuffered($this->_stmt, $this->params, $model, $this->_alias);
      } else {
        $this->_iter = new Dormio_Iterator($this->_stmt, $this->params, $model, $this->_alias);
      }
    }
    return $this->_iter;
  }
  
  public function count() {
    if($this->_iter) return $this->_iter->count();
    
    $o = clone($this);
    $o->query['select'] = array("COUNT(*) AS result");
    $result = $this->query($o->selectSQL());
    return $result['result'];
  }
  
  /**
   * Find out whether a there are results for this query
   * This actually executes the query on the basis you are going to want the results if they are available
   * use count() if you do not want to execute the full query
   * @return bool Whether results are available
   */
  public function hasResults() {
    $this->getIterator();
    return ($this->_iter->count()>0);
  }

  public function __toString() {
    return "<Dormio_Manager::{$this->_meta->model}>";
  }

}

/**
 * Traversable dataset - buffers the results
 * @package dormio
 * @subpackage manager
 * @todo This should probably subclass ArrayIterator
 */
class Dormio_Iterator implements Iterator, Countable {

  function __construct($stmt, $params, $model, $alias) {
    $this->_model = $model;
    $this->_stmt = $stmt;
    $this->_params = $params;
    $this->_alias = $alias;
    $this->_iter = null;
  }

  /**
   * Rewind the iterator.
   * Actual execution is done here
   */
  function rewind() {
    $this->_stmt->execute($this->_params);
    $this->_data = $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
    $this->_iter = new ArrayIterator($this->_data);
    $this->_iter->rewind();
    $this->_model->clear();
  }

  /**
   * Advance the iterator.
   */
  function next() {
    $this->_iter->next();
  }

  /**
   * Is there a current model.
   */
  function valid() {
    return $this->_iter->valid();
  }

  /**
   * Returns the current model.
   */
  function current() {
    $data = $this->_iter->current();
    if ($data) {
      $this->_model->_hydrate($data, $this->_alias); // dont need to clear as should be the same fields each time
    } else {
      $this->_model->clear();
      $this->_data = null;
      $this->_iter = null;
    }
    return $this->_model;
  }

  /**
   * Returns the current model ident, or false.
   * @return int|false
   */
  function key() {
    return $this->_iter->key();
  }
  
  function count() {
    if(!$this->_iter) $this->rewind();
    return $this->_iter->count();
  }

}

/**
 * Traversable dataset - non buffering
 * @package dormio
 * @subpackage manager
 */
class Dormio_Iterator_Unbuffered implements Iterator {

  function __construct($stmt, $params, $model, $alias) {
    $this->_model = $model;
    $this->_stmt = $stmt;
    $this->_params = $params;
    $this->_alias = $alias;
  }

  /**
   * Rewind the iterator.
   */
  function rewind() {
    $this->_stmt->execute($this->_params);
    $this->_model->clear();
    $this->next();
  }

  /**
   * Advance the iterator.
   */
  function next() {
    $data = $this->_stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
      $this->_model->_hydrate($data, $this->_alias);
    } else {
      $this->_model->clear();
    }
  }

  /**
   * Is there a current model.
   */
  function valid() {
    return ($this->_model->ident());
  }

  /**
   * Returns the current model.
   */
  function current() {
    return $this->_model;
  }

  /**
   * Returns the current model ident, or false.
   * @return int|false
   */
  function key() {
    return $this->_model->ident();
  }
  
  function count() {
    throw new Dormio_Manager_Exception("Unable to count an unbuffered query");
  }

}

/**
 * Additional methods where there is a related object.
 * @package dormio
 * @subpackage manager
 */
class Dormio_Manager_Related extends Dormio_Manager {
  
  private $manytomany;

  function __construct($spec, $db, $dialect, $parent) {
    if (!$parent->ident())
      throw new Dormio_Manager_Exception('Model needs to be saved first');
    $this->_parent = $parent;
    parent::__construct($spec['model'], $db, $dialect);
    
    $this->manytomany = isset($spec['through']);

    if($this->manytomany) {
      // we only want to do a half join so we need to do it manually
      $this->_through = Dormio_Meta::get($spec['through']);
      $this->_map_self_field = $this->_through->getAccessorFor($this->_meta->model, $spec['map_remote_field']);
      $reverse = $this->_through->getReverseSpec($this->_meta->model, $this->_map_self_field);
      
      // get the field that maps to the parent
      $this->_map_parent_field = $this->_through->getAccessorFor($parent, $spec['map_local_field']);
      
      // manually do the join to ensure it is onto the right side
      $alias = $this->_alias;
      $this->_addJoin($this->_meta, $reverse, 'INNER', $alias);
      
      // add the query
      $column = $this->_through->getColumn($this->_map_parent_field);
      $this->query['where'][] = "<@{$alias}.@>{{$column}} = ?";
      $this->params[] = &$this->_parent->_id;
    } else { // is foreign key
      // can just use the native queryset method
      $this->_field = $this->_meta->getAccessorFor($parent, $spec['remote_field']);
      $this->filterVar($this->_field, '=', $this->_parent->_id, false);
    }
    
  }

  /**
   * Adds the specified object to the related set.
   * @param  Dormio_Model $obj  The object to add to the set
   */
  function add($obj) {
    
    if ($this->manytomany) {
      if($obj instanceof Dormio_Model) {
        $obj->save();
        $pk = $obj->ident();
      } else {
        $pk = $obj;
      }
      
      $intermediate = new Dormio_Manager($this->_through, $this->_db, $this->dialect);
      $fields = array($this->_map_parent_field, $this->_map_self_field);
      $stmt = $intermediate->insert($fields);
      $stmt->execute(array($this->_parent->ident(), $pk));
      
    } else {
      if ($obj->_meta->model != $this->_meta->model)
        throw new Dormio_Manager_Exception('Can only add like objects');
// update the foreign key on the supplied object
      $obj->__set($this->_field, $this->_parent->ident());
      $obj->save();
    }
  }

  /**
   * Creates a new instance of the related item.
   * Note that manytomany relations are not automatically added, you need
   * to manually call add()
   * <code>
   * $tag = $blog->tags->create(array('tag' => 'Grey'));
   * $blog->tags->add($tag);
   * </code>
   * @param  array   $params   Values to set
   * @return Dormio_Model      The created instance
   */
  function create($params=array()) {
    $obj = $this->_meta->instance($this->_db, $this->dialect);
    if (!$this->manytomany)
      $obj->__set($this->_field, $this->_parent->ident());
    foreach ($params as $key => $value)
      $obj->__set($key, $value);
    return $obj;
  }

  /**
   * Remove a specific object from the related set.
   * This is only valid for manytomany relations
   * @param  int|Dormio_Model  $model  The model to remove from the set
   */
  function remove($model) {
    if (is_object($model))
      $model = $model->pk;
    return $this->clear($model);
  }

  /**
   * Remove all objects from the related set
   * This is only valid for manytomany relations
   * @param  int $pk   Can optionally just remove one item
   */
  function clear($pk=null) {
    if ($this->manytomany) {
      $set = new Dormio_Queryset($this->_through);
      if ($pk) {
        $set = $set->filter($this->_map_self_field, '=', $pk);
      }
      $sql = $set->filter($this->_map_parent_field, '=', $this->_parent->ident())->deleteSQL();
      return $this->batchExecute($sql);
    } else {
      throw new Dormio_Manager_Exception('Unable to clear foreign key sets');
    }
  }

}

/**
 * @package dormio
 * @subpackage exception
 */
class Dormio_Manager_Exception extends Dormio_Exception {}

class Dormio_Result_Empty_Exception extends Dormio_Manager_Exception {}

class Dormio_Result_Multiple_Exception extends Dormio_Manager_Exception {}