<?php
require_once 'DBTest.php';

class Dormio_IntrospectionTest extends Dormio_DBTest{
	function testTableNames() {
		$this->load('data/entities.sql');
		$dialect = Dormio_Dialect::factory('sqlite');

		$stmt = $this->pdo->prepare($dialect->tableNames());
		$stmt->execute();

		$expected = array('blog', 'blog_tag', 'comment', 'comment_x_tag', 'multidep', 'multidep_x_multidep', 'profile', 'tag', 'tag_x_user', 'tree', 'user');
		$this->assertEquals($expected, $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
	}
}