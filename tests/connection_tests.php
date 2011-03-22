<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

global $config;
include(TEST_PATH . '/config/dormio.php');

class TestOfPdoConnection extends UnitTestCase {
	public function testDefaultConnection() {
    global $config;
    $default=Dormio_Connection::instance($config['default']);
		$this->assertIsA($default, 'PDO');
		$this->assertEqual($default->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
	}
	
	public function testBadConnections() {
    global $config;
		try {
			$pdo=Dormio_Connection::instance($config['baddriver']);
			$this->fail();
		} catch(Exception $e) {
			$this->assertIsA($e, 'PDOException');
			$this->assertEqual($e->getMessage(), "No driver available for rubbish");
		}
		if(isset($GLOBALS['_run_long_tests'])) {
			try {
				$pdo=Dormio_Connection::instance($config['noconnect']);
				$this->fail();
			} catch(Exception $e) {
				$this->assertIsA($e, 'PDOException');
			}
		}
	}
}