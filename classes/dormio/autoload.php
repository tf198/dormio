<?
class Dormio_Autoload {
  static $path;

  static function autoload($klass) {
    if(strtolower(substr($klass, 0, 7))=='dormio_') {
      require self::$path . "/" . substr($klass, 7) . ".php";
    }
  }
}
Dormio_Autoload::$path = dirname(__FILE__);
spl_autoload_register(array('Dormio_Autoload','autoload')) or die('Failed to Pom autoloader');
?>