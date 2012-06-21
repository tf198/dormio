<?php

class Dormio_Field_ManyToMany extends Phorm_Field_MultipleChoice {
  public function __construct($label, $manager, $selected, $validators=array(), $attributes=array()) {
    $choices = array();
    foreach($manager as $obj) $choices[$obj->ident()] = (string)$obj;
    
    parent::__construct($label, $choices, 'Phorm_Widget_SelectMultiple', $validators, $attributes);
  }
}