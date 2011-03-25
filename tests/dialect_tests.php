<?php
require_once('simpletest/autorun.php');
require_once('bootstrap.php');


class TestOfDialect extends UnitTestCase {
  function setUp() {
    $this->sqlite = Dormio_Dialect::factory('sqlite');
    $this->ms = Dormio_Dialect::factory('sqlsrv');
    $this->mysql = Dormio_Dialect::factory('mysql');
  }
  
  function testLimit() {
    $spec = array(
      'select' => array('*'),
      'from' => array('{my_table}'),
      'where' => array('{field3}=?'),
      'limit' => 3,
    );
    $this->assertEqual($this->sqlite->select($spec), 'SELECT * FROM "my_table" WHERE "field3"=? LIMIT 3');
    $this->assertEqual($this->mysql->select($spec), 'SELECT * FROM `my_table` WHERE `field3`=? LIMIT 3');
    $this->assertEqual($this->ms->select($spec), 'SELECT TOP 3 * FROM [my_table] WHERE [field3]=?');
    
    $spec['offset'] = 6;
    $this->assertEqual($this->sqlite->select($spec), 'SELECT * FROM "my_table" WHERE "field3"=? LIMIT 3 OFFSET 6');
    $this->assertEqual($this->mysql->select($spec), 'SELECT * FROM `my_table` WHERE `field3`=? LIMIT 3 OFFSET 6');
    // M$ can't handle offsets
    try {
      $this->assertFalse($this->ms->select($spec));
    } catch(Dormio_Dialect_Exception $e) {
      $this->assertEqual($e->getMessage(), "Offset not supported by MSSQL");
    }
  }
}
?>