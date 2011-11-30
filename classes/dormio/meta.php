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

  /**
   * Cache the meta arrays so we only parse them once
   * @var array 
   */
  private static $_meta_cache = array();

  private static $_model_register = array();
  
  /**
   * Set a custom config loader
   * @var callable
   */
  static $config_loader = false;

  /**
   * Name of the model this meta applies to
   * @var string
   */
  public $model;

  /**
   * Local fields
   * @var array
   */
  public $fields;

  /**
   * Reverse fields for easy lookup
   * @var array
   */
  public $reverse;

  /**
   * Indexes that should be added for good performance
   * @var array
   */
  public $indexes;

  /**
   * Name of table in database
   * @var string
   */
  public $table;

  /**
   * Name of primary key for this table
   * @var string
   */
  public $pk;

  /**
   * Version tracking
   * @var int
   */
  public $version;

  /**
   * Verbose description of this model
   * @var string
   */
  public $verbose;

  /**
   * Singleton constructor
   * Normalises the meta
   */
  private function __construct($klass, $spec) {
    $this->model = $klass;
    $this->_spec = self::_normalise($klass, $spec);

    // set up the public properties
    $this->fields = $this->_spec['fields'];
    $this->reverse = $this->_spec['reverse'];
    $this->indexes = $this->_spec['indexes'];
    $this->table = $this->_spec['table'];
    $this->pk = $this->_spec['fields']['pk']['db_column'];
    $this->version = $this->_spec['version'];
    $this->verbose = $this->_spec['verbose'];
  }

  /**
   * Singleton pattern so each model only gets processed once
   */
  public static function get($klass) {
    $klass = strtolower($klass);
    if (!isset(self::$_meta_cache[$klass])) {
      if (!class_exists($klass))
        throw new Dormio_Meta_Exception('No such class: ' . $klass);
      self::$_meta_cache[$klass] = new Dormio_Meta($klass, call_user_func(array($klass, '_meta'), $klass));
    }
    return self::$_meta_cache[$klass];
  }
  
  /**
   * Ensure that the we know about all possible related models
   */
  public static function register() {
    $models = func_get_args();
    self::$_model_register = array_unique(array_merge(self::$_model_register, $models));
  }

  /**
   * Update the fields in place
   * Fills in defaults and generates reverse defininitions and intermediate models as required
   */
  static function _normalise($model, $meta) {
    if (!isset($meta['fields']))
      throw new Dormio_Meta_Exception("Missing required 'fields' on meta");
    // check the basic array structure
    $defaults = array(
        'table' => $model,
        'version' => 1,
        'reverse' => array(),
        'indexes' => array(),
        'verbose' => self::title($model),
    );
    $meta = array_merge($defaults, $meta);

    // default pk - can be overriden by the fields
    $columns['pk'] = array('type' => 'ident', 'db_column' => $model . "_id", 'is_field' => true, 'verbose' => 'ID');


    foreach ($meta['fields'] as $key => $spec) {
      if (!isset($spec['type']))
        throw new Dormio_Meta_Exception("'type' required on field '{$key}'");

      // we only really care about normalizing related fields at this stage
      if (isset($spec['model'])) {
        $spec['model'] = strtolower($spec['model']); // all meta references are lower case
        // set up the required fields based on the type
        switch ($spec['type']) {
          case 'foreignkey':
          case 'onetoone':
            $defaults = array(
                'verbose' => self::title($key),
                'db_column' => strtolower($key) . "_id",
                'null_ok' => false,
                'local_field' => $key,
                'remote_field' => 'pk',
                'on_delete' => ($spec['type'] == 'foreignkey') ? 'cascade' : 'blank',
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
            if ($spec['through']) {
              // load the spec
              Dormio_Meta::get($spec['through']);
            } else {
              $through = self::_generateIntermediate($model, $spec);
              $spec['through'] = $through->model;
              $spec['map_local_field'] = 'l_' . $model;
              $spec['map_remote_field'] = 'r_' . $spec['model'];
            }
            $reverse = array('type' => 'manytomany', 'through' => $spec['through'], 'model' => $model, 'map_local_field' => $spec['map_remote_field'], 'map_remote_field' => $spec['map_local_field']);
            break;

          case 'reverse':
            $reverse = null; // dont generate a reverse spec
            isset($spec['accessor']) || $spec['accessor'] = null; // will call accessorFor() later
            self::register($spec['model']); // ensure the model is loaded later if required
            break;

          default:
            throw new Dormio_Meta_Exception('Unknown relation type: ' . $spec['type']);
        }
        // store a reverse spec so we don't need to traverse the columns
        if (isset($reverse)) {
          $meta['reverse'][$spec['model']][$key] = $reverse;
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
   * Get the first field name that maps to a particular model
   * @param string|Dormio_Model $model Model name or instance
   * @param string $accessor If given, will validate that accessor
   */
  function getAccessorFor($model, $accessor=null) {
    if (is_object($model))
      $model = $model->_meta->model;
    if (!isset($this->reverse[$model]))
      throw new Dormio_Meta_Exception("No reverse relation for '{$model}' on '{$this->model}'");
    if ($accessor) {
      if (!isset($this->reverse[$model][$accessor]))
        throw new Dormio_Meta_Exception("No reverse accessor '{$model}.{$accessor}' on '{$this->model}'");
      return $accessor;
    } else {
      if(count($this->reverse[$model])>1) throw new Dormio_Meta_Exception("More that one reverse relation found for '{$model}' on '{$this->model}'. You need to add an accessor.");
      reset($this->reverse[$model]);
      return key($this->reverse[$model]);
    }
  }

  /**
   * Get a reverse specification for a given model
   * @param string $model Model name
   * @param string $accessor <optional> Return a specific spec instead of the first defined
   * @return array
   */
  function getReverseSpec($model, $accessor=null) {
    $accessor = $this->getAccessorFor($model, $accessor);
    return $this->reverse[$model][$accessor];
  }

  /**
   * Resolve a field name to a usable spec and meta
   * All the black magic happens here with reverse relations etc...
   * @param  $name   string  The field name
   */
  function getSpec($name) {

    // dereference model_set names
    if (substr($name, -4) == '_set') {
      $name = substr($name, 0, -4);
      $spec = array('type' => 'reverse', 'model' => $name, 'accessor' => null);
    } else {
      if (!isset($this->fields[$name]))
        throw new Dormio_Meta_Exception("No field '{$name} on '{$this->model}'");
      $spec = $this->fields[$name];
    }

    if ($spec['type'] == 'reverse') {
      $meta = Dormio_Meta::get($spec['model']);
      $spec = $meta->getReverseSpec($this->model, $spec['accessor']);
    }

    return $spec;
  }

  /**
   * Get the database column for a field
   * @param string $name Field name
   * @return string DB Column
   */
  function getColumn($name) {
    $spec = $this->getSpec($name);
    return $spec['db_column'];
  }

  /**
   * Ensure we have parsed all model specs we know about
   */
  function parseAllModels() {
    foreach(self::$_model_register as $model) Dormio_Meta::get($model);
    self::$_model_register = array();
  }
  
  /**
   * Get all the models and fields that refer to this model
   * Used by delete routines
   */
  function reverseFields() {
    $this->parseAllModels(); // need to parse everything before generating
    $result = array();
    foreach (self::$_meta_cache as $model => $meta) {
      if ($model != $this->model && isset($meta->reverse[$this->model])) {
        foreach ($meta->reverse[$this->model] as $accessor => $spec) {
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
    $klass = $this->model;
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
    if (self::$config_loader)
      $config = array_merge($config, call_user_func(self::$config_loader, $section));
    return $config;
  }

  function __toString() {
    return "<Dormio_Meta:{$this->model}>";
  }

}

/**
 * @package dormio
 * @subpackage exception
 */
class Dormio_Meta_Exception extends Dormio_Exception {
  
}

?>