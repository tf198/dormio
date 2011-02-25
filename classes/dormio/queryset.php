<?
/**
* Class to programatically generate SQL based on table meta information
* Very similar to django queryset but several methods have slightly different
* args due to lack of **kwargs
* Aims to be as lightweight as possible as it gets cloned on each action but
* while retaining a comprehensive field selector interface
*/
class Dormio_Queryset {
  public $query = array(
    'select' => array(), // automatically added fields
    'from' => null, // string - the base table name
    'join' => null, // array - join criteria
    'where' => null, // array of items that are AND'ed together
    'order_by' => null, // array
    'limit' => null, // int
    'offset' => null, // int
  );

  function __construct($meta, $dialect='generic') {
    $this->_meta = is_object($meta) ? $meta : Dormio_Meta::get($meta);
    $this->dialect = is_object($dialect) ? $dialect : Dormio_Dialect::factory($dialect);
    
    // add the base table and its primary key
    $this->query['from'] = "{{$this->_meta->table}}";
    $this->_addFields($this->_meta);
    
    $this->_joined = array();
    $this->params = array();
  }
  
  function _selectIdent() {
    $this->query['select'] = array("{{$this->_meta->table}}.{{$this->_meta->pk}}");
  }
  
  function filter($key, $op, $value) {
    $o = clone $this;
    $f = $o->_resolveField($key);
    $o->query['where'][] = "{$f} {$op} ?";
    if(is_a($value, 'Dormio_Model')) $value = $value->ident();
    $o->params[] = &$value;
    return $o;
  }
  
  function field($path, $alias=null) {
    $o = clone $this;
    $p = $o->_resolvePath($path);
    if(!$alias) $alias = "{$p[0]->table}_{$p[1]}";
    $o->query['select'][] = "{{$p[0]->table}}.{{$p[1]}} AS {{$o->_meta->table}_{$alias}}";
    return $o;
  }
  
  function where($clause, $params) {
    $o = clone $this;
    $o->query['where'][] = $o->_resolveString($clause);
    $o->params = array_merge($o->params, $params);
    return $o;
  }
  
  function with() {
    $o = clone $this;
    foreach(func_get_args() as $table) {
      $spec = $o->_resolve(explode('__', $table));
      $o->_addFields($spec);
    }
    return $o;
  }
  
  function limit($limit, $offset=false) {
    $o = clone $this;
    $o->query['limit'] = $limit;
    if($offset) $o->query['offset'] = $offset;
    return $o;
  }
  
  function orderBy() {
    $o = clone $this;
    foreach(func_get_args() as $path) $o->query['order_by'][] = $o->_resolveField($path);
    return $o;
  }
  
  /**
  * Resolves path to the format "{table}.{column} AS {table_column}"
  */
  function _resolvePrefixed($item) {
    $p = $this->_resolvePath($item);
    return "{{$p[0]->table}}.{{$p[1]}} AS {{$p[0]->table}_{$p[1]}}";
  }
  
  /**
  * Resolves bracketed terms in a string
  * eg "{comment__blog__title} = ?" becomes "{blog}.{title} = ?"
  * Joins will be added automatically as required
  */
  function _resolveString($str) {
    return preg_replace_callback('/\{([a-z_]+)\}/', array($this, '_resolveStringCallback'), $str);
  }
  function _resolveStringCallback($matches) {
    return $this->_resolveField($matches[1]);
  }
  
  /**
  * Resolves path to the format "{table}.{column}"
  */
  function _resolveField($path) {
    $p = $this->_resolvePath($path);
    return "{{$p[0]->table}}.{{$p[1]}}";
  }
  
  function _resolveLocal($fields) {
    $result = array();
    foreach($fields as $field) {
      if(!isset($this->_meta->columns[$field])) throw new Dormio_Queryset_Exception('No such local field: ' . $field);
      $result[] = "{{$this->_meta->columns[$field]['sql_column']}}";
    }
    return $result;
  }
  
  /**
  * Resolves paths to parent meta and field
  * @return array($parent_meta, $field);
  */
  function _resolvePath($path, $strip_pk=true) {
    $parts = explode('__', $path);
    $field = array_pop($parts);
    if($strip_pk && $field=='pk' && $parts) $field = array_pop($parts);
    $spec = $this->_resolve($parts);
    if(!isset($spec->columns[$field])) throw new Dormio_Queryset_Exception('No such field: ' . $field);
    return array($spec, $spec->columns[$field]['sql_column']);
  }
  
  /**
  * Resolves accessors and returns the top level meta object
  * @param  $parts  array   An array of field accessors that can be chained
  * @param  $type   string  The type of join to perform [LEFT]
  * @return object          The top level meta object
  */
  function _resolve($parts, $type='INNER') {
    $spec = $this->_meta;
    for($i=0,$c=count($parts); $i<$c; $i++) $spec = $this->_addJoin($spec, $parts[$i], $type);
    return $spec;
  }
  
  function _addFields($meta) {
    $schema = $meta->schema();
    foreach($schema['columns'] as $key=>$spec) {
      $this->query['select'][] = "{{$meta->table}}.{{$spec['sql_column']}} AS {{$meta->table}_{$spec['sql_column']}}";
    }
  }
  
  function _addJoin($left, $field, $type) {
    // get our spec and meta
    $left->resolve($field, $spec, $meta);
    
    // if manytomany redispatch in two queries
    if(isset($spec['through'])) {
      $mid = $this->_addJoin($left, strtolower($spec['through']) . "_set", "INNER");
      // need to traverse the mid columns to find the model as the name may be different
      return $this->_addJoin($mid, $mid->accessorFor($spec['model']), "INNER");
    }
    
    $right = Dormio_Meta::get($spec['model']);
    // fill in the values that weren't known at parse time
    $left_field = ($spec['sql_column']) ? $spec['sql_column'] : $left->pk;
    $right_field = ($spec['to_field']) ? $spec['to_field'] : $right->pk;
    
    if(array_search($right->table, $this->_joined) === false) {
      $this->query['join'][] = "{$type} JOIN {{$right->table}} ON {{$left->table}}.{{$left_field}}={{$right->table}}.{{$right_field}}";
      //$this->query['join'][]= "{$type} JOIN {{$right->table}}";
      //$this->query['where'][] = "{{$left->table}}.{{$left_field}}={{$right->table}}.{{$right_field}}";
      $this->_joined[] = $right->table;
    }
    return $right;
  }
  
  function update($params) {
    $o = clone $this;
    $o->_selectIdent();
    $update_params = array_merge(array_values($params), $o->params);
    $update_fields = $o->_resolveLocal(array_keys($params));
    return array($this->dialect->update($o->query, $update_fields), $update_params);
  }
  
  function insert($params) {
    // no need to clone as non-destructive
    $update_fields = $this->_resolveLocal(array_keys($params));
    return array($this->dialect->insert($this->query, $update_fields), array_values($params));
  }
  
  function _deleteSpec() {
    $result = array();
    foreach($this->_meta->columns as $key=>$spec) {
      $action = false;
      if($spec['type']=='reverse') {
        $this->_meta->resolve($key, $spec, $meta);
        if($spec['type']=='foreignkey_rev' || $spec['type']=='onetoone_rev') {
          $result[] = array($meta, $spec['on_delete']);
        }
      }
      if(substr($key,0,2)!='__' && $spec['type']=='manytomany') {
        $result[] = array($spec['through'], 'cascade');
      }
    }
    return $result;
  }
  
  function delete($resolved=array()) {
    $sql = array();
    foreach($this->_deleteSpec() as $parts) {
      $child = new Dormio_Queryset($parts[0]);
      //$child->query['select'] = array("{{$child->_meta->table}}.{{$child->_meta->pk}}");
      $child->_selectIdent();
      $child->query['where'] = $this->query['where'];
      $child->query['join'] = $this->query['join'];
      $child->params = $this->params;
      $child->_joined = $this->_joined;
        
      $r = $resolved;
      array_unshift($r, $child->_meta->accessorFor($this));
      $child->_resolve($r);
        
      $sql = array_merge($sql, $child->delete($r));
    }
    
    //print $this->dialect->delete($this->query) . "\n\n";
    $sql[] = array($this->dialect->delete($this->query), $this->params);
    
    return $sql;
  }
  
  function deleteById($id, $query='pk', $resolved=array()) {
    $sql = array();
    foreach($this->_deleteSpec() as $parts) {
      $child = new Dormio_Queryset($parts[0]);
      //$child->query['select'] = array("{{$child->_meta->table}}.{{$child->_meta->pk}}");
      $child->_selectIdent();
      $r = $resolved;
      array_unshift($r, $child->_meta->accessorFor($this));
      $q = implode('__', $r);
      if($parts[1]=='cascade') {
        $sql = array_merge($sql, $child->deleteById($id, $q, $r));
      } elseif ($parts[1]=='blank') {
        $sql[] = $child->filter($q, '=', $id)->update(array($q => null));
      } else {
        throw new Dormio_Queryset_Exception('Unknown ON DELETE action: ' . $parts[1]);
      }
    }
    
    $o = $this->filter($query, '=', $id);
    $sql[] = array($this->dialect->delete($o->query), $o->params);
    
    return $sql;
  }
  
  function select() {
    return array($this->dialect->select($this->query), $this->params);
  }
}

class Dormio_Queryset_Exception extends Dormio_Exception {}
?>