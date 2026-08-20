=== WooCommerce B2B Quoting Engine ===
Contributors: joytirmoyhalder
Tags: woocommerce, b2b, request a quote, wholesale, trade pricing
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.8
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace Add to cart with Request a quote, negotiate prices from the admin, and send customers a one-click approval link into checkout.

== Description ==

Built for wholesale, trade and made-to-order stores where the price depends on volume and specification rather than a fixed list price.

Quoteable products show a quote button instead of add-to-cart. Customers build a request, set quantities, and submit their contact details and technical requirements. Requests arrive under WooCommerce > Quote Requests, where you set an agreed unit price per line, add notes and move the request through its status.

Sending a counter-offer emails the customer a single-use approval link. Opening it loads the quoted items into their cart at the agreed prices and takes them to checkout. The resulting order is linked back to the quote in both directions.

= Choose what gets quoted =

Enable quoting for the whole catalogue, or target specific categories and products. Child categories are matched automatically, and selecting a variable product covers all of its variations.

= Direct message ordering =

Optionally let customers start an order over WhatsApp, Messenger, Telegram, Viber, Skype or LINE, with the product name, link and their own message prepared for them. WhatsApp and LINE accept the message through the link; the others receive it on the clipboard, because those platforms provide no way to prefill message text.

= Designed to be extended =

Product eligibility, the messaging platform list, the notification recipient and the settings fields themselves are all filterable. Actions fire when a request is created, updated and approved. See the repository README for the full reference.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through Plugins > Add New.
2. Activate the plugin. Its database table and a Quote Request Cart page are created automatically.
3. Configure it under WooCommerce > Settings > B2B Quoting.

WooCommerce must be active. If it is not, the plugin stays dormant and shows a notice rather than causing an error.

== Frequently Asked Questions ==

= Can customers still buy normally? =

Yes. Only the products you target switch to quoting. Everything else keeps its standard add-to-cart button.

= Where does the quote cart live? =

On a page containing the `[b2b_quote_cart]` shortcode, created for you on activation. You can move the shortcode to any page.

= Does it work with a caching plugin? =

Yes. The quote cart page is excluded from full-page caching, and the AJAX endpoints return a fresh security token with every response, so the quote button keeps working on a cached page.

= Is it compatible with High-Performance Order Storage? =

Yes. HPOS and the cart and checkout blocks are both declared compatible.

= What happens to my data if I delete the plugin? =

Nothing, unless you tick "Remove data on uninstall" in the settings first. Deactivating never removes data.

= Can an approval link be reused? =

No. Each link is single-use, valid only while the quote is awaiting approval, and is invalidated when a new offer is sent.

== Changelog ==

= 1.3.0 =

Security, correctness and maintenance release following a full audit of 1.2.0.

* Security: a customer reaching checkout through an approval link could obtain goods for free when a line had no negotiated price. Fixed.
* Security: escaped all customer-supplied values in outgoing emails.
* Security: validated every design setting before it is emitted as CSS.
* Security: whitelisted quote statuses and hardened all admin form handling.
* Security: added a honeypot and a throttle to the public submission endpoint.
* Security: quote items are validated for existence, visibility and eligibility.
* Fixed: the database table could not be created on strict MySQL configurations.
* Fixed: approval links always failed, because the flow looked for a status that was never set.
* Fixed: counter-offers were never actually sent; no approval token was generated.
* Fixed: bulk quantities could not be entered, despite this being a bulk quoting plugin.
* Fixed: assets were versioned with the current timestamp, defeating all caching.
* Fixed: category targeting now matches child categories, and product targeting matches variations.
* Fixed: archive pages no longer duplicate the messaging widget for every product.
* Fixed: the quote page is no longer recreated on every front-end request.
* Fixed: failed requests now report an error instead of hanging the button.
* Added: customer confirmation email, per-line pricing in the CRM, pagination and search.
* Added: uninstall routine, HPOS declaration, translation support and a POT file.
* Changed: the plugin now lives at the repository root; the committed build artefact was removed.
* Changed: the settings screen uses native WooCommerce sections, resolving the fatal error listed as a known issue in 1.2.0.

= 1.2.0 =

* Initial public release.

== Upgrade Notice ==

= 1.3.0 =
Important security release. Fixes a flaw that allowed checkout at zero price through an approval link, plus HTML and CSS injection issues. Upgrade immediately. Approval links issued by 1.2.0 never worked and will need to be resent.
