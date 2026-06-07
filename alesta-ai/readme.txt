=== Alesta AI ===
Contributors: alestacomputer, celdebs
Tags: seo, sitemap, performance, gdpr, maintenance
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

All-in-one WordPress toolkit: XML sitemap, .htaccess cache/GZIP, robots.txt, broken-link scanner, GDPR banner, maintenance mode, and more.

== Description ==

**Alesta AI** is a free, all-in-one optimization and compliance toolkit for WordPress administrators. It groups, in a single plugin, the recurring technical tasks that every site needs: cache headers, sitemap, broken link scanning, GDPR cookie banner, maintenance mode, database cleanup, font self-hosting and more.

= What's included (free) =

* **XML Sitemap** — Generate a clean sitemap.xml and ping Google & Bing.
* **Cache, GZIP & HTTPS** — One-click .htaccess optimization for browser cache, GZIP compression and HTTPS redirection. Automatic backup before each change with one-click restore.
* **Robots.txt** — Editor for the indexation rules.
* **HTTP Errors (4xx / 5xx)** — Batch scan of internal links across pages, posts and WooCommerce products. Detects broken links with HTTP code, source page, and one-click fix. Elementor compatible.
* **Database cleaner** — Schedule cleanup of revisions, transients and spam comments via WP Cron.
* **Google Fonts (GDPR)** — Download Google Fonts files and host them locally on your server, eliminating third-party requests.
* **Maintenance Mode** — Customizable 503 maintenance page with countdown, social links, role/IP allow-list and bypass parameter.
* **Health Check** — Detailed site dashboard: PHP, SSL, disk, plugins, MySQL.
* **GDPR Banner** — Sovereign cookie consent banner without third-party dependency.
* **Talk to Me** — Floating multi-channel contact button (WhatsApp, Messenger, phone, email, SMS, Telegram, Instagram, custom URL). Two display modes (deployable menu / stacked buttons), per-page targeting, opening hours.
* **Debug Manager** — Toggle WP_DEBUG and view debug.log.
* **Budget tracker** — Token usage tracking (used by the optional Pro modules).

= Pro version =

A separate **Alesta AI Pro** plugin (distributed outside WordPress.org) adds AI-powered modules built on Claude (Anthropic): bulk SEO title & meta generation, FAQ schema, keyword analysis, content improvement, AI translation, chatbot, image metadata, scheduled updates, alerts, PDF reports and more. Learn more at [alesta-ai.com/tarifs.html](https://www.alesta-ai.com/tarifs.html).

== External Services ==

This plugin connects to the following external services. Please review each service's terms and privacy policy before use.

**1. Anthropic Claude API** *(optional — Configuration module)*
The Configuration module exposes an "Anthropic API key" field and a "Test connection" button. The key is stored locally in the WordPress database and is shared with the separate Alesta AI Pro plugin if you install it later.
- Data sent: only when you click the "Test connection" button — a single test request is sent to Anthropic to verify the key.
- Purpose: validate the API key for use with the optional Pro plugin.
- Triggered only when you click "Test connection". The free plugin makes no other calls to Anthropic.
- Service provider: Anthropic, PBC
- Terms of Service: https://www.anthropic.com/legal/consumer-terms
- Privacy Policy: https://www.anthropic.com/privacy

**2. Google Fonts** *(optional — Fonts Self-Hosting module)*
Used to download Google Fonts files and host them locally on your server, eliminating third-party font requests for your visitors.
- Data sent: HTTP request to fonts.googleapis.com and fonts.gstatic.com to retrieve the font CSS and .woff2 files.
- Purpose: Download fonts for local self-hosting; once downloaded, no further requests are made to Google.
- Triggered only when you click "Download & Self-host Fonts" in the Fonts module.
- Service provider: Google LLC
- Terms of Service: https://developers.google.com/fonts/terms
- Privacy Policy: https://policies.google.com/privacy

**3. Search engine ping endpoints** *(optional — XML Sitemap module)*
When you publish or regenerate a sitemap, the plugin can notify the major search engines that the sitemap has changed.
- Data sent: the public URL of your sitemap.
- Purpose: Inform search engines that your sitemap was updated.
- Triggered only when you click "Notify search engines" in the Sitemap module.
- Service providers: Google LLC, Microsoft Bing
- Privacy Policy (Google): https://policies.google.com/privacy
- Privacy Policy (Bing): https://privacy.microsoft.com/privacystatement

== Installation ==

1. Upload the `alesta-ai` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Alesta AI → Tableau de bord** to access the modules

== Frequently Asked Questions ==

= Does this plugin require an API key? =

No. All features included in this free plugin work without any API key or third-party account. The optional separate Pro plugin uses the Anthropic API (Claude) for AI features and does require an Anthropic API key.

= Does it work with WooCommerce? =

Yes. The HTTP error scanner reads internal links across pages, posts and WooCommerce products.

= Does it work with Elementor? =

Yes. The HTTP error scanner reads both standard WordPress content and Elementor data (`_elementor_data`). Link corrections are applied to both.

= Can I restore the .htaccess if something goes wrong? =

Yes. The plugin saves a full backup of your .htaccess before every modification. A **Restore backup** button is always available in the Cache, GZIP & HTTPS module.

= Can I exclude my IP from the maintenance page? =

Yes. The Maintenance module supports per-IP and per-role allow-lists, plus a bypass query parameter that sets a session cookie.

== Screenshots ==

1. **Master AI Dashboard** — Cockpit overview of every module: SEO, content, media, performance, security and reporting.
2. **Maintenance Mode** — Customizable 503 maintenance page with countdown, social links and role/IP allow-list.
3. **Health Check** — Detailed dashboard for WordPress, PHP, SSL, disk and database state.

== Changelog ==

= 1.2.4 =
* Plugin Check fixes: register the maintenance page CSS/JS via wp_register_style()/wp_register_script() + wp_print_styles()/wp_print_scripts() instead of raw <link>/<script> tags.
* Bump "Tested up to" to WordPress 7.0.
* Shorten Short Description to fit the 150-character limit.
* Fix "Passer à Pro" sidebar link: use /tarifs.html (the page that exists) instead of /tarifs (404).
* No functional change.

= 1.2.3 =
* Security & WP.org review compliance: scope set_time_limit() locally with restore, ensure every ob_start() has a paired ob_get_clean() in a try/finally, justify legitimate ABSPATH/WP_CONTENT_DIR usages with explicit phpcs comments, document json_decode() inputs that are sanitised field-by-field after decoding, harden remaining $_POST/$_GET accesses with sanitize/wp_unslash and nonce verification.
* No functional change — security and review feedback only.

= 1.2.2 =
* Free release for the WordPress.org repository.
* Includes: XML Sitemap, .htaccess (Cache/GZIP/HTTPS), Robots.txt editor, HTTP error scanner (4xx/5xx with Elementor support), database cleaner, Google Fonts self-hosting (GDPR), maintenance mode, health check, GDPR banner, debug manager, budget tracker.
* AI-powered modules are available in the separate Alesta AI Pro plugin.

== Upgrade Notice ==

= 1.2.4 =
Plugin Check compliance: maintenance page assets now properly enqueued. Recommended for all users.

= 1.2.3 =
Security and WordPress.org review compliance update. Recommended for all users.

= 1.2.2 =
First public release on the WordPress.org repository.
