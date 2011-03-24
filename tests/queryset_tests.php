<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfSQL extends UnitTestCase{
  private $all_blogs = array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog"', array());
  
  private $with_join = 'LEFT';
  private $filter_join = 'INNER';
  private $field_join = 'LEFT';

  function testConstruct() {
    $qs = new Dormio_Queryset('Blog');
    $this->assertEqual($qs->select(), $this->all_blogs);
    
    // invalid class name - should throw exception
    try {
      $qs = new Dormio_Queryset('Rubbish');
      $this->fail('Should have thrown exception');
    } catch(Dormio_Meta_Exception $e) { $this->pass(); }
  }
  
  function testResolve() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->_resolveField('title'), '{blog}.{title}');
    $this->assertEqual($blogs->_resolveField('pk'), '{blog}.{blog_id}');
    $this->assertEqual($blogs->_resolveField('the_user'), '{blog}.{the_blog_user}'); // foreign pk
    $this->assertEqual($blogs->_resolveField('the_user__pk'), '{blog}.{the_blog_user}'); // no need to actually get the user
    $this->assertEqual($blogs->_resolveField('the_user__name'), '{user}.{name}'); // foreignkey
    $this->assertEqual($blogs->_resolveField('comment_set__title'), '{comment}.{title}'); // foreignkey_rev
    $this->assertEqual($blogs->_resolveField('tags__tag'), '{tag}.{tag}'); // manytomany
    
    $comments = new Dormio_Queryset('Comment');
    $this->assertEqual($comments->_resolveField('pk'), '{comment}.{comment_id}');
    $this->assertEqual($comments->_resolveField('blog'), '{comment}.{blog_id}'); // foreign pk
    $this->assertEqual($comments->_resolveField('blog__title'), '{blog}.{title}'); // foreignkey
    $this->assertEqual($comments->_resolveField('blog__the_user__name'), '{user}.{name}'); // multistage
    $this->assertEqual($comments->_resolveField('tags__tag'), '{tag}.{tag}'); // manytomany
    
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->_resolveField('profile_set__age'), '{profile}.{age}'); // onetoone_rev
    
    $profile = new Dormio_Queryset('Profile');
    $this->assertEqual($profile->_resolveField('user__name'), '{user}.{name}'); // onetoone
    
    $tags = new Dormio_Queryset('Tag');
    $this->assertEqual($tags->_resolveField('pk'), '{tag}.{tag_id}');
    $this->assertEqual($tags->_resolveField('blog_set__title'), '{blog}.{title}'); // manytomany_rev
    $this->assertEqual($tags->_resolveField('comment_set__title'), '{comment}.{title}'); // manytomany_rev
  }
 
  function testJoin() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->with('the_user')->query['join'], //foreignkey
      array('LEFT JOIN {user} ON {blog}.{the_blog_user}={user}.{user_id}'));
    $this->assertEqual($blogs->filter('comment_set__title', '=', 'Test')->query['join'], // foreignkey_rev
      array('INNER JOIN {comment} ON {blog}.{blog_id}={comment}.{blog_id}')); 
    $this->assertEqual($blogs->filter('tags__tag', '=', 'Red')->query['join'], //manytomany
      array('INNER JOIN {blog_tag} ON {blog}.{blog_id}={blog_tag}.{the_blog_id}', 'INNER JOIN {tag} ON {blog_tag}.{the_tag_id}={tag}.{tag_id}'));
    
    $comments = new Dormio_Queryset('Comment');
    $this->assertEqual($comments->with('user')->query['join'], // foreignkey
      array('LEFT JOIN {user} ON {comment}.{the_comment_user}={user}.{user_id}'));
    $this->assertEqual($comments->with('blog')->query['join'], // foreignkey
      array('LEFT JOIN {blog} ON {comment}.{blog_id}={blog}.{blog_id}')); 
    $this->assertEqual($comments->filter('tags__tag', '=', 'Red')->query['join'], // manytomany
      array('INNER JOIN {comment_tag} ON {comment}.{comment_id}={comment_tag}.{comment_id}', 'INNER JOIN {tag} ON {comment_tag}.{tag_id}={tag}.{tag_id}')); 
    
    $profiles = new Dormio_Queryset('Profile');
    $this->assertEqual($profiles->with('user')->query['join'], // onetoone
      array('LEFT JOIN {user} ON {profile}.{user_id}={user}.{user_id}'));
      
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->with('profile_set')->query['join'], // foreignkey
      array('LEFT JOIN {profile} ON {user}.{user_id}={profile}.{user_id}'));
      
    $tags = new Dormio_Queryset('Tag');
    $this->assertEqual($tags->filter('blog_set__title', '=', 'Test')->query['join'], // manytomany_rev
      array('INNER JOIN {blog_tag} ON {tag}.{tag_id}={blog_tag}.{the_tag_id}', 'INNER JOIN {blog} ON {blog_tag}.{the_blog_id}={blog}.{blog_id}'));
    $this->assertEqual($tags->filter('comment_set__title', '=', 'Test')->query['join'], // manytomany_rev
      array('INNER JOIN {comment_tag} ON {tag}.{tag_id}={comment_tag}.{tag_id}', 'INNER JOIN {comment} ON {comment_tag}.{comment_id}={comment}.{comment_id}'));
  }
 
  function testField() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->field('comments__title')->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user", "comment"."title" AS "blog_comments_title" FROM "blog" LEFT JOIN "comment" ON "blog"."blog_id"="comment"."blog_id"', array()));
  }
 
  function testFilter() {
    $blogs = new Dormio_Queryset('Blog');
    $comments = new Dormio_Queryset('Comment');
    
    // normal field
    $this->assertEqual($blogs->filter('title', '=', 'hello')->select(), 
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."title" = ?', array('hello')));
    
    // primary key
    $this->assertEqual($blogs->filter('pk', '=', 1)->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."blog_id" = ?', array(1)));
      
    // foreign key with id
    $this->assertEqual($blogs->filter('the_user', '=', 2)->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ?', array(2)));
      
    // foreign key with obj
    $user = new User(new PDO('sqlite::memory:'));
    $user->_hydrate(array('user_user_id' => 3), true);
    $this->assertEqual($blogs->filter('the_user', '=', $user)->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ?', array(3)));
    
    // non existent field
    try { $blogs->filter('rubbish', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // direct object field access
    try { $blogs->filter('user_id', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // direct pk access
    try { $blogs->filter('blog_id', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // one step relation
    $this->assertEqual($comments->filter('blog__title', '=', 'hello')->select(), 
      array('SELECT "comment"."comment_id" AS "comment_comment_id", "comment"."title" AS "comment_title", "comment"."the_comment_user" AS "comment_the_comment_user", "comment"."blog_id" AS "comment_blog_id" FROM "comment" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" WHERE "blog"."title" = ?', array('hello')));
    
    // two step relation
    $this->assertEqual($comments->filter('blog__the_user__name', '=', 'tris')->select(), 
      array('SELECT "comment"."comment_id" AS "comment_comment_id", "comment"."title" AS "comment_title", "comment"."the_comment_user" AS "comment_the_comment_user", "comment"."blog_id" AS "comment_blog_id" FROM "comment" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" INNER JOIN "user" ON "blog"."the_blog_user"="user"."user_id" WHERE "user"."name" = ?', array('tris')));
      
    // IN operator
    $this->assertEqual($blogs->filter('the_user__name', 'IN', array('Andy', 'Dave'))->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "user" ON "blog"."the_blog_user"="user"."user_id" WHERE "user"."name" IN (?, ?)', array('Andy', 'Dave')));
  }

  function testWhere() {
    $blogs = new Dormio_Queryset('Blog');
    
    $this->assertEqual($blogs->where('{the_user} = ?', array(1))->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" WHERE "blog"."the_blog_user" = ?', array(1)));
  }
  
  function testLimit() {
    $users = new Dormio_Queryset('User');
    
    $this->assertEqual($users->limit(3)->select(), 
      array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" LIMIT 3', array()));
      
    $this->assertEqual($users->limit(4,2)->select(), 
      array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" LIMIT 4 OFFSET 2', array()));
  }
  
  function testOrder() {
    $users = new Dormio_Queryset('User');
    $blogs = new Dormio_Queryset('Blog');
    
    // single
    $this->assertEqual($users->orderBy('name')->select(),
      array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" ORDER BY "user"."name"', array()));
      
    // multiple
    $this->assertEqual($users->orderBy('name', 'pk')->select(),
      array('SELECT "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "user" ORDER BY "user"."name", "user"."user_id"', array()));
      
    // related
    $this->assertEqual($blogs->orderBy('the_user__name')->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "user" ON "blog"."the_blog_user"="user"."user_id" ORDER BY "user"."name"', array()));
  }
  
  function testWith() {
    $blogs = new Dormio_Queryset('Blog');
    
    // single
    $this->assertEqual($blogs->with('the_user')->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user", "user"."user_id" AS "user_user_id", "user"."name" AS "user_name" FROM "blog" LEFT JOIN "user" ON "blog"."the_blog_user"="user"."user_id"', array()));
      
    // TODO: need to add more tests here but seems to work
  }
  
  function testManyToMany() {
    $blogs = new Dormio_Queryset('Blog');
    
    //var_dump($blogs->filter('tags__tag', '=', 'testing')->select());
    $this->assertEqual($blogs->filter('tags__tag', '=', 'testing')->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "blog_tag" ON "blog"."blog_id"="blog_tag"."the_blog_id" INNER JOIN "tag" ON "blog_tag"."the_tag_id"="tag"."tag_id" WHERE "tag"."tag" = ?', array('testing')));
  }
 
  function testReverse() {
    $blogs = new Dormio_Queryset('Blog');
    $tags = new Dormio_Queryset('Tag');
    
    // reverse foreign key
    $this->assertEqual($blogs->filter('comment_set__title', '=', 'Test')->select(),
      array('SELECT "blog"."blog_id" AS "blog_blog_id", "blog"."title" AS "blog_title", "blog"."the_blog_user" AS "blog_the_blog_user" FROM "blog" INNER JOIN "comment" ON "blog"."blog_id"="comment"."blog_id" WHERE "comment"."title" = ?', array('Test')));
      
    // reverse manytomany
    //var_dump($tags->with('blog_set')->select());
    $this->assertEqual($tags->filter('blog_set__title', '=', 'Test')->select(),
      array('SELECT "tag"."tag_id" AS "tag_tag_id", "tag"."tag" AS "tag_tag" FROM "tag" INNER JOIN "blog_tag" ON "tag"."tag_id"="blog_tag"."the_tag_id" INNER JOIN "blog" ON "blog_tag"."the_blog_id"="blog"."blog_id" WHERE "blog"."title" = ?', array('Test')));
  }

  function testUpdate() {
    $blogs = new Dormio_Queryset('Blog');
    $set = $blogs->filter('title', '=', 'Blog 1');
    
    $this->assertEqual($set->update(array('the_user' => 1)), 
      array('UPDATE "blog" SET "the_blog_user"=? WHERE "blog"."title" = ?', array(1, 'Blog 1')));
    
    $this->assertEqual($set->limit(2)->update(array('the_user' => 2)),
      array('UPDATE "blog" SET "the_blog_user"=? WHERE "blog"."title" = ? LIMIT 2', array(2, 'Blog 1')));
      
    // joined criteria
    $set = $set->filter('comments__user', '=', 1);
    //var_dump($set->update(array('title' => 'New Title')));
  }
  
  function testDeleteById() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->deleteById(3), array(
      array('DELETE FROM "blog_tag" WHERE "blog_tag"."the_blog_id" = ?', array(3)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag"."comment_tag_id" IN (SELECT "comment_tag"."comment_tag_id" FROM "comment_tag" INNER JOIN "comment" ON "comment_tag"."comment_id"="comment"."comment_id" WHERE "comment"."blog_id" = ?)', array(3)),
      array('DELETE FROM "comment" WHERE "comment"."blog_id" = ?', array(3)),
      array('DELETE FROM "blog" WHERE "blog"."blog_id" = ?', array(3)),
    ));
    
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->deleteById(1), array(
      array('DELETE FROM "blog_tag" WHERE "blog_tag"."blog_tag_id" IN (SELECT "blog_tag"."blog_tag_id" FROM "blog_tag" INNER JOIN "blog" ON "blog_tag"."the_blog_id"="blog"."blog_id" WHERE "blog"."the_blog_user" = ?)', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag"."comment_tag_id" IN (SELECT "comment_tag"."comment_tag_id" FROM "comment_tag" INNER JOIN "comment" ON "comment_tag"."comment_id"="comment"."comment_id" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" WHERE "blog"."the_blog_user" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "comment"."comment_id" IN (SELECT "comment"."comment_id" FROM "comment" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" WHERE "blog"."the_blog_user" = ?)', array(1)), 
      array('DELETE FROM "blog" WHERE "blog"."the_blog_user" = ?', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag"."comment_tag_id" IN (SELECT "comment_tag"."comment_tag_id" FROM "comment_tag" INNER JOIN "comment" ON "comment_tag"."comment_id"="comment"."comment_id" WHERE "comment"."the_comment_user" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "comment"."the_comment_user" = ?', array(1)),
      array('UPDATE "profile" SET "user_id"=? WHERE "profile"."user_id" = ?', array(null, 1)),
      array('DELETE FROM "user" WHERE "user"."user_id" = ?', array(1)),
    ));
  }
  
  function testDelete() {
    $blogs = new Dormio_Queryset('Blog');
    $set = $blogs->filter('pk', '=', 1);
    $sql = $set->delete();
    $this->assertEqual($sql, array(
      array('DELETE FROM "blog_tag" WHERE "blog_tag"."blog_tag_id" IN (SELECT "blog_tag"."blog_tag_id" FROM "blog_tag" INNER JOIN "blog" ON "blog_tag"."the_blog_id"="blog"."blog_id" WHERE "blog"."blog_id" = ?)', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag"."comment_tag_id" IN (SELECT "comment_tag"."comment_tag_id" FROM "comment_tag" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" INNER JOIN "comment" ON "comment_tag"."comment_id"="comment"."comment_id" WHERE "blog"."blog_id" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "comment"."comment_id" IN (SELECT "comment"."comment_id" FROM "comment" INNER JOIN "blog" ON "comment"."blog_id"="blog"."blog_id" WHERE "blog"."blog_id" = ?)', array(1)),
      array('DELETE FROM "blog" WHERE "blog"."blog_id" = ?', array(1)),
    ));
  }
  
  function testNonMutation() {
    $qs = new Dormio_Queryset('Blog');
    $qs->filter('title', '=', 'hello');
    $this->assertEqual($qs->select(), $this->all_blogs);
    $qs->with('the_user');
    $this->assertEqual($qs->select(), $this->all_blogs);
    $qs->limit(1,2);
    $this->assertEqual($qs->select(), $this->all_blogs);
    $qs->orderBy('the_user');
    $this->assertEqual($qs->select(), $this->all_blogs);
  }

}