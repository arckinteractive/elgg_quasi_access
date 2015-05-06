Elgg QuasiAccess
================
!(Elgg 1.11.x)[https://img.shields.io/badge/Elgg-1.11.x-yellowgreen.svg]

The plugins takes on a different approach to allow metacollection access.
Instead of performing expensive maintenance of access collections, it uses
reverse DB querying to determine what metacollections the user belongs to.

To add an input field to your form, use:
```
elgg_view('input/access', array(
	'multiple' => true,
	'value' => $entity->access_id
));
```
or add the quasi_access input:
```
elgg_view('input/quasi_access', array(
	'value' => $entity->access_id
));
```
