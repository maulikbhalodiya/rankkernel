# Redirect Architecture

How the Redirects module (id `redirects`, default off) turns a request into at most one redirect response.

## Request lifecycle

```text
Incoming request
        |
WordPress bootstrap
        |
RankKernel module gate (disabled means no class, no hooks)
        |
Redirects module boot registers template_redirect priority 1
        |
template_redirect priority 1 callback (Redirector::maybeRedirect)
        |
guards: is_admin, wp_doing_ajax, REST, cron, sitemap query vars
        |
table check (missing table fails open, no redirect, no fatal)
        |
request normalization through Normalizer::normalize
        |
blocked source check (root / never dispatches)
        |
cache lookup (RedirectCache, object cache then transient fallback)
        |
on miss: matcher runs, exact first via one indexed lookup, then the
bounded in memory pattern tiers; a hit warms the cache
        |
usability check (active flag, known code, known matcher)
        |
destination validation at the send boundary (DestinationValidator)
        |
reentry guard marks sent, hit recorded for the shutdown flush
        |
redirect response for 301, 302, 307 via wp_redirect, then exit;
terminal 410 and 451 via status_header plus a minimal body, no Location
        |
shutdown: one coalesced UPDATE per touched rule (HitCounter::flush)
```

## Hook and priority

Dispatch hooks `template_redirect` at priority 1 (`Redirector::register`).
This runs after the main query is parsed, so conditionals are available,
and before `redirect_canonical` at priority 10, so an intentional
RankKernel rule wins. Matching on `init` or `wp` is rejected by design:
`init` has no conditionals and `wp` fires on every request, which is the
cost this design avoids. Page cache plugins can serve before WordPress
runs; that is documented, not worked around with a different hook.

## Module gating

`RedirectsModule::register()` wires services with no hooks: it calls
`RedirectTable::ensureTables()`, seeds `rankkernel_redirects_settings`
through `RedirectsSettings::ensureSchema()`, flags schema readiness, and
invalidates the match cache so a toggle never serves stale matches.
`RedirectsModule::boot()` builds the `Redirector` and the `SlugWatcher`
and registers their hooks. Both methods return immediately when the
module is disabled, so a disabled module means no class work and zero
`add_action` or `add_filter` calls. Proven by
`RedirectsModuleTest::test_disabled_module_boots_zero_hooks` and the
combined `RedirectsMonitorGatingTest`.

## Dispatch guards

In order inside `Redirector::maybeRedirect()`:

1. Reentry flag: a second call in the same request fires the
   administrator only diagnostic action `rankkernel/redirect/reentry`
   and returns without sending.
2. `is_admin()`, `wp_doing_ajax()`, `REST_REQUEST`, `wp_doing_cron()`.
3. Sitemap query vars `rankkernel_sitemap` and
   `rankkernel_sitemap_xsl`, so the redirector never fights the sitemap
   router.
4. `RedirectTable::exists()` fails open when the table is missing.
5. `Normalizer::isBlockedSource()`: the homepage and bare domain both
   normalize to `/`, which is never a redirect source, so dispatch
   skips it instead of locking visitors out.
6. Unknown rule, inactive rule, unknown code, or unknown matcher:
   no response.
7. Empty destination for a redirect code, or a destination that fails
   validation at the send boundary: no response.
8. Unknown status code outside 301, 302, 307, 410, 451: no response.

The `rankkernel/redirect/allowed_hosts` filter supplies the external
host allowlist (empty by default). It is read at send time, so a host
added by filter applies to the next request without a settings write.
