<?
require_once('bantam/bantam.php');

$path = realpath(dirname(__FILE__) . "/..");
Bantam::instance()->addPaths(array($path));
?>
