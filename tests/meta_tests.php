<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfMeta extends UnitTestCase{

  function testBlog() {
    $blogs = Dormio_Meta::get('Blog');
    $this->assertEqual(array_keys($blogs->columns), array('pk', 'title', '__user', 'the_user', '__tag', 'tags', 'comments'));
    
    $schema = $blogs->schema();
    $this->assertEqual($schema['indexes'], array(
      'the_user_0' => array('the_blog_user' => 'true'),
    ));
  }
  
  function testComment() {
    $comments = Dormio_Meta::get('Comment');
    $schema = $comments->schema();
    $this->assertEqual(array_keys($comments->columns), array('pk', 'title', '__user', 'user', '__blog', 'blog', '__tag', 'tags'));
    $this->assertEqual($schema['indexes'], array(
      'user_0' => array('the_comment_user' => true), 
      'blog_0' => array('blog_id' => true),
    ));
    
    $intermediate = Dormio_Meta::get('comment_tag');
    $schema = $intermediate->schema();
    $this->assertEqual($schema['indexes'], array(
      'comment_0' => array('comment_id' => true),
      'tag_0' => array('tag_id' => true),
    ));
    $this->assertEqual(array_keys($schema['columns']), array('pk', 'comment', 'tag'));
    
  }
  
  function testResolve() {
    // foreign key
    $blog = Dormio_Meta::get('Blog');
    $blog->resolve('comments', $spec1, $meta1);
    $blog->resolve('comment_set', $spec2, $meta2);
    $this->assertEqual($spec1, $spec2);
    $this->assertEqual($meta1, $meta2);
    
    // many to many
    $comment = Dormio_Meta::get('Comment');
    $comment->resolve('tags', $spec1, $meta1);
    $comment->resolve('tag_set', $spec2, $meta2);
    $this->assertEqual($spec1, $spec2);
    $this->assertEqual($meta1, $meta2);
  }
}

?>