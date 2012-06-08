<?php
function bench($message) {
	$now = microtime(true);
	$mem = memory_get_usage();
	fputs(STDOUT, sprintf(" %5.2f %0.2f | %5d %4d | %s\n", ($now-BENCH_START)*1000, ($now-$GLOBALS['bench_last'])*1000, $mem/1024, ($mem-$GLOBALS['mem_last'])/1024, $message));
	$GLOBALS['bench_last'] = $now;
	$GLOBALS['mem_last'] = $mem;
}

define('BENCH_START', microtime(true));
$GLOBALS['bench_last'] = BENCH_START;
$GLOBALS['mem_last'] = memory_get_usage();
fputs(STDOUT, "     ms     |     KB     |\n Total Step | Total Step |\n---------------------------------------------------------\n");
bench('Bench ready');

$entities = array(
	'User' => array(
		'fields' => array(
			'name' => array('type' => 'text', 'max_length' => 3),
			//'blogs' => array('type' => 'reverse', 'model' => 'Blog'),
			//'comments' => array('type' => 'reverse', 'model' => 'Comment'),
			//'profile' => array('type' => 'reverse', 'model' => 'Profile'),
		),
	),
	'Blog' => array(
		'fields' => array(
			'title' => array('type' => 'text', 'max_length' => 30),
			'the_user' => array('type' => 'foreignkey', 'entity' => 'User', 'db_column' => 'the_blog_user'),
			'tags' => array('type' => 'manytomany', 'entity' => 'Tag', 'through' => 'My_Blog_Tag'),
			//'comments' => array('type' => 'reverse', 'model' => 'Comment'),
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
			'blog' => array('type' => 'foreignkey', 'entity' => 'Blog'),
			'tags' => array('type' => 'manytomany', 'entity' => 'Tag'),
		),
	),
	'Tag' => array(
		'fields' => array(
			'tag' => array('type' => 'string'),
		),
	),
);

require "vendor/Dormio/AutoLoader.php";
Dormio_AutoLoader::register();
bench('AutoLoader registered');

class_exists('Dormio_Config');
bench('Dormio_Config parse');

$config = new Dormio_Config;
bench('Dormio_Config create');

$config->addEntities($entities);
bench('Add entities');

$config->generateAutoEntities();
bench('Generate auto entities');

$o = $config->getEntity('Blog');
bench('Create Blog');

$o->getField('title');
bench('Parse default');

$o->getField('Comment_Set');
bench('Get reverse');

$o->getField('tags');
bench('Parse manytomany');
