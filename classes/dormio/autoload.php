<?php
/**
* Dormio Autoloader
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
* Autoloader for Dormio classes.
* Just needs including somewhere around the top of your application entry point for
* access to all the Dormio goodies.
* <code>
* require_once('path/to/dormio/classes/dormio/autoload.php');
* Dormio_Autoload::register();
* </code>
*
* @package dormio
*/
class Dormio_Autoload {
  /**
  * Base dormio path
  * @var string
  */
  static $path=null;

  /**
  * Will autoload classes starting with Dormio_.
  * 
  * @param  string  $klass  Class name
  */
  static function autoload($klass) {
    $klass = strtolower($klass);
    $parts = explode('_', $klass);
    // check for directory
    if(file_exists(self::$path . '/' . $parts[0])) {
      // recursivly check for files that might satisfy the class
      while(count($parts)>0) {
        $file = self::$path . '/' . implode('/', $parts) . ".php";
        if(file_exists($file)) return include_once($file);
        array_pop($parts);
      }
    }
  }
  
  /**
  * Call to register the autoloader
  */
  static function register() {
    if(self::$path) return;
    self::$path = realpath(dirname(__FILE__) . '/..');
    spl_autoload_register(array('Dormio_Autoload','autoload')) or die('Failed to Pom autoloader');
  }
}
?>