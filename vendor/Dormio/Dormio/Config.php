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
 * Basic entity manager
* @author Tris Forster
* @package Dormio
*/
class Dormio_Config {

	/**
	 * Entity cache
	 * @var multitype:Dormio_Config_Entity
	 */
	public $_entities = array();

	/**
	 * Source config
	 * @var multitype:mixed
	 */
	public $_config = array();

	/**
	 * Backward relations
	 * @var multitype:mixed
	 */
	public $_relations = array('auto' => array());
	
	/**
	 * Register entities with the config manager
	 * @param multitype:multitype:multitype:string $entities
	 */
	function addEntities(array $entities) {
		$this->_config = array_merge($this->_config, $entities);
		$this->findRelations(array_keys($entities));
	}

	/**
	 * Does a quick parse of the config to find related fields
	 * @param multitype:string $entities	entity names
	 */
	function findRelations($entities) {
		foreach($entities as $entity) {
			foreach($this->_config[$entity]['fields'] as $field=>&$spec) {
				if(isset($spec['entity'])) {
					if(!isset($spec['related_name'])) $spec['related_name'] = strtolower($entity) . "_set";
					$key = $spec['related_name'];
					
					$reverse = &$this->_relations[$spec['entity']];
					if(isset($reverse[$key])) {
						throw new Dormio_Config_Exception("Reverse field [{$key}] already exists for entity [{$spec['entity']}] - add a [related_name] element");
					}
					$reverse[$key] = array($entity, $field);
						
					// flag manytomany fields so intermediates can be generated
					if($spec['type'] =='manytomany' && !isset($spec['through'])) $this->_relations['auto'][] = $entity;
				}
			}
		}
	}

	/**
	 * Forces parsing of fields that result in extra entities
	 */
	function generateAutoEntities() {
		foreach($this->_relations['auto'] as $entity) {
			$this->getEntity($entity);
		}
	}

	/**
	 * Get the reverse field spec for an entity
	 * @param string $entity	entity name
	 * @param string $accessor	the related_name or <entity>_set
	 * @throws Dormio_Config_Exception
	 * @return multitype:string
	 */
	function getReverseField($entity, $accessor) {
		if(!isset($this->_relations[$entity])) {
			throw new Dormio_Config_Exception("Entity [{$entity}] has no reverse fields");
		}
		if(!isset($this->_relations[$entity][$accessor])) {
			throw new Dormio_Config_Exception("Entity [{$entity}] has no reverse field [{$accessor}]");
		}
		$reverse = $this->_relations[$entity][$accessor];
		return $this->getEntity($reverse[0])->getReverse($reverse[1]);
	}
	
	/**
	 * Get all reverse pairs for an entity (entity, field)
	 * @param string $entity
	 * @return multitype:multitype:string
	 */
	function getReverseFields($entity) {
		if(!isset($this->_relations[$entity])) return array();
		return $this->_relations[$entity]; 
	}
	
	function getThroughAccessor($spec) {
		$reverse = $this->getReverseFields($spec['entity']);
		foreach($reverse as $accessor=>$pair) {
			if($pair[0] == $spec['through'] && $pair[1] == $spec['map_remote_field']) return $accessor;
		}
		throw new Dormio_Config_Exception("Entity [{$entity}] has no accessor for [{$target}->{$field}]");
	}

	/**
	 * Get a list of all entities
	 * @return multitype:string
	 */
	function getEntities() {
		return array_keys($this->_config);
	}

	/**
	 * Get an entity by name
	 * @param string $entity		entity name
	 * @throws Dormio_Config_Exception
	 * @return Dormio_Dormio_Config_Entity
	 */
	function getEntity($entity) {
		if(!isset($this->_entities[$entity])) {
			if(!isset($this->_config[$entity])) {
				throw new Dormio_Config_Exception("Entity [{$entity}] is not defined in configuration");
			}
			$this->_entities[$entity] = new Dormio_Config_Entity($entity, $this->_config[$entity], $this);
		}
		return $this->_entities[$entity];
	}
}

/**
 * Entity
 * Features a progressive parser for maximum efficiency
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_Config_Entity {

	/**
	 * Table name
	 * @var string
	 */
	public $table;

	/**
	 * Field for primary key
	 * @var multitype:string
	 */
	public $pk;

	/**
	 * Indexes for this entity
	 * @var multitype:array
	 */
	public $indexes;

	/**
	 * Field specs
	 * @var multitype:array
	 */
	public $fields;

	/**
	 * The config object
	 * @var Dormio_Config
	 */
	public $config;
	
	/**
	 * Extra data about the table
	 * @var multitype:string
	 */
	public $meta;
	
	/**
	 * Entity name
	 * @var string
	 */
	public $name;

	/**
	 * Construct a new Dormio_Dormio_Config_Entity
	 * @param string $name				entity name
	 * @param multitype:array $entity	config array
	 * @param Dormio_Config $config		parent config
	 * @throws Dormio_Config_Exception
	 */
	function __construct($name, $entity, $config) {
		if(!isset($entity['fields'])) {
			throw new Dormio_Config_Exception("Entity [{$name}] is missing a [fields] element");
		}

		$this->config = $config;
		
		$this->name = $name;
		$this->table = (isset($entity['table'])) ? $entity['table'] : strtolower($name);
		$this->indexes = (isset($entity['indexes'])) ? $entity['indexes'] : array();
		
		// table metadata
		$title = Dormio::title($name);
		$meta = array(
			'verbose' => $title,
			'plural' => $title . 's',
			'model_class' => 'Dormio_Object',
		);
		if(isset($entity['meta'])) $meta = array_merge($meta, $entity['meta']);
		
		$this->meta = $meta;
		
		// set a primary key field (can be overridden)
		$this->fields['pk'] = array('type' => 'ident', 'db_column' => strtolower($name) . "_id", 'is_field' => true, 'verbose' => 'ID', 'validated' => true);
		
		// validate all the fields
		foreach($entity['fields'] as $field=>$spec) {
			$this->fields[strtolower($field)] = $this->validateField($field, $spec);
		}
		$this->pk = $this->getField('pk');
	}

	/**
	 * Get a field specification
	 * @param string $field		field name
	 * @return multitype:string
	 */
	function getField($field) {
		$field = strtolower($field);
		if(!isset($this->fields[$field])) {
			try {
				return $this->config->getReverseField($this->name, $field);
			} catch(Dormio_Config_Exception $dce) {
				throw new Dormio_Config_Exception("Entity [{$this->name}] has no field [{$field}]");
			}
		}
		return $this->fields[$field];
	}

	/**
	 * Get a reverse field specification for a related type
	 * @param string $field		field name
	 * @throws Dormio_Config_Exception	if the field is not reversable
	 * @return multitype:string
	 */
	function getReverse($field) {
		$spec = $this->getField($field);
		if(!isset($spec['reverse'])) {
			throw new Dormio_Config_Exception("Field [{$field}] is not reversable");
		}
		return $spec['reverse'];
	}
	
	function getRelatedEntity($field) {
		$spec = $this->getField($field);
		if(!isset($spec['entity'])) {
			throw new Dormio_Config_Exception("Field [{$field}] is not a related field");
		}
		return $this->config->getEntity($spec['entity']);
	}
	
	function getRelatedFields() {
		return array_keys($this->config->getReverseFields($this->name));
	}
	
	/**
	 * Returns all local fields
	 * @return multitype:array
	 */
	function getFields() {
		return $this->fields;
	}
	
	/**
	 * Return all non-entity local fields
	 * @return multitype:array
	 */
	function getSimpleFields() {
		$result = array();
		foreach($this->fields as $field=>$spec) {
			if(!isset($spec['entity'])) $result[$field] = $spec;
		}
		return $result;
	}
	
	/**
	 * Returns all local and related fields, including reverse ones
	 * @return multitype:array
	 */
	function getAllFields() {
		$fields = $this->fields;
		foreach($this->getRelatedFields() as $field) $fields[$field] = $this->getField($field);
		return $fields;
	}
	
	function getFieldNames() {
		return array_keys($this->fields);
	}
	
	function getDBColumn($field) {
		$f = $this->getField($field);
		return $f['db_column'];
	}
	
	function isField($field) {
		return isset($this->fields[$field]);
	}
	
	/**
	 * Get a field parameter
	 * @param string $field
	 * @param string $param
	 * @param mixed $default
	 * @return string
	 */
	function getParam($field, $param, $default=null) {
		$spec = $this->fields[strtolower($field)];
		if(isset($spec[$param])) {
			return $spec[$param];
		} else {
			return $default;
		}
	}
	
	/**
	 * Get the named param for all fields
	 * e.g. getParams('verbose');
	 * @param string $param
	 * @param mixed $default
	 * @return multitype:string
	 */
	function getParams($param, $default=null) {
		$result = array();
		foreach(array_keys($this->fields) as $field) {
			$result[$field] = $this->getParam($field, $param, $default);
		}
		return $result;
	}
	
	/**
	 * Get meta information about an entity
	 * 
	 * @param string $name name of meta entry
	 * @param mixed $default if meta entry not set
	 * @return string
	 */
	function getMeta($name, $default=null) {
		if(isset($this->meta[$name])) {
			return $this->meta[$name];
		}
		return $default;
	}
	
	/**
	 * Resolve a path to an entity and field
	 * 
	 * @param string $path
	 * @return multitype:mixed array($entity, $field)
	 */
	function resolvePath($path) {
		$o = $this;
		$parts = explode('__', $path);
		while(count($parts) > 1) {
			$o = $o->getRelatedEntity(array_shift($parts));
		}
		if(!$o->isField($parts[0])) {
			throw new Dormio_Config_Exception("No field [{$parts[0]}] on entity [{$o->name}]");
		}
		return array($o, $parts[0]);
	}
	
	function asArray() {
		return array(
			'name' => $this->name,
			'table' => $this->table,
			'indexes' => $this->indexes,
			'fields' => $this->fields,
			'meta' => $this->meta,
		);
	}

	function validateField($field, $spec) {
		// check it has a type
		if(!isset($spec['type'])) {
			throw new Dormio_Config_Exception("Field [{$field}] is missing a [type] element");
		}

		switch($spec['type']) {
			case 'foreignkey':
				//case 'manytoone':
			case 'onetoone':
				return $this->validateHasOne($field, $spec);
			case 'manytomany':
				return $this->validateManyToMany($field, $spec);
			default:
				return $this->validateDefault($field, $spec);
		}
	}

	function validateRelated($field, $spec) {
		if(!isset($spec['entity'])) {
			throw new Dormio_Config_Exception("Field [{$field}] is missing an [entity] element");
		}
	}

	function validateDefault($field, $spec) {
		if(isset($spec['entity'])) {
			throw new Dormio_Config_Exception("Field [{$field}] has an [entity] element defined but is not a recognised related type");
		}

		$defaults = array('verbose' => Dormio::title($field), 'db_column' => strtolower($field), 'null_ok' => false, 'is_field' => true);
		$spec = array_merge($defaults, $spec);
		return $spec;
	}

	function validateHasOne($field, $spec) {
		$this->validateRelated($field, $spec);

		$defaults = array(
			'verbose' => Dormio::title($field),
			'db_column' => strtolower($field) . "_id",
			'null_ok' => false,
			'local_field' => $field,
			'remote_field' => 'pk',
			'on_delete' => ($spec['type'] == 'onetoone') ? 'blank' : 'cascade',
			'is_field' => true,
		);
		$spec = array_merge($defaults, $spec);

		// add a reverse element
		$spec['reverse'] = array(
			'type' => ($spec['type'] == 'onetoone') ? "onetoone" : 'onetomany',
			'local_field' => $spec['remote_field'],
			'remote_field' => $spec['local_field'],
			'entity' => $this->name,
			'verbose' => Dormio::title($spec['related_name']),
			'on_delete' => $spec['on_delete'],
			'is_field' => false,
		);

		// add an index on the field
		$this->indexes["{$field}_0"] = array($spec['db_column'] => true);

		return $spec;
	}

	function validateManyToMany($field, $spec) {
		$this->validateRelated($field, $spec);

		// set some defaults
		$defaults = array(
			'verbose' => Dormio::title($field),
			'through' => null,
			'map_local_field' => null,
			'map_remote_field' => null,
			'is_field' => false,
		);
		$spec = array_merge($defaults, $spec);

		// we need an intermediate table to map through
		if ($spec['through']) {
			// auto-discover local and remote map fields
			if(!isset($spec['map_local_field'])) {
				$local_spec = $this->getField($spec['through'] . "_set");
				$spec['map_local_field'] = $local_spec['remote_field'];
			}
			if(!isset($spec['map_remote_field'])) {
				$remote_entity = $this->config->getEntity($spec['entity']);
				$remote_spec = $remote_entity->getField($spec['through'] . "_set");
				$spec['map_remote_field'] = $remote_spec['remote_field'];
			}
		} else {
			// generate a new entity to act as the intermediary
			$spec['through'] = $this->generateIntermediate($spec['entity']);
			$spec['map_local_field'] = 'lhs';
			$spec['map_remote_field'] = 'rhs';
		}

		// Add the reverse
		$spec['reverse'] = array(
			'type' => 'manytomany',
			'through' => $spec['through'],
			'entity' => $this->name,
			'map_local_field' => $spec['map_remote_field'],
			'map_remote_field' => $spec['map_local_field'],
			'is_field' => false,
			'verbose' => $this->meta['verbose'],
		);

		return $spec;
	}

	function generateIntermediate($r_entity) {
		$l_entity = $this->name;

		// Ensure we always get the same name
		$key = ($l_entity < $r_entity) ? "{$l_entity}_X_{$r_entity}" : "{$r_entity}_X_{$l_entity}";
		$table = strtolower($key);
		
		$through = array(
			'table' => $table,
			'fields' => array(
				"lhs" => array('type' => 'foreignkey', 'entity' => $l_entity, 'related_name' => $table . '_left', 'db_column' => 'l_' . strtolower($l_entity). "_id"),
				"rhs" => array('type' => 'foreignkey', 'entity' => $r_entity, 'related_name' => $table . '_right', 'db_column' => 'r_' . strtolower($r_entity). "_id"),
			),
			'verbose' => "{$l_entity} > {$r_entity}",
			);

		// add it to the entities
		$this->config->addEntities(array($key => $through));
		return $key;
	}

	function validateHasMany($entity, $field, $spec) {
		$this->validateModel($entity, $field, $spec);
		if(!$spec['verbose']) $spec['verbose'] = Dormio::title($field);

		return $spec;
	}

	function __toString() {
		return "[Entity {$this->name}]";
	}
}

/**
 * @package Dormio
 * @subpackage Exception
 */
class Dormio_Config_Exception extends Exception {}