<div class="<?php echo $classes['div']?>">
	<table class="<? echo $classes['table']?>">
		<? if($table->caption) echo "<caption>{$table->caption}</caption>\n" ?>
		<? if($table->show_headings): ?>
		<thead>
			<tr>
				<? if($table->row_headings!==null) echo "<th/>\n" ?>
				<? foreach($table->getColumnHeadings() as $key=>$heading): ?>
				<th class="<?php echo $classes['th'] ?>"><?=$heading?>
				</th>
				<? endforeach ?>
			</tr>
		</thead>
		<? endif ?>
	
		<tbody>
			<? foreach($table->getRows() as $row): ?>
			<tr>
				<?php
				if($table->row_headings!==null) {
					echo "<th>{$table->getRowHeading()}</th>" . PHP_EOL;
				}
				foreach($row as $key=>$value) {
					$c = "{$classes['td']} field-{$key}";
					if(isset($classes["td-{$key}"])) $c .= ' ' . $classes["td-{$key}"];
					echo "<td class=\"{$c}\">{$value}</td>" . PHP_EOL;
				}
				?>
				
			</tr>
			<? endforeach ?>
			<? if($table->row_number == 1): ?>
			<tr>
				<td colspan="<?=$table->field_count?>">No data available</td>
			</tr>
			<? endif ?>
		</tbody>
	
		
	</table>
<? if(isset($table->page_size) && $table->row_number>1): ?>
	<div class="pagination pagination-right"><?php echo $table->pageLinks()?></div>
<? endif ?>
</div>