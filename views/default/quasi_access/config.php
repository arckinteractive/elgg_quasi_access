<?php

$global_acls = array(
	'ACCESS_PUBLIC' => ACCESS_PUBLIC,
	'ACCESS_PRIVATE' => ACCESS_PRIVATE,
	'ACCESS_LOGGED_IN' => ACCESS_LOGGED_IN,
	'ACCESS_FRIENDS' => ACCESS_FRIENDS,
	'QUASI_ACCESS_GROUPS' => QUASI_ACCESS_GROUPS,
);

$global_acls = json_encode($global_acls);
$css = elgg_normalize_url('/mod/elgg_quasi_access/vendors/chosen_v1.4.2/chosen.min.css');

echo PHP_EOL;
echo "elgg.QuasiAccess = elgg.QuasiAccess || {};" . PHP_EOL;
echo "elgg.QuasiAccess.globalAcls = $global_acls;" . PHP_EOL;
echo "elgg.QuasiAccess.chosenCss = '$css';" . PHP_EOL;
