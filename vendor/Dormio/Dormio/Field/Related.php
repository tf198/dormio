<?php

class Dormio_Field_Related extends Phorm_Field_DropDown {

	const OPTION_NULL = '-';
	
	function __construct($label, $manager, $validators=array(), $attributes=array()) {
		$choices[self::OPTION_NULL] = 'Select...';
		foreach($manager as $obj) $choices[$obj->ident()] = (string)$obj;
		parent::__construct($label, $choices, $validators, $attributes);
	}

	public function validate_required($value) {
		if ( $value == '' || $value == self::OPTION_NULL ) {
			throw new Phorm_ValidationError('validation_required');
		}
	}

	public function import_value($value) {
		if($value == self::OPTION_NULL) return null;
		return parent::import_value($value);
	}
}