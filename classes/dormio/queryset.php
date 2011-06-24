<?
/**
* Queryset
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
* Class to programatically generate SQL based on table meta information.
*
* Most methods accept either a field key (as defined in your $meta) or
* a descriptor which traces relations across the tables.
* e.g. 'blog__title' on a Comment queryset refers to the title field on the associated blog model
*
* Reverse relations can either be accessed by field, for example blog has a reverse field defined for its
* comments, or by adding _set to the model name so the same can be achieved by calling 'comment_set'.
* This also works for onetoone relations e.g. 'author__profile_set__fav_colour' on a Blog queryset will traverse
* the intermediate tables to get to the fav_colour field on the Profile model.
*
* Django users will notice the blatent plagerism here, the main difference being the filter() method, where Django
* would use qs->filter(age__gt=16) Dormio uses a separate operator $qs->filter('age', '>', 16);
*
* @example models.php The models refered to in these examples
* @example usage.php Example usage
* @package dormio
*/
class Dormio_Queryset {
  /**
  * @ignore
  */
  public $query = array(
    'select' => array(), // automatically added fields
    'modifiers' => null,
    'from' => null, // string - the base table name
    'join' => null, // array - join criteria
    'where' => null, // array of items that are AND'ed together
    'order_by' => null, // array
    'limit' => null, // int
    'offset' => null, // int
  );

  /**
  * Create a new Queryset
  * @param  string|Dormio_Meta  $meta   A meta object or the name of a model
  * @param  string|Dormio_Dialect $dialect  A dialect object or the name of one e.g. sqlite
  */
  function __construct($meta, $dialect='generic') {
    $this->_meta = is_object($meta) ? $meta : Dormio_Meta::get($meta);
    $this->dialect = is_object($dialect) ? $dialect : Dormio_Dialect::factory($dialect);
    
    // add the base table and its primary key
    $this->query['from'] = "{{$this->_meta->table}}";
    $this->_addFields($this->_meta);
    
    $this->_joined = array();
    $this->params = array();
  }
  
  /**
  * Add the primary key field to the query
  */
  function _selectIdent() {
    $this->query['select'] = array("{{$this->_meta->table}}.{{$this->_meta->pk}}");
  }
  
  /**
  * Filter based on an operator.
  * Operators can be '=', '<', '>', '>=', '<=', 'IN', 'LIKE'.
  * Multiple filters are AND'd together.
  * <code>
  * $qs->filter('age', '>=', '18);
  * $qs->filter('author__profile_set__fav_colour', 'IN', array('red', 'green', 'blue'));
  * </code>
  * @param  string  $key    Field descriptor
  * @param  string  $op     Comparison operator
  * @param  mixed   $value  variable to compare to 
  * @return Dormio_Queryset Cloned copy of the queryset
  * @todo validate IN and LIKE behaviour
  */
  function filter($key, $op, $value) {
    $o = clone $this;
    $f = $o->_resolveField($key);
    $v = '?';
    if($op == 'IN') {
      if(!is_array($value)) throw new Dormio_Queryset_Exception('Need array for IN operator');
      $v = '(' . implode(', ', array_fill(0, count($value), '?')) . ')';
    }
    $o->query['where'][] = "{$f} {$op} {$v}";
    if(is_a($value, 'Dormio_Model')) $value = $value->ident();
    if($op == 'IN') {
      $o->params = array_merge($o->params, $value);
    } else {
      $o->params[] = &$value;
    }
    return $o;
  }
  
  function field($path, $alias=null) {
    // this needs to left join
    $o = clone $this;
    $p = $o->_resolvePath($path, 'LEFT');
    if(!$alias) $alias = str_replace('__', '_', $path);
    $o->query['select'][] = "{{$p[0]->table}}.{{$p[1]}} AS {{$o->_meta->table}_{$alias}}";
    return $o;
  }
  
  /**
  * Makes the query DISTINCT.
  * @return Dormio_Queryset Cloned copy of the queryset
  */
  function distinct() {
    $o = clone $this;
    $o->query['modifiers'][] = "DISTINCT";
    return $o;
  }
  
  /**
  * Add an arbetary where clause.
  * will be AND'd together with previous clauses.
  * Field names in {} will be expanded correctly, including across joins
  * <code>
  * $qs->where('{author__username}=? OR {title}=?', array('bob', 'Test blog'));
  * </code>
  * @param  string  $clause   The WHERE clause to be added
  * @param  array   $params   Substitution parameters for query
  * @return Dormio_Queryset Cloned copy of the queryset
  */
  function where($clause, $params) {
    $o = clone $this;
    $o->query['where'][] = $o->_resolveString($clause);
    $o->params = array_merge($o->params, $params);
    return $o;
  }
  
  /**
  * Do an eager join with another table.
  * Performs LEFT join
  * @param  string  $table   One or more tables to join
  * @return Dormio_Queryset Cloned copy of the queryset
  */
  function with() {
    $o = clone $this;
    foreach(func_get_args() as $table) {
      $spec = $o->_resolve(explode('__', $table), 'LEFT');
      $o->_addFields($spec);
    }
    return $o;
  }
  
  /**
  * Limit the results.
  * @param  int   $limit  Records to return
  * @param  int   $offset Optional number of records to skip
  * @return Dormio_Queryset Cloned copy of the queryset
  */
  function limit($limit, $offset=false) {
    $o = clone $this;
    if($o->query['limit']) return $o;
    $o->query['limit'] = $limit;
    if($offset) $o->query['offset'] = $offset;
    return $o;
  }
  
  /**
  * Order the results.
  * You can prefix a field with - for descending
  * <code> $qs->orderBy('-date', 'name'); </code>
  * @param  string  $field  One or more fields to order by
  * @return Dormio_Queryset Cloned copy of the queryset
  */
  function orderBy() {
    $o = clone $this;
    foreach(func_get_args() as $path) {
      if(substr($path, 0, 1) == '-') {
        $order = " DESC";
        $path = substr($path, 1);
      } else {
        $order = "";
      }
      $o->query['order_by'][] = $o->_resolveField($path) . $order;
    }
    return $o;
  }
  
  /**
  * Resolves path to the format "{table}.{column} AS {table_column}".
  * @access private
  */
  function _resolvePrefixed($item, $type=null) {
    $p = $this->_resolvePath($item, $type);
    return "{{$p[0]->table}}.{{$p[1]}} AS {{$p[0]->table}_{$p[1]}}";
  }
  
  /**
  * Resolves bracketed terms in a string.
  * eg "{comment__blog__title} = ?" becomes "{blog}.{title} = ?"
  * Joins will be added automatically as required
  * @access private
  */
  function _resolveString($str, $local=false) {
    $callback = ($local) ? '_resolveStringLocalCallback' : '_resolveStringCallback';
    return preg_replace_callback('/%([a-z_]+)%/', array($this, $callback), $str);
  }
  /**
  * @ignore
  */
  function _resolveStringCallback($matches) {
    if($matches[1]=='table') return "{" . $this->_meta->table . "}";
    return $this->_resolveField($matches[1]);
  }
  /*
  * @ignore
  */
  function _resolveStringLocalCallback($matches) {
    return $this->_meta->columns[$matches[1]]['sql_column'];
  }

  /**
  * Resolves path to the format "{table}.{column}".
  * @access private
  */
  function _resolveField($path, $type=null) {
    $p = $this->_resolvePath($path, $type);
    return "{{$p[0]->table}}.{{$p[1]}}";
  }
  
  /**
  * Resolves field names to their sql columns.
  * DOES NOT FOLLOW RELATIONS
  * @access private
  */
  function _resolveLocal($fields, $type=null) {
    $result = array();
    foreach($fields as $field) {
      if(!isset($this->_meta->columns[$field])) throw new Dormio_Queryset_Exception('No such local field: ' . $field);
      $result[] = "{{$this->_meta->columns[$field]['sql_column']}}";
    }
    return $result;
  }
  
  /**
  * Resolves paths to parent meta and field
  * @return array array($parent_meta, $field);
  * @access private
  */
  function _resolvePath($path, $type=null, $strip_pk=true) {
    $parts = explode('__', $path);
    $field = array_pop($parts);
    if($strip_pk && $field=='pk' && $parts) $field = array_pop($parts);
    $spec = $this->_resolve($parts, $type);
    if(!isset($spec->columns[$field])) throw new Dormio_Queryset_Exception('No such field: ' . $field);
    return array($spec, $spec->columns[$field]['sql_column']);
  }
  
  /**
  * Resolves accessors and returns the top level meta object
  * @param  $parts  array   An array of field accessors that can be chained
  * @param  $type   string  The type of join to perform [LEFT]
  * @return object          The top level meta object
  * @access private
  */
  function _resolve($parts, $type=null) {
    $spec = $this->_meta;
    for($i=0,$c=count($parts); $i<$c; $i++) $spec = $this->_addJoin($spec, $parts[$i], $type);
    return $spec;
  }
  
  /**
  * Add all the fields for a table
  * @param  Dormio_Meta $meta The meta for the table to add
  * @access private
  */
  function _addFields($meta) {
    $schema = $meta->schema();
    foreach($schema['columns'] as $key=>$spec) {
      $this->query['select'][] = "{{$meta->table}}.{{$spec['sql_column']}} AS {{$meta->table}_{$spec['sql_column']}}";
    }
  }
  
  /**
  * Takes care of joining tables together by field name.
  * Defaults to INNER joins unless specified
  * @param  Dormio_Meta   $left   The lefthand table
  * @param  string        $field  The field containing the relation
  * @param  string  $type         The type of join to perform
  * @return Dormio_Meta   The righthand table which was joined to
  * @access private
  */
  function _addJoin($left, $field, $type=null) {
    if(!$type) $type='INNER';
    // get our spec and meta
    $left->resolve($field, $spec, $meta);
    
    // if manytomany redispatch in two queries
    if(isset($spec['through'])) {
      if($type!='INNER') trigger_error("Trying to {$type} JOIN onto {$spec['model']} - may have unexpected results", E_USER_WARNING);
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
  
  /**
  * Creates an UPDATE statement based on the current query
  * @param  array $params   Assoc array of values to set
  * @return array           array(sql, params)
  */
  function update($params, $custom_fields=array(), $custom_params=array()) {
    $o = clone $this;
    $o->_selectIdent();
    $update_params = array_merge(array_values($params), $custom_params, $o->params);
    $update_fields = $o->_resolveLocal(array_keys($params));
    foreach($custom_fields as &$field) $field = $this->_resolveString($field, true);
    return array($this->dialect->update($o->query, $update_fields, $custom_fields), $update_params);
  }
  
  /**
  * Creates an INSERT statement based on the current query
  * @param  array $params   Assoc array of values to insert
  * @return array           array(sql, params)
  */
  function insert($params) {
    // no need to clone as non-destructive
    $update_fields = $this->_resolveLocal(array_keys($params));
    return array($this->dialect->insert($this->query, $update_fields), array_values($params));
  }
  
  /**
  * @access private
  */
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
  
  /**
  * Create a DELETE path based on the current query.
  * Will follow relations where 'on_delete' is set to 'cascade'
  * @param    array   $resolved   Internal parameter for recursion
  * @return   array   array( array(sql, params), array(sql, params), ... )
  */
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
  
  /**
  * Create the DELETE path for a specific primary key
  * Will follow relations where 'on_delete' is set to 'cascade'
  * @param  int   $id   The primary key to delete
  * @param  string  $query  Internal parameter for recursion
  * @param  array   $resolved Internal parameter for recursion
  */
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
  
  /**
  * Creates an SELECT statement based on the current query
  * @return array           array(sql, params)
  */
  function select() {
    return array($this->dialect->select($this->query), $this->params);
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Queryset_Exception extends Dormio_Exception {}
?>