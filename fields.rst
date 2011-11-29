Dormio Field Definitions
========================

Basic Fields
------------

* type*             Available types: 'string'|'integer'|'password'|...
* verbose           Verbose name for this field [Capitalized <field_name>]
* db_column         Database column for this field [<field_name>]
* null_ok           Whether this field can be null [false]

One to X Fields
---------------

* type*             Either 'foreignkey' or 'onetoone'
* model*            The remote model name
* verbose           Verbose name for this field [Capitalized <field_name>]
* db_column         Database column for this field - [<field_name>]
* remote_field      The field on the remote model - [<model>.pk]
* on_delete         ('cascade'|'blank') ['cascade']
* [is_field]        true
* [local_field]     <field_name>

Reverse = array('type' => '<type>_rev', 'model' => <this_model>, 'local_field' => <remote_field>, 'remote_field' => <field_name>, 'on_delete'=>$spec['on_delete']);

X to X Fields
-------------

* type*             'manytomany'
* model*            The remote model name
* verbose           Verbose name for this field [Capitalized <field_name>]
* through           The class for the mapping table [<model_1>_<model_2>]
* map_local_field   The field on the local model [null]
* map_remote_field  The field on the remote model [null]

Reverse = array('type' => 'manytomany', 'model'=> '<this_model>', 'through' => '<through>', 'local_field' => '<remote_field>', 'remote_field' => '<local_field>');


Reverse Fields
--------------

* type*             'reverse'
* model*            The remote model name
* accessor          The field on the other model that should be used

A reverse field is automatically added to all models with the name <model>_set but this will throw an exception if there
is more than one model1 > model2 two relation set.  Using a reverse field allows for nicer names (e.g. 'children' instead of 'node_set')
and if an accessor is specified allows more than one relation between two tables.  This is also required for self join tables.
