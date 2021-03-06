<?php
/**
 * Note this class doesn't use camelCase as the underlying library doesn't
 * @author Tris Forster
 * @package Dormio
 * @subpackage Forms
 */
class Dormio_Form extends Phorm_Phorm {
	
	/**
	 * List of entity fields to generate form for
	 * @var multitype:string
	 */
	public $include_fields;
	
	/**
	 * List of fields to exclude from entity
	 * @var multitype:string
	 */
	public $exclude_fields = array();
	
	/**
	 * @var Dormio_Object
	 */
	public $obj;
	
	/**
	 * 
	 * @var multitype:mixed
	 */
	public $source_data = array();
	
	/**
	 * 
	 * @var multitype:string
	 */
	private $auto_fields = array();
	
	/**
	 * Map of Dormio field types to Phorm_Field class
	 * @var multitype:string
	 */
	public static $field_classes = array(
		'ident' => 'Phorm_Field_Hidden',
		'integer' => 'Phorm_Field_Integer',
		'float' => 'Phorm_Field_Decimal',
		'double' => 'Phorm_Field_Decimal',
		'boolean' => 'Phorm_Field_Checkbox',
		'ipv4address' => 'Phorm_Field_IPv4Address',
		'string' => 'Phorm_Field_Text',
		'text' => 'Phorm_Field_Textarea',
		'password' => 'Phorm_Field_Password',
		'timestamp' => 'Phorm_Field_DateTime',
		'foreignkey' => 'Dormio_Field_Related',
		'onetoone' => 'Dormio_Field_Related',
		'manytomany' => 'Dormio_Field_ManyToMany',
	);
	
	/**
	 * Default constructors for Phorm_Field classes
	 * @var multitype:multitype:string
	 */
	public static $field_defaults = array(
		'Phorm_Field_Hidden' => array(),
		'Phorm_Field_Text' => array('label' => '', 'size' => 25, 'max_length' => 255),
		'Phorm_Field_Password' => array('label' => '', 'size' => 25, 'max_length' => 255, 'hash' => 'trim'), // dont want it to hash our passwords
		'Phorm_Field_Textarea' => array('label' => '', 'rows' => 5, 'cols' => 40),
		'Phorm_Field_Integer' => array('label' => '', 'size' => '5', 'max_digits' => 10),
		'Phorm_Field_Decimal' => array('label' => '', 'size' => 5, 'precision' => 10),
		'Phorm_Field_Checkbox' => array('label' => ''),
		'Phorm_Field_IPv4Address' => array('label' => '', 'subnet' => null, 'as_long' => false),
		'Phorm_Field_DropDown' => array('label' => '', 'choices' => array('No options')),
		'Phorm_Field_Url' => array('label' => '', 'size' => 25, 'max_length' => 255),
		'Phorm_Field_Email' => array('label' => '', 'size' => 25, 'max_length' => 255),
		'Phorm_Field_DateTime' => array('label' => '', 'format' => 'd/m/Y'),
		'Phorm_Field_URL' => array('label' => '', 'size' => 25, 'max_length' => 255),
		'Phorm_Field_Email' => array('label' => '', 'size' =>25, 'max_length' => 255),
		'Phorm_Field_Range' => array('label' => '', 'min' => 0, 'max' => 100, 'slider' => true),
	
		'Dormio_Field_Related' => array('label' => '', 'manager' => array()),
		'Dormio_Field_ManyToMany' => array('label' => '', 'manager' => array(), 'selected' => '', 'widget' => 'Phorm_Widget_SelectMultiple'),
		'Dormio_Field_Choice' => array('label' => '', 'choices' => array('No options')),
	);
	
	public $buttons = array(
		'reset' => 'Clear',
		'submit' => 'Save',
	);
	
	function __construct($obj, $method='post', $multi_part=false, $lang='en') {
		$this->obj = $obj;
		
		if( $this->obj->ident() && !$this->obj->_hydrated ) $this->obj->hydrate();
		$this->source_data = $this->obj->getData();
		foreach($this->obj->_entity->getFields() as $field=>$spec) {
			if($spec['is_field']) {
				if(!array_key_exists($field, $this->source_data)) {
					$this->source_data[$field] =  isset($spec['default']) ? $spec['default'] : null;
				}
			}
			if($spec['type'] == 'manytomany') {
				$selected = array();
				if($obj->ident()) {
					foreach($this->obj->related($field)->selectIdent()->findArray() as $res) $selected[] = current($res);
				}
				$this->source_data[$field] = $selected;
			}
		}

		if(!isset($this->include_fields)) {
			$this->include_fields = array_keys($this->source_data);
		}
		
		parent::__construct($method, $multi_part, $this->source_data, $lang);	
	}
	
	function define_fields() {
		$manytomany = array();
	
		if(!$this->include_fields) $this->include_fields = $this->obj->_entity->getFieldNames();
	
		foreach($this->include_fields as $field) {
			if(array_search($field, $this->exclude_fields)===false) {
				$spec = $this->obj->_entity->getField($field);
				$spec['name'] = strtolower($field);
				if($spec['is_field']) {
					$this->$field = $this->field_for($spec);
				}
				// defer manytomany till end
				if($spec['type'] == 'manytomany') $manytomany[$field] = $spec;
			}
		}
		
		// add the manytomany fields at the end 
		foreach($manytomany as $key=>$spec) {
			$this->$key = $this->field_for($spec);
		}
		
	}
	
	function modified($field) {
		if($field == 'pk') return false;
		if(!$this->obj->ident()) return true;
		return ($this->$field->get_value() != $this->source_data[$field]);
	}
	
	function generate_field($name, $params=array(), $value=null) {
		$params['name'] = $name;
		$spec = array_merge($this->object->_meta->getSpec($name), $params);
		$field = $this->field_for($spec);
		if($value!==null) $field->set_value($value);
		return $field;
	}
	
	function field_for($spec) {
		if(!isset($spec['label'])) $spec['label'] = $spec['verbose'];
		
		if(isset($spec['form_field'])) {
			$klass = $spec['form_field'];
		} else {
			if(!isset(self::$field_classes[$spec['type']])) {
				throw new RuntimeException("No Phorm class mapper for {$spec['type']}");
			}
			$klass = self::$field_classes[$spec['type']];
			// identify choice fields
			if(isset($spec['choices'])) {
				$klass = 'Dormio_Field_Choice';
			}
		}
	
		$params = $this->params_for($klass, $spec);
		
		$rc = new ReflectionClass($klass);
		$field = $rc->newInstanceArgs($params);
	
		$this->auto_fields[] = $spec['name'];
		
		return $field;
	}
	
	function params_for($type, $spec) {
		if(!isset(self::$field_defaults[$type])) throw new Dormio_Exception("No defaults defined for {$type}");
		$defaults = self::$field_defaults[$type];
	
		// run through overrides
		$spec = $this->override('params_for_type', strtolower($type), $spec);
		$spec = $this->override('params_for_field', strtolower($spec['name']), $spec);
	
		// get them into the correct order
		foreach($defaults as $key => $value) {
			$params[] = isset($spec[$key]) ? $spec[$key] : $value;
		}
	
		$validators = array();
		if(isset($spec['validators'])) $validators = array_merge($validators, $spec['validators']);
		if( !isset($spec['null_ok']) || !$spec['null_ok']) {
			switch($spec['type']) {
				case 'ident':
				case 'boolean':
				case 'password':
				case 'manytomany':
					break;
				default:
					$validators[] = 'required';
			}
		}
		$params[] = $validators;
	
		$attributes = array('class' => 'dormio_' . $spec['type']);
		if(isset($spec['attributes'])) $attributes = array_merge($attributes, $spec['attributes']);
		$params[] = $attributes;
	
		return $params;
	}
	
	function params_for_type_dormio_field_manytomany($params) {
		return $this->params_for_type_dormio_field_related($params);
	}
	
	function params_for_type_dormio_field_related($params) {
		$manager = $this->obj->getManager($params['name']);
	
		// allow models to filter the available options
		$method = "options_field_" . $params['name'];
		if(method_exists($this->obj, $method)) $manager = $this->object->$method($manager);
	
		$params['manager'] = $manager;
		return $params;
	}
	
	function params_for_type_dormio_field_choice($params) {
		// load the choices from the model
		$method = "choices_field_" . $params['name'];
		if(method_exists($this->obj, $method)) $params['choices'] = $this->object->$method();
		return $params;
	}
	
	function buttons() {
		$result = array("<div class=\"df-buttons\">");
		foreach($this->buttons as $type=>$display) $result[] = "<input class=\"btn btn-{$type}\" type=\"{$type}\" value=\"{$display}\"/>";
		$result[] = "</div>";
		return implode("\n", $result);
	}
	
	function validate_form() {
		// pass
	}
	
	function is_valid($reprocess=false) {
		// default field validation
		$valid = parent::is_valid($reprocess);
	
		// form level validation
		$this->validate_form();
		return ($valid && !$this->get_errors());
	}
	
	function add_error($field, $message) {
		$this->$field->add_error(array('extra', $message));
	}
	
	function save() {
		$remote_fields = array();
		foreach($this->auto_fields as $field) {
			//var_dump($field);
			if($this->modified($field)) {
				$spec = $this->obj->_entity->getField($field);
				if($spec['is_field']) {
					$this->obj->setFieldValue($field, $this->$field->get_value());
				} else {
					$remote_fields[] = $field;
				}
			}
		}
		$result = $this->obj->save();
	
		foreach($remote_fields as $field) {
			$value = $this->$field->get_value();
			if($value != $this->source_data[$field]) {
				$removed = array_diff($this->source_data[$field], $value);
				$added = array_diff($value, $this->source_data[$field]);
				$manager = $this->obj->related($field);
				foreach($removed as $id) $manager->remove($id);
				foreach($added as $id) $manager->add($id);
			}
		}
		
	
		return $result;
	}
	
	function header() {
		return (string)$this->obj;
	}
	
	function override($type, $name, $param) {
		$method = $type . '_' . $name;
		if(method_exists($this, $method)) $param = $this->$method($param);
		return $param;
	}
}