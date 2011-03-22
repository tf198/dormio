<?
/**
* Factory for SQL implementation specific schema management
* Abstracts tables as basic PHP arrays.
* Key features: automatic upgrade path, driver specific options
*/
class Dormio_Schema {
	
	/**
	* Private constructor to enforce factory usage
	*/
	private function __construct() {}
	
	public static function factory($lang, $spec) {
		// allow multiple languages to use the same schema rules
		switch($lang) {
			case 'mysql':
			case 'mysqli':
				$lang='generic'; // our base language based on MySQL
				break;
		}
		$classname="Dormio_Schema_{$lang}";
		// allow other autoloaders to try and find a driver first
		if(class_exists($classname, true)) {
			return new $classname($spec);
		} else {
			throw new Dormio_Schema_Exception("No Dormio_Schema driver file found for '{$lang}'");
		}
	}
}

interface Dormio_Schema_Driver {
	public function __construct($spec);
	// Table operations
	public function createTable();
	public function renameTable($newname);
	public function upgradeTo($newspec);
	public function dropTable();
	// Column Operations
	public function addColumn($columnname, $spec, $after=false);
	public function dropColumn($columnname);
	public function alterColumn($columnname, $spec);
	public function renameColumn($oldcolumn, $newcolumn);
	// Index Operations
	public function addIndex($indexname, $spec);
	public function dropIndex($indexname);
}

class Dormio_Schema_Exception extends RuntimeException {}

/**
* Generic is actualy MYSQL grammar
*/
class Dormio_Schema_Generic implements Dormio_Schema_Driver {
	
	protected $spec;
	
	protected $version='mysql';
	
	public function __construct($spec) {
		// check new spec
		if(!isset($spec['table'])) throw new Dormio_Schema_Exception("Require 'table' key");
		if(!isset($spec['columns'])) throw new Dormio_Schema_Exception("Require 'columns' key");
		$this->spec=$spec;
	}
  
  public function schema() {
    return $this->spec;
  }
	
	public function quoteIdentifier($identifier) {
		return '`'.str_replace('`','``',$identifier).'`';
	}
	
	public function quoteValue($value, $type) {
		switch($type) {
			case 'string':
			case 'text':
				return "'".str_replace("'","''",$value)."'";
			default;
				return (string)$value;
		}
	}
	
	public function getPrimitive($colspec) {
		switch($colspec['type']) {
			case 'ident':
				return 'SERIAL';
			case 'integer':
				$size=(isset($colspec['size'])) ? $colspec['size'] : 32;
				return "INTEGER({$size})".((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'boolean':
				return "TINYINT(1)";
			case 'float':
				return 'FLOAT'.((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'double':
				return 'DOUBLE'.((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'string':
				$size=(isset($colspec['size'])) ? $colspec['size'] : 255;
				return "VARCHAR({$size})";
			case 'text':
				return "TEXT";
			case 'date':
				return 'DATE';
			case 'timestamp':
				return 'TIMESTAMP';
      case 'foreignkey':
        return 'INTEGER';
			default:
				throw new Dormio_Schema_Exception('No such type: '.$colspec['type']);
		}
	}
	
	public function getType($colspec) {
		if(!isset($colspec['type'])) throw new Dormio_Schema_Exception('Bad type specification');
		$primitive=$this->getPrimitive($colspec);
		if($colspec['type']=='ident') return $primitive;
		if(isset($colspec['notnull'])) $primitive.=' NOT NULL';
		if(isset($colspec['unique'])) $primitive.=' UNIQUE';
		if(isset($colspec['default'])) $primitive.=' DEFAULT '.$this->quoteValue($colspec['default'],$colspec['type']);
		return $primitive;
	}
	
	public function getColumns() {
		$result=array();
		foreach($this->spec['columns'] as $colspec) {
			$result[]=$this->quoteIdentifier($colspec['sql_column']).' '.$this->getType($colspec);
		}
		return $result;
	}
	
	// Table operations
	public function createTable($drop=false) {
    $sql = ($drop) ? $this->dropTable() : array();
		$sql[] = "CREATE TABLE {$this->quoteIdentifier($this->spec['table'])} (".implode(', ',$this->getColumns($this->spec)).")";
		if(isset($this->spec['indexes'])) {
			foreach($this->spec['indexes'] as $index_name=>$index_spec) {
				$sql=array_merge($sql, $this->createIndex($index_name, $index_spec));
			}
		}
		//echo implode("\n", $sql)."\n";
		return $sql;
	}
	
	public function renameTable($newname) {
		$sql="ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} RENAME TO {$this->quoteIdentifier($newname)}";
		$this->spec['table']=$newname;
		return array($sql);
	}
	
	public function upgradeTo($newspec) {
		$sql=array();
		
		$orig=$this->spec;
		
		// first check for a table rename
		if($newspec['table']!=$this->spec['table']) $sql=array_merge($sql, $this->renameTable($newspec['table']));
		
		// calculate column differences
		$removed=array_diff_assoc($this->spec['columns'], $newspec['columns']);
		$added=array_diff_assoc($newspec['columns'], $this->spec['columns']);
		
		// try and match any removed against added and do a rename
		foreach($removed as $colname => $colspec) {
			if(($match=array_search($colspec, $added))!==false) {
				$sql=array_merge($sql, $this->renameColumn($colname, $match));
				unset($removed[$colname]);
				unset($added[$match]);
			}
		}
		
		// remove old columns
		foreach($removed as $col=>$colspec) $sql=array_merge($sql, $this->dropColumn($col));
		// add new ones at the correct position
		$prev=0;
		foreach($newspec['columns'] as $col=>$colspec) {
			if(isset($added[$col])) $sql=array_merge($sql, $this->addColumn($col, $colspec, $prev));
			$prev=$col;
		}
		
		// check that the column names are now identical
		if(array_keys($this->spec['columns'])!==array_keys($newspec['columns'])) {
			throw new Dormio_Schema_Exception('Failed to migrate column names');
		}
		
		// alter any column specifications
		foreach($newspec['columns'] as $colname => $colvalue) {
			if($this->spec['columns'][$colname]!==$colvalue) {
				$sql=array_merge($sql,$this->alterColumn($colname, $colvalue));
			}
		}
		
		// check that our schema has got to the correct place
		if($this->spec!==$newspec) {
			var_dump($this->spec, $newspec);
			throw new Dormio_Schema_Exception('Failed to generate upgrade route');
		}
		
		return $sql;
	}
	
	public function dropTable() {
		$sql="DROP TABLE IF EXISTS {$this->quoteIdentifier($this->spec['table'])}";
		return array($sql);
	}
	
	// Column Operations
	public function addColumn($columnname, $colspec, $after=false) {
		$sql="ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} ADD COLUMN {$this->quoteIdentifier($columnname)} {$this->getType($colspec)}";
		if($after!==false) {
			$sql.=($after===0) ? ' FIRST' : ' AFTER '.$this->quoteIdentifier($after);
		}
		$this->insertAfter($this->spec['columns'], $columnname, $colspec, $after);
		return array($sql);
	}
	
	public function alterColumn($columnname, $newspec, $after=false) {
		if(!isset($this->spec['columns'][$columnname])) throw new Dormio_Schema_Exception("Column '{$columnname}' doesn't exist");
		$sql="ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} MODIFY COLUMN {$this->quoteIdentifier($columnname)} {$this->getType($newspec)}";
		if($after!==false) {
			$sql.=($after===0) ? ' FIRST' : ' AFTER '.$this->quoteIdentifier($after);
		}
		$this->insertAfter($this->spec['columns'], $columnname, $newspec, $after);
		return array($sql);
	}
	
	public function renameColumn($oldcolumnname, $newcolumnname) {
		$sql="ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} CHANGE COLUMN {$this->quoteIdentifier($oldcolumnname)} {$this->quoteIdentifier($newcolumnname)} {$this->getType($this->spec['columns'][$oldcolumnname])}";
		$this->insertAfter($this->spec['columns'], $newcolumnname, $this->spec['columns'][$oldcolumnname], $oldcolumnname);
		unset($this->spec['columns'][$oldcolumnname]);
		return array($sql);
	}
	
	public function dropColumn($columnname) {
		if(!isset($this->spec['columns'][$columnname])) throw new Dormio_Schema_Exception("Column '{$columnname}' doesn't exist");
		$sql="ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} DROP COLUMN {$this->quoteIdentifier($columnname)}";
		unset($this->spec['columns'][$columnname]);
		return array($sql);
	}
	
	// Index Operations
	public function createIndex($indexname, $spec) {
		$cols=array();
		foreach($spec as $name=> $dir) $cols[]=$this->quoteIdentifier($name).' '.(($dir) ? 'ASC' : 'DESC');
		$sql="CREATE {$this->specific($spec,'modifiers')} INDEX {$this->quoteIdentifier($this->spec['table'].'_'.$indexname)} {$this->specific($spec,'index_type', 'USING %s')} ON {$this->quoteIdentifier($this->spec['table'])} (".implode(', ',$cols).")";
		$sql=preg_replace('/ +/',' ', $sql);
		return array($sql);
	}
	
	public function addIndex($indexname, $spec) {
		if(isset($this->spec['indexes'][$indexname])) throw new Dormio_Schema_Exception("Index '{$indexname}' already exisits");
		$sql=$this->createIndex($indexname, $spec);
		$this->spec['indexes'][$indexname]=$spec;
		return $sql;
	}
	
	public function dropIndex($indexname) {
		if(!isset($this->spec['indexes'][$indexname])) throw new Dormio_Schema_Exception("Index '{$indexname}' doesn't exist");
		$sql="DROP INDEX {$this->quoteIdentifier($this->spec['table'].'_'.$indexname)} ON {$this->quoteIdentifier($this->spec['table'])}";
		unset($this->spec['indexes'][$indexname]);
		return array($sql);
	}
	
	protected function specific($spec, $name, $formatter='%s') {
		return (isset($spec[$this->version][$name])) ? sprintf($formatter, $spec[$this->version][$name]) : '';
	}
	
	public function insertAfter(&$arr,  $key, $value, $after=false) {
		// insert at end is easy
		if($after===false) {
			$arr[$key]=$value;
			return true;
		}
		// insert at start is pretty easy as well
		if($after===0) {
			$arr=array_merge(array($key => $value), $arr);
			return true;
		}
		// insert after a specific key is a little more tricky
		$temp=array();
		$success=false;
		foreach($arr as $arr_key=>$arr_value) {
			$temp[$arr_key]=$arr_value;
			if($arr_key===$after) {
				$temp[$key]=$value;
				$success=true;
			}
		}
		if($success) {
			$arr=$temp;
			return true;
		} else {
			return false;
		}
	}
	
	public function batchExecute($pdo, $statements, $transaction=true) {
		if($transaction) $pdo->beginTransaction();
		$updated=0;
		try {
			for($i=0,$c=count($statements); $i<$c; $i++) {
				$c_updated=$pdo->exec($statements[$i]);
				if($c_updated===false) {
					throw new Dormio_Schema_Exception("Operation failed: '{$statements[$i]} [".print_r($pdo->errorInfo(),true)."]");
				}
				$updated+=$c_updated;
			}
			if($transaction) $pdo->commit();
		} catch(Exception $e) {
			if($transaction) $pdo->rollBack();
			throw $e;
		}
		return $updated;
	}
}
?>