<?
/**
* @package dormio
*/
require_once(dirname(__FILE__) .'/../../3rd Party/phorms/src/Phorms/init.php');

class Dormio_Form extends Phorms_Forms_Form{

  static $base = array('validators' => array(), 'attributes' => array());
  
  function __construct($obj) {
    $this->obj = $obj;
    // get the existing data
    $data = array();
    foreach($this->obj->_meta->columns as $name => $spec) {
      if(isset($spec['is_field'])) {
        $this->model_fields[$name] = $spec;
        if($this->obj->ident()) $data[$name] = $this->obj->_getData($spec['sql_column']);
      }
    }
    parent::__construct(Phorms_Forms_Form::POST, false, $data);
  }

  function save() {
    foreach($this->model_fields as $name => $spec) {
      $value = $this->$name->getValue();
      if($spec['type'] == 'ident') {
        // double check the record is the save
        if($value!=$this->obj->ident()) throw new Exception('Attempt to modify different primary key');
      } else {
        $this->obj->__set($name, $value);
      }
    }
    $this->obj->save();
    return $this->obj->ident();
  }
  
  protected function defineFields() {
    foreach($this->model_fields as $name => $spec) {
      $this->$name = $this->field_for($name, $spec);
    }
  }
  
  function params_for($type, $spec) {
    $defaults = bCommon::config("forms.{$type}");
    foreach($defaults as $key => $value) {
      $params[] = isset($spec[$key]) ? $spec[$key] : $value;
    }
    foreach(self::$base as $key => $value) {
      if($key == 'attributes') $value['class'] = $type;
      $params[] = isset($spec[$key]) ? $spec[$key] : $value;
    }
    return $params;
  }
  
  function field_for($name, $spec) {
    $spec['label'] = isset($spec['verbose']) ? $spec['verbose'] : ucwords(str_replace('_', ' ', $name));
    $map = bCommon::config('forms.map');
    if(!isset($map[$spec['type']])) return new TextField($spec['label'], 25, 255);
    $phorm_type = $map[$spec['type']];
    
    if($phorm_type == 'ForeignKeyField') {
      $spec['manager'] = $this->obj->manager($name);
    }
    
    $params = $this->params_for($phorm_type, $spec);
    
    $rc = new ReflectionClass($phorm_type);
    $field = $rc->newInstanceArgs($params);
    return $field;
  }
}

/**
* @package dormio
* @subpackage form
*/
class ForeignKeyField extends Phorms_Fields_ChoiceField {
  function __construct($label, $help, $manager, $validators=array(), $attributes=array()) {
    $choices[0] = 'Select...';
    foreach($manager as $obj) $choices[$obj->ident()] = (string)$obj;
    parent::__construct($label, $help, $choices, $validators, $attributes);
  }
}
?>