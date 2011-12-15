Dormio
======

Current Status
--------------

Code is currently at Alpha stage - use at your own risk.

Core ORM: Dormio_Meta Dormio_Model Dormio_Queryset Dormio_Manager Dormio_Factory
API for this should be pretty fixed now and it is all pretty well tested and documented.
There may (almost certainly are) be bugs with some edge case usage but its fairly solid now.

Form Generation: Dormio_Form
The API for this is still up in the air.  I'll endevour to keep the example code up to date
and working but be prepared to update your code.

Schema Generation: Dormio_Schema
SQLite dialect is fairly well tested.
MySQL dialect should be okay for simple usage but not tested.
Other dialects unlikely to work without hacking
API very likely to change as I look at this more.

Documentation
-------------
phpDocumentor documentation of the API is available at http://www.tfconsulting.com.au/~tris/dormio/api-docs
and the best place to start is probably with the example usage https://github.com/tf198/dormio/blob/master/docs/examples/usage.php

Introduction (or why another PHP ORM?)
--------------------------------------
 
1) Because Django has shown us how object persistence should work.
2) Because I dont think you should have to run an OpCache for a decent featureset.

Design Principles
~~~~~~~~~~~~~~~~~

* DRY - models, schemas and forms all driven from one lightweight meta class
* No framework requirement, but usable in all.
* Built directly on PDO - no separate abstraction layer
* Embrace the Exception - PDO will tell you pretty quick if there is a problem so there is no need for DB refelection
* Config in PHP - no XML or YAML config or compilation
* Memory concious - no features loaded unless required
* PHP 5.X compatible
* Well unit tested (SimpleTest)
* Well documented (phpDocumentor)
 
Features
--------

Model definition
~~~~~~~~~~~~~~~~
::

    class Blog extends Dormio_Model {
      static $meta = array(
        'fields' => array(
          'title' => array('type' => 'string', 'max_length' => 30),
          'author' => array('type' => 'foreignkey', 'model' => 'User'),
        ),
      );
    }

Basic usage
~~~~~~~~~~~
::

    $pdo = new PDO('sqlite::memory:'); // works on raw PDO objects
    $dormio = new Dormio_Factory($pdo);
    
    $blog = $dormio->get('Blog');
    $blog->title = 'Test Blog 1';
    $blog->author = 23; // or could pass it a User object
    $blog->save();
    
    // lazy loading of related objects
    $blog = $dormio->get('Blog', 46);
    foreach($blog->comment_set as $comment) echo "{$comment->title}\n";
    
Intelligent queries
~~~~~~~~~~~~~~~~~~~~
Reducing the number of queries required to render a page.  The query API is mostly lifted straight
from Django.
::

    // automatic joins for queries
    $blogs = $dormio->manager('Blog')->filter('author__profile__fav_colour', '=', 'green');
    foreach($blogs as $blog) echo "{$blog->title}\n";
    
    // eager loading - will only require one query
    $comments = $dormio->manager('Comments')->filter('timestamp', '>', time()-3600)->with('blog')->limit(10);
    foreach($comments as $comment) echo "{$comment->blog->title}: {$comment->title}\n";
    
    // filtering of related objects
    $blog = $dormio->get('Blog', 23);
    foreach($blog->comment_set->filter('author', '=', $blog->author) as $comment) echo "{$comment->title}\n";
    
Automatic Forms
~~~~~~~~~~~~~~~~
Generate an entire form complete with validation from your model.  Uses the Phorms library.
::

    $blog = $dormio->get('Blog', 23);
    $form = Dormio_Form($blog);
    
    if($form->isValid()) {
      $form->save();
    } else {
      echo $form;
    }

Schema Generation
~~~~~~~~~~~~~~~~~~
Generate schemas directly from your models. Can even upgrade them for you.
::

    $pdo = new PDO('sqlite::memory:');
    $sf = Dormio_Schema::factory('sqlite', 'Blog');
    $sf->createTable();
    $sf->batchExecute($pdo, $sf->sql);
    
Blistering performance
~~~~~~~~~~~~~~~~~~~~~~
Everything is kept as light as possible using just a tiny meta description at the core.  This
results in code that runs nearly as fast as raw PDO and with a not much greater memory footprint while still
giving you a full featureset. The entire library comprises of only 15 files and currently just sneeks in
under 1000 lines of code excluding comments/blank lines, and of those only 7 or so are loaded for typical operation 
clocking in at about 650 lines of code.  Less is more!
::

                      | Insert | findPk | complex| hydrate|  with  |     MB |
                      |--------|--------|--------|--------|--------|--------|
               OptPDO |     42 |     46 |     96 |     80 |     65 |   0.54 | < As fast as is possible
                  PDO |    105 |    111 |    105 |    108 |    107 |   0.52 | 
            OptDormio |     64 |    103 |    121 |    119 |     72 |   1.01 | < Not that far behind
               Dormio |    313 |    125 |    146 |    200 |    203 |   0.96 | < Still pretty respectable
             Outlet07 |    792 |     80 |    178 |    416 |    518 |   2.09 |
             Propel14 |   1453 |    601 |    183 |    364 |    397 |   2.98 |
             Propel15 |   1301 |    709 |    231 |    466 |    573 |   7.24 |
    Propel15WithCache |   1183 |    504 |    198 |    374 |    421 |   7.32 |
           Doctrine12 |   2445 |   3552 |    655 |   1968 |   2196 |  13.36 | < Hope you have a beefy box...
           
Obviously benchmarks are not real world, but they do throw out some interesting numbers... 

The OptX tests are designed to simulate heavy batch work eg importing from CSV or running many cached queries.
The standard tests give a better idea of the loading impact the library can have on your system (setup and teardown for each iteration) -
or some really bad loop based programming :)  I haven't got round to filling in the OptX tests for the other libraries yet
as I don't have a good knowledge of their internal workings - any volunteers?
Benchmark source can be found at https://github.com/tf198/php-orm-benchmark and more information on the original benchmarks
at http://propel.posterous.com/how-fast-is-propel-15

Why Dormio?
-----------

Being so closely related to Django ('*I Awake*' in Roma) then Dormio ('*I Sleep*' in Latin) seemed appropriate, especially
following in the footsteps of Java's *Hibernate*.