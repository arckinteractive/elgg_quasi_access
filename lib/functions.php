<?php

/**
 * Get metacollection entity from supplied $access_id
 *
 * @param integer $access_id ACL id
 * @return boolean
 */
function elgg_quasi_access_get_metacollection($access_id = null) {

	$acl = get_access_collection($access_id);

	if (!$acl->id) {
		return false;
	}

	$owner = get_entity($acl->owner_guid);

	return (elgg_instanceof($owner, 'object', QUASI_ACCESS_METACOLLECTION_SUBTYPE)) ? $owner : false;
}

/**
 * Check if supplied $access_id is a metacollection
 *
 * @param integer $access_id ACL id
 * @return boolean
 */
function elgg_quasi_access_is_metacollection($access_id = null) {

	return (bool) elgg_quasi_access_get_metacollection($access_id);
}

/**
 * Collapse meta collection into its member ACLs
 *
 * @param integer $access_id Metacollection access id
 * @return mixed
 */
function elgg_quasi_access_collapse_metacollection($access_id = null) {

	if (is_array($access_id)) {
		return $access_id;
	}

	$metacollection = elgg_quasi_access_get_metacollection($access_id);

	if (!$metacollection) {
		return array($access_id);
	}

	$return = array();

	$member_acls = elgg_get_metadata(array(
		'guids' => $metacollection->guid,
		'metadata_names' => 'member_acl',
		'limit' => 0
	));

	foreach ($member_acls as $member_acl) {
		$return[] = (int) $member_acl->value;
	}

	return $return;
}

/**
 * Get metacollection id from its member ACLs
 * Create a new metacollection if none exist
 *
 * @param array $member_acl_ids An array of ACL ids
 * @return array
 */
function elgg_quasi_access_get_metacollection_from_members($member_acl_ids = array(), $owner_guid = null) {

	$owner = get_entity($owner_guid);

	if (!$owner) {
		$owner = elgg_get_logged_in_user_entity();
	}


	$member_acl_ids = elgg_quasi_access_filter_member_acls($member_acl_ids);

	if ($member_acl_ids === false) {
		return get_default_access($owner);
	}

	if (!is_array($member_acl_ids)) {
		return $member_acl_ids;
	}

	if (count($member_acl_ids) == 1) {
		return $member_acl_ids[0];
	}

	sort($member_acl_ids, SORT_NUMERIC);
	$hash = md5(implode('|', $member_acl_ids));

	$metacollections = elgg_get_entities_from_metadata(array(
		'owner_guids' => $owner->guid,
		'types' => 'object',
		'subtypes' => QUASI_ACCESS_METACOLLECTION_SUBTYPE,
		'metadata_name_value_pairs' => array(
			'name' => 'hash', 'value' => $hash
		),
		'limit' => 1
	));

	if (!$metacollections) {
		return elgg_quasi_access_create_metacollection($member_acl_ids, $owner->guid);
	}

	$metacollection_guid = sanitize_int($metacollections[0]->guid);
	$dbprefix = elgg_get_config('dbprefix');
	$query = "SELECT * FROM {$dbprefix}access_collections WHERE owner_guid = {$metacollection_guid}";
	$collection = get_data_row($query);

	return ($collection->id) ? (int)$collection->id : get_default_access($owner);
}

/**
 * Create a new metacollection
 *
 * @param array $member_acl_ids
 * @param integer $owner_guid
 * @return integer ACL id of the metacollection
 */
function elgg_quasi_access_create_metacollection($member_acl_ids = array(), $owner_guid = null) {

	$member_acl_ids = elgg_quasi_access_filter_member_acls($member_acl_ids);

	if ($member_acl_ids === false) {
		return get_default_access(get_entity($owner_guid));
	}

	if (!is_array($member_acl_ids)) {
		return $member_acl_ids;
	}

	if (count($member_acl_ids) == 1) {
		return $member_acl_ids[0];
	}

	if (!$owner_guid) {
		$owner_guid = elgg_get_logged_in_user_guid();
	}

	sort($member_acl_ids, SORT_NUMERIC);
	$hash = md5(implode('|', $member_acl_ids));

	$ia = elgg_set_ignore_access();
	$metacollection = new ElggObject;
	$metacollection->subtype = QUASI_ACCESS_METACOLLECTION_SUBTYPE;
	$metacollection->owner_guid = $owner_guid;
	$metacollection->title = 'metacollection';
	$metacollection->description = implode('|', $member_acl_ids);
	$metacollection->access_id = ACCESS_PUBLIC;

	if ($metacollection->save()) {

		$metacollection->hash = $hash;
		$metacollection->member_acl = $member_acl_ids;
		
		$id = create_access_collection('metacollection', $metacollection->getGUID());
		add_user_to_access_collection($owner_guid, $id);

	}

	elgg_set_ignore_access($ia);

	return ($id !== false) ? $id : get_default_access(get_entity($owner_guid));
}

/**
 * Implicit ACLs should take precedence and can not be combined with other ACLs
 *
 * @param array $member_acl_ids
 * @return mixed array or integer
 */
function elgg_quasi_access_filter_member_acls($member_acl_ids = array()) {

	if (!is_array($member_acl_ids)) {
		return false;
	}
	
	if (in_array(ACCESS_PRIVATE, $member_acl_ids)) {
		return ACCESS_PRIVATE;
	}

	if (in_array(ACCESS_LOGGED_IN, $member_acl_ids)) {
		return ACCESS_LOGGED_IN;
	}

	if (in_array(ACCESS_PUBLIC, $member_acl_ids)) {
		return ACCESS_PUBLIC;
	}

	return array_unique($member_acl_ids, SORT_NUMERIC);
}
