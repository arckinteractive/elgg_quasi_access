<?php

/**
 * Take any quasi access values that were submitted with the form
 * and convert them to metacollection ids,
 * feed them back to the action, in such a way as to make them accessible
 * under the same variable names and in the same nested hierarchy as
 * was supplied to the form by the original views
 *
 * @param string $hook Equals 'action'
 * @param string $type Current action anme
 * @param boolean $return
 * @param array $params
 * @return boolean
 */
function elgg_quasi_access_prepare_action_values($hook, $type, $return, $params) {

	$quasi_access_inputs = get_input('__quasi_access_inputs');

	if (!is_array($quasi_access_inputs)) {
		return $return;
	}

	foreach ($quasi_access_inputs as $input_name_hash => $input_name) {

		$user_input = get_input($input_name_hash);

		$metacollection_id = elgg_quasi_access_get_metacollection_from_members($user_input, get_input('__quasi_access_owner_guid', null));

		$query = http_build_query(array(
			$input_name => $metacollection_id
		));
	
		$arr = array();
		parse_str($query, $arr);

		foreach ($arr as $expected_name => $expected_value) {
			$user_value = get_input($expected_name);
			if (is_array($user_value)) {
				$expected_value = array_merge_recursive($user_value, $expected_value);
			}
			set_input($expected_name, $expected_value);
		}
	}

	set_input('__quasi_access_inputs', null);
	
	return $return;
}


/**
 * Collapsing metacollections into their components
 * 
 * @global type $QUASI_ACCESS_IGNORE_SQL_SUFFIX Flag to prevent infinite loops
 * @global type $QUASI_ACCESS_ACL_CACHE Cache to avoid duplicate calls to the DB
 */
function elgg_quasi_access_collections_read($hook, $type, $return, $params) {

	global $QUASI_ACCESS_IGNORE_SQL_SUFFIX, $QUASI_ACCESS_ACL_CACHE;

	if ($QUASI_ACCESS_IGNORE_SQL_SUFFIX) {
		return $return;
	}

	$dbprefix = elgg_get_config('dbprefix');

	$user_guid = elgg_extract('user_id', $params);

	if (!isset($QUASI_ACCESS_ACL_CACHE[$user_guid])) {

		$QUASI_ACCESS_IGNORE_SQL_SUFFIX = true;

		$user = get_entity($user_guid);

		if (!elgg_instanceof($user, 'user')) {
			return $return;
		}

		// We need to grab metadata from the metacollection entity
		// where 'member_acl' metadata value contains any of the
		// values from the current $return

		$metastring_name_id = get_metastring_id('member_acl');
		foreach ($return as $id) {
			$metastring_value_id = get_metastring_id($id);
			if (!$metastring_value_id) {
				$metastring_value_id = add_metastring($id);
			}
			$acl_ids[] = sanitize_int($id);
			$metastring_value_ids[] = sanitize_int($metastring_value_id);
		}

		if (empty($acl_ids)
				|| empty($metastring_value_ids)) {
			return $return;
		}

		$acl_ids_instr = implode(',', $acl_ids);
		$metastring_value_ids_instr = implode(',', $metastring_value_ids);

		$query = "SELECT DISTINCT(ac.id) as acl"
				. " FROM {$dbprefix}access_collections ac"
				. " JOIN {$dbprefix}metadata md ON md.entity_guid = ac.owner_guid AND md.name_id = $metastring_name_id"
				. " JOIN {$dbprefix}access_collection_membership acm ON acm.access_collection_id = ac.id"
				. " WHERE acm.access_collection_id NOT IN ($acl_ids_instr) AND md.value_id IN ($metastring_value_ids_instr)";

		$acls = get_data($query);
		foreach ($acls as $acl) {
			$return[] = (int) $acl->acl;
		}

		$QUASI_ACCESS_IGNORE_SQL_SUFFIX = false;

		$QUASI_ACCESS_ACL_CACHE[$user_guid] = $return;
	} else {
		$return = $QUASI_ACCESS_ACL_CACHE[$user_guid];
	}
	
	return $return;
}
