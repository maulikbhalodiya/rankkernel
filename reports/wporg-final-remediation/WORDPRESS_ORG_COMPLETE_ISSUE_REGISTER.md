# RankKernel — Complete WordPress.org Issue Register

Source reviewed: `https://github.com/maulikbhalodiya/rankkernel` at `release/wporg-0.1.0`  
Baseline commit: `eba5018e66bb9a3236594535b748cc04fdb7779b`  
Submitted package: `rankkernel-0.1.0.zip`, 902,973 bytes, 239 files, SHA-256 `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`

Final candidate for this remediation: source `010c3fe87b678b3565057052d7b460f88050e9ac` (`T6 remediation: no forced PHP limits, nonce-verified edit links, upload boundary sanitization`), ZIP `rankkernel-0.1.0-candidate-t6.zip`, 938,207 bytes, 240 files, SHA-256 `6f6cac8885429822781457f0fe45fd7900681c098edf9132808484524e9db35c`; previous build kept as `rankkernel-0.1.0-last.zip` (904,565 bytes, source `e310308`); PHPUnit 2414 tests / 9271 assertions.

This register covers all nine messages in the owner-supplied Gmail PDF (24 pages). Six messages contain review findings; three are automated file-receipt confirmations.

## Review messages

| Date | Review ID | Type | Summary |
|---|---|---|---|
| 2026-10-13 | `AUTOPREREVIEW` / `T1 13Oct26` / `P0TDX386976HGN` | Manual + AI review | Name includes redundant “Free”; potential trademark; arbitrary `.htaccess`; enqueue; nonces/permissions; 46 sanitization incidences; `rkRedirects` prefix; IndexNow disclosure; hardcoded uploads path; dashboard notices. |
| 2026-10-21 | File receipt | Automated | Updated ZIP `21_10-59-57` received. |
| 2026-10-22 | `PRTSUMAI` | Automated follow-up | “Free” still in display name; update header and readme. |
| 2026-10-27 | File receipt | Automated | Updated ZIP `27_04-16-49` received. |
| 2026-10-28 | `T2 28Oct26` / `P0TDX386976HGN` | Manual review | Enqueue `<noscript><link>` + PHPCS ignores; unsafe `.htaccess` backup fallback; Instant Indexing nonce; 46 sanitization incidences. |
| 2026-11-02 | File receipt | Automated | Updated ZIP `02_05-00-28` received. |
| 2026-11-03 | `T3 3Nov26` / `P0TDX386976HGN` | Manual review | Breadcrumb callback/filter return escaping; 48 sanitization incidences; `$_SERVER['REQUEST_URI']`. |
| 2026-11-05 | File receipt | Automated | Updated ZIP `05_10-32-28` received; this is the exact package audited here. |
| 2026-11-06 | `T5 6Nov26` / `P0TDX386976HGN` | Manual review | `MetadataBox::currentPostId()` nonce/object capability; 37 sanitization incidences including nine examples. |

## Issue rows

Statuses use the required vocabulary: `PASS`, `FIXED`, `ACCEPTED_WITH_REASON`, `BLOCKED`, `UNREVIEWED`. No row will remain `BLOCKED` or `UNREVIEWED` at final release.

| ID | Review date | Category | Reported location | Reported expression / concern | Current location | Assessment | Required action | Test evidence | Status |
|---|---|---|---|---|---|---|---|---|---|
| WO-01 | 2026-10-13, 2026-10-22 | A. Name/slug | Plugin header; `readme.txt` | Display name “RankKernel – Free SEO & Schema Engine”; “Free” redundant | `rankkernel.php:3`; `readme.txt:1` | Current header/readme use “RankKernel SEO & Schema Engine”; slug remains `rankkernel` | Confirm no display-name “Free” remains; keep slug | Header/readme assertion | FIXED |
| WO-02 | 2026-10-13 | A. Name/trademark | Plugin name/slug | AI detected “RankKernel” as a possible trademark | Header, readme, assets, contributor | No documentary ownership/trademark clearance in repository; name is distinctive but owner must confirm legal ownership | Owner decision; no code change | Owner statement required | ACCEPTED_WITH_REASON |
| WO-03 | 2026-10-13 | B. Dashboard notices | Global admin dashboard | Possible Guideline 11 dashboard hijacking | `rankkernel.php`, `AdminMenu.php`, all views | No global promotional/upgrade notice; only requirement notices on plugin screens and inline save notices | Verify no unscoped notices; keep notices scoped | Notice grep + screen tests | PASS |
| WO-04 | 2026-10-13 | C. Arbitrary server directives | `.htaccess` editor | Unrestricted user-supplied server configuration saved verbatim | `HtaccessFile::DIRECTIVES`; `SettingsPage::saveHtaccess()`; `Views/sections/htaccess.php` | Current implementation writes only two fixed, allowlisted directive blocks; request values are ids, unknown ids ignored | Keep allowlist; reject non-scalar/unknown ids | Unit tests for forged directives | FIXED |
| WO-05 | 2026-10-28 | L. Filesystem/backups | `HtaccessFile.php:527` | `copy($path, $backup)` could fall back beside root and be public | `HtaccessFile::storeBackup()`; `BACKUP_OPTION` | Current implementation stores bounded snapshots in a non-autoloaded database option; no filesystem backup fallback | Keep DB-only backup; fail closed if storage unavailable | Backup tests incl. unavailable storage | FIXED |
| WO-06 | 2026-10-13, 2026-10-28 | D. Enqueue | `sitemap-settings.php:148`; `not-found.php:244`; `schema-settings.php:222` | `<noscript><link rel="stylesheet">` and PHPCS ignores | `AdminStyles::enqueueNojsLayer()`; `AdminStyles::enqueueJsFlag()`; `AdminMenu.php:184-185` | Current no-JS CSS is enqueued through WordPress; `rk-js` head flag preserves no-JS fallback; no `<style>/<link>` in views | Verify no static asset tags or ignores remain | AdminStyles/view tests | FIXED |
| WO-07 | 2026-10-13, 2026-10-28 | E. Nonces/capabilities | `InstantIndexingPage.php:595-599` | `postedAction()` lacked nonce validation | `InstantIndexingPage::maybeHandleSave()`; `requireAccess()` | Capability + action-scoped nonce verified before dispatch | Keep gate before dispatch; test invalid/missing nonce | InstantIndexing auth tests | FIXED |
| WO-08 | 2026-11-06 | E. Nonces/capabilities | `MetadataBox.php:1684-1697`, `$_GET['post']:1694` | Unverified post ID could localize another post’s metadata/content without `edit_post` | `MetadataBox::currentPostId()`; `authorizedPostId()`; `localizedState()` | Exact post existence + `current_user_can('edit_post')` required; localized state revalidates | No GET nonce added; object authorization is the correct boundary | Unauthorized + reflection tests | FIXED |
| WO-09 | 2026-10-13, 2026-10-28, 2026-11-03, 2026-11-06 | F. Input sanitization | Multiple exact examples; 46 then 48 then 37 incidences | `wp_unslash()` input retained without context-appropriate sanitization or validation | 16 packaged input files; see `FULL_PLUGIN_INPUT_INVENTORY.csv` | 236 current superglobal expressions inventoried; boundary sanitizers/validators added or verified; 0 UNREVIEWED/BLOCKED | Keep boundary sanitization and regression tests | Input regression matrix + PHPUnit 2414 | FIXED |
| WO-10 | 2026-10-13 | I. Prefixes | `RedirectsPage.php:286` | JS global `rkRedirects` not prefixed | `RedirectsPage.php:282` | Current global is `rankkernelRedirects`; all handles/actions/options reviewed | Confirm no unprefixed public identifiers | Prefix grep + JS tests | FIXED |
| WO-11 | 2026-10-13 | J. External services | `IndexNowClient.php:27,256`; `readme.txt` | IndexNow used without terms/privacy disclosure | `readme.txt` Privacy + External Services | Current readme discloses endpoint, data, timing, and links to IndexNow documentation/terms; terms page contains privacy section; verified HTTP 200 | Keep disclosure; verify links | Readme link check | FIXED |
| WO-12 | 2026-10-13 | K. Path resolution | `HtaccessFile.php:205` | Hardcoded `ABSPATH . '/wp-content/uploads'` fallback | `HtaccessFile.php` | Current code no longer uses a hardcoded uploads path; backup uses DB option; other writers use `wp_upload_dir()`/`WP_CONTENT_DIR` | Verify all writes/reads use WordPress APIs | Path tests/static grep | FIXED |
| WO-13 | 2026-11-03 | H. Callback return escaping | `BreadcrumbsModule.php:158`; `BreadcrumbsBlock.php:130`; `rankkernel/breadcrumbs` | Filter return may inject unsanitized HTML | `Breadcrumbs/functions.php::sanitize_breadcrumb_html()`; shortcode/block paths | Current code sanitizes final filter output with KSES allowlist + URL/style validation; tests cover hostile filter output | Keep sanitizer after final filter; no double escaping | Breadcrumb security tests | FIXED |
| WO-14 | 2026-11-06 | F. Input sanitization | `NotFoundPage.php:777 rk_orderby`; `:174 rk_action`; `:272 rk_final`; `RedirectsPage.php:222 rk_action`; `SchemaMetabox.php:693 rankkernel_schema_custom`; `:353 rankkernel_schema_msg`; `Logger.php:262 REQUEST_URI`; `SettingsPage.php:497 rk_htaccess_directives`; `MetadataBox.php:1212 rankkernel_meta_focus_keywords` | Exact November 6 examples | Same files; current lines confirmed | All nine examples sanitized/validated at the boundary and traced to their sinks | Keep focused regression tests | Focused regression tests | FIXED |

## Historical categories A–N coverage

| Category | Status | Notes |
|---|---|---|
| A. Plugin name, slug, trademark, ownership | FIXED / OWNER DECISION | Display name fixed; legal ownership cannot be verified from repository. |
| B. Dashboard notices | PASS | No global promotional notices; only scoped requirement/inline notices. |
| C. Arbitrary `.htaccess`/server configuration | FIXED | Fixed directive allowlist only. |
| D. CSS/JS enqueue | FIXED | WordPress enqueue APIs; no static `<style>/<link>` output. |
| E. Nonces and capabilities | FIXED | Instant Indexing fixed; MetadataBox exact-post authorization fixed and tested. |
| F. Input sanitization/validation | FIXED | 16 files, 236 current superglobal expressions; 0 UNREVIEWED/BLOCKED. |
| G. Output escaping | PASS | 1,244 output statements inventoried; 1,231 PASS, 13 ACCEPTED_WITH_REASON, 0 UNREVIEWED. |
| H. Callback return-value escaping | FIXED | Breadcrumb final filter sanitizer; shortcode/block tests. |
| I. Prefixes and JS globals | FIXED | `rankkernelRedirects` and prefixed handles/actions. |
| J. IndexNow/external-service disclosure | FIXED | Readme discloses both Support email and IndexNow; links verified. |
| K. Plugin/content/uploads paths | FIXED | WordPress APIs; no hardcoded uploads fallback. |
| L. Filesystem writes/backups | FIXED | DB snapshots; atomic write; fail closed. |
| M. REST/AJAX/import/upload | PASS | 7 REST routes + 1 AJAX action inventoried; permission/nonce/upload checks verified. |
| N. Detailed plugin guidelines | PASS | A–N compliance matrix; Plugin Check 0 errors; final decision ready for independent audit. |

## Limitations

- The exact WordPress.org scanner output is not available. The reported incidence counts are reproduced from the email text; the full-plugin inventory is generated independently from the shipped source.
- The trademark/ownership question cannot be proven from source code; it is explicitly an owner decision.
- No WordPress.org approval or independent Server approval is claimed.
