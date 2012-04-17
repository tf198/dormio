<?php

/**
 * Kohana factory class for Dormio library.
 * Allows database configuration via the existing Kohana config structure.
 * @author tris
 * @package dormio
 */
class Dormio {

  /**
   * PDO instance
   * @var array
   */
  private static $db = array();

  /**
   * Factory cache
   * @var array
   */
  private static $factories = array();

  /**
   * Get a PDO instance
   * Requires config/pdodb.php:
   * <code>
   * return array(
   * 		'default' => array(
   *     'connection' => 'dsn:hostspec',
   *     'username' => 'username', // optional
   *     'password' => 'password', // optional
   *     'parameters' => array()  // optional
   *    ),
   * );
   * </code>
   * @param   string  $which	the database config to use
   * @return  PDO   the requested PDO object
   */
  public static function &instance($which='default') {
    if (!isset(self::$db[$which])) {
      $config = Kohana::$config->load('dormio');
      if (!$config)
        throw new Kohana_Exception('No PDODB config file found');
      self::$db[$which] = Dormio_Factory::PDO($config->get($which));
    }
    return self::$db[$which];
  }

  /**
   * Convenience method to get a factory instance
   * @param  string  $which    The database config to use
   * @return Dormio_Factory
   */
  public static function factory($which='default') {
    if (!isset(self::$factories[$which])) {
      self::$factories[$which] = new Dormio_Factory(self::instance($which));
    }
    return self::$factories[$which];
  }

}
