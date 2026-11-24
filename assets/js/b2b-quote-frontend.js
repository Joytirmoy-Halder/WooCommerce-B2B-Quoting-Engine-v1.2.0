/**
 * Storefront behaviour for the B2B Quoting Engine.
 *
 * @package WooB2BQuotingEngine
 */

(function ($) {
	'use strict';

	var params = window.b2bQuoteParams;

	// 1.2.0 assumed the localized object always existed and threw a
	// ReferenceError on any page where the script loaded without it.
	if (!params || !params.ajaxUrl) {
		return;
	}

	var i18n = params.i18n || {};
	var nonce = params.nonce;
	var qtyTimer = null;
	var $composer = null;

	var composerState = {
		platform: '',
		target: '',
		name: '',
		link: ''
	};

	// Only these two platforms accept prefilled message text in a URL.
	var SUPPORTS_PREFILL = {
		whatsapp: true,
		line: true
	};

	function t(key, fallback) {
		return i18n[key] || fallback || '';
	}

	function digitsOnly(value) {
		return String(value || '').replace(/[^0-9]/g, '');
	}

	function handleOnly(value) {
		return String(value || '').replace(/^@+/, '').replace(/\s+/g, '');
	}

	/**
	 * POST to admin-ajax and normalise the result.
	 *
	 * The nonce is refreshed from every response so the next request still
	 * validates even when this page came from a full-page cache.
	 */
	function request(action, data) {
		var payload = $.extend({ action: action, security: nonce }, data || {});

		return $.ajax({
			url: params.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: payload
		}).then(function (response) {
			var body = (response && response.data) || {};

			if (body.nonce) {
				nonce = body.nonce;
				params.nonce = body.nonce;
			}

			if (!response || !response.success) {
				return $.Deferred().reject(body.message || t('genericError')).promise();
			}

			return body;
		}, function () {
			return $.Deferred().reject(t('networkError') || t('genericError')).promise();
		});
	}

	/**
	 * Show a message. Text is always set with .text() so a server string can
	 * never be interpreted as markup.
	 */
	function notify(message, type) {
		if (!message) {
			return;
		}

		var cssClass = 'b2b-quote-notice is-' + (type || 'info');
		var $target = $('#b2b-quote-notices');

		if ($target.length) {
			$target.empty().append($('<div/>', { 'class': cssClass }).text(message));

			if ($target[0].scrollIntoView) {
				$target[0].scrollIntoView({ block: 'nearest' });
			}

			return;
		}

		var $toast = $('<div/>', { 'class': cssClass })
			.css({
				position: 'fixed',
				right: '30px',
				bottom: '90px',
				zIndex: 100001,
				maxWidth: '320px'
			})
			.text(message)
			.appendTo('body');

		window.setTimeout(function () {
			$toast.fadeOut(200, function () {
				$toast.remove();
			});
		}, 4000);
	}

	function renderCount(data) {
		var count = parseInt(data && data.count, 10);
		var $cart = $('#b2b-floating-cart');

		if (isNaN(count) || !$cart.length) {
			return;
		}

		$cart.find('.b2b-cart-count').text(count);

		if (count > 0) {
			$cart.removeAttr('hidden');
		} else {
			$cart.attr('hidden', 'hidden');
		}
	}

	function refreshCount() {
		if (!$('#b2b-floating-cart').length) {
			return;
		}

		request('b2b_get_quote_count').done(renderCount);
	}

	/**
	 * Flag the add-to-cart forms we control, so CSS can hide the native
	 * purchase controls without an inline !important block per product.
	 */
	function markQuoteForms() {
		$('form.cart').each(function () {
			var $form = $(this);

			if (!$form.find('.b2b-add-to-quote').length) {
				return;
			}

			$form.addClass('b2b-quote-active');

			if (params.hideQty) {
				$form.addClass('b2b-hide-qty');
			}
		});
	}

	/* --------------------------------------------------------------------- *
	 * Add to quote
	 * --------------------------------------------------------------------- */

	$(document).on('click', '.b2b-add-to-quote', function (event) {
		event.preventDefault();

		var $button = $(this);

		if ($button.hasClass('is-loading')) {
			return;
		}

		var $form = $button.closest('form.cart');
		var productId = parseInt($button.data('product_id'), 10);
		var quantity = 1;

		// Quantity is visible again in 1.3.0, so read whatever the customer set.
		var $qty = $form.find('input.qty').first();

		if ($qty.length) {
			quantity = parseInt($qty.val(), 10) || 1;
		}

		// Prefer the chosen variation over the parent product.
		var $variation = $form.find('input[name="variation_id"]').first();

		if ($variation.length) {
			var variationId = parseInt($variation.val(), 10);

			if (variationId > 0) {
				productId = variationId;
			} else {
				var wcVariation = window.wc_add_to_cart_variation_params;

				notify(
					(wcVariation && wcVariation.i18n_make_a_selection_text) || t('genericError'),
					'error'
				);

				return;
			}
		}

		if (!productId) {
			return;
		}

		var original = $button.text();

		$button.addClass('is-loading').prop('disabled', true).text(t('adding'));

		request('b2b_add_to_quote', { product_id: productId, quantity: quantity })
			.done(function (data) {
				renderCount(data);
				notify(data.message || t('added'), 'success');
			})
			.fail(function (message) {
				notify(message, 'error');
			})
			.always(function () {
				$button.removeClass('is-loading').prop('disabled', false).text(original);
			});
	});

	/* --------------------------------------------------------------------- *
	 * Quote cart page
	 * --------------------------------------------------------------------- */

	function updateItem($input) {
		var $row = $input.closest('.b2b-quote-item');
		var productId = parseInt($row.data('product_id'), 10);
		var quantity = parseInt($input.val(), 10);

		if (!productId) {
			return;
		}

		if (isNaN(quantity) || quantity < 1) {
			quantity = 1;
			$input.val(quantity);
		}

		$row.addClass('is-busy');

		request('b2b_update_quote_item', { product_id: productId, quantity: quantity })
			.done(renderCount)
			.fail(function (message) {
				notify(message, 'error');
			})
			.always(function () {
				$row.removeClass('is-busy');
			});
	}

	$(document).on('change input', 'input.b2b-quote-qty', function () {
		var $input = $(this);

		window.clearTimeout(qtyTimer);

		qtyTimer = window.setTimeout(function () {
			updateItem($input);
		}, 500);
	});

	$(document).on('click', '.b2b-remove-item', function (event) {
		event.preventDefault();

		var $row = $(this).closest('.b2b-quote-item');
		var productId = parseInt($row.data('product_id'), 10);

		if (!productId || !window.confirm(t('confirmEmpty'))) {
			return;
		}

		$row.addClass('is-busy');

		request('b2b_remove_from_quote', { product_id: productId })
			.done(function (data) {
				$row.remove();
				renderCount(data);

				// Let the server render its own localized empty state.
				if (!$('.b2b-quote-item').length) {
					window.location.reload();
				}
			})
			.fail(function (message) {
				$row.removeClass('is-busy');
				notify(message, 'error');
			});
	});

	$(document).on('submit', '#b2b-quote-submit-form', function (event) {
		event.preventDefault();

		var $form = $(this);

		if ($form.hasClass('is-submitting')) {
			return;
		}

		var $button = $form.find('button[type="submit"]').first();
		var original = $button.text();
		var data = {};

		$.each($form.serializeArray(), function (index, field) {
			data[field.name] = field.value;
		});

		$form.addClass('is-submitting');
		$button.prop('disabled', true).text(t('submitting'));

		request('b2b_submit_quote', data)
			.done(function (body) {
				renderCount({ count: 0 });

				var $wrapper = $form.closest('.b2b-quote-cart-wrapper');
				var $success = $('<div/>', { 'class': 'b2b-quote-notice is-success' }).text(body.message || '');

				if ($wrapper.length) {
					$wrapper.empty().append($success);
					$wrapper[0].scrollIntoView({ block: 'start' });
				} else {
					notify(body.message, 'success');
				}
			})
			.fail(function (message) {
				notify(message, 'error');
				$form.removeClass('is-submitting');
				$button.prop('disabled', false).text(original);
			});
	});

	/* --------------------------------------------------------------------- *
	 * Direct message composer
	 * --------------------------------------------------------------------- */

	function composer() {
		if (!$composer || !$composer.length) {
			$composer = $('#b2b-social-composer');
		}

		return $composer;
	}

	function closeComposer() {
		var $modal = composer();

		if ($modal.length) {
			$modal.attr('hidden', 'hidden');
		}
	}

	function buildChatUrl(platform, target, message) {
		var encoded = window.encodeURIComponent(message);

		switch (platform) {
			case 'whatsapp':
				return 'https://wa.me/' + digitsOnly(target) + '?text=' + encoded;
			case 'line':
				return 'https://line.me/R/msg/text/?' + encoded;
			case 'messenger':
				// 1.2.0 used insecure http://m.me/ here.
				return 'https://m.me/' + handleOnly(target);
			case 'telegram':
				return 'https://t.me/' + handleOnly(target);
			case 'viber':
				return 'viber://chat?number=' + window.encodeURIComponent('+' + digitsOnly(target));
			case 'skype':
				return 'skype:' + handleOnly(target) + '?chat';
			default:
				return '';
		}
	}

	function copyToClipboard(text) {
		var deferred = $.Deferred();
		var clipboard = window.navigator && window.navigator.clipboard;

		if (clipboard && clipboard.writeText) {
			clipboard.writeText(text).then(function () {
				deferred.resolve();
			}, function () {
				deferred.reject();
			});

			return deferred.promise();
		}

		var $temp = $('<textarea/>')
			.val(text)
			.css({ position: 'fixed', top: '-1000px', opacity: 0 })
			.appendTo('body');

		var copied = false;

		try {
			$temp[0].select();
			copied = window.document.execCommand('copy');
		} catch (error) {
			copied = false;
		}

		$temp.remove();

		if (copied) {
			deferred.resolve();
		} else {
			deferred.reject();
		}

		return deferred.promise();
	}

	$(document).on('click', '.trigger-social-composer', function (event) {
		event.preventDefault();

		var $trigger = $(this);
		var $modal = composer();

		if (!$modal.length) {
			return;
		}

		composerState = {
			platform: String($trigger.data('platform') || ''),
			target: String($trigger.data('target') || ''),
			name: String($trigger.data('pname') || ''),
			link: String($trigger.data('plink') || '')
		};

		$modal.find('.b2b-social-composer__product').text(composerState.name);
		$modal.find('.b2b-social-composer__hint').text('');
		$modal.removeAttr('hidden');
		$modal.find('#b2b-social-composer-message').val('').trigger('focus');
	});

	$(document).on('click', '[data-b2b-close]', function (event) {
		event.preventDefault();
		closeComposer();
	});

	$(document).on('keyup', function (event) {
		if ('Escape' === event.key || 27 === event.keyCode) {
			closeComposer();
		}
	});

	$(document).on('click', '.b2b-social-confirm-btn', function (event) {
		event.preventDefault();

		var $modal = composer();
		var $hint = $modal.find('.b2b-social-composer__hint');
		var note = $.trim($modal.find('#b2b-social-composer-message').val() || '');

		if (!note) {
			$hint.text(t('emptyMessage'));

			return;
		}

		var message = composerState.name + '\n' + composerState.link + '\n\n' + note;
		var url = buildChatUrl(composerState.platform, composerState.target, message);

		if (!url) {
			$hint.text(t('genericError'));

			return;
		}

		if (SUPPORTS_PREFILL[composerState.platform]) {
			closeComposer();
			window.open(url, '_blank', 'noopener');

			return;
		}

		// These platforms cannot accept prefilled text, so hand the customer the
		// message on the clipboard and open the conversation alongside it.
		copyToClipboard(message)
			.done(function () {
				$hint.text(t('copied'));
			})
			.fail(function () {
				$hint.text(t('copyFailed'));
			})
			.always(function () {
				window.open(url, '_blank', 'noopener');
			});
	});

	/* --------------------------------------------------------------------- *
	 * Init
	 * --------------------------------------------------------------------- */

	$(function () {
		markQuoteForms();
		refreshCount();

		$(document.body).on('wc_variation_form updated_wc_div', markQuoteForms);
	});
})(jQuery);
