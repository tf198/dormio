<?
/**
* This is a runable example
*   > php docs/examples/forms.php > form_output.html
*   > php docs/examples/forms.php pk=3 title="Test Title" body="Hello World" user=2
* @package dormio
* @subpackage example
* @filesource
*/

/**
* This just registers the autoloader and creates an example database in memory
* @example setup.php
*/ 
$pdo = include('setup.php');
$dormio = new Dormio_Factory($pdo);

// lets fake some POST data so we can test from the command line
if(isset($argc)) {
  for($i=1; $i<$argc; $i++) {
    $parts = explode('=', $argv[$i], 2);
    $_POST[$parts[0]] = $parts[1];
  }
}

$blog = $dormio->get('Blog', 3);
$form = new Dormio_Form($blog, $_POST);

if($_POST && $form->isValid()) {
  $form->save();
  echo "Blog saved\n";
  var_dump($blog->data());
} else {
  echo <<< EOF
<html>
  <head><title>Example Form</title></head>
  <body>
    {$form->asTable()}
  </body>
</html>
EOF;
}

return 42; // for our auto testing
?>