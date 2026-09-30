=== RankKernel, Free SEO & Schema Engine ===
Contributors: maulikbhalodiya
Tags: seo, meta, sitemap, schema, breadcrumbs
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% free, lightweight SEO with no paywalls or upsell banners.

== Description ==

RankKernel is the Free, Zero-Bloat Open-Source SEO Engine for WordPress: metadata engine, XML sitemaps, schema, breadcrumbs, redirects, 404 monitor and IndexNow, all free, forever.

Hard-gated modules, single-row metadata. A module that is off is never loaded: no hooks, no queries, no bloat.

== Installation ==

1. Upload the `rankkernel` folder to `/wp-content/plugins/`.
2. Activate through the Plugins menu.

== Privacy ==

RankKernel stores its data on your own site. Nothing is sent to the plugin author.

The 404 monitor is off by default. When that module is enabled, a genuine 404 request is recorded in the site's own database table, `wp_rankkernel_404_log` on a default install. A row holds the requested URI, including its query string. The referer and user agent are stored only when advanced field capture is enabled. No IP address is read or stored. Rows are removed by a retention window, 30 days by default, and by a maximum row cap, so the log does not grow without limit.

Plugin settings are stored in the site's options as configuration. This includes any webmaster verification codes you enter for services such as Google, Bing, Yandex, Baidu and Pinterest.

When the Instant Indexing module is enabled, submitting a URL sends that URL plus your IndexNow API key, which is stored in the site's own options, to https://api.indexnow.org/indexnow. Submission happens only when you submit manually or publish content with the module enabled; nothing is sent otherwise.

Uninstalling the plugin removes the plugin's own log tables and its transients. The remaining configuration options, post meta, term meta and user meta are removed only when the purge on uninstall setting is enabled.

== Changelog ==

= 0.1.0 =
* Initial release: bootstrap, ModuleManager, SettingsStore, REST controllers.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
