<?php
class Dormio_Admin {
	
	/**
	 * Dormio object
	 * @var Dormio
	 */
	public $dormio;
	
	function __construct($dormio) {
		$this->dormio = $dormio;
	}
	
	function syncdb($entities=null) {
		$this->dormio->config->generateAutoEntities();
		
		// default to everything
		if($entities === null) {
			$entities = $this->dormio->config->getEntities();
		}
		
		// TODO: get a list of current tables
		$stmt = $this->dormio->pdo->query($this->dormio->dialect->tableNames());
		$current_tables = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		
		foreach($entities as $entity_name) {
			
			$entity = $this->dormio->config->getEntity($entity_name);
			
			if(array_search($entity->table, $current_tables) !== false) {
				Dormio::$logger && Dormio::$logger->log("Skipping table for entity {$entity_name}");
				continue;
			}
			
			Dormio::$logger && Dormio::$logger->log("Creating table for {$entity_name}", LOG_INFO);
			// get the schema array
			
			$schema = Dormio_Schema::fromEntity($entity);
			
			// get a factory
			$sf = Dormio_Schema::factory('sqlite', $schema);
			
			// create the tables
			$sql = $sf->createSQL();
			$sf->batchExecute($this->dormio->pdo, $sql);
		}
	}
}