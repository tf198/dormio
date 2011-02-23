<?
/**
* Factory for language specific query generation
* As lightweight as possible  - just takes care of the special cases
* Cached as likely to be many instances
*/
class Dormio_Dialect {
  static $_cache = array();

  static function factory($lang) {
    switch($lang) {
      case 'sqlsrv':
      case 'dblib':
      case 'odbc':
        $lang = 'mssql';
        break;
      case 'sqlite':
        $lang = 'generic';
        break;
		}
    if(!isset(self::$_cache[$lang])) {
      $klass = "Dormio_Dialect_{$lang}";
      self::$_cache[$lang] = new $klass;
    }
    return self::$_cache[$lang];
    
  }
}

class Dormio_Dialect_Exception extends Exception {}

class Dormio_Dialect_Generic {

  /**
  * Takes an array and turns it into a statement using simple logic
  * If a field has a value then "$FIELD $value" is appended to the statement
  * All value arrays are concatenated using commas, except 'where' which uses ' AND '
  */
  function compile($spec) {
    if(isset($spec['where'])) $spec['where'] = array(implode(' AND ', $spec['where']));
    foreach($spec as $key=>$value) {
      if($value) $result[] = str_replace("_", " ", strtoupper($key)) . " " . ((is_array($value)) ? implode(', ', $value) : $value);
    }
    return implode(' ', $result);
  }
  
  function select($spec) {
    $spec['select'] = array_unique($spec['select']);
    if(isset($spec['join'])) {
      $spec['from'] = $spec['from'] . " " . implode(' ',$spec['join']);
      $spec['join'] = null;
    }
    return $this->quoteIdentifiers($this->compile($spec));
  }
  
  function update($spec, $fields) { 
    foreach($fields as $field) $set[] = "{$field}=?";
    $set = implode(', ', $set);
    $base = "UPDATE {$spec['from']} SET {$set} ";
    if(isset($spec['join'])) {
      $spec['where'] = array("{$spec['select'][0]} IN ({$this->select($spec)})");
      $spec['join'] = null;
    }
    $spec['select'] = $spec['from'] = $spec['order_by'] = $spec['offset'] = null; // irrelevant fields
    return $this->quoteIdentifiers($base . $this->compile($spec));
  }
  
  function insert($spec, $fields) {
    $values = implode(', ', array_fill(0, count($fields), '?'));
    $fields = implode(', ', $fields);
    $sql = "INSERT INTO {$spec['from']} ({$fields}) VALUES ({$values})";
    return $this->quoteIdentifiers($sql);
  }
  
  function delete($spec) {
    if(isset($spec['join'])){
      $spec['where'] = array("{$spec['select'][0]} IN ({$this->select($spec)})");
      $spec['join'] = null;
    } 
    $spec['select'] = $spec['order_by'] = $spec['offset'] = null; // irrelevant fields
    return $this->quoteIdentifiers("DELETE " . $this->compile($spec));
  }
  
  function quoteFields($fields) {
    foreach($fields as $field) $result[] = '{' . $field . '}';
    return $result;
  }
  
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '""');
  }
}

class Dormio_Dialect_MySQL extends Dormio_Dialect_Generic {
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '``');
  }
}

class Dormio_Dialect_MSSQL extends Dormio_Dialect_Generic {
  function select($spec) {
    if(isset($spec['limit'])) $spec['select'][0] = "TOP {$spec['limit']} {$spec['select'][0]}";
    $spec['limit'] = null;
    if(isset($spec['offset'])) throw new Dormio_Dialect_Exception('Offset not supported by MSSQL');
    return parent::select($spec);
  }
  
  function quoteIdentifiers($sql) {
    return strtr($sql, '{}', '[]');
  }
}