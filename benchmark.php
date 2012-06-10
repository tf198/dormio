<?php
function bench($message) {
	$now = microtime(true);
	$mem = memory_get_usage();
	fputs(STDOUT, sprintf(" %6.2f %5.1f | %4d %4d | %s\n", ($now-BENCH_START)*1000, ($now-$GLOBALS['bench_last'])*1000, $mem/1024, ($mem-$GLOBALS['mem_last'])/1024, $message));
	$GLOBALS['bench_last'] = $now;
	$GLOBALS['mem_last'] = $mem;
}

define('BENCH_START', microtime(true));
$GLOBALS['bench_last'] = BENCH_START;
$GLOBALS['mem_last'] = memory_get_usage();
fputs(STDOUT, "     ms     |     KB     |\n Total Step | Total Step |\n---------------------------------------------------------\n");
bench('Bench ready');

define('LOOP', 600);

require "tests/bootstrap.php";
bench('Bootstrapped');

class_exists('Dormio_Config');
bench('Dormio_Config include');

$config = Dormio_Config::instance();
bench('Dormio_Config::instance()');

//$config->addEntities($GLOBALS['test_entities']);
$config->addEntities(include 'docs/examples/entities.php');
bench('addEntities()');

$config->generateAutoEntities();
bench('generateAutoEntities()');

$blog = $config->getEntity('Blog');
bench('getEntity()');

$blog->getField('title');
bench('getField() - default type');

$blog->getField('author');
bench('getField() - foreignkey');

$blog->getField('comments');
bench('getField() - reverse foreignkey');

for($i=0; $i<LOOP; $i++) $blog = $config->getEntity('Blog');
bench('getEntity() multiple');

class_exists('Dormio_Query');
bench('Dormio_Query include');

// query tests
$query = new Dormio_Query($blog, 'sqlite');
bench('Dormio_Query::__construct()');

$query->select();
bench('select() - basic');

$query->filter('author__profile_set__age', '>', 12)->select();
bench('select() - complex filter');

$pdo = new PDO('sqlite::memory:');
foreach(file('docs/examples/setup.sql') as $sql) $pdo->exec($sql);
bench('Table prepared');

class_exists('Dormio');
bench('Dormio include');

$dormio = new Dormio($pdo, $config);
bench('Dormio::__construct()');

$o = $dormio->getObject('Blog', 2);
assert($o->title == 'Andy Blog 2');
bench('Dormio::getObject()');

for($i=0; $i<LOOP; $i++) {
	$o = $dormio->getObject('Blog', 2);
}
bench('Dormio::getObject() multiple');

$o = $dormio->getObject('Blog');
$o->title = 'Test';
$dormio->insert($o, 'Blog');
bench('Insert one object');

for($i=0; $i<LOOP; $i++) {
	$o = $dormio->getObject('Blog');
	$o->title = 'Test ' . $i;
	$dormio->save($o);
}
assert($o->pk == LOOP + 4);
//var_dump($o->pk);
bench('Insert multiple objects');

class_exists('Dormio_Manager');
bench('Dormio_Manager include');

$blogs = new Dormio_Manager($blog, $dormio);
bench('Dormio_Manager::__construct() - ARRAY');

$iter = $blogs->find();
bench('Queryset evaluate');

foreach($iter as $item) { }
unset($iter);
bench('Array iteration');

$blogs = new Dormio_Manager($blog, $dormio, Dormio_Manager::MAP_OBJECT);
bench('Dormio_Manager::__construct() - OBJECT');

$iter = $blogs->find();
bench('Queryset evaluate');

foreach($iter as $item) {}
unset($iter);
bench('Object iteration');