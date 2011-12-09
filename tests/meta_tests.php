<?
require_once('simpletest/autorun.php');
require_once('bootstrap.php');

class TestOfMeta extends UnitTestCase{

  function testNormalize() {
    // standard fields
    
    $meta = array(
        'fields' => array(
            'my_field' => array(),
        ),
    );
    $field = &$meta['fields']['my_field'];
    try {
      Dormio_Meta::_normalise('Model_1', $meta);
    } catch(Dormio_Meta_Exception $dme) {
      $this->assertEqual($dme->getMessage(), "'type' required on field 'my_field'");
    }
    
    // basic with defaults
    $field['type'] = 'string';
    $generated = Dormio_Meta::_normalise('Test', $meta);
    $this->assertEqual($generated['fields']['my_field'], array('type' => 'string', 'verbose' => 'My Field', 'db_column' => 'my_field', 'null_ok'=>false, 'is_field' => true));
    
    // basic with overrides
    $field['db_column'] = 'my_test_column';
    $field['verbose'] = 'My Funky Field';
    $field['size'] = 42;
    $generated = Dormio_Meta::_normalise('Test', $meta);
    $this->assertEqual($generated['fields']['my_field'], array('type' => 'string', 'verbose' => 'My Funky Field', 'db_column' => 'my_test_column', 'null_ok'=>false, 'is_field' => true, 'size' => 42));
    
    // foreignkey with defaults
    $field = array('type' => 'foreignkey', 'model' => 'Model_2');
    $generated = Dormio_Meta::_normalise('Test', $meta);
    $this->assertEqual($generated['fields']['my_field'], array('type' => 'foreignkey', 'model' => 'model_2', 'verbose' => 'My Field', 'db_column' => 'my_field_id', 'null_ok'=>false, 'is_field' => true, 'local_field'=>'my_field', 'remote_field' => 'pk', 'on_delete' => 'cascade'));
   
  
  }
  
  function testBlog() {
    $blogs = Dormio_Meta::get('Blog');
    $this->assertEqual(array_keys($blogs->fields), array('pk', 'title', 'the_user', 'tags', 'comments'));
    
    $this->assertEqual($blogs->indexes, array(
      'the_user_0' => array('the_blog_user' => 'true'),
    ));
  }
  
  function testComment() {
    $comments = Dormio_Meta::get('Comment');
    $this->assertEqual(array_keys($comments->fields), array('pk', 'title', 'user', 'blog', 'tags'));
    $this->assertEqual($comments->indexes, array(
      'user_0' => array('the_comment_user' => true), 
      'blog_0' => array('blog_id' => true),
    ));
    
    $intermediate = Dormio_Meta::get('comment_tag');
    $this->assertEqual($intermediate->indexes, array(
      'l_comment_0' => array('l_comment_id' => true),
      'r_tag_0' => array('r_tag_id' => true),
    ));
    $this->assertEqual(array_keys($intermediate->fields), array('pk', 'l_comment', 'r_tag'));
  }
  
  function testResolve() {
    $blog = Dormio_Meta::get('Blog');
    $comment = Dormio_Meta::get('Comment');
    $tag = Dormio_Meta::get('Tag');
    $tree = Dormio_Meta::get('Tree');
    $module = Dormio_Meta::get('Module');
    
    // standard field
    $spec = $blog->getSpec('title');
    $this->assertEqual($spec, array('type'=>'text', 'max_length'=>30, 'verbose'=>'Title', 'db_column'=>'title', 'null_ok'=>false, 'is_field'=>true));
    
    // forward foreignkey
    $spec = $comment->getSpec('blog');
    $this->assertEqual($spec, array('type'=>'foreignkey', 'model'=>'blog', 'verbose'=>'Blog', 'db_column'=>'blog_id', 'null_ok'=>false, 'local_field'=>'blog', 'remote_field'=>'pk', 'on_delete'=>'cascade', 'is_field'=>true));
    
    // reverse foreignkey
    $spec = $blog->getSpec('comment_set');
    $this->assertEqual($spec, array('type'=>'foreignkey_rev', 'model'=>'comment', 'local_field'=>'pk', 'remote_field'=>'blog', 'on_delete'=>'cascade'));
    
    // reverse foreignkey (defined) - should be identical to the previous result
    $spec_defined = $blog->getSpec('comments');
    $this->assertEqual($spec, $spec_defined);
    
    // forward manytomany
    $spec = $blog->getSpec('tags');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'tag', 'verbose'=>'Tags', 'through'=>'My_Blog_Tag', 'map_local_field'=>null, 'map_remote_field'=>null));
    
    // reverse manytomany
    $spec = $tag->getSpec('blog_set');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'blog', 'through'=>'My_Blog_Tag', 'map_local_field'=>null, 'map_remote_field'=>null));
    
    // forward foreignkey onto self
    $spec = $tree->getSpec('parent');
    $this->assertEqual($spec, array('type'=>'foreignkey', 'model'=>'tree', 'verbose'=>'Parent', 'db_column'=>'parent_id', 'null_ok'=>false, 'local_field'=>'parent', 'remote_field'=>'pk', 'on_delete'=>'cascade', 'is_field'=>true));
    
    // reverse foreignkey onto self
    $spec = $tree->getSpec('children');
    $this->assertEqual($spec, array('type'=>'foreignkey_rev', 'model'=>'tree', 'local_field'=>'pk', 'remote_field'=>'parent', 'on_delete'=>'cascade'));
    
    // forward manytomany onto self
    $spec = $module->getSpec('depends_on');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'module', 'verbose'=>'Depends On', 'through'=>'module_module', 'map_local_field'=>'l_module', 'map_remote_field'=>'r_module'));
    
    // reverse manytomany onto self
    $spec = $module->getSpec('module_set');
    $this->assertEqual($spec, array('type'=>'manytomany', 'model'=>'module', 'through'=>'module_module', 'map_local_field'=>'r_module', 'map_remote_field'=>'l_module'));
    
  }
  
  function testReverseSpec() {
    $module = Dormio_Meta::get('module');
    $through = Dormio_Meta::get('module_module');
    
    $this->assertEqual($through->getReverseSpec('module', 'l_module'), array('type'=>'foreignkey_rev', 'local_field'=>'pk', 'remote_field'=>'l_module', 'model'=>'module_module', 'on_delete'=>'cascade'));
    $this->assertEqual($through->getReverseSpec('module', 'r_module'), array('type'=>'foreignkey_rev', 'local_field'=>'pk', 'remote_field'=>'r_module', 'model'=>'module_module', 'on_delete'=>'cascade'));
    
    try {
      $through->getReverseSpec('Bad_Class', null);
    } catch(Dormio_Meta_Exception $dme) {
      $this->assertEqual($dme->getMessage(), "No reverse relation for 'bad_class' on 'module_module'");
    }
    
    try {
      $through->getReverseSpec('module', 'bad_accessor');
    } catch(Dormio_Meta_Exception $dme) {
      $this->assertEqual($dme->getMessage(), "No reverse accessor 'module.bad_accessor' on 'module_module'");
    }
    
  }
  
  function testReverse() {
    $blog = Dormio_Meta::get('blog');
  }
  
  function testLocalField() {
    $blog = Dormio_Meta::get('Blog');
    $this->assertTrue($blog->isLocalField('pk'));
    $this->assertTrue($blog->isLocalField('title'));
    $this->assertFalse($blog->isLocalField('comments'));
    $this->assertTrue($blog->isLocalField('the_user'));
    $this->assertFalse($blog->isLocalField('rubbish_field'));
  }
  
}

?>