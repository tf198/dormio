<?php
class MyBlog extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'title' => array('type' => 'string', 'max_length' => 30),
        'body' => array('type' => 'text'),
        'author' => array('type' => 'foreignkey', 'model' => 'MyUser'),
        'comments' => array('type' => 'reverse', 'model' => 'MyComment'),
      ),
    );
  }
}

class MyComment extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'blog' => array('type' => 'foreignkey', 'model' => 'MyBlog'),
        'body' => array('type' => 'text'),
        'author' => array('type' => 'foreignkey', 'model' => 'MyUser'),
      ),
    );
  }
}

class MyUser extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'username' => array('type' => 'string', 'max_length' => 50),
        'password' => array('type' => 'password'),
      ),
    );
  }
}

class MyProfile extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'user' => array('type' => 'foreignkey', 'model' => 'MyUser'),
        'fav_colour' => array('type' => 'string', 'max_length' => 10),
        'age' => array('type' => 'integer'),
      ),
    );
  }
}
?>