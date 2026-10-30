# RankKernel — WordPress.org Audit Findings (working document)

Temporary working document. **Do not ship in the release ZIP.**
Repo: `/home/web-dev-3/Local Sites/wordpress-test-site/app/public/wp-content/plugins/rankkernel`
Integration branch: `wporg/audit-2026-10` (from verified release tree + `main`).
Target: `main` first, then `release/wporg-0.1.0` from the exact verified `main` commit.

Status: `OPEN` · `IN_PROGRESS` · `FIXED_PENDING_REVIEW` · `VERIFIED` · `REJECTED_WITH_REASON`

## A. PR consolidation matrix

| PR | Purpose | Files | Decision | Reason |
|---|---|---|---|---|
| #397 | Remove dead `check_admin_referer()` false-branches | 11 src + tests | MERGE | No behavior change; superset of #409/#406 |
| #408 | Normalizer request-level cache | Normalizer + tests | MERGE | Bounded (500), key = case+home+raw, tests |
| #404 | RedirectsSettings static cache | RedirectsSettings + 9 tests | MERGE | Blog-keyed, invalidated by `set()`/`ensureSchema()` |
| #405 | 404 settings aria-describedby | not-found view + test | MERGE | IDs exist; tests assert |
| #401 | htaccess/llms aria-describedby | 2 views + test | MERGE | IDs exist; tests assert |
| #396 | htaccess/llms/robots aria | 3 views | CLOSE | Duplicate of #401; robots hint folded in |
| #402 | wp_unslash/type guards | 11 files | CLOSE/SUPERSEDE | Apply safe type guards directly; KeyFileServer semantic change rejected |
| #409 | Instant Indexing nonce | 2 files | CLOSE | Subset of #397 |
| #406 | Nonce cleanup 3 pages | 6 files | CLOSE | Subset of #397 |
| #410 | AI disclosure docs | 0 files | CLOSE | Empty diff |
| #407 | AI disclosure docs | 1 file | CLOSE | Docs-only duplicate; disclosure already tracked |
| #403 | readme Tested up to | 1 file | CLOSE | Diff is docs-only; does not match title; 7.1 is correct |
| #400 | Lazy DestinationValidator | 0 files | CLOSE | Empty; lazy init already present (`Redirector::destinationValidator`) |
| #398 | readme Tested up to 6.7 | 2 files | CLOSE | Incorrect downgrade; 7.1 verified current |
| #395 | RedirectsSettings cache | 6 files | CLOSE | Less correct duplicate of #404 |
| #387 | AI docs + workflow token downgrade | 4 files | CLOSE | Superseded; GH_PAT→GITHUB_TOKEN needs owner decision |
| #342 | Bump opencode-ai-reviewer | 2 workflow files | HOLD | Workflow-only; owner decision; not shipped |

## B. Findings

### RK-001 — Direct `<noscript><link rel=stylesheet>` with PHPCS suppression
- Severity: Medium
- Category: WP.org A / enqueued resources
- Files: `src/Admin/Views/sitemap-settings.php`, `src/Admin/Views/schema-settings.php`, `src/Admin/Views/not-found.php`
- Status: FIXED_PENDING_REVIEW
- Problem: inline `<link rel="stylesheet" href="...admin-nojs.css">` inside `<noscript>`, suppressing `WordPress.WP.EnqueuedResources`.
- Root cause: `AdminMenu::enqueueSharedStyles()` already enqueued `admin-nojs.css` everywhere; the inline tags were redundant. Worse, the CSS forced `[hidden]` panels visible even with JS, because it was not scoped to a no-JS state.
- Fix: enqueue `admin-nojs.css` through `wp_enqueue_style`; add a head inline script `rankkernel-js-flag` that adds `rk-js` to `<html>`; scope all no-JS reveal rules under `html:not(.rk-js)`. Removed all 3 inline tags and all 3 ignores.
- Tests: `AdminStylesTest` (handle, head flag, scoped CSS), `AdminMenuTest` (shared enqueue), `SitemapSettingsAdminTest` (no `<noscript><link`), `MonitorAdminTest` (aria IDs).
- Verification: focused 54 tests/168 assertions; full suite 2338/9034; phpcs clean. Pending independent re-read.

### RK-002 — `.htaccess` backup fallback writes beside root file
- Severity: High
- Category: WP.org B / filesystem
- Files: `src/Admin/HtaccessFile.php` (`backupDir()`, `backupPath()`, `save()`), `SettingsPage::saveHtaccess()`, `Views/sections/htaccess.php`
- Status: FIXED_PENDING_REVIEW
- Problem: when `backupDir()` failed, `backupPath()` returned `$this->path() . '.rankkernel-backup-<ts>'`, creating a backup in the WP root (potentially web-accessible and outside allowed storage).
- Fix: `backupPath()` returns `''`; `save()` fails with `reason=backup_unavailable` and does not modify the file; admin notice/banner explains the uploads directory must be writable. `backupDir()` now refuses non-writable parents before `mkdir()`, prefers `wp_mkdir_p()`, verifies the directory is writable, and fails closed if the guard files cannot be written.
- Tests: `HtaccessFileTest::test_save_refuses_when_no_safe_backup_location_exists` (no sibling backup, original unchanged, reason), normal backup/restore tests.
- Verification: 78 tests/301 assertions (HtaccessFile+SettingsPage), no PHP warnings; full suite green.

### RK-003 — Instant Indexing action marker read before nonce verification
- Severity: Low (reviewer-cited; no write before gate)
- Category: WP.org C / nonce
- File: `src/Admin/InstantIndexingPage.php` `postedAction()` 595-600, `maybeHandleSave()` 143-164, `requireAccess()` 649-665
- Status: OPEN (restructure for clarity)
- Problem: `$_POST['rankkernel_indexnow_action']` is read to choose the nonce action before the nonce is verified; code carries a `phpcs:ignore ... nonce verified by caller`.
- Verified: every target handler calls `requireAccess(<nonce>)` before any write; `current_user_can('manage_options')` is checked.
- Required: verify action selection and nonce in one obvious gate before dispatch; remove ignore; keep all six action nonces distinct.
- Tests: each action with missing/invalid nonce → 403 and no write; valid nonce + capability → action.

### RK-004 — `twitter_site` passed to sanitizer without scalar guard
- Severity: Medium
- Category: WP.org D / input
- File: `src/Admin/SettingsPage.php:745`
- Status: OPEN
- Problem: `MetaPayload::sanitizeTwitterHandle( wp_unslash( $_POST['twitter_site'] ) )` with no `is_string`/`is_scalar` check; array input can reach custom sanitizer (type error/array-to-string).
- Required: reject non-scalar; unslash then sanitize string; test array/malformed input.

### RK-005 — `focus_keywords` cast to string turns arrays into "Array"
- Severity: Medium
- Category: WP.org D / arrays
- File: `src/Admin/MetadataBox.php:1180-1224` (esp. 1196)
- Status: OPEN
- Problem: `(string) wp_unslash( $_POST['rankkernel_meta_focus_keywords'] )` on an array raises array-to-string warning and imports literal "Array".
- Required: require `is_string`; otherwise reject/ignore; same pattern at 1190/1213.
- Tests: string accepted/split/deduped; array and object rejected without warning.

### RK-006 — `(string) wp_unslash()` on custom separator / settings scalars
- Severity: Low
- Category: WP.org D / input
- File: `src/Admin/SettingsPage.php:700-752`
- Status: OPEN
- Problem: unconditional string casts before/without type checks; array input can produce warnings/invalid settings.
- Required: validate scalar/string per field; preserve existing sanitizers.
- Tests: array, nested array, empty, valid string.

### RK-007 — RedirectsPage GET casts need explicit type guards
- Severity: Low
- Category: WP.org D / input
- File: `src/Admin/RedirectsPage.php:818, 2045, 2096` (plus other GET reads)
- Status: OPEN
- Problem: `(string) wp_unslash($_GET[...])` patterns before allowlists; PHP arrays could reach string functions.
- Verified: values are unslashed, sanitized, allowlisted; no direct SQL/output. Harden type checks and remove unnecessary ignores.
- Tests: array/object inputs for `rk_path`, `rk_bulk`, `rk_open`, `rk_action`, filters.

### RK-008 — Schema import `$_FILES` workflow needs full validation proof
- Severity: High (pending audit)
- Category: WP.org D / uploads
- File: `src/Admin/SchemaMetabox.php:900-1100`
- Status: OPEN
- Problem: reviewer flagged `$_FILES['rankkernel_schema_import']`; current code checks structure, error, tmp_name string, `is_uploaded_file`, readability, name string. Must verify size, extension/MIME, JSON structure, depth, and safe parse.
- Required: bounded size, JSON-only, reject invalid/oversized/deep payloads, no path use, proper nonce+capability.
- Tests: missing file, upload error, oversized, non-JSON, deep JSON, valid JSON, unauthorized/missing nonce.

### RK-009 — `$_SERVER` handling (Logger, Redirector)
- Severity: Low (pending)
- Category: WP.org D / server vars
- Files: `src/Modules/Monitor/Logger.php:262, 579-602`; `src/Modules/Redirects/Redirector.php:207`
- Status: OPEN
- Verified: string checks, unslash, control-char stripping, referer query redaction, 255-char truncation, allowlisted keys; host comparison for IndexNow uses site host.
- Remaining: audit all `HTTP_*` reads for host-header/open-redirect/log injection; ensure logs escape on output.
- Tests: header injection, array `$_SERVER` values, missing keys, null bytes.

### RK-010 — Importer historical risks
- Severity: High (pending audit)
- Category: Importer correctness
- Files: `src/Modules/Importer/*`, `src/Rest/ImportController.php`, `assets/js/importer*`
- Status: OPEN
- Risks to verify: (1) exclusions `post:12` vs raw IDs; (2) preview cap 5000 vs execution scope; (3) empty `choices`/`rule` payloads; (4) progress totals overwritten vs accumulated; (5) execution of unpreviewed rows; (6) idempotency; (7) source metadata preservation.
- Required: prove behavior with unit/integration tests + browser flow; fix any mismatch.

### RK-011 — 203 security PHPCS ignores need case-by-case review
- Severity: Medium
- Category: Code quality / compliance
- Files: 16 files (SettingsPage 54, RedirectsPage 40, NotFoundPage 24, SchemaMetabox 21, SitemapSettingsPage 20, MetadataBox 17, SchemaSettingsPage 16, SupportPage 12, InstantIndexingPage 8, SupportDelivery 3, Logger 3, DashboardPage 3, HtaccessFile 2, Redirector 1, KeyFileServer 1, UserProfileField 1)
- Status: OPEN
- Required: remove where the underlying issue is fixed; keep only with a specific, accurate justification; never replace with blanket ignores.

### RK-012 — EscapeOutput suppressions review
- Severity: Low
- Category: Output escaping
- Files: SchemaMetabox 1105; Views/schema-metabox 56/59/70; Views/redirects 564; Views/redirects-list 263/272; Plugin 278; LlmsRouter 109; SchemaModule 281; Sitemaps/Router 259/316; XslStylesheet 55; Breadcrumbs/template-tags 30; KeyFileServer 141; ImportRunner 190; CsvHandler 467/484
- Status: OPEN
- Note: initial read suggests all are context-justified (pre-escaped fragments, JSON/XML/CSV/Markdown bodies). Verify each and document.

### RK-013 — Direct asset tags elsewhere
- Severity: Low
- Category: Enqueued resources
- Files: `AdminStyles.php:134` font preload; `SchemaModule.php:282` JSON-LD; `HeadRenderer.php:321` canonical
- Status: CLOSED — NOT APPLICABLE (legitimate frontend/head output, not admin asset enqueue).

### RK-014 — readme `Tested up to`
- Severity: Low
- Category: WP.org metadata
- File: `readme.txt`
- Status: VERIFIED — 7.1 is current and installable; #398/#403 are incorrect/no-op. Keep 7.1.
- Tests: PluginTest asserts 7.1.

### RK-015 — AI assistance disclosure / external services
- Severity: Low
- Category: WP.org disclosure
- Files: `docs/ai-disclosure.md`, `readme.txt`
- Status: OPEN (verify accuracy and presence in shipped tree; docs/ is not shipped but readme disclosure must be)
- Required: confirm readme discloses external services (IndexNow) and AI assistance accurately; no unsupported claims.

## C. Pending audit areas (to be expanded into findings)
- Nonce + capability on every admin/AJAX/REST handler; missing/invalid/insufficient-capability tests.
- SQL: all `$wpdb` usage, dynamic identifiers, uninstall/multisite.
- REST: permission callbacks, schema validation, output exposure.
- Redirects: normalization, open redirect, loops/chains, regex DoS, status codes, CSV import.
- Secrets: IndexNow key, logs, exports, error messages.
- External HTTP: timeouts, SSL, response validation, SSRF.
- Cron/locking/performance: importer locks, monitor pruning, sitemap cache.
- Lifecycle: activation/deactivation/uninstall/multisite data handling.
- i18n/a11y: text domain, escaping of translations, aria targets, keyboard, contrast.
