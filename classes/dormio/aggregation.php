<?
class Dormio_Aggregation {
  function __construct($manager) {
    $this->manager = $manager;
    $this->manager->query['select'] = array();
  }
  
  /**
  * Adds an aggregation method to the set
  *
  * @todo Need to allow running of multiple aggregate methods in a single query
  * @return int   The number result of the method
  */
  private function add($method, $extra=null, $field='pk') {
    $spec = $this->manager->_meta->column($field);
    $this->manager->query['select'][] = "{$method}({$extra}{{$spec['sql_column']}}) AS {{$field}_" . strtolower($method) . "}";
    return $this;
  }
  
  function run() {
    return $this->manager->query($this->manager->select());
  }
  
  /**
  * Runs a COUNT(<DISTINCT> $field) on the dataset
  */
  function count($field='pk', $distinct=false) {
    return $this->add("COUNT", (($distinct) ? "DISTINCT " : null), $field);
  }
  
  /**
  * Runs a MAX($field) on the dataset
  */
  function max($field='pk') {
    return $this->add("MAX", null, $field);
  }
  
  /**
  * Runs a MIN($field) on the dataset
  */
  function min($field='pk') {
    return $this->add("MIN", null, $field);
  }
  
  /**
  * Runs a AVG($field) on the dataset
  */
  function avg($field='pk') {
    return $this->add("AVG", null, $field);
  }
  
  /**
  * Runs a SUM($field) on the dataset
  */
  function sum($field='pk') {
    return $this->add("SUM", null, $field);
  }
}
?>