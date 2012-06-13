<?php
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
		return $this->dormio->bindRelated($this, $field);
	}
	
	function __toString() {
		if(!isset($this->_entity)) return "[Unbound " . get_class($this) . "]";
		return ($this->pk) ? "[{$this->_entity->name} {$this->pk}]" : "[New {$this->_entity->name}]";
	}
}
