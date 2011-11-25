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
      'l_comment_0' => array('l_comment_id' => true),
      'r_tag_0' => array('r_tag_id' => true),
    ));
    $this->assertEqual(array_keys($schema['columns']), array('pk', 'l_comment', 'r_tag'));
  }
  
  function testDBColumns() {
    $blogs = Dormio_Meta::get('Blog');
    $this->assertEqual($blogs->DBColumns(), array('blog_id', 'title', 'the_blog_user'));
    //$this->assertEqual($blogs->prefixedDBColumns(), array('{blog}.{blog_id} AS {blog_blog_id}', '{blog}.{title} AS {blog_title}', '{blog}.{the_blog_user} AS {blog_the_blog_user}'));
  }
  
  function testResolve() {
    $blog = Dormio_Meta::get('Blog');
    $comment = Dormio_Meta::get('Comment');
    $tag = Dormio_Meta::get('Tag');
    $tree = Dormio_Meta::get('Tree');
    $module = Dormio_Meta::get('Module');
    
    // standard field
    $spec = $blog->getSpec('title');
    $this->assertEqual($spec, array('type'=>'text', 'max_length'=>30, 'verbose'=>'Title', 'field' => 'title', 'db_column'=>'title', 'is_field'=>true));
    
    // forward foreignkey
    $spec = $comment->getSpec('blog');
    $this->assertEqual($spec, array('type'=>'foreignkey', 'model'=>'blog', 'verbose'=>'Blog', 'field' => 'blog', 'db_column'=>'blog_id', 'to_field'=>null, 'on_delete'=>'cascade', 'is_field'=>true));
    
    // reverse foreignkey
    $spec = $blog->getSpec('comment_set');
    $this->assertEqual($spec, array('type'=>'foreignkey_rev', 'model'=>'comment', 'db_column'=>null, 'to_field'=>'blog_id', 'on_delete'=>'cascade', 'field' => 'comment[blog]'));
    
    // reverse foreignkey (defined) - should be identical to the previous result
    $spec_defined = $blog->getSpec('comments');
    $this->assertEqual($spec, $spec_defined);
    
    // forward manytomany
    $spec = $blog->getSpec('tags');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'tag', 'verbose'=>'Tags', 'field' => 'tags', 'through'=>'My_Blog_Tag', 'local_field'=>null, 'remote_field'=>null));
    
    // reverse manytomany
    $spec = $tag->getSpec('blog_set');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'blog', 'field' => 'blog[tags]', 'through'=>'My_Blog_Tag', 'local_field'=>null, 'remote_field'=>null));
    
    // forward foreignkey onto self
    $spec = $tree->getSpec('parent');
    $this->assertEqual($spec, array('type'=>'foreignkey', 'model'=>'tree', 'verbose'=>'Parent', 'field' => 'parent', 'db_column'=>'parent_id', 'to_field'=>null, 'on_delete'=>'cascade', 'is_field'=>true));
    
    // reverse foreignkey onto self
    $spec = $tree->getSpec('children');
    $this->assertEqual($spec, array('type'=>'foreignkey_rev', 'model'=>'tree', 'db_column'=>null, 'to_field'=>'parent_id', 'on_delete'=>'cascade', 'field' => 'tree[parent]'));
    
    // forward manytomany onto self
    $spec = $module->getSpec('depends_on');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'module', 'verbose'=>'Depends On', 'field' => 'depends_on', 'through'=>'module_module', 'local_field'=>'l_module', 'remote_field'=>'r_module'));
    
    // reverse manytomany onto self
    $spec = $module->getSpec('module_set');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'module', 'through'=>'module_module', 'field' => 'module[depends_on]', 'local_field'=>'r_module', 'remote_field'=>'l_module'));
    
  }
  
  function testReverseSpec() {
    $module = Dormio_Meta::get('module');
    $through = Dormio_Meta::get('module_module');
    
    $this->assertEqual($through->getReverseSpec('module', 'l_module'), array('type'=>'foreignkey_rev', 'db_column'=>null, 'to_field'=>'l_module_id', 'model'=>'module_module', 'on_delete'=>'cascade', 'field'=>'module_module[l_module]'));
    $this->assertEqual($through->getReverseSpec('module', 'r_module'), array('type'=>'foreignkey_rev', 'db_column'=>null, 'to_field'=>'r_module_id', 'model'=>'module_module', 'on_delete'=>'cascade', 'field'=>'module_module[r_module]'));
    
    try {
      $through->getReverseSpec('Bad_Class', null);
    } catch(Dormio_Meta_Exception $dme) {
      $this->assertEqual($dme->getMessage(), "No reverse relation for 'Bad_Class' on 'module_module'");
    }
    
    try {
      $through->getReverseSpec('module', 'bad_accessor');
    } catch(Dormio_Meta_Exception $dme) {
      $this->assertEqual($dme->getMessage(), "No reverse accessor 'module.bad_accessor' on 'module_module'");
    }
    
  }
  
  function testReverseList() {
    $blog = Dormio_Meta::get('blog');
    //var_dump($blog->columns);
    //var_dump($blog->reverseFields());
  }
  
}

?>