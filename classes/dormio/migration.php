<?
class Dormio_Migration extends Dormio_Model {
  static $meta = array(
    'fields' => array(
      'module' => array('type' => 'string'),
      'model' => array('type' => 'string'),
      'file' => array('type' => 'string'),
      'applied' => array('type' => 'timestamp'),
      'schema' => array('type' => 'text', 'null' => true),
    ),
  );
}
?>