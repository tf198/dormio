<?php
require_once('simpletest/autorun.php');
require_once('db_tests.php');

class TestOfIntrospection extends TestOfDB{
	function testTableNames() {
		$this->load('sql/test_schema.sql');
		$dialect = Dormio_Dialect::factory('sqlite');
		
		$stmt = $this->db->prepare($dialect->tableNames());
		$stmt->execute();
		
		$expected = array('aro', 'blog', 'blog_tag', 'comment', 'comment_x_tag', 'module', 'module_x_module', 'profile', 'tag', 'user');
		$this->assertEqual($stmt->fetchAll(PDO::FETCH_COLUMN, 0), $expected);
	}
}