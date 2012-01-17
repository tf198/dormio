<?php

class Dormio_Form {
  
  public $errors = array();
  public $fields = array();
  
  public function __construct($model, $data) {
    $this->obj = $model;
    $this->data = $data;
    
    $this->defineFields();
  }
  
  public function defineFields() {
    foreach($this->obj->_meta->fields as $key=>$spec) {
      $value = isset($this->data[$key]) ? $this->data[$key] : null;
      $field_type = $spec['type'];
      if(isset($spec['form_field'])) $field_type = $spec['form_field'];
      
      $klass = "Dormio_Field_" . ucfirst($field_type);
      if($spec['type']!='reverse') {
        $this->fields[$key] = new $klass($key, $spec, $value);
        if(isset($spec['model'])) {
          $this->fields[$key]->setModel($this->obj);
        }
      }
    }
  }
  
  public function isValid() {
    if(!$this->data) return false;
    $this->errors = array();
    foreach($this->fields as $key=>$field) {
      try {
        $field->validate();
      } catch(Dormio_Validation_Exception $dve) {
        $this->errors[$key] = $dve->getMessage();
      }
    }
    return count($this->errors) == 0;
  }
  
  public function valueFor($name) {
    if(isset($this->data[$name])) return $this->data[$name];
    if($this->obj->ident()) {
      $value = $this->obj->__get($name);
      if($value instanceof Dormio_Model) return $value->ident();
      if($value instanceof Dormio_Manager) return null;
      return $value;
    }
    $spec = $this->obj->_meta->getSpec($name);
    if(isset($spec['default'])) return $spec['default'];
    return "";
  }
  
  public function save() {
    foreach($this->fields as $key=>$field) {
      $value = $this->fields[$key]->cleaned;
      if($field instanceof Dormio_Field_Ident) {
        // double check the record is the save
        if($value!=$this->obj->ident()) throw new Exception('Attempt to modify different primary key');
      } elseif($field instanceof Dormio_Field_ManyToMany) {
        if($value!==null) $this->obj->__get($key)->add($value);
      } else {
        if($this->obj->ident()) {
          if($value !== $this->obj->getValue($key)) {
            $this->obj->__set($key, $value);
          }
        } else {
          $this->obj->__set($key, $value);
        }
      }
    }
    try {
      $this->obj->save();
      return true;
    } catch(Dormio_Validation_Exception $dve) {
      $this->errors['form'] = $dve->getMessage();
      return false;
    }
  }
  
  public function asTable($action="", $method="post") {
    $result[] = "<form action=\"{$action}\" method=\"{$method}\">";
    if(isset($this->errors['form'])) {
      $result[] = "<div><span class=\"field_error\">" . htmlentities($this->errors['form']) . "</span></div>";
    }
    $result[] = "<table class=\"dormio-auto dormio-form\">";
    foreach($this->fields as $key=>$field) {
      $value = $this->valueFor($key);
      $input = $field->widget($value);
      $error = isset($this->errors[$key]) ? "<br/><span class=\"field_error\">" . htmlentities($this->errors[$key]) . "</span>" : "";
      if($field instanceof Dormio_Field_Hidden) {
        $result[] = $input;
      } else {     
        $result[] = "<tr><th>{$field->label}</th><td>{$input}{$error}</td></tr>";
      }
    }
    $result[] = "<tr><td colspan=\"2\">" . Dormio_Form_Widget::input("save", "Save", array('type' => 'submit')) . "</td></tr>";
    $result[] = "</table>";
    $result[] = "</form>";
    return implode(PHP_EOL, $result);
  }
}

class Dormio_Form_Widget {
  static function input($name, $value=null, $attrs=array()) {
    $attrs['name'] = $name;
    $attrs['value'] = $value;
    return self::element('input', $attrs);
  }
  
  static function select($name, $choices, $value=null, $attrs=array()) {
    $options = array();
    foreach($choices as $key=>$text) {
      $opt = array('value' => $key);
      if($key==$value) $opt['selected'] = '1';
      $options[] = self::element('option', $opt, $text);
    }
    
    $attrs['name'] = $name;
    return self::element('select', $attrs, implode(PHP_EOL, $options));
  }
  
  public static function element($type, $attrs=array(), $inner=null) {
    $result = "<{$type} " . self::attrs($attrs);
    if($inner!==null) {
      $result .= ">\n{$inner}\n</$type>";
    } else {
      $result .= " />";
    }
    return $result;
  }
  
  public static function attrs($input) {
    $result = array();
    foreach($input as $key=>$value) $result[] = sprintf('%s="%s"', htmlentities($key), htmlentities($value));
    return implode(' ', $result);
  }
}

class Dormio_Validation_Exception extends Exception {}