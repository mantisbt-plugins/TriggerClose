<?php

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
					// @todo handle error
					unset($new_value[$index]);
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
					// @todo handle error
					unset($new_value[$index]);
				}
			}
			break;

	}
	if(plugin_config_get($option) != $new_value) {
		plugin_config_set($option, $new_value);
	}
}

form_security_purge('plugin_format_config_edit');

print_successful_redirect(plugin_page('config', true));
