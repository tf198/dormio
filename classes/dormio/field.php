<?php

abstract class Dormio_Field {
  
  protected $_value;
  
  protected $attrs = array();
  
  function __construct($name, $spec, $value) {
    $this->name = $name;
    $this->label = $spec['verbose'];
    $this->spec = $spec;
    $this->init();
    
    $this->_value = $value;
    $this->cleaned = $this->clean($value);
  }
  
  function init() {}
  
  abstract function widget($value);
  
  function clean($value) {
    return $value;
  }

  function validate() {
    if($this->cleaned===null || $this->cleaned==='') {
      if(!isset($this->spec['null_ok']) || $this->spec['null_ok']==false) throw new Dormio_Validation_Exception("Required");
    }
  }
  
  function __toString() {
    return htmlentities($this->cleaned);
  }
}

class Dormio_Field_Input extends Dormio_Field {
  protected $type = 'text';
  protected $attrs = array('size' => 20);
  
  function widget($value) {
    $this->attrs['type'] = $this->type;
    return Dormio_Form_Widget::input($this->name, $value, $this->attrs);
  }
}

class Dormio_Field_String extends Dormio_Field_Input {
  
  function validate() {
    parent::validate();
    if(isset($this->spec['max_length']) && strlen($this->cleaned)>$this->spec['max_length']) {
      throw new Dormio_Validation_Exception("Must be no more than {$this->spec['max_length']} chars");
    }
  }
}

class Dormio_Field_Text extends Dormio_Field {
  function widget($value) {
    return Dormio_Form_Widget::element('textarea', array('name' => $this->name), $value);
  }
}

class Dormio_Field_Hidden extends Dormio_Field_Integer {
  protected $type = 'hidden';
}

class Dormio_Field_Ident extends Dormio_Field_Hidden {
  function validate() {
    if(!$this->cleaned) $this->cleaned = 0;
    parent::validate();
  }
}

class Dormio_Field_Password extends Dormio_Field_String {
  protected $type = 'password';
  
  function validate() {
    parent::validate();
    if(isset($this->spec['min_length']) && strlen($this->cleaned)<$this->spec['min_length']) {
      throw new Dormio_Validation_Exception("Must be at least {$this->spec['min_length']} chars");
    }
  }
}

class Dormio_Field_Integer extends Dormio_Field_Input {
  protected $attrs = array('size' => 5);
  
  function validate() {
    parent::validate();
    if($this->cleaned && !is_numeric($this->cleaned)) throw new Dormio_Validation_Exception("Integer required");
  }
  
  function clean($value) {
    if($value=='') return null;
    return (int)$value;
  }
  
}

class Dormio_Field_Enum extends Dormio_Field_Integer {
  
  function init() {
    $field = $this->spec['choices'];
    $this->choices = eval("return {$this->spec['choices']};");
  }
  
  function widget($value) {
    $choices = $this->spec['choices'];
    //return Form::select($this->name, $this->choices, $value);
    //return new Dormio_Widget_Select($this->name, $this->choices, $value);
  }
  
  function validate() {
    parent::validate();
    if(!array_key_exists($this->_value, $this->choices)) throw new Dormio_Validation_Exception("Value outside allowed set");
  }
  
}

class Dormio_Field_Float extends Dormio_Field_Input {
  protected $attrs = array('size' => 5);  
  
  function validate() {
    parent::validate();
    if(!is_numeric($this->_value)) throw new Dormio_Validation_Exception("Decimal number required");
  }
  
  function clean($value) {
    return (float)$value;
  }
}

class Dormio_Field_Boolean extends Dormio_Field {
  
  function widget($value) {
    return Form::checkbox($this->name, 1, (bool)$value);
  }
  function clean($value) {
    $result = (bool) $value;
    return $result;
  }
}

class Dormio_Field_Timestamp extends Dormio_Field_Integer {
  
}

class Dormio_Field_ForeignKey extends Dormio_Field {
  
  function widget($value) {
    $items = array('-' => 'Select...');
    foreach($this->manager as $obj) $items[$obj->ident()] = (string)$obj;
    return Dormio_Form_Widget::select($this->name, $items, $value);
  }
  
  function setModel($obj) {
    $this->manager = $obj->manager($this->name);
  }
  
  function clean($value) {
    return ($value=='-') ? null : $value;
  }
  
}

class Dormio_Field_ManyToMany extends Dormio_Field {  
  
  function widget($value) {
    $items = array('-' => 'Add...');
    foreach($this->all_manager as $obj) $items[$obj->ident()] = (string)$obj;
    $selected = array();
    $request = Request::$current;
    $model = $request->param('model');
    $orig_id = $request->param('id');
    foreach($this->selected_manager as $obj) {
      $id = $obj->ident();
      unset($items[$id]);
      
      $selected[] = "<li>{$obj} <a href=\"/mesh_2.0.5/index.php/models/{$model}/{$orig_id}/remove?field={$this->name}&amp;id={$id}\">remove</a></li>";
    }
    $result = "<ul style=\"padding-left: 1em;\">\n" . implode("\n", $selected) . "</ul>\n";
    $this->attrs['style'] = "width: 100%;";
    return "<fieldset><legend>{$this->label}</legend>" . Dormio_Form_Widget::select($this->name, $items, 0 , $this->attrs) . "\n" . $result . "</fieldset>";
  }
  
  function validate() {
    // dont call parent as null is okay
  }
  
  function clean($value) {
    return ($value=='-') ? null : $value;
  }
  
  function setModel($obj) {
    $this->selected_manager = ($obj->ident()) ? $obj->__get($this->name) : array();
    $this->all_manager = $obj->manager($this->name);
    $spec = $obj->_meta->getSpec($this->name);
    $this->through = $spec['through'];
    return null;
  }
}
