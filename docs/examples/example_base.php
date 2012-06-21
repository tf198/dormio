<?php
/**
 * @package Dormio
 * @subpackage Examples
 * @filesource
 */

/**
 * This just registers the autoloader and creates a database in memory with some example data
 */
$pdo = include('setup.php');

$entities = include('entities.php');
$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);
