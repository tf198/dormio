<?

class User extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'name' => array('type' => 'text', 'max_length' => 3),
      'blogs' => array('type' => 'reverse', 'model' => 'Blog'),
      'comments' => array('type' => 'reverse', 'model' => 'Comment'),
      'profile' => array('type' => 'reverse', 'model' => 'Profile'),
    ),
  );
}

class Blog extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'title' => array('type' => 'text', 'max_length' => 30),
      'the_user' => array('type' => 'foreignkey', 'model' => 'User', 'db_column' => 'the_blog_user'),
      'tags' => array('type' => 'manytomany', 'model' => 'Tag', 'through' => 'My_Blog_Tag'),
      'comments' => array('type' => 'reverse', 'model' => 'Comment'),
    ),
  );
}

class My_Blog_Tag extends Dormio_Model {
  static $meta = array(
    'table' => 'blog_tag',
    'fields' => array(
      'pk' => array('type' => 'ident', 'db_column' => 'blog_tag_id'),
      'the_blog' => array('type' => 'foreignkey', 'model' => 'Blog', 'db_column' => 'the_blog_id'),
      'tag' => array('type' => 'foreignkey', 'model' => 'Tag', 'db_column' => 'the_tag_id'),
    ),
  );
}

class Comment extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'title' => array('type' => 'text', 'max_length' => 30),
      'user' => array('type' => 'foreignkey', 'model' => 'User', 'db_column' => 'the_comment_user'),
      'blog' => array('type' => 'foreignkey', 'model' => 'Blog'),
      'tags' => array('type' => 'manytomany', 'model' => 'Tag'),
    ),
  );
  
  function display() {
    return $this->title;
  } 
}

class Tag extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'tag' => array('type' => 'text', 'max_length' => 30),
      'blogs' => array('type' => 'reverse', 'model' => 'Blog'),
      'comments' => array('type' => 'reverse', 'model' => 'Comment'),
    ),
  );
}

class Profile extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'user' => array('type' => 'onetoone', 'model' => 'User'),
      'age' => array('type' => 'integer'),
    ),
  );
}

class Module extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'name' => array('type' => 'string', 'max_length' => 30),
      'depends_on' => array('type' => 'manytomany', 'model' => 'Module'),
      'required_by' => array('type' => 'reverse', 'model' => 'Module', 'accessor' => 'depends_on')
    ),
  );
}

class Tree extends Dormio_Model {
  static $meta = array(
      'fields' => array(
          'name' => array('type' => 'string', 'max_length' => 30),
          'parent' => array('type' => 'foreignkey', 'model' => 'Tree'),
          'children' => array('type' => 'reverse', 'model' => 'Tree'),
      )
  );
}

class ARO extends Dormio_MPTT {
  static $meta = array(
    'fields' => array(
      'name' => array('type' => 'string'),
    )
  );
}
?>