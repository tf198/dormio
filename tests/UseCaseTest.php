<?php
require_once 'DBTest.php';

class Dormio_UseCaseTest extends Dormio_DBTest {
	function testIterUpdate() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$blogs = $this->dormio->getManager('Blog');
		foreach($blogs as $blog) {
			$blog->title = 'Blog ' . $blog->pk;
			$blog->save();
		}
		$this->assertSQL('SELECT t1."blog_id"...');
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Blog 1', 1);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Blog 2', 2);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Blog 3', 3);
		$this->assertDigestedAll();
	}
	
	function testRelatedUpdate() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$blog = $this->dormio->getObject('Blog', 1);
		foreach($blog->comments as $comment) {
			$comment->title = "Comment {$comment->pk} on blog {$blog->pk}";
			$comment->save();
		}
		
		$this->assertSQL('SELECT ...', 1);
		$this->assertSQL('UPDATE "comment" SET "title"=? WHERE "comment_id" = ?', 'Comment 1 on blog 1', 1);
		$this->assertSQL('UPDATE "comment" SET "title"=? WHERE "comment_id" = ?', 'Comment 2 on blog 1', 2);
		$this->assertDigestedAll();
	}
	
	function testOneToOneUpdateLazy() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$profiles = $this->dormio->getManager('Profile');
		foreach($profiles as $profile) {
			$profile->user->name = "Person aged {$profile->age}";
			$profile->user->save();
		}
		
		$this->assertSQL('SELECT t1."profile_id"...');
		$this->assertSQL('UPDATE "user" SET "name"=? WHERE "user_id" = ?', 'Person aged 23', 1);
		$this->assertSQL('UPDATE "user" SET "name"=? WHERE "user_id" = ?', 'Person aged 46', 2);
		
		
		$this->assertDigestedAll();
	}
	
	function testOneToOneUpdateEager() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$profiles = $this->dormio->getManager('Profile');
		foreach($profiles->with('user') as $profile) {
			$profile->user->name = "Person aged {$profile->age}";
			$profile->user->save();
		}
		
		$this->assertSQL('SELECT t1."profile_id"...');
		$this->assertSQL('UPDATE "user" SET "name"=? WHERE "user_id" = ?', 'Person aged 23', 1);
		$this->assertSQL('UPDATE "user" SET "name"=? WHERE "user_id" = ?', 'Person aged 46', 2);
		
		
		$this->assertDigestedAll();
	}
	
	function testOneToOneRevUpdateLazy() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$users = $this->dormio->getManager('User');
		foreach($users as $user) {
			if($user->profile->ident()) {
				$user->profile->fav_cheese = 'Brie';
				$user->profile->save();
			} else {
				$user->profile->age = 56;
				$user->profile->fav_cheese = 'Cheddar';
				$user->profile->save();
			}
		}
		
		$this->assertSQL('SELECT t1."user_id"...');
		$this->assertSQL('SELECT t1."profile_id"...', 1);
		$this->assertSQL('UPDATE "profile" SET "fav_cheese"=? WHERE "profile_id" = ?', 'Brie', 1);
		$this->assertSQL('SELECT t1."profile_id"...', 2);
		$this->assertSQL('UPDATE "profile" SET "fav_cheese"=? WHERE "profile_id" = ?', 'Brie', 2);
		$this->assertSQL('SELECT t1."profile_id"...', 3);
		$this->assertSQL('INSERT INTO "profile" ("user_id", "age", "fav_cheese") VALUES (?, ?, ?)', 3, 56, 'Cheddar');
		
		$this->assertDigestedAll();
	}
	
	function testOneToOneRevUpdateEager() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		//Dormio::$logger = new Test_Logger();
		
		$users = $this->dormio->getManager('User');
		foreach($users->with('profile') as $user) {
			if($user->profile->ident()) {
				$user->profile->fav_cheese = 'Brie';
				$user->profile->save();
			} else {
				$user->profile->age = 56;
				$user->profile->fav_cheese = 'Cheddar';
				$user->profile->save();
			}
		}
		
		$this->assertSQL('SELECT t1."user_id"...');
		$this->assertSQL('UPDATE "profile" SET "fav_cheese"=? WHERE "profile_id" = ?', 'Brie', 1);
		$this->assertSQL('UPDATE "profile" SET "fav_cheese"=? WHERE "profile_id" = ?', 'Brie', 2);
		$this->assertSQL('INSERT INTO "profile" ("user_id", "age", "fav_cheese") VALUES (?, ?, ?)', 3, 56, 'Cheddar');
		
		$this->assertDigestedAll();
	}
	
	function testForeignKeyUpdateLazy() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$comments = $this->dormio->getManager('Comment');
		foreach($comments as $comment) {
			$comment->blog->title = "Hello {$comment->pk}";
			$comment->blog->save();
		}
		
		$this->assertSQL('SELECT t1."comment_id"...');
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 1', 1);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 2', 1);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 3', 3);
		
		$this->assertDigestedAll();
	}
	
	function testForeignKeyUpdateEager() {
		$this->load("data/entities.sql");
		$this->load("data/test_data.sql");
		
		$comments = $this->dormio->getManager('Comment');
		foreach($comments->with('blog') as $comment) {
			$comment->blog->title = "Hello {$comment->pk}";
			$comment->blog->save();
		}
		
		$this->assertSQL('SELECT t1."comment_id"...');
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 1', 1);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 2', 1);
		$this->assertSQL('UPDATE "blog" SET "title"=? WHERE "blog_id" = ?', 'Hello 3', 3);
		
		$this->assertDigestedAll();
	}
}