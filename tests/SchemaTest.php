<?php

class Dormio_SchemaTest extends PHPUnit_Framework_TestCase{

	private $client_v1_config =  array (
		'table' => 'client',
		'fields' => array(
			'pk' => array('type' => 'ident', 'db_column' => 'ClientId'),
			'ClientName' => array('type' => 'string', 'db_column' => 'ClientName'),
			'ClientAge' => array('type' => 'integer', 'db_column' => 'ClientAge')
		)
	);
	private $client_v2_config = array(
		'table' => 'detailed_client',
		'fields' => array(
			'pk' => array('type' => 'ident', 'db_column' => 'ClientId'),
			'NickName' => array('type' => 'string', 'null_ok' => true),
			'ClientName' => array('type' => 'integer'),
			'AgeAtStartOfYear' => array('type' => 'integer', 'null_ok' => true),
			'ClientDOB' => array('type' => 'timestamp', 'null_ok' => true),
			'Notes' => array('type' => 'text', 'null_ok' => true)
		),
	);

	private $drivers = array('mysql', 'sqlite');

	private $primitives = array(
		array(array('type' => 'ident'),
			'SERIAL', 'INTEGER PRIMARY KEY AUTOINCREMENT'),
		array(array('type' => 'integer'),
			'INTEGER(32)', 'INTEGER'),
		array(array('type' => 'integer', 'size' => 8),
			'INTEGER(8)', 'INTEGER'),
		array(array('type' => 'integer', 'unsigned' => true),
			'INTEGER(32) UNSIGNED', 'INTEGER'),
		array(array('type' => 'integer', 'size' => 8, 'unsigned' => true),
			'INTEGER(8) UNSIGNED', 'INTEGER'),
		array(array('type' => 'float'),
			'FLOAT', 'REAL'),
		array(array('type' => 'float', 'unsigned' => true),
			'FLOAT UNSIGNED', 'REAL'),
		array(array('type' => 'double'),
			'DOUBLE', 'REAL'),
		array(array('type' => 'double', 'unsigned' => true),
			'DOUBLE UNSIGNED', 'REAL'),
		array(array('type' => 'boolean'),
			'TINYINT(1)', 'INTEGER'),
		array(array('type' => 'boolean', 'unsigned' => true),
			'TINYINT(1)', 'INTEGER'),
		array(array('type' => 'string'),
			'VARCHAR(255)', 'TEXT'),
		array(array('type' => 'string', 'max_length' => 32),
			'VARCHAR(32)', 'TEXT'),
		array(array('type' => 'text'),
			'TEXT', 'TEXT'),
		array(array('type' => 'text', 'size' => 4096),
			'TEXT', 'TEXT'),
		array(array('type' => 'timestamp'),
			'TIMESTAMP', 'INTEGER'),
	);

	function setUp() {
		$this->client_v1 = Dormio_Schema::fromConfig($this->client_v1_config);
		$this->client_v2 = Dormio_Schema::fromConfig($this->client_v2_config);
	}
	
	/*
	 function testSchema() {
	$this->assertEquals(array_keys($this->clients['columns']), array('pk', 'ClientName', 'ClientAge'));
	}

	public function testFactory() {
	// nonexistent driver
	try {
	$schema = Dormio_Schema::factory('rubbish', $this->clients);
	$this->fail('Should have thrown an exception');
	} catch (Exception $e) {
	$this->assertIsA($e, 'Dormio_Schema_Exception');
	}
	// external driver
	try {
	$schema = Dormio_Schema::factory('sqlite', $this->clients);
	$this->pass('Loaded correct driver');
	} catch (Exception $e) {
	$this->fail('Should have found the sqlite driver file');
	}

	// test multiple contruct methods
	$this->assertTrue(Dormio_Schema::factory('sqlite', 'Blog'));
	$this->assertTrue(Dormio_Schema::factory('sqlite', Dormio_Meta::get('Blog')));
	$this->assertTrue(Dormio_Schema::factory('sqlite', new Blog(new PDO('sqlite::memory:'))));
	}
	*/
	public function testTableOps() {
		$schema = Dormio_Schema::factory('sqlite', $this->client_v1);
		$schema->sql = array();
		$schema->createTable();
		$this->assertEquals($schema->sql[0], 'CREATE TABLE "client" ("ClientId" INTEGER PRIMARY KEY AUTOINCREMENT, "ClientName" TEXT NOT NULL, "ClientAge" INTEGER NOT NULL)');
		$schema->sql = array();
		$schema->renameTable('new_client');
		$this->assertEquals($schema->sql[0], 'ALTER TABLE "client" RENAME TO "new_client"');
		$schema->sql = array();
		$schema->dropTable();
		$this->assertEquals($schema->sql[0], 'DROP TABLE IF EXISTS "new_client"');
	}

	public function testColumnOps() {
		$schema = Dormio_Schema::factory('mysql', $this->client_v1);
		$schema->sql = array();
		$schema->addColumn('Address', array('type' => 'string'));
		$this->assertEquals($schema->sql[0], 'ALTER TABLE `client` ADD COLUMN `Address` VARCHAR(255) NOT NULL');
	}

	public function testIndexOps() {
		$schema = Dormio_Schema::factory('mysql', $this->client_v1);
		$schema->sql = array();
		$schema->addIndex('TestIndex', array('ClientName' => true, 'Address' => false));
		$this->assertEquals($schema->sql[0], 'CREATE INDEX `client_TestIndex` ON `client` (`ClientName` ASC, `Address` DESC)');
		$schema->sql = array();
		$schema->dropIndex('TestIndex');
		$this->assertEquals($schema->sql[0], 'DROP INDEX `client_TestIndex` ON `client`');
	}

	public function testBadTypeData() {
		$schema = Dormio_Schema::factory('sqlite', $this->client_v1);
		try {
			$schema->getType(null);
			$this->fail('Should have thrown exception');
		} catch (Exception $e) {

		}
		try {
			$schema->getType(array(1, 2, 3));
			$this->fail('Should have thrown exception');
		} catch (Exception $e) {

		}
		try {
			$schema->getType(array('type' => 'rubbish'));
			$this->fail('Should have thrown bad type exception');
		} catch (Exception $e) {

		}
		$this->assertTrue(true, 'Threw all required exceptions');
	}

	public function testPrimitives() {
		for ($i = 0; $i < count($this->drivers); $i++) {
			$schema = Dormio_Schema::factory($this->drivers[$i], $this->client_v1);
			for ($j = 0, $c = count($this->primitives); $j < $c; $j++) {
				$this->assertEquals($schema->getPrimitive($this->primitives[$j][0]), $this->primitives[$j][$i + 1]);
			}
		}
	}

	public function testMysqlUpgradeRoute() {
		$schema = Dormio_Schema::factory('mysql', $this->client_v1);

		//$script = fopen(dirname(__FILE__) . '/output/upgrade_mysql.sql', 'w');
		$sql = $schema->createSQL();
		$sql = array_merge($sql, $schema->upgradeSQL($this->client_v2));
		$post_upgrade = $schema->createSQL();

		$schema2 = Dormio_Schema::factory('mysql', $this->client_v2);
		$target = $schema2->createSQL();
		$sql[] = '-- ' . $post_upgrade[0];
		$sql[] = '-- ' . $target[0];

		$this->assertEquals($post_upgrade, $target);
	}

	public function testSqliteUpgradeRoute() {
		$pdo = new PDO('sqlite::memory:');
		$schema = Dormio_Schema::factory('sqlite', $this->client_v1);
		$schema->batchExecute($pdo, $schema->createSQL());
		// create some test data
		$pdo->exec("INSERT INTO client (ClientName, ClientAge) VALUES ('Tris', 29)");
		// upgrade the schema
		$sql = $schema->upgradeSQL($this->client_v2);
		$schema->batchExecute($pdo, $sql);

		$this->_validateUpgrade($pdo);

		// check the data got upgraded
		$result = $pdo->query("SELECT * FROM detailed_client WHERE ClientId=1")->fetch(PDO::FETCH_ASSOC);
		$this->assertEquals($result['clientname'], 'Tris');
	}

	private function _validateUpgrade($pdo) {
		// create a brand new create statement and compare it to the stored SQLITE one
		$schema2 = Dormio_Schema::factory('sqlite', $this->client_v2);
		$sql2 = $schema2->createSQL();
		$result = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND tbl_name='detailed_client'")->fetch(PDO::FETCH_NUM);
		$this->assertEquals($result[0], $sql2[0]);
	}

	public function testTypes() {
		$schema = Dormio_Schema::factory('sqlite', $this->client_v1);
		// Attributes
		$this->assertEquals($schema->getType(array('type' => 'integer', 'null_ok' => true)), 'INTEGER');
		$this->assertEquals($schema->getType(array('type' => 'integer', 'unique' => true)), 'INTEGER NOT NULL UNIQUE');
		$this->assertEquals($schema->getType(array('type' => 'integer', 'default' => 9, 'null_ok' => true)), 'INTEGER DEFAULT 9');
		$this->assertEquals($schema->getType(array('type' => 'integer', 'default' => 9)), "INTEGER NOT NULL DEFAULT 9");
		// Value quoting
		$this->assertEquals($schema->quoteValue(9, 'integer'), "9");
		$this->assertEquals($schema->quoteValue(9, 'string'), "'9'");
		$this->assertEquals($schema->quoteValue(9, 'text'), "'9'");
		$this->assertEquals($schema->quoteValue("TEST 'WITH' QUOTES", 'text'), "'TEST ''WITH'' QUOTES'");
	}

	public function testInsertAfter() {
		$schema = Dormio_Schema::factory('sqlite', $this->client_v1);

		$orig = array('one' => 1, 'two' => 2, 'three' => 3);
		$this->assertEquals(implode(' ', $orig), '1 2 3');
		// insert at end
		$schema->insertAfter($orig, 'four', 4);
		$this->assertEquals(implode(' ', $orig), '1 2 3 4');
		// insert at start;
		$schema->insertAfter($orig, 'five', 5, 0);
		$this->assertEquals(implode(' ', $orig), '5 1 2 3 4');
		// insert in middle
		$this->assertTrue($schema->insertAfter($orig, 'six', 6, 'two'));
		$this->assertEquals(implode(' ', $orig), '5 1 2 6 3 4');
	}

	function testCodePath() {
		$pdo = new PDO('sqlite::memory:');

		$sf = Dormio_Schema::factory('sqlite', $this->client_v1);
		$sf->createSQL();
		$sf->commitUpgrade($pdo);

		$pdo->exec("INSERT INTO client (ClientName, ClientAge) VALUES ('Tris', 29)");

		$upgrade = dirname(__FILE__) . '/output/upgrade_sqlite.php';
		file_put_contents($upgrade, $sf->upgradePHP($this->client_v2));
		include $upgrade;

		$this->_validateUpgrade($pdo);
		$result = $pdo->query("SELECT * FROM detailed_client WHERE ClientId=1")->fetch(PDO::FETCH_ASSOC);
		$this->assertEquals($result['clientname'], 'Tris');
	}

}

?>
