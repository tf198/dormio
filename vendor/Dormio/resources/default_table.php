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
			<? if($table->row_headings!==null) echo "<th>{$table->getRowHeading()}</th>\n" ?>
			<? foreach($row as $key=>$value): ?>
			<td class="<? echo "{$classes['td']} field-{$key}" ?>"><? echo $value?>
			</td>
			<? endforeach ?>
		</tr>
		<? endforeach ?>
		<? if($table->row_number == 1): ?>
		<tr>
			<td colspan="<?=$table->field_count?>">No data available</td>
		</tr>
		<? endif ?>
	</tbody>

	<? if(isset($table->page_size) && $table->row_number>1): ?>
	<tfoot>
		<tr>
			<td colspan="<?=$table->field_count?>">Page <?php echo $table->pageLinks()?>
			</td>
		</tr>
	</tfoot>
	<? endif ?>
</table>
