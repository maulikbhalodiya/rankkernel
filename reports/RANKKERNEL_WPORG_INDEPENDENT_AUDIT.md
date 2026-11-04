# RankKernel — Independent WordPress.org Remediation Audit

**Auditor:** independent read-only audit session  
**Date:** 2026-11-04  
**Repository:** `maulikbhalodiya/rankkernel`  
**Branch:** `wporg/remediation-2026-11`  
**HEAD:** `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe`  
**PR:** #421 (`OPEN`, non-draft, base `main`, mergeable, CI state `UNSTABLE`)  
**Base `main`:** `390ee9a64eb04f45be917d939da6f01f823d956a`  
**Worktree used:** `/tmp/rk-wporg-audit` (detached at `b0ac1c8`)

## 1. Scope and constraints

This audit independently verifies the changes and evidence for the WordPress.org remediation branch. It covers:

- P1 — breadcrumb HTML output sanitization.
- P2 — schema JSON input limits.
- PR integrations #417, #419, #416.
- Open-PR relationships #420, #418, #415, #414, #342.
- Input sanitization inventory and high-risk data flows.
- Independent test/gate execution where the environment permits.
- WordPress.org compliance and release safety.

**Read-only constraint:** no source, test, branch, commit, tag, release-ZIP, or WordPress.org changes were made. No PR was merged or closed. Reports are the only files created.

> **Important limitation:** the WordPress.org reviewer's reported “48 incidences” was supplied only as ten examples. This audit verifies the complete 409-occurrence/16-file repository inventory and traces high-risk flows to sinks. It does **not** claim to reproduce the reviewer's 48-item scanner output.

## 2. Target verification (DONE)

| Item | Verified value | Result |
|---|---|---|
| Remote | `git@github.com:maulikbhalodiya/rankkernel.git` | DONE |
| Audited branch | `wporg/remediation-2026-11` | DONE |
| Branch HEAD | `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe` | DONE — matches expected |
| Base `main` | `390ee9a64eb04f45be917d939da6f01f823d956a` | DONE — matches expected |
| PR #421 | Open, non-draft, base `main`, head `b0ac1c8`, mergeable, `UNSTABLE` | DONE |
| PR size | 13 commits, 28 files, +711/−32 | DONE |

The audit proceeded only after the branch, commit, PR, and base matched the requested targets.

## 3. Findings summary

| ID | Severity | Area | Finding | Action |
|---|---|---|---|---|
| P1-01 | **Medium** | Breadcrumb tests | Repository unit tests do not execute the production `WP_HTML_Tag_Processor` + real `wp_kses` path; they use a `strip_tags` shim. Production behavior was verified against real WP 6.5.14 and 7.1.4 (17/17 cases), but regression coverage is weak. | Add an integration test/shim that exercises the real Tag Processor and KSES path. Non-blocking; no production defect found. |
| P1-02 | Low | Breadcrumb fallback | Fallback style-stripping regex accepts only double-quoted `style` attributes. It is dead code on supported WP 6.5+ (Tag Processor exists), but could mis-handle single-quoted style if the fallback ever runs. | Extend the fallback regex or document that it is legacy-only. |
| P2-01 | Low | Schema tests | Boundary tests cover 1 MiB+1 rejection but not exact 1 MiB acceptance; depth tests cover 70-deep but not 63/64/65; scalar/null/empty custom JSON is not pinned. | Add boundary tests; no code defect found. |
| P2-02 | Info | Schema depth | With PHP `json_decode(..., 64)`, nesting depth 64 fails; effective accepted depth is 63 levels. | Document the effective limit; not a defect. |
| P2-03 | Info | Schema save | `invalid-json` preserves the stored custom schema but still saves the other schema fields. This is intentional (a typo must not delete custom JSON), but “no partial write” should not be read as “nothing writes.” | Document behavior; no code change required. |
| P2-04 | Info | Schema UI | Browser JS uses a 2 MiB `MAX_IMPORT_BYTES`, while PHP enforces 1 MiB. The server is authoritative; the browser warns late. | Align or comment the intentional difference. |
| PR-01 | Low | #417 i18n | `rankkernel-analysis-format` is registered with no dependencies (`MetadataBox.php:83`) but receives `wp_set_script_translations()` at `:835-836`. Its script falls back to identity translation if `wp.i18n` is absent. | Add `wp-i18n` to the script dependencies. Non-blocking. |
| SO-01 | Low | CSV export | `escape_cell()` neutralizes leading `= + - @ TAB CR` but not leading LF/space. Unreachable from normalized stored data; defense-in-depth only. | Add `\n`/`ltrim` for symmetry. |
| SO-02 | Info | Redirect validation | `DestinationValidator` uses `wp_allowed_protocols()` (includes `mailto:`, `tel:`); such schemes degrade to inert relative paths because no host is present. `javascript:`/`data:` are rejected. | Optional: restrict redirect schemes to HTTP(S). No open redirect found. |
| SO-03 | Info | Regex runtime | Runtime `Matcher` uses a weaker catastrophic-pattern heuristic than save-time `RegexSafety`, but runs under reduced PCRE backtrack/recursion limits and fails closed. | Optional: reuse `RegexSafety` at runtime. No ReDoS found. |
| REL-01 | Info | Process | #420 duplicates #417; #418 duplicates #419; #415/#414 are independent and need rebasing; #342 is open/conflicting and is not in #421. | Clean up PR ownership after release decision; do not merge/close as part of this audit. |

**No Critical or High findings were verified.** No material security defect, injection path, stored-XSS path, header-injection path, or release-blocking WordPress.org violation was found.

## 4. P1 — Breadcrumb HTML output sanitization

### 4.1 Production design

`src/Modules/Breadcrumbs/functions.php` adds `sanitize_breadcrumb_html()` and applies it after the final public `rankkernel/breadcrumbs` filter. The sanitizer:

1. Captures the root `nav.rk-breadcrumbs` style only if it matches the single allowed custom property.
2. Removes unsafe `href`/`src` values before KSES.
3. Runs `wp_kses` with a narrow breadcrumb vocabulary.
4. Removes any remaining style that does not match the custom-property rule.
5. Restores only the validated root style.
6. Uses a regex fallback when `WP_HTML_Tag_Processor` is unavailable.

Allowed URL policy rejects `javascript:`, `data:`, `vbscript:`, and `file:` after trimming and case-folding. It permits `http(s)://`, root-relative, fragment, query, `./`, `../`, and simple relative paths. Protocol-relative `//host/path` is permitted; it is not script execution and was not treated as an XSS bypass.

Allowed CSS is limited to:

```
--rk-breadcrumb-separator: "<value>";
```

The value excludes quotes, backslashes, `<`, `;`, `{`, `}`, `(`, `)`, unless escaped; the exact-match regex is anchored. Extra declarations, `background:url(...)`, `color:red`, and CSS-escape payloads are removed.

### 4.2 Output-path coverage

| Output path | Sanitizer applied | Evidence |
|---|---|---|
| Getter `rankkernel_get_breadcrumbs()` | Yes — after filter | `functions.php:238-247` |
| Template tag | Yes — echoes sanitized getter | `template-tags.php:29-32` |
| Shortcode | Yes — returns sanitized getter | `BreadcrumbsModule.php:293-295` |
| Dynamic block | Yes — string filter return sanitized | `BreadcrumbsBlock.php:208-216` |
| Non-string filter return | Safe — generated markup retained | Getter/block checks |

### 4.3 Real-WordPress runtime verification

A probe (`/tmp/rk-audit-probe/breadcrumb.php`) was run on real WordPress 6.5.14 and 7.1.4 via `wp --skip-plugins eval-file`.

| Version | Tag Processor | `wp_kses` | Cases | Result |
|---|---|---|---|---|
| WP 6.5.14 | Yes | Yes | 17 | **17/17 PASS**, exit 0 |
| WP 7.1.4 | Yes | Yes | 17 | **17/17 PASS**, exit 0 |

Removed: `javascript:` (including entity/whitespace/tab variants), `data:`, `vbscript:`, `file:`, `onclick`, `onmouseover`, injected `background:url(...)`, `color:red`, CSS-escape payloads. Kept: safe `https://` and `/x`, exact separator property, inert script text after tag removal. A malicious first `nav` could set only a safe separator, not background or JavaScript.

### 4.4 Finding P1-01 — test gap (Medium)

The production path is safe in runtime testing, but the repository tests do not exercise it:

- `tests/bootstrap.php` does not provide `WP_HTML_Tag_Processor`.
- `tests/Unit/BreadcrumbsSecurityTest.php:95-103` and `tests/Unit/BreadcrumbsOutputTest.php:201-209` replace `wp_kses` with `strip_tags` plus two regexes.
- `tests/Unit/BreadcrumbsSecurityTest.php:423-462` tests the renderer, not the sanitizer.

**Impact:** a future refactor could break the real Tag Processor/KSES path while the unit suite stays green. This is a regression-risk finding, not a current vulnerability.

**Recommended action:** add a small integration test harness or WordPress test case that runs `sanitize_breadcrumb_html()` with the real Tag Processor and real KSES, including the custom-property restoration path and dangerous URL payloads.

### 4.5 Finding P1-02 — fallback regex (Low)

The fallback regex handles double-quoted `style` attributes only. On supported WP 6.5+ the Tag Processor exists, so the fallback is not used. If the fallback is retained for defensive compatibility, extend it to single-quoted attributes or document the limitation.

### 4.6 P1 checklist

| # | Check | Verdict |
|---|---|---|
| 1 | Final filter output sanitized on every output path | PASS |
| 2 | Non-string filter returns safe | PASS |
| 3 | Allowed HTML/attributes narrow | PASS |
| 4 | Dangerous URL schemes rejected | PASS (runtime 17/17 on WP 6.5/7.1) |
| 5 | Unsafe CSS/event handlers removed | PASS |
| 6 | Custom-property restoration cannot reintroduce unsafe markup | PASS (runtime probe) |
| 7 | Script contents inert after tag removal | PASS (visible text only) |
| 8 | WP 6.5 compatibility | PASS |
| 9 | Tests genuinely exercise production path | **FAIL** — P1-01 |

## 5. P2 — Schema JSON input limits

### 5.1 Implementation

| Control | Location | Verified behavior |
|---|---|---|
| `MAX_IMPORT_BYTES = 1048576` | `src/Admin/SchemaMetabox.php:55` | Byte cap before decode |
| `MAX_JSON_DEPTH = 64` | `src/Admin/SchemaMetabox.php:61` | Passed to every user-JSON decode |
| Custom save size gate | `:700` | `strlen($customRaw) > MAX_IMPORT_BYTES` before `json_decode` at `:703` |
| Custom invalid handling | `:701-706` | Sets `invalid-json`; no fatal |
| Import size gate | `:994-998` | `filesize` stat before `file_get_contents`; rejects false/0/over-cap |
| Import read re-check | `:1055` | Re-checks `strlen` after read |
| Import decode | `:1063-1065` | Depth 64; error and `is_array` check |
| Stored custom preservation | `:761-763` | Restores stored custom on invalid input |
| Payload sanitation | `:722`, `:1073` | `MetaPayload::sanitize()` after decode |

The import path never trusts `$_FILES['size']`; it stats the temporary file. `strlen` is a byte count, appropriate for a byte cap; `mb_strlen` would under-count multibyte input.

### 5.2 P2 checklist

| # | Check | Verdict |
|---|---|---|
| 1 | 1 MiB enforced before custom decode | PASS |
| 2 | 1 MiB enforced before import decode | PASS |
| 3 | Depth 64 everywhere | PASS |
| 4 | Invalid JSON safe; stored custom preserved | PASS |
| 5 | Boundary/failure tests | PART — P2-01 |
| 6 | `wp_unslash`, sanitization, meta ordering | PASS |
| 7 | Bypass checks (`strlen`, depth, flags) | PASS — no bypass found |

### 5.3 Tests

`cd /tmp/rk-wporg-audit && vendor/bin/phpunit --do-not-cache-result --filter 'SchemaMetabox'`

Result: **OK — 48 tests, 150 assertions, 1 unrelated PHP 8.5 deprecation, exit 0.**

Covered: 1 MiB+1 rejection, 70-deep nesting, malformed custom JSON preserving stored custom, oversized/garbage import, deep import, PHP payload, bad filename/mime/probe. Missing: exact 1 MiB acceptance, depth 63/64/65, scalar/null/empty custom. See P2-01.

## 6. PR integration review

### 6.1 PR #421 contents

PR #421 includes:

- `48289b3` — GPL license tags for five importer classes.
- `06d054c` — release archive exclusions.
- `9f3b3f5` — #416 sitemap `nosniff` header.
- `c39d50d` + `494e121` — #417 MetadataBox script translations.
- `ab1b62a` — REL-01 redirect insert-ID fix.
- `fbe3f7d` — #419 AuthorsProvider cache priming.
- `ecb26e0`, `ee5f3a6`, `fb725e7` — empty merge commits for #417/#419/#416.
- `cd16165`, `bcd838a`, `b0ac1c8` — P1/P2 changes and URL-policy tests.

No #414, #415, or #342 content is present. The branch is 28 files, +711/−32; no unrelated source change was found.

### 6.2 #417 — MetadataBox translations

`src/Admin/MetadataBox.php` registers script translations for the editor, analysis, and sidebar scripts. The analysis-format engine is also given translations.

**Finding PR-01 (Low):** `ENGINE_SCRIPTS['rankkernel-analysis-format']` has no dependencies (`MetadataBox.php:83`), but `wp_set_script_translations()` is called for it at `:835-836`. `analysis-format.js` falls back to identity if `wp.i18n` is absent, so analysis messages may not translate. Add `wp-i18n` to that script's dependencies.

Otherwise #417 is appropriate for WordPress.org.

### 6.3 #419 — AuthorsProvider cache priming

`AuthorsProvider.php:301-313` collects positive integer `post_author` IDs, de-duplicates them, and calls `cache_users()` only when the function exists. Output is unchanged. **PASS.**

### 6.4 #416 — sitemap `nosniff`

`Router.php:337-354` adds `X-Content-Type-Options: nosniff` to sitemap XML responses. `XslStylesheet.php:33-41,46-73` adds it to XSL and 404 responses. `headers_sent()` guards are present. Tests were updated. **PASS.**

### 6.5 Open PR relationships / decisions

| PR | Head | State | Relationship / recommendation |
|---|---|---|---|
| #420 | `e4ce13d` | OPEN, UNSTABLE | Duplicate/subset of #417. Leave open; close only with owner authorization after #421 lands. |
| #418 | `abb6029` | OPEN, UNSTABLE | Duplicate of #419. Leave open; close only with owner authorization. |
| #415 | `ac42783` | OPEN, UNSTABLE | Independent a11y metadata-editor announcements. Not in #421; needs rebase. |
| #414 | `cea8d5f` | OPEN, UNSTABLE | Independent breadcrumb performance memoization. Not in #421; needs rebase and re-review against P1 sanitizer. |
| #342 | `1d6f62e` | OPEN, CONFLICTING/DIRTY | Dependabot workflow bump; not merged. Resolve or defer separately. |

No PR was merged or closed during this audit.

### 6.6 Configuration/build checks

- `.distignore` excludes `.opencode-reviewer.yml` (line 6) and `.coderabbit.yaml` (line 5), plus `.github`, tests, vendor, docs, dev configs, and the internal audit findings file.
- A preliminary claim that `.opencode-reviewer.yml` was not excluded was **incorrect**; direct file inspection confirmed it is excluded. No release risk.
- A preliminary claim that #342 was merged was **incorrect**; `gh pr view` shows OPEN/CONFLICTING. The corrected state is used above.

## 7. Input sanitization audit

### 7.1 Inventory

`RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv` does **not** exist in the repository. An independent inventory was generated:

**File:** `/tmp/rk-audit-reports/RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv`  
**Rows:** 409 occurrences  
**Files:** 16  
**Lines carrying matches:** 229

| Superglobal | Occurrences |
|---|---:|
| `$_POST` | 226 |
| `$_GET` | 152 |
| `$_SERVER` | 21 |
| `$_FILES` | 10 |
| `$_REQUEST` / `$_COOKIE` / `$_SESSION` / `$_ENV` | 0 |

Per-file counts: SettingsPage 100, RedirectsPage 79, NotFoundPage 55, SchemaMetabox 30, SupportPage 26, SitemapSettingsPage 26, MetadataBox 25, SchemaSettingsPage 24, InstantIndexingPage 20, DashboardPage 8, Monitor/Logger 5, Support/SupportDelivery 3, Redirects/Redirector 3, Admin/HtaccessFile 3, InstantIndexing/KeyFileServer 1, Admin/UserProfileField 1.

The CSV is a raw occurrence inventory; its `classification` column is `not reviewed` by design. It is **not** proof that every occurrence is safe.

### 7.2 High-risk flow tracing

All high-risk sink classes were traced: SQL, uploads/file paths, redirects/headers, dynamic includes, `unserialize`, mail, and remote HTTP.

| Flow class | Representative paths | Protection observed | Class |
|---|---|---|---|
| Redirect create/edit | `RedirectsPage:1142-1633` → repository | Capability + nonce, unslash, sanitize, match/code allowlists, destination validation, length caps, regex safety | 1 |
| Redirect list/filter | `RedirectsPage:2301-2358` → `paginate()` | Order-by allowlist, forced ASC/DESC, `$wpdb->prepare`, `esc_like`, per-page clamp | 1 |
| CSV import/export | `RedirectsPage:1922-2013`, `CsvHandler` | Upload probe, mime/size checks, row re-validation, nonce-gated export, fixed headers | 1 |
| Redirect dispatch | `Redirector:207-271` | `REQUEST_URI` normalize/control-strip, DB-stored destination, dispatch-time re-validation, safe redirect | 1 |
| 404 logger | `Logger:262-301`, `MonitorRepository` | Normalize, query redaction, sanitize, truncate, prepared SQL, allowlisted order | 1 |
| 404 admin actions | `NotFoundPage:595-662` | Integer casts, existence checks, settings clamps, prepared SQL | 1 |
| Support upload | `SupportPage:96-135`, `SupportDelivery` | Capability + nonce, sanitize, `is_email`, size/mime caps, private temp dir, `wp_handle_upload` | 1 |
| Schema save/import | `SchemaMetabox:656-1083` | Type/allowed-type checks, 1 MiB cap, depth 64, upload probe, `MetaPayload::sanitize`, preserved custom | 1 |
| Metadata save | `MetadataBox:1193-1264` | String/array guards, central `MetaPayload::sanitize`, robots booleans, canonical sanitization | 1 |
| Instant Indexing | `InstantIndexingPage:578-667` | Numeric cast, literal actions, URL host allowlist + `hash_equals`, `wp_safe_remote_post` | 1 |
| Dynamic includes | `SettingsPage:407`, `SupportPage:364` | `sanitize_key` + fixed allowlist / literal view | 1 |
| `unserialize` | `MetaPayload:127`, `RedirectMapper:117`, `RankMathMapper:275` | Stored data, `allowed_classes=false`, no request-reachable gadget path | 1 |
| Key file | `KeyFileServer:73-179` | `hash_equals`, no candidate logging/echo, escaped body | 1 |

**Class 2 (potentially unsafe): none verified.** No unsanitized request value was found reaching SQL, file paths, includes, headers, mail headers, or unescaped output.

### 7.3 Residual / unreviewed areas

| ID | Area | Original status | Follow-up result |
|---|---|---|---|
| C1 | Stored-row cell escaping (404/redirect/IndexNow tables) | Needs evidence | Closed: `not-found.php:690-760` escapes URI/referer/agent; `redirects-list.php:247,254` escapes source/target; IndexNow rows `instant-indexing.php:453-457` escape URL/status/source/time/message. |
| C2 | Frontend stored SEO payload emission | Not reviewed | Closed by stored-output audit: `MetaPayload::sanitize`, `HeadRenderer` escaping, JSON-LD HEX flags. |
| C3 | Store-side sanitizers | Needs evidence | Closed: settings stores whitelist/sanitize on `set()`. |
| C4 | Log query/notice allowlists | Needs evidence | Call-site handling verified; normalizer internals sampled, no sink found. |
| C5 | CSV cell handling | Not reviewed | Closed: formula escaping verified; one low defense-in-depth gap (SO-01). |
| C6 | DestinationValidator/Normalizer internals | Needs evidence | Closed: controls/backslashes/hosts/schemes verified; no open redirect. |
| C7 | Remaining GET display flags | Sampled | Uniform `sanitize_key`/int-cast/literal compare/`esc_html`; not every flag line-by-line. |

The remaining C4/C7 sampling is a completeness limitation, not a known defect. No material stored-XSS or header-injection issue remains.

## 8. Stored-output follow-up (C2/C3/C5/C6)

A focused follow-up audit read the stored-value-to-sink paths and ran:

`vendor/bin/phpunit --do-not-cache-result --filter 'MetaPayload|Llms|Robots|Sitemap|Redirect'`

Result: **724 tests, 2,305 assertions, OK** (2 unrelated deprecations).

Verified safe:

- `MetaPayload::sanitize()` strips HTML/control characters, rejects unsafe URLs, drops unknown keys, caps schema nesting, and re-sanitizes on read.
- `HeadRenderer` escapes description with `esc_attr`, canonical/OG/Twitter URLs with `esc_url`; robots are emitted through core `wp_robots`.
- JSON-LD uses `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`; `</script>` and quote breakout are neutralized.
- `llms.txt`/`robots.txt` are plain-text bodies; no stored value becomes a header.
- Settings stores whitelist keys and sanitize on `set()`; role/user exclusions degrade to inert values.
- `DestinationValidator` rejects controls/backslashes, allowlists HTTP(S) hosts, and dispatch re-validates; no open redirect.
- CSV export neutralizes formula-leading cells; import re-validates every row.

Remaining low hardening notes: SO-01 (CSV leading LF/space), SO-02 (non-HTTP schemes degrade to inert paths), SO-03 (runtime regex heuristic is weaker but bounded/fail-closed).

## 9. Test and gate results

### 9.1 Independently run in the audit worktree

| Check | Exact command | Exit | Result |
|---|---|---:|---|
| PHP syntax | `find . -path ./vendor -prune -o -name '*.php' -print0 \| xargs -0 -n1 -P4 php -l` | 0 | 347 files, 0 failures |
| Composer validate | `composer validate --strict` | 0 | `./composer.json is valid` |
| Composer audit | `composer audit --locked` | 0 | No security advisories |
| PHPStan | `composer stan` | 0 | 179/179 files, no errors |
| PHPUnit | `vendor/bin/phpunit --do-not-cache-result` | 0 | 2,375 tests, 9,142 assertions, 8 deprecations, 0 failures/errors |
| JS tests | `composer test:js` | 0 | 352 tests, 352 pass, 0 fail |
| Node syntax | `node --check` over 21 JS files | 0 | No errors |
| PHPCS/WPCS | `composer lint` + direct `vendor/bin/phpcs --standard=phpcs.xml --report=full` | 0 | 0 findings (full report empty) |
| P1 focused | `vendor/bin/phpunit --do-not-cache-result --filter 'Breadcrumb'` | 0 | 158 tests, 409 assertions, 2 unrelated deprecations |
| P2 focused | `vendor/bin/phpunit --do-not-cache-result --filter 'SchemaMetabox'` | 0 | 48 tests, 150 assertions, 1 unrelated deprecation |
| Stored-output focused | `vendor/bin/phpunit --do-not-cache-result --filter 'MetaPayload\|Llms\|Robots\|Sitemap\|Redirect'` | 0 | 724 tests, 2,305 assertions, 2 unrelated deprecations |
| P1 real-WP probe | `wp --skip-plugins eval-file /tmp/rk-audit-probe/breadcrumb.php` on WP 6.5.14 and 7.1.4 | 0 | 17/17 PASS each |

### 9.2 Inspected CI (not rerun by this audit)

At `b0ac1c8`:

| Check | State |
|---|---|
| WPCS/PHPStan/PHPUnit — PHP 8.3 | Pass |
| WPCS/PHPStan/PHPUnit — PHP 8.4 | Pass |
| Psalm Security Scan | Pass |
| Removed Work Guard | Pass |
| Psalm | Pending/queued |
| AI Code Review | **Fail** per `gh pr checks` (30m10s); check-runs API reports **cancelled**. Non-gating opt-in tool; discrepancy reported as observed. |
| CI workflow | Completed success (2m6s) |

### 9.3 Plugin Check

Two independent runs were made against a staging copy. The first used a temporary folder slug `rk-audit-b0ac1c8`, which produced 1,460 `TextDomainMismatch` ERROR lines because the plugin text domain is `rankkernel`; those were a folder-slug artifact. The second run used the correct slug `rankkernel` and is the authoritative result:

| Metric | Result |
|---|---:|
| Exit code | 0 |
| ERROR lines | 23 |
| WARNING lines | 265 |
| Shipped-runtime errors | **0** |
| `DirectDB.UnescapedDBParameter` | 20 |

The 23 errors are all in non-shipped development files that `.distignore` excludes: 9 missing direct-file-access checks in tests/tools, 7 `OffloadedContent` in test fixtures, 4 hidden files (`.coderabbit.yaml`, `.distignore`, `.gitignore`, `.opencode-reviewer.yml`), 1 application-file detection (`tools/guard-large-deletions.sh`), 1 `strip_tags()` in `tests/bootstrap.php`, and 1 `eval()` in `tests/Unit/HtaccessFileTest.php`. None is in the release package.

The 265 warnings break down as:

| Warning class | Count | Assessment |
|---|---:|---|
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | 204 | False positive for view templates included inside method scope; not global variables, no security impact |
| `NonPrefixedConstantFound` | 20 | Test/tool constants; not shipped as global API |
| `NonPrefixedFunctionFound` | 17 | Test/tool helper functions; not shipped as global API |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter` | 20 | Shipped code; all 20 individually inspected in the prior audit and rechecked as a category here. Values are prepared/allowlisted; no request value reaches SQL unsafely |
| `unexpected_markdown_file` | 2 | `AGENTS.md`, `RANKKERNEL_WPORG_AUDIT_FINDINGS.md`; both excluded from release |
| `github_directory` | 1 | `.github/`; excluded from release |
| `WriteFile.ABSPATHDetected` | 1 | `tests/Unit/HtaccessFileTest.php`; test-only |

The prior clean-package audit reported **0 errors / 223 warnings** at `ab1b62a`; that exact count was not independently reproduced on a clean `b0ac1c8` package because building a release ZIP was prohibited. The current full-worktree count differs because it scans tests, tools, and dotfiles. No new warning class was found in shipped runtime, and no warning was classified as a material security defect.

### 9.4 Environment limitations

- PHP 8.5.12 was used for local runs; CI covers PHP 8.3/8.4.
- Psalm Security was inspected from CI, not independently rerun locally unless the test lane succeeded via Docker.
- Plugin Check requires a proper-slug staging copy; the first temporary copy was not authoritative.
- Multisite and real Apache/LiteSpeed `.htaccess` interception remain outside this audit's runtime coverage.
- The developer's packaging script/byte-identical rebuild remains unavailable.

## 10. WordPress.org compliance and release safety

| Area | Result | Evidence |
|---|---|---|
| Plugin header | DONE | Version 0.1.0, Requires WP 6.5, Requires PHP 8.2, GPLv2+, Text Domain `rankkernel`, Domain Path `/languages` |
| `readme.txt` | DONE | Requires 6.5, Tested up to 7.1, Requires PHP 8.2, Stable tag 0.1.0, GPLv2+ |
| Privacy disclosure | DONE | Local data; 404 log stores no IP; IndexNow key |
| External services | DONE | Support email via `wp_mail` to `rankkernelsupport@gmail.com`; IndexNow `api.indexnow.org`; key-check GET to own site |
| AI assistance disclosure | DONE | Present in `readme.txt` |
| Uninstall/purge | DONE | Custom tables/options/post meta purge documented and previously verified |
| Network/Update URI | DONE | No `Network` or custom `Update URI` header found |
| Hardcoded secrets | DONE | No token/password/secret pattern found in source |
| Release state | DONE | No origin tags; draft release `v0.1.0-rc-rel01` is `isDraft: true`, `publishedAt: null`; nothing published/uploaded |
| Dist archive exclusions | DONE | `.distignore` excludes `.opencode-reviewer.yml`, `.coderabbit.yaml`, `.github`, tests, vendor, docs, dev configs, internal findings |

**Residual WordPress.org risks:** the P1 test gap and the #417 i18n dependency are quality issues, not submission blockers. The audit did not find a security or guideline violation requiring rejection.

## 11. Final verdict

# APPROVED WITH NON-BLOCKING FOLLOW-UPS

The production P1 sanitizer and P2 JSON limits are correct and independently verified, including real-WordPress runtime tests for P1. The PR integrations are appropriate, with one low i18n dependency issue and normal PR-housekeeping items. No material security defect remains unresolved.

**Recommended non-blocking follow-ups before the next release:**

1. Add a P1 integration test using the real `WP_HTML_Tag_Processor` + KSES path.
2. Add `wp-i18n` to `rankkernel-analysis-format` dependencies.
3. Add P2 boundary tests (exact 1 MiB, depth 63/64/65, scalar/null/empty custom).
4. Optionally extend the P1 fallback regex and CSV `escape_cell()` hardening.
5. Decide PR ownership for #420/#418 duplicates, #415/#414 rebases, and #342 conflict after release authorization.

## 12. Deliverables

- `RANKKERNEL_WPORG_INDEPENDENT_AUDIT.md` — this report.
- `RANKKERNEL_WPORG_HANDOFF.md` — concise handoff for future sessions.
- `RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv` — 409-row raw inventory.

No source, test, branch, commit, tag, release, or WordPress.org state was modified by this audit.
