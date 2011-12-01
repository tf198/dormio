<?php
/**
* This is a runable example
*   > php docs/examples/schema.php
* @package dormio
* @subpackage example
* @filesource
*/

// setup our autoloader as before
require_once(dirname(__FILE__) . '/../../classes/dormio/autoload.php');
Dormio_Autoload::register();

// get a connection
$pdo = new PDO('sqlite::memory:');

// When you use Dormio_Factory this is set automatically
// We'll set it here otherwise errors tend to disappear into the ether
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// import our models
require_once('models.php');
$models = array('Blog', 'Comment', 'User', 'Profile');

// these 4 lines do the whole schema creation!
foreach($models as $model) {
  $sf = Dormio_Schema::factory('sqlite', $model);
  $sf->createTable();
  $sf->batchExecute($pdo, $sf->sql);
}

// have a look at the result
$stmt = $pdo->prepare('SELECT sql FROM SQLITE_MASTER');
$stmt->execute();
echo "\nSQLITE Schemas from memory database\n---\n";
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
  echo "{$row[0]}\n";
}

// have a look at what would be used if we were on MySQL
echo "\nMySQL statements that would be used\n---\n";
foreach($models as $model) {
  $sf = Dormio_Schema::factory('mysql', $model);
  foreach($sf->createSQL() as $sql) echo "{$sql};\n";
}

return 42; // for our auto testing
?>