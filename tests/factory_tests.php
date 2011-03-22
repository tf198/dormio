<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');


class TestOfFactory extends UnitTestCase {
  function setUp() {
    $this->pdo = new MockPDO('sqlite::memory:');
    $this->factory = new Dormio_Factory($this->pdo);
  }

  function testDefault() {
    $this->assertIsA($this->factory->get('Blog'), 'Blog');
    $this->assertIsA($this->factory->manager('Blog'), 'Dormio_Manager');
  }
}