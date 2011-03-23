Dormio
======

**Note that Dormio is currently pre-alpha - feel free to play but dont use it
for anything important.  The API is likely to change - you have been warned**

Introduction (or why another PHP ORM?)
--------------------------------------
 
1) Because Django has shown us how object persistence should work.
2) Because I dont think you should have to run an OpCache for a decent featureset.

Design Principles
~~~~~~~~~~~~~~~~~

* DRY - models, schemas and forms all driven from one lightweight meta class
* Built directly on PDO - no separate abstraction layer
* Embrace the exception - PDO will tell you pretty quick if there is a problem so there is no need for DB refelection
* Config in PHP - no XML or YAML config or compilation
* Memory concious - no features loaded unless required
* PHP 5.X compatible

TODO
~~~~

* Tests for other DB drivers
* Use full PHP open tags
 
Features
--------

Model definition
~~~~~~~~~~~~~~~~
::

    class Blog extends Dormio_Model {
      static function getMeta() {
        return array(
          'title' => array('type' => 'string', 'max_length' => 30),
          'author' => array('type' => 'foreignkey', 'model' => 'User'),
        );
      }
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
from Django.::

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
Generate an entire form complete with validation from your model.  Uses the Phorms library.::

    $blog = $dormio->get('Blog', 23);
    $form = Dormio_Form($blog);
    
    if($form->is_valid()) {
      $form->save();
    } else {
      echo $form;
    }

Schema Generation
~~~~~~~~~~~~~~~~~~
Generate schemas directly from your models. Can even upgrade them for you.::

    $pdo = new PDO('sqlite::memory');
    $sf = Dormio_Schema::factory('Blog', 'sqlite');
    $sf->batchExecute($sf->createTable(), $pdo);
    
Blistering performance
~~~~~~~~~~~~~~~~~~~~~~
Everything is kept as light as possible using just a lightweight meta description at the core.  This
results in code that runs nearly as fast as raw PDO and with a not much greater memory footprint while still
giving you a full featureset.::

                      | Insert | findPk | complex| hydrate|  with  |     MB |
                      |--------|--------|--------|--------|--------|--------|
               OptPDO |     42 |     46 |     96 |     80 |     65 |   0.54 |
                  PDO |    105 |    111 |    105 |    108 |    107 |   0.52 |
            OptDormio |     64 |    103 |    121 |    119 |     72 |   1.01 |
               Dormio |    313 |    125 |    146 |    200 |    203 |   0.96 |
             Outlet07 |    792 |     80 |    178 |    416 |    518 |   2.09 |
             Propel14 |   1453 |    601 |    183 |    364 |    397 |   2.98 |
             Propel15 |   1301 |    709 |    231 |    466 |    573 |   7.24 |
    Propel15WithCache |   1183 |    504 |    198 |    374 |    421 |   7.32 |
           Doctrine12 |   2445 |   3552 |    655 |   1968 |   2196 |  13.36 |
           
Obviously benchmarks are not real world, but they do throw out some interesting numbers...
More info on benchmarks at [https://github.com/tf198/php-orm-benchmark]

Why Dormio?
-----------

Being so closely related to Django ('*I Awake*' in Roma) then Dormio ('*I Sleep*' in Latin) seemed appropriate, especially
following in the footsteps of Java's *Hibernate*.