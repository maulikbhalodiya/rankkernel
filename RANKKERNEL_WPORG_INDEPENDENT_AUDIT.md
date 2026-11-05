# RankKernel — Independent WordPress.org Remediation Audit

Auditor: independent review role  
Date: 2026-11-05  
Target: `wporg/remediation-2026-11` at `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe`  
PR: #421, base `main` (`390ee9a`)

## 1. Verified repository state

| Item | Verified value |
|---|---|
| Remote target branch | `origin/wporg/remediation-2026-11` |
| Target HEAD | `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe` |
| PR #421 | OPEN, mergeable, not draft, base `main`, head `b0ac1c8` |
| `origin/main` | `390ee9a64eb04f45be917d939da6f01f823d956a` |
| Audit worktree | `/tmp/opencode/audit-b0ac1c8` (detached at target, clean) |
| Diff `390ee9a..b0ac1c8` | 28 files, +711/-32 |

All findings below are based on source read from the exact target commit, not developer summaries.

## 2. P1-01 — Breadcrumb callback output

### Implemented protection

- `src/Modules/Breadcrumbs/functions.php` defines `sanitize_breadcrumb_html()`.
- Both `rankkernel_get_breadcrumbs()` and `BreadcrumbsBlock::render()` invoke it only when the `rankkernel/breadcrumbs` filter returns a string.
- Non-string filter returns keep the generated markup.
- The sanitizer uses a narrow `wp_kses()` allowlist: `nav`, `ol`, `li`, `a`, `span`, `div`, `p`, `section`, `b`, `strong`, `em`, `i`, `br`; attributes limited to `class`, `href`, `rel`, ARIA attributes and `style`.
- Before KSES, every `href`/`src` is checked by `is_safe_breadcrumb_url()`, which rejects `javascript:`, `data:`, `vbscript:` and `file:`.
- Style is limited to the exact `--rk-breadcrumb-separator` quoted property. The validated root style is re-applied after KSES because core KSES may strip custom properties.

### Independent WordPress staging

Created a clean WordPress 7.1.4 site at `/tmp/opencode/wp-audit-1567`, installed the clean 239-file production source from the target commit, enabled Breadcrumbs, and added an audit-only `mu-plugin` filter returning:

`<script>alert(9)</script><img src=x onerror=alert(8)><a href="javascript:alert(7)" onclick="alert(6)">Bad</a><a href="//evil.example/x">Protocol relative</a><span style="color:red">styled</span>`

Observed front-end output:

- `<script>` tag removed; no `<img>`; no `onerror`; no `onclick`; no `javascript:`; no `color:red`.
- The legitimate `<nav class="rk-breadcrumbs" style="--rk-breadcrumb-separator:&quot;/&quot;;">` and its list/links remained.
- The sanitizer preserved the safe custom property in real WordPress.
- The text `alert(9)` remains as inert text after the script tag is removed. It is not executable.
- **`//evil.example/x` survives as a protocol-relative external link.** `is_safe_breadcrumb_url()` allows any URL beginning with `/`, including `//host/path`. This is not script execution, but it is an external-link/open-redirect policy gap in filter output.

### Assessment

P1-01 is materially fixed: executable tags, event handlers and dangerous schemes are removed, and legitimate breadcrumb markup/style survives. The protocol-relative URL allowance is a low-severity policy gap, not a bypass to script execution. It is recorded as **P3-03** below.

## 3. P2-01 — Schema JSON limits

`src/Admin/SchemaMetabox.php`:

- `MAX_IMPORT_BYTES = 1048576`; the custom textarea checks `strlen()` before decoding.
- `MAX_JSON_DEPTH = 64`; both custom textarea and import file use it.
- Oversized/deep/invalid JSON sets `invalid-json`; stored schema/custom data is preserved.
- Tests cover 1,048,577 bytes, 70-level nesting, valid JSON and invalid JSON.

Verified from source and tests. No defect found.

## 4. Input handling audit

Independent inventory:

| Superglobal | Occurrences |
|---|---:|
| `$_POST` | 226 |
| `$_GET` | 152 |
| `$_SERVER` | 21 |
| `$_FILES` | 10 |
| `$_REQUEST` | 0 |
| `$_COOKIE` | 0 |
| `$_SESSION` | 0 |
| **Total** | **409** |

This matches the developer report. The reviewer’s complete 48-line list is not available locally; only ten examples were supplied. The ten examples were traced and remain guarded by type checks, allowlists, normalization, or later sanitization. No new exploitable input flow was confirmed in this audit.

The audit did not claim to reproduce the missing 38 scanner lines one-to-one.

## 5. PR integration

| PR | Head | State / decision |
|---|---|---|
| #417 | `494e121` | Included in #421; translation registration for editor/analysis/sidebar/analysis-format; tests pass. Approve/merge after #421. |
| #420 | `e4ce13d` | Subset of #417; duplicate. Close after #421 merge. |
| #419 | `fbe3f7d` | Included in #421; `cache_users()` batch priming; tests pass. Approve/merge after #421. |
| #418 | `abb6029` | Duplicate production change with more fake-DB complexity. Close after #419. |
| #422 | `5942b63` | New duplicate of #419 with fake author DB; additional duplicate. Close after #419. |
| #416 | `9f3b3f5` | Included in #421; nosniff on sitemap XML/XSL success/404/500; content types preserved. Approve/merge after #421. |
| #415 | `ac42783` | Hold: overlaps #417, needs rebase and manual screen-reader verification. |
| #414 | `cea8d5f` | Hold: TrailBuilder memoization cache key/fallback and filter/context invalidation need changes. |
| #342 | `1d6f62e` | Tooling-only; PR title says 1.24.1 while diff says 1.24.5; keep separate. |

No PR was merged, closed or modified during this audit.

## 6. Tests independently run

| Check | Command | Result |
|---|---|---|
| PHPUnit | `vendor/bin/phpunit` | **OK — 2375 tests, 9142 assertions** |
| PHPCS | `composer lint` | exit 0 |
| PHPStan | `vendor/bin/phpstan analyse --level=6 src/ --memory-limit=2G` | `[OK] No errors` |
| JS tests | `composer test:js` | 352 pass / 0 fail |
| Composer validate | `composer validate --strict` | valid |
| Composer audit | `composer audit` | no advisories |
| Plugin Check | clean 239-file production source installed on WP 7.1.4 | **0 errors / 223 warnings** |
| WordPress staging | WP 7.1.4 at `/tmp/opencode/wp-audit-1567` | P1 sanitization verified; no `debug.log` |

Plugin Check warning categories:

- 203 `PrefixAllGlobals.NonPrefixedVariableFound` — template-local variables in PHP views, not globals.
- 20 `DirectDB.UnescapedDBParameter` — custom tables; values prepared, ids int-cast, identifiers trusted, uninstall names validated.

CI on PR #421: PHP 8.3/8.4, Psalm and Psalm Security passed; AI Code Review was pending. CI was inspected, not re-run independently.

Environment limitations:

- Independent staging was WordPress 7.1.4; WordPress 6.5 was not independently re-run in this audit (developer evidence exists).
- Full line-by-line review of all 223 Plugin Check warnings was not repeated; category and representative-source verification was performed.
- The reviewer’s exact 48-line scanner list remains unavailable.

## 7. Findings

| ID | Severity | Finding | Recommendation |
|---|---|---|---|
| P3-03 | Low | `is_safe_breadcrumb_url()` allows protocol-relative `//host/path` because it only checks the leading `/`. A filter can introduce an external link. No script execution or XSS. | Reject URLs beginning with `//` (and consider rejecting absolute external hosts unless explicitly intended); add a regression test. |
| P3-04 | Low | Unit tests mock `wp_kses` and never exercise real `WP_HTML_Tag_Processor`; independent staging did exercise it, but the test suite alone would not catch a custom-property regression. | Add a WordPress-integration fixture or a focused real-KSES test in CI. |
| INFO | — | Removed `<script>` tags leave their text content (e.g. `alert(9)`) as inert text. | No security action required; optionally strip script content for cleaner output. |

No P0/P1/P2 security defect was found in the target commit beyond the resolved P1 filter issue and bounded P2 schema path.

## 8. Verdict

**APPROVED WITH NON-BLOCKING FOLLOW-UPS.**

The confirmed WordPress.org callback-output defect is materially fixed, schema JSON is bounded, the selected PR integrations are correct, and the independent regression gates pass. The protocol-relative URL policy and real-KSES test coverage should be addressed before the next submission cycle; neither is an executable XSS or a blocker for merging #421.
