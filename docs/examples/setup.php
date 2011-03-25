<?
/**
* This just registers the autoloader and creates a database in memory with some example data
* @package dormio
* @subpackage example
*/
$example_path = dirname(__FILE__);

// this path should be correct for the examples - modify as appropriate to your project
require_once($example_path . '/../../classes/dormio/autoload.php');
Dormio_Autoload::register();

// include our test models
require_once($example_path . '/models.php');

// our basic connection object is just a stock PDO instance
$pdo = new PDO('sqlite::memory:');
// quickly set up the schemas and load some data
foreach(file($example_path . '/setup.sql') as $sql) $pdo->exec($sql);

// Use this connection in the other examples
return $pdo;
?>