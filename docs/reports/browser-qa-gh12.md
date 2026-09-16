# GH-12 Browser QA Gate: Final Report

Focused manual browser verification of the Redirect Manager and 404 Monitor. No new functionality, no redesign. Only genuine usability or functional bugs were to be fixed.

Date: 2026-09-14
Branch: `GH-12`, head `629adfc`, working tree clean, not pushed
Environment: live LocalWP WordPress site, temporary administrator created and deleted for QA, all QA data removed afterward
Scope note: this pass made no production code changes, because no genuine bug was found. Test numbers are therefore unchanged from the prior pass.

## QA table

| Flow | Tested | Result | Issue | Fixed |
|---|---|---|---|---|
| Redirect: search | yes | PASS | none | n/a |
| Redirect: filters (match, code, status) | yes | PASS | none | n/a |
| Redirect: status tabs with counts | yes | PASS | none | n/a |
| Redirect: pagination | yes | PASS | none | n/a |
| Redirect: bulk actions | yes | PASS | none | n/a |
| Redirect: edit | yes | PASS | none | n/a |
| Redirect: activate and deactivate | yes | PASS | none | n/a |
| Redirect: delete confirmation | yes | PASS | none | n/a |
| Redirect: Add open and close behavior | yes | PASS | none | n/a |
| Redirect: regex validation | yes | PASS | none | n/a |
| Redirect: loop warning | yes | PASS | none | n/a |
| Redirect: chain warning | yes | PASS | none | n/a |
| Redirect: 410 and 451 behavior | yes | PASS | none | n/a |
| Redirect: advanced options | yes | PASS | none | n/a |
| Redirect: CSV import | yes | PASS | none | n/a |
| Redirect: CSV export | yes | PASS | none | n/a |
| 404: search and list | partial | PASS WITH NOTE | list verified, search control present but a search query was not exercised | no |
| 404: pagination | yes | PASS | none | n/a |
| 404: details expansion | yes | PASS | none | n/a |
| 404: create redirect from a row | yes | PASS | none | n/a |
| 404: exclusions list | yes | PASS | none | n/a |
| 404: add exclusion | yes | PASS | none | n/a |
| 404: remove exclusion | yes | PASS | none | n/a |
| 404: clear log | yes | PASS | none | n/a |
| 404: clear confirmation | yes | PASS | none | n/a |
| 404: retention setting | yes | PASS | none | n/a |
| 404: maximum entries setting | yes | PASS | none | n/a |
| 404: near-limit notice | yes | PASS | none | n/a |
| 404: 80 percent state | yes | PASS | none | n/a |
| 404: 90 percent state | yes | PASS | none | n/a |
| 404: flood protection | yes | PASS | none | n/a |
| 404: advanced logging fields | yes | PASS | none | n/a |
| 404: empty state | yes | PASS | none | n/a |
| 404: return flow after creating a redirect | yes | PASS | none | n/a |
| Responsive widths (1600, 1024, 782, 600) | yes | PASS | none | n/a |

No issue required a code fix. The only observation that looked like a bug was investigated and found to be standard WordPress behavior (see below).

## What was verified, with evidence

Redirect Manager:

- Search for `/rk-qa-5` returned exactly one row.
- Status tabs showed All (25), Active (25), Inactive (0), updating correctly after actions.
- The match filter set to regex returned zero rows, as expected with no regex rules.
- Pagination showed "Page 1 of 2" with 20 rows per page.
- Bulk deactivate reported "Bulk deactivate finished for 1 redirects" and moved the count to Inactive (1).
- Activate on the inactive row restored Active to 25.
- Edit opened the shared editor with heading "Edit Redirect", button "Update Redirect", and the source populated.
- Delete showed the confirm dialog "Delete this redirect? This cannot be undone." and, on accept, removed the row (24 remaining).
- Add Redirect opened the editor without a page reload and toggled closed on a second activation. The control is an anchor with `aria-expanded` and `aria-controls="rk-redirect-editor"`.
- Live regex validation showed "That pattern does not compile. Check the syntax and try again." for `[` and "Pattern compiles cleanly." for `^/old/(.*)$`.
- A loop attempt was blocked with "This redirect would create a redirect loop: /rk-loop-b → /rk-loop-a → /rk-loop-b. The rule was not saved."
- A chain was saved with "Redirect chain detected: /rk-chain-c → /rk-loop-a → /rk-loop-b. Consider pointing the source directly to /rk-loop-b."
- Selecting 410 hid and disabled the destination; selecting 301 restored it.
- Advanced options toggled from closed to open as a native details control.
- CSV export returned status 200, content type `text/csv; charset=utf-8`, the documented header, and 24 data rows.
- CSV import of a valid row plus two bad rows reported "1 created, 0 updated, 0 skipped, 2 with errors", rejecting an unsafe `javascript:` destination and an invalid code, and the valid row was stored.

404 Monitor:

- Usage indicator showed current over maximum, with an accessible progressbar.
- At 80 of 100 the info notice read "Your 404 log is approaching its configured entry limit of 100 entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time."
- At 90 of 100 the warning notice read "Your 404 log is nearly at its configured entry limit of 100 entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time."
- Pagination showed "Page 1 of 4" then "Page 1 of 5".
- Details expansion revealed full address, hits, first seen, last seen, and, with advanced logging off, the explanation "Referer and user agent logging is off. Turn on Advanced fields in Monitor Settings below to capture them." With advanced logging on it showed Referer and User agent rows instead.
- Add Exclusion added a row, saved, and the exclusion worked: `/secret.env` was not logged while a normal URL was.
- Remove Exclusion removed the row and saved.
- Retention was changed to 45 days and persisted; the maximum entries value was clamped to the documented minimum of 100 when a below-minimum value was stored, which is correct.
- Clear Log showed "Clear the whole 404 log? This cannot be undone." and, on accept, emptied the log and returned `rk_notice=cleared`.
- Empty state read "No 404 entries are being tracked yet. When visitors hit a missing page, its address appears here with hit counts and first and last seen times."
- Create Redirect from a row opened the Redirects editor prefilled with the source, Exact, 301, empty destination, and a prefill notice, and saving returned to the 404 Monitor.
- Flood protection with a low budget: five unique requests produced a bounded number of new rows and set the suppression marker, so unique URL probing cannot force unbounded growth.
- Return flow after creating a redirect from a 404 returned to the monitor.

## Responsive and admin width

Checked at 1600, 1024, 782, and 600 pixels on both screens:

- No horizontal overflow at any width on either screen.
- The 404 Clear Log button stayed visible and inside the viewport at all widths.
- The Monitor Settings disclosure remained present at all widths.
- No clipped buttons, broken tables, overlapping containers, or broken disclosures were observed.

## UX validation

- Add Redirect is obvious, opens the correct container, and closes on a second activation.
- Edit is understandable, with a distinct heading and an Update button.
- Warning versus error is clear: cycles are red blocking notices, chains are warnings that still save.
- The chain recommendation is understandable and names the direct destination.
- The loop error clearly blocks and shows the cycle path.
- Advanced options are discoverable through a native disclosure and do not overwhelm the default view.
- The 404 Monitor communicates its purpose through the Log Status summary and the tracked list.
- The usage indicator is understandable (current over maximum with a progressbar).
- Clear Log is clearly destructive through its confirmation.
- Important actions are visually obvious; secondary controls are hidden behind disclosures.
- Field errors are understandable and associated with their fields.
- Focus is visible and expandable controls use native details or proper aria attributes.

## The one observation investigated

A row action link reported as off screen during automation. This was investigated and confirmed to be standard WordPress list table behavior: core hides `.row-actions` with `left: -9999em` and reveals them on row hover (`tr:hover .row-actions`, plus the `no-js` and `mobile` cases). A real user hovers the row and the Edit, Activate or Deactivate, and Trash actions appear. This is not a bug and no fix was made.

## Browser QA Result

PASS. No production code changes were required in this pass.

## Remaining limitations

1. The 404 list search query was not exercised (control present, not typed).
2. Responsive checking used layout metrics (overflow, control visibility) rather than a pixel level visual review.
3. The flood budget was observed conservatively because the window still carried a count from an earlier request, which is correct behavior, not a defect.
4. Navigating away with the settings disclosure open triggers the browser beforeunload guard, which is normal browser behavior.

## Owner decisions

1. Redirect trash and restore lifecycle (currently permanent delete with confirmation).
2. Slug watcher expansion to taxonomies and custom post types.
3. 404 log export.
4. A global redirect rule cap beyond the 500 pattern cap and the 5000 row CSV cap.
5. Whether to proceed to the branded admin redesign and possible `WP_List_Table` adoption.

## Final Recommendation

READY FOR OWNER MANUAL REVIEW.

## Quality gates after this pass

- `composer test`: OK, 813 tests, 2964 assertions.
- `composer lint`: clean, exit 0.
- `composer stan`: level 6, no errors.
- No code changes this pass, so counts are identical to the prior pass.
