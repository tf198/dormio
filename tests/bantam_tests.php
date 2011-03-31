<?php
require_once('simpletest/autorun.php');
require_once('bantam/bantam.php');

// hook into bantam autoloader
Bantam::instance()->addPaths(array(dirname(__FILE__) . '../../'));

class TestOfBantam extends UnitTestCase {
  function testInstance() {
    $pdo = Dormio_Bantam::instance();
    $this->assertIsA($pdo, 'PDO');
  }
  
  function testFactory() {
    $dormio = Dormio_Bantam::factory();
    $this->assertIsA($dormio, 'Dormio_Factory');
  }
  
  function testConfig() {
    $dormio = Dormio_Bantam::factory(); // check it is loaded
    $config = Dormio_Meta::config('forms');
    $this->assertEqual($config['string'], 'Phorms_Fields_CharField'); // from dormio
    $this->assertEqual($config['boolean'], 'Bantam_Field_YesNo'); // overriden by ap
    $this->assertEqual($config['custom'], 'Bantam_Field_Custom'); // new in app
  }
}
?>