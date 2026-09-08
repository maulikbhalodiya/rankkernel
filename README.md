# RankKernel – Free SEO & Schema Engine

> 100% free, lightweight SEO for WordPress — no paywalls, no upsell banners, no telemetry.

**Status: work in progress (v0.1.0-dev — foundation).** Not yet submitted to WordPress.org.

## What makes it different

- **Hard-gated modules.** A module that is off is never loaded: no hooks, no queries, no bloat.
- **Single-row metadata.** All per-object SEO data in one meta key (`_rankkernel_meta_data`) — one query instead of competitors' 25–45 rows per post.
- **Clean uninstall, by design.** Purge is an explicit, user-controlled choice.
- **Zero telemetry.** No external requests except endpoints you explicitly configure.

## Shipped (foundation)

- Module system with a hard gate (zero cost when disabled)
- Metadata Engine: title, meta description, robots, canonical, Open Graph, Twitter cards
- REST API (`rankkernel/v1`): settings + module toggles
- Versioned migrations with a safe failure ledger
- Guarded bootstrap, activation requirement checks, uninstall purge

## Roadmap

XML sitemaps (cache ON) · Schema/JSON-LD · Breadcrumbs · Redirects (cache-first) · 404 monitor · Instant Indexing (IndexNow) · Robots editors · Importer (Yoast/Rank Math/SEOPress) · Gutenberg suite · AI suite (bring-your-own-key) · Headless payload

## Requirements

- WordPress 6.5+
- PHP 8.1+

## Development

```bash
composer install          # installs dev tooling (phpcs, phpstan, phpunit)
composer test             # PHPUnit (Brain Monkey — no database needed)
composer lint             # phpcs (WPCS + PSR-12 hybrid)
composer stan             # phpstan level 6
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) — issues first, `GH-<issue>` branches, owner merges.

## License

[GPL-2.0-or-later](LICENSE) — the same license as WordPress itself.
