<?php
class Dormio_DialectTest extends PHPUnit_Framework_TestCase {
  function setUp() {
    $this->sqlite = Dormio_Dialect::factory('sqlite');
    $this->sqlsrv = Dormio_Dialect::factory('sqlsrv');
    $this->mysql = Dormio_Dialect::factory('mysql');
  }
  
  function testLimit() {
    $spec = array(
      'select' => array('*'),
      'from' => array('{my_table}'),
      'where' => array('{field3}=?'),
      'limit' => 3,
    );
    $this->assertEquals($this->sqlite->select($spec), 'SELECT * FROM "my_table" WHERE "field3"=? LIMIT 3');
    $this->assertEquals($this->mysql->select($spec), 'SELECT * FROM `my_table` WHERE `field3`=? LIMIT 3');
    $this->assertEquals($this->sqlsrv->select($spec), 'SELECT TOP 3 * FROM [my_table] WHERE [field3]=?');
    
    $spec['offset'] = 6;
    $this->assertEquals($this->sqlite->select($spec), 'SELECT * FROM "my_table" WHERE "field3"=? LIMIT 3 OFFSET 6');
    $this->assertEquals($this->mysql->select($spec), 'SELECT * FROM `my_table` WHERE `field3`=? LIMIT 3 OFFSET 6');
    // M$ can't handle offsets
    try {
      $this->assertFalse($this->sqlsrv->select($spec));
    } catch(Dormio_Dialect_Exception $e) {
      $this->assertEquals($e->getMessage(), "Offset not supported by MSSQL");
    }
  }
  
  function testTableNames() {
  	$this->assertEquals($this->sqlite->tableNames(), "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence' ORDER BY name");
  	$this->assertEquals($this->mysql->tableNames(), "SHOW TABLES");
  	$this->assertEquals($this->sqlsrv->tableNames(), "SELECT name FROM sys.tables");
  }
}
?>