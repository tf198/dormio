<?
/**
* Kohana entry point for Dormio
*
* @author Tris Forster <tris@tfconsulting.com.au>
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
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
*/

/**
* Kohana entry point for Dormio
*
* @author Tris Forster <tris@tfconsulting.com.au>
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
*/
class Kohana_Dormio {
	/**
	* PDO instance
	* @var	PDO	$db
	*/
	private static $db=array();
	
	private function __construct() {} // cant instansiate
	
	/**
	* Get a PDO instance
	* Requires config/pdodb.php:
	* return array(
	*		'default' => array(
	*     'connection' => 'dsn:hostspec',
	*     'username' => 'username', // optional
	*     'password' => 'password', // optional
	*     'parameters' => array()  // optional
	*    ),
	* );
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
}
?>