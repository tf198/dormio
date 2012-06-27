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

Phorm_Phorm::$html5 = true;

$entity = isset($_GET['entity']) ? ucfirst($_GET['entity']) : 'Blog';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
try {
	$obj = $dormio->getObject($entity, $id);
	$form = new Dormio_Form($obj);
	
	if($form->is_valid()) {
	  $form->save();
	  $message = "Updated {$obj}";
	}
	
} catch(Exception $e) {
	echo "<pre>\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n</pre>\n";
	exit;
}
?>
<!DOCTYPE html>
<html>
  <head><title>Example Form</title></head>
  <link type="text/css" rel="stylesheet" href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css"/>
  <link type="text/css" rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/smoothness/jquery-ui.css"/>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>
  <script type="text/javascript">
$(document).ready(function(){
	$('.phorm_field_datetime').datepicker( { 'dateFormat': 'dd/mm/yy' } );
});
  </script>
  <style type="text/css">
.example-form {
	margin: 2em auto;
	display: table;
}
.validation-advice {
	color: red;
	font-style:italic;
}
.ui-widget {
	font-size: 0.9em;
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
  	<pre>
  		<?php var_dump($obj->_data); ?>
  	</pre>
  </body>
</html>
