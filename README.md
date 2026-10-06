# RankKernel, Free SEO & Schema Engine

> 100% free, lightweight SEO for WordPress, no paywalls, no upsell banners, no telemetry.

**Status: stable release 0.1.0.** Not yet submitted to WordPress.org.

## What makes it different

- **Hard-gated modules.** A module that is off is never loaded: no hooks, no queries, no bloat.
- **Single-row metadata.** All per-object SEO data in one meta key (`_rankkernel_meta_data`), one query instead of competitors' 25 to 45 rows per post.
- **Clean uninstall, by design.** Purge is an explicit, user-controlled choice.
- **Zero telemetry.** No external requests except endpoints you explicitly configure.

## Shipped (V1)

- Module system with a hard gate (zero cost when disabled)
- Metadata Engine: title, meta description, robots, canonical, Open Graph, Twitter cards
- Content Analysis
- XML Sitemaps
- Schema (JSON-LD)
- Breadcrumbs
- Redirects
- 404 Monitor
- Robots.txt editors
- Instant Indexing (IndexNow)
- REST API (`rankkernel/v1`): settings + module toggles
- Versioned migrations with a safe failure ledger
- Guarded bootstrap, activation requirement checks, uninstall purge

## Roadmap

Importer (Yoast/Rank Math/SEOPress) · Image SEO · Internal Linking · AI suite (bring-your-own-key) · WooCommerce · Local SEO · News SEO · Video SEO · Headless

## Requirements

- WordPress 6.5+
- PHP 8.2+

## Development

```bash
composer install          # installs dev tooling (phpcs, phpstan, phpunit)
composer test             # PHPUnit (Brain Monkey, no database needed)
composer lint             # phpcs (WPCS + PSR-12 hybrid)
composer stan             # phpstan level 6
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md), issues first, `GH-<issue>` branches, owner merges.

## License

[GPL-2.0-or-later](LICENSE), the same license as WordPress itself.
