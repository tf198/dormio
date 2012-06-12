<?
/**
* This just registers the autoloader and creates a database in memory with some example data
* @package dormio
* @subpackage example
*/
$example_path = dirname(__FILE__);

// this path should be correct for the examples - modify as appropriate to your project
require_once($example_path . '/../../vendor/Dormio/Dormio/AutoLoader.php');
Dormio_AutoLoader::register();

// our basic connection object is just a stock PDO instance
$pdo = new PDO('sqlite::memory:');
// quickly set up the schemas and load some data
$i=0;
foreach(file($example_path . '/setup.sql') as $sql) $i += $pdo->exec($sql);
assert($i == 21); // error mode not set on pdo so double check everything loaded 

// Use this connection in the other examples
return $pdo;
?>