<?php
class Dormio_ConfigTest extends PHPUnit_Framework_TestCase {
	
	/**
	 * @var Dormio_Config
	 */
	public $config;
	
	function setUp() {
		$this->config = new Dormio_Config;
		$this->config->addEntities($GLOBALS['test_entities']);
	}
	
	function testAddEntities() {
		// check the auto relations have been flagged correctly
		$this->assertEquals(array('Comment', 'MultiDep'), $this->config->_relations['auto']);
		
		// check the reverse relations have been correctly defined
		$this->assertEquals(array('my_blog_tag_set', 'comments'), array_keys($this->config->_relations['Blog']));
		$this->assertEquals(array('blog_set', 'my_blog_tag_set', 'comment_set'), array_keys($this->config->_relations['Tag']));
	}
	
	function testFindRelations() {
		$reverse = $this->config->_relations;
		$this->assertEquals(array('auto', 'User', 'Tag', 'Blog', 'MultiDep', 'Tree'), array_keys($reverse));
		$this->assertEquals(array('my_blog_tag_set', 'comments'), array_keys($reverse['Blog']));
		$this->assertEquals(array('blog_set', 'my_blog_tag_set', 'comment_set'), array_keys($reverse['Tag']));
	}
	
	function testGetEntities() {
		$this->assertEquals(array('User', 'Blog', 'My_Blog_Tag', 'Comment', 'Tag', 'Profile', 'MultiDep', 'Tree'), $this->config->getEntities());
	}
	
	function testGenerateAutoEntities() {
		$this->config->generateAutoEntities();
		$this->assertEquals(array('User', 'Blog', 'My_Blog_Tag', 'Comment', 'Tag', 'Profile', 'MultiDep', 'Tree', 'Comment_X_Tag', 'MultiDep_X_MultiDep'), $this->config->getEntities());
	}
	
	function testGetReverseField() {
		$reverse = $this->config->getReverseField('Blog', 'comments');
		$this->assertEquals('onetomany', $reverse['type']);
		$this->assertEquals('Comment', $reverse['entity']);
		
		$this->assertThrows('Dormio_Config_Exception: Entity [Rubbish] has no reverse fields', 
				array($this->config, 'getReverseField'), 'Rubbish', 'Test');
		
		$this->assertThrows('Dormio_Config_Exception: Entity [Blog] has no reverse field [test]', 
				array($this->config, 'getReverseField'), 'Blog', 'test');
	}
	
	function testGetEntity() {
		$this->config->getEntity('Blog');
		
		$this->assertThrows('Dormio_Config_Exception: Entity [Rubbish] is not defined in configuration', array($this->config, 'getEntity'), 'Rubbish');
	}
	
	function testGetReverseFields() {
		$this->assertEquals(array('my_blog_tag_set', 'comments'), array_keys($this->config->getReverseFields('Blog')));
	}
	
	/** ENTITY TESTS **/
	
	function testGetField() {
		$blog = $this->config->getEntity('Blog');
		// normal field
		$this->assertEquals(array(
				'verbose' => 'Title',
				'db_column' => 'title',
				'null_ok' => false,
				'is_field' => true,
				'type' => 'string',
				'max_length' => 30,
			), $blog->getField('title'));
		
		$this->assertEquals(array(
				'type' => 'onetomany',
				'local_field' => 'pk',
				'remote_field' => 'blog',
				'verbose' => 'Comments',
				'entity' => 'Comment',
				'on_delete' => 'cascade',
				'is_field' => false,
			), $blog->getField('comments'));
	}
	
	function assertThrows($expected, $callable) {
		$params = array_slice(func_get_args(), 2);
		try {
			call_user_func_array($callable, $params);
			$this->fail('An expected exception was not thrown');
		} catch(Exception $e) {
			$output = get_class($e) . ': ' . $e->getMessage();
			$this->assertStringStartsWith($expected, $output);
		}
	}
	
	function testCleanUp() {
		if(gethostname() != 'TFC-SERVER') $this->markTestSkipped();
		$start = memory_get_usage();
		$this->config->getEntity('Blog');
		$this->config->getEntity('Comment');
		$mid = memory_get_usage();
		unset($this->config);
		gc_collect_cycles();
		$end = memory_get_usage();
		$diff = ($end - $start) / 1024;
		
		// should be about 14KB
		//var_dump($start, $mid, $end, $diff);
		$this->assertApprox(-14, $diff);
	}
	
	function assertApprox($expected, $actual, $diff=1, $message='') {
		$result = abs($expected-$actual);
		$this->assertTrue($result <= $diff, "Expected within {$diff} of {$expected}: got {$actual}");
	}
}