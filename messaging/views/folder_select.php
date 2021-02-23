<select name="folder" class="messaging_folder_select" id="messaging_folder_select">
    <option value=""><?=lang('select_folder')?></option>
	<?php foreach ($folders as $folder_id=>$folder_name): ?>
	<option value="<?=$folder_id?>"><?=$folder_name?></option>
	<?php endforeach; ?>
</select>