<?php
require_once('DBTest.php');

class Dormio_ObjectTest extends Dormio_DBTest{
	
	function testGetObject() {
		$this->load("data/entities.sql");
		$this->load('data/test_data.sql');
		
		$blog = $this->dormio->getObject('Blog');
		$this->assertDigestedAll();
		
		$blog->load(1);
		$this->assertSQL('SELECT "blog_id" AS "pk", "title", "the_blog_user" AS "the_user" FROM "blog" WHERE "blog_id" = ?', 1);
		
		$blog = $this->dormio->getObject('Blog', 2);
		$this->assertDigestedAll();
		$this->assertEquals('Andy Blog 2', $blog->title);
		// statement is cached
		$this->assertSQL('SELECT "blog_id" AS "pk", "title", "the_blog_user" AS "the_user" FROM "blog" WHERE "blog_id" = ?', 2);
		$this->assertStatementCount(1);
		$this->assertDigestedAll();
	}
	
	function testInsertUpdateDelete() {
		$this->load("data/entities.sql");
		// insert new
		$u1 = $this->dormio->getObject('User');
		$u1->name = 'Andy';
		$u1->save();
		$this->assertEquals($u1->pk, 1);
		$this->assertSQL('INSERT INTO "user" ("name") VALUES (?)', 'Andy');


		// load existing
		$u2 = $this->dormio->getObject('User', 1);
		// check nothing executed yet
		$this->assertDigestedAll();
		// on access hydration
		$this->assertEquals($u2->name, 'Andy');
		// should have hit the db now
		$this->assertSQL('SELECT "user_id" AS "pk", "name" FROM "user" WHERE "user_id" = ?', 1);

		// update and save
		$u2->name = 'Bob';
		$u2->save();
		$this->assertSQL('UPDATE "user" SET "name"=? WHERE "user_id" = ?', 'Bob', '1');

		$this->assertDigestedAll();

		// delete
		$this->assertEquals($u2->delete(),1);
		$this->assertQueryCount(9);
		//$this->dumpAllSQL();
		
	}
	
	function testUpdate() {
		$this->load("data/entities.sql");
		$this->load('data/test_data.sql');
		
		$blog = $this->dormio->getObject('Blog', 3);
		$blog->title = "Hello";
		$blog->save();
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello', 3);
		
		try {
			$blog->pk = 12;
			$this->fail("Should have thrown exception");
		} catch(Dormio_Exception $e) {
			$this->assertEquals("Unable to update primary key", $e->getMessage());
		}
	}

	/**
	 * 
	 */
	function testToString() {
		$this->load("data/entities.sql");
		$this->load('data/test_data.sql');

		$blog = $this->dormio->getObject('Blog');
		$this->assertEquals((string)$blog, '[New Blog]');
		$blog->setPrimaryKey(3);
		$this->assertEquals((string)$blog, '[Blog 3]');
		$this->assertDigestedAll(); // nothing run as lazy loaded
		
		// custom toString on comment
		$comment = $this->dormio->getObject('Comment', 1);
		$this->assertEquals((string)$comment, 'Andy Comment 1 on Andy Blog 1');
		$this->assertSQL('SELECT...', 1);
		
		$this->assertDigestedAll();
	}

	function testNotFound() {
		$this->load("data/entities.sql");
		$blog = $this->dormio->getObject('Blog');
		
		$this->assertThrows( 'Dormio_Exception: [Entity Blog] has no record with primary key 1',
				array($blog, 'load'), 1);
	}

	function testDelete() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		//Dormio::$logger = new Test_Logger;
		
		$blog = $this->dormio->getObject('Blog', 1);

		$this->assertEquals($blog->delete(), 9); // blog 1, comment 1, comment_tag 2+5, comment 2, comment_tag 1+4, blog_tag 1+2 => 9

		$blog->load(2);
		$this->assertEquals($blog->delete(), 2); // blog 2, blog_tag 3 => 2
		$this->clearSQL();
		
		$user = $this->dormio->getObject('User');
		$user->load(1);
		$this->assertEquals($user->delete(), 4); // user 1, comment 3, comment_tag 3, profile 2 blanked => 3
		//$this->dumpAllSQL();
		// profile 2 should have had its user blanked by the previous delete
		$profile = $this->dormio->getObject('Profile', 2);
		
		$this->assertNull($profile->user->ident());
	}

	function testForeignKey() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		$b1 = $this->dormio->getObject('Blog', '1');

		//Dormio::$logger = new Test_Logger();
		
		// Lazy
		$this->assertEquals($b1->the_user->name, 'Andy');
		$this->assertSQL('SELECT "blog_id" AS "pk", "title", "the_blog_user" AS "the_user" FROM "blog" WHERE "blog_id" = ?', 1);
		$this->assertSQL('SELECT "user_id" AS "pk", "name" FROM "user" WHERE "user_id" = ?', 1);
		$this->assertDigestedAll();

		// Eager
		$blogs = $this->dormio->getManager('Blog');
		$b1 = $blogs->with('the_user')->findOne(1);
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user", t2."user_id" AS "the_user__pk", t2."name" AS "the_user__name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."blog_id" = ? LIMIT 2', 1);
		// all done in a single hit here
		$this->assertEquals($b1->the_user->name, 'Andy');

		$this->assertDigestedAll();

		// Reverse
		$u1 = $this->dormio->getObject('User', 1, true);
		$iter = $u1->blog_set;
		// nothing should have been hit yet
		$this->assertDigestedAll();

		$expected = array('Andy Blog 1', 'Andy Blog 2');
		$this->assertQueryset($iter, 'title', $expected);
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', 1);

		$this->assertDigestedAll();
	}


	function testRepeatRelations() {
		// this test is needed as we use a reference to the parent id
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		$users = $this->dormio->getManager("User");
		$expected_users = array('Andy', 'Bob', 'Charles');
		$expected_blogs = array(
			array('Andy Blog 1', 'Andy Blog 2'),
			array('Bob Blog 1'),
			array(),
		);
		$i_users = 0;
		foreach($users as $user) {
			$i_blogs = 0;
			$this->assertEquals($user->name, $expected_users[$i_users]);
			foreach($user->blog_set as $blog) {
				$this->assertEquals($blog->title, $expected_blogs[$i_users][$i_blogs++]);
			}
			$this->assertEquals($i_blogs, count($expected_blogs[$i_users]));
			$i_users++;
		}
		$this->assertEquals($i_users, 3);

		// the initial queryset
		$this->assertSQL('SELECT t1."user_id" AS "pk", t1."name" AS "name" FROM "user" AS t1');
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', 1);
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', 2);
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ?', 3);
		$this->assertStatementCount(2);
		
		$this->assertDigestedAll();
	}

	function testOneToOne() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		//Dormio::$logger = new Test_Logger;
		
		// Forward Lazy
		$p = $this->dormio->getObject('Profile', 2);
		$this->assertEquals($p->age, 46);
		$this->assertSQL('SELECT "profile_id" AS "pk", "user_id" AS "user", "age", "fav_cheese" FROM "profile" WHERE "profile_id" = ?', 2);
		$this->assertEquals($p->user->name, 'Andy');
		$this->assertSQL('SELECT "user_id" AS "pk", "name" FROM "user" WHERE "user_id" = ?', 1);
		$this->assertDigestedAll();

		
		// Forward Eager
		$p = $this->dormio->getManager('Profile')->with('user')->findOne(2);
		$this->assertEquals($p->user->name, 'Andy');
		$this->assertSQL('SELECT t1."profile_id" AS "pk", t1."user_id" AS "user", t1."age" AS "age", t1."fav_cheese" AS "fav_cheese", t2."user_id" AS "user__pk", t2."name" AS "user__name" FROM "profile" AS t1 LEFT JOIN "user" AS t2 ON t1."user_id"=t2."user_id" WHERE t1."profile_id" = ? LIMIT 2', 2);
		$this->assertDigestedAll();
		
		// Reverse Lazy
		$u1 = $this->dormio->getObject('User', 2);
		$this->assertEquals($u1->profile->age, 23);
		$this->assertEquals($u1->profile->fav_cheese, 'Edam'); // cached query
		// doesn't even need to load the user for this
		$this->assertSQL('SELECT t1."profile_id" AS "pk", t1."user_id" AS "user", t1."age" AS "age", t1."fav_cheese" AS "fav_cheese" FROM "profile" AS t1 WHERE t1."user_id" = ? LIMIT 2', 2);
		$this->assertDigestedAll();
		
		// Reuse
		$users = $this->dormio->getManager('User');
		$ages = array(46, 23, null);
		$cheeses = array('Stilton', 'Edam', null);
		$i=0;
		foreach($users as $user) {
			if($user->profile->ident()) {
				$this->assertEquals($ages[$i], $user->profile->age);
				$this->assertEquals($cheeses[$i++], $user->profile->fav_cheese);
			}
		}
		$this->assertEquals(2, $i);
		$this->assertQueryCount(4);
		
		// Eager reverse
		$i = 0;
		foreach($users->with('profile') as $user) {
			if($user->profile->ident()) {
				$this->assertEquals($ages[$i], $user->profile->age);
				$this->assertEquals($cheeses[$i++], $user->profile->fav_cheese);
			}
		}
		$this->assertEquals(2, $i);
		$this->assertSQL('SELECT t1."user_id" AS "pk", t1."name" AS "name", t2."profile_id" AS "profile__pk", t2."user_id" AS "profile__user", t2."age" AS "profile__age", t2."fav_cheese" AS "profile__fav_cheese" FROM "user" AS t1 LEFT JOIN "profile" AS t2 ON t1."user_id"=t2."user_id"');
		
		//$this->markTestIncomplete("Rogue select on null onetoone");
		//$this->assertDigestedAll();
		$this->dumpAllSQL();
		
		Dormio::$logger = null;
	}

	function testManyToMany() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		// Forward
		$b1 = $this->dormio->getObject('Blog', 1, true); // lazy load

		$iter = $b1->tags;
		// db not hit yet
		$this->assertDigestedAll();

		$expected = array('Yellow', 'Indigo');
		$this->assertQueryset($iter, 'tag', $expected);
		$this->assertSQL('SELECT t1."tag_id" AS "pk", t1."tag" AS "tag" FROM "tag" AS t1 INNER JOIN "blog_tag" AS t2 ON t1."tag_id"=t2."the_tag_id" WHERE t2."the_blog_id" = ?', 1);

		// do a test on one with normal fields
		$c1 = $this->dormio->getObject('Comment', 2, true); // lazy load

		$expected = array('Orange', 'Violet');
		//var_dump($c1->tags);
		$this->assertQueryset($c1->tags, 'tag', $expected);
		$this->assertSQL('SELECT t1."tag_id" AS "pk", t1."tag" AS "tag" FROM "tag" AS t1 INNER JOIN "comment_x_tag" AS t2 ON t1."tag_id"=t2."r_tag_id" WHERE t2."l_comment_id" = ?', 2);

		// manytomany on self
		/*
		 $m1 = new Module($this->pdo);
		$m1->load(3);
		foreach($m1->depends_on as $m) {
		echo "{$m->name}\n";
		}
		$this->assertSQL();
		*/
		$this->assertDigestedAll();
	}
	

	function testManyToManyReverse() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");

		$tag = $this->dormio->getObject('Tag', 4);
		$this->assertDigestedAll();
		
		// overriden fields
		$expected = array('Andy Blog 2');
		$this->assertQueryset($tag->blog_set, 'title', $expected);
		$this->assertSQL('SELECT t1."blog_id" AS "pk", t1."title" AS "title", t1."the_blog_user" AS "the_user" FROM "blog" AS t1 INNER JOIN "blog_tag" AS t2 ON t1."blog_id"=t2."the_blog_id" WHERE t2."the_tag_id" = ?', 4);

		// default fields
		$expected = array('Andy Comment 1 on Andy Blog 1', 'Andy Comment 1 on Bob Blog 1');
		$this->assertQueryset($tag->comment_set, 'title', $expected);
		$this->assertSQL('SELECT t1."comment_id" AS "pk", t1."title" AS "title", t1."the_comment_user" AS "user", t1."blog_id" AS "blog" FROM "comment" AS t1 INNER JOIN "comment_x_tag" AS t2 ON t1."comment_id"=t2."l_comment_id" WHERE t2."r_tag_id" = ?', 4);

		$this->assertDigestedAll();
	}
	
	function testGetValue() {
		$this->config->addEntities(include 'data/basic_types.php');
		$this->load("data/basic_types.sql");
		
		$obj = $this->dormio->getObject('BasicTypes');
		$obj->setData(array('pk' => 1, 'string' => 'the string', 'integer' => '27', 'float' => '3.1', 'double' => '2.3446', 'boolean' => '1', 'timestamp' => '0', 'datetime' => '2012-06-26T12:36:09+10:00'));
		
		$this->assertSame('the string', $obj->string);
		$this->assertSame(27, $obj->integer);
		$this->assertSame(true, $obj->boolean);
		$this->assertEquals('2012_06_26 12,36,09 _GMT+1000_', $obj->datetime->format('Y_m_d H,i,s _T_'));
	}
	
	function testFieldsToColumns() {
		$this->config->addEntities(include 'data/basic_types.php');
		$this->load("data/basic_types.sql");
		
		$obj = $this->dormio->getObject('BasicTypes');
		$obj->string = 'Hello, world';
		$obj->integer = 3;
		$obj->float = pi();
		$obj->double = pi()*2;
		$obj->boolean = false;
		$obj->password = 'secret';
		$obj->ipv4address = '192.168.54.6';
		$obj->timestamp = 3600;
		$obj->date = new DateTime();
		$obj->datetime = new DateTime();
		$now = date('c');
		
		$updated = $this->dormio->fieldsToColumns($obj->_updated, $obj->_entity);
		
		// only datetimes are modified
		$this->assertEquals($now, $updated['datetime']);
	}
	
	function testObjects() {
		$obj = $this->dormio->getObject('Blog');
		
		$manager = $obj->getObjects();
		$this->assertEquals('Blog', $manager->entity->name);
	}
	
	function testAccessors() {
		$obj = $this->dormio->getObject('Blog');
		
		$this->assertInstanceOf('Dormio', $obj->getDormio());
		$this->assertEquals('Blog', $obj->getEntity()->name);
	}
/*
	function testFromDB() {
		$blog = $this->dormio->getObject('Blog');

		$this->assertIdentical($blog->_fromDB('testing', 'string'), 'testing');

		$this->assertIdentical($blog->_fromDB('1', 'boolean'), true);
		$this->assertIdentical($blog->_fromDB(true, 'boolean'), true);
		$this->assertIdentical($blog->_fromDB('yes', 'boolean'), true);
		$this->assertIdentical($blog->_fromDB('0', 'boolean'), false);
		$this->assertIdentical($blog->_fromDB('', 'boolean'), false);
		$this->assertIdentical($blog->_fromDB(null, 'boolean'), null);

		$this->assertIdentical($blog->_fromDB('1', 'integer'), 1);
		$this->assertIdentical($blog->_fromDB('1.6', 'integer'), 1);
		$this->assertIdentical($blog->_fromDB('', 'integer'), 0);
		$this->assertIdentical($blog->_fromDB(null, 'integer'), null);
	}
*/
}

?>
