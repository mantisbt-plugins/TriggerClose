<?php

function error_out($message) {
	$_SESSION['TriggerClose_flash_message'] = $message;
	return print_header_redirect(plugin_page('config', true));
}

if(!$_POST) {
	// POST is empty, wrong HTTP method used
	return error_out("Must POST to save TriggerClose settings");
}

form_security_validate('plugin_format_config_edit');

auth_reauthenticate();
access_ensure_global_level(config_get('manage_plugin_threshold'));

$api = new TriggerCloseApi();

foreach($api->config() as $option => $default_value) {
	switch($option) {
		case 'after_seconds':
			$new_value = gpc_get_int($option, $default_value);
			if($new_value < TriggerCloseApi::MIN_SECONDS) {
				// disables trigger, 0 will abort in main script
				$new_value = 0;
			}
			break;
		case 'categories':
			$new_value = gpc_get_int_array($option, $default_value);
			foreach($new_value as $index => $category) {
				if(!$api->validate_category($category)) {
					return error_out("Invalid category given");
				}
			}
			break;
		case 'privileges':
			$new_value = gpc_get_int_array($option, $default_value);
			foreach($new_value as $index => $privilege) {
				if(!$api->validate_privilege($privilege)) {
					return error_out("Invalid privilege given");
				}
			}
			break;
		case 'maybe_close_active':
			$new_value = (int)(boolean) gpc_get_int($option, $default_value);
			break;
		case 'message':
			$new_value = gpc_get_string($option, $default_value);
			break;
		case 'statuses':
			$new_value = gpc_get_int_array($option, $default_value);
			foreach($new_value as $index => $status) {
				if(!$api->validate_status($status)) {
					return error_out("Invalid status given");
				}
			}
			break;
		case 'user':
			$new_value = gpc_get_int($option, $default_value);
			if(!user_exists($new_value)) {
				return error_out("Invalid user given");
			}
			break;

	}
	if(plugin_config_get($option) != $new_value) {
		plugin_config_set($option, $new_value);
	}
}

form_security_purge('plugin_format_config_edit');

$_SESSION['TriggerClose_flash_message'] = "Settings saved successfully";

print_successful_redirect(plugin_page('config', true));
