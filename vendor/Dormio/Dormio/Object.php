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
 * Basic ORM class
 * @package Dormio
 *
 */
class Dormio_Object {

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
	private $_hydrated;
	
	function __construct(Dormio $dormio, Dormio_Config_Entity $entity, $id=null) {
		$this->_dormio = $dormio;
		$this->_entity = $entity;
		$this->_hydrated = false;
	}
	
	function load($id) {
		if(!$id) throw new Dormio_Exception("No primary key given");
		if($this->_updated) throw new Dormio_Exception("Unsaved data for {$this}");
		Dormio::$logger && Dormio::$logger->log("Load id {$id}");
		$data = $this->_dormio->selectEntity($this->_entity, $id);
		$this->setData($data);
		$this->_hydrated = true;
	}

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
		foreach($this->_updated as $key=>$value) $this->_data[$key] = $value;
		$this->_updated = array();
	}
	
	function ident() {
		return isset($this->_data['pk']) ? $this->_data['pk'] : null;
	}
	
	function delete() {
		return $this->_dormio->deleteEntity($this->_entity, $this->ident());
	}
	
	function setValues($arr) {
		foreach($arr as $key=>$value) {
			$this->setFieldValue($key, $value);
		}
	}
	
	function setData($data) {
		if(!isset($data['pk'])) throw new Dormio_Exception("Cannot set data without a primary key");
		if($this->_updated) throw new Dormio_Exception("Unsaved data for {$this}");
		$this->_data = $data;
		$this->_updated = array();
		$this->_hydrated = false;
	}
	
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
	
	function getFieldValue($field, $throw=true) {
		//Dormio::$logger && Dormio::$logger->log("getFieldValue {$this->_entity}->{$field}", LOG_DEBUG);
		
		// changed values
		if(isset($this->_updated[$field])) {
			return $this->_updated[$field];
		}
		
		// from database
		if(isset($this->_data[$field])) {
			$value = $this->_data[$field];
			//Dormio::$logger && Dormio::$logger->log("Got value {$value}");
			return $value;
		}
		
		// not yet hydrated
		if($this->_entity->isField($field)) {
			Dormio::$logger && Dormio::$logger->log("Need to rehydrate for field {$field}");
			$this->hydrate();
			$value = $this->_data[$field];
			Dormio::$logger && Dormio::$logger->log("Got value {$value}");
			return $value;
		}
		
		if($throw) throw new Dormio_Exception("{$this->_entity} has no field {$field}");
		return null;
	}
	
	function setFieldValue($field, $value) {
		if($field == 'pk') throw new Dormio_Exception("Unable to update primary key");
		Dormio::$logger && Dormio::$logger->log("SET {$this->_entity}->{$field}: {$value}", LOG_DEBUG);
		$this->_updated[$field] = $value;
	}
	
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
	 */
	function getRelatedObject($field, $spec) {
		if(isset($this->_related_objects[$field])) {
			$obj = $this->_related_objects[$field];
		} else {
			$entity = $this->_dormio->config->getEntity($spec['entity']);
			$obj = $this->_dormio->getObjectFromEntity($entity);
			$this->_related_objects[$field] = $obj;
		}

		if(isset($this->_data[$field . "__" . $spec['local_field']])) {
			//var_dump($spec);
			Dormio::$logger && Dormio::$logger->log("Eager loading field {$field}");
			$mapper = $this->_data->getChildMapper($field);
			if($obj->ident() != $mapper['pk']) {
				$obj->setData($mapper);
			}
		} else {
			Dormio::$logger && Dormio::$logger->log("Lazy loading field {$field}");
			if(!isset($this->_data[$field])) {
				$this->hydrate();
			}
			$pk =  $this->_data[$field];
			if($obj->ident() != $pk) {
				//var_dump("P1", $field, $obj->ident(), $pk);
				$obj->setPrimaryKey($pk);
			}
		}
		
		return $obj;
	}
	
	/**
	 * Return a manager for a related entity
	 * @param unknown_type $field
	 */
	function getRelated($field) {
		$spec = $this->_entity->getField($field);
		
		if($spec['type'] == 'onetoone' && isset($this->_data[$field . "__" . $spec['local_field']])) {
			//var_dump($spec);
			Dormio::$logger && Dormio::$logger->log("Trying to eager hydrate field {$field}");
			$obj = $this->getRelatedObject($field, $spec);
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
	
	function related($field) {
		return $this->getRelated($field);
	}
	
	function display() {
		if(!isset($this->_entity)) return "[Unbound " . get_class($this) . "]";
		return (isset($this->_data['pk'])) ? "[{$this->_entity->name} {$this->_data['pk']}]" : "[New {$this->_entity->name}]";
	}
	
	function __toString() {
		try {
			return $this->display();
		} catch(Exception $e) {
			return "Object display error: {$e->getMessage()}";
		}
	}
}
