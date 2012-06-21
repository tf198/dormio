<?php
/**
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
 * @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
 * @package Dormio
 */

/**
 * @var string
 */
define('VENDOR_PATH', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
define('PHORMS_ROOT', VENDOR_PATH . "Phorms" . DIRECTORY_SEPARATOR);

/**
 * Basic autoloader for Dormio
 * @author Tris Forster
 * @package Dormio
 */
class Dormio_AutoLoader {
	
	/**
	 * Include file for named class
	 * @param string $className
	 */
	static function autoload($className) {
		$parts = explode('_', $className);
		switch($parts[0]) {
			case 'Dormio':
				$filename = VENDOR_PATH . 'Dormio' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . ".php";
				break;
			case 'Phorm':
				$filename = VENDOR_PATH . 'Phorms' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";
				break;
			default:
				return;
		}
		if(is_readable($filename)) include $filename;
	}
	
	/**
	 * Register the autoloader
	 */
	static function register() {
		spl_autoload_register('Dormio_AutoLoader::autoload');
	}
}