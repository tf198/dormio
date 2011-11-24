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
    if(!isset($spec['version'])) $spec['version'] = 1;
    $this->_spec = self::_normalise($klass, $spec);
    
    // helpful pointers
    $this->columns = $this->_spec['fields'];
    $this->table = $this->_spec['table'];
    $this->pk = $this->_spec['fields']['pk']['db_column'];
    $this->version = $this->_spec['version'];
    $this->verbose = isset($spec['verbose']) ? $spec['verbose'] : self::title($this->_klass);
  }
  
  /**
  * Singleton pattern so each model only gets processed once
  */
  public static function get($klass) {
    $klass = strtolower($klass);
    if(!isset(self::$_meta_cache[$klass])) {
      if(!class_exists($klass)) throw new Dormio_Meta_Exception('No such class: ' . $klass);
      self::$_meta_cache[$klass] = new Dormio_Meta($klass, call_user_func(array($klass, '_meta'), $klass));
    }
    return self::$_meta_cache[$klass];
  }
  
  /**
  * Update the fields in place
  * Fills in defaults and generates reverse defininitions and intermediate models as required
  */
  static function _normalise($model, $meta) {
    // check the basic array structure
    isset($meta['indexes']) || $meta['indexes'] = array();
    if(!isset($meta['fields'])) throw new Dormio_Meta_Exception("Missing required 'fields' on meta");
    
    // set a pk but it can be overriden by the fields
    $columns['pk'] = array('type' => 'ident', 'db_column' => $model . "_id", 'is_field' => true, 'verbose' => 'ID');
    
    
    foreach($meta['fields'] as $key=>$spec) {
      isset($spec['verbose']) || $spec['verbose'] = self::title($key);
      
      // we only really care about normalizing related fields at this stage
      if(isset($spec['model'])) {
        $spec['model'] = strtolower($spec['model']); // all meta references are lower case
        
        // set up the required fields based on the type
        switch($spec['type']) {
          case 'foreignkey':    // model, db_column, to_field, on_delete
          case 'onetoone':      // model, db_column, to_field, on_delete
            isset($spec['db_column']) || $spec['db_column'] = strtolower($key) . "_id"; // dereferenced to right(remote) PK on join
            isset($spec['to_field']) || $spec['to_field'] = null; // dereferenced to left(local) PK on join
            isset($spec['on_delete']) || $spec['on_delete'] = ($spec['type']=='foreignkey') ? 'cascade' : 'blank';
            $meta['indexes']["{$key}_0"] = array($spec['db_column'] => true);
            $spec['is_field'] = true;
            $reverse = array('type'=>$spec['type'] . "_rev", 'db_column'=>$spec['to_field'], 'to_field'=>$spec['db_column'], 'model'=>$model, 'on_delete'=>$spec['on_delete'] );
            break;
          
          case 'manytomany':    // model, through, local_field, remote_field
            if(isset($spec['through'])) {
              $through = Dormio_Meta::get($spec['through']);
              isset($spec['local_field']) || $spec['local_field'] = null;
              isset($spec['remote_field']) || $spec['remote_field'] = null;
            } else {
              $through = self::_generateIntermediate($model, $spec);
              $spec['through'] = $through->_klass;
              $spec['local_field'] = 'l_' . $model;
              $spec['remote_field'] = 'r_' . $spec['model'];
            }
            $reverse = array('type'=>'manytomany', 'through'=>$spec['through'], 'model'=>$model, 'local_field'=>$spec['remote_field'], 'remote_field'=>$spec['local_field']);
            break;
            
          case 'reverse':
            $reverse = null; // dont generate a reverse spec
            isset($spec['accessor']) || $spec['accessor'] = null; // will call accessorFor() later
            break;
          
          default:
            throw new Dormio_Meta_Exception('Unknown relation type: ' . $spec['type']);
        }
        // store a reverse spec so we don't need to traverse the columns
        if(isset($reverse)) {
          //$reverse['accessor'] = $key;
          //if(isset($columns['__' . $spec['model']])) throw new Dormio_Meta_Exception("More than one reverse relation for model " . $model);
          // reverse specs stored in array by original accessor
          $columns['__' . $spec['model']][$key] = $reverse;
        }
      } else {
        isset($spec['db_column']) || $spec['db_column'] = strtolower($key);
        $spec['is_field'] = true;
      }
      $columns[$key] = $spec;
    }
    $meta['fields'] = $columns;
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
        "l_{$model}" => array('type' => 'foreignkey', 'model' => $model),
        "r_{$spec['model']}" => array('type' => 'foreignkey', 'model' => $spec['model']),
      ),
    );
    $obj = new Dormio_Meta($table, $meta);
    self::$_meta_cache[$table] = $obj;
    return $obj;
  }
  
  /**
  * Replaces underscores with spaces and capitalises the first letter of each word.
  * @param  string  $str  the text to use
  * @return string        modified text
  */
  public static function title($str) {
    return ucwords(str_replace('_', ' ', $str));
  }
  
  /**
  * Returns a table schema for the model without all the relation stuff
  */
  function schema() {
    if(isset($this->_schema)) return $this->_schema;
    $this->_schema = self::_schema($this->_spec);
    return $this->_schema;
  }
  
  /**
  * Converts a meta array into a schema array.
  * Removes non-field entries and renames 'fields' to 'columns'
  * @param  array   $spec   Meta spec
  * @return array           Schema spec
  */
  static function _schema($spec) {
    $spec['columns'] = array_filter($spec['fields'], array('Dormio_Meta', 'filterSchema'));
    unset($spec['fields']);
    return $spec;
  }
  
  /**
  * array_filter for schema()
  * @access private
  */
  static function filterSchema($spec) {
    return (isset($spec['is_field']));
  }
  
  /**
  * Get an array of DB columns (unqualified)
  */
  function DBColumns() {
    $schema = $this->schema();
    $result = array();
    foreach($schema['columns'] as $spec) $result[] = $spec['db_column'];
    return $result;
  }
  
  /**
  * Get an array of field names
  */
  function columns() {
    $schema = $this->schema();
    return array_keys($schema['columns']);
  }
  
  /**
  * Get an array of sql columns suitable for use in a qualified select
  * Format: {TABLE}.{FIELD} AS {TABLE}_{FIELD}
  * @return array   An array of prefixed fields
  */
  function prefixedDBColumns() {
    //$result = array();
    //foreach($this->DBColumns() as $field) $result[] = $this->prefixDBColumn($field);
    //return $result;
    return array_map(array($this, 'prefixDBColumn'), $this->DBColumns());
  }
  
  /**
  * Prefix a field for use in sql statement
  * @return string "{TABLE}.{FIELD} AS {TABLE}_{FIELD}"
  */
  function prefixDBColumn($field) {
    return "{{$this->table}}.{{$field}} AS {{$this->table}_{$field}}";
  }
  
  /**
  * Get column spec by name
  * Will also dereference "model_set" to reverse relations
  */
  /*function column($name) {
    // additional method of accessing reverse relations
    if(substr($name, -4)=='_set') {
      $name = substr($name, 0, -4);
      return array('type' => 'reverse', 'model' => $name); // temporary simplification
    }
    
    if(!isset($this->columns[$name])) throw new Dormio_Meta_Exception('No such field: ' . $name);
    return $this->columns[$name];
  }*/
  
  /**
  * Get the first field name that maps to a particular model
  */
  function accessorFor($model) {
    if(is_object($model)) $model = $model->_meta->_klass;
    $reverse = '__' . strtolower($model);
    if(!isset($this->columns[$reverse])) throw new Dormio_Meta_Exception("No reverse relation found for {$model} on {$this->_klass}");
    $keys = array_keys($this->columns[$reverse]);
    return $keys[0];
  }
  
  function getSpec($name) {
    if(!isset($this->columns[$name])) throw new Dormio_Meta_Exception("No field '{$name} on '{$this->_klass}'");
    return $this->columns[$name];
  }
  
  function getReverseSpec($name, $accessor=null) {
    $reverse = "__" . $name;
    if(!isset($this->columns[$reverse])) throw  new Dormio_Meta_Exception("No reverse relation for '{$name}' on '{$this->_klass}'");
    if($accessor) {
      if(!isset($this->columns[$reverse][$accessor])) throw new Dormio_Meta_Exception("No reverse accessor '{$name}.{$accessor}' on '{$this->_klass}'");
      return $this->columns[$reverse][$accessor];
    } else {
      $specs = array_values($this->columns[$reverse]);
      return $specs[0];
    }
  }
  
  /**
  * Resolve a field name to a usable spec and meta
  * All the black magic happens here with reverse relations etc...
  * @param  $name   string  The field name
  * @param  &$spec  &array  This will have the target spec in it
  * @param  &$meta  &array  This will have the target meta in it
  */
  function resolve($name, &$spec, &$meta) {
    
    // dereference model_set names
    if(substr($name, -4)=='_set') {
      $name = substr($name, 0, -4);
      $spec = array('type' => 'reverse', 'model' => $name, 'accessor' => null);
    } else {
      $spec = $this->getSpec($name);
    }
    
    if($spec['type']=='reverse') {
      $meta = Dormio_Meta::get($spec['model']);
      $spec = $meta->getReverseSpec($this->_klass, $spec['accessor']);
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
    $local = dirname(__FILE__) . "/config/{$section}.php";
    $config = (file_exists($local)) ? include(dirname(__FILE__) . "/config/{$section}.php") : array();
    if(self::$config_loader) $config = array_merge($config, call_user_func(self::$config_loader, $section));
    return $config;
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Meta_Exception extends Dormio_Exception {}
?>