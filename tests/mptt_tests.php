<?php
require_once('simpletest/autorun.php');
require_once('db_tests.php');

class TestOfMPTT extends TestOfDB{

  function testMetaGeneration() {
    $meta = Dormio_Meta::get('ARO');
    $this->assertEqual(array_keys($meta->fields), array('pk', 'lhs', 'rhs', 'name'));
  }

  function testBasic() {
    $this->load("sql/test_schema.sql");
    $this->load('sql/test_data.sql');
    $root = $this->pom->get('ARO', 1);

    $iter = $root->tree();


    $this->assertQueryset($iter, 'name', array('/', 'admin'));

    // add a new group
    $users = $this->pom->create('ARO', array('name' => 'users'));
    $root->add($users);
    $this->assertQueryset($iter, 'name', array('/', 'admin', 'users'));

    // add a new user
    $anon = $this->pom->create('ARO', array('name' => 'anon'));
    $users->add($anon);
    $this->assertQueryset($root->tree(), 'name', array('/', 'admin', 'users', 'anon'));
    $this->assertQueryset($users->tree(), 'name', array('users', 'anon'));

    $this->assertQueryset($anon->path(), 'name', array('/', 'users', 'anon'));

    $this->assertEqual($root->descendants(), 3);

    //foreach($this->pom->manager('ARO') as $item) echo "{$item->lhs} {$item->rhs} {$item->name} {$item->depth()}\n";
    //foreach($anon->path() as $item) echo "{$item->lhs} {$item->rhs} {$item->name} {$item->depth()}\n";

    $sql = $users->delete();
    
    //var_dump($this->db->getSQL());
    
    $this->assertQueryset($this->pom->manager('ARO'), 'name', array('/', 'admin'));
  }
}
?>
