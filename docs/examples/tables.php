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
.dt-div {
	margin: 2em auto;
}
.dt-div .pagination a {
	padding: 0 10px;
	line-height: 30px;
}
		</style>
	</head>
	<body>
		<?php echo $table ?>
	</body>
</html>