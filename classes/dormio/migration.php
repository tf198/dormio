<?
/**
* Schema migration models
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
* Model to hold schema migration information.
* @package dormio
* @subpackage schema
*/
class Dormio_Migration extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'module' => array('type' => 'string'),
      'model' => array('type' => 'string'),
      'file' => array('type' => 'string'),
      'applied' => array('type' => 'timestamp'),
      'schema' => array('type' => 'text', 'null' => true),
    ),
  );
}
?>