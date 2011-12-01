<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');


class TestOfFactory extends UnitTestCase {
  public $config = array(
    'default' => array(	'connection' => 'sqlite::memory:' ),
    'baddriver' => array(	'connection' => 'rubbish:driver' ),
    'noconnect' => array(	'connection' => 'mysql:rubbishserver' ),
  );

  function setUp() {
    $this->pdo = new Dormio_Logging_PDO('sqlite::memory:');
    $this->factory = new Dormio_Factory($this->pdo);
  }

  function testDefaultFactory() {
    $this->assertIsA($this->factory->get('Blog'), 'Blog');
    $this->assertIsA($this->factory->manager('Blog'), 'Dormio_Manager');
  }
  
  public function testDefaultConnection() {
    $default=Dormio_Factory::PDO($this->config['default']);
		$this->assertIsA($default, 'PDO');
		$this->assertEqual($default->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
	}
	
	public function testBadConnections() {
    global $config;
		try {
			$pdo=Dormio_Factory::PDO($this->config['baddriver']);
			$this->fail();
		} catch(Exception $e) {
			$this->assertIsA($e, 'PDOException');
			$this->assertEqual($e->getMessage(), "No driver available for rubbish");
		}
		if(isset($GLOBALS['_run_long_tests'])) {
			try {
				$pdo=Dormio_Factory::PDO($this->config['noconnect']);
				$this->fail();
			} catch(Exception $e) {
				$this->assertIsA($e, 'PDOException');
			}
		}
	}
}
?>