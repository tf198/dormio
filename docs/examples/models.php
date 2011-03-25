<?php
/**
* Example models for tutorials
* @package dormio
* @subpackage example
* @filesource
*/
class Blog extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'title' => array('type' => 'string', 'max_length' => 30),
        'body' => array('type' => 'text'),
        'author' => array('type' => 'foreignkey', 'model' => 'User'),
        'comments' => array('type' => 'reverse', 'model' => 'Comment'),
      ),
    );
  }
}

class Comment extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'blog' => array('type' => 'foreignkey', 'model' => 'Blog'),
        'body' => array('type' => 'text'),
        'author' => array('type' => 'foreignkey', 'model' => 'User'),
      ),
    );
  }
}

class User extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'username' => array('type' => 'string', 'max_length' => 50),
        'password' => array('type' => 'password'),
      ),
    );
  }
  
  function display() {
    return $this->username;
  }
}

class Profile extends Dormio_Model {
  static function getMeta() {
    return array(
      'fields' => array(
        'user' => array('type' => 'onetoone', 'model' => 'User'),
        'fav_colour' => array('type' => 'string', 'max_length' => 10),
        'age' => array('type' => 'integer'),
      ),
    );
  }
}
?>