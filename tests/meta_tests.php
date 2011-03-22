<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfMeta extends UnitTestCase{

  function testBlog() {
    $blogs = Dormio_Meta::get('Blog');
    $this->assertEqual(array_keys($blogs->columns), array('pk', 'title', '__user', 'the_user', '__tag', 'tags', 'comments'));
    
    $schema = $blogs->schema();
    $this->assertEqual($schema['indexes'], array('the_user'));
  }
  
  function testComment() {
    $comments = Dormio_Meta::get('Comment');
    $schema = $comments->schema();
    $this->assertEqual(array_keys($comments->columns), array('pk', 'title', '__user', 'user', '__blog', 'blog', '__tag', 'tags'));
    
    $this->assertEqual($schema['indexes'], array('user', 'blog'));
    
    $intermediate = Dormio_Meta::get('comment_tag');
    $schema = $intermediate->schema();
    $this->assertEqual($schema['indexes'], array('comment', 'tag'));
    $this->assertEqual(array_keys($schema['columns']), array('pk', 'comment', 'tag'));
    
  }
}

?>