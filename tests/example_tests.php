<?php
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfExamples extends UnitTestCase{
  function setUp() {
    $this->con = new PDO('sqlite::memory:');
  }
  
  function testModels() {
    require_once dirname(__FILE__) . '/../examples/models.php';
  }

}
?>