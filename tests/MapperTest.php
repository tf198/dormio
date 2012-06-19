<?php
class_exists('Dormio');

class Dormio_MapperTest extends PHPUnit_Framework_TestCase {
	
	private $basic_map = array('one' => 'f1', 'two' => 'f2', 'three' => 'f3');
	private $basic_data = array('f1' => 'first', 'f2' => 'second');
	
	private $join_map = array('pk' => 't1_pk', 'title' => 't1_title', 'author' => 't1_author_id', 'author__pk' => 't2_pk', 'author__name' => 't2_name');
	private $join_data = array('t1_pk' => 23, 't1_title' => 'My Blog', 't1_author_id' => '45', 't2_pk' => '45', 't2_name' => 'Bob');
	
	function testNoData() {
		$mapper = new Dormio_ResultMapper($this->basic_map);
		try {
			$mapper['test'];
			$this->fail();
		} catch(Exception $e) {
			$this->assertEquals('No raw data provided', $e->getMessage());
		}
	}
	/**
     * @expectedException PHPUnit_Framework_Error
     */
	function testGetBasic() {
		$mapper = new Dormio_ResultMapper($this->basic_map);
		$mapper->setRawData($this->basic_data);
		
		$this->assertEquals('first', $mapper['one']);
		$this->assertEquals('second', $mapper['two']);
		$this->assertEquals('third', $mapper['three']);
	}
	
	function testJoinData() {
		$mapper = new Dormio_ResultMapper($this->join_map);
		$mapper->setRawData($this->join_data);
		
		$this->assertEquals(23, $mapper['pk']);
		$this->assertEquals('My Blog', $mapper['title']);
		
		$author = $mapper->getChildMapper('author');
		$this->assertEquals(45, $author['pk']);
		$this->assertEquals('Bob', $author['name']);
	}
}