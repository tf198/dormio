<?
/**
* PDO singleton with auto config and fake driver support
*
* @author Tris Forster <tris@tfconsulting.com.au>
* @version 0.3
* @package bantam
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
* PDO singleton with auto config and fake driver support
*
* @author Tris Forster <tris@tfconsulting.com.au>
* @version 0.3
* @package bantam
* @depends Common, Event
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
*/
class Dormio_Connection {
	/**
	* PDO instance
	* @var	PDO	$db
	*/
	private static $db=array();
	
	private function __construct() {} // cant instansiate
	
	/**
	* Get a PDO instance
	* Requires config/pdodb.php:
	* $config['default']=array(
	*   'connection' => 'dsn:hostspec',
	*   'username' => 'username', // optional
	*   'password' => 'password', // optional
	*   'parameters' => array()  // optional
	* )
	* @param		string	$which	the database config to use
	*/
	public static function &instance($which='default') {
		if(!isset(self::$db[$which])) {
			bEvent::run('profile','dormio.init');
			$config=bCommon::config('dormio.'.$which);
				$driver=substr($config['connection'],0,strpos($config['connection'],":"));
				// use proper PDO driver if available
				if(array_search($driver,PDO::getAvailableDrivers())!==false) {
					$classname = 'PDO';
				// try to fall back on fake driver
				} else {
					$classname = "PDODB_Driver_{$driver}";
					if(!class_exists($classname)) throw new PDOException("No driver available for {$driver}");
				}
				if(!isset($config['username'])) $config['username']=false;
				if(!isset($config['password'])) $config['password']=false;
				if(!isset($config['parameters'])) $config['parameters']=array();
				self::$db[$which]=new $classname(
					$config['connection'],
					$config['username'],
					$config['password'],
					$config['parameters']
				);
				self::$db[$which]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if($driver=='mysql') $self::$db[$which]->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
			bEvent::run('profile','dormio.load');
		}
		return self::$db[$which];
	}
}
?>