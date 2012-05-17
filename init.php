<?php

define('DORMIOPATH', dirname(__FILE__));
require DORMIOPATH . "/classes/dormio/autoload.php";
Dormio_Autoload::register();

if(Kohana::$environment === Kohana::DEVELOPMENT) {
  Dormio_Factory::$logging = true;
  $logger = new Pluggable_Dormio_Logger();
  Dormio_Logging_PDO::$logger = $logger;
}
Dormio_Meta::$prefix = "model_";
/*
Route::set('models', 'models(/<model>(/<id>(/<op>)))',
          array(
              'id' => '[0-9]+',
              'model' => '[a-z_]+',
              'op' => '(delete|edit|remove)',
          )
        )
        ->defaults(array(
            'controller' => 'model',
            'action' => 'index',
        ));
*/
Route::set('dormio', 'dormio/<controller>(/<action>)')
        ->defaults(array(
           'controller' => 'admin',
           'action' => 'index',
        ));

class Pluggable_Dormio_Logger {
  
  static $levels = array(
  	  'DEBUG' => Log::STRACE,
      'INFO' => Log::INFO,
      'ERROR' => Log::ERROR,
      'WARN' => Log::WARNING,
  );
  
  function log($message, $level='INFO') {
  	$level = strtoupper($level);
    Kohana::$log->add(self::$levels[$level], $message);
  }
}