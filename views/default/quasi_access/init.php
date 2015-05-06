<?php

// Original name is added to the input/access vars by the hook
$name = elgg_extract('name', $vars);
$original_name = elgg_extract('original_name', $vars);
if (!$name || !$original_name) {
	return;
}

elgg_load_css('chosen');

// Create a hidden input that we can use to replace multiple ACLs
// with a metacollection id and pass it down the action pipeline
echo elgg_view('input/hidden', array(
		'name' => "__quasi_access_inputs[$name]",
		'value' => $original_name,
));
echo elgg_format_element('div', array(
	'class' => 'elgg-input-spacer',
));

// Using inline JS initialization, so that chosen is applied on AJAX
?>
<script type="text/javascript">
	require(['jquery', 'quasi_access/lib'], function ($, QuasiAccess) {
		var $input = $('.elgg-input-access[multiple]:not([data-quasiaccess-init])');
		$input.each(function() {
			var qa = new QuasiAccess($(this));
			$(this).attr('data-quasiaccess-init', true).data('quasiaccess', qa.init());
		});
	});
</script>