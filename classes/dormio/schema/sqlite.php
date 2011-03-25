<?php
/**
* SQLite Schema.
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
* @subpackage schema
*/

/**
* @package dormio
* @subpackage schema
*/
class Dormio_Schema_SQLite extends Dormio_Schema_Generic {

	protected $version='sqlite';
	
	private $can_upgrade=true;
	private $map;
	
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
	
	public function upgradeTo($newspec) {
		$this->can_upgrade=true;
		$this->map=array();
		// see whether we can use the standard upgrade process
		$oldspec=$this->spec;
		$sql=parent::upgradeTo($newspec);
		if($this->can_upgrade) {
			return $sql;
		}
		
		// previous step not wasted as it at least determined the column renames
		
		// create the new table
		$this->spec=$newspec;
		if($this->spec['table']==$oldspec['table']) $this->spec['table'].='_new';
		$sql=$this->createTable();
		
    // create a select statement for the data copy
		$columns=array();
		foreach($this->spec['columns'] as $key=>$spec) {
      if(isset($this->map[$key])) {
				$columns[]=$this->quoteIdentifier($this->map[$key]);
			} else if(isset($oldspec['columns'][$key])) { // straight data copy
				$columns[]=$this->quoteIdentifier($spec['sql_column']);
			} else { // use NULL
				$columns[]='NULL';
			}
		}
		$sql[]="INSERT INTO {$this->quoteIdentifier($this->spec['table'])} SELECT ".implode(', ',$columns)." FROM {$this->quoteIdentifier($oldspec['table'])}";
		
		// drop the old table
		$sql[]="DROP TABLE {$this->quoteIdentifier($oldspec['table'])}";
		
		// rename the new table if required
		if($this->spec['table']!=$newspec['table']) {
			$sql=array_merge($sql,$this->renameTable($newspec['table']));
		}
		
		return $sql;
	}
	
	public function addColumn($columnname, $newspec, $after=false) {
		$this->can_upgrade=false;
		return parent::addColumn($columnname, $newspec, $after);
	}
	
	public function alterColumn($columnname, $newspec) {
		// stuff will map anyway so we just set the flag
		$this->can_upgrade=false;
		return parent::alterColumn($columnname, $newspec);
	}
	
	public function renameColumn($oldcolumnname, $newcolumnname) {
		$this->map[$newcolumnname]=$oldcolumnname;
		$this->can_upgrade=false;
		return parent::renameColumn($oldcolumnname, $newcolumnname);
	}
	
	public function dropColumn($columnname) {
		$this->can_upgrade=false;
		return parent::dropColumn($columnname);
	}
}
?>