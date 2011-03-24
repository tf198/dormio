<?php
/**
* Autoloader for Dormio classes
*
* Copyright (C) 2009 Tris Forster
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
* @author Tris Forster <tris.git@tfconsulting.com.au>
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
* @author Tris Forster <tris.701437@tfconsulting.com.au>
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
    if(substr($klass, 0, 7)=='dormio_') {
      $file = self::$path . "/" . str_replace('_', '/', substr($klass, 7)) . ".php";
      if(file_exists($file)) include($file);
    }
  }
  
  /**
  * Call to register the autoloader
  */
  static function register() {
    if(self::$path) return;
    self::$path = dirname(__FILE__);
    spl_autoload_register(array('Dormio_Autoload','autoload')) or die('Failed to Pom autoloader');
  }
}
?>