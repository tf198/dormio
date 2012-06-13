<?php
include dirname(__FILE__) . "/../vendor/Dormio/Dormio/AutoLoader.php";
Dormio_AutoLoader::register();

if($argc<2) exit("Usage: php {$argv[0]} <filename>");

$config = new Dormio_Config;
$entities = include($argv[1]);
$config->addEntities($entities);

$config->generateAutoEntities();

foreach($config->getEntities() as $entity) {
	$schema = Dormio_Schema::fromEntity($config->getEntity($entity));
	$factory = Dormio_Schema::factory('sqlite', $schema);
	$sql = $factory->createSQL();
	foreach($sql as $line) echo $line . ";\n";
}