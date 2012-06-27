<?
/**
* This is a runable example
*   > php docs/examples/forms.php > form_output.html
*   > php docs/examples/forms.php pk=3 title="Test Title" body="Hello World" user=2
* or point your browser to it
* 	http://localhost/dormio/docs/examples/forms.php
* @package Dormio
* @subpackage Examples
* @filesource
*/

/**
* This just registers the autoloader and creates an example database in memory
* @example example_base.php
*/ 
//define('DEBUG', true);
include "example_base.php";

// lets fake some POST data so we can test from the command line
if(isset($argc)) {
  for($i=1; $i<$argc; $i++) {
    $parts = explode('=', $argv[$i], 2);
    $_POST[$parts[0]] = $parts[1];
  }
}

$entity = isset($_GET['entity']) ? ucfirst($_GET['entity']) : 'Blog';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 1;

$obj = $dormio->getObject($entity, $id);
$form = new Dormio_Form($obj);

if($form->is_valid()) {
  $form->save();
  $message = "Updated {$obj}";
}
?>
<!DOCTYPE html>
<html>
  <head><title>Example Form</title></head>
  <link type="text/css" rel="stylesheet" href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css"/>
  <style type="text/css">
.example-form {
	margin: 2em auto;
	display: table;
}
.validation-advice {
	color: red;
	font-style:italic;
}
    </style>
  <body>
	<div class="example-form well">
	<?php if(isset($message)):?>
		<div class="alert alert-info">
			<?php echo $message ?>
		</div>
	<?php endif ?>
  	<?php $form->display('') ?>
  	</div>
  </body>
</html>
