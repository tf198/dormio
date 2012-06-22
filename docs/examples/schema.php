<?php
/**
 * @package Dormio/Examples
 * @filesource
 */

/**
 *  * This is a runable example
 *   > php docs/examples/schema.php
 */
$example_path = dirname(__FILE__);

// setup our autoloader as before
require_once($example_path . '/../../vendor/Dormio/Dormio/AutoLoader.php');
Dormio_AutoLoader::register();

// get a connection
$pdo = new PDO('sqlite::memory:');

// load our entities
$config = new Dormio_Config;
$config->addEntities(include $example_path . '/entities.php');

$dormio = new Dormio($pdo, $config);

$admin = new Dormio_Admin($dormio);
$admin->syncdb();

// have a look at the result
$stmt = $pdo->prepare('SELECT sql FROM SQLITE_MASTER');
$stmt->execute();
echo "\n\nSQLITE Schemas from memory database\n---\n";
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
	echo "{$row[0]}\n";
}

// have a look at what would be used if we were on MySQL
echo "\n\nMySQL statements that would be used\n---\n";
foreach($config->getEntities() as $entity_name) {
	$entity = $config->getEntity($entity_name);
	$schema = Dormio_Schema::fromEntity($entity);
	$sf = Dormio_Schema::factory('mysql', $schema);
	$sf->createTable();
	foreach($sf->sql as $sql) echo "{$sql};\n";
}

return 42; // for our auto testing
?>