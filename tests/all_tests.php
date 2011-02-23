<?
require_once('simpletest/autorun.php');
require_once('bantam_bootstrap.php');

$mod_path = realpath(dirname(__FILE__) . '/..');
Bantam::instance()->addPaths(array($mod_path));

class AllTests extends TestSuite {
	function AllTests() {
		parent::__construct();
		$path = dirname(__FILE__);
		$this->addFile("{$path}/connection_tests.php");
    $this->addFile("{$path}/meta_tests.php");
		$this->addFile("{$path}/schema_tests.php");
    $this->addFile("{$path}/queryset_tests.php");
    $this->addFile("{$path}/model_tests.php");
    $this->addFile("{$path}/manager_tests.php");
   }
}
?>
