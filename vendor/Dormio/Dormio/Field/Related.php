<?php

class Dormio_Field_Related extends Phorm_Field_DropDown {
	
  function __construct($label, $manager, $validators=array(), $attributes=array()) {
    $choices['-'] = 'Select...';
    foreach($manager as $obj) $choices[$obj->ident()] = (string)$obj;
    parent::__construct($label, $choices, $validators, $attributes);
  }
  
	public function validate_required($value) {
		if ( $value == '' || $value == '-' ) {
			throw new Phorm_ValidationError('validation_required');
		}
	}
}