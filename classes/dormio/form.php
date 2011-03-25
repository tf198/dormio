<?php
/**
* Form generation from meta definitions
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
* Need to add the Phorms autoloader
*/
require_once(dirname(__FILE__) . '/../../3rd Party/phorms/src/Phorms/init.php');

/**
* Class to generate a Phorms form based on a Dormio model
* @package dormio
*/
class Dormio_Form extends Phorms_Forms_Form{

  static $base = array('validators' => array(), 'attributes' => array());
  
  function __construct($obj, $config=null) {
    $this->obj = $obj;
    if(!$config) {
      // this is a messy hack at the moment - need to sort it out
      include(dirname(__FILE__) . '/../../config/forms.php');
    }
    $this->form_config = $config;
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
    //$defaults = bCommon::config("forms.{$type}");
    $defaults = $this->form_config[$type];
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
    $map = $this->form_config['map'];
    if(!isset($map[$spec['type']])) return new TextField($spec['label'], 25, 255);
    $phorm_type = $map[$spec['type']];
    
    if($phorm_type == 'Dormio_Form_ManagerField') {
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
class Dormio_Form_ManagerField extends Phorms_Fields_ChoiceField {
  function __construct($label, $help, $manager, $validators=array(), $attributes=array()) {
    $choices['-'] = 'Select...';
    foreach($manager as $obj) $choices[$obj->ident()] = (string)$obj;
    parent::__construct($label, $help, $choices, $validators, $attributes);
  }
  
  function validate($value) {
    if($value=='-') throw new Phorms_Validation_Error('Invalid selection');
    parent::validate($value);
  }
  
}
?>