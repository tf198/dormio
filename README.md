# Dormio 0.8

This is a complete rewrite of the original Dormio project

### Features

* PSR-0 compatible structure
* Better class organisation - less *black magic*
* Performance improvements
* Removed all factory and singleton patterns

### Still to do...

* Turbo runtime - compile queries once
* Global statement cache
* jQuery widget integration

## Examples

### 1) Define your entities: [entities.php](/tf192/dormio/blob/0.8/docs/examples/entities.php)
```php
$entities = array(
   'Blog' => array(
      'fields' => array(...),
   ),
   ...
);
```

### 2) Set up the connection
```php
// database connection is just a plain old PDO
$pdo = new PDO(':sqlite::memory');

$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);
```

### 3) Create your tables: [schema.php](/tf192/dormio/blob/0.8/docs/examples/schema.php)
```php
$admin = new Dormio_Admin($dormio);
$admin->syncdb();
```

### 4) Manipulate your data: [usage.php](/tf192/dormio/blob/0.8/docs/examples/usage.php)
```php
$ticket = $dormio->getObject('Blog');
$ticket->title = "I have a problem";
$ticket->save();
```

### 5) Create forms from objects: [forms.php](/tf192/dormio/blob/0.8/docs/examples/forms.php)
```php
$blog = $dormio->getObject('Blog', 3);
$form = new Dormio_Form($blog);

echo $form
```
![Example Form](/tf198/dormio/raw/0.8/docs/images/example_form.png)

### 6) Create tables for queries: [tables.php](/tf192/dormio/blob/0.8/docs/examples/tables.php)
```php
$query = $dormio->getManager('Comment')->filter('user__profile__age', '<', 40);
$table = new Dormio_Table($query);

echo $table;
```
![Example Table](/tf198/dormio/raw/0.8/docs/images/example_table.png)
