<?php
class Dormio_Object {

	/**
	 * @var Dormio
	 */
	public $dormio;
	
	function load($id) {
		$this->dormio->load($id);
	}

	function save() {
		return $this->dormio->save($this);
	}
	
	/**
	 * Bit of *magic* to bind related types as required
	 * @param string $field
	 */
	function __get($field) {
		echo "MAGIC: {$this->_entity->name}->{$field}\n";
		return $this->dormio->bindRelated($this, $field);
	}
}
