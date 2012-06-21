<?
/**
* This is a runable example
*   > php docs/examples/forms.php > form_output.html
*   > php docs/examples/forms.php pk=3 title="Test Title" body="Hello World" user=2
* @package Dormio/Examples
* @filesource
*/

/**
* This just registers the autoloader and creates an example database in memory
* @example setup.php
*/ 
$pdo = include('setup.php');

$entities = include('entities.php');
$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);

// lets fake some POST data so we can test from the command line
if(isset($argc)) {
  for($i=1; $i<$argc; $i++) {
    $parts = explode('=', $argv[$i], 2);
    $_POST[$parts[0]] = $parts[1];
  }
}

$blog = $dormio->getObject('Blog', 3);
$form = new Dormio_Form($blog);

$message = "";
if($form->is_valid()) {
  $form->save();
  $message = '<div class="info">Blog updated</div>';
}

echo <<< EOF
<!DOCTYPE html>
<html>
  <head><title>Example Form</title></head>
  <link type="text/css" rel="stylesheet" href="form.css"/>
  <body>
  	<div class='form-block'>{$message}
  		{$form->open()}
	    {$form->as_table()}
	    <div class="form-buttons">
	    	{$form->buttons()}
	    </div>
	    {$form->close()}
    </div>
  </body>
</html>
EOF;

return 42; // for our auto testing
?>