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
	
	function syncDB($entities=null) {
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
	
	function loadFixture($file) {
		$json = file_get_contents($file);
		$data = json_decode($json, true);
		
		if($data==null) throw new Exception("Parse error for '{$file}'");
		
		$this->dormio->pdo->exec("PRAGMA ignore_check_constraints=1;");
		$this->dormio->pdo->beginTransaction();
		
		foreach($data as $entry) {
			$entity = $entry['entity'];
			unset($entry['entity']);
			
			$query = new Dormio_Query($this->dormio->config->getEntity($entity), $this->dormio->dialect);
			//$manager->create($entry)->save();
			$q = $query->insert($entry);
			
			// hack to use REPLACE instead of INSERT
			$sql = str_replace('INSERT', 'REPLACE', $q[0]);
			$stmt = $this->dormio->pdo->prepare($sql);
		
			$stmt->execute(array_values($entry));
		}
		
		$this->dormio->pdo->exec("PRAGMA ignore_check_constraints=0;");
		$this->dormio->pdo->commit();
		
		Dormio::$logger && Dormio::$logger->log($file . ": " . count($data));
		
		return count($data);
	}
}