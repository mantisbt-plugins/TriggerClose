<?php

auth_reauthenticate();
access_ensure_global_level(config_get('manage_plugin_threshold'));

html_page_top('TriggerClose');

print_manage_menu();

$saved_categories = plugin_config_get('categories');
if(!$saved_categories) {
	$saved_categories = array();
}

$saved_statuses = plugin_config_get('statuses');
if(!$saved_statuses) {
	$saved_statuses = array();
}

$saved_privileges = plugin_config_get('privileges');
$users_affected_by_privileges = 0;
if(!$saved_privileges) {
	$saved_privileges = array();
} else {
	# We sort the privileges so that [0] represents the most privileged
	# permission entry, since user_count_level() gives us all users of
	# that level *or higher*
	sort($saved_privileges);
	$users_affected_by_privileges = user_count_level($saved_privileges[0]);
}

// @todo find a corresponding function in the API
$query = "SELECT *
	FROM ".db_get_table('mantis_user_table')."
	ORDER BY date_created DESC";
$result = db_query_bound($query);
$user_count = db_num_rows($result);
$saved_user = plugin_config_get('user');

$api = new TriggerCloseApi();

if(isset($_SESSION['TriggerClose_flash_message'])) {
	echo '<p>'.$_SESSION['TriggerClose_flash_message'].'</p>';
	unset($_SESSION['TriggerClose_flash_message']);
}

?>

<br />
<form action="<?php echo plugin_page('config_edit')?>" method="post">
<?php echo form_security_field('plugin_format_config_edit') ?>
<table class="width100" cellspacing="1">

<tr>
	<td class="form-title" colspan="2">
		TriggerClose
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="maybe_close_active">Trigger check on page loads</label>
		<br />
		<p class="small">Otherwise, enable <a href="#cron">cron</a>.</p>
	</td>
	<td valign="top">
		<input type="checkbox" id="maybe_close_active" name="maybe_close_active" value="1" <?php echo plugin_config_get('maybe_close_active')? 'checked="checked"' : null ?> />
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="after_seconds">Close a ticket that haven't been modified after this many seconds</label>
		<br /><span class="small">0 means it's disabled, <?php echo TriggerCloseApi::MIN_SECONDS ?> is minimum</span>
	</td>
	<td valign="top">
		<input type="text" id="after_seconds" name="after_seconds" value="<?php echo plugin_config_get('after_seconds') ?>" />
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="message">Note that signs an automatically closed issue</label>
	</td>
	<td valign="top">
		<textarea name="message" cols="80" rows="10"><?php echo plugin_config_get('message') ?></textarea>
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="categories">Categories to look for inactive issues in</label>
	</td>
	<td valign="top">
		<select multiple="multiple" size="10" name="categories[]" id="categories">
		<?php foreach($api->available_categories() as $category_id => $label) {?>
			<option <?php if(in_array($category_id, $saved_categories)) { ?>selected="selected"<?php }?> value="<?php echo $category_id ?>"><?php echo $label ?></option>
		<?php } ?>
		</select>
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="privileges">Close issues assigned to a member included in the following groups</label><br />
		<?php echo $users_affected_by_privileges; ?> users in the selected groups
	</td>
	<td valign="top">
		<select multiple="multiple" size="10" name="privileges[]" id="privileges">
		<?php foreach($api->available_privileges() as $privilege_id => $label) {?>
			<option <?php if(in_array($privilege_id, $saved_privileges)) { ?>selected="selected"<?php }?> value="<?php echo $privilege_id ?>"><?php echo $label ?></option>
		<?php } ?>
		</select>
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="statuses">Issues with these statuses will be checked for inactivity</label>
	</td>
	<td valign="top">
		<select multiple="multiple" size="<?php echo count($api->available_statuses()) ?>" name="statuses[]" id="statuses">
		<?php foreach($api->available_statuses() as $status => $label) { ?>
			<option <?php if(in_array($status, $saved_statuses)) { ?>selected="selected"<?php }?> value="<?php echo $status ?>"><?php echo $label ?></option>
		<?php } ?>
		</select>
	</td>
</tr>

<tr <?php echo helper_alternate_class()?>>
	<td valign="top">
		<label for="user">Close as this user</label>
	</td>
	<td valign="top">
		<select name="user" id="user">
		<?php while($user_count--) {
			$row = db_fetch_array($result);
		?>
			<option <?php if($row['id'] == $saved_user) { ?>selected="selected"<?php }?> value="<?php echo $row['id'] ?>"><?php echo $row['username'] ?></option>
		<?php } ?>
		</select>
	</td>
</tr>

<tr>
	<td class="center" colspan="2">
		<input type="submit" class="button" value="Save" />
	</td>
</tr>

</table>
</form>

<h3 id="cron">As a cronjob</h3>
<p>To enable cron, type <br />
<pre>*/5 * * * * /usr/bin/env php <?php echo realpath(dirname(__FILE__).'/../TriggerCloseApi.php') ?></pre><br />
into a crontab (for example, by typing <pre>crontab -e</pre> as a user which can execute that file.</p>

<?php
html_page_bottom();
