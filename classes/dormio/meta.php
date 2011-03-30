<?
/**
* Meta Class
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
* Class to store meta information about column structure and relationships
* PHP has no metaclassing so we use singletons instead and the instances
* create a reference _meta when initialised
* @package dormio
*/
class Dormio_Meta {
  // cache for table meta
  static $_meta_cache = array();
  
  static $config_loader = false;
  
  /**
  * Singleton constructor
  * Normalises the meta
  */
  private function __construct($klass, $spec) {
    $this->_klass = $klass;
    if(!isset($spec['table'])) $spec['table'] = $klass;
    $this->_spec = self::_normalise($klass, $spec);
    
    // helpful pointers
    $this->columns = $this->_spec['fields'];
    $this->table = $this->_spec['table'];
    $this->pk = $this->_spec['fields']['pk']['sql_column'];
    $this->verbose = isset($spec['verbose']) ? $spec['verbose'] : self::title($this->_klass);
  }
  
  /**
  * Singleton pattern so each model only gets processes once
  */
  public static function get($klass) {
    $klass = strtolower($klass);
    if(!isset(self::$_meta_cache[$klass])) {
      if(!class_exists($klass)) throw new Dormio_Meta_Exception('No such class: ' . $klass);
      self::$_meta_cache[$klass] = new Dormio_Meta($klass, call_user_func(array($klass, 'getMeta')));
    }
    return self::$_meta_cache[$klass];
  }
  
  /**
  * Update the fields in place
  * Fills in defaults and generates reverse defininitions and intermediate models as required
  */
  private static function _normalise($model, $meta) {
    if(!isset($meta['indexes'])) $meta['indexes'] = array();
    // set a pk but it can be overriden by the fields
    $columns['pk'] = array('type' => 'ident', 'sql_column' => $model . "_id", 'is_field' => true);
    if(!isset($meta['fields'])) throw new Dormio_Meta_Exception("Missing required 'fields' on meta");
    foreach($meta['fields'] as $key=>$spec) {
      isset($spec['verbose']) || $spec['verbose'] = self::title($key);
      if(isset($spec['model'])) { // relations
        $spec['model'] = strtolower($spec['model']); // all meta references are lower case
        switch($spec['type']) {
          case 'foreignkey':
          case 'onetoone':
            isset($spec['sql_column']) || $spec['sql_column'] = strtolower($key) . "_id";
            isset($spec['to_field']) || $spec['to_field'] = null; // dereferenced by queryset builder
            isset($spec['on_delete']) || $spec['on_delete'] = ($spec['type']=='foreignkey') ? 'cascade' : 'blank';
            $meta['indexes']["{$key}_0"] = array($spec['sql_column'] => true);
            $spec['is_field'] = true;
            $reverse = array('type' => $spec['type'] . "_rev", 'sql_column' => $spec['to_field'], 'to_field' => $spec['sql_column'], 'model' => $model, 'on_delete' => $spec['on_delete'] );
            break;
          case 'manytomany':
            if(!isset($spec['through'])) $spec['through'] = self::_generateIntermediate($model, $spec);
            $reverse = array('type' => 'manytomany', 'through' => $spec['through'], 'model' => $model);
            break;
          case 'reverse':
            $reverse = null; // dont generate a reverse spec
            break;
          default:
            throw new Dormio_Meta_Exception('Unknown relation type: ' . $spec['type']);
        }
        // store a reverse spec so we don't need to traverse the columns
        if(isset($reverse)) {
          $reverse['accessor'] = $key;
          $columns['__' . $spec['model']] = $reverse;
        }
      } else {
        isset($spec['sql_column']) || $spec['sql_column'] = strtolower($key);
        $spec['is_field'] = true;
      }
      $columns[$key] = $spec;
    }
    $meta['fields'] = $columns;
    //$meta['indexes'] = array_unique($meta['indexes']);
    return $meta;
  }
  
  /**
  * Create a fake model for use in joins and schema generation
  */
  private static function _generateIntermediate($model, $spec) {
    $table = ($model < $spec['model']) ? "{$model}_{$spec['model']}" : "{$spec['model']}_{$model}";
    $meta = array(
      'table' => $table,
      'fields' => array(
        $model => array('type' => 'foreignkey', 'model' => $model),
        $spec['model'] => array('type' => 'foreignkey', 'model' => $spec['model']),
      ),
    );
    $obj = new Dormio_Meta($table, $meta);
    self::$_meta_cache[$table] = $obj;
    return $table;
  }
  
  public static function title($str) {
    return ucwords(str_replace('_', ' ', $str));
  }
  
  /**
  * Returns a table schema for the model without all the relation stuff
  */
  function schema() {
    if(isset($this->_schema)) return $this->_schema;
    $this->_schema = $this->_spec;
    $this->_schema['columns'] = array_filter($this->_schema['fields'], array($this, 'filterSchema'));
    unset($this->_schema['fields']);
    return $this->_schema;
  }
  
  function filterSchema($spec) {
    return (isset($spec['is_field']));
  }
  
  /**
  * Get an array of sql fields (unqualified)
  */
  function sqlFields() {
    $schema = $this->schema();
    $result = array();
    foreach($schema['columns'] as $spec) $result[] = $spec['sql_column'];
    return $result;
  }
  
  /**
  * Get an array of field names
  */
  function fields() {
    $schema = $this->schema();
    return array_keys($schema['columns']);
  }
  
  /**
  * Get the field name that maps to a particular model
  */
  function accessorFor($model) {
    if(is_object($model)) $model = $model->_meta->_klass;
    $reverse = '__' . $model;
    if(!isset($this->columns[$reverse])) throw new Dormio_Meta_Exception('No reverse relation found for ' . $model);
    return $this->columns[$reverse]['accessor'];
  }
  
  /**
  * Get an array of sql columns suitable for use in a qualified select
  * Format: {TABLE}.{FIELD} AS {TABLE}_{FIELD}
  * @return array   An array of prefixed fields
  */
  function prefixedSqlFields() {
    $result = array();
    foreach($this->sqlFields() as $field) $result[] = $this->prefixSqlField($field);
    return $result;
  }
  
  /**
  * Prefix a field for use in sql statement
  * @return string "{TABLE}.{FIELD} AS {TABLE}_{FIELD}"
  */
  function prefixSqlField($field) {
    return "{{$this->table}}.{{$field}} AS {{$this->table}_{$field}}";
  }
  
  /**
  * Get column spec by name
  * Will also dereference "model_set" to reverse relations
  */
  function column($name) {
    // additional method of accessing reverse relations
    if(substr($name, -4)=='_set') {
      $name = substr($name, 0, -4);
      // check if it is actually defined on this model - allows use of both defined and _set notation
      if(isset($this->columns["__{$name}"])) { 
        $name = $this->columns["__{$name}"]['accessor'];
      } else {
        // otherwise fake a reverse type
        return array('type' => 'reverse', 'model' => $name);
      }
    }
    
    if(!isset($this->columns[$name])) throw new Dormio_Meta_Exception('No such field: ' . $name);
    return $this->columns[$name];
  }
  
  /**
  * Resolve a field name to a usable spec and meta
  * All the black magic happens here with reverse relations etc...
  * @param  $name   string  The field name
  * @param  &$spec  &array  This will have the target spec in it
  * @param  &$meta  &array  This will have the target meta in it
  */
  function resolve($name, &$spec, &$meta) {
    $spec = $this->column($name);
    //var_dump($spec);
    if($spec['type']=='reverse') {
      $meta = Dormio_Meta::get($spec['model']);
      $spec = $meta->column("__{$this->_klass}");
    } else {
      $meta = $this;
    }
  }
  
  /**
  * Instance factory
  * Get a new instance of the underlying model
  */
  function instance($db, $dialect) {
    $klass = $this->_klass;
    return new $klass($db, $dialect);
  }
  
  /**
  * Overloadable config file loader.
  * Provides enough basic functionality to run independently but is easily augmented by a framework adapter
  * by setting Dormio_Meta::$config_loader to a callback.
  * Tacked on to meta as it is small and always loaded.
  * @param  string  $section  The section(file) to load
  * @return array
  */
  static function config($section) {
    $config = include(dirname(__FILE__) . "/config/{$section}.php");
    if(self::$config_loader) $config = array_merge($config, call_user_func(self::$config_loader, $section, $value));
    return $config;
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Meta_Exception extends Dormio_Exception {}
?>