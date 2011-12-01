<?php
/**
 * Example models for the tutorials
 * @package dormio
 * @subpackage example
 */

/**
* A minimal blog entry
* @package dormio
* @subpackage example
* @filesource
*/
class Blog extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'title' => array('type' => 'string', 'max_length' => 30),
      'body' => array('type' => 'text'),
      'author' => array('type' => 'foreignkey', 'model' => 'User'),
      'comments' => array('type' => 'reverse', 'model' => 'Comment'),
    ),
  );
}

/**
* Comments for blog entries
* @package dormio
* @subpackage example
* @filesource
*/
class Comment extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'blog' => array('type' => 'foreignkey', 'model' => 'Blog'),
      'body' => array('type' => 'text'),
      'author' => array('type' => 'foreignkey', 'model' => 'User'),
    ),
  );
}

/**
* Basic user object
* @package dormio
* @subpackage example
* @filesource
*/
class User extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'username' => array('type' => 'string', 'max_length' => 50),
      'password' => array('type' => 'password'),
    ),
  );
  
  /**
  * This overrides the default display for the object.
  * It is used in HTML select elements instead of [User:23]
  */
  function display() {
    return ucfirst($this->username);
  }
}

/**
* A profile that is attached to a user
* @package dormio
* @subpackage example
* @filesource
*/
class Profile extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'user' => array('type' => 'onetoone', 'model' => 'User'),
      'fav_colour' => array('type' => 'string', 'max_length' => 10),
      'age' => array('type' => 'integer'),
    ),
  );
}
