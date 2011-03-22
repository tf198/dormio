<?
class Dormio_Autoload {
  static $path;

  static function autoload($klass) {
    $klass = strtolower($klass);
    if(substr($klass, 0, 7)=='dormio_') {
      $file = self::$path . "/" . str_replace('_', '/', substr($klass, 7)) . ".php";
      if(file_exists($file)) include($file);
    }
  }
}
Dormio_Autoload::$path = dirname(__FILE__);
spl_autoload_register(array('Dormio_Autoload','autoload')) or die('Failed to Pom autoloader');
?>