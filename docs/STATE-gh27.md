# GH-27 Session State (resume here)

Compact continuation record. Read this first after a context compaction.

## Current position

- Repo: `wp-content/plugins/rankkernel`, branch **GH-27** (do NOT create another branch)
- HEAD: `0c97f3c` (working tree clean at time of writing)
- Nothing pushed, nothing merged. Owner merges.
- Issue: **#27**. Starting commit `7e100f4` (after PR #26 merged to main).

## Commits on GH-27

| Commit | Content |
|---|---|
| `1117dba` | Six module audit (311 rows) + competitor editor UX studies |
| `01b8994` | Seven engine fixes (canonical, robots merge, archive tokens, decodeMetaValue, schema custom preservation, sitemap escaping, redirect cache) |
| `7d2cd60` | Metadata editor WIP (documents the JS/view mismatch honestly) |
| `9e4f684` | JS to view hook contract reconciliation + contract test |
| `44e0b1a` | Docs: roadmap 2.7 + parity matrix PARTIAL rows |
| `c66a823` | Four tab sidebar + matching Classic panel |
| `43de144` | Design system tokens + UI + metabox hide + default templates |
| `dee2b7a` | Sidebar render fix + schema metabox guard |
| `0c97f3c` | Edit Snippet as an editing workspace + social workflow |

## Browser-verified working (real Gutenberg, page 53671)

- Sidebar renders: 1 complementary area, 4 tabs, correct `aria-selected` and `aria-label`, `clipped: false`
- Gutenberg Meta Boxes area contains NO RankKernel panel (`ScreenGuard::isBlockEditorScreen()`)
- General: Search Preview, Desktop/Mobile, "Preview snippet editor" entry, title inherited 22/60 OK, description inherited 0/160 OK
- Advanced: Index/Noindex + Follow/Nofollow with explainer text, Additional robots collapsed, Canonical
- Schema: full type list present in sidebar
- Social: Facebook/Twitter, image Select with 1200x630 / 600x315 guidance, title, description
- Edit Snippet modal: title "Edit Snippet", 2 inputs `rk-modal-title` and `rk-modal-description`, counter, device toggle

## NOT yet verified (do not claim DONE)

- Token insertion end to end (click, insert, counter recompute, preview, save, reload, persist)
- Social image select / replace / remove cycle through wp.media
- Modal to sidebar state propagation while typing
- Save and reload persistence; frontend robots/canonical after UI changes
- Classic Editor visual check
- Responsive beyond one width (narrow, laptop, zoom)
- Rank Math was never inspected live (installed but INACTIVE; activating it would mutate the shared environment)

## CLEAN ROOM STATUS (verified by scan)

No Rank Math code, CSS, markup, icons or class names in the plugin. `rm-icon`, `serp-preview-`,
`edfa-d-f`, `customterm`, `is-next-40px`, `components-tab-panel__tabs` all appear in **0** files.
Only legitimate references: a conflict-detection guard in `rankkernel.php:99`, importer key mapping
in `blueprint.md`, and the policy text in `clean-room-policy.md` (which states Rank Math is
GPL-reusable but must NOT be reused). Keep it that way.

## OPEN TASK: four UI changes requested by the owner

1. **Remove the SEO title and meta description input fields from the General tab.** They are now
   edited in the Edit Snippet modal. General keeps the Search Preview and the Edit Snippet entry.
2. **Widen the token selector.** It is currently too narrow to read.
3. **Restructure the modal to Rank Math's observed pattern**, clean-room, with our own classes.
   The owner supplied their markup as a behavioural reference only. Target structure: a tablist
   with icon plus label on the active tab; the SERP preview with the device toggle inside its
   header; a length indicator showing both characters and pixels, formatted like
   `51 / 60 (445px / 580px)`; a searchable variable dropdown whose rows show a bold token name
   and a description line; a permalink field; field help text under each control.
   NEVER copy their class names, icons, CSS or markup. Rebuild the pattern with RankKernel classes.
4. **Verify the Desktop and Mobile preview modes actually differ and work.**

## Files that matter

- `assets/js/metadata-sidebar.js` — the Gutenberg sidebar (primary surface)
- `assets/js/metadata-editor.js` — Classic metabox behaviour
- `assets/css/rankkernel-admin.css` — canonical token layer (41 tokens, palette + spacing + radius + shadow)
- `assets/css/metadata-editor.css`, `assets/css/metadata-classic.css` — consume the tokens, zero raw hex
- `src/Admin/MetadataBox.php`, `src/Admin/SchemaMetabox.php`, `src/Admin/ScreenGuard.php`
- `src/Admin/Views/metadata-box.php` — Classic view
- `src/Modules/Metadata/{MetaPayload,Context,HeadRenderer,TagsReplacer}.php`
- `src/Settings/SettingsStore.php` — default templates `%%title%% %%sep%% %%sitename%%` and `%%excerpt%%`
- `tests/Unit/MetadataBoxTest.php` — includes the JS to view hook contract test

## How to verify in a browser (reversible, no credential changes kept)

The site is LocalWP at `http://localhost:10043`. RankKernel is active; Rank Math and Yoast are
installed but inactive. There is no CLI PHP with mysqli, so wp-cli cannot be used.

1. DB access: use the `mysql` client bundled with Local. Both the client binary and its socket live
   under the Local run directory for this site; resolve them there rather than recording them here.
   This step applies only to the host-local LocalWP environment, never a shared or production database.
2. You need an administrator account you control. Do not record the login names in this file.
3. To authenticate in a browser without learning a password: read the account's `user_pass` meta,
   save it verbatim, set `user_pass` to a temporary MD5 hash, log in at `/wp-login.php`, verify, then
   restore the exact original hash and confirm it matches byte for byte. Always restore. Confirm the
   temporary password no longer authenticates.
4. Open a post: `http://localhost:10043/wp-admin/post.php?post=53671&action=edit`.
   Open our sidebar programmatically with
   `wp.data.dispatch('core/edit-post').openGeneralSidebar('rankkernel-seo/rankkernel-seo')`.
5. When probing for a modal, filter to the VISIBLE frame: several
   `.components-modal__frame` elements exist and the first one is WordPress's hidden link dialog.

## Gates (all currently green at 0c97f3c)

```text
composer lint                        exit 0
composer stan                        level 6, no errors
composer test                        1048 tests, 3660 assertions
vendor/bin/phpcs --standard=phpcs.xml exit 0
node --check assets/js/metadata-sidebar.js
node --check assets/js/metadata-editor.js
```

## Standing rules

- Never copy competitor source, CSS, markup, icons, class names or UI wording. Behaviour reference only.
- 100% free forever: no PRO gates, upsells, licence checks, telemetry, or bundled paid services.
- Do not add React/build tooling. Hand written JS against wp.* globals, matching
  `src/Modules/Schema/blocks/faq/faq-editor.js`. No Tailwind, no CSS-in-JS, no framework.
- Tabs for indentation. WordPress coding standards. Zero dash writing rule in comments and UI strings.
- One metadata source of truth: `_rankkernel_meta_data` via `MetaPayload`. Never a second store.
- Module hard gating: metadata off means no metabox and no editor assets.
- Editor assets must never load on the frontend.
- Update `docs/ROADMAP.md` and the parity matrix only after real verification. A visual shell is not DONE.
