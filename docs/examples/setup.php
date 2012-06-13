<?
/**
* @package Dormio/Examples
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

// this is just for my testing - turn it on if you want to see whats happening
if(false) {
	/**
	 * Simple logger implementation
	 * @package Dormio/Examples
	 *
	 */
	class Logger implements Dormio_Logger{
		function log($message, $level=LOG_INFO) {
			fputs(STDOUT, $message . "\n");
		}
	}
	$pdo = new Dormio_Logging_PDO('sqlite::memory:');
	Dormio::$logger = new Logger;
}

// quickly set up the schemas and load some data
foreach(file($example_path . '/setup.sql') as $sql) $pdo->exec($sql);

// Use this connection in the other examples
return $pdo;


?>