# IndexNow / Instant Indexing Behavioral Spec

*Sources for the `instant-indexing` module. Behavioral facts only. This document is the
legitimate reference for implementation under `docs/clean-room-policy.md` rule 4.1.2.*

## 1. Purpose and scope

`instant-indexing` is already a registered module id in `ModuleRegistry` with a dashboard
card marked "Planned", but it has no implementing class. This spec defines the behavior for
issue #80 so the module can be written from this document without opening competitor source.

The module notifies participating search engines that a specific URL has changed. It signals
deltas only. It never replaces XML sitemaps and never performs a full-site submission.

## 2. Protocol facts

All facts in this section come from official vendor documentation, not from any plugin's code.

| Fact | Value | Source |
|---|---|---|
| Global endpoint | `POST https://api.indexnow.org/indexnow` | indexnow.org/documentation |
| Participating endpoints | `api.indexnow.org`, `www.bing.com`, `yandex.com`, `searchadvisor.naver.com`, `search.seznam.cz`, `indexnow.amazonbot.amazon`, `indexnow.yep.com` | indexnow.org/faq |
| Batch content type | `application/json; charset=utf-8` | indexnow.org/documentation, Yandex reference |
| Allowed methods | `GET` and `POST` only, anything else returns `405` | Yandex reference |
| Payload fields | `host`, `key`, optional `keyLocation`, `urlList` (array of strings) | indexnow.org/documentation |
| Field casing | case sensitive, exactly as written above | indexnow.org/documentation |
| `host` value | bare hostname, no scheme, no trailing slash | indexnow.org/documentation |
| Maximum batch | 10000 URLs, excess returns `422` | indexnow.org/faq, Yandex reference |
| Key alphabet | `a-z`, `A-Z`, `0-9`, `-` | indexnow.org/documentation, Yandex reference |
| Key length | minimum 8, maximum 128 characters | Yandex reference |
| Key file name | exactly `{key}.txt` | Yandex key reference |
| Key file location | site root is the recommended placement | indexnow.org/documentation |
| Key file content | exactly the key string, UTF-8, text only, no HTML markup | Yandex key reference |
| Key file reachability | public, no login, no firewall, no IP restriction | indexnow.org/faq |
| Key file content type | **not documented as a required value.** Text only is the documented requirement. We send `text/plain`. | derived from Yandex key reference |
| `keyLocation` when omitted | allowed when the key file sits at the site root | Yandex reference |
| `keyLocation` scope effect | a key outside the root restricts valid URLs to that directory subtree | indexnow.org/documentation |
| Host matching | submitted URLs must belong to the `host` serving the key file, exact match, no `www` to apex aliasing | indexnow.org/documentation, Yandex reference |
| Subdomains | each subdomain is a separate host and needs its own key | Yandex key reference |
| Key verification cadence | Yandex checks the key on **every** request, so the key file must stay live permanently | Yandex key reference |
| Key rotation | not required on a schedule. Rotate by publishing the new key file, submitting with it, and expecting `202` until verified | Yandex key reference |

### 2.1 Response codes

| Code | Meaning | Our treatment |
|---|---|---|
| `200` | URL received | success |
| `202` | received, key validation pending | success, **not** an error |
| `400` | invalid request format | permanent failure, log and stop |
| `403` | key not valid or not found | permanent failure, log and stop, surface to admin |
| `422` | URLs do not belong to `host`, or key does not match the schema | permanent failure, log and stop |
| `429` | too many requests | transient failure, honor `Retry-After` |
| `405` | method not allowed | not produced by us, logged if seen |
| `5xx` | server error | transient failure, eligible for retry |

`200` and `202` both mean **received**, never crawled or indexed. The admin surface must not
claim a URL was indexed.

Bing's Get Started page omits `202` from its table. The IndexNow FAQ and Yandex both document
`202` as the normal first-response state for an unverified key. We treat `202` as success
because the protocol documentation is authoritative over a single vendor's summary table.

### 2.2 Rate and debounce guidance

The sources diverge, so we take the stricter value.

* IndexNow FAQ: wait at least 5 minutes before resubmitting the same URL.
* Yandex: do not recommend sending the same URL too often, 10 minute intervals if needed.

**Decision: 10 minutes.** It satisfies both. Yandex publishes no numeric quota, and the Bing
URL Submission API quota of 10000 per day belongs to a different API and must not be copied here.

### 2.3 What may be submitted in this version

* Published, publicly viewable post URLs.
* Published, publicly viewable term URLs, for taxonomies that are publicly queryable.
* The URL a post or term had immediately before it was trashed, submitted as its `404` or `410` URL.
* The previous and current URL of a post whose permalink changed, so the old URL is signalled too.

Not submitted: revisions, autosaves, drafts, private posts, password protected posts, posts whose
post type is not publicly viewable, terms in a non public taxonomy, and any URL that does not
belong to the site host.

**Deferred, not forgotten.** The reference documentation says redirect sources and targets can and
should be submitted, and the existing `RedirectsModule` is the natural place to hook that. It is
deliberately out of this version so the module does not gain a cross-module dependency on first
implementation. It stays valid work for a follow-up issue.

## 3. WordPress behavioral observations

Observed from **public repositories** at recorded commits, for behavior only. No expression from
these projects is reused. The local copies of these plugins were not opened for this feature.

| Project | Commit | Behavior observed |
|---|---|---|
| `microsoft/indexnow-wordpress-plugin` | `7c0550e37570335c9dcfd3fd1cbcf41d951a639c` | Key generated from a UUID with dashes stripped, giving 32 hex characters. Key file served virtually on `template_redirect` with `text/plain` and `X-Robots-Tag: noindex`, never written to the webroot. Blocking request so the status code can be read. Restart batch cap and a retry queue with exponential backoff. |
| `rankmath/seo-by-rank-math` | `4b5ba3e2b754adfd4e0763909e93c61e6fdf7d96` | Key file served virtually on the `wp` hook against a filterable location. Key location is `trailingslashit(home_url()) . key . '.txt'`. Skips revisions and autosaves explicitly. Snapshots the pre-save permalink on `wp_insert_post_data` so a trashed post submits the URL it had before trashing. In-request dedupe plus a same-URL throttle. Bulk submission sends one request for all selected IDs. |
| `peterwilsoncc/simple-search-submission` | `1016829a320167d872363b17c98cb2d0fbaa5cbf` | Key never appears in admin HTML, JavaScript, or any endpoint. Key file served from a rewrite rule with strict equality and a `403` on mismatch. Uses `wp_safe_remote_post`. Submits the previous and current URL pair so a slug change deindexes the old URL. Handles sites with an empty permalink structure. |
| `crawlwp/mihdan-index-now` | `13d4ccbd1aebb28445f302522441392333740f9e` | Serves the key on `parse_request` and sends `DONOTCACHEPAGE`. Request timeout raised to 30 seconds. Failure status recorded with the code. |

### 3.1 Failure modes observed in the wild

These are the traps to avoid. Each is recorded here so the implementation does not repeat it.

1. **Key returned to the browser.** The official plugin exposes the raw key through a REST read
   consumed by its admin app, and Rank Math renders it in a readonly settings field and returns it
   from a reset endpoint. Any stored cross-site scripting flaw then discloses the key.
2. **No revision or autosave guard.** The official plugin submits from `transition_post_status`
   with no `wp_is_post_revision` or `wp_is_post_autosave` check, so an autosave can trigger a
   submission. This wastes crawl quota.
3. **Telemetry headers.** Both the official plugin and Rank Math send an `X-Source-Info` header,
   and Rank Math adds a pseudonymous `User-Agent` derived from a hash of the site URL. Neither
   header is required by the protocol.
4. **Server-side fetch of a user supplied URL.** The official plugin performs
   `wp_safe_remote_get` against a manually entered URL to check for a noindex header, which lets
   an authenticated user make the server issue a request.
5. **Physical key file in the webroot.** Older tutorials write a real file, which breaks on
   multisite, on read-only filesystems, and on deploys that replace the webroot.
6. **Silent failure from a non-blocking request.** Setting `blocking` to `false` makes every
   failure invisible and makes `429` backoff impossible. No maintained implementation surveyed
   does this.

## 4. Our design decisions

Where a choice was open, this is the decision and the reason.

| Decision | Reason |
|---|---|
| The key never leaves PHP. No admin field, no REST read, no localized script variable, no HTML attribute. The admin surface shows only whether a key exists, plus a regenerate action. | Section 3.1 item 1. Satisfies the project rule that no secret reaches the browser. |
| Serve the key file virtually. No file is written to disk. | Section 3.1 item 5. |
| Serve on `parse_request`, and additionally accept a query argument when the permalink structure is empty. | A path based key file silently 404s on plain permalinks, which breaks verification. Observed gap in the official plugin. |
| Send neither `X-Source-Info` nor a custom `User-Agent`. | Section 3.1 item 3 and the project no-telemetry rule. Only the documented `Content-Type` is sent. |
| Never issue a server-side request to a user supplied URL. Submitted URLs are validated against the site host and are not fetched. | Section 3.1 item 4. Removes the SSRF surface entirely. |
| Use `wp_safe_remote_post` and stay blocking, with an explicit timeout. | Blocking is required to read the status code. `wp_safe_` blocks private address ranges. A non-blocking request is never used. |
| Treat `202` as success and label it "key pending verification" in the log. | Section 2.1. A new key always produces `202` first. |
| Debounce the same URL for 10 minutes. | Section 2.2, the stricter of the two published values. |
| Snapshot the permalink before a status transition so a trashed or redirected URL is submitted as it was. | Observed in Rank Math, and required by section 2.3 which says deleted URLs are submittable. |
| No dependency on the sitemaps module. | IndexNow signals deltas and needs no sitemap. Depending on sitemaps would force the module off for no reason. |
| The module defaults to off because an absent id in `rankkernel_modules` evaluates to disabled. | `ModuleEnableMap::isEnabled` returns false for unknown ids, so no activation seed change is needed and no outbound request can happen until an admin opts in. |
| Automatic submission defaults to **off** even once the module is enabled. Enabling the module therefore still sends nothing until an admin ticks a second, separate control. | Owner decision, 2026-09-24. This is the safest posture: two deliberate opt-ins stand between an install and any outbound request. |
| The admin surface is a dedicated `InstantIndexingPage` submenu. | Owner decision, 2026-09-24. Matches the existing `RedirectsPage` and `NotFoundPage` precedent and keeps a submit form plus a log off the toggle-only dashboard and off the already long General Settings page. |
| No persistent retry queue and no cron. Failures are logged and marked retryable, and a failed URL is only retried on its next real publish. | Owner decision, 2026-09-24. A custom table, a per-minute cron and backoff machinery is a large new surface. The alternative the surveyed code uses, a non-blocking fire-and-forget request, is explicitly rejected by section 3.1 item 6. |
| Batch cap 10000 per request, chunked in that order. | Section 2, the documented maximum. |
| Log the most recent 50 outcomes, stored outside the autoloaded settings option. | An autoloaded option must stay small. The log is a diagnostic aid, not an audit ledger. |

## 5. Storage layout

All names use the `rankkernel_` prefix per clean-room rule 5.

| Name | Autoload | Contents |
|---|---|---|
| `rankkernel_instant_indexing_settings` | yes | `api_key` (default empty), `auto_submit` (default **false**) |
| `rankkernel_instant_indexing_log` | no | last 50 entries of `url`, `code`, `source`, `time`, `message` |

Per-post state needed for debounce and for remembering the previously submitted permalink uses
post meta `_rankkernel_indexnow` holding `last_url` and `last_submitted`.

## 6. Test matrix

| Behavior to prove | How |
|---|---|
| Disabled module is inert | Enabled map off, assert zero hooks registered and the client is never constructed |
| Key format is valid | Assert 8 to 128 characters matching `[A-Za-z0-9-]` |
| Key is never exposed | Assert the key string does not appear in rendered admin HTML, in any REST response, or in any enqueued script |
| Key file serves only the key | Assert the exact key as plain text with `noindex`, and a non matching path is not intercepted |
| Plain permalink fallback | Empty permalink structure, assert the query argument form serves the key |
| Payload is spec exact | Assert `host`, `key`, `keyLocation`, `urlList` casing and the content type header |
| Batch cap enforced | 10001 URLs are chunked, never sent as one request |
| Host validation | A URL on another host is rejected without any request being made |
| `202` is success | Assert a `202` response is recorded as accepted, not as a failure |
| `429` is transient | Assert the response is recorded as retryable and no tight retry loop runs |
| Autosave and revision are skipped | Assert the submit path does not run for either |
| Debounce holds | Two publishes of the same URL inside 10 minutes submit once |
| Trashed URL is submitted as it was | Snapshot before the transition, submit the pre-trash permalink |
| Manual submit is authorized | Assert a request without `manage_options` is rejected and without a valid nonce is rejected |
| No outbound request on failure paths | Assert zero client calls when the key is absent or the host does not match |
| Enabling the module alone sends nothing | With the module enabled and `auto_submit` at its default, assert a publish produces zero client calls |
| Auto-submit opt-in is honored | With `auto_submit` true, assert a publish of a public post produces exactly one client call |
| No cron is registered | Assert the module registers no `wp_schedule_event` and no scheduled hook |
| Regenerating the key requires authorization | Assert the regenerate action is rejected without `manage_options` and without a valid nonce, and that the stored key is unchanged |

## 7. Provenance statement

Protocol facts are from official IndexNow, Bing, and Yandex documentation as cited in section 2.
WordPress behavioral observations are from public repositories at the commits recorded in section 3.

No competitor source was opened while writing the implementation for this feature. All RankKernel
code is original and written from this document. The local copies of the audited plugins under
`wp-content/plugins/` were deliberately not read for this feature.
