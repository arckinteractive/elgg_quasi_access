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
 * We need to allow access to entities that have a metacollection access id:
 * - if metacollection contains ACCESS_FRIENDS and the current user is friends with the metacollection owner
 * - if metacollection contains any of the ACLs that the current user is allowed to see
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

	$user_guid = sanitize_int(elgg_extract('user_id', $params));

	if (!isset($QUASI_ACCESS_ACL_CACHE[$user_guid])) {

		$QUASI_ACCESS_IGNORE_SQL_SUFFIX = true;

		$user = get_entity($user_guid);

		if (!elgg_instanceof($user, 'user')) {
			return $return;
		}

		$metastring_name_id = get_metastring_id('member_acl');

		// We need to grab metacollection access ids for metacollections that contain ACCESS_FRIENDS
		// and where the current user is a friend of the metacollecion owner

		$metastring_value_id = get_metastring_id(ACCESS_FRIENDS);
		if (!$metastring_value_id) {
			$metastring_value_id = add_metastring(ACCESS_FRIENDS);
		}

		$query = "SELECT DISTINCT(ac.id) as acl"
				. " FROM {$dbprefix}access_collections ac"
				. " JOIN {$dbprefix}entities e ON e.guid = ac.owner_guid" // metacollection entity
				. " JOIN {$dbprefix}metadata md ON md.entity_guid = e.guid AND md.name_id = $metastring_name_id" // metacollection member acl metadata
				. " WHERE  md.value_id = $metastring_value_id AND e.owner_guid IN "
						. " (SELECT r.guid_one "
							. " FROM {$dbprefix}entity_relationships r"
							. " WHERE r.relationship='friend' AND r.guid_two=$user_guid)";

		$acls = get_data($query);
		foreach ($acls as $acl) {
			if ($acl->acl) {
				$return[] = (int) $acl->acl;
			}
		}

		// We need to grab metacollection access ids for metacollections that contain any
		// of the current read access ids (except implicit acls)

		$implicit_acls = array(ACCESS_PRIVATE, ACCESS_FRIENDS, ACCESS_LOGGED_IN, ACCESS_PUBLIC);
		$allowed_acl_ids = array_diff($return, $implicit_acls);

		foreach ($allowed_acl_ids as $id) {
			$metastring_value_id = get_metastring_id($id);
			if (!$metastring_value_id) {
				$metastring_value_id = add_metastring($id);
			}
			$metastring_value_ids[] = sanitize_int($metastring_value_id);
		}

		foreach ($return as $id) {
			$acl_ids[] = sanitize_int($id);
		}

		if (empty($acl_ids) || empty($metastring_value_ids)) {
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
			if ($acl->acl) {
				$return[] = (int) $acl->acl;
			}
		}

		$QUASI_ACCESS_IGNORE_SQL_SUFFIX = false;

		$QUASI_ACCESS_ACL_CACHE[$user_guid] = $return;
	} else {
		$return = $QUASI_ACCESS_ACL_CACHE[$user_guid];
	}

	return $return;
}
