<?php
class Dormio_QueryTest extends PHPUnit_Framework_TestCase {
	private $all_blogs = array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1', array());

	/**
	 * @var Dormio_Config
	 */
	public $config;

	function setUp() {
		$this->config = new Dormio_Config;
		$this->config->addEntities($GLOBALS['test_entities']);
	}

	function getQuery($name) {
		return new Dormio_Query($this->config->getEntity($name));
	}
	
	function testConstruct() {
		$qs = $this->getQuery('Blog');
		$this->assertEquals($this->all_blogs, $qs->select());

		try {
			$qs = $this->getQuery('Rubbish');
			$this->fail('Should have thrown exception');
		} catch(Dormio_Config_Exception $e) {
		}
	}
	
	function testResolve() {
		//Dormio_Query::$logger = new Debugger();
		$blogs = $this->getQuery('Blog');

		// DIRECT
		$this->assertEquals($blogs->_resolveField('title'), '<@t1.@>{title}'); // standard field
		$this->assertEquals($blogs->_resolveField('pk'), '<@t1.@>{blog_id}'); // want blog id

		// FOREIGNKEY
		$this->assertEquals($blogs->setAlias()->_resolveField('the_user__name'), '<@t2.@>{name}'); // standard field
		$this->assertEquals($blogs->setAlias()->_resolveField('the_user'), '<@t1.@>{the_blog_user}'); // want user id
		$this->assertEquals($blogs->setAlias()->_resolveField('the_user__pk'), '<@t1.@>{the_blog_user}'); // want user id

		// FOREIGNKEY_REV (with reverse field)
		$this->assertEquals($blogs->setAlias()->_resolveField('comments__title'), '<@t2.@>{title}'); // standard field
		$this->assertEquals($blogs->setAlias()->_resolveField('comments'), '<@t2.@>{comment_id}'); // want comment ids
		$this->assertEquals($blogs->setAlias()->_resolveField('comments__pk'), '<@t2.@>{comment_id}'); // want comment ids

		// MANYTOMANY (forward)
		$this->assertEquals($blogs->setAlias()->_resolveField('tags__tag'), '<@t3.@>{tag}'); // standard field via link table
		$this->assertEquals($blogs->setAlias()->_resolveField('tags'), '<@t2.@>{the_tag_id}'); // want tag ids but only with half join
		$this->assertEquals($blogs->setAlias()->_resolveField('tags__pk'), '<@t2.@>{the_tag_id}'); // want tag ids but only with half join

		// MANYTOMANY (reverse with reverse field and defined intermediate)
		$tags = $this->getQuery('Tag');
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs__title'), '<@t3.@>{title}'); // standard field
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs'), '<@t2.@>{the_blog_id}'); // standard field
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs__pk'), '<@t2.@>{the_blog_id}'); // standard field

		// MANYTOMANY (reverse no reverse field)
		$tags = $this->getQuery('Tag');
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set__title'), '<@t3.@>{title}'); // standard field
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set'), '<@t2.@>{l_comment_id}'); // standard field
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set__pk'), '<@t2.@>{l_comment_id}'); // standard field

		// OTHER RANDOM USAGE TESTS

		$comments = $this->getQuery('Comment');
		$this->assertEquals($comments->setAlias()->_resolveField('pk'), '<@t1.@>{comment_id}');
		$this->assertEquals($comments->setAlias()->_resolveField('blog'), '<@t1.@>{blog_id}'); // foreign pk
		$this->assertEquals($comments->setAlias()->_resolveField('blog__title'), '<@t2.@>{title}'); // foreignkey
		$this->assertEquals($comments->setAlias()->_resolveField('blog__the_user__name'), '<@t3.@>{name}'); // multistage
		$this->assertEquals($comments->setAlias()->_resolveField('tags__tag'), '<@t3.@>{tag}'); // manytomany
		$this->assertEquals($comments->setAlias()->_resolveField('blog__the_user__profile'), '<@t4.@>{profile_id}'); // multistage with pk

		$users = $this->getQuery('User');
		$this->assertEquals($users->setAlias()->_resolveField('profile__age'), '<@t2.@>{age}'); // onetoone_rev

		$profile = $this->getQuery('Profile');
		$this->assertEquals($profile->setAlias()->_resolveField('user__name'), '<@t2.@>{name}'); // onetoone

		$this->assertEquals($tags->_resolveString("SELECT * FROM {table}"), "SELECT * FROM {tag}");

	}

	function testJoin() {
		//Dormio_Query::$logger = new Debugger;
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->with('the_user')->query['join'], //foreignkey
				array('LEFT JOIN {user} AS t2 ON t1.{the_blog_user}=t2.{user_id}'));
		$this->assertEquals($blogs->filter('comments__title', '=', 'Test')->query['join'], // foreignkey_rev
				array('INNER JOIN {comment} AS t2 ON t1.{blog_id}=t2.{blog_id}'));
		$this->assertEquals($blogs->filter('tags__tag', '=', 'Red')->query['join'], //manytomany
				array("LEFT JOIN {blog_tag} AS t2 ON t1.{blog_id}=t2.{the_blog_id}", "INNER JOIN {tag} AS t3 ON t2.{the_tag_id}=t3.{tag_id}"));

		$comments = $this->getQuery('Comment');
		$this->assertEquals($comments->with('user')->query['join'], // foreignkey
				array('LEFT JOIN {user} AS t2 ON t1.{the_comment_user}=t2.{user_id}'));
		$this->assertEquals($comments->with('blog')->query['join'], // foreignkey
				array('LEFT JOIN {blog} AS t2 ON t1.{blog_id}=t2.{blog_id}'));
		$this->assertEquals($comments->filter('tags__tag', '=', 'Red')->query['join'], // manytomany
				array('LEFT JOIN {comment_x_tag} AS t2 ON t1.{comment_id}=t2.{l_comment_id}', 'INNER JOIN {tag} AS t3 ON t2.{r_tag_id}=t3.{tag_id}'));

		$profiles = $this->getQuery('Profile');
		$this->assertEquals($profiles->with('user')->query['join'], // onetoone
				array('LEFT JOIN {user} AS t2 ON t1.{user_id}=t2.{user_id}'));

		$users = $this->getQuery('User');
		$this->assertEquals($users->with('profile')->query['join'], // foreignkey
				array('LEFT JOIN {profile} AS t2 ON t1.{user_id}=t2.{user_id}'));

		$tags = $this->getQuery('Tag');
		$this->assertEquals($tags->filter('blog_set__title', '=', 'Test')->query['join'], // manytomany_rev
				array('LEFT JOIN {blog_tag} AS t2 ON t1.{tag_id}=t2.{the_tag_id}', 'INNER JOIN {blog} AS t3 ON t2.{the_blog_id}=t3.{blog_id}'));
		$this->assertEquals($tags->filter('comment_set__title', '=', 'Test')->query['join'], // manytomany_rev
				array('LEFT JOIN {comment_x_tag} AS t2 ON t1.{tag_id}=t2.{r_tag_id}', 'INNER JOIN {comment} AS t3 ON t2.{l_comment_id}=t3.{comment_id}'));

		$nodes = $this->getQuery('Tree');
		$this->assertEquals($nodes->filter('parent__name', '=', 'Bob')->query['join'],
				array('INNER JOIN {tree} AS t2 ON t1.{parent_id}=t2.{tree_id}'));

		$this->assertEquals($nodes->filter('tree_set__name', '=', 'Andy')->query['join'],
				array('INNER JOIN {tree} AS t2 ON t1.{tree_id}=t2.{parent_id}'));

		$modules = $this->getQuery('MultiDep');
		$this->assertEquals($modules->filter('depends_on__name', '=', 'core')->query['join'], // manytomany self
				array('LEFT JOIN {multidep_x_multidep} AS t2 ON t1.{multidep_id}=t2.{l_multidep_id}', 'INNER JOIN {multidep} AS t3 ON t2.{r_multidep_id}=t3.{multidep_id}'));
		$this->assertEquals($modules->filter('required_by__name', '=', 'core')->query['join'], // manytomany self
				array('LEFT JOIN {multidep_x_multidep} AS t2 ON t1.{multidep_id}=t2.{r_multidep_id}', 'INNER JOIN {multidep} AS t3 ON t2.{l_multidep_id}=t3.{multidep_id}'));
	}

	function testFields() {
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->fields('comments__title')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user", t2."title" AS "comments__title" FROM "blog" AS t1 LEFT JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id"', array()));
	}
	
	function testField() {
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->selectField('COUNT({comments}) AS comment_count')->select(),
			array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user", COUNT(t2."comment_id") AS comment_count FROM "blog" AS t1 INNER JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id"', array()));
	}
	
	function testFunc() {
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->func('COUNT', 'comments')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user", COUNT(t2."comment_id") AS "comments_count" FROM "blog" AS t1 LEFT JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id"', array()));
	}

	function testFilter() {
		$blogs = $this->getQuery('Blog');
		$comments = $this->getQuery('Comment');

		// normal field
		$this->assertEquals($blogs->filter('title', '=', 'hello')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."title" = ?', array('hello')));

		// primary key
		$this->assertEquals($blogs->filter('pk', '=', 1)->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."blog_id" = ?', array(1)));

		// foreign key with id
		$this->assertEquals($blogs->filter('the_user', '=', 2)->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(2)));

		// foreign key with obj
		// TODO: restore test
		/*
		 $user = new User(new PDO('sqlite::memory:'));
		$user->load(3);
		$this->assertEquals($blogs->filter('the_user', '=', $user)->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(3)));
		*/
		// non existent field
		try { $blogs->filter('rubbish', '=', 1); $this->fail('Should have thrown exception');
		} catch(Dormio_Query_Exception $e) {
		}

		// direct object field access
		try { $blogs->filter('user_id', '=', 1); $this->fail('Should have thrown exception');
		} catch(Dormio_Query_Exception $e) {
		}

		// direct pk access
		try { $blogs->filter('blog_id', '=', 1); $this->fail('Should have thrown exception');
		} catch(Dormio_Query_Exception $e) {
		}

		// one step relation
		$this->assertEquals($comments->filter('blog__title', '=', 'hello')->select(),
				array('SELECT t1."comment_id" AS "pk", t1."title" AS "title", t1."the_comment_user" AS "user", t1."blog_id" AS "blog" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('hello')));

		// two step relation
		$this->assertEquals($comments->filter('blog__the_user__name', '=', 'tris')->select(),
				array('SELECT t1."comment_id" AS "pk", t1."title" AS "title", t1."the_comment_user" AS "user", t1."blog_id" AS "blog" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" INNER JOIN "user" AS t3 ON t2."the_blog_user"=t3."user_id" WHERE t3."name" = ?', array('tris')));

		// IN operator
		$this->assertEquals($blogs->filter('the_user__name', 'IN', array('Andy', 'Dave'))->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t2."name" IN (?, ?)', array('Andy', 'Dave')));
	}
	
	function testFilterSpecial() {
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->filterSpecial('title', 'IS NOT NULL')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."title" IS NOT NULL', array()));
	}
	
	function testDistinct() {
		$blogs = $this->getQuery('Blog');
		$this->assertEquals($blogs->distinct()->select(),
				array('SELECT DISTINCT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1', array()));
	}

	function testWhere() {
		$blogs = $this->getQuery('Blog');

		$this->assertEquals($blogs->where('{the_user} = ?', array(1))->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(1)));
	}

	function testLimit() {
		$users = $this->getQuery('User');

		$this->assertEquals($users->limit(3)->select(),
				array('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1 LIMIT 3', array()));

		$this->assertEquals($users->limit(4,2)->select(),
				array('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1 LIMIT 4 OFFSET 2', array()));
	}

	function testOrder() {
		$users = $this->getQuery('User');
		$blogs = $this->getQuery('Blog');

		// single
		$this->assertEquals($users->orderBy('name')->select(),
				array('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1 ORDER BY t1."name"', array()));

		// multiple
		$this->assertEquals($users->orderBy('name', 'pk')->select(),
				array('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id"', array()));

		// descending
		$this->assertEquals($users->orderBy('name', '-pk')->select(),
				array('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id" DESC', array()));

		// related
		$this->assertEquals($blogs->orderBy('the_user__name')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" ORDER BY t2."name"', array()));
	}

	function testWith() {
		$blogs = $this->getQuery('Blog');

		// single
		$this->assertEquals($blogs->with('the_user')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user", t2."user_id" AS "the_user__pk", t2."name" AS "the_user__name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id"', array()));

		// TODO: need to add more tests here but seems to work
	}

	function testManyToMany() {
		$blogs = $this->getQuery('Blog');

		//var_dump($blogs->filter('tags__tag', '=', 'testing')->select());
		$this->assertEquals($blogs->filter('tags__tag', '=', 'testing')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 LEFT JOIN "blog_tag" AS t2 ON t1."blog_id"=t2."the_blog_id" INNER JOIN "tag" AS t3 ON t2."the_tag_id"=t3."tag_id" WHERE t3."tag" = ?', array('testing')));
	}

	function testReverse() {
		$blogs = $this->getQuery('Blog');
		$tags = $this->getQuery('Tag');

		// reverse foreign key
		$this->assertEquals($blogs->filter('comments__title', '=', 'Test')->select(),
				array('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 INNER JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('Test')));

		// reverse manytomany
		//var_dump($tags->with('blog_set')->select());
		$this->assertEquals($tags->filter('blog_set__title', '=', 'Test')->select(),
				array('SELECT t1."tag_id" AS "pk", t1."tag" AS "tag" FROM "tag" AS t1 LEFT JOIN "blog_tag" AS t2 ON t1."tag_id"=t2."the_tag_id" INNER JOIN "blog" AS t3 ON t2."the_blog_id"=t3."blog_id" WHERE t3."title" = ?', array('Test')));
	}

	function testAliases() {
		$comments = $this->getQuery('Comment');

		$set = $comments->with('blog')->filter('tags__tag', '=', 'Yo');
		$this->assertEquals($set->aliases, array(
			"Comment" => "t1",
			"Comment.blog__Blog.pk" => "t2",
			"Comment.pk__Comment_X_Tag.lhs" => "t3",
			"Comment_X_Tag.rhs__Tag.pk" => "t4",
		));

	}

	function testUpdate() {
		$blogs = $this->getQuery('Blog');
		$set = $blogs->filter('title', '=', 'Blog 1');

		$this->assertEquals($set->update(array('the_user' => 1)),
				array('UPDATE "blog" SET "the_blog_user"=? WHERE "title" = ?', array(1, 'Blog 1')));

		$this->assertEquals($set->limit(2)->update(array('the_user' => 2)),
				array('UPDATE "blog" SET "the_blog_user"=? WHERE "title" = ? LIMIT 2', array(2, 'Blog 1')));

		// joined criteria
		$set = $set->filter('comments__user', '=', 1);
		//var_dump($set->updateSQL(array('title' => 'New Title')));
	}

	function testInsert() {
		$blogs = $this->getQuery('Blog');

		$this->assertEquals($blogs->insert(array('the_user'=>1, 'title'=>'A blog')),
				array('INSERT INTO "blog" ("the_blog_user", "title") VALUES (?, ?)', array(1, 'A blog')));
	}


	function testDeleteById() {
		//Dormio_Query::$logger = new Query_Debugger;
		$blogs = $this->getQuery('Blog');
		$sql = $blogs->deleteById(3);
		$this->assertEquals($sql, array(
			array('DELETE FROM "blog_tag" WHERE "the_blog_id" = ?', array(3)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."blog_id" = ?)', array(3)),
			array('DELETE FROM "comment" WHERE "blog_id" = ?', array(3)),
			array('DELETE FROM "blog" WHERE "blog_id" = ?', array(3)),
		));

		$users = $this->getQuery('User');
		$this->assertEquals($users->deleteById(1), array(
			array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t1."blog_tag_id" FROM "blog_tag" AS t1 INNER JOIN "blog" AS t2 ON t1."the_blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" INNER JOIN "blog" AS t3 ON t2."blog_id"=t3."blog_id" WHERE t3."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t1."comment_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "blog" WHERE "the_blog_user" = ?', array(1)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."the_comment_user" = ?)', array(1)),
			array('DELETE FROM "comment" WHERE "the_comment_user" = ?', array(1)),
			array('UPDATE "profile" SET "user_id"=? WHERE "user_id" = ?', array(null, 1)),
			array('DELETE FROM "tag_x_user" WHERE "l_user_id" = ?', array(1)),
			array('DELETE FROM "user" WHERE "user_id" = ?', array(1)),
		));
		
		$tags = $this->getQuery('Tag');
		$this->assertEquals($tags->deleteById(1), array(
			array('DELETE FROM "blog_tag" WHERE "the_tag_id" = ?', array(1)),
			array('DELETE FROM "comment_x_tag" WHERE "r_tag_id" = ?', array(1)),
			array('DELETE FROM "tag_x_user" WHERE "r_tag_id" = ?', array(1)),
			array('DELETE FROM "tag" WHERE "tag_id" = ?', array(1)),
		));

	}

	function testDelete() {
		//Dormio_Query::$logger = new Query_Debugger;
		$blogs = $this->getQuery('Blog');

		// simple(ish) delete
		$set = $blogs->filter('title', '=', 'My First Blog');
		$sql = $set->delete();
		//foreach($sql as $parts) echo $parts[0]."\n";
		$this->assertEquals($sql, array(
			array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t2."blog_tag_id" FROM "blog_tag" AS t2 INNER JOIN "blog" AS t1 ON t2."the_blog_id"=t1."blog_id" WHERE t1."title" = ?)', array('My First Blog')),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t4."comment_x_tag_id" FROM "comment_x_tag" AS t4 INNER JOIN "comment" AS t5 ON t4."l_comment_id"=t5."comment_id" INNER JOIN "blog" AS t1 ON t5."blog_id"=t1."blog_id" WHERE t1."title" = ?)', array('My First Blog')),
			array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t2."comment_id" FROM "comment" AS t2 INNER JOIN "blog" AS t1 ON t2."blog_id"=t1."blog_id" WHERE t1."title" = ?)', array('My First Blog')),
			array('DELETE FROM "blog" WHERE "title" = ?', array('My First Blog')),
		));

		// delete a complex cross-table set
		$set = $blogs->filter('title', '=', 'My First Blog')->filter('the_user__name', '=', 'Bob');
		$sql = $set->delete();
		//foreach($sql as $parts) echo $parts[0]."\n";
		$this->assertEquals($sql, array(
			array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t3."blog_tag_id" FROM "blog_tag" AS t3 INNER JOIN "blog" AS t1 ON t3."the_blog_id"=t1."blog_id" INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."title" = ? AND t2."name" = ?)', array('My First Blog', 'Bob')),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t5."comment_x_tag_id" FROM "comment_x_tag" AS t5 INNER JOIN "comment" AS t6 ON t5."l_comment_id"=t6."comment_id" INNER JOIN "blog" AS t1 ON t6."blog_id"=t1."blog_id" INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."title" = ? AND t2."name" = ?)', array('My First Blog', 'Bob')),
			array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t3."comment_id" FROM "comment" AS t3 INNER JOIN "blog" AS t1 ON t3."blog_id"=t1."blog_id" INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."title" = ? AND t2."name" = ?)', array('My First Blog', 'Bob')),
			array('DELETE FROM "blog" WHERE "blog_id" IN (SELECT t1."blog_id" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."title" = ? AND t2."name" = ?)', array('My First Blog', 'Bob')),
		));
		
		$tags = $this->getQuery('Tag');
		$set = $tags->filter('tag', '=', 'Orange');
		$this->assertEquals($set->delete(), array(
			array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t2."blog_tag_id" FROM "blog_tag" AS t2 INNER JOIN "tag" AS t1 ON t2."the_tag_id"=t1."tag_id" WHERE t1."tag" = ?)', array('Orange')),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t2."comment_x_tag_id" FROM "comment_x_tag" AS t2 INNER JOIN "tag" AS t1 ON t2."r_tag_id"=t1."tag_id" WHERE t1."tag" = ?)', array('Orange')),
			array('DELETE FROM "tag_x_user" WHERE "tag_x_user_id" IN (SELECT t2."tag_x_user_id" FROM "tag_x_user" AS t2 INNER JOIN "tag" AS t1 ON t2."r_tag_id"=t1."tag_id" WHERE t1."tag" = ?)', array('Orange')),
			array('DELETE FROM "tag" WHERE "tag" = ?', array('Orange')),
		));
	}

	function testNonMutation() {
		$qs = $this->getQuery('Blog');
		$qs->filter('title', '=', 'hello');
		$this->assertEquals($qs->select(), $this->all_blogs);
		$qs->with('the_user');
		$this->assertEquals($qs->select(), $this->all_blogs);
		$qs->limit(1,2);
		$this->assertEquals($qs->select(), $this->all_blogs);
		$qs->orderBy('the_user');
		$this->assertEquals($qs->select(), $this->all_blogs);
	}
	
	function testToString() {
		$qs = $this->getQuery('Blog');
		$this->assertEquals($this->all_blogs[0] . '; ()', (string)$qs);
	}
	
	function testTypes() {
		$qs =$this->getQuery('Blog');
		
		// default fields
		$this->assertEquals(array('pk' => 'ident', 'title' => 'string', 'the_user' => 'foreignkey'), $qs->types);
		
		$this->assertEquals(array('pk' => 'ident', 'title' => 'string', 'the_user' => 'foreignkey', 'the_user__pk' => 'ident', 'the_user__name' => 'text'), $qs->with('the_user')->types);
		
		// selectIdent is destructive
		$qs->selectIdent();
		$this->assertEquals(array('blog_id' => 'ident'), $qs->types);
		
		$o = $qs->fields('the_user__profile__age');
		$this->assertEquals(array('blog_id' => 'ident', 'the_user__profile__age' => 'integer'), $o->types);
	}

}

class Query_Debugger {
	function log($message) {
		fputs(STDERR, "DEBUG: {$message}\n");
	}
}