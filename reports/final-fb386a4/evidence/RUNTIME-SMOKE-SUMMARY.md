# RankKernel 0.1.0 exact-ZIP runtime smoke — results

- ZIP: `/tmp/rk-final-audit/new/rankkernel-0.1.0.zip`, SHA-256 `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`, 902973 bytes, 239 files (all verified).
- Staging: `/home/ubuntu/rk-staging/wp71` (WP 7.1.4, :8086), `/home/ubuntu/rk-staging/wp65` (WP 6.5.14, :8085). PHP 8.5.12.
- WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false set in both wp-config.php (staging only); debug.log cleared before testing.
- Raw logs: `/tmp/rk-final-audit/new/runtime/` (public-*.log, admin-*.log, metadata-workflow.log, module-toggle.log, debug-*-final.log, hash-*.txt).

## Install verification (ZIP round-trip)

| check | wp71 | wp65 |
|---|---|---|
| installed file hashes vs extracted package | 239/239 match, 0 missing/mismatch | 239/239 match, 0 missing/mismatch |
| plugin active, version | active, 0.1.0 (wp plugin list + get_plugin_data header) | active, 0.1.0 (same) |
| ZIP hash/size | 4edb5beb…113287a / 902973 bytes (unchanged) | same |

## Public smoke

| phase | WP | command/URL | status | result | debug.log |
|---|---|---|---|---|---|
| homepage | 7.1.4 | `GET /` | 200, 72677 B, no "fatal" | PASS | no rankkernel lines |
| homepage | 6.5.14 | `GET /` | 200, 98798 B, no "fatal" | PASS | no rankkernel lines (core PHP 8.5 deprecations only) |
| sitemap index | 7.1.4 | `GET /sitemap_index.xml` | 200, valid XML (xml.dom parse) | PASS | — |
| sitemap index | 6.5.14 | `GET /sitemap_index.xml` | 200, valid XML | PASS | — |
| sitemap child | 7.1.4 | `GET /post-sitemap.xml` | 200, valid XML | PASS | — |
| sitemap child | 6.5.14 | `GET /post-sitemap.xml` | 200, valid XML | PASS | — |
| core sitemap | both | `GET /wp-sitemap.xml` | 404 both | expected: RankKernel owns sitemaps; pre-existing `/wp-sitemap.xml` 404 row in wp71 monitor log corroborates | — |
| robots | 7.1.4 | `GET /robots.txt` | 200, RankKernel AI-bot rules + `Sitemap: …/sitemap_index.xml` | PASS (robots module on) | — |
| robots | 6.5.14 | `GET /robots.txt` | 200, default rules + `Sitemap: …/sitemap_index.xml` | PASS with note: robots module was OFF (staging had only 5/10 modules); output is baseline, not RankKernel rules | — |
| redirect | 7.1.4 | `GET /rk-smoke-old-<ts>` (`insert()` id 5, 301 exact) | 301 `Location: /` | PASS | — |
| redirect | 6.5.14 | same (id 3; modules redirects/404/robots/instant-indexing/importer enabled for test, reverted after) | 301 `Location: /` | PASS | — |
| query preservation | both | `GET /rk-smoke-old-<ts>?utm_source=test&x=1` | 301 `Location: /?utm_source=test&x=1` both | PASS | — |
| 404 + logging | 7.1.4 | `GET /rk-404-probe-<ts>` | 404; row in monitor table hits=1 | PASS | — |
| 404 + logging | 6.5.14 | same | 404; row hits=1 | PASS | — |

## Admin smoke (cookie auth: logged_in + auth cookies from WP_Session_Tokens; initial 302 was harness missing the `wordpress_*` auth cookie `auth_redirect()` needs — documented, not a defect)

| page | wp71 (7.1.4) | wp65 (6.5.14) |
|---|---|---|
| rankkernel (dashboard) | 200, 111688 B, no fatal | 200, 52407 B, no fatal |
| rankkernel-general | 200, no fatal | 200, no fatal |
| rankkernel-sitemap | 200, no fatal | 200, no fatal |
| rankkernel-schema | 200, no fatal | 200, no fatal |
| rankkernel-redirects | 200, lists test rule | 200, lists test rule |
| rankkernel-404 | 200, lists probe row | 200, lists probe row |
| rankkernel-instant-indexing | 200, no fatal | 200, no fatal |
| rankkernel-support | 200, no fatal | 200, no fatal |
| rankkernel-importer | 200, no fatal | 200, no fatal |

No `Notice:`/`Warning:`/`Deprecated:` strings in any rendered admin HTML. Zero `rankkernel` lines in either debug.log.

## Module toggle + workflows

| check | result |
|---|---|
| metadata save→render both | post meta `_rankkernel_meta_data` {title,description} rendered in `<head>` on both versions; test posts deleted |
| sitemap generation | index + child valid XML both (above) |
| robots output | above |
| redirect match | 301 + query preservation both (above); rules deleted after, 404-gone verified |
| disable `redirects` (wp71) | admin page → HTTP 403 (WP core "not allowed"; submenu unregistered — observed, not claimed as defect); front-end rule URL → 404 (redirect inactive) |
| re-enable `redirects` (wp71) | admin → 200, front-end → 301 again |
| 404 monitor | probe logged with hits=1 on both; all test rows deleted (remaining rows are pre-existing staging data: wp71 ids 1–3, wp65 id 1) |
| wp65 modules | restored to as-found 5-module list after testing |

## debug.log / error logs

- `debug-wp71-final.log` (369 lines), `debug-wp65-final.log` (1907 lines): **0 lines mention rankkernel; 0 web-request fatals.**
- All `Deprecated` lines are environment noise: WP-CLI phar (`Mustache_*`, `cli\Table`, `Colors.php`) and WP core on PHP 8.5 (`class-wp-block-type-registry.php:168`, `class-wp-block.php:246`, `class-wp-theme-json.php:2532`, `class-wp-translation-controller.php`, `media.php:5502`). wp65 (older core) emits far more than wp71.
- Two non-deprecated harness artifacts, both from my own `wp eval` one-liners (eval'd code stack frames), NOT RankKernel: (1) 08:47:51 `TypeError array_values(stdClass)` from a SHOW TABLES probe; (2) 08:51:14 `array offset on int / Undefined array key "id"` from a cleanup loop iterating `paginate()`'s wrapper keys instead of `['rows']` (corrected and re-run cleanly).
- PHP built-in server logs (`server.log` both sites): connection Accepted/Closing only, no PHP errors.

## Cleanup

Deleted: redirect rules (wp71 id 5, wp65 id 3), all `rk-404-probe`/`rk-smoke-old`/trigger 404 rows, test posts 9/12, `/wp71/rk-cookie-probe.php`, my session tokens; wp65 modules reverted. Production `/var/www/maulik-dev`, source, release, ZIP untouched (ZIP re-hashed identical at end).

## Verdict

Exact package 0.1.0: **PASS on WP 7.1.4 and WP 6.5.14** — public, admin (9/9 pages × 2), metadata/redirect/sitemap/robots/404-monitor workflows, module toggle. No fatal, warning, deprecation, or behavioral defect attributable to the exact package. All observed log noise is environment (PHP 8.5 vs old core/WP-CLI) or my own harness commands, each identified by timestamp and stack frame above.
