<?php

$entity = elgg_extract('entity', $vars);

?>
<div>
	<label><?php echo elgg_echo('quasiaccess:settings:default_multiple') ?></label>
	<?php
		echo elgg_view('input/dropdown', array(
			'name' => 'params[default_multiple]',
			'value' => $entity->default_multiple,
			'options_values' => array(
				0 => elgg_echo('question:no'),
				1 => elgg_echo('question:yes'),
			)
		));
	?>
</div>

