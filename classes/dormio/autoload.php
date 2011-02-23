<?
class Dormio_Autoload {
  static $path;

  static function autoload($klass) {
    if(strtolower(substr($klass, 0, 4))=='pom_') require self::$path . "/" . substr($klass, 4) . ".php";
  }
}
Dormio_Autoload::$path = dirname(__FILE__);
spl_autoload_register(array('Dormio_Autoload','autoload')) or die('Failed to Pom autoloader');
?>