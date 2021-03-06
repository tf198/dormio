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
 * Basic object modeling for tables
 * Allows for easy table manipulation using objects
 * @package Dormio
 *
 */
class Dormio_Object implements ArrayAccess, Countable{

	/**
	 * @var Dormio
	 */
	public $_dormio;
	
	/**
	 * @var Dormio_Config_Entity
	 */
	public $_entity;
	
	/**
	 * @var multitype:string
	 */
	public $_data = array();
	
	/**
	 * @var multitype:string
	 */
	public $_updated = array();
	
	/**
	 * @var multitype:Dormio_Manager
	 */
	public $_related_managers = array();
	
	/**
	 * @var multitype:Dormio_Object
	 */
	public $_related_objects = array();
	
	/**
	 * @var bool
	 */
	public $_hydrated;
	
	/**
	 * Create a new Dormio_Object
	 * @param Dormio $dormio
	 * @param Dormio_Config_Entity $entity
	 */
	function __construct(Dormio $dormio, Dormio_Config_Entity $entity) {
		$this->_dormio = $dormio;
		$this->_entity = $entity;
		$this->_hydrated = false;
	}
	
	/**
	 * Load data by primary key
	 * @param string $id primary key to load
	 */
	function load($id) {
		if(!$id) throw new Dormio_Exception("No primary key given");
		if($this->_updated) throw new Dormio_Exception("Unsaved data for {$this}");
		Dormio::$logger && Dormio::$logger->log("Load id {$id}");
		$data = $this->_dormio->selectEntity($this->_entity, $id);
		$this->setData($data);
		$this->_hydrated = true;
	}

	/**
	 * Save any changes to object
	 */
	function save() {
		if($this->ident()) {
			if(isset($this->_updated['pk'])) {
				throw new Dormio_Exception('Cannot update primary key');
			}
			$this->_dormio->updateEntity($this->_entity, $this->ident(), $this->_updated);
		} else {
			$pk = $this->_dormio->insertEntity($this->_entity, $this->_updated);
			$this->_data['pk'] = $pk;
		}
		//foreach($this->_updated as $key=>$value) $this->_data[$key] = $value;
		$this->_data = array_merge($this->_data, $this->_updated);
		$this->_updated = array();
	}
	
	/**
	 * Get the current primary key
	 * @return string
	 */
	function ident() {
		return isset($this->_data['pk']) ? $this->_data['pk'] : null;
	}
	
	/**
	 * Delete the current object
	 * Also removes related items if set to cascade
	 * @return int number of rows removed from all tables
	 */
	function delete() {
		return $this->_dormio->deleteEntity($this->_entity, $this->ident());
	}
	
	/**
	 * Bulk set values
	 * @param multitype:string $arr key/value pairs to set
	 */
	function setValues($arr) {
		foreach($arr as $key=>$value) {
			$this->setFieldValue($key, $value);
		}
	}
	
	/**
	 * Hydrate the object with data
	 * @param multitype:string $data
	 */
	function setData($data) {
		if(!isset($data['pk'])) throw new Dormio_Exception("Cannot set data without a primary key");
		if($this->_updated) throw new Dormio_Exception("Unsaved data for {$this}");
		$this->_data = $data;
		$this->_updated = array();
		$this->_hydrated = false;
	}
	
	function getData() {
		return $this->_data;
	}
	
	/**
	 * Set the primary key of the object
	 * Clears all existing values
	 * @param string $id
	 */
	function setPrimaryKey($id) {
		//if(!$id) throw new Dormio_Exception("No primary key given");
		Dormio::$logger && Dormio::$logger->log("setPrimaryKey: {$id}");
		if($this->_updated) throw new Dormio_Exception("Unsaved data for {$this} ({$id})");
		$this->_data = array('pk' => $id);
		$this->_updated = array();
		$this->_hydrated = false;
	}
	
	function hydrate() {
		if($this->_hydrated) throw new Dormio_Exception("{$this->_entity} already hydrated");
		if(!isset($this->_data['pk'])) {
			throw new Dormio_Exception("No primary key set for {$this->_entity}");
		}
		Dormio::$logger && Dormio::$logger->log("Hydrating {$this->_entity}");
		$this->load($this->ident());
	}
	
	/**
	 * Get value for a field
	 * @param string $field field name
	 * @param bool $throw whether to throw an error if unable to get value
	 */
	function getFieldValue($field, $throw=true) {
		Dormio::$logger && Dormio::$logger->log("getFieldValue {$this->_entity}->{$field}", LOG_DEBUG);
		
		// changed values
		if( array_key_exists($field, $this->_updated) ) {
			return $this->_updated[$field];
		}
		
		// from database
		if( array_key_exists($field, $this->_data) ) {
			$value = $this->_data[$field];
			//Dormio::$logger && Dormio::$logger->log("Got value {$value}");
			return $this->_dormio->dialect->fromDB($value, $this->_entity->getParam($field, 'type'));
		}
		
		if($field == 'pk') throw new Dormio_Exception("{$this->_entity} has no primary key set");
		
		// not yet hydrated
		if($this->_entity->isField($field)) {
			Dormio::$logger && Dormio::$logger->log("Need to rehydrate for field {$field}");
			$this->hydrate();
			$value = $this->_data[$field];
			Dormio::$logger && Dormio::$logger->log("Got value {$value}");
			return $this->_dormio->dialect->fromDB($value, $this->_entity->getParam($field, 'type'));
		}
		
		if($throw) throw new Dormio_Exception("{$this->_entity} has no field {$field}");
		return null;
	}
	
	/**
	 * Set a field value
	 * @param string $field
	 * @param mixed $value
	 */
	function setFieldValue($field, $value) {
		if($field == 'pk') throw new Dormio_Exception("Unable to update primary key");
		Dormio::$logger && Dormio::$logger->log("SET {$this->_entity}->{$field}: {$value}", LOG_DEBUG);
		$this->_updated[$field] = $value;
	}
	
	/**
	 * Magic getter to maintain compatibility with older version
	 * Enter description here ...
	 * @param string $field
	 * @return mixed
	 */
	function __get($field) {
		$spec = $this->_entity->getField($field);
		//var_dump($spec);
		if($spec['is_field']) {
			if(isset($spec['entity'])) { // foreignkey or forward onetoone
				return $this->getRelatedObject($field, $spec);
			} else { // standard field
				return $this->getFieldValue($field);
			}
		}
		
		// related field
		return $this->getRelated($field);
	}
	
	/**
	 * Magic setter to maintain compatibility with older version
	 * @param string $field
	 * @param mixed $value
	 */
	function __set($field, $value) {
		
		$spec = $this->_entity->getField($field);
		
		if($spec['is_field']) {
			$this->setFieldValue($field, $value);
			return;
		}
		
		throw new Dormio_Exception("Unable to set field [{$field}] on {$this->_entity}");
	}
	
	/**
	 * Related objects with a field on the current entity
	 * @param string $field
	 * @param multitype:string $spec
	 * @return Dormio_Object
	 */
	function getRelatedObject($field, $spec) {
		if(isset($this->_related_objects[$field])) {
			$obj = $this->_related_objects[$field];
		} else {
			$entity = $this->_dormio->config->getEntity($spec['entity']);
			$obj = $this->_dormio->getObjectFromEntity($entity);
			$this->_related_objects[$field] = $obj;
		}

		if(isset($this->_data[$field . "__pk"])) { 
			//var_dump($spec);
			$pk = $this->_data[$field . "__pk"];
			Dormio::$logger && Dormio::$logger->log("Eager loading field {$field} ({$pk})");
			if($obj->ident() != $pk) {
				$related = Dormio::getRelatedData($this->_data, $field);
				//var_dump($related);
				$obj->setData($related);
			}
		} else {
			Dormio::$logger && Dormio::$logger->log("Lazy loading field {$field}");
			$pk = $this->getFieldValue($field, false);
			if($obj->ident() != $pk) {
				//var_dump("P1", $field, $obj->ident(), $pk);
				$obj->setPrimaryKey($pk);
			}
		}
		
		return $obj;
	}
	
	/**
	 * Return a manager for a related entity
	 * @param string $field
	 * @return Dormio_Manager_Related|Dormio_Object
	 */
	function getRelated($field) {
		$spec = $this->_entity->getField($field);
		
		if($spec['type'] == 'onetoone' && array_key_exists("{$field}__{$spec['local_field']}", $this->_data)) {
			Dormio::$logger && Dormio::$logger->log("Trying to eager hydrate field {$field}");
			$obj = $this->getRelatedObject($field, $spec);
			// if it is a new object set the remote field
			if(!$obj->ident()) {
				$obj->setFieldValue($spec['remote_field'], $this->getFieldValue($spec['local_field']));
			}
			return $obj;
		}
		
		// use cached if possible
		if(isset($this->_related_managers[$field])) {
			list($manager, $bound_field) = $this->_related_managers[$field];
		} else {
			$manager = $this->_dormio->getRelatedManager($this->_entity, $field);
			$bound_field = (isset($spec['local_field'])) ? $spec['local_field'] : 'pk';
			$this->_related_managers[$field] = array($manager, $bound_field);
		}
		
		// update with current bound value
		
		$manager->setBoundId($this->getFieldValue($bound_field));
		if($spec['type'] == 'onetoone') {
			return $manager->getObject();
		} else {
			
			return $manager;
		}
	}
	
	function getManager($field) {
		$spec = $this->_entity->getField($field);
		if(!isset($spec['entity'])) throw new Dormio_Exception("Field {$field} is not related to {$this->_entity}");
		return $this->_dormio->getManager($spec['entity']);
	}
	
	/**
	 * Get a manager for the current object
	 * 
	 * @return Dormio_Manager
	 */
	function getObjects() {
		return $this->_dormio->getManager($this->_entity->name);
	}
	
	/**
	 * New accessor for related types
	 * @param string $field
	 * @return Dormio_Manager_Related|Dormio_Object
	 * @todo Needs to return related objects as well
	 */
	function related($field) {
		return $this->getRelated($field);
	}
	
	function getDormio() {
		return $this->_dormio;
	}
	
	function getEntity() {
		return $this->_entity;
	}
	
	/* ArrayAccess */
	
	function offsetExists($offset) {
		return (isset($this->_data[$offset]) || isset($this->_updated[$offset]));
	}
	
	function offsetGet($offset) {
		return $this->getFieldValue($offset);
	}
	
	function offsetSet($offset, $value) {
		return $this->setFieldValue($offset, $value);
	}
	
	function offsetUnset($offset) {
		unset($this->_data[$offset], $this->_updated[$offset]);
	}
	
	/* Countable */
	
	function count() {
		return count($this->data);
	}
	
	/**
	 * String represention of the current object
	 * Override in subclass to customise
	 * @return string
	 */
	function display() {
		if(!isset($this->_entity)) return "[Unbound " . get_class($this) . "]";
		if(isset($this->_data['pk'])) {
			$display = $this->_entity->getMeta('display_field');
			if($display) {
				return $this->getFieldValue($display);
			}
			return "[{$this->_entity->getMeta('verbose')} {$this->_data['pk']}]";
		} else {
			return "[New {$this->_entity->getMeta('verbose')}]";
		}
	}
	
	/**
	 * Return a nice string representation
	 * @return string
	 */
	function __toString() {
		try {
			return $this->display();
		} catch(Exception $e) {
			return "Object display error: {$e->getMessage()}";
		}
	}
}
