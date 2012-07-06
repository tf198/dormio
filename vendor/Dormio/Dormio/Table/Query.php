<?php

class Dormio_Table_Query extends Dormio_Table_Array {

	/**
	 * Maximum number of results per page
	 * @var integer
	 */
	public $page_size = null;

	/**
	 * GET parameter for this table
	 * @var string
	 */
	public $page_param = 'page';

	/**
	 * Current page number
	 * @var integer
	 */
	public $page_number;
	
	/**
	 * Total number of pages
	 * @var integer
	 */
	public $page_count;

	/**
	 * Source query
	 * @var Dormio_Query
	 */
	public $queryset;

	/**
	 * Whether to apply sorting to all entity fields
	 * @var boolean
	 */
	public $auto_sort = true;
	
	/**
	 * Whether to resolve related entities to their display field
	 * @var boolean
	 */
	public $auto_related = true;

	/**
	 * Cache for field specs
	 * @var multitype:multitype:string
	 */
	private $spec_cache;

	/**
	 * A list of entity fields to exclude
	 * @var multitype:string
	 */
	public $exclude_fields = array();

	/**
	 * Set the table data
	 * 
	 * @param Dormio_Query
	 * @return Dormio_Table_Query
	 */
	function setData($queryset) {
		// allow subclasses to modify the queryset
		$this->queryset = $this->auto_filter($queryset);
		 
		if(!$this->fields) {
			// get field list from the queryset
			$this->fields = array_keys($this->queryset->types);
		}
		$this->excludeFields($this->exclude_fields);
		$this->setFields($this->fields);

		return $this;
	}
	
	function excludeFields(array $fields) {
		foreach($fields as $field) {
			unset($this->fields[$field]);
		}
	}
	
	/**
	 * @see Dormio_Table_Array::setFields()
	 */
	function setFields(array $fields) {
		$this->spec_cache = array();
		foreach($fields as &$field) {
			try {
				list($entity, $f) = $this->queryset->entity->resolvePath($field);
				$spec = $entity->getField($f);
				// modify onetoone and foreignkey fields
				if($this->auto_related) {
					if($spec['type'] == 'foreignkey' || $spec['type'] == 'onetoone') {
						$entity = $entity->getRelatedEntity($f);
						if($display = $entity->getMeta('display_field')) {
							$field .= '__' . $display;
							$f = $display;
							$this->column_headings[$field] = $entity->getMeta('verbose');
						}
					}
				}
				$this->spec_cache[$field] = $entity->getField($f);
			} catch(Dormio_Config_Exception $e) {
				// not an entity field - ignore
			}
		}
		$this->fields = $fields;
	}

	function setPageSize($i) {
		$this->page_size = $i;
		return $this;
	}

	function auto_filter($queryset) {
		return $queryset;
	}

	function getColumnHeading($field) {
		// explicit heading
		if(isset($this->column_headings[$field])) {
			return $this->column_headings[$field];
		}
		
		// verbose from entity
		if(isset($this->spec_cache[$field])) {
			return $this->spec_cache[$field]['verbose'];
		}
		
		// defer to parent
		return parent::getColumnHeading($field);
	}

	function sort_default($field, $desc) {
		if($desc) $field = '-' . $field;
		$this->queryset = $this->queryset->orderBy($field);
	}

	function getRows() {
		$this->data = new ArrayIterator($this->queryset->findArray());
		//var_dump($this->data);
		return parent::getRows();
	}

	function getType($field) {
		if(isset($this->spec_cache[$field])) {
			return $this->spec_cache[$field]['type'];
		}
		return parent::getType($field);
	}
	
	function getRenderer($field) {
		// check if a custom renderer has been set
		$renderer = parent::getRenderer($field);
		if(substr($renderer, 0, 12) == 'render_field') return $renderer;
		
		// catch fields with choices set
		if(isset($this->entity_fields[$field]['choices'])) {
			return 'render_type_choice';
		}
		
		return $renderer;
	}
	
	function render_default($value, $field) {
		return htmlentities($value);
	}

	function render_type_string($value) {
		return htmlentities($value);
	}

	public function render_type_choice($value, $field) {
		$choices = $this->spec_cache[$field]['choices'];
		if(isset($choices[$value])) return $choices[$value];
		return $value;
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
		
		return "[ {$this->spec_cache[$key]['entity']} {$value} ]";
	}

	function render_type_onetoone($value, $key) {
		return $this->render_type_foreignkey($value, $key);
	}

	function render() {
		// auto sorting
		if($this->auto_sort) $this->setSortable(array_keys($this->spec_cache));
		
		// add any related fields
		foreach($this->fields as $field) {
			if(strpos($field, '__')) {
				$this->queryset = $this->queryset->field($field);
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
		$output[] = '<li class="disabled"><a href="#">Page</a></li>';
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
