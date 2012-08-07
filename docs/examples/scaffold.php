<?php
/**
 * An psudo controller for showing how you can easily scaffold your data
 */


// this path should be correct for the examples - modify as appropriate to your project
$example_path = dirname(__FILE__);
require_once($example_path . '/../../vendor/Dormio/Dormio/AutoLoader.php');
Dormio_AutoLoader::register();

// we need an actual on disk database for this example 
$datafile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scaffold_example.sq3';
$create = !file_exists($datafile);
$pdo = new PDO('sqlite:' . $datafile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if($create) {
	// quickly set up the schemas and load some data
	foreach(file($example_path . '/entities.sql') as $sql) $pdo->exec($sql);
	foreach(file($example_path . '/data.sql') as $sql) $pdo->exec($sql);
}

// same entity setup as before
$entities = include('entities.php');
$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);

// You can render the content however you want...
try {
	$scaffold = new Dormio_Scaffold($dormio);
	$content = $scaffold->getContent();
} catch(Exception $e) {
	header('HTTP/1.0 404 Not found');
	echo "<h1>404 {$e->getMessage()}</h1>";
	exit;
}

// Start of the view
?>
<!DOCTYPE html>
<html>
<head>
	<title>Scaffold example</title>
	<link type="text/css" rel="stylesheet" href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css"/>
</head>
<body>
	<div class="navbar">
	  <div class="navbar-inner">
	    <div class="container">
	      <a class="brand" href="scaffold.php">Dormio Scaffolding Example</a>
	    </div>
	  </div>
	</div>
	
	<div class="container">
		<?php echo $content ?>
	</div>
</body>
</html>