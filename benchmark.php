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

require "tests/bootstrap.php";
bench('Bootstrapped');

class_exists('Dormio_Config');
bench('Dormio_Config include');

$config = Dormio_Config::instance();
bench('Dormio_Config::instance()');

$config->addEntities($GLOBALS['test_entities']);
bench('addEntities()');

$config->generateAutoEntities();
bench('generateAutoEntities()');

$blog = $config->getEntity('Blog');
bench('getEntity()');

$blog->getField('title');
bench('getField() - default type');

$blog->getField('the_user');
bench('getField() - foreignkey');

$blog->getField('comments');
bench('getField() - reverse foreignkey');

$blog->getField('tags');
bench('getField() - manytomany');

class_exists('Dormio_Query');
bench('Dormio_Query include');

// query tests
$query = new Dormio_Query($blog, 'sqlite');
bench('Dormio_Query::__construct()');

$query->select();
bench('select() - basic');

$query->filter('the_user__profile__age', '>', 12)->select();
bench('select() - complex filter');