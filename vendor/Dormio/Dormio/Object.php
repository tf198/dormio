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
	 * @var mixed
	 */
	public $pk;
	
	function __construct(Dormio $dormio, Dormio_Config_Entity $entity, $id=null) {
		$this->_dormio = $dormio;
		$this->_entity = $entity;
		$this->pk = $id;
	}
	
	function load($id) {
		if(!$id) throw new Dormio_Exception("No primary key given");
		$data = $this->_dormio->selectEntity($this->_entity, $id);
		$this->setData($data);
	}

	function save() {
		if($this->pk) {
			$this->_dormio->updateEntity($this->_entity, $this->pk, $this->_updated);
		} else {
			$this->pk = $this->_dormio->insertEntity($this->_entity, $this->_updated);
		}
	}
	
	function delete() {
		$this->_dormio->deleteEntity($this->_entity. $this->pk);
		$this->pk = null;
	}
	
	function setValues($arr) {
		foreach($arr as $key=>$value) {
			$this->setFieldValue($key, $value);
		}
	}
	
	function setData($data) {
		$this->_data = $data;
		$this->_updated = array();
		$this->pk = $this->getFieldValue('pk');
	}
	
	function setPrimaryKey($id) {
		$this->_data = array();
		$this->_updated = array();
		$this->pk = $id;
	}
	
	function getFieldValue($field, $throw=true) {
		
		// changed values
		if(isset($this->_updated[$field])) {
			return $this->_updated[$field];
		}
		
		// from database
		if(isset($this->_data[$field])) {
			return $this->_data[$field];
		}
		
		// not yet hydrated
		if($this->_entity->isField($field)) {
			if(!$this->pk) throw new Dormio_Exception("No primary key set for {$this->_entity}");
			$this->load($this->pk);
			return $this->_data[$field];
		}
		
		if($throw) throw new Dormio_Exception("{$this->_entity} has no field {$field}");
		return null;
	}
	
	function setFieldValue($field, $value) {
		$this->_updated[$field] = $value;
	}
	
	function __get($field) {
		$spec = $this->_entity->getField($field);
		//var_dump($spec);
		if($spec['is_field']) {
			if(isset($spec['entity'])) { // foreignkey or onetoone
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
	
	function getRelatedObject($field, $spec) {
		if(isset($this->_related_objects[$field])) {
			$obj = $this->_related_objects[$field];
		} else {
			$entity = $this->_dormio->config->getEntity($spec['entity']);
			$obj = $this->_dormio->getObjectFromEntity($entity);
			$this->_related_objects[$field] = $obj;
		}
		
		if($obj->pk != $this->_data[$field]) {
			$mapper = $this->_data->getChildMapper($field, $spec['remote_field']);
			$obj->setData($mapper);
		}
		return $obj;
	}
	
	function getRelated($field) {
		// use cached if possible
		if(isset($this->_related_managers[$field])) {
			list($manager, $bound_field) = $this->_related_managers[$field];
		} else {
			$manager = $this->_dormio->getRelatedManager($this->_entity, $field);
			$spec = $this->_entity->getField($field);
			$bound_field = (isset($spec['local_field'])) ? $spec['local_field'] : 'pk';
			$this->_related_managers[$field] = array($manager, $bound_field);
		}
		
		// update with current bound value
		$manager->setBoundId($this->getFieldValue($bound_field));
		
		if($manager instanceof Dormio_Manager_OneToOne) {
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
		return ($this->pk) ? "[{$this->_entity->name} {$this->pk}]" : "[New {$this->_entity->name}]";
	}
	
	function __toString() {
		try {
			return $this->display();
		} catch(Exception $e) {
			return "Object display error: {$e->getMessage()}";
		}
	}
}
