<?php
define('TEST_PATH', dirname(__FILE__));
define('DORMIO_PATH', realpath(TEST_PATH . '/../'));

require_once(DORMIO_PATH . '/classes/dormio/autoload.php');
Dormio_Autoload::register();

require_once(TEST_PATH . '/classes/model.php');
?>