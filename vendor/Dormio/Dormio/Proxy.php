<?php
class Dormio_Proxy {

	function __construct($obj, $entity, $dormio) {
		$this->obj = $obj;
		$this->entity = $entity;
		$this->dormio = $dormio;
		$this->id = null;
		$this->query = new Dormio_Query($entity, $this->dormio->dialect);
	}

	function load($id) {
		$q = $this->query->filter('pk', '=', $id);
		$sql = $q->select();
		$stmt = $this->dormio->execute($sql[0], $sql[1]);
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$this->hydrate($q->mapArray($data[0]));

	}

	function hydrate($data) {
		$this->data = $data;
		foreach($this->entity->getFields() as $field=>$spec) {
			if(!isset($this->data[$field])) $partial = true; // TODO: work out what to do here
			$this->obj->$field = $this->data[$field];
		}
		$this->id = $data['pk'];
	}

	function save() {
		if($this->id) {
			$this->update();
		} else {
			$this->insert();
			$this->id = $this->dormio->pdo->lastInsertId();
			$this->obj->pk = $this->id;
		}
	}

	function insert() {
		$params = array();
		foreach($this->entity->getFields() as $name=>$spec) {
			if(isset($spec['is_field']) && $spec['is_field'] && isset($this->obj->$name)) {
				$params[$name] = $this->obj->$name;
			}
		}
		if(!$params) throw new Dormio_Exception("No values set for entity [{$this->entity->name}]");

		$sql = $this->dormio->dialect->insert(array('from' => $this->entity->table), array_keys($params));
		$this->dormio->execute($sql, array_values($params));
	}

	function update() {
		$params = array();
		foreach($this->entity->getFields() as $name=>$spec) {
			if($spec['is_field'] && isset($this->obj->$name)) {
				if($name == 'pk') continue;
				if(isset($this->data[$name]) && $this->data[$name]==$obj->$name) continue;
				$params[$name] = $this->obj->$name;
			}
		}
		if(!$params) return;

		$query = array(
			'from' => $this->entity->table,
			'where' => array("{$this->entity->pk['db_column']} = ?")
			);

		$sql = $this->dormio->dialect->update($query, array_keys($params));

		$values = array_keys($params);
		array_unshift($values, $this->id);
		$this->dormio->execute($sql, $values);
	}
}
