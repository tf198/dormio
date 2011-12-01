<?php
/**
* Dormio adapter for Kohana
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author Tris Forster <tris.701437@tfconsulting.com.au>
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
* @package dormio
* @subpackage adapter
*/

/**
* Kohana entry point for Dormio
*
* @author Tris Forster <tris@tfconsulting.com.au>
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
* @package dormio
* @subpackage adapter
*/
class Dormio_Kohana {
	/**
	* PDO instance
	* @var	PDO	$db
	*/
	private static $db=array();
  
  /**
  * Factory cache
  */
  private static $factories = array();
	
	private function __construct() {} // cant instansiate
	
	/**
	* Get a PDO instance
	* Requires config/pdodb.php:
  * <code>
	* return array(
	*		'default' => array(
	*     'connection' => 'dsn:hostspec',
	*     'username' => 'username', // optional
	*     'password' => 'password', // optional
	*     'parameters' => array()  // optional
	*    ),
	* );
  * </code>
	* @param		string	$which	the database config to use
	*/
	public static function &instance($which='default') {
		if(!isset(self::$db[$which])) {
			$config=Kohana::config('pdodb.'.$which);
			if(!$config) throw new Kohana_Exception('No PDODB config file found');
      self::$db[$which] = Dormio_Factory::PDO($config);
		}
		return self::$db[$which];
	}
  
  /**
  * Convenience method to get a factory instance
  * @param  string  $which    The database config to use
  * @return Dormio_Factory
  */
  public static function factory($which='default') {
    if(!isset(self::$factories[$which])) {
      self::$factories[$which] = new Dormio_Factory(self::instance($which));
    }
    return self::$factories[$which];
  }
}
