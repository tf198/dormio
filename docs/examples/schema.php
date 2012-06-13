<?php
/**
 * This is a runable example
 *   > php docs/examples/schema.php
 * @package dormio
 * @subpackage example
 * @filesource
 */
$example_path = dirname(__FILE__);

// setup our autoloader as before
require_once($example_path . '/../../vendor/Dormio/Dormio/AutoLoader.php');
Dormio_AutoLoader::register();

// get a connection
$pdo = new PDO('sqlite::memory:');

// When you use Dormio methods this is set automatically
// We'll set it here otherwise errors tend to disappear into the ether
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// load our entities
$config = new Dormio_Config;
$config->addEntities(include $example_path . '/entities.php');

// force generation of automatic entities
$config->generateAutoEntities();

// these 5 lines create every table defined in our entities and their indexes
foreach($config->getEntities() as $entity_name) {
	$entity = $config->getEntity($entity_name);
	$sf = Dormio_Schema::factory('sqlite', $entity);
	$sf->createTable();
	$sf->batchExecute($pdo, $sf->sql);
}

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
	$sf = Dormio_Schema::factory('mysql', $entity);
	$sf->createTable();
	foreach($sf->sql as $sql) echo "{$sql};\n";
}

return 42; // for our auto testing
?>