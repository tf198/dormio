<?php

class Dormio_Table_Query extends Dormio_Table_Array {

	public $page_size = null;

	public $page_param = 'page';

	public $page_number, $page_count;

	/**
	 *
	 * @var Dormio_Query
	 */
	public $queryset;

	public $auto_sort = true;

	private $choices = array();

	public $entity_fields;
	
	public $exclude_fields = array();

	function setData($queryset) {
		$this->queryset = $this->auto_filter($queryset);
		$this->entity_fields = $this->queryset->entity->getFields();
		 
		if(!$this->fields) {
			foreach($this->entity_fields as $key=>$spec) {
				if(array_search($key, $this->exclude_fields)===false && $spec['type'] != 'manytomany') $this->fields[] = $key;
			}
		}
		if($this->auto_sort) $this->setSortable();

		return $this;
	}

	function setPageSize($i) {
		$this->page_size = $i;
		return $this;
	}

	function auto_filter($queryset) {
		return $queryset;
	}

	function getColumnHeading($field) {
		if(isset($this->column_headings[$field])) {
			return $this->column_headings[$field];
		}

		if(isset($this->entity_fields[$field])) {
			return $this->entity_fields[$field]['verbose'];
		}

		return parent::getColumnHeading($field);
	}

	function sort_default($field, $desc) {
		if($desc) $field = '-' . $field;
		$this->queryset = $this->queryset->orderBy($field);
	}

	function getRows() {
		$this->data = $this->queryset->getIterator();
		return parent::getRows();
	}

	function getValue($field) {
		if(isset($this->entity_fields[$field]) && $this->entity_fields[$field]['type'] != 'manytomany') {
			return $this->row->getFieldValue($field);
		}
	}

	function getType($field) {
		if(isset($this->entity_fields[$field])) {
			return $this->entity_fields[$field]['type'];
		}
		return parent::getType($field);
	}
	/*
	 function getRenderer($field) {
	$renderer = parent::getRenderer($field);
	if(substr($renderer, 0, 12) == 'render_field') return $renderer;
	#if($this->queryset->entity->isField($field) && isset($this->queryset->_meta->fields[$field]['field'])) {
	#	return 'render_type_choice';
	#}
	return $renderer;
	}

	function render_type_choice($value, $field) {
	if(!isset($this->choices[$field])) {
	$method = 'choices_field_' . $field;
	$this->choices[$field] = $this->row->$method();
	}
	return Arr::get($this->choices[$field], $value, $value);
	}
	*/
	function render_default($value, $field) {
		return htmlentities($value);
	}

	function render_type_string($value) {
		return htmlentities($value);
	}

	function render_type_text($value) {
		$short = substr($value, 0, 50);
		if($short != $value) $short .= '...';
		return htmlentities($short);
	}

	function render_type_timestamp($value) {
		if(!$value) return null;
		if(time()-$value < 86400) return date('H:i:s', $value);
		return date('d/m/y H:i', $value);
	}

	function render_type_foreignkey($value, $key) {
		if(!$value) return null;
		return $this->row->__get($key);
	}

	function render_type_onetoone($value, $key) {
		return $this->render_type_foreignkey($value, $key);
	}

	function render() {
		// add any related fields
		foreach($this->fields as $field) {
			if(isset($this->entity_fields[$field])) {
				$spec = $this->entity_fields[$field];
				if($spec['type'] == 'foreignkey' || $spec['type'] == 'onetoone') {
					$this->queryset = $this->queryset->with($field);
				}
			}
		}

		// do pagination if required
		if($this->page_size) $this->queryset = $this->pagenate($this->queryset);

		return parent::render();
	}

	function pagenate($qs) {
		$c = count($qs);
		$this->page_count = ceil($c / $this->page_size);

		$this->page_number = (isset($_GET[$this->page_param])) ? (int)$_GET[$this->page_param] : false;
		if(!$this->page_number) $this->page_number = 1;

		return $qs->limit($this->page_size, ($this->page_number-1)*$this->page_size);
	}

	function pageLinks() {
		$output = array();
		$output[] = '<ul>';
		for($i=1; $i<=$this->page_count; $i++) {
			if($i === $this->page_number) {
				$output[] = "<li class=\"active\"><a href=\"#\">$i</a></li>";
			} else {
				$url = Dormio::URL(array('page' => $i));
				$output[] = "<li><a href=\"{$url}\">{$i}</a></li>";
			}
		}
		$output[] = '</ul>';
		return implode(PHP_EOL, $output);
	}
}

?>
