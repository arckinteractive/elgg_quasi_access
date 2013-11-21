<?php

/**
 * Elgg access level input
 *
 * @uses $vars['value']          The current value, if any
 * @uses $vars['options_values'] Array of value => label pairs (overrides default)
 * @uses $vars['name']           The name of the input field
 * @uses $vars['entity']         Optional. The entity for this access control (uses access_id)
 * @uses $vars['class']          Additional CSS class
 * @uses $vars['multiple']		 Allow users to select multiple values (creates a metacollection)
 * @uses $vars['strict']		 Allow users to generate custom ACL on the fly
 */
if (isset($vars['class'])) {
	$vars['class'] = "elgg-input-access {$vars['class']}";
} else {
	$vars['class'] = "elgg-input-access";
}

$defaults = array(
	'disabled' => false,
	'value' => get_default_access(),
	'options_values' => get_write_access_array(),
);

if (isset($vars['entity'])) {
	$defaults['value'] = $vars['entity']->access_id;
	unset($vars['entity']);
}

$vars = array_merge($defaults, $vars);

if ($vars['value'] == ACCESS_DEFAULT) {
	$vars['value'] = get_default_access();
}

if (isset($vars['multiple'])) {
	$multiple = $vars['multiple'];
	unset($vars['multiple']);
} else {
	//$multiple = elgg_get_plugin_setting('default_multiple', 'elgg_quasi_access');
	$multiple = true;
}

if ($multiple) {
	$vars['value'] = elgg_quasi_access_collapse_metacollection($vars['value']);

	if (is_array($vars['options_values']) && sizeof($vars['options_values']) > 0) {
		foreach ($vars['options_values'] as $key => $val) {
			$checkbox_options_values[$val] = $key;
		}

		if (elgg_is_logged_in()) {
			$user_guid = elgg_get_logged_in_user_guid();

			$dbprefix = elgg_get_config('dbprefix');
			$query = "SELECT DISTINCT(ac.id), ge.name"
					. " FROM {$dbprefix}access_collections ac"
					. " JOIN {$dbprefix}groups_entity ge ON ge.guid = ac.owner_guid"
					. " JOIN {$dbprefix}entity_relationships r ON r.guid_two = ge.guid AND r.relationship='member' AND r.guid_one = $user_guid"
					. " ORDER BY ge.name";

			$group_acls = get_data($query);
		}

		if (count($group_acls)) {
			foreach ($group_acls as $group_acl) {
				$label = elgg_echo('quasiaccess:group_acl', array($group_acl->name));
				$checkbox_options_values[$label] = $group_acl->id;
			}
		}

		$vars['options'] = $checkbox_options_values;
		unset($vars['options_values']);

		$name = $vars['name'];
		$name_hash = md5($vars['name']);
		$vars['name'] = $name_hash;

		echo elgg_view('input/checkboxes', $vars);
		echo elgg_view('input/hidden', array(
			'name' => "__quasi_access_inputs[$name_hash]",
			'value' => $name
		));
	}
} else {
	if (is_array($vars['options_values']) && sizeof($vars['options_values']) > 0) {
		echo elgg_view('input/dropdown', $vars);
	}
}
