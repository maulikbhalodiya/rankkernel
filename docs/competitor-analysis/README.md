# Competitor Analysis — Yoast SEO & Rank Math Reverse-Engineering (Phase 1)

Reverse-engineering audit of the two market-leading WordPress SEO plugins to ground the design of a free, lightweight, modular competitor plugin.

## Audited codebases

| Plugin | Version | Path |
|---|---|---|
| Yoast SEO (free) | 27.8 | `wp-content/plugins/wordpress-seo/` |
| Yoast SEO Premium | 27.8 | `wp-content/plugins/wordpress-seo-premium/` |
| Rank Math SEO (free) | 1.0.277.2 | `wp-content/plugins/seo-by-rank-math/` |

Rank Math **Pro** source is not available in this repository — its paid tier is documented from the free code's own gating flags (`probadge`/`upgradeable`/`disabled`) and public docs, marked 📄 in the matrix.

## Documents

| File | Contents |
|---|---|
| [`yoast-audit.md`](./yoast-audit.md) | Master Yoast audit: DB schema (meta keys, options, `wp_yoast_*` tables, cron, uninstall no-op), head pipeline (hooks/priorities/presenters), sitemaps, REST/Gutenberg, Premium mechanics (redirects-in-options, prominent words TF-IDF, licensing), bloat inventory, 11 bottlenecks |
| [`rankmath-audit.md`](./rankmath-audit.md) | Master Rank Math audit: DB schema (`rank_math_*` meta, 4 autoloaded option groups, custom tables), Paper head architecture, schema module, sitemaps, redirects+404 (per-request query cost), IndexNow, REST/module gating, bloat, bottlenecks |
| [`feature-matrix.md`](./feature-matrix.md) | Feature-by-feature free/paid comparison + "we win here" priorities |
| [`gap-analysis.md`](./gap-analysis.md) | Verified performance bottlenecks, architectural weaknesses, bloat, and our counter-designs |

## Method

Nine parallel deep-explore agents audited the source (data layer, head pipeline, sitemaps, REST/Gutenberg, Premium internals, module registry, redirects/404, bloat), followed by consolidation writers and cross-verification. All `file:line` citations reference the actual plugin sources in this repo. Plugins were installed but never set up, so findings are derived from code, not runtime DB inspection.

## Headline findings

1. **Yoast deletes nothing on uninstall** (`register_uninstall_hook(…, '__return_false')`) and RM retains data unless a filter flips — trust and hygiene win available.
2. **Yoast Premium stores redirects in autoload-excluded options** — no queryable index, whole set read per request. RM queries its redirect tables on **every** frontend request.
3. **Yoast's sitemap transient cache ships disabled**; **neither plugin actually pings search engines** anymore (Yoast removed it in v22; RM's `hit_index` only warms its own cache).
4. **25–45 postmeta rows per post** across vendors vs our planned single-key design.
5. Both run meaningful per-request frontend cost (Yoast: DI container + breadcrumb queries + replace-var re-resolution; RM: redirect lookup + 4 autoloaded option groups).
6. RM's `can_load_module()` hard gate (disabled module = zero instantiation) is the one competitor pattern worth copying.

## Status / caveats

- RM audit sections "Frontend Head Pipeline", "Schema Architecture", "Module Registry", "Free vs Pro", and "Bloat" were thin from the first pass — two targeted re-audits were run and **merged** into `rankmath-audit.md` (28-module registry table, full hook-priority map, schema `@graph` architecture, external-HTTP table, and the definitive finding that header/footer code injection has no frontend output in free).
- `yoast-audit.md` passed a ~30-claim fact-check against source (all hook priorities, meta keys, option names, table columns, and behavior claims verified; two wording nuances fixed).
- `rankmath-audit.md` passed the same ~30-claim fact-check (all 15 claim categories verified; one error found and fixed — Recipe/Event are `@type`-keyed view templates of the `[rank_math_rich_snippet]` shortcode, not standalone `[rank_math_recipe]`/`[rank_math_event]` shortcodes — plus one path nuance fixed).
- Rank Math Pro features are 📄-marked (documented, not code-verified).
- Next phases (not started): architecture blueprint → plugin build → WordPress.org compliance/deployment.
