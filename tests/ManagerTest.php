<?php
require_once 'DBTest.php';

class Dormio_ManagerTest extends Dormio_DBTest{
	
	function testFindOneArray() {
		$this->load("sql/test_schema.sql");
		$this->load('sql/test_data.sql');
	
		$blogs = $this->dormio->getManager('Blog');
	
		$this->assertThrows('Dormio_Manager_MultipleResultsException:', array($blogs, 'findOne'));
		$this->pdo->digest();
	
		// basic pk load
		$blog = $blogs->findOneArray(2);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ? LIMIT 2', 2);
	
		// bad pk load
		$this->assertThrows('Dormio_Manager_NoResultException:', array($blogs, 'findOneArray'), 23);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ? LIMIT 2', 23);
	
		// eager load
		$blog = $blogs->with('the_user')->findOneArray(1);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."user_id" AS "t2_user_id", t2."name" AS "t2_name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."blog_id" = ? LIMIT 2', 1);
		$this->assertEquals('Andy', $blog['the_user']['name']);
	
		// complex query and pk
		$blog = $blogs->filter('the_user__name', '=', 'Andy')->findOneArray(2);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t2."name" = ? AND t1."blog_id" = ? LIMIT 2', 'Andy', 2);
		$this->assertEquals('Andy Blog 2', $blog['title']);
	
		// other query
		$blog = $blogs->filter('title', '=', 'Andy Blog 2')->findOneArray();
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" = ? LIMIT 2', 'Andy Blog 2');
		$this->assertEquals($blog['pk'], 2);
	
		// non specific query
		$q = $blogs->filter('the_user', '=', 1);
		$this->assertThrows('Dormio_Manager_MultipleResultsException:', array($q, 'findOneArray'));
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ? LIMIT 2', 1);
	
		$this->assertDigestedAll();
	}
	
	function testFindOne() {
		$this->load("sql/test_schema.sql");
		$this->load('sql/test_data.sql');

		$blogs = $this->dormio->getManager('Blog');

		$this->assertThrows('Dormio_Manager_MultipleResultsException:', array($blogs, 'findOne'));
		$this->pdo->digest();

		// bad pk load
		$this->assertThrows('Dormio_Manager_NoResultException:', array($blogs, 'findOne'), 23);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ? LIMIT 2', 23);
		
		// basic pk load
		$blog = $blogs->findOne(2);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."blog_id" = ? LIMIT 2', 2);
		$this->assertEquals('Andy Blog 2', $blog->title);

		// lazy load
		$this->assertEquals('Andy', $blog->the_user->name);
		$this->assertSQL('SELECT "user_id" AS "pk", "name" FROM "user" WHERE "user_id" = ?', 1);

		// eager load
		$blog = $blogs->with('the_user')->findOne(1);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user", t2."user_id" AS "t2_user_id", t2."name" AS "t2_name" FROM "blog" AS t1 LEFT JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t1."blog_id" = ? LIMIT 2', 1);
		$this->assertEquals('Andy', $blog->the_user->name);

		// complex query and pk
		$blog = $blogs->filter('the_user__name', '=', 'Andy')->findOne(2);
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 INNER JOIN "user" AS t2 ON t1."the_blog_user"=t2."user_id" WHERE t2."name" = ? AND t1."blog_id" = ? LIMIT 2', 'Andy', 2);
		$this->assertEquals('Andy Blog 2', $blog->title);

		// other query
		$blog = $blogs->filter('title', '=', 'Andy Blog 2')->findOne();
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" = ? LIMIT 2', 'Andy Blog 2');
		$this->assertEquals($blog->pk, 2);

		// non specific query
		$q = $blogs->filter('the_user', '=', 1);
		$this->assertThrows('Dormio_Manager_MultipleResultsException:', array($q, 'findOne'));
		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."the_blog_user" = ? LIMIT 2', 1);

		$this->assertDigestedAll();
	}
	
	function testRelated() {
		$this->load("sql/test_schema.sql");
		$this->load('sql/test_data.sql');
		
		$blog = $this->dormio->getObject('Blog', 1);
		
		$this->assertQueryset($blog->comments, 'title', array('Andy Comment 1 on Andy Blog 1', 'Bob Comment 1 on Andy Blog 1'));
		
		// forward many to many
		$this->assertQueryset($blog->tags, 'tag', array('Yellow', 'Indigo'));
		
		// reverse many to many
		$tag = $this->dormio->getManager('Tag')->filter('tag', '=', 'Green')->findOne();
		$this->assertQueryset($tag->blog_set, 'title', array('Andy Blog 2'));
	}
/*
	function testCreate() {
		$this->load("sql/test_schema.sql");
		$blogs = new Dormio_Manager('Blog', $this->pdo);

		$b1 = $blogs->create();
		$this->assertIsA($b1, 'Dormio_Model');

		$b2 = $blogs->create(array('title' => 'Test Blog 1'));
		$this->assertEquals($b2->title, 'Test Blog 1');
		$b2->save();

		try {
			$b3 = $blogs->create(array('rubbish' => 'duff'));
			$this->fail("Should bhave thrown exception");
		} catch(Dormio_Meta_Exception $dme) {
			$this->assertEquals($dme->getMessage(), "No field 'rubbish' on 'blog'");
		}
	}

	function testGetOrCreate() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = new Dormio_Manager('Blog', $this->pdo);

		// get
		$b1 = $blogs->getOrCreate(1);
		$this->assertEquals($b1->ident(), 1);
		$this->assertEquals($b1->title, 'Andy Blog 1');

		// create
		$b1 = $blogs->getOrCreate(23);
		$this->assertFalse($b1->ident());

		// create with defaults
		$b1 = $blogs->getOrCreate(23, array('title' => 'Extra Blog'));
		$this->assertEquals($b1->ident(), 4);
		$this->assertEquals($b1->title, 'Extra Blog');
	}
*/
	function testAggregationMethods() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$tags = $this->dormio->getManager('Tag');

		$data = $tags->filter('tag', '<', 'H')->getAggregator()->count('pk', true)->max('tag')->run();

		$this->assertEquals($data['pk.count'], 2);
		$this->assertEquals($data['tag.max'], 'Green');
		$this->assertSQL('SELECT COUNT(DISTINCT t1."tag_id") AS "t1_tag_id_count", MAX(t1."tag") AS "t1_tag_max" FROM "tag" AS t1 WHERE t1."tag" < ?', 'H');

		$data = $tags->getAggregator()->count()->avg()->sum()->run();
		$this->assertEquals($data['pk.count'], 7);
		$this->assertEquals($data['pk.sum'], 28);
		$this->assertEquals($data['pk.avg'], 4);
	}

	function testInsert() {
		$this->load("sql/test_schema.sql");
		$blogs = $this->dormio->getManager('Blog');
		$stmt = $blogs->insert(array('title', 'the_user'));
		$this->assertEquals($stmt->_stmt->queryString, 'INSERT INTO "blog" ("title", "the_blog_user") VALUES (?, ?)');
	}

	function testUpdate() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");
		
		$comments = $this->dormio->getManager('Comment');
		$set = $comments->filter('blog', '=', 1)->filter('tags__tag', '=', 'Green');
		$this->assertEquals($set->update(array('title' => 'New Title')), 1);
		$comment = $comments->findOne(1);
		$this->assertEquals($comment->title, 'New Title');
	}

	function testDelete() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');
		$set = $blogs->filter('title', '=', 'Andy Blog 1');
		// 1 blog with 2 tags and 2 comments with 4 comment tags between them
		$this->assertEquals($set->delete(), 9);
		//var_dump($this->pdo->stack);
	}

	function testForeignKeyCreate() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");
		
		$blog = $this->dormio->getObject('Blog', 2);
		$this->assertEquals($blog->title, 'Andy Blog 2');

		$comment = $blog->comments->create(array('title' => 'New Comment'));
		$comment->user = 1;
		$comment->save();
		$this->pdo->digest();
		$this->assertEquals($this->pdo->digest(),
				array('INSERT INTO "comment" ("title", "blog_id") VALUES (?, ?)', array(array('New Comment', 2))));
		$this->assertEquals($this->pdo->digest(),
		  array('UPDATE "comment" SET "the_comment_user"=? WHERE "comment_id" = ?', array(array(1, 4))));
		$this->assertDigestedAll();
	}
/*
	function testForeignKeyAdd() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");
		$blog = new Blog($this->pdo);
		$blog->load(2);
		$this->assertEquals($blog->title, 'Andy Blog 2');

		$comment = new Comment($this->pdo);
		$comment->title = "Another new comment";
		$comment->user = 1;
		$blog->comments->add($comment);
		$this->pdo->digest();
		$this->assertEquals($this->pdo->digest(),
				array('INSERT INTO "comment" ("title", "the_comment_user", "blog_id") VALUES (?, ?, ?)', array(array('Another new comment', 1, 2))));
	}

	function testManyToManyAdd() {
		$this->load("sql/test_schema.sql");
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
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blog = $this->pom->get('Blog', 1);
		$this->assertEquals($blog->tags->clear(), 2);
		$this->assertSQL('DELETE FROM "blog_tag" WHERE "the_blog_id" = ?', 1);

		$this->assertDigestedAll();
	}

	function testRemove() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blog = $this->pom->get('Blog', 1);

		// Yellow(3) is on blog 1
		$this->assertEquals($blog->tags->remove(3), 1);
		$this->assertSQL('DELETE FROM "blog_tag" WHERE "the_tag_id" = ? AND "the_blog_id" = ?', 3, 1);

		// Red(1) is not on blog 1
		$this->assertEquals($blog->tags->remove(1), 0);
		$this->assertSQL('DELETE FROM "blog_tag" WHERE "the_tag_id" = ? AND "the_blog_id" = ?', 1, 1);

		// reverse with a model instead of pk
		$tag = $this->pom->get('Tag', 4); // Green
		$blog = $this->pom->get('Blog', 2);
		$this->assertEquals($tag->blogs->remove($blog), 1);
		$this->assertSQL('DELETE FROM "blog_tag" WHERE "the_blog_id" = ? AND "the_tag_id" = ?', 2, 4);

		$this->assertDigestedAll();
	}

*/
	function testCount() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');

		$this->assertEquals(3, $blogs->count());
		$this->assertSQL('SELECT COUNT(*) AS count FROM "blog" AS t1');
		
		// check single call
		$this->assertEquals(3, $blogs->count());
		$this->assertDigestedAll();
		
		// check cloning
		$this->assertEquals(0, $blogs->filter('title', '=', 'Rubbish')->count());
	}
/*
	function testTypeValues() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');

		$all = $blogs->values();
		$this->assertTrue(is_array($all));
		$this->assertEquals(count($all), 3);
		$this->assertEquals($all[1]['t1_title'], 'Andy Blog 2');
	}
*/
	function testFilterIn() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');
		$comments = $this->dormio->getManager('Comment');

		$blog_set = $blogs->filter('the_user', '=', 1);

		$comment_set = $comments->filter('blog', 'IN', $blog_set);

		$result = $comment_set->find();
		$this->assertEquals(count($result), 2);
		$this->assertEquals($result[0]->title, 'Andy Comment 1 on Andy Blog 1');
		$this->assertEquals($result[1]->title, 'Bob Comment 1 on Andy Blog 1');
	}
/*
	function testHasResults() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');

		$this->assertEquals($blogs->hasResults(), true);
		$this->assertEquals($blogs->filter('title', '=', 'Fictional title')->hasResults(), false);

		$this->pdo->clear();

		// should just execute the query once
		$query = $blogs->filter('title', '=', 'Andy Blog 1');
		$this->assertEquals($query->hasResults(), true);

		$this->assertSQL('SELECT t1."blog_id" AS "t1_blog_id", t1."title" AS "t1_title", t1."the_blog_user" AS "t1_the_blog_user" FROM "blog" AS t1 WHERE t1."title" = ?', 'Andy Blog 1');

		// this shouldn't hit the db again
		foreach($query as $result) $result->title;
		$this->assertDigestedAll();
	}

	function testAsArray() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");
			
		$blogs = $this->dormio->getManager('Blog');
			
		$data = $blogs->with('the_user')->asArray();
		$this->assertEquals($data[1], array(
			'pk' => '2',
			'title' => 'Andy Blog 2',
			'the_user' => array(
				'pk' => 1,
				'name' => 'Andy',
			),
		));
	}

	function testHydrate() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');
			
		$obj = new StdClass;
			
	}
*/
	function testJoinSanity() {
		$this->load("sql/test_schema.sql");
		$this->load("sql/test_data.sql");

		$blogs = $this->dormio->getManager('Blog');
		$comments = $this->dormio->getManager('Comment');
		$users = $this->dormio->getManager('User');

		// want to get all comments with tagged as Green
		$set = $comments->filter('tags__tag', '=', 'Green');
		$this->assertQueryset($set, 'title',
				array('Andy Comment 1 on Andy Blog 1', 'Andy Comment 1 on Bob Blog 1'));

		// want to get all blogs where the comment is tagged as Green
		$set = $blogs->filter('comments__tags__tag', '=', 'Green');
		$this->assertQueryset($set, 'title',
				array('Andy Blog 1', 'Bob Blog 1'));

		// need to get all users and their associated profiles
		// note user 3 doesn't have a profile attached
		$set = $users->with('profile');
		$expected = array("23", "46", null);
		$i=0;
		foreach($set as $user) {
			$this->assertEquals($user->profile->age, $expected[$i++]);
		}
		$this->assertEquals($i, 3);

		// it makes no sense to use with on manytomany fields
		// it should generate a warning
		// Tris: changed behaviour - need to think about this some more
		#$this->expectError();
		$set = $blogs->with('tags');
		$this->assertQueryset($set, 'title',
				array('Andy Blog 1', 'Andy Blog 1', 'Andy Blog 2', 'Bob Blog 1'));

		// doesn't de-dup automatically
		$set = $blogs->where('{tags__tag} IN (?, ?)', array('Yellow', 'Indigo'));
		$this->assertQueryset($set, 'title',
				array('Andy Blog 1', 'Andy Blog 1'));

		// use distinct
		$set = $set->distinct();
		$this->assertQueryset($set, 'title',
				array('Andy Blog 1'));

		// additional field
		/*
		$set = $users->field('profile__age', 'age');
		$this->assertQueryset($set, 'age',
				array(23, 46, null));
		*/
		// with followed by filter
		$set = $comments->with('blog')->filter('blog__title', '=', 'Andy Blog 1');
		$this->assertQueryset($set, 'title',
				array('Andy Comment 1 on Andy Blog 1', 'Bob Comment 1 on Andy Blog 1'));

		// filter followed by with
		$set = $comments->filter('blog__title', '=', 'Andy Blog 1')->with('blog');
		$this->assertQueryset($set, 'title',
				array('Andy Comment 1 on Andy Blog 1', 'Bob Comment 1 on Andy Blog 1'));


	}

}