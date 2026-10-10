=== RankKernel – Free SEO & Schema Engine ===
Contributors: maulikbhalodiya
Tags: seo, meta, sitemap, schema, breadcrumbs
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% free, lightweight SEO with no paywalls or upsell banners.

== Description ==

RankKernel is the Free, Zero-Bloat Open-Source SEO Engine for WordPress, all free, forever:

* Metadata engine (titles, meta descriptions, robots, canonical, Open Graph, Twitter cards)
* Content analysis
* XML sitemaps
* Schema (JSON-LD)
* Breadcrumbs
* Redirects
* 404 monitor
* Robots.txt editors
* IndexNow instant indexing
* REST API (`rankkernel/v1`)
* Versioned migrations
* Uninstall purge

Modules are hard-gated: a module that is off is never loaded.

RankKernel is built for single-site installations. Network activation is not supported.

Hard-gated modules, single-row metadata. A module that is off is never loaded: no hooks, no queries, no bloat.

== Installation ==

1. Upload the `rankkernel` folder to `/wp-content/plugins/`.
2. Activate through the Plugins menu.

== Privacy ==

RankKernel stores its data on your own site. Outside the contact form described below, nothing is sent to the plugin author.

The 404 monitor is off by default. When that module is enabled, a genuine 404 request is recorded in the site's own database table, `wp_rankkernel_404_log` on a default install. A row holds the requested URI, including its query string. The referer and user agent are stored only when advanced field capture is enabled. No IP address is read or stored. Rows are removed by a retention window, 30 days by default, and by a maximum row cap, so the log does not grow without limit.

Plugin settings are stored in the site's options as configuration. This includes any webmaster verification codes you enter for services such as Google, Bing, Yandex, Baidu and Pinterest.

When the Instant Indexing module is enabled, submitting a URL sends that URL plus your IndexNow API key, which is stored in the site's own options, to https://api.indexnow.org/indexnow. Submission happens only when you submit manually or publish content with the module enabled; nothing is sent otherwise.

== External Service ==

RankKernel contacts two external services, and only with your explicit action:

1. Support inbox. The Support screen sends your message (subject, message, reply address, and optional screenshots you attach) plus site diagnostics (RankKernel version, WordPress version, PHP version, theme name, site URL, multisite yes/no) to rankkernelsupport@gmail.com, with Reply-To set to your address. Sending happens only when you press Send.
2. IndexNow. Submitting a URL sends that URL plus your IndexNow API key to https://api.indexnow.org/indexnow, only when you submit manually or publish content with the Instant Indexing module enabled.

Uninstalling the plugin removes the plugin's own log tables and its transients. The remaining configuration options, post meta, term meta and user meta are removed only when the purge on uninstall setting is enabled.

== Changelog ==

= 0.1.0 =
* Initial release: metadata engine, XML sitemaps, schema, breadcrumbs, redirects, 404 monitor, robots, IndexNow, REST API, migrations, uninstall purge, plus bootstrap, ModuleManager and SettingsStore.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
