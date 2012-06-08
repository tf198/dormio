<?php
require dirname(__DIR__) . "/vendor/Dormio/AutoLoader.php";
Dormio_AutoLoader::register();

$GLOBALS['test_entities'] = array(
	'User' => array(
		'fields' => array(
			'name' => array('type' => 'text', 'max_length' => 3),
		),
	),
	'Blog' => array(
		'fields' => array(
			'title' => array('type' => 'string', 'max_length' => 30),
			//'body' => array('type' => 'text'),
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
			'title' => array('type' => 'string', 'max_length' => 30),
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
	'Profile' => array(
		'fields' => array(
			'user' => array('type' => 'onetoone', 'entity' => 'User', 'related_name' => 'profile'),
			'age' => array('type' => 'integer'),
		),
	),
	'MultiDep' => array(
		'fields' => array(
			'name' => array('type' => 'string', 'max_length' => 30),
			'depends_on' => array('type' => 'manytomany', 'entity' => 'MultiDep', 'related_name' => 'required_by'),
		),
	),
	'Tree' => array(
		'fields' => array(
			'name' => array('type' => 'string', 'max_length' => 30),
			'parent' => array('type' => 'foreignkey', 'entity' => 'Tree'),
		),
	),
);