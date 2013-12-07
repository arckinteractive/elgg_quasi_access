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
	$multiple = elgg_get_plugin_setting('default_multiple', 'elgg_quasi_access');
}

$name_hash = md5(microtime());

$vars['value'] = elgg_quasi_access_collapse_metacollection($vars['value']);

if (is_array($vars['options_values']) && sizeof($vars['options_values']) > 0) {
	foreach ($vars['options_values'] as $key => $val) {
		$checkbox_options_values[$val] = $key;
	}

	$vars['options'] = $checkbox_options_values;
	unset($vars['options_values']);

	$name = $vars['name'];
	$vars['name'] = $name_hash;

	echo elgg_view('input/checkboxes', $vars);
	echo elgg_view('input/hidden', array(
		'name' => "__quasi_access_inputs[$name_hash]",
		'value' => $name
	));
}

