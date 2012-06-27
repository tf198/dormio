<?php
/**
 * @package Dormio/Examples 
 * @filesource
 */

return array(
	'Blog' => array(
		'extra' => array('display_field' => 'title'),
		'fields' => array(
			'title' => array('type' => 'string', 'max_length' => 30),
			'body' => array('type' => 'text'),
			'author' => array('type' => 'foreignkey', 'entity' => 'User'),
			'tags' => array('type' => 'manytomany', 'entity' => 'Tag', 'widget' => 'Phorm_Widget_Checkbox', 'null_ok' => true),
		),
	),
	'Comment' => array(
		'fields' => array(
			'blog' => array('type' => 'foreignkey', 'entity' => 'Blog', 'related_name' => 'comments'),
			'body' => array('type' => 'text'),
			'author' => array('type' => 'foreignkey', 'entity' => 'User'),
			'tags' => array('type' => 'manytomany', 'entity' => 'Tag'),
		),
	),
	'User' => array(
		'extra' => array('display_field' => 'display_name'),
		'fields' => array(
			'username' => array('type' => 'string', 'max_length' => 50),
			'password' => array('type' => 'password'),
			'display_name' => array('type' => 'string'),
		),
	),
	'Profile' => array(
		'fields' => array(
			'user' => array('type' => 'onetoone', 'entity' => 'User'),
			'fav_cheese' => array('type' => 'string', 'max_length' => 10, 'verbose' => 'Favorite Cheese'),
			'age' => array('type' => 'integer'),
		),
	),
	'Tag' => array(
		'extra' => array('display_field' => 'tag'),
		'fields' => array(
			'tag' => array('type' => 'string', 'max_length' => 10),
		),
	),
	'FieldTest' => array(
		'fields' => array(
			'string' => array('type' => 'string'),
			'integer' => array('type' => 'integer'),
			'float' => array('type' => 'float'),
			'password' => array('type' => 'password'),
			'timestamp' => array('type' => 'timestamp'),
			'foreignkey' => array('type' => 'foreignkey', 'entity' => 'Blog'),
			'onetoone' => array('type' => 'onetoone', 'entity' => 'Profile'),
			'manytomany' => array('type' => 'manytomany', 'entity' => 'Tag', 'widget' => 'Phorm_Widget_Checkbox'),
		),
	)
);
