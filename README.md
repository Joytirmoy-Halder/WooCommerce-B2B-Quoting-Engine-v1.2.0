# WooCommerce B2B Quoting Engine

Replace *Add to cart* with *Request a quote* on all or part of a WooCommerce
catalogue, negotiate the price from the WordPress admin, and send the customer a
one-click approval link that loads the agreed prices straight into checkout.

Built for wholesale, trade and made-to-order stores where the price depends on
volume and specification rather than a fixed list price.

- **Version:** 1.3.0
- **Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+
- **License:** GPL-2.0-or-later

---

## What it does

1. **Collect.** Quoteable products show a quote button instead of add-to-cart.
   Customers build a request, set quantities, and submit their contact details
   and technical requirements.
2. **Negotiate.** Requests land in **WooCommerce > Quote Requests**. You set a
   per-line agreed unit price, add notes, and move the request through its
   status.
3. **Approve.** Sending a counter-offer emails the customer a single-use
   approval link. Opening it loads the quoted items into their cart at the
   agreed prices and sends them to checkout.
4. **Fulfil.** The resulting order is linked back to the quote in both
   directions, so the order screen and the CRM always agree.

Optionally, customers can also start an order over WhatsApp, Messenger,
Telegram, Viber, Skype or LINE, with the product name, link and their message
prepared for them.

---

## Installation

1. Download or clone this repository into `wp-content/plugins/`.
2. Activate **WooCommerce B2B Quoting Engine** in **Plugins**.
3. On activation the plugin creates its database table and a **Quote Request
   Cart** page containing the `[b2b_quote_cart]` shortcode.

WooCommerce must be active. If it is not, the plugin stays dormant and shows an
admin notice instead of causing a fatal error.

### Composer (development)

```bash
composer install
composer run lint     # PHP syntax check on every file
composer run phpcs    # WordPress Coding Standards
```

---

## Configuration

Everything lives under **WooCommerce > Settings > B2B Quoting**.

### General

| Setting | Purpose |
| --- | --- |
| Enable for all products | Quote every product in the catalogue. Ignores the targeting below. |
| Quoteable categories | Target categories. Child categories are matched automatically. |
| Quoteable products | Target individual products. Variable products cover their variations. |
| Button label | Text on the quote button. |
| Notification email | Where new requests are sent. Defaults to the site admin email. |
| Floating cart badge | Show the floating quote counter on the storefront. |
| Hide the quantity field | Off by default. Turning it on forces every line to quantity 1. |
| Remove data on uninstall | Delete the table, settings and page when the plugin is deleted. |

### Button design

Colours, border, padding, font size and weight, with a live preview. Values are
published as CSS custom properties on `:root`, so a theme can override any of
them:

```css
:root {
	--b2b-quote-primary: #0d5c63;
	--b2b-quote-border-radius: 999px;
}
```

### Direct message ordering

One field per platform. Leave a field blank to hide it. WhatsApp and Viber
expect a full international number; the rest expect a username.

WhatsApp and LINE receive the composed message through the URL. The other
platforms have no such parameter, so the message is copied to the clipboard and
the conversation opens beside it.

---

## Shortcode

```
[b2b_quote_cart]
```

Renders the quote request cart and the submission form. The page holding it is
excluded from full-page caching automatically.

---

## Quote statuses

| Key | Label |
| --- | --- |
| `pending` | Pending review |
| `reviewing` | Under review |
| `waiting_approval` | Waiting for approval |
| `accepted` | Accepted by customer |
| `completed` | Completed |
| `declined` | Declined |
| `cancelled` | Cancelled |

An approval link is only valid while the quote is `waiting_approval`. It is
consumed on first use, and generating a new one invalidates the previous link.

---

## Developer reference

### Actions

| Hook | Fired when |
| --- | --- |
| `b2b_quote_loaded` | All components are constructed. Receives the engine instance. |
| `b2b_quote_item_added` | A product is added to a quote session. |
| `b2b_quote_request_created` | A request is saved. Receives the quote ID. |
| `b2b_quote_updated` | A request is saved from the CRM. Receives ID and status. |
| `b2b_quote_approved` | A customer consumes an approval link. Receives the quote ID. |

### Filters

| Hook | Purpose |
| --- | --- |
| `b2b_quote_is_product_quoteable` | Override eligibility per product. |
| `b2b_quote_social_platforms` | Add, remove or restyle messaging platforms. |
| `b2b_quote_admin_email_recipient` | Change the notification recipient. |
| `b2b_quote_settings_fields` | Add fields to any settings section. |

```php
// Quote anything over 500 in stock, whatever the settings say.
add_filter( 'b2b_quote_is_product_quoteable', function ( $quoteable, $product ) {
	return $quoteable || $product->get_stock_quantity() > 500;
}, 10, 2 );
```

### AJAX actions

All use the `b2b-quote-nonce` action in a `security` field, and all return a
fresh nonce so the endpoints keep working behind a full-page cache.

`b2b_add_to_quote`, `b2b_update_quote_item`, `b2b_remove_from_quote`,
`b2b_get_quote_count`, `b2b_submit_quote`.

### Database

`{$wpdb->prefix}b2b_quote_requests`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | Primary key. |
| `client_name` | varchar(255) | |
| `client_email` | varchar(255) | Indexed. |
| `client_company` | varchar(255) | |
| `technical_reqs` | text | Nullable. |
| `quote_data` | longtext | JSON line items. |
| `status` | varchar(32) | Indexed. |
| `negotiation_notes` | text | Nullable. |
| `approval_token` | varchar(64) | Indexed. Cleared once used. |
| `order_id` | bigint unsigned | `0` until checkout completes. |
| `created_at` | datetime | GMT. Indexed. |
| `updated_at` | datetime | GMT. |

Order and line-item meta: `_b2b_quote_id`, `_b2b_quote_price`.

### Order meta and HPOS

High-Performance Order Storage and cart/checkout blocks are both declared
compatible. Order data is written through the CRUD API, never with direct post
meta calls.

---

## Repository layout

```
woo-b2b-quoting-engine.php   Bootstrap, constants, activation
uninstall.php                Optional data removal
includes/                    One class per responsibility
assets/css, assets/js        Enqueued front-end and admin assets
languages/                   Translation template
docs/AUDIT.md                Full 1.2.0 audit and remediation record
.github/workflows/ci.yml     Lint and coding standards
```

---

## Security notes

- Every AJAX endpoint verifies a nonce; the public submission endpoint also has
  a honeypot field and a per-visitor throttle.
- Every admin write checks `manage_woocommerce` and verifies its nonce.
- Statuses are validated against a whitelist before they reach the database.
- All database access uses `$wpdb->prepare()`, with column and ordering
  whitelists.
- Design settings are validated with `sanitize_hex_color()`, `absint()` and a
  strict pattern before being emitted as CSS.

Found something? Please open an issue rather than a public pull request.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Version 1.3.0 is a security and correctness
release; [docs/AUDIT.md](docs/AUDIT.md) records every finding and its fix.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
