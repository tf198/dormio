<?
require_once('bantam/bantam.php');

$path = realpath(dirname(__FILE__) . "/..");
Bantam::instance()->add_paths(array($path));
?>