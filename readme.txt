=== RankKernel SEO & Schema Engine ===
Contributors: maulikbhalodiya
Tags: seo, meta, sitemap, schema, breadcrumbs
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% free, lightweight SEO with no paywalls or upsell banners.

== Description ==

RankKernel is the zero-bloat, open-source SEO engine for WordPress:

* Metadata engine (titles, meta descriptions, robots, canonical, Open Graph, Twitter cards)
* Content analysis
* XML sitemaps
* Schema (JSON-LD)
* Breadcrumbs
* Redirects
* 404 monitor
* Importers (Yoast SEO, Rank Math, AIOSEO, redirects, sitewide settings)
* Robots.txt, llms.txt and .htaccess editors
* IndexNow instant indexing
* REST API (`rankkernel/v1`)
* Support screen with site diagnostics
* Versioned migrations
* Uninstall purge

Modules are hard-gated: a module that is off is never loaded.

RankKernel is built for single-site installations. Network activation is blocked: a network activation attempt is refused and the plugin is network-deactivated before any options, cron events or tables are created. Activate RankKernel on each site individually.

Hard-gated modules, single-row metadata. A module that is off is never loaded: no hooks, no queries, no bloat.

== Installation ==

1. Upload the `rankkernel` folder to `/wp-content/plugins/`.
2. Activate through the Plugins menu.

== Privacy ==

RankKernel stores its data on your own site. Outside the contact form described below, nothing is sent to the plugin author.

The 404 monitor is off by default. When that module is enabled, a genuine 404 request is recorded in the site's own database table, `wp_rankkernel_404_log` on a default install. A row holds the requested URI, including its query string. The referer and user agent are stored only when advanced field capture is enabled. No IP address is read or stored. Rows are removed by a retention window, 30 days by default, and by a maximum row cap, so the log does not grow without limit.

Plugin settings are stored in the site's options as configuration. This includes any webmaster verification codes you enter for services such as Google, Bing, Yandex, Baidu and Pinterest.

When the Instant Indexing module is enabled, submitting a URL sends that URL plus your IndexNow API key, which is stored in the site's own options, to https://api.indexnow.org/indexnow. Submission happens only when you submit manually or publish content with the module enabled; nothing is sent otherwise.

== External Services ==

RankKernel contacts two external services, and only after your explicit opt-in (manual submit, or enabling the module with auto-submit on):

1. Support inbox (email). The Support screen sends an email through wp_mail to rankkernelsupport@gmail.com. Sending happens only when you press Send. The message carries the subject, the message body, the reply address, optional screenshots you attach, and site diagnostics: RankKernel version, WordPress version, PHP version, theme name, site URL, and multisite yes/no. The Reply-To header is set to your address.

2. IndexNow. Submitting a URL sends an HTTP POST to https://api.indexnow.org/indexnow with a JSON body containing host, key, keyLocation, and urlList, sent with Content-Type application/json. This happens when you submit manually from the Instant Indexing screen, and automatically through a deferred submit when a post is published, updated, or trashed, or when a term is created, edited, or deleted. Automatic submission runs only when the Instant Indexing module is enabled and auto-submit is enabled. The request uses wp_safe_remote_post, is blocking, has a timeout of 10 seconds, and does not follow redirects (redirection set to 0).

A separate key check performs an HTTP GET through wp_safe_remote_get to your own site's key file URL (the keyLocation). It runs only when you trigger the Verify key action, and it fetches only your own site's key file.

IndexNow is the official shared submission endpoint. Official documentation: https://www.indexnow.org/documentation. Official terms: https://www.indexnow.org/terms. The terms page includes a Privacy section. IndexNow does not publish a separate privacy policy URL; no standalone privacy URL is available, so the privacy terms are found within the terms page. This is a factual description of the service and its published pages, not a legal clearance.

== AI Assistance Disclosure ==

Portions of RankKernel were developed with AI assistance. Tools used include Claude, GitHub Copilot, and Jules (an AI software engineering agent). AI assistance was used for code refactoring, test coverage, documentation, internationalization string formatting, and WordPress.org compliance checks.

The developer reviewed, tested, and verified all AI-assisted code and documentation, and remains responsible for the plugin. All code is licensed under GPL-2.0-or-later.

Uninstalling the plugin removes the plugin's own log tables and its transients. The remaining configuration options, post meta, term meta and user meta are removed only when the purge on uninstall setting is enabled. Uninstall is scoped to the current site; network activation is unsupported and blocked at activation.

== Screenshots ==

1. RankKernel dashboard with one-click module toggles, live status cards, and quick actions.
2. Site-wide SEO settings: title and description templates with tokens, separator choice, and save.
3. Per-post SEO in the block editor: live search preview, focus keyword, and content analysis.
4. Redirects manager with a saved 301 rule, filters, and bulk actions.
5. Automatically generated XML sitemap index for search engines.
6. Schema.org identity and structured-data settings with validator links.
7. 404 Monitor catching real missing-page traffic, ready to convert into redirects.

== Changelog ==

= 0.1.1 =
* Sitemap generation primes image attachment caches, reducing repeated database lookups for posts with images.
* Taxonomy sitemap modification dates now match the set they describe, and all sitemap dates are emitted consistently as UTC instants.
* Toggling the sitemap module off and on no longer leaves stale rewrite rules behind.
* Automatic redirects are cleaned up when published posts or pages are unpublished, trashed or deleted; manually created, imported and edited rules are protected.
* Corrected homepage Open Graph metadata, aligned canonical and Open Graph URLs, and fixed canonical URLs on paginated archives.
* Improved default social image handling and Twitter card fallback when no suitable image is available.
* Focus-keyword boundary detection and phrase matching are now consistent across title, content and image analysis.
* Corrected 404 hit counting when two requests race to log the same missing page, and reduced redundant cache writes when the flood limit is active.
* Support-request rate limiting now works correctly with persistent object caches.
* CSV exports escape values that look like spreadsheet formulas so they cannot be evaluated when the file is opened.
* Added an administrative warning when the stored database schema version is newer than the running plugin version, so schema migrations are skipped safely after a downgrade.

= 0.1.0 =
* Initial release: metadata engine, XML sitemaps, schema, breadcrumbs, redirects, 404 monitor, importer, robots, IndexNow, REST API, migrations, uninstall purge, plus bootstrap, ModuleManager and SettingsStore.

== Upgrade Notice ==

= 0.1.1 =
Maintenance and reliability update for sitemaps, redirects, metadata, content analysis and monitoring. Includes CSV export formula protection and a safer database downgrade warning. No database migration is required.

= 0.1.0 =
Initial release.
