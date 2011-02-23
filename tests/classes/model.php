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
  static function getMeta() { return self::$meta; }
}

class Blog extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'title' => array('type' => 'text', 'max_length' => 30),
      'the_user' => array('type' => 'foreignkey', 'model' => 'User', 'sql_column' => 'the_blog_user'),
      'tags' => array('type' => 'manytomany', 'model' => 'Tag', 'through' => 'My_Blog_Tag'),
      'comments' => array('type' => 'reverse', 'model' => 'Comment'),
    ),
  );
  static function getMeta() { return self::$meta; }
}

class My_Blog_Tag extends Dormio_Model {
  static $meta = array(
    'table' => 'blog_tag',
    'fields' => array(
      'pk' => array('type' => 'ident', 'sql_column' => 'blog_tag_id'),
      'the_blog' => array('type' => 'foreignkey', 'model' => 'Blog', 'sql_column' => 'the_blog_id'),
      'tag' => array('type' => 'foreignkey', 'model' => 'Tag', 'sql_column' => 'the_tag_id'),
    ),
  );
  static function getMeta() { return self::$meta; }
}

class Comment extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'title' => array('type' => 'text', 'max_length' => 30),
      'user' => array('type' => 'foreignkey', 'model' => 'User', 'sql_column' => 'the_comment_user'),
      'blog' => array('type' => 'foreignkey', 'model' => 'Blog'),
      'tags' => array('type' => 'manytomany', 'model' => 'Tag'),
    ),
  );
  static function getMeta() { return self::$meta; }
  
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
  static function getMeta() { return self::$meta; }
}

class Profile extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'user' => array('type' => 'onetoone', 'model' => 'User'),
      'age' => array('type' => 'integer'),
    ),
  );
  static function getMeta() { return self::$meta; }
}
?>