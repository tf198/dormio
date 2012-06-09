<?php
class Dormio {
	/**
	 * Database object
	 * @var PDO
	 */
	public $pdo;
	
	/**
	 * Entity configuration
	 * @var Dormio_Config
	 */
	private $config;
	
	/**
	 * Dialect for the underlying database
	 * @var Dormio_Dialect
	 */
	public $dialect;
	
	public function __construct($pdo, $config) {
		$this->pdo = $pdo;
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->config = $config;
		$this->dialect = Dormio_Dialect::factory($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
	}
	
	function save($obj, $entity_name=null) {
		$this->proxy($obj, $entity_name)->save();
	}
	
	function load($obj, $id, $entity_name=null) {
		$this->proxy($obj, $entity_name)->load($id);
	}
	
	function proxy($obj, $entity_name=null) {
		if(!isset($obj->proxy)) {
			if(!$entity_name) $entity_name = get_class($obj);
			$entity = $this->config->getEntity($entity_name);
				
			$obj->proxy = new Dormio_Proxy($obj, $entity, $this);
		}
		return $obj->proxy;
	}
	
	function execute($sql, $params) {
		$stmt = $this->pdo->prepare($sql);
		$result = $stmt->execute($params);
		printf("SQL: %s (%s) [%d]\n", $sql, implode(', ', $params), $result);
		return $stmt;
	}
	
	function executeQuery($query) {
		$stmt = $this->pdo->prepare($query[0]);
		$stmt->execute($query[1]);
		return $stmt;
	}
}

class Dormio_Exception extends Exception {}