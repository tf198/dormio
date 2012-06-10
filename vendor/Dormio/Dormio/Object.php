<?php
class Dormio_Object {

	function load($id) {
		$this->dormio->load($id);
	}

	function save() {
		return $this->dormio->save($this);
	}
}
