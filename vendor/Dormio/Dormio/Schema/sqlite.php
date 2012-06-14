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
* @package Dormio
* @subpackage Schema
*/
class Dormio_Schema_sqlite extends Dormio_Schema_Generic {

	protected $version='sqlite';
	
	public function quoteIdentifier($identifier) {
		return '"'.$identifier.'"';
	}
	
	public function getPrimitive($colspec) {
		switch($colspec['type']) {
			case 'ident':
				return 'INTEGER PRIMARY KEY AUTOINCREMENT';
			case 'integer':
			case 'boolean':
      case 'foreignkey':
      case 'onetoone':
				return 'INTEGER';
			case 'float':
			case 'double':
				return 'REAL';
			case 'string':
			case 'text':
      case 'password':
				return 'TEXT';
			case 'timestamp':
				return 'INTEGER';
			default:
        return parent::getPrimitive($colspec);
		}
	}
	
  public function startUpgrade($spec) {
    parent::startUpgrade($spec);
    $this->oldspec = $this->spec;
    $this->can_upgrade = true;
    $this->map = array();
  }
  
  public function finishUpgrade($newspec) {
		if($this->can_upgrade) return parent::finishUpgrade($newspec);
		
    // need to rewrite the sql
    $this->sql = array();
    
		// create the new table
		$this->spec=$newspec;
		if($this->spec['table']==$this->oldspec['table']) $this->spec['table'].='_new';
		$this->createTable();
		
    // create a select statement for the data copy
		$columns=array();
		foreach($this->spec['columns'] as $key=>$spec) {
      if(isset($this->map[$key])) {
				$columns[]=$this->quoteIdentifier($this->map[$key]);
			} else if(isset($this->oldspec['columns'][$key])) { // straight data copy
				$columns[]=$this->quoteIdentifier($spec['db_column']);
			} else { // use NULL
				$columns[]='NULL';
			}
		}
		$this->sql[] = "INSERT INTO {$this->quoteIdentifier($this->spec['table'])} SELECT ".implode(', ',$columns)." FROM {$this->quoteIdentifier($this->oldspec['table'])}";
		
		// drop the old table
		$this->sql[] = "DROP TABLE {$this->quoteIdentifier($this->oldspec['table'])}";
		
		// rename the new table if required
		if($this->spec['table']!=$newspec['table']) {
			$this->renameTable($newspec['table']);
		}
    
    parent::finishUpgrade($newspec);
	}
  
	public function addColumn($columnname, $newspec, $after=false) {
		$this->can_upgrade=false;
	}
	
	public function alterColumn($columnname, $newspec) {
		// stuff will map anyway so we just set the flag
		$this->can_upgrade=false;
	}
	
	public function renameColumn($oldcolumnname, $newcolumnname) {
    // remember the old to new column mappings
		$this->map[$newcolumnname]=$oldcolumnname;
		$this->can_upgrade=false;
	}
	
	public function dropColumn($columnname) {
		$this->can_upgrade=false;
	}
}
