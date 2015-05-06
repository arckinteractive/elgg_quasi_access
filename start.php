<?php

define('QUASI_ACCESS_METACOLLECTION_SUBTYPE', 'quasi_access_metacollection');

define('QUASI_ACCESS_GROUPS', -3);

require_once('lib/functions.php');
require_once('lib/hooks.php');
require_once('lib/events.php');

elgg_register_event_handler('init', 'system', 'elgg_quasi_access_init');

/**
 * Initialize the plugin
 */
function elgg_quasi_access_init() {

	// Substitute user input with metacollection acl ids
	elgg_register_plugin_hook_handler('action', 'all', 'elgg_quasi_access_prepare_action_values', 1);

	// Add metacollections to user access list
	elgg_register_plugin_hook_handler('access:collections:read', 'all', 'elgg_quasi_access_collections_read', 999);
	elgg_register_plugin_hook_handler('access:collections:write', 'all', 'elgg_quasi_access_collections_write', 999);

	// Rebuild metacollections when the metacollection owner no longer belongs to a member acl
	elgg_register_plugin_hook_handler('access:collections:remove_user', 'all', 'elgg_quasi_access_reset_user_metacollections', 999);

	// Rebuild metacollections when a member acl of a metacollection is deleted
	elgg_register_plugin_hook_handler('access:collections:deletecollection', 'all', 'elgg_quasi_access_reset_metacollections', 999);

	// Check if 'multiple' parameter has been passed to the access input and serve quasi_access input if so
	elgg_register_plugin_hook_handler('view', 'input/access', 'elgg_quasi_access_input_view_replacement');

	elgg_register_css('chosen', '/mod/elgg_quasi_access/vendors/chosen_v1.4.2/chosen.min.css');
	elgg_define_js('chosen', array(
		'src' => '/mod/elgg_quasi_access/vendors/chosen_v1.4.2/chosen.jquery.min.js',
		'deps' => array('jquery'),
	));
}
