<?
require_once('simpletest/autorun.php');
require_once('bantam_bootstrap.php');


class TestOfFactory extends UnitTestCase {
  function testDefault() {
    $dormio = Dormio_Factory::instance();
    $this->assertIsA($dormio, 'Dormio_Factory');
  }
  
  function testBad() {
    try {
      $dormio = Dormio_Factory::instance('rubbish');
      $this->fail();
    } catch(Exception $e) {
      $this->assertEqual($e->getMessage(), "Missing required value for 'dormio.rubbish'");
    }
  }
}