<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class AllTests extends TestSuite {
	function AllTests() {
		parent::__construct();
		$path = dirname(__FILE__);
		$this->addFile("{$path}/meta_tests.php");
		$this->addFile("{$path}/schema_tests.php");
    $this->addFile("{$path}/queryset_tests.php");
    $this->addFile("{$path}/model_tests.php");
    $this->addFile("{$path}/manager_tests.php");
    $this->addFile("{$path}/factory_tests.php");
    $this->addFile("{$path}/dialect_tests.php");
  }
}
?>
