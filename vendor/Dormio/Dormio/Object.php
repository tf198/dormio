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
	public $dormio;
	
	/**
	 * @var Dormio_Config_Entity
	 */
	public $_entity;
	
	function load($id) {
		$this->dormio->load($this, $id);
	}

	function save() {
		return $this->dormio->save($this);
	}
	
	function delete() {
		return $this->dormio->delete($this);
	}
	
	function setValues($arr) {
		foreach($arr as $key=>$value) {
			$this->$key = $value;
		}
	}
	
	function related($field) {
		$key = "{$field}_manager";
		if(!isset($this->$key)) {
			$this->$key = $this->dormio->getRelated($this, $field);
		}
		return $this->$key;
	}
	
	/**
	 * Bit of *magic* to bind related types as required
	 * @param string $field
	 */
	function __get($field) {
		if($this->_entity->isField($field)) {
			$spec = $this->_entity->getField($field);
			// lazy loading
			if($spec['is_field']) {
				$this->load($this->pk);
				return $this->$field;
			}
		}
		// assume it is a related field and allow exceptions to get thrown
		return $this->related($field);
	}
	
	function __toString() {
		if(!isset($this->_entity)) return "[Unbound " . get_class($this) . "]";
		return ($this->pk) ? "[{$this->_entity->name} {$this->pk}]" : "[New {$this->_entity->name}]";
	}
}
