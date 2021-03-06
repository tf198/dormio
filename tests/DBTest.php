<?php
abstract class Dormio_DBTest extends PHPUnit_Framework_TestCase {

	/**
	 *
	 * @var Dormio_Logging_PDO
	 */
	public $pdo;

	/**
	 *
	 * @var Dormio
	 */
	public $dormio;

	/**
	 *
	 * @var Dormio_Config
	 */
	public $config;

	function setUp() {
		$this->pdo = new Dormio_Logging_PDO('sqlite::memory:');
		$this->config = new Dormio_Config;
		$this->config->addEntities($GLOBALS['test_entities']);
		$this->dormio = new Dormio($this->pdo, $this->config);
	}

	function load($name) {
		$lines = file(TEST_PATH . '/' . $name);
		if(!$lines) throw new Exception('Failed to load file: ' . $name);
		foreach($lines as $sql) {
			$sql = trim($sql);
			try {
				if($sql) $this->pdo->exec($sql);
			} catch(PDOException $e) {
				throw new Exception("Failed to execute [{$sql}]\n{$e}");
			}
		}
		$this->pdo->stack = array();
	}
	
	function clearSQL() {
		$this->pdo->clear();
	}

	function assertQueryset($qs, $field, $expected) {
		$i=0;
		foreach($qs as $obj) {
			if(is_array($obj)) {
				$this->assertEquals($expected[$i++], $obj[$field]);
			} else {
				$this->assertEquals($expected[$i++], $obj->$field);
			}
		}
		$this->assertEquals(count($expected), $i, 'Expected '.count($expected)." results, got {$i}");
	}
	
	function assertQueryCount($expected) {
		$this->assertEquals($expected, $this->pdo->count());
		$this->pdo->clear();
	}

	function dumpSQL() {
		$sql = $this->pdo->digest();
		//var_dump($sql);
		//$params = array();
		//foreach($sql[1] as $set) $params[] = "[" . implode(', ', $set) . "]";
		echo $sql[0] . " (" . implode(', ', $sql[1]) . ")\n";
	}

	function dumpAllSQL() {
		while($this->pdo->stack) $this->dumpSQL();
	}

	function dumpData() {
		$stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
		foreach($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $table) {
			echo "TABLE: {$table}\n";
			$stmt = $this->pdo->query("SELECT * FROM {$table}");
			foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $key=>$columns) {
				echo "{$key}";
				foreach($columns as $key=>$value) echo "\t{$key}: {$value}";
				echo "\n";
			}
		}
	}
	
	function assertSQL() {
		$params = func_get_args();
		$sql = array_shift($params);
		
		$executed = $this->pdo->digest();
		
		if(substr($sql, -3) == '...') {
			$this->assertStringStartsWith(substr($sql, 0, -3), $executed[0]);
		} else {
			$this->assertEquals($sql, $executed[0]);
		}
		
		$this->assertEquals($params, $executed[1], "Params passed to SQL differ");
	}

	function assertDigestedAll() {
		$c = $this->pdo->count();
		if($c == 0) {
			$this->assertTrue(true);	
		} else {
			$this->fail("{$c} undigested queries: " . $this->pdo->stack[0][0]);
		}
	}
	
	function assertStatementCount($expected) {
		$this->assertEquals($expected, $this->pdo->statementsPrepared());
	}
	
	function assertThrows($expected, $callable) {
		$params = array_slice(func_get_args(), 2);
		try {
			call_user_func_array($callable, $params);
			$this->fail('An expected exception was not thrown');
		} catch(Exception $e) {
			$output = get_class($e) . ': ' . $e->getMessage();
			$this->assertStringStartsWith($expected, $output);
		}
	}
}