<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfSQL extends UnitTestCase{
  private $all_blogs = array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1', array());
  
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
    $this->assertEqual($blogs->_resolveField('title'), '<@t1.@>{title}');
    $this->assertEqual($blogs->_resolveField('pk'), '<@t1.@>{blog_id}');
    $this->assertEqual($blogs->_resolveField('the_user'), '<@t1.@>{the_blog_user}'); // foreign pk
    $this->assertEqual($blogs->_resolveField('the_user__pk'), '<@t1.@>{the_blog_user}'); // no need to actually get the user
    $this->assertEqual($blogs->_resolveField('the_user__name'), '<@t2.@>{name}'); // foreignkey
    $this->assertEqual($blogs->_resolveField('comment_set__title'), '<@t3.@>{title}'); // foreignkey_rev
    $this->assertEqual($blogs->_resolveField('tags__tag'), '<@t5.@>{tag}'); // manytomany
    
    $comments = new Dormio_Queryset('Comment');
    $this->assertEqual($comments->_resolveField('pk'), '<@t1.@>{comment_id}');
    $this->assertEqual($comments->_resolveField('blog'), '<@t1.@>{blog_id}'); // foreign pk
    $this->assertEqual($comments->_resolveField('blog__title'), '<@t2.@>{title}'); // foreignkey
    $this->assertEqual($comments->_resolveField('blog__the_user__name'), '<@t3.@>{name}'); // multistage
    $this->assertEqual($comments->_resolveField('tags__tag'), '<@t5.@>{tag}'); // manytomany
    
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->_resolveField('profile_set__age'), '<@t2.@>{age}'); // onetoone_rev
    
    $profile = new Dormio_Queryset('Profile');
    $this->assertEqual($profile->_resolveField('user__name'), '<@t2.@>{name}'); // onetoone
    
    $tags = new Dormio_Queryset('Tag');
    $this->assertEqual($tags->_resolveField('pk'), '<@t1.@>{tag_id}');
    $this->assertEqual($tags->_resolveField('blog_set__title'), '<@t3.@>{title}'); // manytomany_rev
    $this->assertEqual($tags->_resolveField('comment_set__title'), '<@t5.@>{title}'); // manytomany_rev

    $this->assertEqual($tags->_resolveString("SELECT * FROM %table%"), "SELECT * FROM {tag}");
  }
 
  function testJoin() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->with('the_user')->query['join'], //foreignkey
      array('LEFT JOIN {user} AS t2 ON t1.{the_blog_user}=t2.{user_id}'));
    $this->assertEqual($blogs->filter('comment_set__title', '=', 'Test')->query['join'], // foreignkey_rev
      array('INNER JOIN {comment} AS t2 ON t1.{blog_id}=t2.{blog_id}')); 
    $this->assertEqual($blogs->filter('tags__tag', '=', 'Red')->query['join'], //manytomany
      array('INNER JOIN {blog_tag} AS t2 ON t1.{blog_id}=t2.{the_blog_id}', 'INNER JOIN {tag} AS t3 ON t2.{the_tag_id}=t3.{tag_id}'));
    
    $comments = new Dormio_Queryset('Comment');
    $this->assertEqual($comments->with('user')->query['join'], // foreignkey
      array('LEFT JOIN {user} AS t2 ON t1.{the_comment_user}=t2.{user_id}'));
    $this->assertEqual($comments->with('blog')->query['join'], // foreignkey
      array('LEFT JOIN {blog} AS t2 ON t1.{blog_id}=t2.{blog_id}')); 
    $this->assertEqual($comments->filter('tags__tag', '=', 'Red')->query['join'], // manytomany
      array('INNER JOIN {comment_tag} AS t2 ON t1.{comment_id}=t2.{l_comment_id}', 'INNER JOIN {tag} AS t3 ON t2.{r_tag_id}=t3.{tag_id}')); 
    
    $profiles = new Dormio_Queryset('Profile');
    $this->assertEqual($profiles->with('user')->query['join'], // onetoone
      array('LEFT JOIN {user} AS t2 ON t1.{user_id}=t2.{user_id}'));
      
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->with('profile_set')->query['join'], // foreignkey
      array('LEFT JOIN {profile} AS t2 ON t1.{user_id}=t2.{user_id}'));
      
    $tags = new Dormio_Queryset('Tag');
    $this->assertEqual($tags->filter('blog_set__title', '=', 'Test')->query['join'], // manytomany_rev
      array('INNER JOIN {blog_tag} AS t2 ON t1.{tag_id}=t2.{the_tag_id}', 'INNER JOIN {blog} AS t3 ON t2.{the_blog_id}=t3.{blog_id}'));
    $this->assertEqual($tags->filter('comment_set__title', '=', 'Test')->query['join'], // manytomany_rev
      array('INNER JOIN {comment_tag} AS t2 ON t1.{tag_id}=t2.{r_tag_id}', 'INNER JOIN {comment} AS t3 ON t2.{l_comment_id}=t3.{comment_id}'));
    
    $nodes = new Dormio_Queryset('Tree');
    $this->assertEqual($nodes->filter('parent__name', '=', 'Bob')->query['join'],
            array('INNER JOIN {tree} AS t2 ON t1.{parent_id}=t2.{tree_id}'));
    
    $modules = new Dormio_Queryset('Module');
    $this->assertEqual($modules->filter('depends_on__name', '=', 'core')->query['join'], // manytomany self
      array('INNER JOIN {module_module} AS t2 ON t1.{module_id}=t2.{l_module_id}', 'INNER JOIN {module} AS t3 ON t2.{r_module_id}=t3.{module_id}'));
    $this->assertEqual($modules->filter('required_by__name', '=', 'core')->query['join'], // manytomany self
      array('INNER JOIN {module_module} AS t2 ON t1.{module_id}=t2.{r_module_id}', 'INNER JOIN {module} AS t3 ON t2.{l_module_id}=t3.{module_id}'));
  }
 
  function testField() {
    $blogs = new Dormio_Queryset('Blog');
    $this->assertEqual($blogs->field('comments__title')->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."title" AS "t2_comments_title" FROM "blog" AS t1 LEFT JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id"', array()));
  }
 
  function testFilter() {
    $blogs = new Dormio_Queryset('Blog');
    $comments = new Dormio_Queryset('Comment');
    
    // normal field
    $this->assertEqual($blogs->filter('title', '=', 'hello')->select(), 
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" = ?', array('hello')));
    
    // primary key
    $this->assertEqual($blogs->filter('pk', '=', 1)->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ?', array(1)));
      
    // foreign key with id
    $this->assertEqual($blogs->filter('the_user', '=', 2)->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(2)));
      
    // foreign key with obj
    $user = new User(new PDO('sqlite::memory:'));
    $user->load(3);
    $this->assertEqual($blogs->filter('the_user', '=', $user)->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(3)));
    
    // non existent field
    try { $blogs->filter('rubbish', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // direct object field access
    try { $blogs->filter('user_id', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // direct pk access
    try { $blogs->filter('blog_id', '=', 1); $this->fail('Should have thrown exception'); } catch(Dormio_Queryset_Exception $e) {$this->pass();}
    
    // one step relation
    $this->assertEqual($comments->filter('blog__title', '=', 'hello')->select(), 
      array('SELECT t1."comment_id" AS "t1_comment_id", t1."title" AS "t1_title", t1."the_comment_user" AS "t1_the_comment_user", t1."blog_id" AS "t1_blog_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('hello')));
    
    // two step relation
    $this->assertEqual($comments->filter('blog__the_user__name', '=', 'tris')->select(), 
      array('SELECT t1."comment_id" AS "t1_comment_id", t1."title" AS "t1_title", t1."the_comment_user" AS "t1_the_comment_user", t1."blog_id" AS "t1_blog_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" INNER JOIN "user" AS t3 ON t2."the_blog_user"=t3."user_id" WHERE t3."name" = ?', array('tris')));
      
    // IN operator
    $this->assertEqual($blogs->filter('the_user__name', 'IN', array('Andy', 'Dave'))->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t2."name" IN (?, ?)', array('Andy', 'Dave')));
  }

  function testWhere() {
    $blogs = new Dormio_Queryset('Blog');
    
    $this->assertEqual($blogs->where('%the_user% = ?', array(1))->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(1)));
  }
  
  function testLimit() {
    $users = new Dormio_Queryset('User');
    
    $this->assertEqual($users->limit(3)->select(), 
      array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 LIMIT 3', array()));
      
    $this->assertEqual($users->limit(4,2)->select(), 
      array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 LIMIT 4 OFFSET 2', array()));
  }
  
  function testOrder() {
    $users = new Dormio_Queryset('User');
    $blogs = new Dormio_Queryset('Blog');
    
    // single
    $this->assertEqual($users->orderBy('name')->select(),
      array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name"', array()));
      
    // multiple
    $this->assertEqual($users->orderBy('name', 'pk')->select(),
      array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id"', array()));
    
    // descending
    $this->assertEqual($users->orderBy('name', '-pk')->select(),
      array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id" DESC', array()));
    
    // related
    $this->assertEqual($blogs->orderBy('the_user__name')->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" ORDER BY t2."name"', array()));
  }
  
  function testWith() {
    $blogs = new Dormio_Queryset('Blog');
    
    // single
    $this->assertEqual($blogs->with('the_user')->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."user_id" AS "t2_user_id", t2."name" AS "t2_name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id"', array()));
      
    // TODO: need to add more tests here but seems to work
  }
  
  function testManyToMany() {
    $blogs = new Dormio_Queryset('Blog');
    
    //var_dump($blogs->filter('tags__tag', '=', 'testing')->select());
    $this->assertEqual($blogs->filter('tags__tag', '=', 'testing')->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "blog_tag" AS t2 ON t1."blog_id"=t2."the_blog_id" INNER JOIN "tag" AS t3 ON t2."the_tag_id"=t3."tag_id" WHERE t3."tag" = ?', array('testing')));
  }
 
  function testReverse() {
    $blogs = new Dormio_Queryset('Blog');
    $tags = new Dormio_Queryset('Tag');
    
    // reverse foreign key
    $this->assertEqual($blogs->filter('comment_set__title', '=', 'Test')->select(),
      array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('Test')));
      
    // reverse manytomany
    //var_dump($tags->with('blog_set')->select());
    $this->assertEqual($tags->filter('blog_set__title', '=', 'Test')->select(),
      array('SELECT t1."tag_id" AS "t1_tag_id", t1."tag" AS "t1_tag" FROM "tag" AS t1 INNER JOIN "blog_tag" AS t2 ON t1."tag_id"=t2."the_tag_id" INNER JOIN "blog" AS t3 ON t2."the_blog_id"=t3."blog_id" WHERE t3."title" = ?', array('Test')));
  }
  
  function testAliases() {
    $comments = new Dormio_Queryset('Comment');
    
    $set = $comments->with('blog')->filter('tags__tag', '=', 'Yo');
    $this->assertEqual($set->aliases, array(
        "comment" => "t1", 
        "comment.blog__blog.pk" => "t2",
        "comment.pk__comment_tag.l_comment" => "t3",
        "comment_tag.r_tag__tag.pk" => "t4",
    ));
  
  }

  function testUpdate() {
    $blogs = new Dormio_Queryset('Blog');
    $set = $blogs->filter('title', '=', 'Blog 1');
    
    $this->assertEqual($set->update(array('the_user' => 1)), 
      array('UPDATE "blog" SET "the_blog_user"=? WHERE "title" = ?', array(1, 'Blog 1')));
    
    $this->assertEqual($set->limit(2)->update(array('the_user' => 2)),
      array('UPDATE "blog" SET "the_blog_user"=? WHERE "title" = ? LIMIT 2', array(2, 'Blog 1')));
      
    // joined criteria
    $set = $set->filter('comments__user', '=', 1);
    //var_dump($set->update(array('title' => 'New Title')));
  }
  
  function testInsert() {
    $blogs = new Dormio_Queryset('Blog');
    
    $this->assertEqual($blogs->insert(array('the_user'=>1, 'title'=>'A blog')),
            array('INSERT INTO "blog" ("the_blog_user", "title") VALUES (?, ?)', array(1, 'A blog')));
  }
  
  
  function testDeleteById() {
    $blogs = new Dormio_Queryset('Blog');
    $sql = $blogs->deleteById(3);
    $this->assertEqual($sql, array(
      array('DELETE FROM "blog_tag" WHERE "the_blog_id" = ?', array(3)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag_id" IN (SELECT t1."comment_tag_id" FROM "comment_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."blog_id" = ?)', array(3)),
      array('DELETE FROM "comment" WHERE "blog_id" = ?', array(3)),
      array('DELETE FROM "blog" WHERE "blog_id" = ?', array(3)),
    ));
    
    $users = new Dormio_Queryset('User');
    $this->assertEqual($users->deleteById(1), array(
      array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t1."blog_tag_id" FROM "blog_tag" AS t1 INNER JOIN "blog" AS t2 ON t1."the_blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag_id" IN (SELECT t1."comment_tag_id" FROM "comment_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" INNER JOIN "blog" AS t3 ON t2."blog_id"=t3."blog_id" WHERE t3."the_blog_user" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t1."comment_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)), 
      array('DELETE FROM "blog" WHERE "the_blog_user" = ?', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag_id" IN (SELECT t1."comment_tag_id" FROM "comment_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."the_comment_user" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "the_comment_user" = ?', array(1)),
      array('UPDATE "profile" SET "user_id"=? WHERE "user_id" = ?', array(null, 1)),
      array('DELETE FROM "user" WHERE "user_id" = ?', array(1)),
    ));
     
  }
  
  function testDelete() {
    $blogs = new Dormio_Queryset('Blog');
    $set = $blogs->filter('title', '=', 'My First Blog');
    //$sql = $set->new_delete();
    //foreach($sql as $parts) echo $parts[0]."\n";
    /**
    $this->assertEqual($sql, array(
      array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t1."blog_tag_id" FROM "blog_tag" AS t1 INNER JOIN "blog" AS t2 ON t1."the_blog_id"=t2."blog_id" WHERE t3."title" = ?)', array(1)),
      array('DELETE FROM "comment_tag" WHERE "comment_tag_id" IN (SELECT t1."comment_tag_id" FROM "comment_tag" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" INNER JOIN "comment" AS t3 ON t2."l_comment_id"=t3."comment_id" WHERE t2."blog_id" = ?)', array(1)),
      array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t1."comment_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?)', array(1)),
      array('DELETE FROM "blog" WHERE "title" = ?', array(1)),
    ));
     */
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