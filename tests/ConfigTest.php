<?php
class Dormio_ConfigTest extends PHPUnit_Framework_TestCase {
	
	static $entities = array(
		'User' => array(
			'fields' => array(
				'name' => array('type' => 'text', 'max_length' => 3),
			),
		),
		'Blog' => array(
			'fields' => array(
				'title' => array('type' => 'text', 'max_length' => 30),
				'the_user' => array('type' => 'foreignkey', 'entity' => 'User', 'db_column' => 'the_blog_user'),
				'tags' => array('type' => 'manytomany', 'entity' => 'Tag', 'through' => 'My_Blog_Tag'),
			),
		),
		'My_Blog_Tag' => array(
			'table' => 'blog_tag',
			'fields' => array(
				'pk' => array('type' => 'ident', 'db_column' => 'blog_tag_id'),
				'the_blog' => array('type' => 'foreignkey', 'entity' => 'Blog', 'db_column' => 'the_blog_id'),
				'tag' => array('type' => 'foreignkey', 'entity' => 'Tag', 'db_column' => 'the_tag_id'),
			),
		),
		'Comment' => array(
			'fields' => array(
				'title' => array('type' => 'text', 'max_length' => 30),
				'user' => array('type' => 'foreignkey', 'entity' => 'User', 'db_column' => 'the_comment_user'),
				'blog' => array('type' => 'foreignkey', 'entity' => 'Blog', 'related_name' => 'comments'),
				'tags' => array('type' => 'manytomany', 'entity' => 'Tag'),
			),
		),
		'Tag' => array(
			'fields' => array(
				'tag' => array('type' => 'string'),
			),
		),
	);
	
	/**
	 * @var Dormio_Config
	 */
	public $config;
	
	function setUp() {
		$this->config = new Dormio_Config;
		$this->config->addEntities(self::$entities);
	}
	
	function testAddEntities() {
		// check the auto relations have been flagged correctly
		$this->assertEquals(array(array('Comment', 'tags')), $this->config->_relations['auto']);
		
		// check the reverse relations have been correctly defined
		$this->assertEquals(array('My_Blog_Tag_Set', 'comments', 'Comment_Set'), array_keys($this->config->_relations['Blog']));
		$this->assertEquals(array('Blog_Set', 'My_Blog_Tag_Set', 'Comment_Set'), array_keys($this->config->_relations['Tag']));
	}
	
	function testGetEntities() {
		$this->assertEquals(array('User', 'Blog', 'My_Blog_Tag', 'Comment', 'Tag'), $this->config->getEntities());
	}
	
	function testGenerateAutoEntities() {
		$this->config->generateAutoEntities();
		$this->assertEquals(array('User', 'Blog', 'My_Blog_Tag', 'Comment', 'Tag', 'Comment_X_Tag'), $this->config->getEntities());
	}
	
	function testUsage() {
		$o = $this->config->getEntity('Blog');
		var_dump($o->getField('tags'));
	}
}