<?php

/**
 * Get metacollection entity from supplied $access_id
 *
 * @param integer $access_id ACL id
 * @return boolean
 */
function elgg_quasi_access_get_metacollection_object($access_id = null) {

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

	return (bool) elgg_quasi_access_get_metacollection_object($access_id);
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

	$metacollection = elgg_quasi_access_get_metacollection_object($access_id);

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
		return (int)get_default_access($owner);
	}

	if (!is_array($member_acl_ids)) {
		return (int)$member_acl_ids;
	}
	
	if (count($member_acl_ids) == 1) {
		return (int)reset($member_acl_ids);
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

	$metacollection = $metacollections[0];

	return elgg_quasi_access_get_metacollection_id($metacollection->guid);
}

/**
 * Get ACL id for the give metacollection object guid
 *
 * @param integer $metacollection_object_guid
 * @return integer
 */
function elgg_quasi_access_get_metacollection_id($metacollection_object_guid) {

	$metacollection_object = get_entity($metacollection_object_guid);

	if (!elgg_instanceof($metacollection_object, 'object', QUASI_ACCESS_METACOLLECTION_SUBTYPE)) {
		return get_default_access();
	}

	if (!$metacollection_object->collection_id) {
		$dbprefix = elgg_get_config('dbprefix');
		$query = "SELECT * FROM {$dbprefix}access_collections WHERE owner_guid = {$metacollection_object_guid}";
		$collection = get_data_row($query);
		$metacollection_object->collection_id = (int)$collection->id;
	}

	return $metacollection_object->collection_id;
}

/**
 * Create a new metacollection object and metacollection ACL
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

		$metacollection->collection_id = $id;
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
		return null;
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

/**
 * Propagate access id changes across DB tables
 *
 * @param int $current_access_id Current access id to look for
 * @param int $future_access_id Access id to set
 */
function elgg_quasi_access_set_access_id($current_access_id, $future_access_id) {

	$current_access_id = sanitize_int($current_access_id);
	$future_access_id = sanitize_int($future_access_id);

	if ($current_access_id == $future_access_id) {
		return false;
	}

	$dbprefix = elgg_get_config('dbprefix');


	update_data("UPDATE {$dbprefix}entities SET access_id=$future_access_id"
			. " WHERE access_id=$current_access_id");

	update_data("UPDATE {$dbprefix}river SET access_id=$future_access_id"
			. " WHERE access_id=$current_access_id");

	update_data("UPDATE {$dbprefix}metadata SET access_id=$future_access_id"
			. " WHERE access_id=$current_access_id");

	update_data("UPDATE {$dbprefix}annotations SET access_id=$future_access_id"
			. " WHERE access_id=$current_access_id");

	return true;
}
