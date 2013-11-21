<?php

define('QUASI_ACCESS_METACOLLECTION_SUBTYPE', 'quasi_access_metacollection');

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
	elgg_register_plugin_hook_handler('access:collections:read', 'user', 'elgg_quasi_access_collections_read', 999);

	/**
	 * @todo: do we need to hook into 'delete' events?
	 */
}
