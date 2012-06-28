<?php
include "example_base.php";

$entity = isset($_GET['entity']) ? ucfirst($_GET['entity']) : 'Blog';
$query = $dormio->getManager($entity);
$table = new Dormio_Table_Query($query);

$table->setClasses(array(
			'table' => 'table table-bordered table-condensed',
		))->setPageSize(2);

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Dormio Table Example</title>
		<link type="text/css" rel="stylesheet" href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css"/>
		<style type="text/css">
.table {
	width: auto;
	margin: 2em auto;
}
.table tfoot td {
	text-align: right;
}
		</style>
	</head>
	<body>
		<?php echo $table ?>
	</body>
</html>