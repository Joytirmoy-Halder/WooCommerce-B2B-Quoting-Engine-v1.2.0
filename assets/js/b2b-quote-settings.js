/**
 * Live button preview for the B2B Quoting settings tab.
 *
 * 1.2.0 polled every field with setInterval(updatePreview, 250) for the whole
 * time the page was open, and separately rebuilt the settings screen into fake
 * tabs by hiding every h2 inside #mainform. WooCommerce now renders the sections
 * natively, so this file only has to mirror the field values into the preview.
 *
 * @package WooB2BQuotingEngine
 */

(function ($) {
	'use strict';

	var FIELDS = {
		primary: '#b2b_quote_color_primary',
		hover: '#b2b_quote_color_secondary',
		text: '#b2b_quote_text_color',
		textHover: '#b2b_quote_text_color_hover',
		borderColor: '#b2b_quote_border_color',
		borderWidth: '#b2b_quote_border_width',
		borderRadius: '#b2b_quote_border_radius',
		padding: '#b2b_quote_padding',
		fontSize: '#b2b_quote_font_size',
		fontWeight: '#b2b_quote_font_weight',
		label: '#b2b_quote_btn_text'
	};

	var PADDING_PATTERN = /^(?:\d{1,3}(?:\.\d{1,2})?(?:px|em|rem|%)?)(?: \d{1,3}(?:\.\d{1,2})?(?:px|em|rem|%)?){0,3}$/;

	function value(selector, fallback) {
		var $field = $(selector);

		if (!$field.length) {
			return fallback;
		}

		var raw = $.trim(String($field.val() || ''));

		return '' === raw ? fallback : raw;
	}

	function px(selector, fallback) {
		var raw = parseInt(value(selector, fallback), 10);

		return (isNaN(raw) ? fallback : Math.max(0, raw)) + 'px';
	}

	/** Mirrors B2B_Quote_Settings::sanitize_css_padding(). */
	function padding() {
		var raw = value(FIELDS.padding, '10px 20px').replace(/\s+/g, ' ');

		return PADDING_PATTERN.test(raw) ? raw : '10px 20px';
	}

	function updatePreview() {
		var $preview = $('#b2b_preview_btn');

		if (!$preview.length) {
			return;
		}

		var label = value(FIELDS.label, '');

		if (label) {
			$preview.text(label);
		}

		$preview.data('b2bColors', {
			idle: value(FIELDS.primary, '#007cba'),
			hover: value(FIELDS.hover, '#005a8c'),
			text: value(FIELDS.text, '#ffffff'),
			textHover: value(FIELDS.textHover, '#ffffff')
		});

		$preview.css({
			'background-color': value(FIELDS.primary, '#007cba'),
			color: value(FIELDS.text, '#ffffff'),
			'border-style': 'solid',
			'border-color': value(FIELDS.borderColor, '#005a8c'),
			'border-width': px(FIELDS.borderWidth, 0),
			'border-radius': px(FIELDS.borderRadius, 4),
			padding: padding(),
			'font-size': px(FIELDS.fontSize, 16),
			'font-weight': value(FIELDS.fontWeight, '700')
		});
	}

	$(function () {
		if (!$('#b2b_preview_btn').length) {
			return;
		}

		var selectors = $.map(FIELDS, function (selector) {
			return selector;
		}).join(', ');

		// Real events instead of a permanent 250ms timer. irischange fires while
		// dragging inside the WordPress colour picker.
		$(document).on('change input irischange', selectors, updatePreview);

		$('#b2b_preview_btn')
			.on('mouseenter focus', function () {
				var colors = $(this).data('b2bColors') || {};

				$(this).css({ 'background-color': colors.hover, color: colors.textHover });
			})
			.on('mouseleave blur', function () {
				var colors = $(this).data('b2bColors') || {};

				$(this).css({ 'background-color': colors.idle, color: colors.text });
			});

		updatePreview();
	});
})(jQuery);
