# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-08-20

Security, correctness and maintenance release following a full audit of 1.2.0.
Every finding is recorded in [docs/AUDIT.md](docs/AUDIT.md).

### Security

- **Free checkout was possible.** The approval flow fell back to a price of `0`
  whenever a line had no negotiated price, and then called `set_price( 0 )`. A
  customer reaching checkout through an approval link could obtain goods for
  nothing. A negotiated price is now only applied when one was explicitly saved,
  and is floored at zero.
- **Email HTML injection.** Customer names and email addresses were interpolated
  into HTML emails unescaped. All values are escaped.
- **CSS injection through settings.** Design options, including a free-text
  padding field, were written straight into a `<style>` block, allowing arbitrary
  CSS or a closing `</style>` tag to be injected site-wide. Values are now
  validated with `sanitize_hex_color()`, `absint()` and a strict pattern.
- **Unvalidated CRM input.** The quote status was written from `$_POST` with no
  whitelist and no `isset()` guards, and the nonce was neither unslashed nor
  sanitised. All input is validated and capability checks are enforced.
- **Unprotected public endpoint.** `b2b_submit_quote` is available to logged-out
  visitors and wrote to the database with no abuse protection. Added a honeypot
  field and a per-visitor throttle.
- **Unvalidated product IDs.** Any integer could be added to a quote, including
  drafts, private posts and non-quoteable products. IDs are now checked for
  existence, visibility and eligibility.
- Approval tokens are now 48 characters, single-use, cleared once consumed, and
  invalidated when a new offer is sent.

### Fixed

- **The plugin could not create its own table.** Two `TEXT` columns were declared
  `DEFAULT '' NOT NULL`, which MySQL rejects, and an inline `--` comment broke
  `dbDelta()` parsing. Installation failed on any strict configuration.
- **The approval link never worked.** The lookup required a status of
  `negotiating`, which no code path ever set, so every approval link returned
  *Invalid quote*. Corrected to the real status.
- **Counter-offers could not be sent.** No approval token was ever generated and
  `send_client_counter_offer()` was never called. The negotiation feature is now
  wired up end to end.
- **Bulk quantities could not be requested.** The quantity input was hidden with
  a CSS override while the script read that same input, so every line was fixed
  at 1 in a bulk quoting plugin. Quantity is visible by default, editable in the
  quote cart, and hiding it is now an explicit opt-in.
- **Assets were never cached.** `time()` was passed as the asset version,
  producing a unique URL on every page view.
- Category targeting now matches child categories, and product targeting matches
  variations of a selected variable product. Previously neither did.
- Archive pages no longer render a duplicate messaging widget, with duplicated
  element IDs, for every product in the loop.
- The quote page is no longer created on every front-end request, and a trashed
  page is no longer treated as valid.
- Quote sessions survive correctly for logged-out visitors, and a null
  WooCommerce session no longer causes a fatal error.
- `json_decode()` results are validated before iteration, so malformed data no
  longer produces PHP warnings in the CRM and checkout.
- The CRM list is paginated instead of selecting every row with no limit.
- Quantities are clamped to sane bounds and a quote is capped at 100 lines.
- AJAX failures surface an error instead of leaving the button in a permanent
  loading state.
- Messenger deep links use `https` rather than `http`.
- Telegram, Messenger, Viber and Skype messages are copied to the clipboard,
  since those platforms cannot accept prefilled text in a URL.
- Removed a duplicate variable declaration in the storefront script.

### Added

- Customer confirmation email on submission. 1.2.0 sent the customer nothing.
- Per-line agreed unit price and quantity editing in the CRM.
- Status filter, search and pagination in the CRM.
- `uninstall.php`, with an opt-in setting for removing data.
- High-Performance Order Storage and cart/checkout blocks compatibility
  declarations.
- `load_plugin_textdomain()` and a `languages/woo-b2b-quote.pot` template. Every
  user-facing string is now translatable.
- Two-way order linking: `_b2b_quote_id` on the order, `order_id` on the quote.
- Extension points: `b2b_quote_loaded`, `b2b_quote_item_added`,
  `b2b_quote_request_created`, `b2b_quote_updated`, `b2b_quote_approved`,
  `b2b_quote_is_product_quoteable`, `b2b_quote_social_platforms`,
  `b2b_quote_admin_email_recipient`, `b2b_quote_settings_fields`.
- Full plugin headers, `readme.txt`, `LICENSE`, `.gitignore`, `.editorconfig`,
  `composer.json`, `phpcs.xml.dist` and a CI workflow.
- Responsive quote cart, keyboard-dismissable composer dialog, `aria-live`
  notices and reduced-motion support.

### Changed

- **Repository restructured.** The plugin now lives at the repository root
  instead of inside a version-named folder, so it can be cloned directly into
  `wp-content/plugins/`. The committed build artefact was removed.
- The settings screen uses native WooCommerce sections through the documented
  `WC_Admin_Settings` API, replacing JavaScript that hid every `h2` in
  `#mainform` and rebuilt fake tabs from a hardcoded index. This also resolves
  the fatal error the 1.2.0 README listed as a known issue.
- Product targeting uses WooCommerce's AJAX product search instead of rendering
  the entire catalogue into a `<select>`.
- Eligibility logic moved into `B2B_Quote_Rules`, shared by every component.
- All CSS and JavaScript moved out of PHP into enqueued asset files.
- The live settings preview is event-driven instead of polling every 250ms.
- Database access consolidated in `B2B_Quote_DB` with prepared statements and
  column whitelists.
- Timestamps are stored in GMT and rendered in the site timezone.

## [1.2.0] - 2025

- Initial public release: quote requests, admin CRM, direct message ordering,
  approval link scaffolding.

[1.3.0]: https://github.com/Joytirmoy-Halder/WooCommerce-B2B-Quoting-Engine-v1.2.0/releases/tag/v1.3.0
