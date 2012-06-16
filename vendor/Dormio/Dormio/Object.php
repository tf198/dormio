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
	
	public $_data = array();
	
	public $_updated = array();
	
	public $_related = array();
	
	public $_child_objects = array();
	
	public $_fields;
	
	public $pk;
	
	function bind(Dormio $dormio, Dormio_Config_Entity $entity) {
		$this->_dormio = $dormio;
		$this->_entity = $entity;
		
		$this->_fields = $this->_entity->getFields();
	}
	
	function isBound() {
		return isset($this->_dormio);
	}
	
	function load($id) {
		$this->_dormio->load($this, $id);
	}

	function save() {
		return $this->_dormio->save($this);
	}
	
	function delete() {
		return $this->_dormio->delete($this);
	}
	
	function setValues($arr) {
		foreach($arr as $key=>$value) {
			$this->setFieldValue($key, $value);
		}
	}
	
	function setData($data, $map=null) {
		$this->_data = $data;
		$this->pk = isset($this->_data['pk']) ? $this->_data['pk'] : null;
	}
	
	function getFieldValue($field, $throw=true) {
		if(isset($this->_data[$field])) {
			return $this->_data[$field];
		}
		if($throw) throw new Dormio_Exception("No value for field: {$field}");
		return null;
	}
	
	function setFieldValue($field, $value) {
		$this->_updated[$field] = $value;
	}
	
	function getUpdated() {
		return $this->_updated;
	}
	
	function __get($field) {
		// changed values
		if(isset($this->_updated[$field])) {
			return $this->_updated[$field];
		}
		
		$spec = $this->_entity->getField($field);
		//var_dump($spec);
		if($spec['is_field']) {
			if(isset($spec['entity'])) { // foreignkey or onetoone
				return $this->getRelatedChild($field, $spec);
			} else {
				return $this->getFieldValue($field);
			}
		}
		
		return $this->getRelated($field);
	}
	
	function getRelatedChild($field, $spec) {
		if(!isset($this->_child_objects[$field])) {
			$entity = $this->_dormio->config->getEntity($spec['entity']);
			$obj = $this->_dormio->getObjectFromEntity($entity);
			$mapper = $this->_data->getChildMapper($field);
			$obj->setData($mapper);
			$this->_child_objects[$field] = $obj;
		}
		return $this->_child_objects[$field];
	}
	
	function getRelated($field) {
		if(!isset($this->_related[$field])) {
			$this->_related[$field] = $this->_dormio->getRelated($this, $field);
		}
		return $this->_related[$field];
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
