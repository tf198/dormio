<?
require_once('simpletest/autorun.php');
require_once('db_tests.php');

class TestOfManager extends TestOfDB{
  function testGet() {
    $this->load('sql/test_data.sql');
    
    $blogs = new Dormio_Manager('Blog', $this->db);
    
    // without any parameters
    /*
    try {
      $blogs->get();
      $this->fail("Should have thrown exception");
    } catch(Dormio_Manager_Exception $e) {
      $this->assertEqual($e->getMessage(), "Need some criteria for get()");
    }
    */
    
    // basic pk load
    $blog = $blogs->get(2);
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."blog_id" = ? LIMIT 2', 2);
    
    // bad pk load
    try {
      $blog = $blogs->get(23);
      $this->fail("Should have thrown exception");
    } catch(Dormio_Manager_Exception $e) {
      $this->assertEqual($e->getMessage(), "No record returned");
    }
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."blog_id" = ? LIMIT 2', 23);
    
    
    // eager load
    $blog = $blogs->with('the_user')->get(1);
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user", "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "blog" INNER JOIN "user" ON "blog"."the_blog_user"="user"."user_id" WHERE "blog"."blog_id" = ? LIMIT 2', 1);
    $this->assertEqual($blog->the_user->name, 'Andy');
    
    // complex query and pk
    $blog = $blogs->filter('the_user__name', '=', 'Andy')->get(2);
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "user" ON "blog"."the_blog_user"="user"."user_id" WHERE "user"."name" = ? AND "blog"."blog_id" = ? LIMIT 2', 'Andy', 2);
    $this->assertEqual($blog->title, 'Andy Blog 2');
    
    // other query
    $blog = $blogs->filter('title', '=', 'Andy Blog 2')->get();
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."title" = ? LIMIT 2', 'Andy Blog 2');
    $this->assertEqual($blog->ident(), 2);
    
    // non specific query
    try {
      $blog = $blogs->filter('the_user', '=', 1)->get();
      $this->fail("Should have thrown exception");
    } catch(Dormio_Manager_Exception $e) {
      $this->assertEqual($e->getMessage(), "More than one record returned");
    }
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ? LIMIT 2', 1);
    
    $this->assertDigestedAll();
  }
  
  function testAggregationMethods() {
    $this->load("sql/test_data.sql");
    
    $tags = new Dormio_Manager('Tag', $this->db);
    
    $data = $tags->filter('tag', '<', 'H')->aggregate()->count('pk', true)->max('tag')->run();
    $this->assertEqual($data['pk_count'], 2);
    $this->assertEqual($data['tag_max'], 'Green');
    $this->assertSQL('SELECT COUNT(DISTINCT "tag_id") AS "pk_count", MAX("tag") AS "tag_max" FROM "tag" WHERE "tag"."tag" < ?', 'H');
    
    $data = $tags->aggregate()->count()->avg()->sum()->run();
    $this->assertEqual($data['pk_count'], 7);
    $this->assertEqual($data['pk_sum'], 28);
    $this->assertEqual($data['pk_avg'], 4);
  }
  
  function testInsert() {
    $blogs = new Dormio_Manager('Blog', $this->db);
    $stmt = $blogs->insert(array('title', 'the_user'));
    $this->assertEqual($stmt->_stmt->queryString, 'INSERT INTO "blog" ("title", "the_blog_user") VALUES (?, ?)');
  }
  
  function testUpdate() {
    $this->load("sql/test_data.sql");
    $comments = new Dormio_Manager('Comment', $this->db);
    $set = $comments->filter('blog', '=', 1)->filter('tags__tag', '=', 'Green');
    $this->assertEqual($set->update(array('title' => 'New Title')), 1);
    $comment = $comments->get(1);
    $this->assertEqual($comment->title, 'New Title');
  }
  
  function testDelete() {
    $this->load("sql/test_data.sql");
    
    $blogs = new Dormio_Manager('Blog', $this->db);
    $set = $blogs->filter('title', '=', 'Andy Blog 1');
    $this->assertEqual($set->delete(), 8);
    //var_dump($this->db->stack);
  }
  
  function testForeignKeyCreate() {
    $this->load("sql/test_data.sql");
    $blog = new Blog($this->db);
    $blog->load(2);
    $this->assertEqual($blog->title, 'Andy Blog 2');
    
    $comment = $blog->comments->create(array('title' => 'New Comment'));
    $comment->user = 1;
    $comment->save();
    $this->db->digest();
    $this->assertEqual($this->db->digest(), 
      array('INSERT INTO "comment" ("blog_id", "title", "the_comment_user") VALUES (?, ?, ?)', array(array(2, 'New Comment', 1))));
  }
  
  function testForeignKeyAdd() {
    $this->load("sql/test_data.sql");
    $blog = new Blog($this->db);
    $blog->load(2);
    $this->assertEqual($blog->title, 'Andy Blog 2');
    
    $comment = new Comment($this->db);
    $comment->title = "Another new comment";
    $comment->user = 1;
    $blog->comments->add($comment);
    $this->db->digest();
    $this->assertEqual($this->db->digest(), 
      array('INSERT INTO "comment" ("title", "the_comment_user", "blog_id") VALUES (?, ?, ?)', array(array('Another new comment', 1, 2))));
  }
  
  function testManyToManyAdd() {
    $this->load("sql/test_data.sql");
    
    $blog = $this->pom->get('Blog', 1);
    
    $tag = $blog->tags->create();
    $tag->tag = 'Black';
    
    $blog->tags->add($tag);
    // tag is automatically saved before attaching
    $this->assertSQL('INSERT INTO "tag" ("tag") VALUES (?)', 'Black');
    $this->assertSQL('INSERT INTO "blog_tag" ("the_blog_id", "the_tag_id") VALUES (?, ?)', 1, 8);
    
    // try the other way round
    $tag = $blog->tags->create(array('tag' => 'White'));
    $tag->save();
    $this->assertSQL('INSERT INTO "tag" ("tag") VALUES (?)', 'White');
    $tag->blogs->add($blog);
    $this->assertSQL('INSERT INTO "blog_tag" ("the_tag_id", "the_blog_id") VALUES (?, ?)', 9, 1);
    
    
    $this->assertDigestedAll();
  }
  
  function testClear() {
    $this->load("sql/test_data.sql");
    
    $blog = $this->pom->get('Blog', 1);
    $this->assertEqual($blog->tags->clear(), 2);
    $this->assertSQL('DELETE FROM "blog_tag" WHERE "blog_tag"."the_blog_id" = ?', 1);
    
    $this->assertDigestedAll();
  }
  
  function testRemove() {
    $this->load("sql/test_data.sql");
    
    $blog = $this->pom->get('Blog', 1);
    
    // Yellow(3) is on blog 1
    $this->assertEqual($blog->tags->remove(3), 1);
    $this->assertSQL('DELETE FROM "blog_tag" WHERE "blog_tag"."the_tag_id" = ? AND "blog_tag"."the_blog_id" = ?', 3, 1);
    
    // Red(1) is not on blog 1
    $this->assertEqual($blog->tags->remove(1), 0);
    $this->assertSQL('DELETE FROM "blog_tag" WHERE "blog_tag"."the_tag_id" = ? AND "blog_tag"."the_blog_id" = ?', 1, 1);
    
    // reverse with a model instead of pk
    $tag = $this->pom->get('Tag', 4); // Green
    $blog = $this->pom->get('Blog', 2);
    $this->assertEqual($tag->blogs->remove($blog), 1);
    $this->assertSQL('DELETE FROM "blog_tag" WHERE "blog_tag"."the_blog_id" = ? AND "blog_tag"."the_tag_id" = ?', 2, 4);
    
    $this->assertDigestedAll();
  }
}
?>
