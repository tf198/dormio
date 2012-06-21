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

if($form->is_valid()) {
  $form->save();
  echo "Blog saved\n";
  var_dump($blog->getData());
} else {
  echo <<< EOF
<html>
  <head><title>Example Form</title></head>
  <body>
  	{$form->open()}
    {$form->as_table()}
    {$form->buttons()}
    {$form->close()}
  </body>
</html>
EOF;
}

return 42; // for our auto testing
?>