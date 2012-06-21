<?
/**
* @package Dormio
* @subpackage Examples
* @filesource
*/

/**
 * This just registers the autoloader and creates a database in memory with some example data
 */
$example_path = dirname(__FILE__);

// this path should be correct for the examples - modify as appropriate to your project
require_once($example_path . '/../../vendor/Dormio/Dormio/AutoLoader.php');
Dormio_AutoLoader::register();

// our basic connection object is just a stock PDO instance
$pdo = new PDO('sqlite::memory:');

// set DEBUG to see what is going on
if(defined('DEBUG')) include 'debug.php';

// quickly set up the schemas and load some data
foreach(file($example_path . '/entities.sql') as $sql) $pdo->exec($sql);
foreach(file($example_path . '/data.sql') as $sql) $pdo->exec($sql);

// Use this connection in the other examples
return $pdo;


?>