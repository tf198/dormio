<?php
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
  
  public $aliases, $_alias;
  
  /**
  * Create a new Queryset
  * @param  string|Dormio_Meta  $meta   A meta object or the name of a model
  * @param  string|Dormio_Dialect $dialect  A dialect object or the name of one e.g. sqlite
  * @param  int $alias  The table alias to start at [default: 1]
  */
  function __construct($meta, $dialect='generic', $alias=1) {
    $this->_meta = is_object($meta) ? $meta : Dormio_Meta::get($meta);
    $this->dialect = is_object($dialect) ? $dialect : Dormio_Dialect::factory($dialect);
    $this->params = array();
    
    $this->_alias = 't' . $alias;
    $this->aliases = array($this->_meta->model => $this->_alias);
    
    // add the base table and its primary key
    $this->query['from'] = "{{$this->_meta->table}}<@ AS {$this->_alias}@>";
    $this->_addFields($this->_meta, $this->_alias);
    
    $this->_next_alias = $alias+1;
  }
  
  /**
  * Add the primary key field to the query
  */
  function _selectIdent() {
    $this->query['select'] = array("<@{$this->_alias}.@>{{$this->_meta->pk}}");
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
  function filter($key, $op, $value, $clone=true) {
    return $this->filterVar($key, $op, $value, $clone);
  }
  
  function filterVar($key, $op, &$value, $clone=true) {
    $o = ($clone) ? clone $this : $this;
    $f = $o->_resolveField($key);
    $v = '?';
    if($op == 'IN') {
      if(!is_array($value)) throw new Dormio_Queryset_Exception('Need array for IN operator');
      $v = '(' . implode(', ', array_fill(0, count($value), '?')) . ')';
    }
    $o->query['where'][] = "{$f} {$op} {$v}";
    if($value instanceof Dormio_Model) $value = $value->ident();
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
    if($alias) { // place it in the current object scope
      $alias = $this->_alias . "_" . $alias;
    } else {
      $alias = "{$p[2]}_" . str_replace('__', '_', $path);
    }
    $o->query['select'][] = "<@{$p[2]}.@>{{$p[1]}}<@ AS {{$alias}}@>";
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
      list($spec, $alias) = $o->_resolveArray(explode('__', $table), 'LEFT');
      $o->_addFields($spec, $alias);
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
  * Add all the fields for a table
  * @param  Dormio_Meta $meta The meta for the table to add
  * @access private
  */
  function _addFields($meta, $alias) {
    foreach($meta->fields as $key=>$spec) {
      if(isset($spec['is_field']) && $spec['is_field']) {
        $this->query['select'][] = "{$alias}.{{$spec['db_column']}} AS {{$alias}_{$spec['db_column']}}";
      }
    }
  }
  
  /**
  * Resolves path to the format "{table}.{column} AS {table_column}".
  * @param  string  $item   e.g. "author__profile__age" 
  * @return  string "t3.{age} AS {t3_age}"
  * @access private
  */
  function _resolvePrefixed($item, $type=null) {
    $p = $this->_resolvePath($item, $type);
    return "<@{$p[2]}.@>{{$p[1]}}<@ AS {{$p[2]}_{$p[1]}}@>";
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
    return $this->_meta->fields[$matches[1]]['db_column'];
  }

  /**
  * Resolve to an aliased field
  * @return string e.g. "t1.{name}"
  * @access private
  */
  function _resolveField($path, $type=null) {
    $p = $this->_resolvePath($path, $type);
    return "<@{$p[2]}.@>{{$p[1]}}";
  }
  
  /**
  * Resolves field names to their sql columns.
  * DOES NOT FOLLOW RELATIONS
  * @access private
  */
  function _resolveLocal($fields, $type=null) {
    $result = array();
    foreach($fields as $field) {
      if(!isset($this->_meta->fields[$field])) throw new Dormio_Queryset_Exception('No such local field: ' . $field);
      $result[] = "{{$this->_meta->fields[$field]['db_column']}}";
    }
    return $result;
  }
  
  /**
  * Resolves paths to parent meta and field
  * @return array array($parent_meta, $field, $alias);
  * @access private
  */
  function _resolvePath($path, $type=null, $strip_pk=true) {
    $parts = explode('__', $path);
    $field = array_pop($parts);
    if($strip_pk && $field=='pk' && $parts) $field = array_pop($parts);
    list($meta, $alias) = $this->_resolveArray($parts, $type);
    
    if(!isset($meta->fields[$field])) throw new Dormio_Queryset_Exception('No such field: ' . $field);
    return array($meta, $meta->fields[$field]['db_column'], $alias);
  }
  
  /**
  * Resolves accessors and returns the top level meta object
  * @param  $parts  array   An array of field accessors that can be chained
  * @param  $type   string  The type of join to perform [LEFT]
  * @return object          The top level meta object
  * @access private
  */
  function _resolveArray($parts, $type=null) {
    $meta = $this->_meta;
    $alias = $this->_alias;
    for($i=0,$c=count($parts); $i<$c; $i++) {
      $spec = $meta->getSpec($parts[$i]);
      $meta = $this->_addJoin($meta, $spec, $type, $alias);
    }
    return array($meta, $alias);
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
  function _addJoin($left, $spec, $type, &$left_alias) {
    if(!$type) $type='INNER';
    
    // if manytomany redispatch in two queries
    if(isset($spec['through'])) {
      if($type!='INNER') trigger_error("Trying to {$type} JOIN onto {$spec['model']} - may have unexpected results", E_USER_WARNING);
      
      // do the reverse bit
      $through_meta = Dormio_Meta::get($spec['through']);
      $reverse_spec = $through_meta->getReverseSpec($left->model, $spec['map_local_field']);
      $mid = $this->_addJoin($left, $reverse_spec, "INNER", $left_alias);
      
      // do the forward bit
      if(!$spec['map_remote_field']) $spec['map_remote_field'] = $mid->getAccessorFor($spec['model']);
      $spec = $mid->getSpec($spec['map_remote_field']);
      return $this->_addJoin($mid, $spec, "INNER", $left_alias);
    }
    
    $right = Dormio_Meta::get($spec['model']);
    
    $left_field = $left->getColumn($spec['local_field']);
    $right_field = $right->getColumn($spec['remote_field']);
    
    $key = "{$left->model}.{$spec['local_field']}__{$spec['model']}.{$spec['remote_field']}";
    
    if(isset($this->aliases[$key])) {
      $left_alias = $this->aliases[$key];
    } else {
      $right_alias = "t" . $this->_next_alias++;
      $this->query['join'][] = "{$type} JOIN {{$right->table}} AS {$right_alias} ON {$left_alias}.{{$left_field}}={$right_alias}.{{$right_field}}";
      $this->aliases[$key] = $right_alias;
      $left_alias = $right_alias;
    }
    return $right;
  }
  
  /**
  * Creates an UPDATE statement based on the current query
  * @param  array $params   Assoc array of values to set
  * @param  array $custom_fields
  * @return array           array(sql, params)
  */
  //function update($params, $custom_fields=array(), $custom_params=array()) {
  function updateSQL($params, $custom_fields=array(), $custom_params=array()) {
    $o = clone $this;
    $o->_selectIdent();
    
    $update_params = array_merge(array_values($params), $custom_params, $o->params);
    //$update_params = array_merge(array_values($params), $o->params);
    $update_fields = $o->_resolveLocal(array_keys($params));
    foreach($custom_fields as &$field) $field = $this->_resolveString($field, true);
    //return array($this->dialect->update($o->query, $update_fields, $custom_fields), $update_params);
    return array($this->dialect->update($o->query, $update_fields, $custom_fields), $update_params);
  }
  
  /**
  * Creates an INSERT statement
  * @param  array $params   Assoc array of values to insert
  * @return array           array(sql, params)
  */
  function insertSQL($params) {
    $update_fields = $this->_resolveLocal(array_keys($params));
    return array($this->dialect->insert($this->query, $update_fields), array_values($params));
  }
  
  function deleteSQL($resolved=array(), $base=null) {
    if($base === null) $base = $this;
    $sql = array();
    $this->_selectIdent();
    
    foreach($this->_meta->reverseFields() as $spec) {
      $child = new Dormio_Queryset($spec['model'], $this->dialect, $this->_next_alias);
      
      $child->query['where'] = $this->query['where'];
      $child->params = $this->params;
      
      $r = $resolved;
      array_unshift($r, $spec['accessor']);
      $child->_resolveArray($r);
      
      if($base->query['join']) {
        $child->query['join'] = array_merge($child->query['join'], $base->query['join']);
        $child->aliases = array_merge($child->aliases, $base->aliases);
      }
      
      $sql = array_merge($sql, $child->deleteSQL($r, $base));
      
    }
    
    
    $result = array($this->dialect->delete($this->query), $this->params);
    
    // rewrite all references to the base model
    $base_key = "__{$base->_meta->model}.pk";
    //var_dump($this->aliases, $base_key);
    foreach($this->aliases as $key=>$alias) {
      if(substr($key, -strlen($base_key)) == $base_key) {
        $result[0] = str_replace($alias, $base->_alias, $result[0]);
      }
    }
    $sql[] = $result;
    
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
    if($this->query['where']) throw new Dormio_Queryset_Exception("Cannot delete item if filters are set");
    $sql = array();
    foreach($this->_meta->reverseFields() as $spec) {
      $child = new Dormio_Queryset($spec['model']);
      $child->_selectIdent();
      
      $r = $resolved;
      array_unshift($r, $spec['accessor']);
      
      $q = implode('__', $r);
      switch($spec['on_delete']) {
        case "cascade":
          $sql = array_merge($sql, $child->deleteById($id, $q, $r));
          break;
        case "blank":
        case "set_null":
          $sql[] = $child->filter($q, '=', $id)->updateSQL(array($q => null));
          break;
        default:
          throw new Dormio_Queryset_Exception('Unknown ON DELETE action: ' . $spec['on_delete']);
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
  function selectSQL() {
    $o = clone $this;
    return array($this->dialect->select($o->query), $o->params);
  }
  
  function __toString() {
    $sql = $this->select();
    return $sql[0] . "; (" . implode(', ', $sql[1]) . ")";
  }
}

/**
* @package dormio
* @subpackage exception
*/
class Dormio_Queryset_Exception extends Dormio_Exception {}
