<?php
/* 
 * @package dormio
 */

/**
 * Dormio implementation of Modified Preorder Traversal Tree
 *
 * @author tris
 * @package dormio
 */
class Dormio_MPTT extends Dormio_Model {

  private $_lhs, $_rhs;
  public $_stack = array();

  function __construct(PDO $db, $dialect=null) {
    parent::__construct($db, $dialect);
    $this->_lhs = $this->_meta->fields['lhs']['db_column'];
    $this->_rhs = $this->_meta->fields['rhs']['db_column'];
  }

  function _hydrate($data, $prefixed=false) {
    parent::_hydrate($data, $prefixed);
    // shift old bits off the stack
    while(isset($this->_stack[0]) and $this->__get('lhs') > $this->_stack[0]) array_shift($this->_stack);
    // add ourself onto the stack
    array_unshift($this->_stack, $this->__get('rhs'));
  }

  static $_mptt_fields = array(
    'lhs' => array('type' => 'integer'),
    'rhs' => array('type' => 'integer'),
  );

  public static function _meta($klass) {
    $meta = parent::_meta($klass);
    $meta['fields'] = array_merge(self::$_mptt_fields, $meta['fields']);
    return $meta;
  }

  function depth() {
    return count($this->_stack);
  }

  function refresh() {
    $this->_forget('lhs');
    $this->_forget('rhs');
  }

  function tree() {
    $this->refresh();
    return $this->objects()->where('%lhs% BETWEEN ? AND ?', array($this->lhs, $this->rhs))->orderBy('lhs');
  }

  function path() {
    $this->refresh();
    return $this->objects()->where('%lhs% <= ? AND %rhs% >= ?', array($this->lhs, $this->rhs))->orderBy('lhs');
  }

  function descendants() {
    return ($this->rhs - $this->lhs - 1) / 2;
  }

  function add($obj) {
    $obj->lhs = $this->rhs;
    $obj->rhs = $this->rhs + 1;
    $this->_db->beginTransaction();
    try {
      $this->objects()->filter('lhs', '>', $this->rhs)->update(array(), array('%lhs%=%lhs%+2'));
      $this->objects()->filter('rhs', '>=', $this->rhs)->update(array(), array('%rhs%=%rhs%+2'));
      $obj->save();
      $this->_db->commit();
    } catch(PDOException $e) {
      $this->_db->rollback();
      throw $e;
    }
    $this->_data[$this->_dataIndex('rhs')] += 2;
  }

  function delete($preview=false) {
    // force a reload
    $this->refresh();
    $diff = $this->rhs - $this->lhs + 1;
    $sql = array(
      // delete child items
      array("DELETE FROM {{$this->_meta->table}} WHERE {{$this->_lhs}}>? AND {{$this->_rhs}}<?", array($this->lhs, $this->rhs)),
      // shift everything back down
      array("UPDATE {{$this->_meta->table}} SET {{$this->_lhs}}={{$this->_lhs}}-{$diff}  WHERE {{$this->_lhs}}>?", array($this->rhs)),
      array("UPDATE {{$this->_meta->table}} SET {{$this->_rhs}}={{$this->_rhs}}-{$diff}  WHERE {{$this->_rhs}}>?", array($this->rhs)),
    );
    foreach($sql as &$pair) $pair[0] = $this->_dialect->quoteIdentifiers($pair[0]);
    $sql = array_merge($sql, parent::delete(true));
    return ($preview) ? $sql : $this->objects()->batchExecute($sql);
  }
}
