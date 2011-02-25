<?
/**
* Base model class
* All models should subclass this
*/
class Dormio_Model {
  static $_cache_ref = null;
  private $_data = array();
  public $_updated = array();
  private $_related = array();
  private $_db, $_stmt, $_objects, $_dialect;
  public $_meta, $_id=false; // need to be accessed by the manager
  
  // overridable meta fields for sub classes
  static $meta = array();
  
  static $logger = null;

  function __construct(PDO $db, $dialect=null) {
    $this->_db = $db;
    $this->_meta = Dormio_Meta::get(get_class($this));
    //if(!$dialect) throw new Exception();
    $this->_dialect = ($dialect) ? $dialect : Dormio_Dialect::factory($db->getAttribute(PDO::ATTR_DRIVER_NAME));
  }
  
  /**
  * Populate the data
  */
  function _hydrate($data, $prefixed=false) {
    if($prefixed) {
      $this->_data = array_merge($this->_data, $data);
    } else {
      foreach($data as $key=>$value) $this->_setData($key, $value);
    }
    $pk = $this->_dataIndex($this->_meta->pk);
    //if(!isset($this->_data[$pk])) throw new Dormio_Model_Exception('No primary key in hydration data');
    $this->_id = (isset($this->_data[$pk])) ? (int)$this->_data[$pk] : false;
  }
  
  function _rehydrate() {
    if(!$this->_id) throw new Dormio_Model_Exception('No primary key set');
    isset(self::$logger) && self::$logger->log("Rehydrating {$this->_meta->_klass}({$this->_id})");
    $stmt = $this->_hydrateStmt();
    $stmt->execute(array($this->_id));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if($data) {
      $this->_hydrate($data, true);
    } else {
      //var_dump($stmt);
      throw new Dormio_Model_Exception('No result found for primary key ' . $this->_id);
    }
  }
  
  /**
  * Cache the hydration stmts to improve pk lookups
  */
  function _hydrateStmt() {
    // slightly cheekily we store proceedures on the actual pdo object
    // tried a static cache but causes problems if you change db handle
    if(!isset($this->_db->_stmt_cache)) $this->_db->_stmt_cache = array();
    if(!isset($this->_db->_stmt_cache[$this->_meta->_klass])) {
      $fields = implode(', ', $this->_meta->prefixedSqlFields());
      $sql = "SELECT {$fields} FROM {{$this->_meta->table}} WHERE {{$this->_meta->table}}.{{$this->_meta->pk}} = ?";
      $this->_db->_stmt_cache[$this->_meta->_klass] = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    }
    return $this->_db->_stmt_cache[$this->_meta->_klass];
  }
  
  /**
  * Get the key for the data array
  * Result is dependant on whether the data is qualified
  */
  function _dataIndex($field) {
    //return ($this->_qualified) ? "{$this->_meta->table}_{$field}" : $field;
    return "{$this->_meta->table}_{$field}";
  }
  
  /**
  * Empty the current object
  */
  function clear() {
    $this->_data = $this->_updated = array();
    $this->_id = false;
  }
  
  /**
  * Get the value of the primary key for the current record
  * Returns false if unbound
  */
  function ident() {
    return $this->_id;
  }
  
  /**
  * Get the manager for this model
  */
  function objects() {
    if(!isset($this->_objects)) $this->_objects = new Dormio_Manager($this->_meta, $this->_db);
    return $this->_objects;
  }
  
  /**
  * Accessor for all data
  * All internal functions should use this as it takes care of qualifying the
  * indexes and rehydrating the object if required
  */
  function _getData($column) {
    $key = $this->_dataIndex($column);
    if(!isset($this->_data[$key])) $this->_rehydrate(); // first try rehydrating
    return $this->_data[$key];
  }
  
  function _setData($column, $value) {
    $this->_data[$this->_dataIndex($column)] = $value;
  }
  
  /**
  * Does the heavy lifting of returning values, related objects and managers
  */
  function __get($name) {
    
    // need to do this first so any aggregate results appear
    // might speed up everyday access as well to bypass resolve/check proceedure
    //$key = $this->_dataIndex($name);
    //if(isset($this->_data[$key])) return $this->_data[$key];
    
    
    $this->_meta->resolve($name, $spec, $meta);
    isset($spec['sql_column']) || $spec['sql_column'] = $this->_meta->pk;
    
    switch($spec['type']) {
      case 'foreignkey':
      case 'onetoone':
      case 'onetoone_rev':
        // relations that return a single object
        if(!isset($this->_related[$name])) $this->_related[$name] = new $spec['model']($this->_db, $this->_dialect);
   
        $id = $this->_getData($spec['sql_column']);
        isset(self::$logger) && self::$logger->log("Preparing {$spec['model']}({$id})");
        if($this->_related[$name]->ident()!=$id) {
          // We pass the current data to the related object - allows for eager loading
          // DB is not hit at all in this operation
          $this->_related[$name]->load($id); // clears the stale data
          $this->_related[$name]->_hydrate($this->_data, true);
        }
        return $this->_related[$name];
        
      case 'manytomany':
      case 'foreignkey_rev':
        // relations that return a manager
        // due to the parameters being referenced we dont need to do anything if these are cached
        if(isset($this->_related[$name])) return $this->_related[$name];
      
        //print "{$this->_klass}->{$name}\n";
        if($spec['type'] == 'manytomany') {
          $target = Dormio_Meta::get($spec['through']);
          $through = $spec['through'];
        } else {
          // reverse foreign - manually add the where clause
          $target = Dormio_Meta::get($spec['model']);
          $through = null;
        }
        $field = $target->accessorFor($this);
        $manager = new Dormio_Manager_Related($spec['model'], $this->_db, $this->_dialect, $this, $field, $through);
        $this->_related[$name] = $manager;
        return $this->_related[$name];
      default:
        // everything else is concidered a field on the table
        $column = $spec['sql_column'];
        if(isset($this->_updated[$column])) return $this->_updated[$column];
        return $this->_getData($column);
    }
  }
  
  function __set($name, $value) {
    if($name=='pk') throw new Dormio_Model_Exception("Can't update primary key");
    $spec = $this->_meta->column($name);
    if(is_a($value, 'Dormio_Model')) { // use the primary key of objects
      $this->_related[$name] = $value;
      $value = $value->ident();
    }
    $this->_updated[$spec['sql_column']] = $value; // key is un-qualified
  }
   
  /**
  * Load a record by id
  * This actually does very little except set the id - it wont be populated until a request is made
  */
  function load($id) {
    $this->clear();
    $this->_id = $id;
    $this->_setData($this->_meta->pk, $id);
    //$this->_qualified = true;
  }
  
  function save() {
    if(count($this->_updated)==0) return;
    return ($this->ident()===false) ? $this->insert() : $this->update();
  }
  
  function insert() {
    $fields = array_keys($this->_updated);
    foreach($fields as &$field) $field = '{' . $field . '}';
    $fields = implode(', ', $fields);
    $values = implode(', ', array_fill(0, count($this->_updated), '?'));
    $sql = "INSERT INTO {{$this->_meta->table}} ({$fields}) VALUES ({$values})";
    $params = array_values($this->_updated);
    $stmt = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    if($stmt->execute($params) !=1) throw new Dormio_Model_Exception('Insert failed');
    //$this->_insert = false;
    $this->_updated[$this->_meta->pk] = $this->_id = $this->_db->lastInsertId();
    $this->_merge();
  }
  
  function update() {
    $params = array_values($this->_updated);
    foreach(array_keys($this->_updated) as $key) $pairs[] = "{{$key}}=?";
    $params[] = $this->ident();
    $pairs = implode(', ', $pairs);
    $sql = "UPDATE {{$this->_meta->table}} SET {$pairs} WHERE {{$this->_meta->pk}} = ?";
    $stmt = $this->_db->prepare($this->_dialect->quoteIdentifiers($sql));
    if($stmt->execute($params) !=1) throw new Dormio_Model_Exception('Insert failed');
    $this->_merge();
  }
  
  function _merge() {
    $this->_hydrate($this->_updated, false);
    //$this->_qualified = true;
    $this->_updated = array();
  }
  
  function delete() {
    if($this->_stmt) $this->_stmt->closeCursor(); // can prevent transaction committing
    $objects = $this->objects();
    $sql = $objects->deleteById($this->ident());
    return $objects->batchExecute($sql);
  }
  
  function display() {
    return "<{$this->_meta->_klass}:{$this->ident()}>";
  }
  
  function __toString() {
    try {
      return $this->display();
    } catch(Exception $e) {
      return "<{$this->_meta->_klass}:{$e->getMessage()}>";
    }
  }
}

/** Model exception */
class Dormio_Model_Exception extends Dormio_Exception {}
?>
