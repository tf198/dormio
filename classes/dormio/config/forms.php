<?
/**
* Default form mappings
* @package dormio
*/

// mappings of dormio meta types to Phorm Fields
$config['ident'] = 'Phorms_Fields_HiddenField';
$config['integer'] = 'Phorms_Fields_IntegerField';
$config['float'] = 'Phorms_Fields_DecimalField';
$config['double'] = 'Phorms_Fields_DecimaldField';
$config['boolean'] = 'Phorms_Fields_BooleanField';
$config['string'] = 'Phorms_Fields_CharField';
$config['text'] = 'Phorms_Fields_TextField';
$config['password'] = 'Phorms_Fields_PasswordField';
$config['timestamp'] = 'Phorms_Fields_IntegerField';
$config['foreignkey'] = 'Dormio_Form_ManagerField';

// default Phorm Field parameters
$config['Phorms_Fields_HiddenField'] = array();
$config['Phorms_Fields_CharField'] = array('label' => '', 'help' => '', 'size' => 25, 'max_length' => 255);
$config['Phorms_Fields_PasswordField'] = array('label' => '', 'help' => '', 'hash' => 'md5', 'size' => 25, 'max_length' => 255);
$config['Phorms_Fields_TextField'] = array('label' => '', 'help' => '', 'rows' => 5, 'cols' => 40);
$config['Phorms_Fields_IntegerField'] = array('label' => '', 'help' => '', 'max_digits' => 10, 'size' => 10);
$config['Phorms_Fields_DecimalField'] = array('label' => '', 'help' => '', 'precision' => 10);
$config['Phorms_Fields_BooleanField'] = array('label' => '', 'help' => '');
$config['Phorms_Fields_DropDownField'] = array('label' => '', 'help' => '', 'choices' => array('No options'));
$config['Phorms_Fields_UrlField'] = $config['Phorms_Fields_CharField'];
$config['Phorms_Fields_EmailField'] = $config['Phorms_Fields_CharField'];
$config['Phorms_Fields_DateTimeField'] = array('label' => '', 'help' => '');
$config['Dormio_Form_ManagerField'] = array('label' => '', 'help' => '', 'manager' => array());

return $config;
