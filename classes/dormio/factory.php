<?php
/**
* Dormio Factory.
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
*/

/**
* Factory for creating Dormio Models and Managers.
* This is the best way to obtain Models and Managers as it takes care of the connection
* and dialect selection.
* <code>
* $pdo = new PDO('sqlite::memory:');
* $dormio = new Dormio_Factory($pdo);
*
* $blog = $dormio->get('Blog', 23);
* </code>
* @package dormio
*/
class Dormio_Factory {
  static $_cache = array();
  static $instances = array();
  
  static $logging = false;
  
  /**
  * Construct a new factory.
  * @param  PDO $db   The connection to use
  */
  function __construct(PDO $db) {
    // set error mode here so we dont get any silent errors later
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $this->db = $db;
    $this->dialect = Dormio_Dialect::factory($db->getAttribute(PDO::ATTR_DRIVER_NAME));
  }
  
  /**
  * Get a model instance, ready to use.
  * @param  string  $model  The model name
  * @param  int     $pk     If provided the specified model will be loaded (well, prepared for access...)
  * @return Dormio_Model
  */
  function get($model, $pk=null) {
    if(!class_exists($model)) throw new Dormio_Exception('No such model: ' . $model);
    $obj = new $model($this->db, $this->dialect);
    if($pk) $obj->load($pk);
    return $obj;
  }
  
  /**
  * Get a manager for the named model.
  * @param  string  $name   The model
  * @return Dormio_Manager
  */
  function manager($name) {
    if(!isset(self::$_cache[$name])) self::$_cache[$name] = new Dormio_Manager($name, $this->db, $this->dialect);
    return self::$_cache[$name];
  }

  /**
   * Create a new instance with the supplied parameters and save it.
   * @param string $name    Name of model
   * @param array $params   Assoc array of field values
   * @return Dormio_Model   Model instance (already saved)
   */
  function create($name, $params) {
    $model = $this->get($name);
    $model->_updated = $params;
    $model->save();
    return $model;
  }

  /**
	* Get a PDO instance based on a config.
  * <code>
	* $config = array(
	*   'connection' => 'dsn:hostspec',
	*   'username' => 'username', // optional
	*   'password' => 'password', // optional
	*   'parameters' => array()  // optional
	* )
  * </code>
	* @param    array   $config	the config to use
  * @return   PDO     a database connection
	*/
	public static function PDO($config) {
		$driver=substr($config['connection'],0,strpos($config['connection'],":"));
		// use proper PDO driver if available
		if(array_search($driver,PDO::getAvailableDrivers())!==false) {
			$classname = 'PDO';
                        if(self::$logging) $classname = 'Dormio_Logging_PDO';
		// try to fall back on fake driver
		} else {
			$classname = "PDODB_Driver_{$driver}";
			if(!class_exists($classname)) throw new PDOException("No driver available for {$driver}");
		}
		if(!isset($config['username'])) $config['username']=false;
		if(!isset($config['password'])) $config['password']=false;
		if(!isset($config['parameters'])) $config['parameters']=array();
		$db = new $classname(
			$config['connection'],
			$config['username'],
			$config['password'],
			$config['parameters']
		);
		if($driver=='mysql') $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		return $db;
	}
}
