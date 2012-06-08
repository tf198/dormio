<?php
/**
 * Basic entity manager
* @author tris
*/
class Dormio_Config {

	public $_entities = array();

	public $_config = array();

	public $_relations = array('auto' => array());

	/**
	 * Register entities with the config manager
	 * @param multitype:array $entities
	 */
	function addEntities($entities) {
		$this->_config = array_merge($this->_config, $entities);
		$this->findRelations(array_keys($entities));
	}

	/**
	 * Does a quick parse of the config to find related fields
	 * @param multitype:string $entities	entity names
	 */
	function findRelations($entities) {
		foreach($entities as $entity) {
			foreach($this->_config[$entity]['fields'] as $field=>$spec) {
				if(isset($spec['entity'])) {
					$reverse = array($entity, $field);
					if(isset($spec['related_name'])) $this->_relations[$spec['entity']][$spec['related_name']] = $reverse;
					$this->_relations[$spec['entity']][$entity . "_Set"] = $reverse;
						
					// flag manytomany fields so intermediates can be generated
					if($spec['type'] =='manytomany' && !isset($spec['through'])) $this->_relations['auto'][] = $reverse;
				}
			}
		}
	}

	/**
	 * Forces parsing of fields that result in extra entities
	 */
	function generateAutoEntities() {
		foreach($this->_relations['auto'] as $pair) {
			$this->getEntity($pair[0])->getField($pair[1]);
		}
	}

	/**
	 * Get
	 * @param unknown_type $entity
	 * @param unknown_type $accessor
	 * @throws Dormio_Config_Exception
	 */
	function getReverseField($entity, $accessor) {
		if(!isset($this->_relations[$entity])) throw new Dormio_Config_Exception("Entity [{$entity} has no reverse fields");
		if(!isset($this->_relations[$entity][$accessor])) throw new Dormio_Config_Exception("Entity [{$entity}] has no reverse field [{$accessor}]");
		$reverse = $this->_relations[$entity][$accessor];
		return $this->getEntity($reverse[0])->getReverse($reverse[1]);
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
			if(!isset($this->_config[$entity])) throw new Dormio_Config_Exception("Entity [{$entity}] is not defined in configuration");
			$this->_entities[$entity] = new Dormio_Config_Entity($entity, $this->_config[$entity], $this);
		}
		return $this->_entities[$entity];
	}
}

/**
 * Entity
 * Features a progressive parser for maximum efficiency
 * @author tris
 *
 */
class Dormio_Config_Entity {

	/**
	 * Table name
	 * @var string
	 */
	public $table;

	/**
	 * Verbose name for entity
	 * @var string
	 */
	public $verbose;

	/**
	 * Indexes for this entity
	 * @var multitype:array
	 */
	public $indexes;

	/**
	 * Field specs
	 * @var multitype:array
	 */
	public $_fields;

	/**
	 * @var Dormio_Config
	 */
	public $_config;

	/**
	 * Construct a new Dormio_Dormio_Config_Entity
	 * @param string $name				entity name
	 * @param multitype:array $entity	config array
	 * @param Dormio_Config $config		parent config
	 * @throws Dormio_Config_Exception
	 */
	function __construct($name, $entity, $config) {
		if(!isset($entity['fields'])) throw new Dormio_Config_Exception("Entity [{$name}] is missing a [fields] element");

		$this->name = $name;
		$this->table = (isset($entity['table'])) ? $entity['table'] : strtolower($name);
		$this->verbose = (isset($entity['verbose'])) ? $entity['verbose'] : self::title($name);
		$this->indexes = (isset($entity['indexes'])) ? $entity['indexes'] : array();
		$this->_fields = $entity['fields'];
		$this->_config = $config;
	}

	/**
	 * Get a field specification
	 * @param string $field		field name
	 * @return multitype:string
	 */
	function getField($field) {
		if(!isset($this->_fields[$field])) {
			return $this->_config->getReverseField($this->name, $field);
		}
		if(!isset($this->_fields[$field]['validated'])) {
			$this->_fields[$field] = $this->validateField($field, $this->_fields[$field]);
			$this->_fields[$field]['validated'] = true;
		}
		return $this->_fields[$field];
	}

	/**
	 * Get a reverse field specification for a related type
	 * @param string $field		field name
	 * @throws Dormio_Config_Exception	if the field is not reversable
	 * @return multitype:string
	 */
	function getReverse($field) {
		$spec = $this->getField($field);
		if(!isset($spec['reverse'])) throw new Dormio_Config_Exception("Field [{$field}] is not reversable");
		return $spec['reverse'];
	}

	function validateField($field, $spec) {
		// check it has a type
		if(!isset($spec['type'])) throw new Dormio_Config_Exception("Field [{$field}] is missing a [type] element");

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
		if(!isset($spec['entity'])) throw new Dormio_Config_Exception("Field [{$field}] is missing an [entity] element");
	}

	function validateDefault($field, $spec) {
		if(isset($spec['entity'])) throw new Dormio_Config_Exception("Field [{$field}] has an [entity] element defined but is not a recognised related type");

		$defaults = array('verbose' => self::title($field), 'db_column' => strtolower($field), 'null_ok' => false, 'is_field' => true);
		$spec = array_merge($defaults, $spec);
		return $spec;
	}

	function validateHasOne($field, $spec) {
		$this->validateRelated($field, $spec);

		$defaults = array(
			'verbose' => self::title($field),
			'db_column' => strtolower($field) . "_id",
			'null_ok' => false,
			'local_field' => $field,
			'remote_field' => 'pk',
			'on_delete' => ($spec['type'] == 'onetoone') ? 'blank' : 'cascade',
			'is_field' => true,
			'related_name' => $this->name . "_Set",
		);
		$spec = array_merge($defaults, $spec);

		// add a reverse element
		$spec['reverse'] = array(
			'type' => ($spec['type'] == 'onetoone') ? "onetoone" : 'onetomany',
			'local_field' => $spec['remote_field'],
			'remote_field' => $spec['local_field'],
			'entity' => $this->name,
			'on_delete' => $spec['on_delete']
		);

		// add an index on the field
		$this->indexes["{$field}_0"] = array($spec['db_column'] => true);

		return $spec;
	}

	function validateManyToMany($field, $spec) {
		$this->validateRelated($field, $spec);

		// set some defaults
		$defaults = array(
			'verbose' => self::title($field),
			'through' => null,
			'map_local_field' => null,
			'map_remote_field' => null,
			'related_name' => $this->name . "_Set",
		);
		$spec = array_merge($defaults, $spec);

		// we need an intermediate table to map through
		if ($spec['through']) {
			// auto-discover local and remote map fields
			if(!isset($spec['map_local_field'])) {
				$local_spec = $this->getField($spec['through'] . "_Set");
				$spec['map_local_field'] = $local_spec['remote_field'];
			}
			if(!isset($spec['map_remote_field'])) {
				$remote_entity = $this->_config->getEntity($spec['entity']);
				$remote_spec = $remote_entity->getField($spec['through'] . "_Set");
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
		);

		return $spec;
	}

	function generateIntermediate($r_entity) {
		$l_entity = $this->name;

		// Ensure we always get the same name
		$key = ($l_entity < $r_entity) ? "{$l_entity}_X_{$r_entity}" : "{$r_entity}_X_{$l_entity}";

		$through = array(
			'table' => strtolower($key),
			'fields' => array(
				"lhs" => array('type' => 'foreignkey', 'entity' => $l_entity),
				"rhs" => array('type' => 'foreignkey', 'entity' => $r_entity),
			),
			'verbose' => "{$l_entity} > {$r_entity}",
			);

		// add it to the entities
		$this->_config->addEntities(array($key => $through));
		return $key;
	}

	function validateHasMany($entity, $field, $spec) {
		$this->validateModel($entity, $field, $spec);
		if(!$spec['verbose']) $spec['verbose'] = self::title($field);

		return $spec;
	}

	static function title($str) {
		return ucwords(str_replace('_', ' ', $str));
	}
}

class Dormio_Config_Exception extends Exception {}