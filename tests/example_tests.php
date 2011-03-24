<?php
require_once('simpletest/autorun.php');
define('TEST_PATH', dirname(__FILE__));
define('DORMIO_PATH', realpath(TEST_PATH . '/../'));

require_once(DORMIO_PATH . '/classes/dormio/autoload.php');
Dormio_Autoload::register();

require_once(TEST_PATH . '/classes/mockpdo.php');
require_once(TEST_PATH . '/../examples/models.php');

class TestOfExamples extends UnitTestCase{

  /**
  * Examples from the queryset doc - only care if they compile
  */
  function testQueryset() {
    $comments = new Dormio_Queryset('Comment');
    $blogs = new Dormio_Queryset('Blog');
    
    $set = $blogs->filter('author__profile_set__fav_colour', 'IN', array('red', 'green'));
    
  }
  
  function testUsage() {
    ob_start();
    $this->assertTrue(include(TEST_PATH . '/../examples/usage.php'));
    ob_end_clean();
  }
  
  function testREADMESchema() {
    $pdo = new PDO('sqlite::memory:');
    $meta = Dormio_Meta::get('Blog');
    $sf = Dormio_Schema::factory('sqlite', $meta->schema());
    $sf->batchExecute($pdo, $sf->createTable());
  }
}
?>