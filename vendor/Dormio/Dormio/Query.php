<?php
/**
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
 * @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
 * @package Dormio
 */

/**
 * Class to programatically generate SQL based on entities
 *
 * Most methods accept either a field key (as defined in your entity) or
 * a descriptor which traces relations across the tables.
 * e.g. `blog__title` on a *Comment* query refers to the `title` field on the associated *Blog* entity
 *
 * Reverse relations can either be accessed by field, for example blog has a reverse field defined for its
 * comments, or by adding _set to the model name so the same can be achieved by calling 'comment_set'.
 * This also works for onetoone relations e.g. `author__profile__fav_colour` on a *Blog* query will traverse
 * the intermediate tables to get to the fav_colour field on the *Profile* entity. (FIXME: Incorrect)
 *
 * Django users will notice the blatent plagerism here, the main difference being the filter() method, where Django
 * uses kwargs `qs->filter(age__gt=16)` Dormio uses a separate operator `$qs->filter('age', '>', 16)`;
 *
 * @example entities.php The entities described in these examples
 * @example usage.php Example usage
 * @package Dormio
 */
class Dormio_Query {
	/**
	 * Query data
	 * @ignore
	 * @var multitype:mixed
	 */
	public $query = array(
		'select' => array(), // automatically added fields
		'modifiers' => null, // string - e.g. TOP, DISTINCT
		'from' => null, // string - the base table name
		'join' => null, // array - join criteria
		'where' => null, // array of items that are AND'ed together
		'order_by' => null, // array
		'limit' => null, // int
		'offset' => null, // int
	);

	/**
	 * Table aliases
	 * @var multitype:string
	 */
	public $aliases;

	/**
	 * Alias for the base entity
	 * @var string
	 */
	public $alias;

	/**
	 * Store path mappings as we go
	 * @var multitype:string
	 */
	public $reverse;

	/**
	 * The base entity for this query
	 * @var Dormio_Config_Entity
	 */
	public $entity;

	/**
	 * Params to be sent with the query
	 * @var multitype:mixed
	 */
	public $params;

	/**
	 * Next table alias
	 * @var int
	 */
	private $next_alias;

	/**
	 * Query compiler
	 * @var Dormio_Dialect_Generic
	 */
	public $dialect;
	
	static $logger;

	/**
	 * Create a new Query
	 * @param  Dormio_Entity  $entity   base entity for query
	 * @param  string|Dormio_Dialect $dialect  A dialect object or the name of one e.g. sqlite
	 * @param  int $alias  The table alias to start at [default: 1]
	 */
	function __construct(Dormio_Config_Entity $entity, $dialect='generic', $alias=1) {
		//$this->entity = is_object($entity) ? $entity : Dormio_Config::instance()->getEntity($entity);
		$this->entity = $entity;

		$this->dialect = is_object($dialect) ? $dialect : Dormio_Dialect::factory($dialect);
		$this->params = array();
		$this->reverse = array();

		$this->setAlias($alias);

		// add the base table and its primary key
		$this->query['from'] = "{{$this->entity->table}}<@ AS {$this->alias}@>";
		$this->_addFields($this->entity, $this->alias);
	}

	/**
	 * Set the table alias for this query
	 * @param int $id
	 * @return Dormio_Query
	 */
	function setAlias($id=1) {
		$this->alias = 't' . $id;
		$this->aliases = array($this->entity->name => $this->alias);
		$this->next_alias = $id + 1;
		return $this;
	}

	/**
	 * Override select fields with just the current primary key
	 * @return Dormio_Query
	 */
	function selectIdent() {
		$this->query['select'] = array("<@{$this->alias}.@>{{$this->entity->pk['db_column']}}");
		$this->reverse = array();
		return $this;
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
	 * @return Dormio_Query
	 * @todo validate IN and LIKE behaviour
	 */
	function filter($key, $op, $value, $clone=true) {
		return $this->filterBind($key, $op, $value, $clone);
	}

	function filterBind($key, $op, &$value, $clone=true) {
		$o = ($clone) ? clone $this : $this;
		$f = $o->_resolveField($key);
		$v = '?';
		if($op == 'IN') {
			if(!is_array($value)) throw new Dormio_Query_Exception('Need array for IN operator');
			$v = '(' . implode(', ', array_fill(0, count($value), '?')) . ')';
		}
		$o->query['where'][] = "{$f} {$op} {$v}";
		if($value instanceof Dormio_Object) $value = $value->ident();
		if($op == 'IN') {
			$o->params = array_merge($o->params, $value);
		} else {
			$o->params[] = &$value;
		}
		return $o;
	}

	/**
	 * Add a non value based filter
	 * e.g. 'IS NOT NULL', 'IS NULL'
	 * @param string field descriptor
	 * @param string $special special expression
	 * @return Dormio_Query
	 */
	function filterSpecial($key, $special) {
		$o = clone $this;
		$o->query['where'][] = $o->_resolveField($key) . " " . $special;
		return $o;
	}

	/**
	 * Add a single field to the SELECT
	 * This should not be used if you are planning to hydrate an object with this query
	 * @param string $path
	 * @param string $alias
	 * @return Dormio_Query
	 */
	function field($path, $alias=null) {
		// this needs to left join
		$o = clone $this;
		$p = $o->_resolvePath($path, 'LEFT');
		if($alias) $alias = $this->alias . "_" . $alias;
		//$as = $o->_addField($p[2], $p[1], $alias);
		//$o->reverse[$as] = $path;
		$o->query['select'][] = "{$p[2]}.{{$p[1]}} AS {{$path}}";
		return $o;
	}

	/**
	 * Makes the query DISTINCT.
	 * @return Dormio_Query
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
	 * @return Dormio_Query
	 */
	function where($clause, $params=array()) {
		$o = clone $this;
		$o->query['where'][] = $o->_resolveString($clause);
		$o->params = array_merge($o->params, $params);
		return $o;
	}

	/**
	 * Do an eager join with another table.
	 * Performs LEFT join
	 * @param  string  $table   One or more tables to join
	 * @return Dormio_Query
	 */
	function with() {
		$o = clone $this;
		foreach(func_get_args() as $table) {
			$parts = explode('__', $table);
			list($spec, $alias) = $o->_resolveArray($parts, 'LEFT', true);
			$o->_addFields($spec, $alias, $table . '__');
		}
		return $o;
	}

	/**
	 * Limit the results.
	 * @param  int   $limit  Records to return
	 * @param  int   $offset Optional number of records to skip
	 * @return Dormio_Query
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
	 * @return Dormio_Query
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
	 * @param Dormio_Config_Entity $entity
	 * @param string $alias			table alias
	 * @param string $path			base selector path [default: '']
	 * @access private
	 */
	function _addFields($entity, $alias, $path='') {
		foreach($entity->getFields() as $key=>$spec) {
			if($spec['is_field']) {
				$accessor = $path . $key;
				$this->query['select'][] = "{$alias}.{{$spec['db_column']}} AS {{$accessor}}";
				//$this->query['select'][] = "{$alias}.{{$spec['db_column']}} AS {{$alias}_{$spec['db_column']}}";
				//$as = $this->_addField($alias, $spec['db_column']);
				//$accessor = $path . $key;
				//$this->reverse[$as] = $accessor;
			}
		}
	}

	/**
	 * Adds a field to the SELECT
	 * @param string $table_alias
	 * @param string $db_column
	 * @param string $as	override default result key
	 * @return string		key in the result
	 */
	/*
	function _addField($table_alias, $db_column, $as=null) {
		if(!$as) $as = "{$table_alias}_{$db_column}";
		$this->query['select'][] = "{$table_alias}.{{$db_column}} AS {{$as}}";
		return $as;
	}
	*/
	/**
	 * Resolves bracketed terms in a string.
	 * eg "{comment__blog__title} = ?" becomes "{blog}.{title} = ?"
	 * Joins will be added automatically as required
	 * @access private
	 */
	function _resolveString($str, $local=false) {
	 $callback = ($local) ? '_resolveStringLocalCallback' : '_resolveStringCallback';
	 return preg_replace_callback('/{([a-z_]+)}/', array($this, $callback), $str);
	}
	/**
	 * @ignore
	 */
	function _resolveStringCallback($matches) {
	 if($matches[1]=='table') return "{" . $this->entity->table . "}";
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
	 * @param string $path
	 * @param string $type 	force a specific join (INNER, LEFT) [default: null]
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
	 	//$result[] = "{" . $this->entity->getDBColumn($field) . "}";
	 	$result[] = $this->entity->getDBColumn($field);
	 }
	 return $result;
	}

	/**
	 * Resolves paths to parent meta and field
	 * @param string $path
	 * @param string $type 		force a specific join (INNER, LEFT) [default: null]
	 * @param boolean $strip_pk dont join unless we have to [default: true]
	 * @return array array($parent_meta, $field, $alias);
	 * @access private
	 */
	function _resolvePath($path, $type=null, $strip_pk=true) {

		self::$logger && self::$logger->log("_resolvePath('{$path}')");
		try {
	 		list($meta, $alias, $field) = $this->_resolveArray(explode('__', $path), $type);
		} catch(Dormio_Config_Exception $dce) {
	 		throw new Dormio_Query_Exception("Failed to resolve path '{$path}': " . $dce->getMessage());
		}

		//if(!isset($meta->fields[$field])) throw new Dormio_Queryset_Exception('No such field: ' . $field);
		return array($meta, $meta->fields[$field]['db_column'], $alias);
	}

	/**
	 * Resolves accessors and returns the top level entity
	 * @param  $parts  multitype:string   an array of field accessors that can be chained
	 * @param  $type   string  			  the type of join to perform [LEFT]
	 * @return Dormio_Config_Entity       the top level entity
	 * @access private
	 */
	function _resolveArray($parts, $type=null, $full_joins=false) {
		$entity = $this->entity;
		$alias = $this->alias;

		// default to INNER JOIN
		if($type==null) $type = "INNER";

		$c = count($parts);
		// remove any pk
		if($c>1 && $parts[$c-1] == 'pk') $c--;

		for($i=0; $i<$c; $i++) {
			// get the field spec
			$field = $parts[$i];
			$spec = $entity->getField($field);

			// do the first hop of any link tables and set up the second
			if(isset($spec['through'])) {
				if($type!='INNER') self::$logger && self::$logger->log("Trying to {$type} JOIN onto {$spec['entity']} - may have unexpected results", "WARN");

				// do the reverse bit
				$through_entity = $entity->config->getEntity($spec['through']);
				$through_spec = $through_entity->getReverse($spec['map_local_field']);
				// use a left join to preserve current results
				$entity = $this->_addJoin($entity, $through_spec, "LEFT", $alias);

				// update the field
				$field = $spec['map_remote_field'];

				// if we are the last hop then no field has been requested so we can return the PK of the mid table
				if(!$full_joins && $i==$c-1) {
					self::$logger && self::$logger->log("Only half join required for {$field}");
					return array($entity, $alias, $field);
				}

				// update the spec so the default join will continue as expected
				$spec = $entity->getField($spec['map_remote_field']);
			}

			//self::$logger && self::$logger->log("HOP: {$entity->name} -> {$field}");

			// check whether this join is actually needed on final run
			if($i==$c-1 && $spec['is_field']) {
				if(!$full_joins) break;
			}

			// finally, do the join
			$entity = $this->_addJoin($entity, $spec, $type, $alias);
		}

		// check whether it is a reverse field
		if(!$spec['is_field']) {
			$field = $spec['local_field'];
		}

		self::$logger && self::$logger->log("_resolveArray() returning {$entity->name} and {$alias}.{$field}");
		return array($entity, $alias, $field);
	}

	/**
	 * Takes care of joining tables together by field name.
	 * Defaults to INNER joins unless specified
	 * @param  Dormio_Config_Entity $left   lefthand table
	 * @param  string  $field  				field containing the relation
	 * @param  string  $type         		type of join to perform
	 * @return Dormio_Config_Entity 		righthand table which was joined to
	 * @access private
	 */
	function _addJoin($left, $spec, $type, &$left_alias) {

		$right = $left->config->getEntity($spec['entity']);

		$left_field = $left->getDBColumn($spec['local_field']);
		$right_field = $right->getDBColumn($spec['remote_field']);

		$key = "{$left->name}.{$spec['local_field']}__{$spec['entity']}.{$spec['remote_field']}";

		if(isset($this->aliases[$key])) {
			$left_alias = $this->aliases[$key];
		} else {
			$right_alias = "t" . $this->next_alias++;
			$this->query['join'][] = "{$type} JOIN {{$right->table}} AS {$right_alias} ON {$left_alias}.{{$left_field}}={$right_alias}.{{$right_field}}";
			$this->aliases[$key] = $right_alias;
			$left_alias = $right_alias;
		}

		self::$logger && self::$logger->log("_addJoin() {$left->name} -> {$right->name} AS {$left_alias}");
		return $right;
	}

	/**
	 * Creates an UPDATE statement based on the current query
	 * @param multipart:mixed $params		assoc array of values to set
	 * @param multipart:mixed $custom_fields
	 * @param multipart:mixed $custom_params
	 * @return multitype:mixed		array(sql, params)
	 */
	function update($params, $custom_fields=array(), $custom_params=array()) {
		$o = clone $this;
		$o->selectIdent();

		$update_params = array_merge(array_values($params), $custom_params, $o->params);
		$update_fields = $o->_resolveLocal(array_keys($params));
		
		foreach($custom_fields as &$field) $field = $this->_resolveString($field, true);
		
		return array($this->dialect->update($o->query, $update_fields, $custom_fields), $update_params);
	}

	/**
	 * Creates an INSERT statement
	 * @param  multitype:mixed	$params		assoc array of values to insert
	 * @return multitype:mixed				array(sql, params)
	 */
	function insert($params) {
		$update_fields = $this->_resolveLocal(array_keys($params));
		return array($this->dialect->insert($this->query, $update_fields), array_values($params));
	}

	function delete($resolved=array(), $base=null) {
		if($base === null) $base = $this;
		$sql = array();
		$this->selectIdent();
		
		foreach($this->entity->getRelatedFields() as $name) {
			$spec = $this->entity->getField($name);
			$child_entity = $this->entity->config->getEntity($spec['entity']);
			$child = new Dormio_Query($child_entity, $this->dialect, $this->next_alias);

			$child->query['where'] = $this->query['where'];
			$child->params = $this->params;

			$r = $resolved;
			array_unshift($r, $spec['remote_field']);
			$child->_resolveArray($r, 'INNER', true);

			if($base->query['join']) {
				$child->query['join'] = array_merge($child->query['join'], $base->query['join']);
				$child->aliases = array_merge($child->aliases, $base->aliases);
			}

			$sql = array_merge($sql, $child->delete($r, $base));

		}


		$result = array($this->dialect->delete($this->query), $this->params);

		// rewrite all references to the base model
		$base_key = "__{$base->entity->name}.pk";
		
		foreach($this->aliases as $key=>$alias) {
			if(substr($key, -strlen($base_key)) == $base_key) {
				$result[0] = str_replace($alias, $base->alias, $result[0]);
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
		
		foreach($this->entity->getRelatedFields() as $name) {
			$spec = $this->entity->getField($name);
			$child_entity = $this->entity->config->getEntity($spec['entity']);
			$child = new Dormio_Query($child_entity, $this->dialect);
			$child->selectIdent();

			$r = $resolved;
			array_unshift($r, $spec['remote_field']);
			
			if($spec['type'] == 'manytomany') { // just delete the entry
				// pass
			} else {
				$q = implode('__', $r);
				switch($spec['on_delete']) {
	 			case "cascade":
	 				$sql = array_merge($sql, $child->deleteById($id, $q, $r));
	 				break;
	 			case "blank":
	 			case "set_null":
	 				$sql[] = $child->filter($q, '=', $id)->update(array($q => null));
	 				break;
	 			default:
	 				var_dump($spec);
	 				throw new Dormio_Queryset_Exception("Unknown ON DELETE action '{$spec['on_delete']}' for field '{$spec['db_column']}'");
				}
			}
		}
		
		//self::$logger && self::$logger->log("DELETEID: {$this->entity->name} {$query}");
		$o = $this->filter($query, '=', $id);
		
		$delete = array($this->dialect->delete($o->query), $o->params);
		//self::$logger && self::$logger->log($delete);
		$sql[] = $delete;
		
		return $sql;
	}

	/**
	 * Creates an SELECT statement based on the current query
	 * @return array           array(sql, params)
	 */
	function select() {
		return array($this->dialect->select($this->query), $this->params);
	}

	function __toString() {
		$sql = $this->select();
		return $sql[0] . "; (" . implode(', ', $sql[1]) . ")";
	}

	/**
	 * Turns a result row from this query into a multi-dimentional array
	 * Maps all db_columns back to their fields
	 * @param multitype:string $row
	 * @return multitype:mixed
	 */
	/*
	function mapArray($row) {
		$result = array();
		foreach($row as $key=>$value) {
			$parts = explode('__', $this->reverse[$key]);
			$arr = &$result;
			$p = count($parts)-1;
			for($i=0; $i<$p; $i++) {
				$arr = &$arr[$parts[$i]];
				if(!is_array($arr)) $arr = array('pk' => $arr); // convert id to array
			}
			$arr[$parts[$p]] = $value;
		}
		return $result;
	}
	*/
}

/**
 * @package Dormio
 * @subpackage Exception
 */
class Dormio_Query_Exception extends Exception {}
