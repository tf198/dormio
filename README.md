# Dormio
**WARNING:** Code still very much alpha - use at your own risk!

## About

### Introduction (or 'Why another PHP database library')

1. Because I want to interact with my data in as clean and simple a way as Django models.
2. Because I don't think you should have to run an OpCache to get a decent featureset.

Dormio is a lightweight but fully featured database library designed to take the pain out of dealing with your data.
It uses a powerful query syntax similar to [Django](https://www.djangoproject.com/) that generates efficient SQL, joining tables as necessary and supports 
eager loading of objects for minimum queries.  It can also generate forms, tables and schema SQL all from the one entitiy
description so when you change your database you only have to update one config array.

### Design Features

* DRY - define your entities once. Full stop.
* No framework requirement but usable in any.
* Built directly on PDO - no separate abstraction layer.
* Memory concious - under 900KB in memory.
* Config in PHP - no XML or YAML config or compilation.
* PHP5+ compatible.
* <del>Fully</del> unit tested (PHPUnit).
* <del>Fully</del> documented code.

### Alternatives

While looking for a lightweight alternative to Doctrine/Propel I found the following projects which
while not suitable for my project may be of interest:

* [OutletORM](http://www.outlet-orm.org/site/) - Nice hibernate inspired library
* [NotORM](http://www.notorm.com/) - Interesting approach to database interaction

### Documentation
Currently limited to the phpDocumentor generated API docs at http://www.tfconsulting.com.au/~tris/dormio/0.8/api, though
you can get a good idea of the functionality by looking at the [examples](/tf198/dormio/blob/0.8/docs/examples)

## Changelog

### 0.9 planned features

* Turbo runtime - compile queries once
* Global statement cache
* jQuery widget integration
* Finish documentation and unit tests

### 0.8
This is a complete rewrite of the original project with the configuration system changed to array and
a more object orientation approach.

* PSR-0 compatible structure
* Better class organisation - less *black magic*
* Performance improvements
* Removed all factory and singleton patterns
* Entities now declared in config instead of on the object

### 0.6
The first real version that could be used in another project.  Contained a lot of black magic so proved
to be unmaintainable.

## Examples

### 1) Define your entities: [entities.php](/tf198/dormio/blob/0.8/docs/examples/entities.php)
```php
<?
$entities = array(
   'Blog' => array(
      'fields' => array(
      	'title' => array('type' => 'string', 'max_length' => 30),
      	'author' => array('type' => 'foreignkey', entity='User'),
      ),
   ),
   ...
);
?>
```

### 2) Set up the connection
```php
<?
// database connection is just a plain old PDO
$pdo = new PDO(':sqlite::memory');

$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);
// you are now ready to go
?>
```

### 3) Automatically create tables (optional): [schema.php](/tf198/dormio/blob/0.8/docs/examples/schema.php)
```php
<?
$admin = new Dormio_Admin($dormio);
$admin->syncdb();
?>
```

### 4) Manipulate your data: [usage.php](/tf198/dormio/blob/0.8/docs/examples/usage.php)
All the standard object interaction methods with support for eager loading, aggregation and cross
table queries.
```php
<?
$blogs = $dormio->getManager('Blog');
foreach($blogs->filter('author__profile__age', '<', 18) as $blog {
   $blog->title = "## This blog has been censored ##";
   $blog->save();
}

// you can actually achieve the above in a single query
$blogs->filter('author__profile__age', '<', 18)->update('title' => '## This blog has been censored ##');
?>
```

### 5) Create forms from objects: [forms.php](/tf198/dormio/blob/0.8/docs/examples/forms.php)
Automatically generate a form to interact with your record including the relationships.  Data is validated
against the database type and also custom validators. Subclass Dormio_Form to get infinite control over the 
fields and add more complex multi-field validation rules.
```php
<?
$blog = $dormio->getObject('Blog', 1);
$form = new Dormio_Form($blog);

echo $form
?>
```
![Example Form](/tf198/dormio/raw/0.8/docs/images/example_form.png)

### 6) Create tables from queries: [tables.php](/tf198/dormio/blob/0.8/docs/examples/tables.php)
Generate paged, sortable tables from your entities in one line, or customise to your hearts content.
```php
<?
$query = $dormio->getManager('Comment')->filter('tags__tag', 'IN', array('Red', 'Green'));
$table = new Dormio_Table_query($query);

echo $table;
?>
```
![Example Table](/tf198/dormio/raw/0.8/docs/images/example_table.png)
(Not the actual table from the above query)

## Why Dormio?
Being so closely related to Django ('_I Awake_' in Roma) then Dormio ('_I Sleep_' in Latin) seemed appropriate,
especially following in the footsteps of Java's Hibernate.
