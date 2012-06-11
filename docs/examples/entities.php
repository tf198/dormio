<?php
return array(
	'Blog' => array(
		'fields' => array(
			'title' => array('type' => 'string', 'max_length' => 30),
			'body' => array('type' => 'text'),
			'author' => array('type' => 'foreignkey', 'entity' => 'User'),
			'tags' => array('type' => 'manytomany', 'entity' => 'Tag'),
		),
	),
	'Comment' => array(
		'fields' => array(
			'blog' => array('type' => 'foreignkey', 'entity' => 'Blog', 'related_name' => 'comments'),
			'body' => array('type' => 'text'),
			'author' => array('type' => 'foreignkey', 'entity' => 'User'),
		),
	),
	'User' => array(
		'fields' => array(
			'username' => array('type' => 'string', 'max_length' => 50),
			'password' => array('type' => 'password'),
			'display_name' => array('type' => 'string'),
		),
	),
	'Profile' => array(
		'fields' => array(
			'user' => array('type' => 'onetoone', 'entity' => 'User'),
			'fav_colour' => array('type' => 'string', 'max_length' => 10),
			'age' => array('type' => 'integer'),
		),
	),
	'Tag' => array(
		'fields' => array(
			'tag' => array('type' => 'string'),
		),
	)
);
