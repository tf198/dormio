<?php

class Dormio_Admin {
  
  public static $logger = false;
  
  static function truncate($which='default') {
    $config = Kohana::$config->load('dormio');
    
    $db = $config->get($which, array());
    $connection = $db['connection'];
    $parts = explode(":", $connection, 2);
    if($parts[0] == 'sqlite') file_put_contents($parts[1], null);
  }
  
  static function syncDB($which='default') {
    
    $pdo = Dormio::instance($which);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    // find all the currently defined models
    $models = Dormio_Meta::getModels();
    $result = array();
    
    foreach($models as $model) {
      self::$logger && self::$logger->log("Installing {$model}");
      $sf = Dormio_Schema::factory($driver, $model);
      $sf->createTable();
      $sf->batchExecute($pdo, $sf->sql);
    }
  }
  
  static function loadFixtures($file) {
    //$json = json_encode(array(array('test' => 1)));
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    
    if($data==null) throw new Exception("Parse error for '{$file}'");
    
    Dormio_Meta::parseAllModels();
    
    foreach($data as $entry) {
      $model = $entry['model'];
      unset($entry['model']);
      $meta = Dormio_Meta::get($model);
      $manager = Dormio::factory()->manager($meta);
      //$manager->create($entry)->save();
      $keys = array_keys($entry);
      foreach($keys as &$value) if(substr($value,0,1)=='_') $value = substr($value, 1);
      $stmt = $manager->insert($keys);
      $stmt->execute(array_values($entry));
    }
    
    self::$logger && self::$logger->log($file . ": " . count($data));
  }
}