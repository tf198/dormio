<?php
require_once "DBTest.php";

class Dormio_FormTest extends Dormio_DBTest {
	
	function testBasicFieldGeneration() {
		$this->load('data/entities.sql');
		$obj = $this->dormio->getObject('Blog');
		
		$form = new Dormio_Form($obj);
		
		$this->assertFieldHTML('<input value="" class="dormio_ident phorm_field_hidden" maxlength="255" size="25" type="hidden" />',
				$form, 'ident');
		$this->assertFieldHTML('<input value="" class="dormio_integer phorm_field_integer" maxlength="10" size="5" type="text" />', 
				$form, 'integer');
		$this->assertFieldHTML('<input value="" class="dormio_float phorm_field_decimal" size="5" type="text" />', 
				$form, 'float');
		$this->assertFieldHTML('<input value="" class="dormio_double phorm_field_decimal" size="5" type="text" />',
				$form, 'double');
		$this->assertFieldHTML('<input value="on" class="dormio_boolean phorm_field_checkbox" type="checkbox" />',
				$form, 'boolean');
		$this->assertFieldHTML('<input value="" class="dormio_string phorm_field_text" maxlength="255" size="25" type="text" />',
				$form, 'string');
		$this->assertFieldHTML('<textarea  class="dormio_text phorm_field_textarea" cols="40" rows="5" ></textarea>',
				$form, 'text');
		$this->assertFieldHTML('<input value="" class="dormio_password phorm_field_password" maxlength="255" size="25" type="password" />',
				$form, 'password');
		$this->assertFieldHTML('<input value="" class="dormio_timestamp phorm_field_datetime" maxlength="100" size="10" type="text" />',
				$form, 'timestamp');
	}
	
	function testAutoFieldGeneration() {
		$this->load('data/entities.sql');
		$this->load('data/test_data.sql');
		
		$obj = $this->dormio->getObject('Blog', 1);
		$form = new Dormio_Form($obj);
		
		// string
		$this->assertEquals('<input value="Andy Blog 1" class="dormio_string phorm_field_text" maxlength="30" size="25" id="id_title" name="title" type="text" />', $form->title->html());
		
		// foreignkey
		$this->assertEquals( <<< EOF
<select class="dormio_foreignkey dormio_field_related" id="id_the_user" name="the_user" ><option value="-" >Select...</option>
<option value="1" selected="selected" >Andy</option>
<option value="2" >Bob</option>
<option value="3" >Charles</option>
</select>
EOF
			, $form->the_user->html());
		
		// manytomany
		$this->assertEquals( <<< EOF
<select multiple="multiple" class="dormio_manytomany dormio_field_manytomany" id="id_tags" name="tags[]" ><option value="1" >Red</option>
<option value="2" >Orange</option>
<option value="3" selected="selected" >Yellow</option>
<option value="4" >Green</option>
<option value="5" >Blue</option>
<option value="6" selected="selected" >Indigo</option>
<option value="7" >Violet</option>
</select>
EOF
			, $form->tags->html());
		
		$obj = $this->dormio->getObject('Profile', 1);
		$form = new Dormio_Form($obj);
		
		// integer
		$this->assertEquals('<input value="23" class="dormio_integer phorm_field_integer" maxlength="10" size="5" id="id_age" name="age" type="text" />', $form->age->html());
		
		// onetoone
		$this->assertEquals( <<< EOF
<select class="dormio_onetoone dormio_field_related" id="id_user" name="user" ><option value="-" >Select...</option>
<option value="1" >Andy</option>
<option value="2" selected="selected" >Bob</option>
<option value="3" >Charles</option>
</select>
EOF
				, $form->user->html());
	}
	
	function assertFieldHTML($expected, $form, $type, $attrs=array()) {
		$attrs['type'] = $type;
		$attrs['name'] = 'my_field';
		$attrs['verbose'] = 'My Field';
		
		$field = $form->field_for($attrs);
		
		$this->assertEquals($expected, $field->html());
	}
	
}