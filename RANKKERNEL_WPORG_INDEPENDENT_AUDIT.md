# RankKernel — Independent WordPress.org Remediation Audit

Date: 2026-11-05  
Auditor role: independent code / security auditor. No implementation work performed.

## 1. Exact audit target

| Item | Verified value |
|---|---|
| Repository | `maulikbhalodiya/rankkernel` |
| Target branch | `wporg/remediation-2026-11` |
| Target commit | `a3fb941290b6e0cf7e11ec997fd687272b71bf7b` |
| PR | #421 (OPEN, mergeable, not draft) |
| PR base | `main` at `390ee9a64eb04f45be917d939da6f01f823d956a` |
| Worktree | `/tmp/opencode/audit-a3fb941` (detached, clean source) |

The branch contains the release baseline `ab1b62a` (REL-01, five importer license tags, corrected `.distignore`, prior remediation), the P1 breadcrumb sanitizer, P2 schema bounds, the P3 protocol-relative URL fix, the real-KSES integration test, and PR #417/#419/#416 integrations.

## 2. Method

- Inspected the exact commit tree, full diff `390ee9a..a3fb941`, and the relevant call chains.
- Ran project gates locally in the detached worktree with a copied `vendor` tree and regenerated autoloader.
- Built a clean 239-file production source package from `a3fb941` using `.distignore`, installed it on a disposable WordPress 7.1.4 site, and ran Plugin Check plus real WordPress KSES/shortcode/block tests.
- Did not modify source, merge/close PRs, push source commits, create tags, or build a release ZIP.

## 3. Findings

### P1-01 — Breadcrumb filter output — FIXED / VERIFIED

Files: `src/Modules/Breadcrumbs/functions.php`, `blocks/BreadcrumbsBlock.php`, `BreadcrumbsModule.php`.

Verified:
- `sanitize_breadcrumb_html()` runs after the final `rankkernel/breadcrumbs` filter in both `rankkernel_get_breadcrumbs()` and `BreadcrumbsBlock::render()`.
- Narrow `wp_kses()` allowlist; no `esc_html()` on whole markup.
- `href`/`src` are validated before KSES by `is_safe_breadcrumb_url()`, which rejects control/space/backslash characters, protocol-relative `//`, and `javascript:`, `data:`, `vbscript:`, `file:` schemes.
- Event handlers, script/img/iframe/object/embed tags, event attributes, and invalid styles do not survive.
- The only permitted style is the exact `--rk-breadcrumb-separator` quoted property. A validated root style is restored after KSES when WordPress strips custom properties.
- Non-string filter returns keep the generated markup.
- Actual WordPress 7.1.4 test through both `do_shortcode()` and `BreadcrumbsBlock::render()` with a hostile filter returned: safe `/safe` link and separator style preserved; `javascript:`, `onclick`, `<script>`, `<img onerror>`, `//evil.example`, and `color:red` removed; no `debug.log` entries.

Residual non-blocking: KSES removes the script tag but leaves inert text content (e.g. `alert(3)`); it cannot execute. Absolute `http(s)` links supplied by a code-level filter remain allowed; this is an external-link policy choice, not script execution.

### P2-01 — Custom schema JSON bounds — FIXED / VERIFIED

File: `src/Admin/SchemaMetabox.php`.

Verified:
- `MAX_IMPORT_BYTES = 1,048,576` checked before decode.
- `MAX_JSON_DEPTH = 64` used for the textarea and import paths.
- Oversized/deep/invalid JSON sets `invalid-json`; existing stored schema is preserved.
- Valid JSON still persists; tests cover 1,048,577-byte input, 70-level nesting, invalid JSON, valid JSON, and persistence.

### P3-03 — Protocol-relative breadcrumb URL — FIXED / VERIFIED

`is_safe_breadcrumb_url()` now rejects `//evil.example/x`, raw backslashes, control/space characters, and unsafe schemes. `/about/`, `../up`, `?q=1`, and `#anchor` remain valid. Unit policy tests cover these cases.

### P3-04 — Real KSES integration test — ADDED / VERIFIED

`tests/Integration/breadcrumb-kses.php` runs against real WordPress KSES and `WP_HTML_Tag_Processor`; CI job `breadcrumb-kses` provisions WordPress 7.1.4 and passed. It covers safe markup/style preservation and script/img/event/scheme/style removal.

### P3-05 — Input occurrence audit coverage — LIMITED / DISCLOSED

The plugin contains 409 superglobal accesses: `$_POST` 226, `$_GET` 152, `$_SERVER` 21, `$_FILES` 10; no `$_REQUEST`/`$_COOKIE`/`$_SESSION`. The supplied reviewer list contains only ten examples; the complete 48-line scanner output is unavailable. The ten examples were traced to guarded sinks (nonce/capability, type checks, allowlists, normalizers, `MetaProvider`/`MetaPayload` sanitizers, prepared SQL). The complete 409 occurrences were inventoried, but this audit does not claim line-by-line reproduction of the missing 38 scanner hits.

### PR findings

| PR | State | Audit decision |
|---|---|---|
| #417 | Included in #421 | Correct MetadataBox translation registration; superset of #420. |
| #420 | Open | Duplicate/subset of #417; close after #421 confirmation. |
| #419 | Included in #421 | Correct `cache_users()` author cache priming; equivalent to #418 with clearer tests. |
| #418 | Open | Duplicate of #419; close after #421 confirmation. |
| #416 | Included in #421 | Correct sitemap/XSL `nosniff` header paths; tests cover success/404/500/XSL. |
| #415 | Open | HOLD — a11y; rebase after #417 and perform manual announcement test. |
| #414 | Open | HOLD — TrailBuilder memoization; cache key/multisite/filter invalidation not proven. |
| #342 | Open | Tooling-only; title/diff version mismatch; keep separate from release. |

## 4. Tests independently run

Exact commit `a3fb941`, detached worktree:

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `vendor/bin/phpunit` | OK — 2375 tests / 9150 assertions |
| WPCS | `composer lint` | exit 0 |
| PHPStan | `vendor/bin/phpstan analyse --level=6 src/ --memory-limit=2G` | `[OK] No errors` |
| JS tests | `composer test:js` | 352 pass / 0 fail |
| JS syntax | `node --check` all `assets/js/*.js` | clean |
| Composer validate | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Plugin Check | clean 239-file source package on WordPress 7.1.4 | 0 errors / 223 warnings |
| Breadcrumb runtime | real WP 7.1.4 `do_shortcode()` + block render with hostile filter | sanitized; no debug log |
| CI | PR #421 at `a3fb941` | PHP 8.3/8.4 gates, Psalm, Psalm Security, Breadcrumb KSES, Removed Work Guard pass; AI review skipped/pending |

Plugin Check warnings: 203 `PrefixAllGlobals.NonPrefixedVariableFound` (template-local variables in views required inside methods) and 20 `DirectDB.UnescapedDBParameter` (custom-table SQL with prepared values, int-cast IDs, trusted/validated identifiers). No new errors.

## 5. Limitations

- The exact WordPress.org submitted ZIP and the Playground hash `406c9808a108cdbc350a957e14d1a9d2` remain unresolved; the source line mapping strongly indicates `ab1b62a`.
- The complete reviewer 48-line list is unavailable; the 409-occurrence matrix is inventoried but not exhaustively line-traced in this audit.
- WordPress 6.5.14 runtime for the new sanitizer was tested by the developer, not independently re-run here; WordPress 7.1.4 was independently tested.
- Plugin Check warnings were grouped by defensible category, not every line re-proven.
- No release ZIP was built or approved by this audit.

## 6. Verdict

**APPROVED WITH NON-BLOCKING FOLLOW-UPS.**

The P1 callback-output defect and P2 schema hardening are fixed and verified; P3-03/P3-04 are fixed and independently exercised. No unresolved P0/P1/P2 executable vulnerability was found. Remaining items are the inert script text, the input-matrix evidence gap, and the open PRs (#415/#414/#342) that remain outside this remediation.
