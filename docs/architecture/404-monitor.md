# 404 Monitor Architecture

The 404 Monitor module (id `404`, default off) logs genuine frontend
404s with cheap synchronous dedupe. No IP address is read or stored
anywhere in this module.

## Capture hook and priority

`Logger::register()` hooks `template_redirect` at priority 99
(`Logger::PRIORITY`), after `redirect_canonical` at priority 10 and
after the redirector at priority 1. Only genuine 404s reach the
logger: a request that WordPress canonicalizes or that a redirect rule
resolves never arrives here as a 404. Capture additionally requires
`is_404()` to be true.

## Exclusions

Every skip category is checked before any write, in order:

1. `is_admin()`, `wp_doing_ajax()`, `REST_REQUEST`,
   `wp_doing_cron()`.
2. Sitemap requests: any `rankkernel_sitemap` or
   `rankkernel_sitemap_xsl` query var, including the 404 responses the
   sitemap router sends for unknown sets.
3. Response codes 410 and 451, read live with a test override seam.
4. Missing log table fails open: no log, no fatal.
5. Homepage and root requests.
6. Static asset requests by extension: images, CSS, JS, fonts, video,
   audio, PDF, archives (`Logger::STATIC_EXTENSIONS`).
7. Scanner probe substrings such as `.env`, `wp-config`, `.git/`,
   and `xmlrpc.php` (`Logger::PROBE_SUBSTRINGS`).
8. Configured exclusion rules via `Exclusions::matches()`: exact,
   prefix, contains, suffix, and star wildcard comparators. There is
   no regex comparator by design, so rule input can never become a
   pattern injection. Matching is case sensitive against the
   normalized URI.

The flood budget is spent only on real content 404s that survive every
skip above, so noise never consumes it.

## Dedupe

`MonitorRepository::record()` upserts on the `UNIQUE (uri_hash)` index:

- First request inserts a row with `hits = 1`.
- Repeat requests update the row: `hits = hits + 1` with a refreshed
  `last_accessed`.
- A concurrent insert that loses the race falls back to the increment
  path, so one URI can never produce two rows.
- One increment per URI per request: the static `$logged` map
  collapses repeat writes inside a single request.

Advanced fields ride along on the insert and refresh to the latest
values on repeat hits; empty values leave the stored fields untouched.

## Advanced fields, opt in

Referer and user agent are captured only when the `advanced_fields`
setting is on (off by default). Values pass through
`sanitize_text_field` and truncate to 255 characters
(`Logger::FIELD_LENGTH`). Only the `HTTP_REFERER` and
`HTTP_USER_AGENT` server fields are ever read. No IP field is read
anywhere in the module, and the admin screen states that IP addresses
are never stored.

## Query handling

The path passes through the shared redirect normalizer so 404 URIs and
redirect sources compare identically. The query string is kept only
when the `ignore_query` setting is off (on by default): with the
default, query variants collapse to one row; with the setting off,
each query string logs as a distinct entry.

## Flood protection

`FloodGuard` caps new URIs per time window (default 50 new URIs per
300 seconds, configurable between 1 and 1000 per 60 to 3600 seconds).
State lives in the object cache group `rankkernel-monitor` with a
transient fallback, so no table and no cron are needed. When the budget
is exhausted, new URIs are suppressed while known URIs keep
incrementing, and the `rankkernel_404_suppressed` marker is set for an
administrator notice. The per site key keeps multisite budgets separate.
