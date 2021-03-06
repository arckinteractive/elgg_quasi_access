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

		$query[] = http_build_query(array(
			$input_name => $metacollection_id
		));
	}

	$query = implode("&", $query);

	$arr = array();
	parse_str($query, $arr);

	foreach ($arr as $expected_name => $expected_value) {
		$user_value = get_input($expected_name);

		if (is_array($user_value)) {
			$expected_value = array_merge_recursive($user_value, $expected_value);
		}

		set_input($expected_name, $expected_value);
	}

	set_input('__quasi_access_inputs', null);

	return $return;
}

/**
 * We need to allow access to entities that have a metacollection access id:
 * - if metacollection contains ACCESS_FRIENDS and the current user is friends with the metacollection owner
 * - if metacollection contains any of the ACLs that the current user is allowed to see
 * - if metacollection contains QUASI_ACCESS_GROUPS and the current user is in the same group as the metacollection owner
 * 
 * @param string $hook Equals 'access:collections:read'
 * @param string $type Equals 'all'
 * @param array $return An array of ACLs before the hook
 * @param array $params Additional params
 * @uses $params['user_id'] GUID of the user whose read access array is being obtained
 *
 * @global boolean $QUASI_ACCESS_IGNORE_SQL_SUFFIX Flag to prevent infinite loops
 * @global array $QUASI_ACCESS_ACL_CACHE Cache to avoid duplicate calls to the DB
 *
 * @return array An array of ACLs, including quasi access metacollection ids
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

		$metastring_name_id = elgg_get_metastring_id('member_acl');

		// We need to grab metacollection access ids for metacollections that contain ACCESS_FRIENDS
		// and where the current user is a friend of the metacollecion owner

		$metastring_value_id = elgg_get_metastring_id(ACCESS_FRIENDS);

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

		// We need to grab metacollection access ids for metacollections that contain QUASI_ACCESS_GROUPS
		// and where the current user shares a group with the metacollecion owner

		$metastring_value_id = elgg_get_metastring_id(QUASI_ACCESS_GROUPS);

		$query = "SELECT DISTINCT(ac.id) as acl"
				. " FROM {$dbprefix}access_collections ac"
				. " JOIN {$dbprefix}entities e ON e.guid = ac.owner_guid" // metacollection entity
				. " JOIN {$dbprefix}metadata md ON md.entity_guid = e.guid AND md.name_id = $metastring_name_id" // metacollection member acl metadata
				. " WHERE  md.value_id = $metastring_value_id "
				. " AND e.owner_guid != $user_guid AND e.owner_guid IN ( SELECT er1.guid_one FROM {$dbprefix}entity_relationships er1 "
				. " JOIN {$dbprefix}groups_entity ge ON er1.guid_two = ge.guid AND er1.relationship = 'member'"
				. " JOIN {$dbprefix}entity_relationships er2 ON er2.guid_two = ge.guid AND er2.relationship = 'member'"
				. " WHERE er2.guid_one = $user_guid )";

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
			$metastring_value_id = elgg_get_metastring_id($id);
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
				. " WHERE acm.access_collection_id NOT IN ($acl_ids_instr)"
				. " AND md.value_id IN ($metastring_value_ids_instr)";

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

/**
 * Allows users to use group ACLs to create metacollections when uploading
 * content outside of a specific group
 *
 * @param string $hook Equals 'access:collections:write'
 * @param string $type Equals 'all'
 * @param array $return An array of ACLs before the hook
 * @param array $params Additional params
 * @uses $params['user_id'] GUID of the user whose read access array is being obtained
 *
 * @return array An array of ACLs including group ACLs
 *
 * @global array $QUASI_ACCESS_GROUPS_ACL_CACHE Cache to avoid duplicate calls to the DB
 */
function elgg_quasi_access_collections_write($hook, $type, $return, $params) {

	global $QUASI_ACCESS_GROUPS_ACL_CACHE;

	$page_owner = elgg_get_page_owner_entity();
	if (elgg_instanceof($page_owner, 'group') || !elgg_is_logged_in()) {
		return $return;
	}

	$user_guid = sanitize_int($params['user_id']);

	if (!isset($QUASI_ACCESS_GROUPS_ACL_CACHE[$user_guid])) {
		$dbprefix = elgg_get_config('dbprefix');
		$query = "SELECT DISTINCT(ac.id) AS acl_id, e.subtype AS group_subtype, ge.name as group_name"
				. " FROM {$dbprefix}access_collections ac"
				. " JOIN {$dbprefix}entities e ON e.guid = ac.owner_guid AND e.type = 'group'"
				. " JOIN {$dbprefix}groups_entity ge ON ge.guid = e.guid"
				. " JOIN {$dbprefix}entity_relationships r ON r.guid_two = ge.guid AND r.relationship='member' AND r.guid_one = $user_guid"
				. " ORDER BY ge.name";

		$group_acls = get_data($query);

		if (count($group_acls)) {
			foreach ($group_acls as $group_acl) {
				if ($group_acl->acl_id) {
					$subtype = get_subtype_from_id($group_acl->group_subtype);
					$subtype_label = ($subtype) ? elgg_echo("item:group:$subtype") : elgg_echo('group');
					$label = elgg_echo('quasiaccess:group_acl', array($group_acl->group_name, ucwords($subtype_label)));
					$groups[$group_acl->acl_id] = $label;
				}
			}
		}

		$QUASI_ACCESS_GROUPS_ACL_CACHE[$user_guid] = $groups;
	}

	if (is_array($QUASI_ACCESS_GROUPS_ACL_CACHE[$user_guid])) {
		$return[QUASI_ACCESS_GROUPS] = elgg_echo('quasiaccess:all_groups');
		return $return + $QUASI_ACCESS_GROUPS_ACL_CACHE[$user_guid];
	}

	return $return;
}

/**
 * Rebuild metacollections when the owner no longer belongs to a member acl
 *
 * @param string $hook Equals 'access:collections:remove_user'
 * @param string $type Equals 'all'
 * @param boolean $return Should this action propagate?
 * @param array $params Additional params
 * @uses $params['collection_id'] ACL id
 * @uses $params['user_guid'] GUID of the user being removed
 *
 * @return boolean
 */
function elgg_quasi_access_reset_user_metacollections($hook, $type, $return, $params) {

	if (!$return) {
		return $return; // Another plugin is preventing the user from being removed from ACL
	}

	$collection_id = elgg_extract('collection_id', $params, 0);
	$user_guid = elgg_extract('user_guid', $params, 0);

	$ia = elgg_set_ignore_access();

	$metacollections = new ElggBatch('elgg_get_entities_from_metadata', (array(
		'types' => 'object',
		'subtypes' => QUASI_ACCESS_METACOLLECTION_SUBTYPE,
		'owner_guids' => $user_guid,
		'metadata_names' => 'member_acl',
		'metadata_values' => $collection_id,
		'limit' => false
	)));

	$metacollections->setIncrementOffset(false);

	foreach ($metacollections as $metacollection) {

		$metacollection_id = elgg_quasi_access_get_metacollection_id($metacollection->guid);
		$member_acl_ids = $metacollection->member_acl;

		$new_member_acl_ids = array_diff($member_acl_ids, array($collection_id));
		$new_metacollection_id = elgg_quasi_access_get_metacollection_from_members($new_member_acl_ids, $user_guid);

		elgg_quasi_access_set_access_id($metacollection_id, $new_metacollection_id);

		$metacollection->delete();
		delete_access_collection($metacollection_id);
	}

	elgg_set_ignore_access($ia);
	return $return;
}

/**
 * Rebuild metacollections when the owner no longer belongs to a member acl
 *
 * @param string $hook Equals 'access:collections:deletecollection'
 * @param string $type Equals 'all'
 * @param boolean $return Should this action propagate?
 * @param array $params Additional params
 * @uses $params['collection_id'] ACL id
 *
 * @return boolean
 */
function elgg_quasi_access_reset_metacollections($hook, $type, $return, $params) {

	if (!$return) {
		return $return; // Another plugin is preventing the user from being removed from ACL
	}

	$collection_id = elgg_extract('collection_id', $params, 0);

	$ia = elgg_set_ignore_access();

	$metacollections = new ElggBatch('elgg_get_entities_from_metadata', (array(
		'types' => 'object',
		'subtypes' => QUASI_ACCESS_METACOLLECTION_SUBTYPE,
		'metadata_names' => 'member_acl',
		'metadata_values' => $collection_id,
		'limit' => false
	)));

	$metacollections->setIncrementOffset(false);

	foreach ($metacollections as $metacollection) {

		$metacollection_id = elgg_quasi_access_get_metacollection_id($metacollection->guid);
		$member_acl_ids = $metacollection->member_acl;

		$new_member_acl_ids = array_diff($member_acl_ids, array($collection_id));
		$new_metacollection_id = elgg_quasi_access_get_metacollection_from_members($new_member_acl_ids, $user_guid);

		elgg_quasi_access_set_access_id($metacollection_id, $new_metacollection_id);

		delete_access_collection($metacollection_id);
	}

	$collection = get_access_collection($collection_id);
	$collection_owner = get_entity($collection->owner_guid);

	if (elgg_instanceof($collection_owner, 'object', QUASI_ACCESS_METACOLLECTION_SUBTYPE)) {
		$collection_owner->delete();
	}

	elgg_set_ignore_access($ia);
	return $return;
}

/**
 * Filter access input vars
 *
 * @param string $hook   "view_vars"
 * @param string $view   "input/access"
 * @param array  $vars   Vars
 * @param array  $params Hook params
 * @return array
 */
function elgg_quasi_access_filter_vars($hook, $view, $vars, $params) {

	if (!is_array($vars)) {
		$vars = elgg_extract('vars', $params);
	}

	$name = elgg_extract('name', $vars);
	$multiple = elgg_extract('multiple', $vars);

	if ($multiple === true || (elgg_get_plugin_setting('default_multiple', 'elgg_quasi_access') && $multiple !== false && $name == 'access_id')) {
		// Force multiple
		$vars['multiple'] = true;

		// Store original input name
		$vars['original_name'] = $vars['name'];

		// Replace input name with a hash, so that we can substitute multiple ACLs with a metacollection id
		$name_hash = md5(serialize($vars));
		$vars['name'] = $name_hash;
		
		// Collapse the value into metacollection member ids
		$vars['value'] = elgg_quasi_access_collapse_metacollection($vars['value']);
	}

	return $vars;
}
