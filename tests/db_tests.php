<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

abstract class TestOfDB extends UnitTestCase{
  function setUp() {
    $this->db = new MockPDO('sqlite::memory:');
    $this->load("sql/test_schema.sql");
    
    $this->pom = new Dormio_Factory($this->db);
  }
  
  function load($name) {
    $lines = file(TEST_PATH . '/' . $name);
    if(!$lines) throw new Exception('Failed to load file: ' . $name);
    foreach($lines as $sql) {
      try {
        if($sql) $this->db->exec($sql);
      } catch(PDOException $e) {
        throw new Exception("Failed to execute [{$sql}]\n{$e}");
      }
    }
    $this->db->stack = array();
  }
  
  function assertQueryset($qs, $field, $expected) {
    $i=0;
    foreach($qs as $obj) {
      $this->assertEqual($obj->__get($field), $expected[$i++]);
    }
    $this->assertEqual($i, count($expected), 'Expected '.count($expected)." results, got {$i}");
  }
  
  function dumpSQL() {
    var_dump($this->db->digest());
  }
  
  function assertSQL() {
    $params = func_get_args();
    $sql = array_shift($params);
    $this->assertEqual($this->db->digest(), array($sql, array($params)));
  }
  
  function assertDigestedAll() {
    $this->assertEqual($this->db->count(), 0, 'Undigested queries: ' . $this->db->count());
  }
}
?>
