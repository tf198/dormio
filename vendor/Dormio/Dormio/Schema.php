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
 * @subpackage Schema
 */

class_exists('Dormio_Config');

/**
 * Factory for SQL implementation specific schema management
 * Abstracts tables as basic PHP arrays.
 * Key features: automatic upgrade path, driver specific options
 * @package Dormio
 * @subpackage Schema
 * @example schema.php
 * @tutorial example.pkg#schema
 */
class Dormio_Schema {
	
	public static function fromConfig($config) {
		$e = new Dormio_Config_Entity('Temp', $config, null);
		return self::fromEntity($e);
	}
	
	public static function fromEntity($entity) {
		$spec = $entity->asArray();
		foreach($spec['fields'] as $key=>$colspec) {
			if($colspec['is_field']) $spec['columns'][$key] = $colspec;
		}
		unset($spec['fields'], $spec['name'], $spec['verbose'], $spec['model_class']);
		return $spec;
	}
	
	/**
	 * Get a new Schema instance
	 * @param  string  $lang     		dialect
	 * @param  Dormio_Config_Entity   $entity
	 * @return Dormio_Schema_Driver
	 */
	public static function factory($lang, $schema) {
		// allow multiple languages to use the same schema rules
		switch ($lang) {
			case 'mysql':
			case 'mysqli':
				$lang = 'generic'; // our base language based on MySQL
				break;
		}

		$classname = "Dormio_Schema_{$lang}";
		// allow other autoloaders to try and find a driver first
		if (class_exists($classname, true)) {
			return new $classname($schema);
		} else {
			throw new Dormio_Schema_Exception("No Dormio_Schema driver file found for '{$lang}'");
		}
	}
}

/**
 * @package Dormio
 * @subpackage Schema
 */
interface Dormio_Schema_Driver {

	public function __construct(array $spec);

	// SQL generation
	public function createSQL();

	public function upgradeSQL($newspec);

	// Code generation
	public function upgradePHP($newspec);

	// Table operations
	public function createTable($drop=false);

	public function renameTable($newname);

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

/**
 * @package Dormio
 * @subpackage Exception
 */
class Dormio_Schema_Exception extends Exception {}


/**
 * Generic is actualy MYSQL grammar
 * @package Dormio
 * @subpackage Schema
 */
class Dormio_Schema_Generic implements Dormio_Schema_Driver {

	/**
	 * @var multitype:mixed
	 */
	public $spec;

	protected $version = 'mysql';

	public function __construct(array $spec) {
		$this->spec = $spec;
	}

	public function quoteIdentifier($identifier) {
		return '`' . str_replace('`', '``', $identifier) . '`';
	}

	public function quoteValue($value, $type) {
		switch ($type) {
			case 'string':
			case 'text':
				return "'" . str_replace("'", "''", $value) . "'";
			default;
			return (string) $value;
		}
	}

	public function getPrimitive($colspec) {
		switch ($colspec['type']) {
			case 'ident':
				return 'SERIAL';
			case 'integer':
				$size = (isset($colspec['size'])) ? $colspec['size'] : 32;
				return "INTEGER({$size})" . ((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'boolean':
				return "TINYINT(1)";
			case 'float':
				return 'FLOAT' . ((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'double':
				return 'DOUBLE' . ((isset($colspec['unsigned'])) ? ' UNSIGNED' : '');
			case 'string':
			case 'password':
				$size = (isset($colspec['size'])) ? $colspec['size'] : 255;
				return "VARCHAR({$size})";
			case 'text':
				return "TEXT";
			case 'date':
				return 'DATE';
			case 'timestamp':
				return 'TIMESTAMP';
			case 'ipv4address':
				return 'INTEGER(32)';
			case 'foreignkey':
			case 'onetoone':
				return 'INTEGER';
			default:
				throw new Dormio_Schema_Exception('No such type: ' . $colspec['type']);
		}
	}

	public function getType($colspec) {
		if (!isset($colspec['type']))
			throw new Dormio_Schema_Exception('Bad type specification');
		$primitive = $this->getPrimitive($colspec);
		if ($colspec['type'] == 'ident')
			return $primitive;
		if (!isset($colspec['null_ok']) || $colspec['null_ok'] == false)
			$primitive.=' NOT NULL';
		if (isset($colspec['unique']))
			$primitive.=' UNIQUE';
		if (isset($colspec['default']))
			$primitive.=' DEFAULT ' . $this->quoteValue($colspec['default'], $colspec['type']);
		return $primitive;
	}

	public function getColumns() {
		$result = array();
		foreach ($this->spec['columns'] as $field=>$colspec) {
			$result[] = $this->quoteIdentifier($colspec['db_column']) . ' ' . $this->getType($colspec);
		}
		return $result;
	}

	public function createSQL($drop=false) {
		$this->startUpgrade(array());
		$this->createTable($drop);
		$this->finishUpgrade($this->spec);
		return $this->sql;
	}

	// Table operations
	public function createTable($drop=false) {
		if ($drop)
			$this->dropTable();
		$this->sql[] = "CREATE TABLE {$this->quoteIdentifier($this->spec['table'])} (" . implode(', ', $this->getColumns()) . ")";
		foreach ($this->spec['indexes'] as $index_name => $index_spec) {
			$this->sql[] = $this->createIndex($index_name, $index_spec);
		}
	}

	public function renameTable($newname) {
		$this->sql[] = "ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} RENAME TO {$this->quoteIdentifier($newname)}";
		$this->spec['table'] = $newname;
	}

	private function _upgradePath($newspec) {
		$actions = array();

		// first check for a table rename
		if ($newspec['table'] != $this->spec['table'])
			$actions[] = array('renameTable', $newspec['table']);

		// calculate column differences
		$removed = array_diff_assoc($this->spec['columns'], $newspec['columns']);
		$added = array_diff_assoc($newspec['columns'], $this->spec['columns']);

		// try and match any removed against added and do a rename
		foreach ($removed as $colname => $colspec) {
			if (($match = array_search($colspec, $added)) !== false) {
				$actions[] = array('renameColumn', $colname, $match);
				unset($removed[$colname]);
				unset($added[$match]);
			}
		}

		// remove old columns
		foreach ($removed as $col => $colspec)
			$actions[] = array('dropColumn', $col);
		// add new ones at the correct position
		$prev = 0;
		foreach ($newspec['columns'] as $col => $colspec) {
			if (isset($added[$col]))
				$actions[] = array('addColumn', $col, $colspec, $prev);
			$prev = $col;
		}

		// alter any column specifications
		foreach ($newspec['columns'] as $colname => $colvalue) {
			if (isset($this->spec['columns'][$colname]) && $this->spec['columns'][$colname] !== $colvalue) {
				$actions[] = array('alterColumn', $colname, $colvalue);
			}
		}

		return $actions;
	}

	public function createPHP() {
		$output = array('<?php');

		$output[] = '$new_schema = ' . var_export($this->spec, true) . ';';
		$output[] = '$lang = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);';
		$output[] = '$schema = Dormio_Schema::factory($lang, $new_schema);';
		$output[] = '$schema->startUpgrade($new_schema);';
		$output[] = '$schema->createTable();';
		$output[] = '$schema->finishUpgrade($new_schema);';
		$output[] = '$schema->commitUpgrade($pdo);';
		$output[] = 'return $new_schema;';

		$output[] = "?>\n";
		return implode("\n", $output);
	}

	public function upgradePHP($newspec) {
		$actions = $this->_upgradePath($newspec);

		if(count($actions)==0) return null;

		$output = array('<?php');
		
		$output[] = '$old_schema = ' . var_export($this->spec, true) . ';';
		$output[] = '$new_schema = ' . var_export($newspec, true) . ';';

		$output[] = '$lang = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);';
		$output[] = '$schema = Dormio_Schema::factory($lang, $old_schema);';
		$output[] = '$schema->startUpgrade($new_schema);';

		foreach ($actions as $action) {
			$params = array();
			for ($i = 1; $i < count($action); $i++) {
				$params[] = var_export($action[$i], true);
			}
			$output[] = '$schema->' . $action[0] . '(' . implode(', ', $params) . ');';
		}

		$output[] = '$schema->finishUpgrade($new_schema);';
		$output[] = '$schema->commitUpgrade($pdo);';
		$output[] = 'return $new_schema;';
		$output[] = "?>\n";

		return implode("\n", $output);
	}

	public function startUpgrade($spec) {
		$this->sql = array();
	}

	public function finishUpgrade($spec) {
		if ($this->spec !== $spec) {
			var_dump($this->spec, $spec);
			throw new Dormio_Schema_Exception('Schema specification not valid');
		}
	}

	public function commitUpgrade($pdo) {
		$this->batchExecute($pdo, $this->sql, false);
	}

	public function upgradeSQL($newspec) {
		
		$actions = $this->_upgradePath($newspec);

		$this->startUpgrade($this->spec);

		foreach ($actions as $action) {
			$method = array_shift($action);
			call_user_func_array(array($this, $method), $action);
		}

		$this->finishUpgrade($newspec);

		return $this->sql;
	}

	public function dropTable() {
		$this->sql[] = "DROP TABLE IF EXISTS {$this->quoteIdentifier($this->spec['table'])}";
	}

	// Column Operations
	public function addColumn($columnname, $colspec, $after=false) {
		$sql = "ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} ADD COLUMN {$this->quoteIdentifier($columnname)} {$this->getType($colspec)}";
		if ($after !== false) {
			$sql.=($after === 0) ? ' FIRST' : ' AFTER ' . $this->quoteIdentifier($after);
		}
		$this->insertAfter($this->spec['columns'], $columnname, $colspec, $after);
		$this->sql[] = $sql;
	}

	public function alterColumn($columnname, $newspec, $after=false) {
		if (!isset($this->spec['columns'][$columnname]))
			throw new Dormio_Schema_Exception("Column '{$columnname}' doesn't exist");
		$sql = "ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} MODIFY COLUMN {$this->quoteIdentifier($columnname)} {$this->getType($newspec)}";
		if ($after !== false) {
			$sql.=($after === 0) ? ' FIRST' : ' AFTER ' . $this->quoteIdentifier($after);
		}
		$this->sql[] = $sql;
		$this->insertAfter($this->spec['columns'], $columnname, $newspec, $after);
	}

	public function renameColumn($oldcolumnname, $newcolumnname) {
		$this->sql[] = "ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} CHANGE COLUMN {$this->quoteIdentifier($oldcolumnname)} {$this->quoteIdentifier($newcolumnname)} {$this->getType($this->spec['columns'][$oldcolumnname])}";
		$this->insertAfter($this->spec['columns'], $newcolumnname, $this->spec['columns'][$oldcolumnname], $oldcolumnname);
		unset($this->spec['columns'][$oldcolumnname]);
	}

	public function dropColumn($columnname) {
		if (!isset($this->spec['columns'][$columnname]))
			throw new Dormio_Schema_Exception("Column '{$columnname}' doesn't exist");
		$this->sql[] = "ALTER TABLE {$this->quoteIdentifier($this->spec['table'])} DROP COLUMN {$this->quoteIdentifier($columnname)}";
		unset($this->spec['columns'][$columnname]);
	}

	// Index Operations
	private function createIndex($indexname, $spec) {
		$cols = array();
		foreach ($spec as $name => $dir)
			$cols[] = $this->quoteIdentifier($name) . ' ' . (($dir) ? 'ASC' : 'DESC');
		$sql = "CREATE {$this->specific($spec, 'modifiers')} INDEX {$this->quoteIdentifier($this->spec['table'] . '_' . $indexname)} {$this->specific($spec, 'index_type', 'USING %s')} ON {$this->quoteIdentifier($this->spec['table'])} (" . implode(', ', $cols) . ")";
		$sql = preg_replace('/ +/', ' ', $sql);
		return $sql;
	}

	public function addIndex($indexname, $spec) {
		if (isset($this->spec['indexes'][$indexname]))
			throw new Dormio_Schema_Exception("Index '{$indexname}' already exisits");
		$this->sql[] = $this->createIndex($indexname, $spec);
		$this->spec['indexes'][$indexname] = $spec;
	}

	public function dropIndex($indexname) {
		if (!isset($this->spec['indexes'][$indexname]))
			throw new Dormio_Schema_Exception("Index '{$indexname}' doesn't exist");
		$this->sql[] = "DROP INDEX {$this->quoteIdentifier($this->spec['table'] . '_' . $indexname)} ON {$this->quoteIdentifier($this->spec['table'])}";
		unset($this->spec['indexes'][$indexname]);
	}

	protected function specific($spec, $name, $formatter='%s') {
		return (isset($spec[$this->version][$name])) ? sprintf($formatter, $spec[$this->version][$name]) : '';
	}

	public function insertAfter(&$arr, $key, $value, $after=false) {
		// insert at end is easy
		if ($after === false) {
			$arr[$key] = $value;
			return true;
		}
		// insert at start is pretty easy as well
		if ($after === 0) {
			$arr = array_merge(array($key => $value), $arr);
			return true;
		}
		// insert after a specific key is a little more tricky
		$temp = array();
		$success = false;
		foreach ($arr as $arr_key => $arr_value) {
			$temp[$arr_key] = $arr_value;
			if ($arr_key === $after) {
				$temp[$key] = $value;
				$success = true;
			}
		}
		if ($success) {
			$arr = $temp;
			return true;
		} else {
			return false;
		}
	}

	public function batchExecute($pdo, $statements, $transaction=true) {
		if ($transaction)
			$pdo->beginTransaction();
		$updated = 0;
		try {
			for ($i = 0, $c = count($statements); $i < $c; $i++) {
				$c_updated = $pdo->exec($statements[$i]);
				if ($c_updated === false) {
					throw new Dormio_Schema_Exception("Operation failed: '{$statements[$i]} [" . print_r($pdo->errorInfo(), true) . "]");
				}
				$updated+=$c_updated;
			}
			if ($transaction)
				$pdo->commit();
		} catch (Exception $e) {
			if ($transaction)
				$pdo->rollBack();
			throw $e;
		}
		return $updated;
	}

}
