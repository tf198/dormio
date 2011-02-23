<?
class MockPDO extends PDO {
  public $stack = array();

  function __construct($dsn, $username=null, $password=null, $driver_options=null) {
    parent::__construct($dsn, $username, $password, $driver_options);
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
  
  function prepare($sql) {
    $stmt = parent::prepare($sql);
    $mock = new MockPDOStatment($stmt);
    array_push($this->stack, array($sql, $mock));
    return $mock;
  }
  
  function exec($sql) {
    array_push($this->stack, $sql);
    return parent::exec($sql);
  }
  
  function digest() {
    if(count($this->stack)<1) throw new Exception('Statement stack is empty');
    $q = array_shift($this->stack);
    return array( $q[0], $q[1]->stack );
  }
  
  function count() {
    return count($this->stack);
  }
}

class MockPDOStatment {
  public $stack = array();

  function __construct($stmt) {
    $this->_stmt = $stmt;
  }
  
  function execute($params) {
    array_push($this->stack, $params);
    return $this->_stmt->execute($params);
  }
  
  function __call($method, $args) {
    return call_user_func_array(array($this->_stmt, $method), $args);
  }
  
  function run_count() {
    return count($this->stack);
  }
  
}
?>