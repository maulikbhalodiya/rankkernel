# Content Analysis research gate

Evidence base and build decision for the `analysis` module (ROADMAP 1.3.3).
Compiled 2026-09-19 from the competitor sources below, then reduced to a build plan.

Status: decision recorded, build starting.

---

## 1. Sources

Competitor rule inventories were read from source, not from marketing pages.

| Source | What it gave |
|---|---|
| `rankmath/content-analyzer` @ `79871a4` (`src/analysis/*.js`) | Every free on-page test, its weight, its threshold |
| `rankmath/seo-by-rank-math` @ `a9a9e44` | Test registration, keyword limits |
| Rank Math KB: `seo-score-vs-content-ai-score`, `score-100-in-tests`, `disable-seo-content-tests`, `what-is-a-focus-keyword` | Canonical test list, colour bands, per-test strings |
| `Yoast/wordpress-seo` @ `5602d492` (`packages/yoastseo`, `SCORING SEO.md`, `SCORING READABILITY.md`, `KEYPHRASE MATCHING.md`) | Every free assessment, its score, its thresholds |
| Yoast KB and feature pages | Free versus Premium boundary |
| WordPress.org support forums, r/SEO, r/WordPress, r/freelanceWriters, agency audits | Real user demand and pain |

---

## 2. What the competitors actually do

### Rank Math free

- **5 focus keywords**, index 0 is primary. Primary only runs title, description, URL, first 10 percent, image alt, uniqueness and title-position tests. Secondary keywords run content, subheading and density tests, and each gets its own score pill. PRO is unlimited.
- Scoring is weighted: `score = round(earned / applicable_max * 100)`. Title is worth 36, content length 8, density 6, media 6, URL 5, internal links 5, and so on, about 95 to 99 applicable points for a typical post.
- Bands: green 81 to 100, yellow 51 to 80, red below 50.
- Matching is a lowercased substring, diacritics stripped. No stemming. Variation is not understood, which is the root of the complaint below.
- Free runs 20 local tests. The Content AI test and every suggestion endpoint are remote and are excluded here.

### Yoast free

- **1 focus keyphrase.** Premium adds synonyms, 4 related keyphrases, word forms, keyphrase distribution and word complexity.
- Matching is **per word inside one sentence**, with function words filtered, so `healthy cat food` matches `Your cat may like a food brand that's not that healthy.` Order and proximity are ignored.
- Each assessment scores 0 to 9. Overall is `round(sum / (count * 9) * 100)`. Bands: GOOD 71 to 100, OK 41 to 70, BAD 1 to 40.
- Free runs 16 SEO assessments and 7 readability assessments.
- Penalty scores exist (`-10`, `-20`, `-50`) for density extremes and short text, which is why the readability checks feel punishing.

### The real user signal (this drives our design)

Strongest and best evidenced, across every source sampled:

1. **Multiple or supporting keywords is the single most requested gap.** Yoast free users ask for it repeatedly, and it is the reason many leave Yoast. Rank Math leads with it in its own marketing.
2. **Exact-match density is the single biggest complaint.** Rank Math's own support replies concede the engine looks for exact matches, so a natural variation is reported as missing, and users report being pushed to repeat a keyword about a dozen times. There is a 2025 to 2026 wave of density `0.00%` bug reports across Korean, Bulgarian, Arabic and Ukrainian sites.
3. **The score is widely distrusted.** Both the SEO community and the vendors themselves say the number does not correlate with ranking, and agency audits repeat that a green light never meant a ranking.
4. **Scoring is accused of encouraging keyword stuffing and bad writing.** Vendors concede it in their own docs.
5. **Readability checks are called too strict and infantilising**, with passive voice, transition words and sentence length named most often.
6. **Page builder and custom field content is invisible** to the analyser, so good pages go red.

Thinly evidenced, so not being built on: entity coverage and topical authority as explicit user requests, and local language stemming as a named request. The symptom users report is `keyword not found`, not `I want stemming`.

---

## 3. Product decision

Build the parity engine, then fix the two things users actually complain about.

1. **One primary keyword plus unlimited supporting keywords, free.** Primary is `focus_keywords[0]`, supporting keywords are the rest. No cap in the free tier, which beats Rank Math free at 5 and Yoast free at 1. This reuses the existing payload key, so no meta migration is needed.
2. **Variation aware matching, and never pressure toward repetition.** A keyword counts as present when any of these hold: the exact phrase appears, all content words appear within one sentence in any order (the Yoast model), or the phrase appears with only function words inserted or removed. This directly answers the `Bicycles for women` versus `Bicycles women` complaint.
3. **Density advises a band, it does not reward repetition.** The band is reported as a range with the actual count, and the copy never tells a writer to add the keyword more times.
4. **Per check guidance first, one score second.** Every check carries its own pass, improve or problem state and a plain sentence, and the numeric score is a summary of those, never a target to chase.
5. **Builder aware.** In the block editor the analyser reads the real edited content from `wp.data`, so block content counts. In Classic it reads the editor content field.
6. **Approximate length signals only.** No penalty thresholds that push writers to pad an article.

Local only. No external service, no AI, no remote call, in keeping with free forever.

---

## 4. Check specification

Every check is local and deterministic. `P` means it runs for the primary keyword, `S` means it also runs per supporting keyword, `C` means it is content only.

### SEO

| Check | Scope | Pass condition | Weight |
|---|---|---|---|
| Keyword in SEO title | P | keyword present (variation aware) | 36 |
| Keyword in meta description | P | keyword present | 2 |
| Keyword in URL slug | P | keyword (stopwords optional) present in slug | 5 |
| Keyword in opening 10 percent | P | present in the first `floor(words * 0.10)` words, whole text when under 400 words | 3 |
| Keyword in content | P S | present anywhere in the body text | 3 |
| Keyword in a subheading | P S | present in any `h2` to `h6` | 3 |
| Keyword in an image alt | P | present in any `alt`, singular or plural | 2 |
| Keyword density | P S | band 0.5 to 2.5 percent, reported with the real count | max 6 |
| Content length | C | 600 words minimum, full marks at 2500 | max 8 |
| URL length | C | 75 characters or fewer | 4 |
| Internal links | C | at least one | 5 |
| External links | C | at least one | 4 |
| At least one followed external link | C | a dofollow outbound link exists | 2 |
| Keyword not already used | P | no other post targets this keyword | 0, advisory |

### Title readability

| Check | Scope | Pass condition | Weight |
|---|---|---|---|
| Keyword in the first half of the title | P | position under half the title length | 3 |
| Title contains a number | C | any digit | 1 |
| Title contains a power word | C | matches the bundled list | 1 |
| Title sentiment | C | non neutral, English only | 1 |

### Content readability

| Check | Scope | Pass condition | Weight |
|---|---|---|---|
| Short paragraphs | C | every paragraph at or under 120 words | 3 |
| Sentence length | C | at most 25 percent of sentences over 20 words | 3 |
| Subheading distribution | C | no gap over 300 words without a subheading | 3 |
| Consecutive sentences | C | fewer than 3 consecutive sentences starting with the same word | 3 |
| Passive voice | C | at most 10 percent of sentences | 3 |
| Transition words | C | at least 30 percent of sentences, waived on short text | 3 |
| Media present | C | images and video, partial credit, full at 3 images | max 6 |
| Single `h1` | C | fewer than 2 `h1` elements | 3 |
| Table of contents present | C | a TOC block is present | 2 |
| Text present | C | at least 50 characters, gates the readability set | 3 |
| Keyword distribution | P S | no section is left unmentioned across 15 plus sentences | 3 |

The keyword distribution check is a deliberate free win. It is Premium in Yoast and absent in Rank Math free.

### Scoring

`score = round(earned / applicable_max * 100)`, the same shape both competitors use, so the number is familiar. Bands: good 81 to 100, improve 51 to 80, problem below 50. Checks that do not apply are excluded from the denominator rather than scored zero. Supporting keywords each get their own score pill, computed from the `P S` checks only.

---

## 5. Architecture

| Piece | Path |
|---|---|
| Module | `src/Modules/Analysis/AnalysisModule.php` (new id `analysis`, priority after `metadata`) |
| Keyword matching | `src/Modules/Analysis/KeywordMatcher.php` |
| Text statistics | `src/Modules/Analysis/TextStats.php` |
| Check registry and scoring | `src/Modules/Analysis/Analyzer.php` |
| Editor JS | `assets/js/analysis-editor.js`, plus mounting the existing `FocusKeywordsInput` and `ContentAnalysisChecklist` in `metadata-sidebar.js` |
| Payload | reuse `MetaPayload` `focus_keywords`, index 0 is primary. No schema change. |
| Editor attach points | `src/Admin/Views/metadata-box.php:198` (reserved placeholder) and `metadata-sidebar.js:1849`, `:1927` (dead helpers) |

Registered in three places, as every module is: an id in `ModuleRegistry`, a `register()` and `boot()` pair in `Plugin.php`, and its own gated bootstrap. Disabled means zero hooks and zero assets.

The editor computes locally. A PHP parity path runs the same engine for REST and headless later, in the same phase as 1.8, so the two never diverge silently.

---

## 6. Phases

1. **Engine.** `KeywordMatcher`, `TextStats`, `Analyzer` with the full check set and scoring, plus unit tests per check using positive and negative fixtures. No UI.
2. **Editor surface.** Mount the keyword input and checklist in the Gutenberg sidebar, add the Classic analysis panel at the reserved placeholder, and drive both from the engine.
3. **Parity and polish.** PHP parity endpoint, keyword uniqueness against the database, per check copy, and a browser pass on a real post.

Phase 1 is the deliverable that makes phases 2 and 3 thin.

---

## 7. Out of scope, with reasons

- **Live SERP rank tracking.** Needs a paid service, recorded as EXTERNAL in the parity matrix and deferred. The only free path noted by the project is a later read-only Search Console bridge, which is its own feature.
- **AI generation.** STEP 3, BYO key only.
- **Entities, topical authority, information gain, E-E-A-T scoring.** Thin user evidence and not deterministic. The honest position is that no local analyser can measure them.
- **Word complexity, inclusive language, synonyms.** Premium competitor features, and not requested by free users. Candidates for a later pass once parity is done.

---

## 8. Acceptance criteria

- One primary plus unlimited supporting keywords, analysed free and locally.
- A variation of the keyword, including a different word order or an inserted function word, is recognised, so a writer is never told to repeat the exact phrase.
- Density copy never asks for more repetitions.
- Every check has a pass, improve or problem state with a plain sentence.
- The block editor analyses the real edited content, not just the database row.
- Disabled module costs zero hooks and zero assets.
- PHP and JavaScript agree on the same input, proven by a parity test.
