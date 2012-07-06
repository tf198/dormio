<?php

/**
 *
 * Base class for all automatic tables.
 *
 * Takes care of sorting and rendering in a consistent manner.
 * @author Tris Forster
 * @package Dormio
 * @subpackage Tables
 */
class Dormio_Table_Array implements Iterator, Countable{

	public static $default_classes = array(
		'div' => 'dt-div',
		'table' => 'dt-table',
		'sortable' => 'dt-sort',
		'sort-asc' => 'dt-sort-asc',
		'sort-desc' => 'dt-sort-desc',
		'th' => 'dt-heading',
		'td' => 'dt-field',
		'bool-false' => 'dt-bool-false',
		'bool-true' => 'dt-bool-true',
	);
	
	public static $default_icons = array(
		'sort-asc' => "&dArr;",
		'sort-desc' => "&uArr;",
	);
	
	public static $default_boolean = array(
		'true' => '&#x2713;',
		'false' => '&#x2717;',
	);
	
	public $template = null;

	protected $data = null;

	public $fields = null;

	protected $renderers = array();

	public $column_headings = array();

	public $show_headings = true;

	public $row_headings = null;

	public $sortable = array();

	public $param_sort = "sort";

	public $sort_field = null;

	public $sort_desc = false;

	public $caption = null;

	public $classes, $icons;

	public $row_number;

	public $field_count = 0;
	
	public $row;
	
	public function __construct($data=null) {
		$this->parseParams();
		
		$this->icons = self::$default_icons;
		$this->classes = self::$default_classes;
		
		if($data) $this->setData($data);
	}

	/**
	 * Set the data object
	 * @param array $data
	 * @return Table_Simple
	 */
	public function setData($data) {
		$this->data = new ArrayIterator($data);
		if(!$this->fields) $this->fields = array_keys($this->data[0]);
		return $this;
	}

	/**
	 * Overide the default fields
	 * @param array $fields
	 * @return Table_Simple
	 */
	public function setFields(array $fields) {
		$this->fields = $fields;
		return $this;
	}

	/**
	 * Override the default classes
	 * @param array $classes
	 */
	public function setClasses(array $classes) {
		$this->classes = array_merge($this->classes, $classes);
		return $this;
	}

	/**
	 * Set or generate the column headings
	 * @param array $headings
	 * @return Table_Simple
	 */
	public function setColumnHeadings(array $headings=null) {
		if($headings===null) $headings = array_combine($this->fields, array_map('Dormio::title', $this->fields));
		$this->column_headings = $headings;
		$this->show_headings = true;
		return $this;
	}

	/**
	 * Set the row heading field
	 * @param string $field
	 * @return Table_Simple
	 */
	public function setRowHeadings($field='id') {
		$this->row_headings = $field;
		return $this;
	}

	/**
	 * Set or generate the sortable fields
	 * @param array $sortable
	 * @return Table_Simple
	 */
	public function setSortable($sortable=null) {
		if($sortable===null) $sortable = $this->fields;
		$this->sortable = $sortable;
		return $this;
	}

	public function setSorting($field, $desc=false) {
		$this->sort_field = $field;
		$this->sort_desc = $desc;
	}

	public function parseParams() {
		if(isset($_GET[$this->param_sort])) {
			$field = $_GET[$this->param_sort];
			$desc = false;
			if(substr($field, 0, 1)=='-') {
				$field = substr($field, 1);
				$desc = true;
			}
			$this->setSorting($field, $desc);
		}
	}

	/**
	 * Get the column headings
	 * @return array
	 */
	public function getColumnHeadings() {
		return array_map(array($this, 'renderColumnHeading'), $this->fields);
	}

	public function renderColumnHeading($field) {
		$heading = $this->getColumnHeading($field);
		if(array_search($field, $this->sortable)===false) return $heading;
		$sort = urlencode($field);
		// add a minus for descending
		$icon = "";
		if($this->sort_field!==null && $field == $this->sort_field) {
			if($this->sort_desc) {
				$class = $this->classes['sort-desc'];
				$icon = "&nbsp;<span class=\"{$this->classes['sort-desc']}\">{$this->icons['sort-desc']}</span>";
			} else {
				$sort = "-" . $sort;
				$icon = "&nbsp;<span class=\"{$this->classes['sort-asc']}\">{$this->icons['sort-asc']}</span>";
			}
			
		}
		$url = Dormio::URL(array($this->param_sort => $sort));
		return "<a href=\"{$url}\">{$heading}</a>{$icon}";
	}

	public function getColumnHeading($field) {
		return (array_key_exists($field, $this->column_headings)) ? $this->column_headings[$field] : Dormio::title($field);
	}

	public function getRowHeading() {
		return $this->renderField($this->row_headings);
	}

	public function getRows() {
		if(!$this->data) throw new RuntimeException("No data given");

		// set some extra vars
		$this->field_count = count($this->fields);

		return $this;
	}

	public function sort() {
		// do the sort, using a custom sorter if provided
		if(array_search($this->sort_field, $this->sortable)!==false) {
			$method = "sort_field_{$this->sort_field}";
			if(method_exists($this, $method)) {
				$this->$method($this->sort_field, $this->sort_desc);
			} else {
				$this->sort_default($this->sort_field, $this->sort_desc);
			}
		}
	}

	public function sort_default($field, $desc) {
		usort($this->data, array($this, '_sorter'));
	}

	public function _sorter($a, $b) {
		$res = $a[$this->sort_field] < $b[$this->sort_field] ? -1 : 1;
		return $this->sort_desc ? $res * -1 : $res;
	}

	protected function preRender() {
		// pass
	}

	public function __toString() {
		try {
			return $this->render();
		} catch(Exception $e) {
			return "<pre>Table render error:\n{$e->getMessage()}\n{$e->getTraceAsString()}</pre>";
		}
	}

	public function render() {
		if(!$this->fields) throw new Exception("No data set");
		if($this->sort_field!==null) $this->sort();
		if($this->row_headings!==null) $this->renderers[$this->row_headings] = $this->getRenderer($this->row_headings);
		$this->renderers = array_combine($this->fields, array_map(array($this, 'getRenderer'), $this->fields));
		$this->preRender();

		if(!$this->template) $this->template = VENDOR_PATH . 'Dormio/resources/default_table.php';

		if(!is_readable($this->template)) throw new RuntimeException("Unable to find template: {$this->template}");
		 
		// a basic View renderer

		// export vars
		$table = $this;
		$classes = $this->classes;

		ob_start();
		include $this->template;
		return ob_get_clean();
	}

	public function getValue($field) {
		return isset($this->row[$field]) ? $this->row[$field] : null;
	}

	function getRenderer($field) {
		$method = "render_field_{$field}";
		if(method_exists($this, $method)) return $method;

		$method = "render_type_" . $this->getType($field);
		if(method_exists($this, $method)) return $method;

		return "render_default";
	}

	function getType($field) {
		return gettype($field);
	}

	public function renderField($field) {
		$value = $this->getValue($field);
		$renderer = (isset($this->renderers[$field])) ? $this->renderers[$field] : 'render_default';

		return $this->$renderer($value, $field);
	}

	function render_default($value, $field) {
		return htmlentities($value);
	}

	public function render_field_id() {
		return key($this->data);
	}

	public function render_type_boolean($value) {
		if($value === null) return '';
		
		$b = ($value) ? "true" : "false";
		
		return "<span class=\"{$this->classes['bool-' . $b]}\">" . self::$default_boolean[$b] . "</span>";
	}

	function current() {
		// loop through the fields and create an array for output
		$this->row = $this->data->current();
		return array_combine($this->fields, array_map(array($this, 'renderField'), $this->fields));
	}

	function rewind() {
		$this->data->rewind();
		$this->row_number = 1;
	}

	function key() {
		return $this->data->key();
	}

	function valid() {
		return $this->data->valid();
	}

	function next() {
		$this->data->next();
		$this->row_number++;
	}
	
	function count() {
		return count($this->data);
	}
}
