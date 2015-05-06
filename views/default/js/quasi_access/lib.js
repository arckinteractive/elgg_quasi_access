define(['jquery', 'elgg', 'chosen'], function ($, elgg) {

	var QuasiAccess = function ($input, config) {
		this.$input = $input;
		this.config = config || {
			disable_search_threshold: 10,
			inherit_select_classes: true,
			no_results_text: elgg.echo('quasiaccess:nomatch'),
			placeholder_text_single: elgg.echo('quasiaccess:placeholder:single'),
			placeholder_text_multiple: elgg.echo('quasiaccess:placeholder:multiple'),
			search_contains: true,
			allow_single_deselect: true
		};
		this.exclusiveAcls = [elgg.QuasiAccess.globalAcls.ACCESS_PRIVATE.toString(),
			elgg.QuasiAccess.globalAcls.ACCESS_PUBLIC.toString(),
			elgg.QuasiAccess.globalAcls.ACCESS_LOGGED_IN.toString()];
	};

	QuasiAccess.prototype = {
		constructor: QuasiAccess,
		init: function () {
			var self = this;
			if (!self.$input.length) {
				return;
			}
			self.$input.find('option').each(function() {
				if (self.isExclusiveAcl($(this).attr('value'))) {
					$(this).attr('data-exclusive', true);
				}
			});
			self.$input.on('chosen:ready', self.onChosenReady.bind(self));
			self.$input.on('change', self.onChange.bind(self));
			self.$input.chosen(this.config);
			return self;
		},
		onChosenReady: function () {
			this.$input.trigger('change');
		},
		onChange: function (e, params) {
			var self = this,
				val = self.$input.val();
			
			if (!val || val.length === 0) {
				self.$input.find('option').prop('disabled', false);
			} else if (val.filter(self.isExclusiveAcl, this).length) {
				self.$input.find('option').prop('disabled', true);
			} else {
				self.$input.find('option[data-exclusive]').prop('disabled', true);
			}
			self.$input.trigger('chosen:updated');
		},
		isExclusiveAcl: function (id) {
			return this.exclusiveAcls.indexOf(id) !== -1;
		},
		disableOptions: function (values) {
			var self = this;
			self.$input.find('option').each(function () {
				$(this).prop('disabled', false);
				if (values.length && values.indexOf($(this).attr('value')) !== -1) {
					$(this).prop('disabled', true);
				}
			});
		},
	};

	return QuasiAccess;
});


