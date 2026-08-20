# Code audit: 1.2.0 to 1.3.0

A full read of all 14 files in the 1.2.0 release, covering correctness, security,
WordPress and WooCommerce API usage, internationalisation, and repository
hygiene.

**36 findings.** 8 critical, 14 high, 9 medium, 5 packaging. All are fixed on
this branch.

| Severity | Count | Summary |
| --- | --- | --- |
| Critical | 8 | Free checkout, dead approval flow, table creation failure, HTML and CSS injection, unusable quantity field |
| High | 14 | Unvalidated admin and AJAX input, no rate limiting, broken targeting, missing textdomain and uninstall |
| Medium | 9 | Storefront script robustness, insecure link, polling timer, missing UI |
| Packaging | 5 | Committed build artefact, missing license and metadata, dead CSS |

The headline problem: **the negotiation workflow, which is the reason the plugin
exists, could never have worked.** Three independent defects each broke it on
their own. That strongly suggests it was never executed end to end.

---

## Critical

### C1. Customers could check out for free

`class-b2b-quote-checkout.php`

```php
$price = isset( $item['negotiated_price'] ) ? $item['negotiated_price'] : 0;
// ...
$cart_item['data']->set_price( 0 );
```

Nothing anywhere in 1.2.0 ever wrote `negotiated_price`. The CRM read
`$_POST['negotiated_prices']` and discarded it without saving. So the fallback
was not an edge case, it was the only path: every line was priced at `0`, and
any customer who reached checkout through an approval link received the goods for
nothing.

**Fixed.** Prices are saved per line in the CRM. At checkout a price override is
applied *only* when a negotiated price was explicitly stored, is numeric, and is
greater than zero. Otherwise the product keeps its catalogue price. The override
is applied in `woocommerce_before_calculate_totals` against the cart item's own
data object, and stored as `_b2b_quote_price` on the line item for the audit
trail.

### C2. The approval link always failed

`class-b2b-quote-checkout.php` looked the quote up with:

```php
WHERE approval_token = %s AND status = 'negotiating'
```

No code path in the plugin ever set a status of `negotiating`. The only statuses
written were `pending` and whatever the CRM dropdown offered. The query could
never match, so every approval link ended at `wp_die( 'Invalid quote' )`.

**Fixed.** Statuses are now declared as constants on `B2B_Quote_DB` and the
lookup uses `STATUS_WAITING_APPROVAL`, the status the CRM actually sets when an
offer is sent. No more string literals scattered across files.

### C3. Counter-offers were never sent

`send_client_counter_offer()` existed in `class-b2b-quote-emails.php` and was
never called from anywhere. `approval_token` was never generated and never
written. The column existed; nothing populated it.

**Fixed.** Saving a quote with *Send counter-offer* ticked generates a token via
`wp_generate_password( 48, false, false )`, stores it, and emails the customer
the approval URL. The token is single-use, cleared the moment it is consumed, and
replaced whenever a new offer is sent, so an old link cannot be replayed.

### C4. The database table could not be created

`class-b2b-quote-db.php`

```sql
technical_reqs text DEFAULT '' NOT NULL,
negotiation_notes text DEFAULT '' NOT NULL, -- JSON formatted notes
```

Two separate faults:

1. MySQL does not permit a `DEFAULT` value on a `TEXT` column, and errors with
   *1101: BLOB, TEXT, GEOMETRY or JSON column can't have a default value*.
2. `dbDelta()` parses the schema string line by line; the inline `--` comment
   corrupts that parsing.

On a strict installation the table was never created, and every subsequent query
failed silently because nothing checked the result.

**Fixed.** Both columns are nullable with no default. The schema is `dbDelta()`
safe: one column per line, two spaces before `KEY`, no comments, no trailing
comma. Added `order_id`, an `updated_at` column, and indexes on `status`,
`client_email`, `approval_token` and `created_at`. Schema changes are versioned
by `B2B_QUOTE_DB_VERSION` so upgrades run once.

### C5. HTML injection into outgoing email

`class-b2b-quote-emails.php` interpolated `{$client_name}`,
`{$client_email}` and `{$quote->client_name}` directly into HTML email bodies. A
name submitted through the public form was rendered as markup in the shop
owner's inbox, and again in the customer-facing email.

**Fixed.** Every interpolated value passes through `esc_html()`, and the
recipient header through `sanitize_email()`. Headers are passed as an array
rather than concatenated.

### C6. CSS injection through the settings screen

`class-b2b-quote-settings.php` echoed raw option values into a `<style>` block.
The padding field was free text, so a value containing a closing `</style>` tag
plus a `<script>` tag was injected into every page of the storefront. Colour
fields were equally unvalidated.

**Fixed.** Colours pass through `sanitize_hex_color()` with a fallback, numeric
fields through `absint()`, and padding is matched against a strict pattern that
allows one to four numeric values with an optional `px`, `em`, `rem` or `%` unit,
falling back to the default on any mismatch. The result is published as CSS
custom properties from an enqueued stylesheet rather than an inline block.

### C7. Bulk quantities could not be entered

The frontend printed:

```css
form.cart .quantity.quantity.quantity { display: none !important; }
```

while the storefront script read the value of that same hidden input:

```js
var quantity = $form.find( 'input.qty' ).val();
```

So the quantity was always `1`. In a plugin whose purpose is volume quoting,
there was no way for a customer to request a volume.

**Fixed.** The quantity input is visible by default, the script reads it
correctly, and quantities are editable per line in the quote cart with a
debounced update. Hiding the field is now an explicit opt-in setting, and when it
is enabled the plugin no longer pretends to read a quantity.

### C8. The plugin recreated its page on every request

`ensure_quote_page_exists()` ran on `init`. Every single front-end request did a
`get_option()` plus a `get_post()`, and could call `wp_insert_post()` from an
uncached front-end context. A trashed page was treated as valid, so once trashed
the shortcode target was permanently broken.

**Fixed.** Moved to activation only, as a static method registered with
`register_activation_hook`. It checks `post_status` and recreates the page if it
was trashed or deleted.

---

## High

### H1. Unvalidated admin form handling

`class-b2b-quote-crm.php` read `$_POST['quote_id']`, `$_POST['status']` and
`$_POST['negotiation_notes']` with no `isset()` guards, producing notices on any
partial post. The status was written to the database with no whitelist, so any
string could be stored, including one that would silently exclude the quote from
the checkout lookup. The nonce was neither unslashed nor sanitised before
`wp_verify_nonce()`.

**Fixed.** `admin_post_b2b_quote_update` handler with an explicit
`current_user_can( 'manage_woocommerce' )` check, `check_admin_referer()`, and a
status validated against the whitelist. All input is unslashed and sanitised.

### H2. Unbounded query

`SELECT * FROM $table ORDER BY created_at DESC` with no `LIMIT`. On a store with
a few thousand requests this loads the entire table, with every JSON blob, into
memory to render one screen.

**Fixed.** `get_quotes()` takes status, search, ordering, limit and offset, with
whitelisted `ORDER BY` columns and directions, and returns a total for
pagination. The CRM list is paginated at 20 per page with a status filter and
search.

### H3. `json_decode()` results used without checking

Both the CRM and checkout did `foreach ( json_decode( $quote->quote_data, true )
as $item )`. Malformed or truncated data returns `null` and produces a PHP
warning, and in the checkout path that meant a fatal on a customer-facing page.

**Fixed.** A single `decode_items()` helper validates the result is an array,
coerces each row, and returns an empty array otherwise. Every consumer uses it.

### H4. Any integer could be added to a quote

`class-b2b-quote-ajax.php` did `absint( $_POST['product_id'] )` and stored it,
with no check that the ID resolved to a product, that the product was published,
or that it was quoteable at all. Draft and private products could be enumerated,
and the targeting settings could be bypassed entirely by posting a product ID
directly.

**Fixed.** IDs are resolved with `wc_get_product()`, rejected unless the status
is `publish`, and checked against `B2B_Quote_Rules::is_quoteable()`.

### H5. Quantity accepted zero, and had no ceiling

`absint()` allows `0`, and repeated additions did `+=` with no cap, so a single
visitor could grow an unbounded session payload.

**Fixed.** Quantities are clamped to 1 to 999999, and a quote is capped at 100
distinct lines.

### H6. Fatal error when the session was unavailable

`isset( WC()->session )` throws a fatal error if `WC()` itself returns null, and
there was no `function_exists( 'WC' )` guard. `set_quote_session( null )` was
also used to clear the quote, so the next `foreach` iterated null.

**Fixed.** Guarded session access, and clearing now writes `array()`.

### H7. No abuse protection on a public endpoint

`wp_ajax_nopriv_b2b_submit_quote` wrote a row to the database and sent an email,
with nothing but a nonce, which is not a rate limit and is trivially obtainable
from a page load.

**Fixed.** Added a honeypot field, rendered off-screen, and a 20 second
transient-backed throttle keyed on the visitor. Both fail closed.

### H8. Category and product targeting did not work as described

`is_product_quoteable()` used `wc_get_product_term_ids( $id, 'product_cat' )`,
which returns only directly assigned terms. Selecting a parent category matched
nothing in its children, which is how anyone would expect to configure a
catalogue. Variation IDs were also compared against a list of parent product IDs,
so quoting never applied to a variation.

**Fixed.** New `B2B_Quote_Rules` class walks category ancestors with
`get_ancestors()`, and resolves a variation to its parent before matching. It is
the single source of truth, used by the frontend, the AJAX handlers and the
shortcode, and is filterable through `b2b_quote_is_product_quoteable`.

### H9. Duplicate element IDs on every archive page

`modify_loop_add_to_cart_button()` appended the entire social composer markup,
including its fixed IDs, once per product in the loop. A 24-product archive
emitted 24 elements with the same ID, which is invalid HTML and means
`document.getElementById` returns whichever one came first.

**Fixed.** The loop renders only a button. A single dialog is printed once in
`wp_footer`, and only when at least one trigger was actually rendered.

### H10. Caching was permanently defeated

```php
wp_enqueue_script( 'b2b-quote-frontend', $url, array( 'jquery' ), time(), true );
```

`time()` as the version string produces a unique URL on every page view, so the
browser, any CDN and any page cache must refetch the asset every time.

**Fixed.** All assets are versioned with `B2B_QUOTE_VERSION`.

### H11. No text domain loaded

Every string used `__()` with the `woo-b2b-quote` domain, but
`load_plugin_textdomain()` was never called and there was no `languages/`
directory or POT file. The plugin could not be translated at all.

**Fixed.** Text domain loaded on `init`, `Domain Path` header added, POT template
committed, and a `composer run make-pot` script provided.

### H12. No HPOS declaration

Without `FeaturesUtil::declare_compatibility()`, WooCommerce flags the plugin as
incompatible and stores running High-Performance Order Storage will not enable it.

**Fixed.** Both `custom_order_tables` and `cart_checkout_blocks` are declared,
and order data is written through the CRUD API rather than post meta.

### H13. No uninstall routine

Deleting the plugin left the table, all 30-odd options and the quote page behind
forever.

**Fixed.** `uninstall.php` guarded by `WP_UNINSTALL_PLUGIN`, gated on an opt-in
setting, with a multisite loop.

### H14. Missing plugin headers

No `Plugin URI`, `License`, `License URI`, `Domain Path`, `Requires at least`,
`Requires PHP`, `WC requires at least` or `WC tested up to`. WooCommerce shows an
*Untested* warning and WordPress cannot enforce version requirements.

**Fixed.** Full header block.

---

## Medium

### M1. No AJAX error handling

The storefront script passed only a success callback. Any 500, timeout or dropped
connection left the button disabled and reading *Adding...* forever, with no
indication anything had gone wrong.

**Fixed.** Every request has a failure path that restores the button and shows
the message.

### M2. Server strings concatenated into HTML

```js
$( '.notices' ).html( '<div class="notice">' + response.data.message + '</div>' );
```

**Fixed.** Nodes are built with `$( '<div/>' ).text( message )` throughout, so a
message can never be parsed as markup.

### M3. Duplicate variable declaration

`var cleanTarget` was declared twice in the same function scope.

**Fixed.** Removed, along with the wider rewrite into an IIFE with `'use
strict'`.

### M4. Insecure Messenger link

`'http://m.me/' + target`, over plain HTTP.

**Fixed.** `https://m.me/`.

### M5. Telegram messages were silently dropped

The code built `https://t.me/<user>?text=<message>`. Telegram ignores `?text=` on
a profile link, so the customer arrived at an empty conversation with no idea the
message had been lost. The same is true for Messenger, Viber and Skype.

**Fixed.** Only WhatsApp and LINE receive the message through the URL. For the
others the message is copied to the clipboard, with a `document.execCommand`
fallback where the async clipboard API is unavailable, and the customer is told
whether the copy succeeded before the conversation opens.

### M6. `alert()` for all feedback, and a reload after every action

**Fixed.** Inline `aria-live` notices, a toast fallback on product pages, and no
reload except when the last line is removed, where the server re-renders its own
localised empty state.

### M7. Nonce went stale behind a page cache

A cached page serves a nonce that may be hours old, and every AJAX call then
fails with `-1`.

**Fixed.** Every response returns a fresh nonce and the script swaps it in for
the next request. The quote cart page also sets `DONOTCACHEPAGE`.

### M8. Settings screen polled itself, and rebuilt the DOM

`setInterval( updatePreview, 250 )` ran for as long as the page was open. The
section navigation was faked by hiding every `h2` inside `#mainform` and keying
off a hardcoded `clickedIndex === 1`, which is what produced the fatal error the
1.2.0 README documented as a known issue.

**Fixed.** Native WooCommerce sections through `WC_Admin_Settings`, and a preview
bound to `change`, `input` and the colour picker's `irischange` event.

### M9. Missing workflow pieces

No quantity column in the quote cart, no confirmation email to the customer, and
no way to enter per-line pricing, which is what C1 depended on.

**Fixed.** All three added.

---

## Packaging

### P1. Build artefact committed

`woo-b2b-quoting-engine.zip`, 28KB, checked into the repository. It cannot be
reviewed in a diff, goes stale immediately, and bloats every clone.

**Fixed.** Deleted, and `*.zip` is in `.gitignore`. CI fails if an archive is
ever committed again.

### P2. Plugin nested in a version-named folder

Everything lived under `woo-b2b-quoting-engine-release_v1.2.0/`, so the
repository could not be cloned into `wp-content/plugins/` and used, and the
directory name would have to be renamed at every release.

**Fixed.** The plugin is at the repository root.

### P3. Missing repository metadata

No `LICENSE` despite the header claiming GPL, no `.gitignore`, no `readme.txt`,
no `CHANGELOG.md`, no `composer.json`, no `phpcs.xml`, no CI.

**Fixed.** All added.

### P4. CSS and JavaScript echoed from PHP

Large inline `<style>` and `<script>` blocks were printed from PHP, so they could
not be cached, minified, or overridden by a theme, and were the vector for C6.

**Fixed.** Everything moved to enqueued files. Dynamic values are passed as CSS
custom properties and via `wp_localize_script()`.

### P5. The stylesheet was dead code

`assets/css/b2b-quote-frontend.css` contained five rules, targeting
`.b2b-quote-shortcode-wrapper`, `.b2b-quote-cart-table th`,
`.b2b-quote-cart-table td.product-thumbnail img` and `.b2b-quote-form-container`.
Only one of those class names appeared anywhere in the rendered markup. The file
was enqueued on every page and styled nothing.

**Fixed.** Rewritten against the markup the plugin actually produces, with a
responsive quote cart, a dialog, and `prefers-reduced-motion` and print rules.

---

## Verification

No automated test suite exists yet, so this pass was verified by reading and by
the following manual plan. `php -l` passes on every file across PHP 7.4 to 8.3.

1. **Install.** Activate on a strict MySQL configuration and confirm the table is
   created with all 12 columns. This alone was impossible in 1.2.0 (C4).
2. **Targeting.** Assign a product to a child category, select the *parent* in
   settings, and confirm the quote button appears (H8).
3. **Quantity.** Add 250 of a product and confirm 250 arrives in the quote cart,
   the CRM, and the eventual order (C7).
4. **Variations.** Confirm the selected variation ID is quoted, not the parent.
5. **The money path.** Set an agreed price on one line, leave a second line
   blank, send the offer, open the link, and confirm the first line uses the
   agreed price and the second uses its catalogue price. Neither may be zero
   (C1).
6. **Token reuse.** Reload the approval URL and confirm it is rejected. Send a
   new offer and confirm the old link stays dead (C3).
7. **Injection.** Submit `<img src=x onerror=alert(1)>` as the company name and
   confirm it renders as text in both emails (C5). Save
   `10px}</style><script>alert(1)</script>` as the padding value and confirm it
   is rejected (C6).
8. **Privilege.** Post to `admin_post_b2b_quote_update` as a Subscriber and
   confirm it is refused (H1).
9. **Direct ID.** Post a draft product ID to `b2b_add_to_quote` and confirm it is
   refused (H4).
10. **Cache.** Serve the product page from a full-page cache, wait out the nonce
    lifetime, and confirm adding to a quote still works (M7).
11. **Failure.** Block admin-ajax in devtools and confirm the button recovers and
    reports an error (M1).

---

## Residual risks and recommendations

Out of scope for this pass, and worth planning:

- **No automated tests.** The defects in C1 to C3 would each have been caught by
  one integration test of the quote-to-order path. A WP-CLI plus PHPUnit harness
  covering the rules engine, the DB layer and the approval flow is the single
  highest-value follow-up.
- **Quote data is denormalised JSON.** Convenient, but it cannot be queried or
  reported on. A line-items table would allow real sales reporting.
- **Prices carry no tax or currency context.** An agreed price is stored as a
  bare decimal. Multi-currency stores need the currency recorded alongside it,
  and the tax treatment made explicit.
- **No expiry on quotes.** An accepted quote is valid forever. A validity window
  with an automatic expiry is standard in B2B quoting.
- **Approval links are unauthenticated by design.** A single-use token is
  reasonable, but anyone holding the URL can accept. Consider requiring a login
  or an email confirmation step for high-value quotes.
- **Emails bypass the WooCommerce email system.** Using `wp_mail()` directly
  means no template inheritance and no per-email settings in the admin. Porting
  these to `WC_Email` subclasses would make them customisable.
- **No CSV export or bulk actions** in the CRM.
