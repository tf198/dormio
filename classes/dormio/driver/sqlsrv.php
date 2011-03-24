<?php
/**
* PDO Wrapper for SQLSVR
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
* @subpackage driver
*/

/**
* Class to emulate a PDO object for sqlsrv functions
*
* Emulates a significant subset of PDO methods to allow PDO development using
* SQLServer until Microsoft release a PDO driver of their own
*
* Thanks to Raymond Irvine <xwisdom (at) yahoo ! com>, for fixes to a couple of the regexs
*
* @package dormio
* @subpackage driver
*/
class Dormio_Driver_SQLSVR {
	private $dbh=null;
	private $errors=null;
	public static $attributes=array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DRIVER_NAME => 'sqlsrv',
		PDO::ATTR_CONNECTION_STATUS => false
	);
	/**
	* Create a new fake PDO object
	*
	* @param		string	$dsn				sqlsrv:servername;Database=dbname;UID=user;PWD=pass
	* @param		string	$username		If present replaces UID in dsn
	* @param		string	$password		If presend replaces PWD in dsn
	* @param		array	$driveroptions	If present appended to options
	*/
	public function __construct($dsn, $username=null, $password=null, $driveroptions=null) {
		// check the sqlsrv extension is installed
		if(!function_exists('sqlsrv_connect')) return self::report_error('sqlsrv extension not installed');
		// parse the dsn
		$dsn_parts=explode(';',$dsn);
		$pos=strpos($dsn_parts[0],':');
		if(!$pos) return self::report_error('Bad DSN');
		$server=substr($dsn_parts[0],$pos+1);
		$options=array();
		$c=count($dsn_parts);
		for($i=1;$i<$c;$i++) {
			list($key,$value)=explode('=',$dsn_parts[$i]);
			$options[$key]=$value;
		}
		// override dsn entries with optional params
		if($username) $options['UID']=$username;
		if($password) $options['PWD']=$password;
		if($driveroptions && is_array($driveroptions)) $options=array_merge($options,$driveroptions);
		// connect to the db
		$this->dbh=sqlsrv_connect($server,$options);
		if(!$this->dbh) return $this->handle_error();
		$this->errors=null;
		self::$attributes[PDO::ATTR_CONNECTION_STATUS]=true;
		// set some attributes
		$server=sqlsrv_server_info($this->dbh);
		self::$attributes[PDO::ATTR_SERVER_VERSION]=$server['SQLServerVersion'];
		self::$attributes[PDO::ATTR_SERVER_INFO]=$server['SQLServerName'];
		self::$attributes[PDO::ATTR_CLIENT_VERSION]=sqlsrv_client_info($this->dbh);
	}
	
	/**
	* Execute a one time SELECT query
	*
	* @param		string		$tsql	SQL to be executed
	* @return	PDOStatement
	*/
	public function query($tsql) {
		$stmt=new sqlsrv_pdo_statement($this->dbh, $tsql, sqlsrv_pdo_statement::TYPE_QUERY);
		return $stmt;
	}
	
	/**
	* Execute an UPDATE or INSERT
	*
	* @param	  string	$tsql	SQL to be executed
	* @return	int		Number of rows affected 
	*/
	public function exec($tsql) {
		$stmt=sqlsrv_query($this->dbh, $tsql);
		if(!$stmt) return $this->handle_error();
		$rows=sqlsrv_rows_affected($stmt);
		if($rows===false) return $this->handle_error();
		$this->errors=null;
		return $rows;
	}
		
	/**
	* Create a prepared statement that can contain ? mark replacements
	*
	* @param		string	$tsql			SQL to be prepared
	* @param		array	$driver_options	Not currently implemented
	* @return	PDOStatement		or FALSE on error
	*/
	public function prepare($tsql, $driver_options=false) {
		if($driver_options) return self::report_error('Driver options not implemented');
		$stmt=new sqlsrv_pdo_statement($this->dbh, $tsql, sqlsrv_pdo_statement::TYPE_PREPARED);
		$this->errors=null;
		return $stmt;
	}
	
	/** 
	* Start a new transaction
	*
	* @return	bool		TRUE on success
	*/
	public function beginTransaction() {
		return sqlsrv_begin_transaction($this->dbh);
	}

	/** 
	* Commit a transaction
	*
	* @return	bool		TRUE on success
	*/
	public function commit() {
		return sqlsrv_commit($this->dbh);
	}
	
	/** 
	* Roll back a transaction
	*
	* @return	bool		TRUE on success
	*/
	public function rollBack() {
		return sqlsrv_rollback($this->dbh);
	}
	
	/**
	* Retrieve any error information (use multiple calls to get all info);
	*
	* @return	array	With keys 'SQLSTATE', 'code' and 'message' or null if no error
	*/
	public function errorInfo() {
		return (is_array($this->errors)) ? array_pop($this->errors) : null;
	}
	
	/**
	* Retrieve the last error code
	*
	* @return	string	SQLSTATE code or null if no error
	*/
	public function errorCode() {
		return (is_array($this->errors)) ? $this->errors[count($this->errors)-1]['SQLSTATE'] : null;
	}
	
	/** 
	* Get the last inserted id
	*
	* @param		string	$name	Optional table to query
	* @return	int
	*/
	public function lastInsertId($name=null) {
		$tsql=($name) ? "SELECT IDENT_CURRENT('{$name}')" : 'SELECT @@IDENTITY';
		$stmt=sqlsrv_query($this->dbh, $tsql);
		if(!$stmt) return self::report_error('Failed to execute identity sql');
		$row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_NUMERIC);
		return (isset($row[0])) ? $row[0] : 0;
	}
	
	/**
	* Quote a string based on type
	*
	* @param		mixed	$string			The parameter to be quoted
	* @param		int		$parameter_type	PDO::PARAM_XXX const
	* @return	string	The input quoted correctly
	*/
	public function quote($string, $parameter_type=PDO::PARAM_STR) {
		switch($parameter_type) {
			case PDO::PARAM_STR:
				return '\''.mysql_escape_string($string).'\'';
			default:
				return $string;
		}
	}
	
	/**
	* Set an attribute
	*
	* @param		int		$attribute	One of the PDO::ATTR_XXX constants
	* @param		mixed	$value		The value to set
	* @return	bool		TRUE on success
	*/
	public static function setAttribute($attribute, $value) {
		if(!isset(self::$attributes[$attribute])) trigger_error('Unsupported attribute: '.$attribute);
		self::$attributes[$attribute]=$value;
		return true;
	}
	
	/**
	* Get the value of an attribute
	*
	* @param		int		$attribute	One of the PDO::ATTR_XXX constants
	* @return	mixed	The value or null if not set
	*/
	public static function getAttribute($attribute) {
		return (isset(self::$attributes[$attribute])) ? self::$attributes[$attribute] : null;
	}
	
	/**
	* A generic function to set the errors object and generate the correct response
	*/
	private function handle_error() {
		$this->errors=sqlsrv_errors(SQLSRV_ERR_ERRORS);
		$message=($this->errors) ? $this->errors[count($this->errors)-1]['message'] : 'Unknown error';
		return self::report_error($this->errors);
	}
	
	/**
	* A public method to deal with error situations based on ERRMODE
	*/
	public static function report_error(&$errors) {
		$message="";
		for($i=0, $c=count($errors);$i<$c;$i++) {
			$message.="#{$i} {$errors[$i]['message']}<br/>\n";
		}
		switch(self::getAttribute(PDO::ATTR_ERRMODE)) {
			case PDO::ERRMODE_EXCEPTION:
				throw new PDOException($message);
			case PDO::ERRMODE_WARNING:
				trigger_error($message, E_USER_WARNING);
		}
		return false;
	}
	
	/**
	* Close the database connection
	*/
	public function __destruct() {
		if($this->dbh) sqlsrv_close($this->dbh);
	}	
}

/**
* Class to emulate a PDOStatement for sqlsrv functions
* @author Tris Forster <tris.git@tfconsulting.com.au>
* @package dormio
* @subpackage driver
* @access private
*/
class sqlsrv_pdo_statement{
	private $stmt=null;
	private $tsql=null;
	private $errors=null;
	private $params=null;
	private $named=null;
	private $dbh=null;
	private $attributes=array(
		'FETCHMODE' => PDO::FETCH_BOTH
	);
	private $meta=false;
	
	const TYPE_QUERY=1;
	const TYPE_PREPARED=2;
	
	/**
	* Create a new fake PDOStatement object
	*
	* @param		resource		$dbh		Pointer to an open sqlsrv instance
	*/
	public function __construct(&$dbh, $tsql, $type) {
		if(!$dbh) return sqlsrv_pdo_driver::report_error('Invalid database handle');
		$this->dbh=&$dbh;
		switch($type) {
			case self::TYPE_QUERY:
				$this->stmt=sqlsrv_query($this->dbh, $tsql);
				if($this->stmt===false) return $this->handle_error();
				break;
			case self::TYPE_PREPARED:
				$this->params=array();
				$param_count=substr_count($tsql,'?');
				if($param_count>0) {
					for($i=0;$i<$param_count;$i++) $this->params[$i]=0;
				} else {
					// Raymond: fixed named placeholder regex
					//if(preg_match_all('/[= ]:([a-z]+)\b/',$tsql,$matches)) {
					if(preg_match_all('/[\,= \(]:([a-z]+)\b/',$tsql,$matches)) {
						$this->named=array();
						for($i=0, $c=count($matches[1]); $i<$c; $i++) {
							$this->params[$i]=0;
							$this->named[$i]=$matches[1][$i];
							// Raymond: Added boundary to placeholder match
							//$tsql=str_replace(':'.$matches[1][$i],'?',$tsql);
							$regx = '/\:'.$matches[1][$i].'\b/';
							$tsql=preg_replace($regx,'?',$tsql);
						}
					}
				}
				$this->tsql=$tsql;
				break;
			default:
				return sqlsrv_pdo_driver::report_error('Unknown sqlsrv_pdo_statement type');
		}
	}
	
	/**
	* Bind a variable to a placeholder
	*
	* @param		int		$parameter		Parameter number, starting from 1
	* @param		mixed	&$variable		Reference to the variable
	* @param		int		$data_type		Not implemented
	* @param		int		$length			Not implemented
	* @param		array	$driver_options	Not implemented
	* @return	bool		TRUE on success
	*/
	public function bindParam($parameter, &$variable, $data_type=false, $length=false, $driver_options=false) {
		if($this->stmt) return sqlsrv_pdo_driver::report_error('Statement already prepared - too late to bind');
		if($this->named!==null) {
			if(substr($parameter,0,1)==':') $parameter=substr($parameter,1);
			$pos=array_search($parameter,$this->named);
			if($pos===false) return sqlsrv_pdo_driver::report_error('Parameter not in use: '.$parameter);
			$this->params[$pos]=&$variable;
		} else {
			if($parameter>0 && $parameter<=count($this->params)) {
				$this->params[$parameter-1]=&$variable;
			} else {
				return sqlsrv_pdo_driver::report_error('Parameter out of bounds: '.$parameter);
			}
		}
		return true;
	}
	
	/**
	* Bind a value to a placeholder (dereference first)
	*
	* @param		int		$parameter	Parameter number, starting from 1
	* @param		mixed	&$variable	Reference to the variable
	* @param		int		$data_type	Not implemented
	* @return	bool		TRUE on success
	*/
	public function bindValue($parameter, $value, $data_type=false) {
		return $this->bindParam($parameter, $value);
	}
	
	/**
	* Executes the prepared statement
	*
	* @param		array	$params	Optional values for placeholders
	* @return	bool		TRUE on success
	*/
	public function execute($params=false) {
		// late bind the array to take account of bindParam calls
		if(!$this->stmt) {
			$this->stmt=sqlsrv_prepare($this->dbh, $this->tsql, $this->params);
			if(!$this->stmt) return $this->handle_error();
		}
		// Reorder the supplied params if bound by name
		if($params && $this->named!==null) {
			$newparams=array();
			for($i=0, $c=count($this->named); $i<$c; $i++) {
				$key=$this->named[$i];
				if(isset($params[$key])) {
					$newparams[$i]=$params[$key];
				} else {
					$key=':'.$key;
					if(isset($params[$key])) {
						$newparams[$i]=$params[$key];
					} else {
						return sqlsrv_pdo_driver::report_error('Missing value for placemarker '.$key);
					}
				}
			}
			$params=&$newparams;
		}
		// update the params
		if($params) {
			if(count($params)!=count($this->params)) return sqlsrv_pdo_driver::report_error('Parameter count should equal ? marks');
			for($i=0, $c=count($params);$i<$c;$i++) $this->params[$i]=$params[$i];
		}
		if(!sqlsrv_execute($this->stmt)) $this->handle_error();
		return true;
	}
	
	/**
	* Return a row from the opened statement
	*
	* @param		int	$fetch_style			A PDO::FETCH_XXX constant
	* @param		int	$cursor_orientation	Not implemented
	* @param		int	$cursor_offset		Not implemented
	* @return	mixed	The row requested or FALSE on failure/end of recordset
	*/
	public function fetch($fetch_style=false, $cursor_orientation=false, $cursor_offset=false) {
		if($cursor_orientation || $cursor_offset) return sqlsrv_pdo_driver::report_error('Scrollable cursors not implemented');
		if(!$this->stmt) return sqlsrv_pdo_driver::report_error('Statement is closed');
		if(!$fetch_style) $fetch_style=$this->attributes['FETCHMODE'];
		// array fetch
		switch($fetch_style) {
			case PDO::FETCH_ASSOC: 
				$result=sqlsrv_fetch_array($this->stmt, SQLSRV_FETCH_ASSOC);
				break;
			case PDO::FETCH_NUM:
				$result=sqlsrv_fetch_array($this->stmt, SQLSRV_FETCH_NUMERIC);
				break;
			case PDO::FETCH_BOTH:
				$result=sqlsrv_fetch_array($this->stmt, SQLSRV_FETCH_BOTH);
				break;
			case PDO::FETCH_OBJ:
				$result=sqlsvr_fetch_object($this->stmt);
				break;
			default:
				return sqlsrv_pdo_driver::report_error('Unimplemented fetch mode');
		}
		if($result===false) return $this->handle_error();
		if($result==null) $result=false;
		$this->errors=null;
		return $result;
	}
	
	/**
	* Return a row from the opened statement as an object
	* 
	* @param		string	$class_name		Optional name of the class to instanciate
	* @param		array	$ctor_args		Not implemented
	* @return	object	of type $class_name or stdClass
	*/
	public function fetchObject($class_name='stdClass', $ctor_args=false) {
		if(!$this->stmt) return sqlsrv_pdo_driver::report_error('Statement is closed');
		$result=sqlsrv_fetch_object($this->stmt, $class_name);
		if($result===false) return $this->handle_error();
		$this->errors=null;
		return $result;
	}
	
	/**
	* Get a single columns value from an open statement
	*
	* @param		int	$column_number	The column to fetch (default 0)
	* @return	mixed	The columns value for that row
	*/
	public function fetchColumn($column_number=0) {
		$row=$this->fetch(PDO::FETCH_NUM);
		if(!$row) return false;
		if(!isset($row[$column_number])) return false;
		return $row[$column_number];
	}
	
	/**
	* Get an array containing all the results of the current query
	*
	* @param		int		$fetch_style		A PDO::FETCH_XXX constant
	* @param		int		$column_index		The column to fetch for PDO::FETCH_COLUMN (default 0)
	* @param		array	$ctor_args		Not implemented
	* @return	array
	*/
	public function fetchAll($fetch_style=false, $column_index=0, $ctor_args=false) {
		if(!$this->stmt) return sqlsrv_pdo_driver::report_error('Statement is closed');
		$results=array();
		if($fetch_style==PDO::FETCH_COLUMN) {
			while($results[]=$this->fetchColumn($column_index));
		} else {
			while($results[]=$this->fetch($fetch_style));
		}
		array_pop($results);
		return $results;	
	}
	
	/**
	* Get the number of rows affected by previous query
	*
	* @return	int		Number of rows affected
	*/
	public function rowCount() {
		if(!$this->stmt) return -1;
		$rows=sqlsrv_rows_affected($this->stmt);
		if($rows===false) return $this->handle_error();
		return $rows;
	}
	
	/**
	* Get the number of fields returned by a query
	*
	* @return	int		Column count
	*/
	public function columnCount() {
		if(!$this->stmt) return 0;
		$cols=sqlsrv_num_fields($this->stmt);
		if($cols===false) return $this->handle_error();
		return $cols;
	}
	
	/**
	* Set the default fetch mode
	*
	* @param		int		$mode	One of the PDO::FETCH_XXX constants
	* @return	int		1
	*/
	public function setFetchMode($mode) {
		$this->attributes['FETCHMODE']=$mode;
		return 1;
	}
	
	/**
	* Gets the default fetch mode
	*
	* @return	int	One of the PDO::FETCH_XXX constants
	*/
	public function getFetchMode($mode) {
		return $this->attributes['FETCHMODE'];
	}
	
	/**
	* Move to the next rowset in a multi rowset query
	*
	* @return	bool		TRUE on success
	*/
	public function nextRowset() {
		if(!$this->stmt) return false;
		$result=sqlsrv_next_result($this->stmt);
		if($result===false) return $this->handle_error();
		if($result==null) $result=false;
		return $result;
	}
	
	// NOT IMPLEMENTED METHODS - FALSE seems to be the prefered way of indicating in PDO
	public function bindColumn($column, &$param, $type=false, $maxlen=false, $driverdata=false) { return false; }
	public function closeCursor() { return false; }
	public function getColumnMeta($column) { return false; }
	public function getAttribute($attribute) { return false; }
	public function setAttribute($attribute) { return false; }
	
	/**
	* A generic function to set the errors object and generate the correct response
	*
	* @return	bool		FALSE if hasn't thrown exception
	*/
	private function handle_error() {
		$this->errors=sqlsrv_errors(SQLSRV_ERR_ERRORS);
		return sqlsrv_pdo_driver::report_error($this->errors);
	}
}
?>