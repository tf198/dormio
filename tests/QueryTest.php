<?php
class Dormio_QueryTest extends PHPUnit_Framework_TestCase {
	private $all_blogs = array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1', array());

	/**
	 * @var Dormio_Config
	 */
	public $config;

	function setUp() {
		Dormio_Config::reset();
		$this->config = Dormio_Config::instance();
		$this->config->addEntities($GLOBALS['test_entities']);
	}

	function testConstruct() {
		$qs = new Dormio_Query('Blog');
		$this->assertEquals($this->all_blogs, $qs->select());

		try {
			$qs = new Dormio_Query('Rubbish');
			$this->fail('Should have thrown exception');
		} catch(Dormio_Config_Exception $e) {
		}
	}

	function testResolve() {
		//Dormio_Query::$logger = new Debugger();
		$blogs = new Dormio_Query('Blog');

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
		$tags = new Dormio_Query('Tag');
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs__title'), '<@t3.@>{title}'); // standard field
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs'), '<@t2.@>{the_blog_id}'); // standard field
		//$this->assertEquals($tags->setAlias()->_resolveField('blogs__pk'), '<@t2.@>{the_blog_id}'); // standard field

		// MANYTOMANY (reverse no reverse field)
		$tags = new Dormio_Query('Tag');
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set__title'), '<@t3.@>{title}'); // standard field
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set'), '<@t2.@>{l_comment_id}'); // standard field
		$this->assertEquals($tags->setAlias()->_resolveField('comment_set__pk'), '<@t2.@>{l_comment_id}'); // standard field

		// OTHER RANDOM USAGE TESTS

		$comments = new Dormio_Query('Comment');
		$this->assertEquals($comments->setAlias()->_resolveField('pk'), '<@t1.@>{comment_id}');
		$this->assertEquals($comments->setAlias()->_resolveField('blog'), '<@t1.@>{blog_id}'); // foreign pk
		$this->assertEquals($comments->setAlias()->_resolveField('blog__title'), '<@t2.@>{title}'); // foreignkey
		$this->assertEquals($comments->setAlias()->_resolveField('blog__the_user__name'), '<@t3.@>{name}'); // multistage
		$this->assertEquals($comments->setAlias()->_resolveField('tags__tag'), '<@t3.@>{tag}'); // manytomany
		$this->assertEquals($comments->setAlias()->_resolveField('blog__the_user__profile'), '<@t4.@>{profile_id}'); // multistage with pk

		$users = new Dormio_Query('User');
		$this->assertEquals($users->setAlias()->_resolveField('profile__age'), '<@t2.@>{age}'); // onetoone_rev

		$profile = new Dormio_Query('Profile');
		$this->assertEquals($profile->setAlias()->_resolveField('user__name'), '<@t2.@>{name}'); // onetoone

		$this->assertEquals($tags->_resolveString("SELECT * FROM {table}"), "SELECT * FROM {tag}");

	}

	function testJoin() {
		//Dormio_Query::$logger = new Debugger;
		$blogs = new Dormio_Query('Blog');
		$this->assertEquals($blogs->with('the_user')->query['join'], //foreignkey
				array('LEFT JOIN {user} AS t2 ON t1.{the_blog_user}=t2.{user_id}'));
		$this->assertEquals($blogs->filter('comments__title', '=', 'Test')->query['join'], // foreignkey_rev
				array('INNER JOIN {comment} AS t2 ON t1.{blog_id}=t2.{blog_id}'));
		$this->assertEquals($blogs->filter('tags__tag', '=', 'Red')->query['join'], //manytomany
				array("LEFT JOIN {blog_tag} AS t2 ON t1.{blog_id}=t2.{the_blog_id}", "INNER JOIN {tag} AS t3 ON t2.{the_tag_id}=t3.{tag_id}"));

		$comments = new Dormio_Query('Comment');
		$this->assertEquals($comments->with('user')->query['join'], // foreignkey
				array('LEFT JOIN {user} AS t2 ON t1.{the_comment_user}=t2.{user_id}'));
		$this->assertEquals($comments->with('blog')->query['join'], // foreignkey
				array('LEFT JOIN {blog} AS t2 ON t1.{blog_id}=t2.{blog_id}'));
		$this->assertEquals($comments->filter('tags__tag', '=', 'Red')->query['join'], // manytomany
				array('LEFT JOIN {comment_x_tag} AS t2 ON t1.{comment_id}=t2.{l_comment_id}', 'INNER JOIN {tag} AS t3 ON t2.{r_tag_id}=t3.{tag_id}'));

		$profiles = new Dormio_Query('Profile');
		$this->assertEquals($profiles->with('user')->query['join'], // onetoone
				array('LEFT JOIN {user} AS t2 ON t1.{user_id}=t2.{user_id}'));

		$users = new Dormio_Query('User');
		$this->assertEquals($users->with('profile')->query['join'], // foreignkey
				array('LEFT JOIN {profile} AS t2 ON t1.{user_id}=t2.{user_id}'));

		$tags = new Dormio_Query('Tag');
		$this->assertEquals($tags->filter('blog_set__title', '=', 'Test')->query['join'], // manytomany_rev
				array('LEFT JOIN {blog_tag} AS t2 ON t1.{tag_id}=t2.{the_tag_id}', 'INNER JOIN {blog} AS t3 ON t2.{the_blog_id}=t3.{blog_id}'));
		$this->assertEquals($tags->filter('comment_set__title', '=', 'Test')->query['join'], // manytomany_rev
				array('LEFT JOIN {comment_x_tag} AS t2 ON t1.{tag_id}=t2.{r_tag_id}', 'INNER JOIN {comment} AS t3 ON t2.{l_comment_id}=t3.{comment_id}'));

		$nodes = new Dormio_Query('Tree');
		$this->assertEquals($nodes->filter('parent__name', '=', 'Bob')->query['join'],
				array('INNER JOIN {tree} AS t2 ON t1.{parent_id}=t2.{tree_id}'));

		$this->assertEquals($nodes->filter('tree_set__name', '=', 'Andy')->query['join'],
				array('INNER JOIN {tree} AS t2 ON t1.{tree_id}=t2.{parent_id}'));

		$modules = new Dormio_Query('MultiDep');
		$this->assertEquals($modules->filter('depends_on__name', '=', 'core')->query['join'], // manytomany self
				array('LEFT JOIN {multidep_x_multidep} AS t2 ON t1.{multidep_id}=t2.{l_multidep_id}', 'INNER JOIN {multidep} AS t3 ON t2.{r_multidep_id}=t3.{multidep_id}'));
		$this->assertEquals($modules->filter('required_by__name', '=', 'core')->query['join'], // manytomany self
				array('LEFT JOIN {multidep_x_multidep} AS t2 ON t1.{multidep_id}=t2.{r_multidep_id}', 'INNER JOIN {multidep} AS t3 ON t2.{l_multidep_id}=t3.{multidep_id}'));
	}

	function testField() {
		$blogs = new Dormio_Query('Blog');
		$this->assertEquals($blogs->field('comments__title')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."title" AS "t2_title" FROM "blog" AS t1 LEFT JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id"', array()));
	}

	function testFilter() {
		$blogs = new Dormio_Query('Blog');
		$comments = new Dormio_Query('Comment');

		// normal field
		$this->assertEquals($blogs->filter('title', '=', 'hello')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" = ?', array('hello')));

		// primary key
		$this->assertEquals($blogs->filter('pk', '=', 1)->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ?', array(1)));

		// foreign key with id
		$this->assertEquals($blogs->filter('the_user', '=', 2)->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(2)));

		// foreign key with obj
		// TODO: restore test
		/*
		 $user = new User(new PDO('sqlite::memory:'));
		$user->load(3);
		$this->assertEquals($blogs->filter('the_user', '=', $user)->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(3)));
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
				array('SELECT t1."comment_id" AS "t1_comment_id", t1."title" AS "t1_title", t1."the_comment_user" AS "t1_the_comment_user", t1."blog_id" AS "t1_blog_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('hello')));

		// two step relation
		$this->assertEquals($comments->filter('blog__the_user__name', '=', 'tris')->select(),
				array('SELECT t1."comment_id" AS "t1_comment_id", t1."title" AS "t1_title", t1."the_comment_user" AS "t1_the_comment_user", t1."blog_id" AS "t1_blog_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" INNER JOIN "user" AS t3 ON t2."the_blog_user"=t3."user_id" WHERE t3."name" = ?', array('tris')));

		// IN operator
		$this->assertEquals($blogs->filter('the_user__name', 'IN', array('Andy', 'Dave'))->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t2."name" IN (?, ?)', array('Andy', 'Dave')));
	}
	
	function testFilterSpecial() {
		$blogs = new Dormio_Query('Blog');
		$this->assertEquals($blogs->filterSpecial('title', 'IS NOT NULL')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" IS NOT NULL', array()));
	}
	
	function testDistinct() {
		$blogs = new Dormio_Query('Blog');
		$this->assertEquals($blogs->distinct()->select(),
				array('SELECT DISTINCT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1', array()));
	}

	function testWhere() {
		$blogs = new Dormio_Query('Blog');

		$this->assertEquals($blogs->where('{the_user} = ?', array(1))->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', array(1)));
	}

	function testLimit() {
		$users = new Dormio_Query('User');

		$this->assertEquals($users->limit(3)->select(),
				array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 LIMIT 3', array()));

		$this->assertEquals($users->limit(4,2)->select(),
				array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 LIMIT 4 OFFSET 2', array()));
	}

	function testOrder() {
		$users = new Dormio_Query('User');
		$blogs = new Dormio_Query('Blog');

		// single
		$this->assertEquals($users->orderBy('name')->select(),
				array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name"', array()));

		// multiple
		$this->assertEquals($users->orderBy('name', 'pk')->select(),
				array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id"', array()));

		// descending
		$this->assertEquals($users->orderBy('name', '-pk')->select(),
				array('SELECT t1."user_id" AS "t1_user_id", t1."name" AS "t1_name" FROM "user" AS t1 ORDER BY t1."name", t1."user_id" DESC', array()));

		// related
		$this->assertEquals($blogs->orderBy('the_user__name')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" ORDER BY t2."name"', array()));
	}

	function testWith() {
		$blogs = new Dormio_Query('Blog');

		// single
		$this->assertEquals($blogs->with('the_user')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."user_id" AS "t2_user_id", t2."name" AS "t2_name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id"', array()));

		// TODO: need to add more tests here but seems to work
	}

	function testManyToMany() {
		$blogs = new Dormio_Query('Blog');

		//var_dump($blogs->filter('tags__tag', '=', 'testing')->select());
		$this->assertEquals($blogs->filter('tags__tag', '=', 'testing')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 LEFT JOIN "blog_tag" AS t2 ON t1."blog_id"=t2."the_blog_id" INNER JOIN "tag" AS t3 ON t2."the_tag_id"=t3."tag_id" WHERE t3."tag" = ?', array('testing')));
	}

	function testReverse() {
		$blogs = new Dormio_Query('Blog');
		$tags = new Dormio_Query('Tag');

		// reverse foreign key
		$this->assertEquals($blogs->filter('comments__title', '=', 'Test')->select(),
				array('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "comment" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."title" = ?', array('Test')));

		// reverse manytomany
		//var_dump($tags->with('blog_set')->select());
		$this->assertEquals($tags->filter('blog_set__title', '=', 'Test')->select(),
				array('SELECT t1."tag_id" AS "t1_tag_id", t1."tag" AS "t1_tag" FROM "tag" AS t1 LEFT JOIN "blog_tag" AS t2 ON t1."tag_id"=t2."the_tag_id" INNER JOIN "blog" AS t3 ON t2."the_blog_id"=t3."blog_id" WHERE t3."title" = ?', array('Test')));
	}

	function testAliases() {
		$comments = new Dormio_Query('Comment');

		$set = $comments->with('blog')->filter('tags__tag', '=', 'Yo');
		$this->assertEquals($set->aliases, array(
			"Comment" => "t1",
			"Comment.blog__Blog.pk" => "t2",
			"Comment.pk__Comment_X_Tag.lhs" => "t3",
			"Comment_X_Tag.rhs__Tag.pk" => "t4",
		));

	}

	function testUpdate() {
		$blogs = new Dormio_Query('Blog');
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
		$blogs = new Dormio_Query('Blog');

		$this->assertEquals($blogs->insert(array('the_user'=>1, 'title'=>'A blog')),
				array('INSERT INTO "blog" ("the_blog_user", "title") VALUES (?, ?)', array(1, 'A blog')));
	}


	function testDeleteById() {
		//Dormio_Query::$logger = new Query_Debugger;
		$blogs = new Dormio_Query('Blog');
		$sql = $blogs->deleteById(3);
		$this->assertEquals($sql, array(
			array('DELETE FROM "blog_tag" WHERE "the_blog_id" = ?', array(3)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."blog_id" = ?)', array(3)),
			array('DELETE FROM "comment" WHERE "blog_id" = ?', array(3)),
			array('DELETE FROM "blog" WHERE "blog_id" = ?', array(3)),
		));

		$users = new Dormio_Query('User');
		$this->assertEquals($users->deleteById(1), array(
			array('DELETE FROM "blog_tag" WHERE "blog_tag_id" IN (SELECT t1."blog_tag_id" FROM "blog_tag" AS t1 INNER JOIN "blog" AS t2 ON t1."the_blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" INNER JOIN "blog" AS t3 ON t2."blog_id"=t3."blog_id" WHERE t3."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "comment" WHERE "comment_id" IN (SELECT t1."comment_id" FROM "comment" AS t1 INNER JOIN "blog" AS t2 ON t1."blog_id"=t2."blog_id" WHERE t2."the_blog_user" = ?)', array(1)),
			array('DELETE FROM "blog" WHERE "the_blog_user" = ?', array(1)),
			array('DELETE FROM "comment_x_tag" WHERE "comment_x_tag_id" IN (SELECT t1."comment_x_tag_id" FROM "comment_x_tag" AS t1 INNER JOIN "comment" AS t2 ON t1."l_comment_id"=t2."comment_id" WHERE t2."the_comment_user" = ?)', array(1)),
			array('DELETE FROM "comment" WHERE "the_comment_user" = ?', array(1)),
			array('UPDATE "profile" SET "user_id"=? WHERE "user_id" = ?', array(null, 1)),
			array('DELETE FROM "user" WHERE "user_id" = ?', array(1)),
		));

	}

	function testDelete() {
		//Dormio_Query::$logger = new Query_Debugger;
		$blogs = new Dormio_Query('Blog');

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
	}

	function testNonMutation() {
		$qs = new Dormio_Query('Blog');
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
		$qs = new Dormio_Query('Blog');
		$this->assertEquals($this->all_blogs[0] . '; ()', (string)$qs);
	}

}

class Query_Debugger {
	function log($message) {
		fputs(STDERR, "DEBUG: {$message}\n");
	}
}