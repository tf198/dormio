<?php
class Dormio_Scaffold {
	
	private $dormio;
	
	public $action, $entity, $pk;
	
	public $defaults = array(
		'base_path' => null,
		'table_class' => 'Dormio_Scaffold_Table',
		'form_class' => 'Dormio_Form',
	);
	
	function __construct($dormio, $config=array()) {
		$this->dormio = $dormio;
		
		$config = array_merge($this->defaults, $config);
		if(!$config['base_path']) $config['base_path'] = $_SERVER['SCRIPT_NAME'];
		$this->config = $config;
		
		$this->action = (isset($_GET['action'])) ? $_GET['action'] : 'index';
		$this->entity = (isset($_GET['entity'])) ? $_GET['entity'] : null;
		$this->pk = (isset($_GET['pk'])) ? $_GET['pk'] : null;		
	}
	
	function getContent() {
		$method = "action_{$this->action}";
		
		if(!method_exists($this, $method)) throw new Dormio_Exception("No such action: {$this->action}");
		return $this->$method();
	}
	
	function action_index() {
		$enties = array();
		foreach($this->dormio->config->getEntities() as $entity) {
			$title = $this->dormio->config->getEntity($entity)->getMeta('verbose');
			$url = $this->generate_link(array('action' => 'list', 'entity' => $entity));
			$entities[] = "<li><a href=\"{$url}\">{$title}</a></li>";
		}
		return "<ul>\n" . implode("\n", $entities) . "</ul>\n";
	}
	
	function action_list() {
		if(!$this->entity) throw new Exception("No entity given");
		
		$qs = $this->dormio->getManager($this->entity);
		$klass = $this->config['table_class'];
		$table = new $klass($qs, $this);
		
		return $table;
	}
	
	function action_edit() {
		if(!$this->entity) throw new Exception("No entity given");
		if(!is_numeric($this->pk)) throw new Exception("No valid pk given");
		
		$klass = $this->config['form_class'];
		
		$obj = $this->dormio->getObject($this->entity, $this->pk);
		$form = new $klass($obj);
		
		if($form->is_valid()) {
			$form->save();
			$this->redirect(array('action' => 'list', 'entity' => $this->entity));
		} else {
			return <<< EOS
<div class="well">
<div style="float: right"><a href="{$this->generate_link(array('action' => 'list', 'entity' => $this->entity))}">Back</a></div>
<h3>{$form->header()}</h3>
{$form->open('')}
{$form}
{$form->buttons()}
{$form->close()}
</div>

EOS;
		}
	}
	
	function action_delete() {
		if(!$this->entity) throw new Exception("No entity given");
		if(!$this->pk) throw new Exception("No pk given");
		
		$obj = $this->dormio->getObject($this->entity, $this->pk);
		$obj->delete();
		
		$this->redirect(array('action' => 'list', 'entity' => $this->entity));
	}
	
	function redirect($params) {
		header('Location: ' . $this->generate_link($params, '&'));
		exit;
	}
	
	function generate_link($params=array(), $glue='&amp;') {
		$url = $this->config['base_path'];
		
		if(!$params) return $url;
		
		$url .= (strpos($url, '?') === false) ? '?' : $glue;
		return $url . http_build_query($params, '', $glue);
	}
}

class Dormio_Scaffold_Table extends Dormio_Table_Query {
	
	public $page_size = 20;

	function __construct($data, $controller) {
		parent::__construct($data);
		$this->controller = $controller;
		
		$this->classes['table'] = 'table table-bordered table-condensed';
		$this->fields[] = 'actions';
		$this->column_headings['actions'] = '';
		$this->caption = "<div style=\"text-align: right;\"><a href=\"{$this->action_link('edit')}\">Add new +</a></div>";
	}
	
	function render_field_actions() {
		return <<< EOS
<a href="{$this->action_link('edit')}">Edit</a>
<a href="{$this->action_link('delete')}" onclick="return confirm('Are you sure you wish to delete this record?');">Delete</a>
EOS;
	}
	
	function action_link($action) {
		$params = array(
			'entity' => $this->controller->entity,
			'action' => $action,
			'pk' => ($this->row) ? $this->row['pk'] : 0,
		);
		return $this->controller->generate_link($params);
	}
	
}