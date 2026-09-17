# RankKernel Clean-Room Development Policy

*Copyright, trademark, and license compliance policy for the RankKernel WordPress SEO plugin.*

> **Status:** Project policy (Phase 1 onward). This document is internal engineering guidance, not legal advice. Where money, a launch, or a takedown notice is involved, consult a qualified IP attorney for final clearance. See the footnote in section 10.

---

## 1. Purpose and Scope

RankKernel is a free, lightweight, modular WordPress SEO plugin built to compete with Yoast SEO and Rank Math. This policy exists so we can study those products, learn from them, and ship a plugin that is legally clean and defensible.

The core principle is simple. **Reading to understand is fine. Copying expression is never.** We are allowed to know what competitors do and why. We are not allowed to take their creative work and pass it off as ours.

This policy covers:

- Every line of PHP, JavaScript, CSS, and HTML we write for the plugin.
- Every asset we ship: icons, banners, screenshots, logos, fonts, illustrations.
- Every dependency we add through Composer or npm.
- Every third-party API we call, including the BYO-key AI feature.
- Every translation and documentation file we publish.
- The naming and branding of the plugin itself.

This policy applies to the project owner and to any contributor, contractor, or AI assistant working on RankKernel. It is binding from the first commit.

The Phase 1 competitor audit (`docs/competitor-analysis/`) was a **clean-room analysis**: we read competitor source to understand behavior and produced our own specification documents. Those spec docs are the only legitimate source we build from. The competitor source itself is off limits while writing code.

### 1.1 How this policy fits the workflow

The intended flow is a one-way street. Audit reads competitor code and writes facts into spec docs. Design turns those facts into our own architecture. Implementation writes code from the spec docs alone. The competitor source is a closed book during design and implementation. If a question comes up that the spec docs do not answer, the answer is to extend the spec docs from a fresh, deliberate reading, not to peek while coding.

---

## 2. Legal Foundations: What Copyright Actually Protects

Copyright protects **expression**, not **facts**, **ideas**, **systems**, **functional methods**, or **interfaces**. This distinction is the whole game. Get it right and the rest of this policy is just detail.

### 2.1 The rule in plain terms

- **Facts are free.** The fact that WordPress fires `pre_get_document_title` and that a plugin can hook it at priority 15 is a fact about how the platform works. Anyone can use that fact. No one owns it.
- **Ideas are free.** "Add a focus keyphrase analysis box to the editor" is an idea. "Migrate a user's redirects from Yoast on activation" is an idea. Ideas cannot be copyrighted.
- **Expression is owned.** The specific way someone wrote their code, their prose, their CSS, their icons, their marketing copy: that is expression. Taking it is infringement.
- **Interfaces and data formats are facts.** A stored meta key like `_yoast_wpseo_title` is a data format, an interface between the database and the plugin. Reading it and writing our own converter is legitimate interop. Copying their converter code is not.

### 2.2 Concrete examples from THIS project

| What we learn | Type | What we may do | What we may NOT do |
|---|---|---|---|
| Yoast hooks `pre_get_document_title` at priority 15 | Fact | Hook the same filter at any priority we choose | Copy Yoast's `Title_Presenter` class body line for line |
| Rank Math stores titles under `rank_math_title` in `wp_postmeta` | Fact / interface | Read that key in our importer and map it to our storage | Reuse Rank Math's conversion function verbatim |
| Both plugins show a focus keyphrase field in the metabox | Idea | Build our own metabox with our own markup and labels | Clone their HTML, CSS, and help text |
| Yoast's `Title_Presenter` class has 40 lines of specific logic | Expression | Write our own title presenter from our spec | Paste those 40 lines, even with renamed variables |
| The set of JSON-LD `@type` values (Article, FAQ, HowTo) | Facts / standards | Emit the same schema.org types | Copy their schema-builder class or template strings |
| A competitor's README explains their redirect feature | Expression | Understand the feature from it | Reuse their sentences, examples, or screenshots in our docs |

The test is not "did I change a few variable names." The test is "did I write this myself, from a spec I authored, using only facts and ideas I am free to use."

### 2.3 The "substantial similarity" doctrine

Courts look at whether the protected expression is **substantially similar** to the original, not whether it is byte-identical. Two consequences for us:

- Renaming variables, reformatting, or splitting a function does not make copied code safe. If the structure, flow, and creative choices track the original, it is still infringement.
- Conversely, two plugins solving the same problem will naturally share facts (hook names, meta key purposes, schema.org types). Shared facts are not similarity of expression. We are fine as long as our expression is independently written.

When in doubt, rewrite from the spec and move on. The cost of rewriting is small; the cost of a takedown is not.

---

## 3. License Map of Audited Competitors

We audited three codebases in Phase 1. Their licenses dictate what we may touch.

| Product | Tier | Slug / path | License | Can we reuse its code? |
|---|---|---|---|---|
| Yoast SEO | Free | `wordpress-seo` | **GPL** (hosted on WordPress.org) | Technically GPL-reusable under the same license, but **we do not reuse it**. See rule 4.1. |
| Yoast SEO Premium | Paid | `wordpress-seo-premium` | **PROPRIETARY** (not on wp.org; its `license.txt` is proprietary) | **Never.** Copying even one line is straightforward infringement. |
| Rank Math SEO | Free | `seo-by-rank-math` | **GPL** (hosted on wp.org) | Technically GPL-reusable, but **we do not reuse it**. See rule 4.1. |
| Rank Math SEO | Pro | not in repo | **PROPRIETARY** (paid) | **Never.** No Pro source was even available to us. |

### 3.1 Why "GPL but we still don't copy"

Even where a competitor's free code is GPL, our policy is to **not copy it**. Reasons:

- GPL reuse would force us to carry their copyright notice and license, which muddies our own provenance and audit trail.
- Mixing their expression with ours creates ambiguity about what is ours. Clean-room discipline keeps ownership unambiguous.
- Premium tiers are proprietary regardless. Treating all competitor code as off limits is simpler and safer than tracking which line came from which tier.

So: GPL competitors are a **reference we may read for facts**, never a **source we copy**. Proprietary competitors are entirely off limits.

---

## 4. The Clean-Room Rulebook

These are the operating rules. Each is a hard requirement unless marked "recommended."

### 4.1 Source isolation

1. **Never open competitor source while writing RankKernel code.** The Phase 1 audit is done. Close those files. Build only from `docs/competitor-analysis/` spec docs and our own design docs.
2. **Derive implementations from behavioral spec docs only.** If a feature needs a fact we did not record in the spec docs, write the fact down in a spec doc first, then implement from the doc.
3. **One person should not be both the auditor and the implementer of the same feature** where practical. The auditor learns facts; the implementer writes from the spec. This separation is the heart of clean-room method.

### 4.2 Code and naming

4. **Write every function, class, and template yourself.** No pasting, no "lightly edited" copies, no translated variable names.
5. **Avoid wholesale-adopted identical key names where we can.** Our own meta keys should be namespaced to `rankkernel_`: the per-post payload lives in `_rankkernel_meta_data` and per-term data in `_rankkernel_term_data` (see `architecture/blueprint.md` §A), while other stored values use the `rankkernel_` prefix (for example `rankkernel_title`). Do not mirror `_yoast_wpseo_*` or `rank_math_*` naming for our own storage.
6. **Identical key names are allowed ONLY for migration interop.** Our `CompetitorImporter` MUST read competitor meta keys (`_yoast_wpseo_title`, `rank_math_title`, etc.) from `wp_postmeta` to migrate user data. Reading and interoperating with documented data formats is legitimate. This is exactly what Yoast's and Rank Math's own importers do to each other.
   - **The interop rule:** we may **READ** their stored data formats and **WRITE** our own conversion code. We may **NOT** copy their conversion code.
   - Document every competitor key we read in `docs/competitor-analysis/` so the reading is traceable and intentional, not a copy-paste habit.
7. **No copying CSS, JavaScript, images, logos, icons, README text, or translation files** from any competitor, free or paid.

### 4.3 Assets and dependencies

8. **All shipped assets must be original or licensed CC0 / GPL-compatible.** Icons, banners, screenshots, fonts, illustrations: create our own, or pull from a source with a verifiable CC0 or GPL-compatible license. Keep the license file or URL in an `assets/PROVENANCE` note.
9. **Dependency license audit is mandatory before adding any Composer or npm package.** Procedure:
   - Identify the package's declared license.
   - Confirm it appears on the GNU license list as GPL-compatible (or is GPL/CC0/MIT/ BSD/Apache-2.0 with GPL-compatible terms).
   - If the license is **unverifiable or non-compatible, do not add it.** WordPress.org rule #1 requires a verifiable GPL-compatible license for every third-party library; unverifiable means it cannot ship.
   - Record the package name, version, license, and source URL in a `docs/dependency-audit.md` entry.
10. **Third-party service ToS compliance is required for the BYO-key AI feature.** When a user supplies their own OpenAI, Anthropic, or Google API key, we send their content to that service. We must:
    - Document that the user is the data controller and supplies their own key.
    - Comply with each provider's API Terms of Service (no prohibited use, proper attribution if required).
    - Never store the user's key in plaintext where it leaks; handle it per our security policy.
    - Keep our integration code our own; do not embed provider SDK code copied from elsewhere without license check.

### 4.4 Naming and trademark

11. **The plugin name is "RankKernel", slug `rankkernel`.** Verified: zero collisions on WordPress.org and globally. "Kernel®" is trademarked by kernel.ai (CRM data industry, unrelated); risk is low, but run a final official trademark search before submission. The official wp.org "Plugin Namer" tool can pre-check the slug.
12. **Do not use another project's trademark as the sole or initial slug term.** No "yoast-", "rank-math-", or "wp-" as our slug prefix. WordPress.org rule #17 forbids another project's trademark as the slug's leading term and forbids "WordPress" in the slug or domain.
13. **Do not imply affiliation.** Our docs, UI, and store listing must not suggest we are endorsed by or affiliated with Yoast, Rank Math, or WordPress. Comparative statements must be factual and our own wording.
14. **Our plugin must not be a "100% copy of another plugin."** WordPress.org's review checklist rejects clones. We differentiate on architecture (single-key storage, no per-request query tax, clean uninstall), not by reskinning a competitor.

### 4.5 Practical hygiene (recommended)

15. **Run a periodic self-audit.** Before each release, grep the tree for competitor identifiers (`yoast`, `rank_math`, `wpseo`) and confirm every hit is either an intentional interop read (documented) or absent. Unexpected hits mean a boundary was crossed.
16. **Keep competitor source out of the working copy.** The audited plugins live in a separate, non-shipping location. Do not symlink or copy them into the RankKernel project folder. Out of sight reduces accidental reference.
17. **Review AI-generated diffs for "too-familiar" patterns.** If a snippet looks like it came from a known tutorial or a competitor, rewrite it. Familiarity is a smell.
18. **Document our differentiators explicitly.** Maintain a short "why RankKernel is not a clone" note (single meta key per post, clean uninstall that actually deletes, no per-request redirect query, modular load gating) to show independent design intent.

---

## 5. AI-Assisted Development Policy

We may use AI tools (including the models behind this very assistant) to help write RankKernel. The 2026 WordPress AI Guidelines shape how.

1. **The developer is fully responsible for every line.** AI assistance does not lower the bar. The human developer must be able to explain and defend each line shipped.
2. **Explainability requirement.** If you cannot explain why a block of code exists and what it does, do not ship it. AI output that is not understood is a liability, especially under this clean-room policy.
3. **AI output must be GPL-compatible.** The terms of any AI tool we use must permit us to release its output under GPLv2+. If a tool's terms are incompatible or unclear, do not use its output in the plugin.
4. **Disclosure norm.** Where the community or wp.org expects it, note that AI assistance was used in development. This is a norm, not a hiding game; be straightforward about it.
5. **AI does not get a clean-room exemption.** An AI model trained on competitor code can reproduce their expression. The rules in section 4 apply fully: the human must verify the output is original, our own, and sourced from our spec docs, not from memorized competitor code. Treat suspiciously similar output as a red flag and rewrite it.

---

## 6. Asset Provenance Rules

1. **Create our own icons, banners, and screenshots** wherever possible. Original work is the safest provenance.
2. **If we use third-party assets, they must be CC0 or GPL-compatible**, with the license and source recorded in `assets/PROVENANCE`.
3. **No competitor UI screenshots in our docs or store listing.** A screenshot of Yoast's or Rank Math's admin screen, even with our own commentary, invites confusion and trademark issues. Show our own UI only.
4. **Logos and wordmarks are ours.** Do not reuse any competitor logo, icon, or brand element, even "just for reference" in a draft. Reference mentally, recreate originally.
5. **Fonts must be licensed for web embedding** under GPL-compatible terms (for example, SIL OFL fonts are fine; proprietary fonts are not).

---

## 7. Translation and i18n File Policy

1. **We maintain our own `.pot` template**, generated from our own strings. Never reuse a competitor's `.pot` or `.po` file.
2. **Translation files (`.po` / `.mo`) are our own work** or contributed by our community under GPL. Do not import another plugin's translations.
3. **String keys are ours.** Our text domain is `rankkernel`. Do not copy competitor string identifiers or wording.
4. **If a contributor translates, their work must be original phrasing**, not a machine copy of a competitor's existing translation.

---

## 8. Audit Trail Practice

The goal: anyone reviewing RankKernel later can see that each feature was designed from **our** spec docs, not copied.

1. **Spec docs are the source of truth.** Keep behavioral specs in `docs/competitor-analysis/` and our own design docs. Reference them by file when implementing.
2. **Cite the spec, not the source.** In code comments or commit messages, write "per docs/competitor-analysis/feature-matrix.md" not "mirrors yoast class X". The spec doc is the legitimate reference; the competitor source is not.
3. **Record every competitor data key we read** for interop in a dedicated section of the importer spec, with the reason (migration of user data). This makes the reading intentional and auditable.
4. **Keep a dependency audit log** (`docs/dependency-audit.md`) with package, version, license, compatibility verdict, and date for every third-party library.
5. **Commit discipline.** Each feature lands with a commit that references the spec doc it implements. If a reviewer cannot trace a feature to a spec, the traceability is incomplete and must be fixed.
6. **Pre-submission checklist.** Before any wp.org submission, confirm: no competitor code in the tree, all assets accounted for in provenance, all deps audited, slug and name cleared, and the "not a clone" differentiation statement documented.

### 8.1 Example audit entries

A good spec-to-code trace looks like this in a commit message:

```
feat(title): add title presenter
Implements docs/competitor-analysis/feature-matrix.md (title rendering row).
Behavioral spec only; no competitor source referenced during implementation.
```

A good interop read is recorded in the importer spec like this:

```
Competitor keys read for migration (legitimate interop, facts/interfaces):
- _yoast_wpseo_title  -> maps to rankkernel_title
- rank_math_title     -> maps to rankkernel_title
Conversion logic written fresh; no competitor conversion code reused.
```

A good dependency entry in `docs/dependency-audit.md`:

```
Package: psr/log
Version: 3.0.0
License: MIT (GPL-compatible per GNU list)
Source: https://github.com/php-fig/log
Verdict: OK to ship
Date: 2026-09-08
```

These small records are what turn "we think we're clean" into "we can prove we're clean."

---

## 9. Quick-Reference Decision Table

**Can I...?**

| Question | Answer | Why |
|---|---|---|
| Read Yoast/Rank Math source to understand behavior? | Yes (audit phase only) | Facts and ideas are free; reading to learn is fine. |
| Keep competitor source open while writing our code? | No | Clean-room separation; build from spec docs only. |
| Copy a competitor's GPL free code into RankKernel? | No | We don't reuse any competitor code, GPL or not. |
| Copy Yoast Premium or Rank Math Pro code? | No, ever | Proprietary; one line is infringement. |
| Use the same WordPress hook at the same priority? | Yes | That's a fact about the platform. |
| Recreate a competitor feature from our own spec? | Yes | Ideas are free; our implementation is ours. |
| Read `_yoast_wpseo_title` / `rank_math_title` from postmeta to migrate? | Yes | Interop with a documented data format is legitimate. |
| Copy their conversion/migration code? | No | That code is their expression. |
| Name our meta keys `_yoast_wpseo_*` or `rank_math_*`? | No (except reading for interop) | Use our own `rankkernel_` namespace for storage. |
| Use a competitor's CSS/JS/images/logo/icons? | No | Those are protected expression and trademarks. |
| Add a Composer/npm dep without a license audit? | No | wp.org requires verifiable GPL-compatible licenses. |
| Add a dep whose license is unverifiable? | No | Unverifiable means it cannot ship. |
| Use OpenAI/Anthropic/Google APIs for BYO-key AI? | Yes, with ToS compliance | User is controller; follow each provider's terms. |
| Name the plugin "RankKernel", slug `rankkernel`? | Yes (clear trademark first) | Verified clear; run final trademark search pre-submit. |
| Use "yoast" or "rank-math" as our slug prefix? | No | wp.org rule #17: no other project's trademark in slug. |
| Put "WordPress" in our slug or domain? | No | Forbidden by wp.org rule #17. |
| Ship a UI that is a reskin of a competitor? | No | wp.org rejects 100% copies. |
| Use AI to help write code? | Yes, with explainability | 2026 wp.org AI guidelines; developer owns every line. |
| Reuse a competitor's `.po`/`.pot` translations? | No | Their translation files are their expression. |
| Screenshot a competitor's admin screen in our docs? | No | Trademark and confusion risk; show only our UI. |

---

## 9.1 Decision log

Recorded decisions that settle open questions, so they are not relitigated in a later pass.

| Date | Decision | Rationale |
|---|---|---|
| 2026-09-17 | Visual parity with competitors targets an **equivalent user experience, independently implemented**. A 100 percent visually identical clone of a competitor admin UI is explicitly out of scope, even though both audited competitors are GPL and reuse would be lawful. | The owner was offered a choice between relaxing this policy to lift competitor CSS and markup wholesale for an exact visual match, and keeping clean room for close visual equivalence. The owner chose clean room. Reuse would forfeit the isolation this document exists to establish, and the competitor admin stylesheets are compiled bundles whose generated class names are themselves their expression. The achievable and adopted target is identical layout structure, field order, grouping, control anatomy, spacing rhythm and states, built from RankKernel classes and the tokens in `assets/css/rankkernel-admin.css`. What differs is pixel values and generated class names, which no user perceives. |
| 2026-09-17 | A competitor JavaScript bundle, for example a minified admin application file, must **not** be read in order to reproduce layout or styling. Behaviour may be observed from a running instance and from official documentation. | Reading a minified bundle to replicate presentation is reading their implementation, which section 4.1 forbids. Observing the rendered interface and its behaviour stays on the permitted side of the line. |
| 2026-09-17 | Competitor references are permitted in three specific shapes only: behavioural observation of a running instance, official vendor documentation, and mapping tables that let our importer READ their stored keys during migration. Their source, CSS, markup, icons and class names remain off limits. | This is the boundary that keeps the importer lawful, since reading `rank_math_title` in order to migrate it is interoperability rather than copying. A repository scan must keep finding zero competitor identifiers in `src/` and `assets/`. |

---

## 10. Footnote: This Is Policy, Not Legal Advice

This document sets internal engineering standards for RankKernel. It is written by a developer, not an attorney, and it does not constitute legal advice.

Copyright and trademark law are fact-specific. Before public release, and especially if you receive any complaint, takedown, or legal inquiry, **consult a qualified intellectual property lawyer** for formal clearance. The cost of a short review is trivial next to the cost of a takedown or a lawsuit.

Where this policy says "low risk" or "verify," treat that as a prompt to confirm with counsel, not as a guarantee.
