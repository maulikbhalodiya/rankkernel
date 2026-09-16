# GH-12 Redirect Validation Hardening: Final Handoff

Focused hardening of redirect validation and chain recommendation. No new feature phase, no visual redesign, no `WP_List_Table`, no branding change.

Date: 2026-09-14
Branch: `GH-12`, head `e1cb6f5`, 22 commits since main, working tree clean, not pushed

## Implementation

- Added `Validator::is_equivalent_redirect()` which compares the normalized source path against the normalized internal destination path and returns true when they are the same effective resource.
- Added `Validator::assess_safety()` as the single shared precedence used by every creation path: equivalent, cycle, chain, inconclusive, or ok.
- Wired that single precedence into the admin save and edit, the CSV import per row, and the slug watcher. The create from 404 flow prefills the same admin editor, so it inherits the same validation with no separate path.
- Added the "Use recommended destination" action in the redirect editor. It copies the final destination into the destination field with JavaScript only and never submits or saves. Without JavaScript the notice still stands alone.
- Added the equivalent redirect error message and the inconclusive chain message with associated, accessible notices.

## Equivalence validation

`/blog` and `/blog/` both normalize to `/blog` through the existing `Normalizer` (trailing slash trimmed except root, duplicate slashes collapsed, percent encoding canonicalized, query and fragment stripped, home host folded). The equivalence check treats them as the same effective path, so saving is blocked with "The source and destination resolve to the same URL. Please enter a different destination. The rule was not saved."

Verified blocking cases: `/blog` to `/blog/`, `/blog/` to `/blog`, `/about` to `/about/`, and the query variants `/blog?x=1` versus `/blog/?x=1` (path only, per existing semantics).

Regex sources are never path-normalized for equivalence. `normalizeSource()` still stores regex patterns verbatim, and the equivalence check returns false for regex and pattern matchers unless a definite equivalence can be proven, so patterns are never corrupted and never falsely flagged.

## Loop behavior

The bounded loop detector is unchanged: depth 10, and 50 matching edge examinations where the node budget counts only matching rules, not every scanned rule. Real cycles block with the cycle path. Inconclusive analysis never blocks and is never treated as safe. The matching edge budget fix from the earlier pass is preserved and covered by tests.

## Chain behavior

A new redirect that creates a known chain is allowed to save with a warning that shows the full chain and the recommended final destination, computed by the existing bounded traversal (max 5 hops). Example observed live: `Redirect chain detected: /rk-old → /rk-A → /rk-B → /rk-C. Consider pointing the source directly to /rk-C`, with the "Use recommended destination" action present. Clicking it set the destination field to `/rk-C` and did not save.

## Inconclusive behavior

When the final destination cannot be determined (pattern matcher, capture reference, external target, or a traversal limit), the warning states that the final destination could not be determined from the available rules and that the redirect can still be saved. No guessed destination is shown. The slug watcher fails closed in the same situation and creates nothing.

## CSV behavior

Each imported row passes the same precedence: invalid input is an error; definite self or equivalent is an error; definite cycle is an error; a known chain imports with its warning preserved; an inconclusive chain imports with its manual verify note; a valid unrelated row imports normally. Live run with five rows reported "3 created, 0 updated, 0 skipped, 2 with errors" and per row reports: "Row 3: The source and destination resolve to the same URL", "Row 6: This redirect would create a redirect loop: /rk-cyc-2 → /rk-cyc-1 → /rk-cyc-2", "Row 4: Redirect chain detected: /rk-csv-chain → /rk-A → /rk-B → /rk-C". Warnings are not silently dropped.

## Slug watcher

Continues to fail closed. It now rejects definite equivalent and cyclic proposals and refuses creation on an inconclusive chain, in addition to its existing revision, autosave, and duplicate handling. Scope remains posts and pages only.

## 404 redirect creation

The create from 404 flow prefills the source and uses the identical admin validation. Live check: prefilled `/rk-eq-test`, destination `/rk-eq-test/` was blocked with the same URL error, and the rule was not saved.

## Tests

Before: 830 tests, 3023 assertions after the implementation. Prior baseline before this task was 813 tests, 2964 assertions. New coverage adds 17 tests and 59 assertions across equivalent paths, cycles, chains, longer chains, inconclusive chains, regex preservation, query regression, CSV classification, slug watcher, and the 404 path.

Gate results after this pass:

```text
composer test: OK (830 tests, 3023 assertions)
composer lint: clean, exit 0
composer stan: level 6, no errors
Node/editor checks: node --check passes on redirects-admin.js and monitor-admin.js
```

## Browser QA

| Scenario | Result |
|---|---|
| `/blog` to `/blog/` blocked | PASS |
| `/blog/` to `/blog` blocked | PASS |
| `/about` to `/about/` blocked | PASS |
| Chain `/rk-old` to `/rk-A` with `/rk-A` to `/rk-B` to `/rk-C` | PASS, saved with warning |
| Chain shows full path and recommends `/rk-C` | PASS |
| Use recommended destination sets field, does not save | PASS |
| Cycle `/rk-C` to `/rk-A` blocked with loop path | PASS |
| Regex `^/old/(.*)$` saved and stored verbatim | PASS |
| 410 terminal saved without a destination | PASS |
| 404 created redirect uses the same validation | PASS |
| CSV import classification and warnings | PASS |
| Query variants `/blog?x=1` and `/blog/?x=1` treated equal | PASS |

## Files changed

- `src/Modules/Redirects/Validator.php` (equivalence check and shared precedence)
- `src/Admin/RedirectsPage.php` (notice wording, use recommendation action, precedence wiring)
- `src/Modules/Redirects/CsvHandler.php` (per row precedence and warnings)
- `src/Modules/Redirects/SlugWatcher.php` (fail closed on equivalent and cycle)
- `assets/js/redirects-admin.js` (use recommended destination action)
- `assets/css/redirects-admin.css` if needed for the new notice
- tests under `tests/Unit/Redirects*`

## Git state

- Branch: `GH-12`
- Commit: `e1cb6f5` (22 commits since main)
- Working tree: clean
- Pushed: no. Merged: no. PR: none.

## Note for the owner

Four leftover QA redirect rules were found in the live database from the implementation pass (they used the plan example paths and one redirected a real blog post to `/`). They were removed during cleanup so the site is clean. All QA data and the temporary administrator were removed (redirects 0, 404s 0, temporary users 0).
