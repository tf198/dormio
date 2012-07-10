Dormio Fields
=============

General options
---------------
Apply to all fields

#### type
	The Dormio type for this field (required)
 
	Built in types are:
	* ident (auto increment field)
	* string (typically up to 255 chars)
	* text
	* password
	* integer
	* float
	* double
	* boolean
	* timestamp
	* date
	* datetime
	* foreignkey
	* onetoone
	* manytomany

#### null_ok
	``(*true*|*false*) [default: *false*]``

	If set to *true* then the database entry can be *null* - enforced at database level

#### unique
	If set to *true* then field must be unique - enforced at database level

#### default
	Database default value - set in table schema

#### verbose
	Verbose name for field.
	Defaults to *<field_name>* with underscores translated to spaces.

#### form_field
	Use the specified Phorm_Field class instead of the default to represent this field.
   
#### <del>form_default</del>
	Default value for form fields, overriding ``default``
   
#### choices
	An array where the keys are the allowable values and the values are displayed to the user

#### validators
	Array of callbacks to be run on user supplied data. Note this only affects the Dormio_Form
	component, not Dormio_Object manipulations where only database constraints are applied.
   
#### attributes
	Array of additional attributes to pass to the Dormio_Form widget.  Useful for setting classes, style
	etc on form elements.

Field types
-----------

string
~~~~~~
#### max_length
	Maximum allowable string length [default: 255]
   
integer
~~~~~~~
#### size
	Size in bits [default: 32]
   
float
~~~~~

boolean
~~~~~~~

timestamp
~~~~~~~~~

foreignkey / onetoone
~~~~~~~~~~~~~~~~~~~~~
These are identical except for ``on_delete`` behaviour

#### entity
	Name of related entity (required)
   
#### local_field
	Name of the local field [default: *<field_name>*]
   
#### remote_field
	Name of the field on the target entity [default: *pk*]
   
#### on_delete
	``(*cascade*|*blank*)``
   
	What to do to the target entity when this record is deleted.
	Defaults to *cascade* for *foreignkey* and *blank* for *onetoone*
   
manytomany
~~~~~~~~~~

#### entity
	Name of related entity (required)
#### through
	Name of intemediate entity.
	If none given then an automatic entity and associated table with be created
#### map_local_field
	Field on intermediate entity referencing this entity [default: *lhs*]
#### map_remote_field
	Field on itermediate entity referencing the target entity [default: *rhs*]
