<?
$config['map'] = array(
  'ident' => 'Phorms_Fields_HiddenField',
  'integer' => 'Phorms_Fields_IntegerField',
  'float' => 'Phorms_Fields_DecimalField',
  'double' => 'Phorms_Fields_DecimaldField',
  'boolean' => 'Phorms_Fields_BooleanField',
  'string' => 'Phorms_Fields_CharField',
  'text' => 'Phorms_Fields_TextField',
  'password' => 'Phorms_Fields_PasswordField',
  'timestamp' => 'Phorms_Fields_IntegerField',
  'foreignkey' => 'Dormio_Form_RelatedField',
);

// defaults
$config['Phorms_Fields_HiddenField'] = array();
$config['Phorms_Fields_CharField'] = array('label' => '', 'help' => '', 'size' => 25, 'max_length' => 255);
$config['Phorms_Fields_PasswordField'] = array('label' => '', 'help' => '', 'size' => 25, 'max_length' => 255, 'hash' => 'md5');
$config['Phorms_Fields_TextField'] = array('label' => '', 'help' => '', 'rows' => 5, 'cols' => 40);
$config['Phorms_Fields_IntegerField'] = array('label' => '', 'help' => '', 'max_digits' => 10, 'size' => 10);
$config['Phorms_Fields_DecimalField'] = array('label' => '', 'help' => '', 'precision' => 10);
$config['Phorms_Fields_BooleanField'] = array('label' => '', 'help' => '');
$config['Phorms_Fields_DropDownField'] = array('label' => '', 'help' => '', 'choices' => array('No options'));
$config['Phorms_Fields_UrlField'] = $config['Phorms_Fields_CharField'];
$config['Phorms_Fields_EmailField'] = $config['Phorms_Fields_CharField'];
$config['Phorms_Fields_DateTimeField'] = array('label' => '', 'help' => '');
$config['Dormio_Form_RelatedField'] = array('label' => '', 'help' => '', 'manager' => array());
?>