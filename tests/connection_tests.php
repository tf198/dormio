<?
require_once('simpletest/autorun.php');
require_once('bantam_bootstrap.php');


class TestOfPdoConnection extends UnitTestCase {
	public function testDefaultConnection() {
		$default=Dormio_Connection::instance();
		$this->assertIsA($default, 'PDO');
		$this->assertEqual($default->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
	}
	
	public function testBadConnections() {
		try {
			$pdo=Dormio_Connection::instance('missing');
			$this->fail();
		} catch(Exception $e) {
			$this->assertIsA($e, 'Exception');
			$this->assertEqual($e->getMessage(), "Missing required value for 'dormio.missing'");
		}
		try {
			$pdo=Dormio_Connection::instance('baddriver');
			$this->fail();
		} catch(Exception $e) {
			$this->assertIsA($e, 'PDOException');
			$this->assertEqual($e->getMessage(), "No driver available for rubbish");
		}
		if(isset($GLOBALS['_run_long_tests'])) {
			try {
				$pdo=Dormio_Connection::instance('noconnect');
				$this->fail();
			} catch(Exception $e) {
				$this->assertIsA($e, 'PDOException');
			}
		}
	}
}