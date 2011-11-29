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
    $this->_spec = self::_normalise($klass, $spec);
    
    // set up some helpful pointers
    $this->columns = $this->_spec['fields'];
    $this->reverse = $this->_spec['reverse'];
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
   * @todo Remove verbose field as generated in constructor
  */
  static function _normalise($model, $meta) {
    if(!isset($meta['fields'])) throw new Dormio_Meta_Exception("Missing required 'fields' on meta");
    // check the basic array structure
    $defaults = array(
        'table' => $model,
        'version' => 1,
        'reverse' => array(),
        'indexes' => array(),
    );
    $meta = array_merge($defaults, $meta);
    
    // default pk - can be overriden by the fields
    $columns['pk'] = array('type' => 'ident', 'db_column' => $model . "_id", 'is_field' => true, 'verbose' => 'ID');
    
    
    foreach($meta['fields'] as $key=>$spec) {
      if(!isset($spec['type'])) throw new Dormio_Meta_Exception("'type' required on field '{$key}'");
      
      // we only really care about normalizing related fields at this stage
      if(isset($spec['model'])) {
        $spec['model'] = strtolower($spec['model']); // all meta references are lower case
        
        // set up the required fields based on the type
        switch($spec['type']) {
          case 'foreignkey':    // model, db_column, remote_field, on_delete
          case 'onetoone':      // model, db_column, remote_field, on_delete
            $defaults = array(
                'verbose' => self::title($key), 
                'db_column' => strtolower($key) . "_id",
                'null_ok' => false,
                'local_field' => $key,
                'remote_field' => 'pk',
                'on_delete' => ($spec['type']=='foreignkey') ? 'cascade' : 'blank',
                'is_field' => true,
            );
            $spec = array_merge($defaults, $spec);
            $reverse = array(
                'type' => $spec['type'] . "_rev", 
                'local_field' => $spec['remote_field'], 
                'remote_field' => $key, 
                'model' => $model, 
                'on_delete' => $spec['on_delete']
            );
            
            // add an index on the field
            $meta['indexes']["{$key}_0"] = array($spec['db_column'] => true);
            break;
          
          case 'manytomany':    // model, through, local_field, remote_field
            $defaults = array(
                'verbose' => self::title($key),
                'through' => null,
                'map_local_field' => null,
                'map_remote_field' => null,
            );
            $spec = array_merge($defaults, $spec);
            //if(isset($spec['through'])) {
            if($spec['through']) {
              // load the spec
              Dormio_Meta::get($spec['through']);
            } else {
              $through = self::_generateIntermediate($model, $spec);
              $spec['through'] = $through->_klass;
              $spec['map_local_field'] = 'l_' . $model;
              $spec['map_remote_field'] = 'r_' . $spec['model'];
            }
            $reverse = array('type'=>'manytomany', 'through'=>$spec['through'], 'model'=>$model, 'map_local_field'=>$spec['map_remote_field'], 'map_remote_field'=>$spec['map_local_field']);
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
          //if(isset($columns['__' . $spec['model']])) throw new Dormio_Meta_Exception("More than one reverse relation for model " . $model);
          // reverse specs stored in array by original accessor
          //$columns['__' . $spec['model']][$key] = $reverse;
          $meta['reverse'][$spec['model']][$key] = $reverse;
          //$meta['reverse'][] = $spec['model'];
        }
      } else {
        $defaults = array('verbose' => self::title($key), 'db_column' => strtolower($key), 'null_ok' => false, 'is_field' => true);
        $spec = array_merge($defaults, $spec);
        //isset($spec['db_column']) || $spec['db_column'] = strtolower($key);
        //$spec['is_field'] = true;
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
    $spec['columns'] = array_filter($spec['fields'], array('Dormio_Meta', '_filterSchema'));
    unset($spec['fields']);
    return $spec;
  }
  
  /**
  * array_filter for schema()
  * @access private
  */
  static function _filterSchema($spec) {
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
  * Get the first field name that maps to a particular model
  */
  function accessorFor($model, $accessor=null) {
    /*
    if(is_object($model)) $model = $model->_meta->_klass;
    $reverse = '__' . strtolower($model);
    if(!isset($this->columns[$reverse])) throw new Dormio_Meta_Exception("No reverse relation found for {$model} on {$this->_klass}");
    $keys = array_keys($this->columns[$reverse]);
    return $keys[0];
    */
    if(is_object($model)) $model = $model->_meta->_klass;
    if(!isset($this->reverse[$model])) throw  new Dormio_Meta_Exception("No reverse relation for '{$model}' on '{$this->_klass}'");
    if($accessor) {
      if(!isset($this->reverse[$model][$accessor])) throw new Dormio_Meta_Exception("No reverse accessor '{$model}.{$accessor}' on '{$this->_klass}'");
      return $accessor;
    } else {
      reset($this->reverse[$model]);
      return key($this->reverse[$model]);
    }
  }
  
  /**
   * Get a reverse specification for a given model
   * @param string $name Model name
   * @param string $accessor <optional> Return a specific spec instead of the first defined
   * @return array
   */
  function getReverseSpec($model, $accessor=null) {
    /*
    $reverse = "__" . $name;
    if(!isset($this->columns[$reverse])) throw  new Dormio_Meta_Exception("No reverse relation for '{$name}' on '{$this->_klass}'");
    if($accessor) {
      if(!isset($this->columns[$reverse][$accessor])) throw new Dormio_Meta_Exception("No reverse accessor '{$name}.{$accessor}' on '{$this->_klass}'");
      return $this->columns[$reverse][$accessor];
    } else {
      $specs = array_values($this->columns[$reverse]);
      return $specs[0];
    }
  */
    $accessor = $this->accessorFor($model, $accessor);
    return $this->reverse[$model][$accessor];
  }
  
  /**
  * Resolve a field name to a usable spec and meta
  * All the black magic happens here with reverse relations etc...
  * @param  $name   string  The field name
  */
  function getSpec($name) {
    
    // dereference model_set names
    if(substr($name, -4)=='_set') {
      $name = substr($name, 0, -4);
      $spec = array('type' => 'reverse', 'model' => $name, 'accessor' => null);
    } else {
      if(!isset($this->columns[$name])) throw new Dormio_Meta_Exception("No field '{$name} on '{$this->_klass}'");
      $spec = $this->columns[$name];
    }
    
    if($spec['type']=='reverse') {
      $meta = Dormio_Meta::get($spec['model']);
      $spec = $meta->getReverseSpec($this->_klass, $spec['accessor']);
    }
    
    return $spec;
  }
  
  function getColumn($name) {
    $spec = $this->getSpec($name);
    return $spec['db_column'];
  }
  
  /**
   * Get all the models and fields that refer to this model
   * Used by delete routines
   */
  function reverseFields() {
    $result = array();
    foreach(self::$_meta_cache as $model=>$meta) {
      if($model != $this->_klass && isset($meta->reverse[$this->_klass])) {
        foreach($meta->reverse[$this->_klass] as $accessor=>$spec) {
          $spec['accessor'] = $accessor;
          $result[] = $spec;
        }
      }
    }
    return $result;
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
  
  function __toString() {
    return "<Dormio_Meta:{$this->_klass}>";
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Meta_Exception extends Dormio_Exception {}
?>