<?
require_once('simpletest/autorun.php');
require_once('db_tests.php');

class TestOfModel extends TestOfDB{

  function testInsertUpdateDelete() {
    $this->load("sql/test_schema.sql");
    // insert new
    $u1 = new User($this->db);
    $u1->name = 'Andy';
    $u1->save();
    $this->assertEqual($this->db->digest(), array('INSERT INTO "user" ("name") VALUES (?)', array(array('Andy'))));
    
    // load existing
    $u2 = new User($this->db);
    $u2->load(1);
    // check nothing executed yet
    $this->assertEqual($this->db->count(), 0);
    // on access hydration
    $this->assertEqual($u2->name, 'Andy');
    // should have hit the db now
    $this->assertEqual($this->db->digest(), array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" WHERE "user"."user_id" = ?', array(array(1))));
    
    // update and save
    $u2->name = 'Bob';
    $u2->save();
    $this->assertEqual($this->db->digest(), array('UPDATE "user" SET "name"=? WHERE "user_id" = ?', array(array('Bob', '1'))));
    
    $this->assertDigestedAll();
    
    // delete
    $this->assertEqual($u2->delete(),1);
  }
  
  function testToString() {
    $this->load("sql/test_schema.sql");
    $this->load('sql/test_data.sql');
    
    $blog = $this->pom->get('Blog');
    $this->assertEqual((string)$blog, '[blog:]');
    $blog = $this->pom->get('Blog', 3);
    $this->assertEqual((string)$blog, '[blog:3]');
    
    // custom toString on comment
    $comment = $this->pom->get('Comment', 1);
    $this->assertEqual((string)$comment, 'Andy Comment 1 on Andy Blog 1');
    $this->db->digest();
    
    // bad display string
    $comment = $this->pom->get('Comment', 76);
    $this->assertEqual((string)$comment, '[comment:No result found for primary key 76]');
    $this->db->digest();
    
    $this->assertDigestedAll();
  }
  
  function testNotFound() {
    $this->load("sql/test_schema.sql");
    $blog = new Blog($this->db);
    $blog->load(1);
    try {
      $blog->title;
      $this->fail('Should have thrown an exception');
    } catch(Dormio_Model_Exception $e) {
      $this->assertEqual($e->getMessage(), "No result found for primary key 1");
    }
  }
  
  function testDelete() {
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    $blog = new Blog($this->db);
    
    $blog->load(1);
    $this->assertEqual($blog->delete(), 9);
    $blog->load(2);
    $this->assertEqual($blog->delete(), 2);
    
    $user = new User($this->db);
    $user->load(1);
    $this->assertEqual($user->delete(), 4); // sounds about right after the previous actions but haven't checked
  }
  
  function testForeignKey() {
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    $b1 = new Blog($this->db);
    $b1->load(1);
    
    // Lazy
    $this->assertEqual($b1->the_user->name, 'Andy');
    $this->assertEqual($this->db->digest(), array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."blog_id" = ?', array(array('1'))));
    $this->assertEqual($this->db->digest(), array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" WHERE "user"."user_id" = ?', array(array('1'))));
    
    // Eager
    $blogs = new Dormio_Manager('Blog', $this->db);
    $b1 = $blogs->with('the_user')->get(1);
    $this->assertEqual($this->db->digest(), array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user", "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "blog" LEFT JOIN "user" ON "blog"."the_blog_user"="user"."user_id" WHERE "blog"."blog_id" = ? LIMIT 2', array(array('1'))));
    // all done in a single hit here
    $this->assertEqual($b1->the_user->name, 'Andy');
    
    $this->assertEqual($this->db->count(), 0);
    
    // Reverse
    $u1 = new User($this->db);
    $u1->load(1);
    $iter = $u1->blogs;
    // nothing should have been hit yet
    $this->assertEqual($this->db->count(), 0);
    
    $expected = array('Andy Blog 1', 'Andy Blog 2');
    $this->assertQueryset($iter, 'title', $expected);
    $this->assertEqual($this->db->digest(), array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ?', array(array(1))));
    
    $this->assertDigestedAll();
  }
  
  function testRepeatRelations() {
    // this test is needed as we use a reference to the parent id
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    $users = new Dormio_Manager('User', $this->db);
    $expected_users = array('Andy', 'Bob', 'Charles');
    $expected_blogs = array(
      array('Andy Blog 1', 'Andy Blog 2'),
      array('Bob Blog 1'),
      array(),
    );
    $i_users = 0;
    foreach($users as $user) {
      $i_blogs = 0;
      $this->assertEqual($user->name, $expected_users[$i_users]);
      foreach($user->blogs as $blog) {
        $this->assertEqual($blog->title, $expected_blogs[$i_users][$i_blogs++]);
      }
      $this->assertEqual($i_blogs, count($expected_blogs[$i_users]));
      $i_users++;
    }
    $this->assertEqual($i_users, 3);
    
    // the initial queryset
    $this->assertSQL('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user"');
    // only one prepared statement with two execution sets.  Params evaluate to 3 now as they are a reference
    //var_dump($this->db->digest());
    $this->assertEqual($this->db->digest(), array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ?', array(array(3), array(3), array(3))));
    
    $this->assertDigestedAll();
  }
  
  function testOneToOne() {
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    // Lazy
    $p2 = new Profile($this->db);
    $p2->load(2);
    $this->assertEqual($p2->age, 46);
    $this->assertEqual($this->db->digest(), array('SELECT "profile"."profile_id" AS "profile_profile_id", "profile"."user_id" AS "profile_user_id", "profile"."age" AS "profile_age" FROM "profile" WHERE "profile"."profile_id" = ?', array(array('2'))));
    $this->assertEqual($p2->user->name, 'Bob');
    $this->assertEqual($this->db->digest(), array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" WHERE "user"."user_id" = ?', array(array('2'))));
    
    // Reverse
    $u1 = new User($this->db);
    $u1->load(1);
    $this->assertEqual($u1->profile_set->age, 23);
    // doesn't even need to load the user for this
    //$this->assertEqual($this->db->digest(), array('SELECT "profile"."profile_id" AS "profile_profile_id", "profile"."user_id" AS "profile_user_id", "profile"."age" AS "profile_age" FROM "profile" WHERE "profile"."profile_id" = ?', array(array('1'))));
    
    $this->assertDigestedAll();
  }
  
  function testManyToMany() {
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    // Forward
    $b1 = new Blog($this->db);
    $b1->load(1);
    
    $iter = $b1->tags;
    // db not hit yet
    $this->assertEqual($this->db->count(), 0);
    
    $expected = array('Yellow', 'Indigo');
    $this->assertQueryset($iter, 'tag', $expected);
    $this->assertSQL('SELECT "tag"."tag_id" AS "tag_tag_id", "tag"."tag" AS "tag_tag" FROM "tag" INNER JOIN "blog_tag" ON "tag"."tag_id"="blog_tag"."the_tag_id" WHERE "blog_tag"."the_blog_id" = ?', 1);
    
    // do a test on one with normal fields
    $c1 = new Comment($this->db);
    $c1->load(2);
    
    $expected = array('Orange', 'Violet');
    $this->assertQueryset($c1->tags, 'tag', $expected);
    $this->assertSQL('SELECT "tag"."tag_id" AS "tag_tag_id", "tag"."tag" AS "tag_tag" FROM "tag" INNER JOIN "comment_tag" ON "tag"."tag_id"="comment_tag"."tag_id" WHERE "comment_tag"."comment_id" = ?', 2);
    
    $this->assertDigestedAll();
  }
  
  function testManyToManyReverse() {
    $this->load("sql/test_schema.sql");
    $this->load("sql/test_data.sql");
    
    $tag = new Tag($this->db);
    $tag->load('4'); // Green
   
    // overriden fields
    $expected = array('Andy Blog 2');
    $this->assertQueryset($tag->blog_set, 'title', $expected);
    $this->assertSQL('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "blog_tag" ON "blog"."blog_id"="blog_tag"."the_blog_id" WHERE "blog_tag"."the_tag_id" = ?', 4);
    
    // default fields
    $expected = array('Andy Comment 1 on Andy Blog 1', 'Andy Comment 1 on Bob Blog 1');
    $this->assertQueryset($tag->comment_set, 'title', $expected);
    $this->assertSQL('SELECT "comment"."comment_id" AS "comment_comment_id", "comment"."title" AS "comment_title", "comment"."the_comment_user" AS "comment_the_comment_user", "comment"."blog_id" AS "comment_blog_id" FROM "comment" INNER JOIN "comment_tag" ON "comment"."comment_id"="comment_tag"."comment_id" WHERE "comment_tag"."tag_id" = ?', 4);
    
    $this->assertDigestedAll();
  }
}

?>
