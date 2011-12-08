<?php

/**
 * Dormio Model
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
 * Django inspired model class.
 * All models should subclass this and provide a static $meta specification
 * @example models.php
 * @package dormio
 */
abstract class Dormio_Model {

  static $_cache_ref = null;
  public $_data = array();
  public $_updated = array();
  private $_related = array();
  public $_db, $_stmt, $_objects, $_dialect;
  public $_meta, $_id = false; // need to be accessed by the manager
  private $_table_aliases = array();
  private $_prefix;
  // overridable meta fields for sub classes
  static $meta = array();
  static $logger = null;
  
  public $display_field = null;

  /**
   * Create a new Model instance using the provided PDO connection.
   * @param  PDO   $db   The connection
   * @param  Dormio_Dialect  $dialect  If provided saves creating a new instance
   */
  function __construct(PDO $db, $dialect=null) {
    $this->_db = $db;
    $this->_meta = Dormio_Meta::get(get_class($this));
    $this->_dialect = ($dialect) ? $dialect : Dormio_Dialect::factory($db->getAttribute(PDO::ATTR_DRIVER_NAME));
    $this->_prefix = $this->_meta->model;
  }

  /**
   * Get the meta for the specified class.
   * Allows intermediate classes to add fields etc...
   * @param  string  $klass  the model to get meta for
   * @return array           the meta spec
   */
  public static function _meta($klass) {
    // if we were 5.3+ we could just use static::$meta but in this one case we pander to backward compatibility
    $vars = get_class_vars($klass);
    return $vars['meta'];
  }

  /**
   * Update the aliases table
   * used to dereference fields
   * @param array $alias_table
   * @param string $prefix 
   * @access private
   */
  function _setAliases($alias_table, $prefix="t1") {
    $this->_table_aliases = $alias_table;
    //$this->_prefix = $prefix;
    //$this->_data = array();
  }

  /**
   * Bulk load prefixed data onto the object
   * @access private
   */
  function _hydrate($data, $prefix=null) {
    $this->_data = array_merge($this->_data, $data);
    if($prefix) $this->_prefix = $prefix;
    $pk = $this->_dataIndex($this->_meta->pk);
    $this->_id = (isset($this->_data[$pk])) ? $this->_data[$pk] : false;
  }

  /**
   * Add the current table prefix onto each of the keys in the array
   * @param array $data Array to modify
   * @return array Source array with the current table prefixed 
   * @access private
   */
  function _prefixData($data) {
    foreach ($data as $key => $value)
      $result["{$this->_prefix}_{$key}"] = $value;
    return $result;
  }

  /**
   * Query the database for the current record.
   * Called when model has been populated with partial data e.g. just PK or joined fields
   * @access private
   */
  function _rehydrate() {
    if (!$this->_id)
      throw new Dormio_Model_Exception("No primary key set for {$this->_meta->model}");
    isset(self::$logger) && self::$logger->log("Rehydrating {$this->_meta->model}({$this->_id})");
    $stmt = $this->_hydrateStmt();
    $stmt->execute(array($this->_id));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if ($data) {
      $this->_hydrate($data, $this->_meta->model);
    } else {
      throw new Dormio_Model_Exception('No result found for primary key ' . $this->_id);
    }
  }

  /**
   * Cache the hydration stmts to improve pk lookups.
   * @access private
   */
  function _hydrateStmt() {

    // slightly cheekily we store proceedures on the actual pdo object
    // tried a static cache but causes problems if you change db handle
    if (!isset($this->_db->_stmt_cache))
      $this->_db->_stmt_cache = array();
    if (!isset($this->_db->_stmt_cache[$this->_meta->model])) {
      $fields = array();
      foreach($this->_meta->fields as $key=>$spec) {
        if(isset($spec['is_field']) && $spec['is_field']) {
          $fields[] = "{{$this->_meta->table}}.{{$spec['db_column']}} AS {{$this->_meta->model}_{$spec['db_column']}}";
        }
      }
      $fields = implode(', ', $fields);
      $sql = "SELECT {$fields} FROM {{$this->_meta->table}} WHERE {{$this->_meta->table}}.{{$this->_meta->pk}} = ?";
      $this->_db->_stmt_cache[$this->_meta->model] = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    }
    return $this->_db->_stmt_cache[$this->_meta->model];
  }

  /**
   * Empty the current object.
   */
  function clear() {
    $this->_data = $this->_updated = array();
    $this->_id = false;
  }

  /**
   * Get the value of the primary key for the current record.
   * @return int|false The primary key or false if unbound
   */
  function ident() {
    return $this->_id;
  }

  /**
   * Get the manager for this model.
   * @return Dormio_Manager A manager instance
   */
  function objects() {
    if (!isset($this->_objects))
      $this->_objects = new Dormio_Manager($this->_meta, $this->_db);
    return $this->_objects;
  }

  /**
   * Get the manager for a related model.
   * @param  string  $field  The field name to resolve
   * @return Dormio_Manager A manager instance
   */
  function manager($field) {
    $spec = $this->_meta->getSpec($field);
    return new Dormio_Manager($spec['model'], $this->_db, $this->_dialect);
  }

  /**
   * Retrieve the raw data.
   * @return array Raw data
   */
  function data() {
    return $this->_data;
  }

  /**
   * Get the key for the data array.
   * This is in the format 'table_field'
   * @param  string  $field  The field name
   * @return string
   * @access private
   */
  function _dataIndex($field) {
    return "{$this->_prefix}_{$field}";
  }
  
  /**
   * Get a value from the data cache
   * No rehydration is performed
   * @param type $column
   * @param type $type
   * @access private
   */
  function _getCachedData($column, $type) {
    $key = $this->_dataIndex($column);
    if(!array_key_exists($key, $this->_data)) throw new Dormio_Model_Exception("No cached data for '{$column}' on model '{$this->_meta->model}'");
    return $this->_fromDB($this->_data[$key], $type);
  }
  
  /**
   * Gets values from the data cache, rehydrating if neccessary
   * All internal functions should use this as it takes care of qualifying the
   * indexes and rehydrating the object if required
   * @access private
   */
  function _getModelData($column, $type) {
    try {
      return $this->_getCachedData($column, $type);
    } catch(Dormio_Model_Exception $dme) {
      $this->_rehydrate();
      return $this->_getCachedData($column, $type);
    }
  }
  
  /**
   * Gets values from the cache or update queue
   * returns updated values if they have been set but not committed
   * @param string $name
   * @param array $spec
   * @return mixed Field value
   */
  function _getCurrentData($column, $type) {
    if (array_key_exists($column, $this->_updated))
      return $this->_updated[$column];
    return $this->_getModelData($column, $type);
  }

  /**
   * Perform any data munging dependant on the underlying database type
   * @param mixed $data Source data
   * @param string $type Field type
   * @return mixed Munged data
   * @access private
   */
  function _fromDB($data, $type) {
    if (is_null($data))
      return null;
    switch ($type) {
      case 'integer':
        return (int) $data;
      case 'float': 
        return (float) $data;
      case 'date':
      case 'datetime':
        return ($data instanceof DateTime) ? $data : new DateTime($data);
      case 'boolean':
        return (bool) $data;
      default:
        return $data;
    }
  }

  /**
   * Setter for all data.
   * All internal functions should use this as it takes care of qualifying the
   * indexes.
   * @access private
   */
  function _setData($column, $value) {
    $this->_data[$this->_dataIndex($column)] = $value;
  }

  /**
   * Force the model to forget a particular value
   * will be rehydrated on next access
   * @param string $name Field name
   */
  function _forget($name) {
    unset($this->_data[$this->_dataIndex($name)]);
  }

  /**
   * Magic access method
   * @access private
   */
  function __get($name) {
    return $this->getField($name);
  }
  
  /**
   * Does the heavy lifting of returning values, related objects and managers.
   * @param string $name  Field name
   * @return mixed Field value or related object
   */
  function getField($name) {

    try {
      $spec = $this->_meta->getSpec($name);
    } catch(Dormio_Meta_Exception $dme) {
      try {
        // this will return any non field data e.g. aggregated results
        return $this->_getCachedData($name, "string");
      } catch(Dormio_Model_Exception $e) {
        throw $dme;
      }
    }
    
    isset($spec['db_column']) || $spec['db_column'] = $this->_meta->pk;

    switch ($spec['type']) {
      case 'foreignkey':
      case 'onetoone':
      case 'onetoone_rev':
        return $this->_getForwardRelation($name, $spec);
      case 'manytomany':
      case 'foreignkey_rev':
        return $this->_getReverseRelation($name, $spec);
      default:
        // everything else is concidered a field on the table
        return $this->_getCurrentData($spec['db_column'], $spec['type']);
    }
  }
  
  /**
   * Get the value for a field on this model
   * no model dereferencing is done so the PK for a foreignkey will be returned
   * @param type $name 
   */
  function getValue($name) {
    // check it is a field
    $spec = $this->_meta->getSpec($name);
    if(!isset($spec['is_field'])) throw new Dormio_Model_Exception("No local field '{$name}' on model '{$this->_meta->model}'");
    
    return $this->_getCurrentData($spec['db_column'], $spec['type']);
  }

  /**
   * Gets model for forward relation
   * @param string $name
   * @param array $spec
   * @return Dormio_Model Related model
   */
  function _getForwardRelation($name, $spec) {
    if (!isset($this->_related[$name]))
      $this->_related[$name] = new $spec['model']($this->_db, $this->_dialect);
    $id = $this->_getCurrentData($spec['db_column'], $spec['type']);
    isset(self::$logger) && self::$logger->log("Preparing {$spec['model']}({$id})");
    if ($this->_related[$name]->ident() != $id) {
      $this->_related[$name]->load($id); // clears the stale data
      // Pass the current data if it is relevant
      // DB is not hit at all in this operation
      $key = "{$this->_meta->model}.{$spec['local_field']}__{$spec['model']}.{$spec['remote_field']}";
      if (isset($this->_table_aliases[$key])) {
        //echo "Reusing data for {$key}\n";
        $this->_related[$name]->_setAliases($this->_table_aliases);
        $this->_related[$name]->_hydrate($this->_data, $this->_table_aliases[$key]);
      }
    }
    return $this->_related[$name];
  }

  /**
   * Gets a Manager for a reverse relation
   * @param string $name
   * @param array $spec
   * @return Dormio_Manager Related manager
   */
  function _getReverseRelation($name, $spec) {
    // relations that return a manager
    // due to the parameters being referenced we dont need to do anything if these are cached
    if (!isset($this->_related[$name])) {
      $this->_related[$name] = new Dormio_Manager_Related($spec, $this->_db, $this->_dialect, $this);
    }
    return $this->_related[$name];
  }

  /**
   * Magic method for assigning updated values
   * @access private
   */
  function __set($name, $value) {
    return $this->setField($name, $value);
  }
  
  /**
   * Update a model value
   * @param string $name
   * @param mixed $value
   */
  function setField($name, $value) {
    if ($name == 'pk')
      throw new Dormio_Model_Exception("Can't update primary key");
    $spec = $this->_meta->getSpec($name);
    if ($value instanceof Dormio_Model) { // use the primary key of objects
      $this->_related[$name] = $value;
      $value = $value->ident();
    }
    if(!isset($spec['is_field'])) throw new Dormio_Model_Exception("Cannot directly set a related field");
    $this->_updated[$spec['db_column']] = $value; // key is un-qualified
  }

  /**
   * Load a record by id.
   * This actually does very little except set the id - it wont be populated until a request is made
   * @param  int   $id   Record to load
   */
  function load($id) {
    $this->clear();
    $this->_setData($this->_meta->pk, $id);
    $this->_id = $id;
  }

  /**
   * Save the record.
   * Will update or insert as appropriate
   */
  function save() {
    if (count($this->_updated) == 0)
      return;
    return ($this->ident() === false) ? $this->insert() : $this->update();
  }

  /**
   * Perform an INSERT of the current record.
   */
  function insert($additional=array()) {
    $merged = array_merge($this->_updated, $additional);
    
    $fields = array_keys($merged);
    foreach ($fields as &$field)
      $field = '{' . $field . '}';
    $fields = implode(', ', $fields);
    $values = implode(', ', array_fill(0, count($this->_updated), '?'));
    $sql = "INSERT INTO {{$this->_meta->table}} ({$fields}) VALUES ({$values})";
    $params = array_values($merged);
    $stmt = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    if ($stmt->execute($params) != 1)
      throw new Dormio_Model_Exception('Insert failed');
    //$this->_insert = false;
    $this->_updated[$this->_meta->pk] = $this->_db->lastInsertId();
    $this->_mergeUpdated();
  }

  /**
   * Perform an UPDATE of the current record.
   */
  function update($additional=array()) {
    $merged = array_merge($this->_updated, $additional);
    $params = array_values($merged);
    foreach (array_keys($merged) as $key)
      $pairs[] = "{{$key}}=?";
    $params[] = $this->ident();
    $pairs = implode(', ', $pairs);
    $sql = "UPDATE {{$this->_meta->table}} SET {$pairs} WHERE {{$this->_meta->pk}} = ?";
    $stmt = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    if ($stmt->execute($params) != 1)
      throw new Dormio_Model_Exception('Insert failed');
    $this->_mergeUpdated();
  }

  /**
   * Moves updated values accross into the main data array.
   * Resets the updated array
   * @access private
   */
  function _mergeUpdated() {
    $data = $this->_prefixData($this->_updated);
    $this->_hydrate($data);
    //$this->_qualified = true;
    $this->_updated = array();
  }

  /**
   * Deletes a record and all its children.
   * @param  bool $preview If true returns an array of statements that would be executed instead of running them
   * @return bool|array   Whether the command succeeded or the commands themselves if previewing
   */
  function delete($preview=false) {
    if ($this->_stmt)
      $this->_stmt->closeCursor(); // can prevent transaction committing
    $objects = $this->objects();
    $sql = $objects->deleteById($this->ident());
    return ($preview) ? $sql : $objects->batchExecute($sql);
  }

  /**
   * The text representation of this record. Should be overriden by subclasses if required
   * Used in HTML SELECT elements
   * @return string The text to display
   */
  function display() {
    return ($this->display_field) ? $this->getValue($this->display_field) : "[{$this->_meta->verbose} {$this->ident()}]";
  }

  /**
   * Human readable representation of the object
   * Uses the result of the display() method
   * @see display()
   * @access private
   */
  function __toString() {
    try {
      if($this->ident()) {
        return (string) $this->display();
      } else {
        return "null";
      }
    } catch (Exception $e) {
      return "[{$this->_meta->model}:{$e->getMessage()}]";
    }
  }
  
  /**
   * Allows submodels to override the default output for a field
   * Checks for render_field_<field>, render_type_<type> and render_default
   * @param string $field the field to render
   * @return string output [defaults to raw value]
   */
  function render($field) {
    $spec = $this->_meta->getSpec($field);
    
    $renderers = array("render_field_{$field}", "render_type_{$spec['type']}", "render_default");
    foreach($renderers as $renderer) {
      if(method_exists($this, $renderer)) return $this->$renderer($field, $this->__get($field));
    }
    return $this->__get($field);
  }
  
  function render_type_password($field, $value) {
    return '********';
  }
  
  function render_type_boolean($field, $value) {
    $value = ($value) ? "yes" : "no";
    return "<p style=\"text-align: center\">{$value}</p>";
  }
}

/**
 * Model exception 
 * @package dormio
 * @subpackage exception
 */
class Dormio_Model_Exception extends Dormio_Exception {
  
}
