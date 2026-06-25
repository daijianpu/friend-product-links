(function () {
	'use strict';

	function initCopyFields() {
		document.querySelectorAll('.fpl-copy-field').forEach(function (field) {
			field.addEventListener('click', function () {
				copyField(field);
			});
		});

		document.querySelectorAll('.fpl-copy-button').forEach(function (button) {
			button.addEventListener('click', function () {
				var field = button.parentNode.querySelector('.fpl-copy-field');
				if (field) {
					copyField(field);
					showCopied(button);
				}
			});
		});
	}

	function copyField(field) {
		field.select();
		if (navigator.clipboard) {
			navigator.clipboard.writeText(field.value).catch(function () {
				tryFallbackCopy(field);
			});
		} else {
			tryFallbackCopy(field);
		}
	}

	function tryFallbackCopy(field) {
		try {
			field.select();
			document.execCommand('copy');
		} catch (e) {
			// Clipboard not available.
		}
	}

	function showCopied(button) {
		var oldText = button.textContent;
		button.textContent = 'Copied!';
		window.setTimeout(function () {
			button.textContent = oldText;
		}, 1200);
	}

	function initConfirmations() {
		document.querySelectorAll('form[data-fpl-confirm]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if (!window.confirm(form.getAttribute('data-fpl-confirm'))) {
					event.preventDefault();
				}
			});
		});
	}

	function getSelectedProductValues(select) {
		var values = [];

		if (window.jQuery) {
			var jqValues = window.jQuery(select).val();
			if (Array.isArray(jqValues)) {
				values = jqValues;
			} else if (typeof jqValues === 'string' && jqValues.length) {
				values = jqValues.split(/[,\s]+/);
			}
		}

		if (!values.length && select.selectedOptions) {
			values = Array.prototype.slice.call(select.selectedOptions).map(function (option) {
				return option.value;
			});
		}

		return values.filter(function (value, index, list) {
			return value && list.indexOf(value) === index;
		});
	}

	function getSelectMax(select) {
		var max = parseInt(select.getAttribute('data-max'), 10);
		return isNaN(max) ? 9 : max;
	}

	function getSelectMin(select) {
		var min = parseInt(select.getAttribute('data-min'), 10);
		return isNaN(min) ? 3 : min;
	}

	function initProductPicker() {
		if (window.jQuery) {
			window.jQuery(document.body).trigger('wc-enhanced-select-init');
		}

		document.querySelectorAll('.fpl-product-select').forEach(function (select) {
			var max = getSelectMax(select);

			select.addEventListener('change', function () {
				var selectedValues = getSelectedProductValues(select);
				if (selectedValues.length <= max) {
					return;
				}

				Array.prototype.slice.call(select.options).forEach(function (option) {
					option.selected = selectedValues.slice(0, max).indexOf(option.value) !== -1;
				});

				if (window.jQuery) {
					window.jQuery(select).val(selectedValues.slice(0, max)).trigger('change.select2');
				}

				window.alert('Select up to ' + max + ' products.');
			});
		});
	}

	function initProductFormValidation() {
		document.querySelectorAll('form[data-fpl-product-form="1"]').forEach(function (form) {
			function shouldRequireMinimumProducts(form) {
				var controllerName = form.getAttribute('data-fpl-products-required-when-enabled');
				if (!controllerName) {
					return true;
				}
				var controller = form.querySelector('[name="' + controllerName + '"]');
				if (!controller) {
					return true;
				}
				if (controller.type === 'checkbox' || controller.type === 'radio') {
					return controller.checked;
				}
				return !!controller.value;
			}

			form.addEventListener('submit', function (event) {
				var select = form.querySelector('.fpl-product-select');
				var count = select ? getSelectedProductValues(select).length : 0;
				var min = select ? getSelectMin(select) : 3;
				var max = select ? getSelectMax(select) : 9;
				var requireMinimum = shouldRequireMinimumProducts(form);

				if (requireMinimum && count < min) {
					event.preventDefault();
					window.alert('Select at least ' + min + ' public WooCommerce products.');
					return;
				}

				if (count > max) {
					event.preventDefault();
					window.alert('Select up to ' + max + ' products.');
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initCopyFields();
			initConfirmations();
			initProductPicker();
			initProductFormValidation();
		});
	} else {
		initCopyFields();
		initConfirmations();
		initProductPicker();
		initProductFormValidation();
	}
}());