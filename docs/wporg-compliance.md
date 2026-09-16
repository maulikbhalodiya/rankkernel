# RankKernel — WordPress.org Plugin Directory Compliance Requirements

> **Status:** Planning / requirements document (no implementation code).
> **Scope:** Maps every verified 2026 WordPress.org directory rule to a concrete build requirement for RankKernel.
> **Source of truth for rules:** Official WordPress.org Plugin Guidelines (last modified March 2026), Plugin Check (PCP) v2.1.0 (Aug 2026), the 2026 review process, and the official WordPress AI Guidelines v0 (Feb 2026). These facts are embedded in this doc and must not be contradicted by later planning.
> **Companion docs:** `docs/competitor-analysis/feature-matrix.md`, `gap-analysis.md`, `yoast-audit.md`, `rankmath-audit.md`.

---

## 1. Submission Process Overview (how review works in 2026)

RankKernel will be submitted to the official WordPress.org Plugin Directory. The directory is the **only** sanctioned distribution point; the hosted copy in SVN is what users install. Understanding the pipeline shapes how we build, because several rules are enforced *during* review, not just at the end.

### 1.1 The pipeline

1. **Developer account + Plugin Check Namer.** Before submitting, run the AI-powered "Plugin Namer" tool (Tools > Plugin Check Namer, requires WP 7.0+) to pre-check name similarity and trademark conflicts. This is **guidance only** — reviewers have the final say. It is a cheap pre-flight, not a guarantee.
2. **Submission.** Upload the complete plugin. A **stable version must already be available** in the directory (SVN is the distribution point). The complete plugin must be present at submission; names cannot be reserved ahead of time.
3. **Automated tooling pass.** Plugin Check (PCP) runs automated checks. To be approved, a plugin must typically pass **all** checks in the **"Plugin repo"** category. Other categories are advisory.
4. **Hand review.** A human reviewer checks the items automation cannot: licensing of every file, guideline circumvention, trademark/slug legality, security patterns, and the "not a 100% copy" rule.
5. **Decision + response window.** Review typically lands within **5–14 business days** (can spike during backlogs). Roughly **69.5%** of reviewed plugins are approved. **Author responsiveness strongly correlates with approval** — slow or dismissive replies hurt.
6. **Post-approval.** Updates ship via SVN only; the directory is the sole update source. No phone-home update mechanisms are permitted.

### 1.2 What this means for our build

- We run `wp plugin check` (the PCP CLI) **in CI before every release**, not just before first submission. Treat a failing "Plugin repo" check as a release blocker.
- We keep a **review-response runbook** (Section 5) so we can reply fast and precisely when flagged.
- We use the official **Plugin Directory MCP server** (March 2026) to validate `readme.txt` and assist submission where helpful.
- We budget for the **5–14 business day** window and avoid "reserve the name now, build later" thinking (G11 forbids name reservation; the complete plugin ships at submission).
- Because **author responsiveness correlates with approval**, we assign a single owner to watch the review ticket and reply within 1–2 business days.

### 1.3 What hand reviewers specifically look for

Automated tooling catches format and many security issues, but a human reviewer is the gate for judgment calls:

- **License of every file.** They will question any bundled asset (image, font, JS) whose license is not obviously GPL-compatible and verifiable (G1).
- **Guideline circumvention.** They look for disguised paywalls, "free but requires our account," or code that re-enables behavior we were asked to remove (G2).
- **Trademark/slug legality.** They confirm no "WordPress" in slug/domain/display name and that competitor references are fair use (G10).
- **Security patterns.** They sample code for missing nonce/capability/escape/sanitize even where automation is silent (G9).
- **Originality.** They check the plugin is not a 100% copy of another (G13).
- **Functional completeness.** They confirm the hosted plugin actually works end to end without off-directory dependencies (G3, G5, G11).

---

## 2. Full Rule Table

Each row maps a verified guideline to (a) the official requirement, (b) how RankKernel complies, and (c) the enforcement mechanism we bake into the build so compliance is structural, not manual.

| # | Official Requirement | How RankKernel Complies | Enforcement Mechanism in Build |
|---|---|---|---|
| G1 | **GPLv2-or-later compatible license for ALL files** — code, images, JS, fonts, docs. Third-party libraries must be GPL-compatible with **verifiable** licenses; if licensing cannot be validated, the library cannot be used. | Every file carries the GPLv2+ header. We ship **zero** bundled third-party libraries whose license we cannot verify. Any dependency is vetted against a license allowlist before merge. | CI license-header check on all files; a `composer`/`npm` audit step that fails the build on any unverifiable or non-GPL-compatible dependency. A `LICENSE` file at plugin root. |
| G2 | **Developer is 100% responsible** for all plugin content and actions. Circumventing guidelines or restoring removed code is prohibited. | We own every line. If a reviewer asks us to remove something, we remove it permanently and do not re-add it. No obfuscation-to-hide tricks. | Code review gate: no "restore removed code" commits; no conditional-define workarounds that re-enable disabled behavior. |
| G3 | **A stable version must be available in the directory** (SVN is the distribution point). | We tag a Stable tag in `readme.txt` and commit that tagged tree to SVN. The hosted copy is the canonical one. | Release script requires a matching `Stable tag` + SVN commit before "released" status. |
| G4 | **Code must be mostly human-readable** — no obfuscation (no PHP packer/uglify-mangle). Compiled JS bundles are acceptable practice. | PHP is plain, readable, PSR-4. We may ship compiled/minified JS bundles (allowed), but source maps / build inputs stay in the repo so the result is auditable. | Lint + readability review; no PHP obfuscator in the toolchain. |
| G5 | **No trialware / paywalls / license-gates / usage-quotas** inside the hosted plugin. The free plugin must be fully functional. | RankKernel is 100% free, 100% open. The **BYO-key AI Suite** is fully functional in the hosted plugin: the user supplies their own API key to a third-party service (OpenAI/Anthropic/Gemini). We do **not** sell keys, do not meter usage, do not gate features. This is **not** a paywall because no RankKernel feature is locked and we collect no payment. | Feature-flag audit: every module is on/off by user choice, never by payment. AI Suite has no quota/license-check code path. |
| G6 | **No tracking / telemetry without explicit opt-in consent. No phone-home. Updates only via WordPress.org.** | Zero telemetry by default. Zero external requests by default (BYO-key calls go to the user's chosen third-party AI provider at the user's explicit action, not to us). Updates ship via SVN. | Static analysis: forbid `wp_remote_*` to our own domains; no analytics/beacon endpoints; no custom updater. |
| G7 | **No CDN-hosted assets** — enqueue local bundled assets. | All CSS/JS/images ship in the plugin and are enqueued locally. No `https://cdn...` asset URLs. | Build check fails on any remote `src`/`href` in enqueued assets. |
| G8 | **No embedded public-facing external links** ("powered by") without opt-in. | No "powered by RankKernel" links injected into frontend output. Any attribution is admin-only and off by default. | Frontend output grep test: no injected external anchor tags in rendered HTML. |
| G9 | **Security:** sanitize all input (`sanitize_text_field` etc.), escape all output (`esc_html`/`esc_attr`/`esc_url`/`wp_json_encode`), nonces on all forms/AJAX/REST writes, capability checks (`current_user_can`), `$wpdb->prepare` for all SQL. | Every module follows this contract (see Section 3 per-module notes). Single-row meta uses `update_post_meta`/`get_post_meta` (no raw SQL); any custom table (redirects/404 only when enabled) uses `$wpdb->prepare`. | CI static-security scan (PHPCS WP ruleset) blocking on missing escape/sanitize/nonce/capability. Manual review of every REST write. |
| G10 | **Trademarks / copyrights (Rule #17):** a trademark or another project's name must not be the sole or initial term of a slug; "WordPress" must not appear in the slug or domain (avoid in display name too). Referencing competitor names in a compatibility/import context ("Import from Yoast SEO") is **nominative fair use** and allowed (Rank Math does this) — must not imply endorsement. | Slug `rankkernel`, text domain `rankkernel`, REST namespace `rankkernel/v1`. No "WordPress"/"Yoast"/"Rank Math" in name or slug. Importer labels use nominative fair use ("Import from Yoast SEO", "Import from Rank Math") with no endorsement language. | Slug/name lint; legal review of all competitor references; no "WordPress" in display name. |
| G11 | **Complete plugin available at submission; names cannot be reserved.** | We submit the whole plugin, not a placeholder. | Release gate: no stub/placeholder modules at submission. |
| G12 | **`readme.txt` standard format** (=== headers, == Description/Installation/Changelog ==, Requires at least / Requires PHP / Tested up to / Stable tag). No SEO spam in readme. | We maintain a compliant `readme.txt` (Section 4). Description explains the plugin plainly; no keyword stuffing. | Plugin Directory MCP / PCP readme validation in CI. |
| G13 | **Plugin must not be a 100% copy of another plugin** (review checklist). | RankKernel is architecturally distinct (single-key meta, zero-query disabled modules, lean storage). We do not copy competitor code. | Architecture review confirms original implementation; no vendored competitor source. |

### 2.1 Naming specifics (verified)

- **Name:** RankKernel
- **Slug:** `rankkernel`
- **Text domain:** `rankkernel`
- **REST namespace:** `rankkernel/v1`
- **Forbidden in name/slug:** "WordPress", "Yoast", "Rank Math".
- **Trademark flag:** "Kernel®" is trademarked by an unrelated CRM-data company (kernel.ai). Different industry, distinctive compound, low conflict risk — but we must run a **final official trademark search before submission** and document the result.

### 2.2 AI assistance disclosure (WordPress AI Guidelines v0, Feb 2026)

- AI-assisted code **is allowed** in submissions.
- The developer is **fully responsible for every line** and must be able to explain any submitted code during review ("code you cannot explain, do not submit").
- AI tool output must be **GPL-compatible** per the tool's terms.
- **Disclosure is the community norm** for meaningful assistance, format: `AI assistance: Yes — Tool(s): … — Used for: …`. Trivial assistance (naming/formatting) does not require disclosure.
- **Build requirement:** maintain a `docs/ai-disclosure.md` (or equivalent) recording meaningful AI use per release, so we can paste the standard line into the support thread if asked.

### 2.3 Plugin Check (PCP) categories we must respect

PCP v2.1.0 (Aug 2026) groups its automated checks into categories. The practical rule:

- **"Plugin repo" category — must pass ALL checks.** This is the approval gate. A single failing "Plugin repo" check is normally disqualifying until fixed.
- **Other categories — advisory.** Warnings here (performance, experimental, etc.) do not block approval but should be reviewed; a pile-up of advisories can prompt a reviewer to look closer.
- **Plugin Namer (Tools > Plugin Check Namer, WP 7.0+)** — AI-assisted name/trademark pre-check. Guidance only; reviewers decide.
- **Local CI integration.** We wire `wp plugin check` into the release pipeline so the "Plugin repo" gate is enforced automatically, not by memory.

We do **not** treat an advisory warning as optional forever; each release we either fix it or record a conscious decision.

---

## 3. Per-Module Compliance Requirements

Each module below lists what it **must do** to stay compliant. These are specs, not code. References to class/function names are naming intentions only.

The module list mirrors the feature set in `docs/competitor-analysis/feature-matrix.md` and the architectural mandates in `gap-analysis.md`. Where a module's compliance requirement is also a competitive differentiator (single-key meta, hard-gated modules, clean uninstall), that link is called out so builders see compliance and positioning as the same goal, not conflicting ones.

### 3.1 Metadata Engine (`MetaEngine`)
- Stores per-post SEO data as a **single serialized meta key** (`_rankkernel_meta_data`; one row vs competitors' 25–45), satisfying the "zero database bloat" pillar.
- All template inputs sanitized on save (`sanitize_text_field` / `sanitize_textarea_field`); all rendered `<title>`, meta description, and OG/Twitter tags escaped with `esc_html()` / `esc_attr()` / `esc_url()`.
- Replace-vars resolved in a **single memoized pass per field per request** (no per-presenter re-resolution).
- No external requests; social tags use locally computed values only.
- Per-post overrides never inject external "powered by" links (G8).
- On uninstall, the single meta key is removed for all objects when the user chooses data purge (pillar: complete data purge on uninstall).
- Settings stored in a flat option array; large payloads set `autoload = no` to avoid per-request option bloat (competitor flaw we avoid).

### 3.2 Sitemaps
- XML sitemaps generated locally; cache **ON by default** (transient/object-cache with validator-based invalidation).
- All sitemap assets (XSL, styles) enqueued locally (G7). No CDN.
- No telemetry around sitemap generation; no phone-home ping.
- Escapes all URL output with `esc_url()`.
- Supports posts/taxonomies/users; image data inlined. No external fetch to build the index.
- When the sitemap module is OFF, its rewrite rules and generators are not registered (hard-gate pattern).

### 3.3 Schema (JSON-LD)
- Auto JSON-LD graph comparable in coverage to competitors, emitted via `wp_json_encode` with proper escaping.
- Custom schema builder (free) stores data in the single-key meta; no duplicated meta rows.
- Lazy generation: schema pieces computed only when output is requested (supports the zero-query-when-off goal).
- No external schema-fetch calls.
- Multi-entity `@graph` supported per page; all literal values escaped before encoding.
- Breadcrumb schema generated only when breadcrumb output is actually rendered (avoids competitors' always-on query cost).

### 3.4 Redirects
- Full manager (301/302/307/410/451 + regex) when the module is **ON**.
- **Hard gate:** when the module is **OFF**, zero hooks are registered (copy Rank Math's `can_load_module` pattern) — zero cost, zero queries.
- When ON: **cache-first lookup**; only hit the match table on cache miss. **Never claim "zero-query redirects"** — that overclaim was already rejected by review and must not appear in copy, readme, or UI.
- Any custom table uses `$wpdb->prepare` for all queries (G9). Admin write routes require nonce + `current_user_can('manage_options')`.

### 3.5 404 Monitor
- Log + 1-click "fix with redirect". When module OFF, zero hooks (same hard-gate pattern).
- Prune by age/count, do **not** truncate the whole table and lose history (competitor flaw we avoid).
- All admin inputs sanitized; outputs escaped.

### 3.6 IndexNow
- Free instant-indexing ping to `api.indexnow.org` at the user's explicit publish/update action.
- This is a user-initiated outbound request to a public indexing API, not telemetry and not a phone-home to us (G6 compliant because it is the feature's purpose and user-triggered).
- API key stored via `update_option` with appropriate sanitization; key never echoed to frontend.

### 3.7 Gutenberg UI
- Blocks (e.g., breadcrumbs, schema, social preview) registered via the Block API; assets enqueued locally (G7).
- Editor-side analysis runs client-side where possible (no unnecessary server round-trips).
- All block attributes sanitized on save, escaped on render. No external links injected into frontend output.
- Block scripts/styles enqueued only on screens that use them (no global admin enqueue of heavy bundles — avoids competitor bloat pattern).
- No admin ads, upsells, or "pro" badges anywhere in the UI (pillar: zero admin noise).

### 3.8 AI Suite (BYO-key)
- Fully functional in the hosted plugin with **no payment, quota, or license gate** (G5). The user pastes their own API key for OpenAI/Anthropic/Gemini.
- Outbound requests go **only** to the user's chosen third-party provider, initiated by the user. This is not a paywall and not telemetry (G6).
- API key stored securely (not in frontend, not in logs). All settings sanitized; admin routes nonce + capability protected.
- UI must make clear the key is the user's own and that token cost is borne by the user. No "upgrade to use AI" messaging.

### 3.9 Importer (competitor meta migrator)
- 1-click import from Yoast SEO and Rank Math meta.
- Competitor names used in **nominative fair use** ("Import from Yoast SEO", "Import from Rank Math") — allowed, but **must not imply endorsement** (G10). No "better than" comparative claims in the UI that could read as misleading.
- Imports map into the single-key meta; all imported values sanitized on write.
- No competitor code is copied; we read public meta keys only.
- Import is a user-initiated admin action behind a nonce and `current_user_can('manage_options')`; it makes no external requests.
- The importer is a distinct, optional module — off by default means zero footprint.

### 3.10 REST API (`rankkernel/v1`)
- Namespace `rankkernel/v1` (no "WordPress"/competitor terms).
- **Every write route** requires: nonce verification, `current_user_can` capability check, input sanitization, output escaping (G9).
- Read routes still escape output.
- No REST route performs telemetry or phone-home.
- AI Suite REST routes are user-action triggered and go only to the third-party provider.

### 3.11 Cross-cutting: ModuleManager & boot (hard-gate pattern)
- A single `ModuleManager` registry controls which modules load. A module that is **OFF** registers **zero** hooks, menus, REST routes, and rewrite rules — this is the structural guarantee behind "zero cost when a module is off" and behind several G6/G7/G8 outcomes (no hooks = no chance to phone-home or inject output).
- Boot is two-phase: `plugins_loaded` (register what is needed) then `init` (module activation). No DI container is compiled per request (avoids competitor frontend-cost flaw).
- Because disabled modules leave no trace, the plugin's default-off posture also minimizes the surface a reviewer must audit.
- The same manager drives the **uninstall data-purge** choice: when the user opts to delete all data, every module's cleanup runs; otherwise data is left intact. This is an explicit user choice, never silent retention (competitors retain by default — we make cleanup the offered, documented option).

---

## 4. Pre-Submission Checklist

Run this before the first submission and before every release.

### 4.1 Plugin Check (PCP) — release blocker
- [ ] `wp plugin check` (PCP v2.1.0) run in CI; **all "Plugin repo" category checks pass**. *Why: this category is the approval gate; a single failure normally blocks.*
- [ ] Advisory categories reviewed; no new warnings introduced without sign-off. *Why: advisories don't block but a pile-up draws reviewer attention.*
- [ ] Plugin Namer pre-check run (WP 7.0+); name `rankkernel` shows no blocking conflict (remember: guidance only). *Why: cheap pre-flight; reviewers still decide.*

### 4.2 `readme.txt` (G12)
- [ ] Standard format: `=== Plugin Name ===`, `Contributors`, `Tags`, `Requires at least`, `Tested up to`, `Requires PHP`, `Stable tag`, `License: GPLv2 or later`. *Why: required headers; missing ones fail automated checks.*
- [ ] Sections present: `== Description ==`, `== Installation ==`, `== Changelog ==` (and others as needed). *Why: mandatory sections.*
- [ ] No SEO spam / keyword stuffing in Description. *Why: readme spam is a guideline violation.*
- [ ] Validated via Plugin Directory MCP or PCP readme check. *Why: catches format errors before human review.*

### 4.3 Licensing (G1)
- [ ] `LICENSE` file (GPLv2+) at plugin root. *Why: the directory requires a compatible license on file.*
- [ ] GPL header in every PHP file; license declared in every bundled image/JS/font/doc where applicable. *Why: "all files" must be GPL-compatible.*
- [ ] No bundled third-party library with unverifiable or non-GPL-compatible licensing. *Why: unvalidatable licensing = library cannot be used.*
- [ ] Dependency audit (composer/npm) green. *Why: catches transitive non-compliant deps.*

### 4.4 Assets
- [ ] Banner (772x250 and 1544x500), icon (128x128 and 256x256), and screenshots placed in `/assets` for SVN. *Why: directory display requirements.*
- [ ] All assets local; none pulled from a CDN at runtime (G7). *Why: CDN-hosted assets are prohibited.*
- [ ] Screenshots do not contain external "powered by" links or endorsement language (G8, G10). *Why: no unopted external links; no misleading endorsement.*

### 4.5 SVN / Distribution (G3, G11)
- [ ] Complete plugin committed; `Stable tag` matches the tagged release. *Why: SVN is the distribution point; stable must exist.*
- [ ] No placeholder/stub modules at submission (G11). *Why: complete plugin required; names not reserved.*
- [ ] Trunk/tags structure correct; SVN is the distribution point. *Why: reviewers install from SVN.*

### 4.6 Security self-audit (G9)
- [ ] PHPCS WordPress ruleset clean on sanitization/escaping/nonce/capability. *Why: these are explicit security requirements.*
- [ ] Every REST write and admin form verified for nonce + `current_user_can`. *Why: forms/AJAX/REST writes must be protected.*
- [ ] Any raw SQL uses `$wpdb->prepare`. *Why: all SQL must be prepared.*

### 4.7 Telemetry / external requests (G6, G7, G8)
- [ ] Zero telemetry by default; zero phone-home to our infrastructure. *Why: no tracking without opt-in; no phone-home.*
- [ ] No CDN asset URLs. *Why: G7.*
- [ ] No injected public-facing external links without opt-in. *Why: G8.*
- [ ] BYO-key and IndexNow outbound calls are user-initiated and documented as such. *Why: distinguishes features from telemetry.*

### 4.8 Trademark (G10, Section 2.1)
- [ ] Final official trademark search for "Kernel"/"RankKernel" completed and documented. *Why: "Kernel®" is held by an unrelated CRM company; confirm no conflict.*
- [ ] No "WordPress" in slug, domain, or display name. *Why: explicit slug/name rule.*
- [ ] Competitor references limited to nominative fair use in import context. *Why: fair use allowed; endorsement implied is not.*

### 4.9 AI disclosure (AI Guidelines v0)
- [ ] `docs/ai-disclosure.md` updated with meaningful AI assistance for this release, in the standard format. *Why: disclosure is the community norm and supports "explain any code" responsibility.*

---

## 5. Review-Response Playbook

When a reviewer flags something, speed and precision matter: responsiveness correlates with approval.

### 5.1 General principles
- **Reply within 1–2 business days.** Acknowledge the specific point; do not argue guidelines. Responsiveness is itself a factor reviewers weigh.
- **Be concrete.** Cite the file/behavior and the exact fix. Reviewers respect "here is what changed and why it is now compliant."
- **Never restore removed code** (G2). If a reviewer says remove it, remove it for good. Re-adding disabled behavior is a prohibited circumvention.
- **Do not re-submit unchanged** hoping for a different reviewer. Address the substance.
- **Own the code.** If any line was AI-assisted, be ready to explain it (AI Guidelines v0: "code you cannot explain, do not submit"). The AI disclosure record helps here.

### 5.2 Common flag categories and responses

| Flag | Likely cause | Response playbook |
|---|---|---|
| **Licensing issue (G1)** | Bundled library with unverifiable license; missing GPL header. | Remove or replace the dependency with a GPL-compatible, verifiable one; add headers. Reply with the license proof. |
| **Paywall / not fully functional (G5)** | Reviewer reads BYO-key AI as a gate. | Explain: plugin is 100% functional free; user supplies their own third-party key; no payment, quota, or lock. Point to the AI Suite spec (Section 3.8). |
| **Telemetry / phone-home (G6)** | Outbound request mistaken for tracking. | Distinguish user-initiated feature calls (BYO-key to provider, IndexNow ping) from telemetry. Confirm zero analytics beacons; show static-analysis proof. |
| **CDN asset (G7)** | Remote `src`/`href` in enqueued asset. | Move asset into the plugin; enqueue locally; re-run PCP. |
| **External link (G8)** | "Powered by" anchor in frontend. | Remove injected link; keep any attribution admin-only and off by default. |
| **Security (G9)** | Missing escape/sanitize/nonce/capability. | Patch the specific route/form; attach PHPCS output showing clean. |
| **Trademark / slug (G10)** | "WordPress" in name; competitor name misused. | Confirm slug `rankkernel` has no forbidden term; show nominative fair use is limited to import labels with no endorsement. Provide trademark search result. |
| **Obfuscation (G4)** | Minified PHP or packed code. | Confirm PHP is plain; compiled JS bundles are allowed. Share build inputs if asked. |
| **Copy of another plugin (G13)** | Architecture looks cloned. | Show distinct design (single-key meta, hard-gated modules, lean storage); confirm no competitor source vendored. |
| **readme issues (G12)** | Wrong headers / SEO spam. | Fix `readme.txt` to standard format; re-validate via MCP/PCP. |
| **Uninstall retains data** | Reviewer sees no cleanup path. | Point to the explicit user-choice data-purge on uninstall; show the cleanup routine removes the single meta key and module tables when chosen (pillar: complete purge). |
| **Always-on module cost** | Reviewer suspects per-request queries when off. | Show the `ModuleManager` hard-gate: disabled modules register zero hooks; provide the evidence pack note (5.4). Never claim "zero-query redirects" — that overclaim was rejected. |

### 5.3 Escalation
- If a flag is unclear, ask a focused clarifying question rather than guessing.
- If we disagree on a factual point (e.g., AI Suite is not a paywall), present evidence calmly and offer the compliant interpretation; never circumvent.
- Keep the tone collaborative. The reviewer's decision is final; our job is to make the plugin clearly compliant.

### 5.4 Evidence pack to keep ready

Reviewers may ask for proof on short notice. Keep these artifacts current so replies are fast and concrete:

- **License manifest** — list of every bundled asset with its GPL-compatible license and source link (supports G1).
- **Dependency allowlist** — composer/npm tree with license verdicts (supports G1).
- **External-request map** — every outbound call in the plugin, labeled user-initiated vs none, with destination (supports G6/G7).
- **AI disclosure record** — `docs/ai-disclosure.md` in the standard format (supports AI Guidelines v0).
- **Trademark search result** — the final official search for "Kernel"/"RankKernel" (supports G10).
- **PCP report** — latest `wp plugin check` output showing "Plugin repo" category clean (supports the approval gate).
- **Module off-cost proof** — a note that disabled modules register zero hooks (supports the zero-cost-when-off pillar and G6/G8).

---

## 6. Compliance Summary (pillars → rules)

| RankKernel Pillar | Backing Rules |
|---|---|
| **100% Free** | G5 (no paywalls/quotas), G11 (complete at submission) |
| **100% Open** | G1 (GPLv2+ all files), AI Guidelines v0 (responsible, disclosed) |
| **Zero Database Bloat** | Single-key meta (Section 3.1), hard-gated modules (Sections 3.4/3.5), clean uninstall with user choice |
| **No paywalls / gated features** | G5, AI Suite spec (3.8) |
| **Hard-gated modules** | Zero cost when off, cache-first when on; never claim "zero-query redirects" |
| **Single-row metadata + full purge on uninstall** | G9 (sanitize/escape on write), uninstall cleanup routine |
| **Transparent / community-driven** | AI disclosure (2.2), open license |
| **Zero telemetry** | G6, G7, G8 enforced via static analysis |
| **Zero external requests by default** | G6; BYO-key/IndexNow are user-initiated exceptions, documented |

---

*This document is a requirements/planning artifact. It references class and function names only as specifications. No implementation code is contained herein. Rule facts are sourced from official WordPress.org materials read 2026-09 and must not be contradicted by later planning.*

---

## 7. Open Items Before Submission

One external action remains outside the codebase and must not be skipped:

- **Final official trademark search** for "Kernel" / "RankKernel" (the "Kernel®" mark is held by an unrelated CRM-data company, kernel.ai). Different industry and a distinctive compound lower the conflict risk, but the search result must be documented in the evidence pack (Section 5.4) before we submit. This is the only item in this doc that depends on a party other than the build team.

Everything else in this document is enforceable through CI, code review, and the pre-submission checklist (Section 4). Compliance for RankKernel is therefore mostly structural: the architecture that makes us lighter than Yoast and Rank Math (single-key meta, hard-gated modules, zero telemetry, clean uninstall) is the same architecture that satisfies the directory rules. Build to the pillars and the guidelines follow.
