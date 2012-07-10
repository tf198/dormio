Dormio Queries
==============

Creating objects
----------------
    <?php
    // a fresh object
    $blog = $dormio->getObject('Blog');
    $blog->title = "My Blog";
    $blog->user = 1;
    $blog->body = "My first blog";
    $blog->save();
    
    // append to a manager
    $blogs = $dormio->getManager('Blog');
    $blogs->create(array('title' => 'My Blog', 'user' => 1, 'body' => 'My first blog'));
    // created and saved in one operation
    ?>
    
Fetching objects
----------------
You can interact with your data as objects like any other ORM

    <?php
    // by id
    $blog = $dormio->getObject('Blog', 23);
    
    // alternate lookup by manager
    $blog = $dormio->getManager('Blog')->findOne(23);
    
    // lookup by any other unique field
    $blog = $dormio->getManager('Blog')->findOne('My Blog', 'title');
    echo $blog->body;
    ?>

Fetching arrays
---------------
Dorio tries to make it as easy to interact with the raw data as objects - much
more efficient (and how Dormio_Tables works internally)

    <?php
    $blogs = $dormio->getManager('Blog')->with('author');
    foreach($blogs->fetchArray() as $data) {
      fprintf("%20s %20s", $data['title'], $data['author__name']);
    }
    ?>

Modifiying objects
------------------
    <?php
    // using objects
    $blog = $dormio->getObject('Blog', 23);
    $blog->body = str_replace('darn', '####', $blog->body);
    $blog->save();
    
    // or if you dont need logic then you can bulk update it
    $dormio->getManager('Blog')->filter('body', 'LIKE', '%darn%')
      ->update('body' => '### CENSORED ###');
    ?>