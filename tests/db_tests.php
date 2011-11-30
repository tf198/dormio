<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

abstract class TestOfDB extends UnitTestCase{
  function setUp() {
    $this->db = new MockPDO('sqlite::memory:');
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
    $sql = $this->db->digest();
    //var_dump($sql);
    $params = array();
    foreach($sql[1] as $set) $params[] = "[" . implode(', ', $set) . "]";
    echo $sql[0] . " (" . implode(', ', $params) . ")\n";
  }
  
  function dumpAllSQL() {
    while($this->db->stack) $this->dumpSQL();
  }
  
  function dumpData() {
    $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'");
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $table) {
      echo "TABLE: {$table}\n";
      $stmt = $this->db->query("SELECT * FROM {$table}");
      foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $key=>$columns) {
        echo "{$key}";
        foreach($columns as $key=>$value) echo "\t{$key}: {$value}";
        echo "\n";
      }
    }
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
