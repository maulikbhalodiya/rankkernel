# Design wiring backlog

The approved Stitch designs in `docs/designcode/` show controls that the pages do not yet
wire up. Per the current decision, every element the design shows is rendered on the page,
but anything without backing is **disabled** rather than omitted, so the composition matches
the design exactly. This file records what is disabled, why, and where the logic already
lives if we decide to make it live.

Rule for the future: **if the functionality already exists on its own page, reuse that logic
rather than writing new logic.** Most entries below are a thin call into code that already
ships.

## How to use this file

Each row is a candidate for a later, separate change. Do not bundle these into design PRs.
When one is picked up, it needs its own issue, its own tests, and its own review, because each
one turns a visual element into a real action with a security surface (nonce, capability,
sanitisation) and a failure path.

## Dashboard

| Element | Current state | Existing logic to reuse | Notes |
| --- | --- | --- | --- |
| Flush Sitemap Cache | Disabled | `src/Modules/Sitemaps/SitemapCache.php`, `SitemapsModule.php` | Needs a nonce-protected admin-post handler, then a notice. Cache invalidation already exists. |
| Export Redirects CSV | Disabled | `src/Modules/Redirects/CsvHandler.php`, `src/Admin/RedirectsPage.php` (existing CSV export action) | Could link straight to the existing Redirects export URL instead of reimplementing. Lowest effort of the four. |
| View robots.txt | Disabled | `src/Modules/Robots/RobotsBuilder.php` (robots.txt is served) | A plain link to `/robots.txt` opening in a new tab. No backend work. |
| Clear 404 Log | Disabled | `src/Admin/NotFoundPage.php::handleClear()` and `NONCE_CLEAR` | Reuse the existing handler and nonce rather than adding a second clear path. |
| Attention count ("N missing URLs") | Shows switched-off module count | Monitor log table | To show real 404 volume, the dashboard needs a count query the controller does not currently make. Decide whether the dashboard should read the log at all. |
| SEO Health status rows | Live (derived from module enabled flags) | Module enable map | Already real. If a deeper health check is wanted (sitemap reachable, robots.txt valid), that is new logic. |

## Sitemap Settings

| Element | Current state | Existing logic to reuse | Notes |
| --- | --- | --- | --- |
| Submit to Google | Disabled | None | No Search Console integration exists. Genuinely new work, and it needs OAuth. Treat as out of scope. |
| Discard Changes | Disabled | None | Needs a client-side reset to last-saved state. Small, but it is new JavaScript. |
| engine: v2.4-fast chip | Static label | None | Decorative in the design. Either drop it or make it report something real. |
| Sitemap index regenerate hook: active | Static label | Sitemap regeneration hooks | Could report the real hook state. Small. |

## Schema Settings

| Element | Current state | Existing logic to reuse | Notes |
| --- | --- | --- | --- |
| Copy JSON | Disabled | Schema JSON-LD generator | Needs a copy-to-clipboard script. The graph itself already exists. |
| Export JSON | Disabled | Schema JSON-LD generator | Download the generated graph. Straightforward once the graph is exposed to the page. |
| Open in Validator | Live | `$validatorUrl` already in the controller | Already wired. |
| Rich Results Test | Live | `$richResultsUrl` already in the controller | Already wired. |
| JSON-LD preview graph | Static or omitted | Schema JSON-LD generator | Rendering the real graph needs the generator output passed to the admin view. Medium effort. |
| Schema Status panel | Static | Schema entity statuses | Each row maps to a real toggle or entity. Can be derived, similar to the Dashboard SEO Health rows. |
| Preview tag column | Static | Schema type mapping | Derivable from the stored per-post-type type. Small. |
| Assembled in X ms | Disabled | None | A timing metric. Only show it if we actually measure it. |

## 404 Monitor

| Element | Current state | Existing logic to reuse | Notes |
| --- | --- | --- | --- |
| CAPACITY tile | Disabled | `Monitor` settings (`rk_max_rows`) plus the log table count | Real once the dashboard reads the log count. |
| RETENTION tile | Disabled | `rk_retention_days` setting | Already stored, just not surfaced on this screen as a tile. |
| MOST RECENT HIT tile | Disabled | Monitor log table (latest row) | Real once the latest row is queried. |
| Create Redirect from this URL | Disabled | `RedirectRepository::insert()`, `src/Admin/RedirectsPage.php` | Reuse the existing redirect insert plus its validation and nonce. Do not add a second insert path. |
| Copy URL | Disabled | None | Clipboard script. Small. |
| Detailed log payload (referer, user agent) | Renders when logging is on | Monitor log columns | Already real when advanced fields are enabled. |
| PERSISTENT LIMIT ALERT | Disabled | Monitor capacity logic | Real once the capacity state is computed for the page. |

## General Settings

| Element | Current state | Existing logic to reuse | Notes |
| --- | --- | --- | --- |
| Import / Export area | Disabled | Partial: redirects CSV only | A settings exporter does not exist. This is the largest item on this list. Confirm scope before starting. |
| Presets with no server-side implementation | Disabled | None | Only enable a preset once a handler exists for it. |

## Non-negotiable requirements for any of these

1. **Nonce and capability on every action.** No exceptions, even for read-only-looking actions.
2. **Reuse the existing handler** when one exists. Do not create a parallel code path for the
   same operation.
3. **Sanitise and validate** anything that arrives from the request.
4. **Fail visibly.** An action that cannot complete must say so, not fail silently.
5. **Tests.** Each wired action needs a regression test proving the refusal paths too.
6. **Never fabricate a value.** If a number cannot be derived from real state, leave it out
   rather than printing a placeholder.
