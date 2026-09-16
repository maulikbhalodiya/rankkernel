# Rank Math PRO — Code-Verified Feature Inventory

**Source of truth (actual local plugin files, not marketing):**

- PRO: `wp-content/plugins/seo-by-rank-math-pro/` — version **3.0.109** (`rank-math-pro.php:12`, `:39`)
- Free: `wp-content/plugins/seo-by-rank-math/` — version **1.0.278** (`rank-math.php` header)

**Method:** every PRO module directory and every core `includes/` class was read; free-plugin counterparts and the gating flags (`probadge`, `upgradeable`, `disabled` in free `includes/module/class-manager.php`) were cross-referenced. Every feature row carries a real `file:line` citation. Regenerate with the same plugin versions if the code moves.

**Gating context (free plugin `includes/module/class-manager.php`):**
Modules flagged `upgradeable` (free ships limited code, PRO replaces it): `404-monitor:118`, `local-seo:127`, `redirections:136`, `rich-snippet:145`, `image-seo:178`, `content-ai:196`, `analytics:271`, `seo-analysis:281`, `woocommerce:368`.
Modules flagged `probadge` + `disabled` (no free code at all): `link-genius:168`, `news-sitemap:212`, `video-sitemap:221`, `podcast:230`, `bbpress:348` (bbPress flag is conditional on `RANK_MATH_PRO_FILE`).

At runtime the free loader keeps module IDs and PRO swaps in its own classes: `includes/class-modules.php:90` replaces `rich-snippet` class with `\RankMathPro\Schema\Schema`; `:92-96` renames free `link-genius` to `link-counter` (the free one is the basic link counter).

**Paid-service flag:** "yes" = the feature cannot function offline without a paid Rank Math plan / Content AI credits / RankMath.com account / a billing-enabled external key. "no" = runs entirely locally (free third-party plugins like ACF, bbPress, WooCommerce, Elementor, Divi are dependencies, not paid Rank Math services).

---

## 0. Core / non-module PRO features (`includes/`, `rank-math-pro.php`)

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Requires-manual | Hides "Upgrade to PRO" notice when PRO active | `includes/admin/class-admin.php:186` | admin notice filter | no |
| Free-version auto-install/activate | Auto-installs + activates free Rank Math from wordpress.org, auto-deactivates self on failure | `rank-math-pro.php:413`, `:501`, `:193` | activation lifecycle | no |
| Thumbnail Overlays (watermarks) | Custom social image watermarks with position choices + defaults per post type/taxonomy | `includes/class-thumbnail-overlays.php:319`, `:367`, `:391` | option keys `titles.custom_image_overlays`, `titles.default_image_overlay`, `titles.pt_{type}_image_overlay`, `titles.tax_{tax}_image_overlay`; filters `rank_math/social/overlay_images`, `overlay_image_position(s)` | no |
| Overlay meta defaults | Applies default watermark to post/term/user meta when unset | `includes/class-thumbnail-overlays.php:210`, `:227`, `:244` | filters `default_post_metadata`, `default_term_metadata`, `default_usermeta` | no |
| Random-word replacement vars | `%randomword(w1\|w2)%` persistent + `%randomword_np()%` non-persistent replacement variables | `includes/class-register-vars.php:37`, `:48`, `:67`, `:93` | variable replacements | no |
| Increase focus-keyword max tags | Raises focus-keyword limit to 100 | `includes/class-common.php:53` | filter `rank_math/focus_keyword/maxtags` | no |
| Affiliate-link external detection | Cloaked affiliate prefixes counted as external + `sponsored` rel added | `includes/class-common.php:90`, `:113`; `includes/admin/class-links.php:102` | option `general.affiliate_link_prefixes`; filters `wp_helpers_is_affiliate_link`, `rank_math/link/add_attributes`, `rank_math/links/is_external` | no |
| Search-Intent for keyword | Calls Content AI `keyword_intent` endpoint, caches intent per keyword, shows intent badge in SEO Details column | `includes/class-common.php:220`; `includes/admin/class-admin-helper.php:626`, `:672`; `includes/admin/class-quick-edit.php:651` | option `rank_math_search_intent`; setting `general.determine_search_intent`; REST `/searchIntent` | yes — Content AI |
| Product tests in content analysis | Adds `hasProductSchema` / `isReviewEnabled` tests and drops irrelevant tests for Woo/EDD products | `includes/class-common.php:126`, `:151` | filter `rank_math/researches/tests` | no |
| Product gallery in SEO-score tool | Feeds gallery images into recalc data | `includes/class-common.php:167`, `:232`; `assets/admin/js/product-analysis.js` | filter `rank_math/recalculate_score/data` | no |
| Trends (Google Trends) in editor | Enqueues editor trends scripts; upgrade CTA when not connected | `includes/admin/class-trends-tool.php:43`; `includes/class-common.php:65` | scripts `rank-math-pro-editor`, `assets/admin/js/gutenberg.js` | yes — trends API/connection |
| Quick Edit + Bulk Edit of SEO fields | Inline edit of SEO title/desc/robots/focus keyword/canonical/primary term on posts and terms | `includes/admin/class-quick-edit.php:37`, `:196`, `:402`, `:478`, `:564` | Quick/Bulk edit UI; hidden fields in `rank_math_seo_details` column | no |
| Term SEO Details column | Adds `rank_math_tax_seo_details` column to term lists | `includes/admin/class-quick-edit.php:61` | admin column | no |
| Bulk SEO actions (posts) | Robots index/noindex/nofollow/follow, redirect, stop-redirect, schema none/default, remove canonical, determine search intent | `includes/admin/class-bulk-actions.php:86-228` | bulk actions `rank_math_bulk_*` | intent action yes — Content AI |
| Bulk SEO actions (terms) | Robots + redirect + stop-redirect for taxonomies | `includes/admin/class-bulk-actions.php:258`, `:277` | bulk actions | no |
| Bulk primary-term save | Saves primary term via bulk edit | `includes/admin/class-bulk-actions.php:359` | postmeta `rank_math_primary_{tax}` | no |
| Post-list SEO filters | Dropdown: custom canonical/title/description, redirected, orphan, schema-type | `includes/admin/class-post-filters.php:45`, `:70`, `:120`, `:210`, `:302`, `:321` | GET `seo-filter`, `schema-filter`; `posts_where` | no |
| Media Library SEO filters | Missing alt / missing title / missing caption (grid + list) | `includes/admin/class-media-filters.php:32`, `:66`, `:91`, `:152`, `:167` | GET `seo-filter` on `upload.php`; AJAX `ajax_query_attachments_args` | no |
| CSV Import/Export panel (Status & Tools) | Export/import SEO meta for posts, terms, users; 20+ columns; background import | `includes/admin/csv-import-export/class-csv-import-export.php:66`, `:125`, `:163`, `:243`, `:282`; `class-exporter.php`; `class-importer.php`; `class-import-row.php`; `class-import-background-process.php` | screen `rank-math-status&view=import_export`; options `rank_math_csv_import*`; REST `/importCSV`, `/cancelCsvImport`; AJAX `csv_import_progress` | no |
| CSV export columns | id, object_type, slug, seo_title, seo_description, is_pillar_content, focus_keyword, seo_score, robots, advanced_robots, canonical_url, primary_term, schema_data, social facebook/twitter thumbnail/title/description (+ redirect_to/redirect_type when redirections active) | `includes/admin/csv-import-export/class-csv-import-export.php:243` | CSV file | no |
| CSV → CSV helper | Shared CSV writer used by 404/redirections exporters | `includes/admin/class-csv.php:28` | internal | no |
| API client (RankMath.com) | siteSettings / deactivateSite / siteStats / keywordsInfo calls | `includes/admin/class-api.php:269`, `:300`, `:324`, `:341`, `:355` | external RankMath.com API | yes — RankMath.com |
| License activation / enroll site | Auto-registers site from `licence-data.php` constants via `enrollSite` | `includes/admin/class-licence-activation.php:41`, `:92`, `:77` | option `rank_math_reseller_data`; external endpoint | yes — RankMath.com |
| Setup wizard PRO steps | Sitemap step gains news/video sitemap toggles; Analytics step gains countries + `console_email_send_to` | `includes/class-setup-wizard.php:38`, `:111`, `:130`, `:159`, `:178` | wizard screen `rank-math-wizard` | partly (console email via RankMath.com) |
| Setup-wizard settings import | AJAX import of exported settings JSON incl. modules/options/roles/redirections | `includes/class-setup-wizard.php:75`, `:222`, `:274` | AJAX `rank_math_import_settings`; options `rank-math-options-*`, `rank_math_modules` | no |
| REST `pingSettings` | Receives plan + keyword quota + analytics settings from RankMath.com | `includes/class-rest.php:42`, `:201` | REST `rankmath/v1/pingSettings` | yes — RankMath.com |
| REST `searchIntent` | Determines + stores search intent for a keyword | `includes/class-rest.php:53`, `:167` | REST `rankmath/v1/searchIntent` | yes — Content AI |
| REST `importCSV` / `cancelCsvImport` | Starts / cancels CSV metadata import | `includes/class-rest.php:64`, `:74`, `:123`, `:156` | REST `rankmath/v1/importCSV`, `/cancelCsvImport` | no |
| PRO schema REST controller | `saveTemplate` + `getVideoData` registered from bootstrap | `rank-math-pro.php:301`; `includes/modules/schema/class-rest.php:42`, `:53` | REST `rankmath/v1/saveTemplate`, `/getVideoData` | no |
| AMP redirection | Auto-creates `/amp/` redirection mirroring a post redirection | `includes/admin/class-amp.php:45` | hook `rank_math/redirection/post_updated` / `term_updated` | no |
| Plugin update manager | Custom update transient, beta opt-in, auto-update, license notices, forced network checks | `includes/plugin-update/class-plugin-update.php:71`, `:300`, `:347`, `:483`, `:953`, `:1121` | external `rankmath.com/wp-json/rankmath/v1/versionCheck`,`/updateCheck2`,`/pluginInfo`; options `rank_math_pro_versions`, `rank_math_pro_updates` | yes — RankMath.com |
| Versioned DB/option migrations | 14 update routines (options, schema type migrations, GA referrer column, Link Genius schema) | `includes/class-updates.php:30`; `includes/updates/update-*.php` | on `admin_init` | no |
| Divi integration | FAQ schema from Divi accordions; Rank Math tab (news sitemap robots) in Divi builder | `includes/3rdparty/divi/class-divi.php:38`, `:165`, `:245`, `:339` | filters `et_builder_get_parent_modules`, `rank_math/json_ld` | no |
| Elementor integration | Breadcrumbs widget + FAQ Schema switch on Accordion/Nested-Accordion → FAQPage JSON-LD | `includes/3rdparty/elementor/class-elementor.php:31`, `:66`, `:75`, `:94`; `class-widget-breadcrumbs.php:26` | Elementor widget `breadcrumbs`; control `rank_math_add_faq_schema` | no |
| WPML integration | News sitemap language per post via WPML | `includes/3rdparty/class-wpml.php:28`, `:40` | filter `rank_math/sitemap/news/language` | no |
| WP Media plugin-family (Imagify) | Imagify install/activate + tracking | `includes/3rdparty/plugin-family/class-plugin-family.php:42`, `:63` | AJAX `install_imagify`; option `imagifyp_id` | no |

---

## 1. `modules/404-monitor/` — PRO delta over free

Free already captures 404s, has referer/user-agent columns, bulk redirect/delete, dashboard widget. PRO adds (all cite `includes/modules/404-monitor/class-monitor-pro.php`):

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| CSV date-range export panel | "Export 404 Logs" box with From/To datepickers | `:72` | `rank-math-404-monitor` screen, `rank_math/404_monitor/before_list_table` | no |
| Export title action | "Export" page-title button | `:57` | `rank_math/404_monitor/page_title_actions` | no |
| Export handler | Dumps `rank_math_404_logs` to CSV (optional dates) | `:113` | GET `action=rank_math_export_404` | no |
| Aggregated Hits column | `total_hits` per URI in advanced mode | `:211`, `:228` | `rank_math/404_monitor/list_table_columns` | no |
| URI drilldown filter | `?uri=` filters logs to one URI | `:246` | GET `uri` | no |

---

## 2. `modules/redirections/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| `.htaccess` in-place sync | Replaces Rank Math block in `.htaccess` with generated Apache rules (1000 redirects, query-string conds, 410→G) | `includes/modules/redirections/class-redirections.php:60`, `:85`, `:119`, `:269` | Export tab; cap `edit_htaccess` | no |
| Auto-redirect tracking | Stores created redirection IDs per post/term; GC on delete | `class-redirections.php:287`, `:307`, `:326`, `:341` | post/term meta `rank_math_auto_redirect` | no |
| Query-param matching | Full-URI fallback matching for `?`/`&` sources | `class-redirections.php:384` | filter `rank_math/redirection/redirection_match` | no |
| Redirection Categories taxonomy | Private hierarchical `rank_math_redirection_category`, UI + menu fixups, REST-visible | `class-categories.php:71`, `:105`, `:171`, `:426`, `:460` | taxonomy; `edit-tags.php?taxonomy=rank_math_redirection_category` | no |
| Category admin column + cell | Category column + "Uncategorized" fallback | `class-categories.php:203`, `:215` | `rank_math/redirection/admin_columns` | no |
| Category tablenav filter | Category dropdown filter + clear | `class-categories.php:314`, `:358` | GET `redirection_category` | no |
| Bulk "Add to Category" | Bulk-assign checked redirections | `class-categories.php:114`, `:128` | bulk action `bulk_add_redirection_category` | no |
| Save category on create | Persists categories after save | `class-categories.php:288` | `rank_math/redirection/saved` | no |
| Import Redirection-plugin groups | Maps `redirection_groups` → categories | `class-categories.php:379`, `:396` | `rank_math/redirection/after_import` (external table) | no |
| Scheduled activation/deactivation | Action Scheduler one-shot start/end dates; status lock | `class-schedule.php:65`, `:86`, `:96`, `:106`, `:184`, `:233`, `:282`, `:315` | AS hooks `rank_math/redirections/scheduled_activate`/`scheduled_deactivate` (group `rank-math`) | no |
| CSV import/export (redirections) | Import + Export tabs, chunked background importer (100/row), create/update/merge/delete rows | `csv-import-redirections/class-csv-import-export-redirections.php:95`, `:115`, `:154`, `:207`, `:230`, `:352`, `:413`; `class-import-row.php:149`; `class-import-background-process.php:22` | options `rank_math_csv_import_redirections*`; AJAX `csv_import_redirections_progress`; help tab | no |

---

## 3. `modules/robots-txt/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| robots.txt validator UI | Mount point for validator replacing free tester notice | `includes/modules/robots-txt/options.php:11`; `class-robots-txt.php:29`, `:48`, `:39` | settings field `robots_txt_validator` on General → Edit robots.txt | no |

---

## 4. `modules/status/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| PRO version row | Splices PRO version into System Status | `includes/modules/status/class-system-status.php:44` | `rank_math/status/rank_math_info`; option `rank_math_pro_version` | no |
| Status assets + CSV JSON | Loads status/product-analysis JS; injects export/import JSON + nonces | `class-system-status.php:70`, `:86`, `:123`, `:139`, `:155` | `rank-math-status` screen | no |

---

## 5. `modules/bbPress/` — entirely PRO-only (no free counterpart)

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Mark Solved/Unsolved | Moderator link on replies; stores accepted reply | `includes/modules/bbPress/class-bbpress.php:40`, `:74`, `:107` | postmeta `rank_math_bbpress_solved_answer`; AJAX `rank_math_mark_answer_solved` | no |
| QAPage schema | QAPage/Question + acceptedAnswer JSON-LD | `class-bbpress.php:129` | `rank_math/json_ld` | no |
| Frontend assets | bbpress.js + inline CSS | `class-bbpress.php:62`, `:171` | `wp_enqueue_scripts`, `wp_footer` | no |
| Solved-text filter | Override button label | `class-bbpress.php:97` | filter `rank_math/bbpress/solved_text` | no |

---

## 6. `modules/acf/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Include ACF images in sitemap | Toggle to add ACF image fields (gallery/group/repeater/flexible + text/wysiwyg) to XML sitemap image tags | `includes/modules/acf/class-acf.php:37`, `:64`, `:104`, `:126`, `:165`, `:216`, `:244` | option `sitemap.include_acf_images`; filters `rank_math/sitemap/urlimages`, `rank_math/sitemap/content_before_parse_html_images` | no |

---

## 7. `modules/analytics/` — PRO delta over free

Free already provides GSC tables + 11 dashboard REST routes. PRO adds Google Analytics (pageviews), AdSense, Rank Tracker, PageSpeed, win/lose engine, branded email reports, AI-referrer mode.

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Analytics screen assets | PRO CSS/JS on analytics screen | `includes/modules/analytics/class-analytics.php:324` | `rank-math_page_rank-math-analytics` | no |
| Post Analytics admin-bar link | Link to single-post analytics | `class-analytics.php:304` | admin bar `post_analytics` | no |
| Frontend admin-bar stats | Loads stats CSS/JS + JSON on singular | `class-analytics.php:221` | `wp_enqueue_scripts` | no |
| Search Traffic column | Adds traffic/impressions to `rank_math_seo_details` column | `class-analytics.php:751` | admin column | no |
| AdSense connect box | Account select + connected/error UI | `class-analytics.php:122` | `rank_math/analytics/adsense` | yes — AdSense API |
| Country dropdowns | Console + GA country selectors | `class-analytics.php:359` | analytics options | no |
| `sync_global_setting` / `google_updates` | Business-gated RankMath.com sync + Core Updates overlay | `class-analytics.php:461`, `:499`; `class-summary.php:278` | options; RankMath.com | yes — RankMath.com |
| Email report fields | Frequency (`every_15_days`/`weekly`), tracked keywords, branding (logo/header/footer/subject/CSS) | `class-email-reports.php:177`, `:205`, `:434` | options `general.console_email_*` | no |
| Auto-add focus keywords | Auto-import focus keywords into Rank Tracker | `class-keywords.php:792`; `class-rest.php:270` | option `general.auto_add_focus_keywords`; REST `/autoAddFocusKeywords` | yes — quota via RankMath.com |
| GA options | AdSense id, country, anonymize IP, local gtag hosting | `class-analytics.php:405`, `:603`, `:634` | option `rank_math_google_analytic_options` | no |
| Keyword quota cache | Taken/available keywords from RankMath.com | `class-keywords.php:197`; `class-rest.php:346`; `class-db.php:646` | option `rank_math_keyword_quota` | yes — RankMath.com |
| Local gtag.js proxy | Serves cached gtag.js at `?local_ga_js=` | `class-analytics.php:543`, `:558`, `:611` | frontend `template_redirect`; transients | no |
| Pageview merge + AI traffic mode | Merges GA pageviews, filters `referrer!=''`, groups AI referrers (chatgpt/gemini/perplexity/claude/…) | `class-analytics.php:193`; `class-db.php:501`; `class-pageviews.php:63`, `:494` | filters `rank_math/analytics/rows` | no |
| Winning/losing engine | Top-5 winning/losing posts + keywords, position diff cap ±80 | `class-analytics.php:660`; `class-posts.php:688`; `class-keywords.php:649` | transient caches | no |
| Keyword position graphs/badges | Ranking keywords, top-5 badges, per-post graphs | `class-keywords.php:83`; `class-posts.php:88`, `:357` | filters `rank_math/analytics/keywords`, `post_data` | no |
| AdSense/pageviews summaries | Adds adsense/pageviews/clicks summary + graphs | `class-summary.php:32`, `:128`, `:163`, `:252` | filter `rank_math/analytics/summary/...` | partly — AdSense API |
| URL Inspection PRO columns | rich results items, last crawl time, coverage state, raw response | `class-url-inspection.php:28`, `:45`, `:77` | filters `rank_math/analytics/url_inspection_map_properties` | no |
| Link-counter join | Joins internal/external/incoming counts into post rows | `class-links.php:28` | filter `rank_math/analytics/get_posts_rows_by_objects` | no |
| Retention extension | 180 days (PRO) / 1000 (business) vs free 90 | `class-analytics.php:274`, `:452` | filter `rank_math/analytics/max_days_allowed` | partly — plan |
| gtag config | Appends `anonymize_ip` | `class-analytics.php:634` | filter `rank_math/analytics/gtag/gtag_config` | no |
| Site Health permissions info | Appends AdSense/Analytics permission status | `class-analytics.php:618` | filter `rank_math/status/rank_math_info` | no |
| Email report PRO sections | winning/losing posts + keywords sections, detailed button, frequencies | `views/email-reports/report.php:11`; `views/email-reports/sections/*.php`; `class-email-reports.php:503` | email template filters | no |
| Workflow bootstrap | PRO workflow scheduling (`schedule_gap` 10s) | `workflows/class-workflow.php:49`, `:85` | cron hook `rank_math/analytics/workflow` | no |
| GA/AdSense fetch jobs | Fetch + store GA/AdSense, backfill, retention, empty-date tracking | `workflows/class-analytics.php:34`; `workflows/class-adsense.php:34`; `workflows/class-jobs.php:74`, `:98`, `:126`, `:238`, `:298` | cron hooks `rank_math/analytics/get_analytics_data`, `data_fetch`, `get_adsense_data` | yes — Google APIs |
| Summary push | Pushes 30-day summary to RankMath.com | `class-analytics.php:425` | `update_option_rank_math_analytics_last_updated` | yes — RankMath.com |
| AdSense AJAX | Save/test AdSense account | `class-ajax.php:31`, `:53` | AJAX `rank_math_save_adsense_account`, `rank_math_check_adsense_request` | yes — AdSense API |
| PageSpeed REST | On-demand desktop/mobile PageSpeed + SEO score | `class-rest.php:149`, `:426` | REST `/getDesktopPagespeed`, `/getMobilePagespeed`, `/getPageSEOScore` | yes — PageSpeed API |
| Tracker REST | Tracked keyword CRUD + summaries | `class-rest.php:65`, `:75`, `:86`, `:96`, `:106`, `:128`, `:139` | REST `/getTrackedKeywords*`, `/addTrackKeyword`, `/removeTrackKeyword`, `/deleteTrackedKeywords` | yes — RankMath.com quota |
| Posts/overview REST | `/postsOverview`, `/postsRows`, `/inspectionStats`, `/getKeywordPages` | `class-rest.php:44`, `:55`, `:182`, `:192` | REST | no |

---

## 8. `modules/content-ai/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Business-gated editor script | Loads PRO Content AI editor JS only for business plan | `includes/modules/content-ai/class-content-ai.php:30`, `:52` | `rank_math/admin/editor_scripts` | yes — Content AI / Business plan |
| Credit-exhaustion copy | Overrides help/notice text with "purchase more" | `assets/js/content-ai.js` (filter `rank_math_content_ai_help_text` / credits notice) | editor JS filter | yes — Content AI credits |

The full Content AI engine (REST, bulk actions, scheduler, blocks, admin page) lives in the **free** plugin (`free:includes/modules/content-ai/`); PRO only gates/upsells it.

---

## 9. `modules/seo-analysis/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Competitor Analyzer UI | Competitor tab + side-by-side + logo/WP Rocket flags | `includes/modules/seo-analysis/class-seo-analysis-pro.php:40`, `:64` | `rank-math-seo-analysis` screen | yes — Analyzer API |
| Allow external URLs | Analyzes competitor URLs, not just own site | `class-competitor-analysis.php:33`, `:45` | filter `rank_math/analysis/is_allowed_url` | yes — API |
| Competitor API flag | Adds `?ca=1` to analyzer endpoint | `class-competitor-analysis.php:34`, `:56` | filter `rank_math/seo_analysis/api_endpoint` | yes — API |
| Persist competitor result | Stores results/url/date; clears on tool reset | `class-competitor-analysis.php:35`, `:65` | options `rank_math_seo_analysis_competitor_results/url/date` | yes — API |

---

## 10. `modules/image-seo/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Auto image caption | Fills missing captions (classic + Gutenberg figures) | `includes/modules/image-seo/class-image-seo-pro.php:61`, `:322`, `:368`; `options.php:13` | options `general.add_img_caption`, `general.img_caption_format` | no |
| Auto attachment description | Appends description to empty attachment pages | `class-image-seo-pro.php:66`, `:407`; `options.php:39` | options `general.add_img_description`, `img_description_format` | no |
| Avatar ALT | Sets `Avatar of {name}` | `class-image-seo-pro.php:57`, `:258`; `options.php:138` | option `general.add_avatar_alt` | no |
| Find/replace rules | Per-rule find→replace in alt/title/caption (variables allowed) | `class-image-seo-pro.php:70`, `:453`; `options.php:149` | option `general.image_replacements[]` | no |
| Casing enforcement | title/alt/description/caption case conversion (incl. Gutenberg figcaption) | `class-image-seo-pro.php:78`, `:197`, `:339`, `:427`; `options.php:65` | options `general.img_{title,alt,description,caption}_change_case` | no |
| `%imagealt%` / `%imagetitle%` vars | Registers image-specific replacement vars | `class-image-seo-pro.php:89`, `:96` | variable replacements | no |
| Sanitization bypass | Allows whitespace find/replace values | `class-image-seo-pro.php:55`, `:562` | filter `rank_math/settings/sanitize_fields` | no |
| Imagify notice | Display-only cross-sell | `options.php:198` | settings notice | no |

---

## 11. `modules/link-genius/` — PRO-only (no free counterpart; free has basic `links/`)

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Link Genius admin page | React SPA overriding free Links page: overview/posts/links/bulk-update/keyword-maps | `includes/modules/link-genius/Admin/class-admin.php:62`, `:89`, `:98` | `rank-math_page_rank-math-links-page` | no |
| General Settings tab | Adds `link-genius` settings tab | `Admin/class-admin.php:119` | `rank_math/settings/general` | no |
| Keyword Maps settings | case/whole-word/headings/max-links/excluded post types-ids-terms | `Features/KeywordMaps/class-keyword-maps.php:81`, `:125` | options `general.keyword_maps_*` | no |
| Gutenberg sidebar panel | Related/AI link panel in editor + auto-link opt-out | `Admin/class-admin.php:140`, `:169`; `Features/KeywordMaps/class-auto-linker.php:361` | postmeta `rank_math_auto_linking_disabled`, `rank_math_ai_link_suggestions` | partly — AI suggestions |
| Admin bar tree | Link Genius submenus | `class-link-genius.php:163`, `:180` | admin bar | no |
| Links REST | list/details/delete/undo/remove-nofollow/update/bulk-restore | `Api/class-links-controller.php:36`, `:48`, `:66`, `:89`, `:112`, `:130`, `:170` | `rankmath/v1/link-genius/links*` | no |
| Posts/stats/regenerate REST | posts, posts-stats, stats, regenerate | `Api/class-posts-controller.php:173`, `:185`, `:196`, `:207` | `rankmath/v1/link-genius/{posts,posts-stats,stats,regenerate}` | no |
| Free-response overrides | Hijacks free `/links/*` responses | `Api/class-posts-controller.php:40` | filters `rank_math/links/rest_*` | no |
| Audit REST | stats/start/recheck/mark-safe broken-link audit | `Api/class-audit-controller.php:35`, `:46`, `:64`, `:87` | `rankmath/v1/link-genius/audit/*` | no |
| Export REST | Background CSV export | `Api/class-export-controller.php:31` | `rankmath/v1/link-genius/export` | no |
| Editor AI endpoints | link-suggestions, link-opportunities, related-posts | `Api/class-editor-controller.php:33`, `:61`, `:84`, `:114`; `services/class-content-similarity.php:726` | REST; Content AI gate | yes — Content AI |
| Unified operations REST | progress/cancel/clear for all async ops | `Api/class-operations-rest.php:69`, `:92`, `:115` | `rankmath/v1/link-genius/operations/*` | no |
| Bulk-update REST | apply/rollback/changes | `Features/BulkUpdate/class-rest.php:53`, `:95`, `:121` | `rankmath/v1/link-genius/bulk-update/*` | no |
| Keyword-maps REST | CRUD + bulk-toggle/delete + execute + variations (AI) | `Features/KeywordMaps/class-rest.php:76`, `:130`, `:154`, `:178`, `:197`, `:210`, `:252`, `:285` | `rankmath/v1/link-genius/keyword-maps*` | partly — `generate-variations` uses Content AI |
| Keyword-variations AI client | POSTs to Content AI for synonyms; 402 = credits | `Features/KeywordMaps/class-ai-client.php:31`, `:54`, `:132` | `CONTENT_AI_URL/ai/keyword_variations` | yes — Content AI credits |
| Related-posts shortcode | Renders related cards with layouts/attrs | `Shortcodes/class-related-posts-shortcode.php:34`, `:49` | shortcode `[rank_math_related_posts]` | partly — AI list |
| Related-posts block | SSR `rank-math/related-posts` | `blocks/related/class-block-related-posts.php:46`, `:63` | block `rank-math/related-posts` | partly |
| Cron jobs | daily export GC; weekly orphan GC; start-crawler; dispatch-crawler; queue pending links; keyword auto-link | `class-link-genius.php:44`, `:47`, `:50`, `:53`, `:55`, `:61`, `:66`; `Features/KeywordMaps/class-auto-linker.php:92` | cron hooks `rank_math_cleanup_exports`, `rank_math_cleanup_orphaned_link_status`, `rank_math_link_genius_start_crawler`, `rank_math_link_genius_dispatch_crawler`, `rank_math_link_genius_queue_pending_links`, `rank_math_keyword_maps_auto_link` | no |
| Background workers | regenerate, audit crawler, bulk modifier, export, bulk-update preview/apply, keyword-map preview/apply | `Background/class-regenerate-links.php:267`; `Background/class-link-status-crawler.php:237`; `Background/class-bulk-link-modifier.php:181`; `Background/class-export-processor.php:29`; `Features/BulkUpdate/class-processor.php:41`; `Features/BulkUpdate/class-preview-processor.php:32`; `Features/KeywordMaps/class-keyword-map-processor.php:34` | background actions `link_genius_*` | no |
| DB tables/columns | audit/history/snapshots/maps/map_variations tables + PRO columns on free `rank_math_internal_links` | `Data/class-table-extension.php:48`, `:89`, `:121`, `:135`, `:253`, `:296`, `:304`, `:336` | tables listed in §"Database tables" | no |
| Query filters | is_nofollow / target_blank / is_broken filtering | `Data/class-query-builder.php:546`, `:555`, `:603` | REST args | no |
| Robots check before audit | Skips disallowed URLs | `Services/class-robots-checker.php` | internal | no |
| Related-posts service + meta | Computes related posts | `Services/class-related-posts.php`; `Api/class-editor-controller.php:238` | postmeta `rank_math_related_posts` | partly |

---

## 12. `modules/local-seo/` — PRO delta over free (multi-location system)

Free ships only single-location Organization/Person + KML plumbing.

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Titles & Meta → Local tab | Local business settings view | `includes/modules/local-seo/class-admin.php:32`, `:44` | `rank_math/settings/title` | no |
| Sitemap → Local KML tab | KML toggle + live URL | `class-admin.php:57`, `:64`; `views/sitemap-settings.php:15` | option `sitemap.local_sitemap` | no |
| LocalBusiness-only schema | Locks Location schema picker to LocalBusiness | `class-admin.php:81` | filter `rank_math/settings/snippet/types` | no |
| Local option keys | knowledgegraph_type, use_multiple_locations, local_address, opening_hours, map_unit/style, limit_results, primary_country, route_label, enable_location_detection, same_organization_locations, locations_enhanced_search, maps_api_key, geo, CPT bases/labels, about/contact pages | `views/titles-options.php:272`, `:345`, `:379`, `:415`, `:456`, `:595`, `:610`, `:627`, `:645`, `:657`, `:668`, `:683`, `:698`, `:713`, `:725`, `:735`, `:746`, `:757`, `:768`, `:784`, `:800` | options `titles.*` | maps key yes |
| Locations CPT | Public `rank_math_locations` CPT (REST, custom slug/labels) | `class-local-seo.php:83`, `:139`, `:189`, `:208` | post type `rank_math_locations` | no |
| Location Categories taxonomy | Hierarchical `rank_math_location_category` | `class-local-seo.php:214`, `:248` | taxonomy | no |
| Force Gutenberg | Disables Classic Editor for CPT | `class-local-seo.php:103` | filter `classic_editor_enabled_editors_for_post_type` | no |
| Address/Phone columns | Admin columns from Schema | `class-local-seo.php:51`, `:271`, `:286` | columns `address`, `telephone` | no |
| Bulk-action prune | Removes schema bulk actions for Locations | `class-local-seo.php:315` | `bulk_actions-edit-rank_math_locations` | no |
| Geo meta persistence | Stores lat/lng for radius queries | `class-local-seo.php:65`, `:75` | postmeta `rank_math_local_business_latitide` (sic), `_longitude` | no |
| Sitemap cache busting | Clears locations sitemap on save | `class-local-seo.php:256`; `class-kml-file.php:40` | `save_post*` | no |
| Place entity / publisher fix | Lifts address/geo/hours into `place`, strips publisher, 24/7 | `class-frontend.php:73`, `:102`, `:150`, `:174` | `rank_math/json_ld` | no |
| Geo meta tags | `geo.placename/position/region` | `class-frontend.php:40`, `:61` | frontend head | no |
| Enhanced search | Location address in search excerpts | `class-search.php:36`, `:49` | filter `the_excerpt` | no |
| Locations KML | Builds `locations.kml` from Location schemas | `class-kml-file.php:31`, `:56` | filter `rank_math/sitemap/locations/data` | no |
| `[rank_math_local]` shortcode | Dispatcher address/map/opening-hours/store-locator/all-locations | `class-location-shortcode.php:87`, `:129`, `:139` | shortcode `rank_math_local` | map/locator yes — Google Maps |
| Yoast shims | `[wpseo_all_locations]`, `[wpseo_storelocator]`, `[wpseo_opening_hours]`, `[wpseo_map]` | `class-location-shortcode.php:82`, `:83`, `:84`, `:85` | shortcodes | map yes |
| Address renderer | Formats address | `shortcodes/class-address.php:34`, `:111` | shortcode | no |
| Opening-hours renderer | Hours + "Open now" | `shortcodes/class-opening-hours.php:29`, `:92` | shortcode | no |
| Map renderer | Google Map embed | `shortcodes/class-map.php:36` | shortcode | yes — Maps key |
| Store locator | Search form + radius SQL + geolocation | `shortcodes/class-store-locator.php:31`, `:50`, `:155` | shortcode | yes — Maps JS + billing key |
| Local Business block | SSR `rank-math/local-business` (4 types) | `class-block-local-business.php:66`, `:81` | block `rank-math/local-business` | map/locator yes |
| Maps asset enqueue | Loads Maps API + markerclusterer when key set | `class-location-shortcode.php:79`, `:100` | scripts `rank-math-google-maps*` | yes — Maps key |

---

## 13. `modules/schema/` — PRO delta over free

No `schemas/` subfolder; layout is `schema/*.php` + `schema/video/*` + `schema/shortcode/*`.

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Schema Templates CPT | Reusable templates `rank_math_schema` (REST enabled, admin menu) | `includes/modules/schema/class-post-type.php:57`, `:105`, `:114` | post type; Rank Math > Schema Templates | no |
| Display-conditions engine | Include/exclude/insert rules for templates | `class-display-conditions.php:100`, `:314` | meta `displayConditions`; frontend JSON-LD | no |
| subjectOf nesting | Nests secondary FAQ/HowTo under primary entity | `class-frontend.php:174` | `rank_math/json_ld` | no |
| ItemList auto-grouping | 2+ Course/Movie/Recipe/Restaurant → ItemList; archive ItemList rewrite | `class-frontend.php:247`, `:425` | frontend JSON-LD | no |
| Dataset/LocalBusiness validator | Strips invalid props; Dataset publisher→creator | `class-frontend.php:457` | frontend JSON-LD | no |
| Product manufacturer @id | References site organization/person | `class-frontend.php:549` | Product JSON-LD | no |
| Remove empty offers | Drops offers when price missing | `class-frontend.php:567` | Product JSON-LD | no |
| isFamilyFriendly normalize | Forces string `True` | `class-frontend.php:622` | Video/Podcast JSON-LD | no |
| about/mentions links | Link dialog checkboxes → WebPage about/mentions | `class-frontend.php:373`; `class-admin.php:124` | editor link modal | no |
| Schema preview endpoint | `?schema-preview` JSON preview | `class-frontend.php:66`, `:74`, `:81` | rewrite `schema-preview` | no |
| Taxonomy schema | Term metabox + term schema + CollectionPage | `class-taxonomy.php:31`, `:112`, `:265` | term meta; options `titles.tax_*` | no |
| Import JSON-LD from URL | Fetch URL → extract ld+json for templates | `class-parser.php:29`, `:55`; `class-ajax.php:36` | AJAX `fetch_from_url` | no |
| REST saveTemplate | Create/update template | `class-rest.php:42`, `:103` | REST `rankmath/v1/saveTemplate` | no |
| REST getVideoData | Parse pasted video URL | `class-rest.php:53`, `:71` | REST `rankmath/v1/getVideoData` | no |
| AJAX conditions data | Autocomplete for condition builder | `class-ajax.php:58` | AJAX `get_conditions_data` | no |
| Video autodetect | Parse saved content → VideoObject(s) | `class-video.php:164`; `video/class-parser.php:60` | meta `rank_math_schema_VideoObject` | no |
| Per-post-type video toggles | Autodetect Video (on) / Autogenerate Image (off) | `class-video.php:63`, `:75`, `:88` | options `titles.pt_{type}_autodetect_video`, `_autogenerate_image` | no |
| Video providers | YouTube/Vimeo/DailyMotion/VideoPress/TED/self-hosted | `video/class-youtube.php:29`; `class-vimeo.php:30`; `class-dailymotion.php:28`; `class-videopress.php:28`; `class-tedvideos.php:29`; `class-wordpress.php:37` | frontend VideoObject | no (YT key optional free) |
| YouTube API mode | Uses `sitemap.youtube_api_key` or scrapes | `video/class-youtube.php:39`, `:100` | option `sitemap.youtube_api_key` | no |
| Custom-field/Elementor video scan | Scans custom fields + Elementor data | `video/class-parser.php:500`, `:548` | option `sitemap.video_sitemap_custom_fields` | no |
| Video thumbnail sideload | Downloads remote thumbnails | `video/class-parser.php:327` | Media Library | no |
| Bulk Generate Video Schema | Background backfill tool | `class-video.php:184`, `:211`; `class-video-schema-generator.php:60`, `:29` | Database Tools `generate_video_schema`; option `rank_math_video_posts`; queue `rank_math_add_video_schema` | no |
| Media RSS (MRSS) | Adds media:* tags to RSS items with VideoObject | `class-media-rss.php:55`, `:30`, `:108` | option `general.disable_media_rss`; `rss2_item` | no |
| Yandex OpenGraph | ya:ovs:* video tags | `class-video.php:135` | `rank_math/opengraph/facebook` | no |
| PRO schema shortcode views | PodcastEpisode/Dataset/ClaimReview/Movie templates; enhanced FAQ/HowTo/JobPosting/Product/Recipe views | `class-snippet-pro-shortcode.php:82`, `:78`, `:101`; `shortcode/*.php` | shortcode `rank_math_rich_snippet` | no |
| Shortcode `fields` attr | Whitelist output fields | `class-snippet-pro-shortcode.php:43`, `:64` | shortcode attr | no |
| Pros/Cons renderer | positiveNotes/negativeNotes lists | `class-snippet-pro-shortcode.php:101` | shortcode HTML | no |
| HowTo block PRO extension | estimatedCost/supply/tools/material | `class-schema.php:135`, `:99`, `:83` | block `rank-math/howto-block` | no |
| Rich-snippet block `id` attr | Embed template by ID | `class-schema.php:116`; `class-frontend.php:504` | block/shortcode | no |

**PRO-only schema types:** `DataSet`, `FactCheck` (ClaimReview), `Movie`, `PodcastEpisode`. Free dropdown has 13 (`free:includes/helpers/class-choices.php:436`).

---

## 14. `modules/news-sitemap/` — PRO-only

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| News sitemap provider | `news-sitemap.xml`, last 48h, max 1000 | `includes/modules/news-sitemap/class-news-provider.php:149`, `:62`, `:95`, `:172` | sitemap slug `news` | no |
| News XML + XSL | news:news tags + stylesheet | `class-news-sitemap.php:107`, `:131`, `:142` | XML | no |
| Publication name/language | news:name + locale | `class-news-sitemap.php:250`, `:259`; `settings-news.php:19` | option `sitemap.news_sitemap_publication_name` | no |
| Post-type + exclude terms | News post types + excluded terms | `settings-news.php:31`, `:71`; `class-admin.php:174` | options `sitemap.news_sitemap_post_type`, `_exclude_{type}_terms` | no |
| News robots override | Googlebot-News noindex meta + metabox | `class-news-sitemap.php:55`, `:83`; `class-admin.php:126` | meta `rank_math_news_sitemap_robots`; metabox | no |
| Default NewsArticle | Forces Article default type for news post types | `class-news-sitemap.php:195` | filter `rank_math/schema/default_type` | no |
| Copyright enrichment | copyrightYear/Holder | `class-news-sitemap.php:224` | Article JSON-LD | no |
| Cache invalidation | Invalidates news sitemap on save | `class-admin.php:140`, `:159` | hooks | no |
| REST getTerms | Term autocomplete | `class-rest.php:31`, `:76` | REST `rankmath/v1/sitemap/getTerms` | no |

---

## 15. `modules/video-sitemap/` — PRO-only

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Video sitemap provider | `video-sitemap*.xml` for posts with VideoObject meta | `includes/modules/video-sitemap/class-video-provider.php:55`, `:91`, `:116`, `:214`, `:240` | sitemap slug `video` | no |
| Video URL tags | video:title/player_loc/thumbnail_loc/etc. | `class-video-sitemap.php:128`, `:160` | XML | no |
| Post-type selector | Which post types scanned | `settings-video.php:29`; `class-video-sitemap.php:59` | option `sitemap.video_sitemap_post_type` | no |
| Hide from humans | Serve only to bots when enabled | `class-video-sitemap.php:201`; `settings-video.php:15` | option `sitemap.hide_video_sitemap` | no |
| YouTube API key setting | Stored for schema parser | `settings-video.php:40` | option `sitemap.youtube_api_key` | no |
| Custom fields scan list | Extra meta keys scanned | `settings-video.php:57` | option `sitemap.video_sitemap_custom_fields` | no |
| Cache invalidation | Invalidates video sitemap on save | `class-video-metabox.php:38`; `class-video-sitemap.php:216` | hooks | no |

---

## 16. `modules/podcast/` — PRO-only

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Podcast settings tab | Channel title/desc/owner/category/image/tracking/explicit/copyright | `includes/modules/podcast/views/options.php:13`; `class-podcast.php:93` | options `general.podcast_*` | no |
| Dedicated `feed/podcast` | Feed of posts with PodcastEpisode schema | `class-podcast.php:57`, `:119`, `:126`; `views/feed-rss2.php:86` | feed slug `podcast`; `add_feed` | no |
| iTunes RSS namespace | Channel-level itunes tags | `class-podcast-rss.php:43`, `:88`, `:30` | `rss2_ns`/`rss2_head` | no |
| Per-episode RSS tags | itunes episode tags + enclosure from meta | `class-podcast-rss.php:142`, `:149`, `:189` | `rss2_item` | no |
| WebSub/PubSubHubbub ping | Pings 3 hubs on publish | `class-publish-podcast.php:58`, `:103`, `:73` | hooks `rank_math/schema/update` | no |
| `%podcast_image%` var | Channel image replacement | `class-podcast.php:65` | variable; option `general.podcast_image` | no |

---

## 17. `modules/woocommerce/` — PRO delta over free

| Feature | Behaviour | PRO file:line | Surface | Paid? |
|---|---|---|---|---|
| Brand taxonomy selector | Choose brand taxonomy/string for schema + OG | `includes/modules/woocommerce/class-admin.php:49`; `class-woocommerce-pro.php:253` | options `general.product_brand`, `custom_product_brand` | no |
| Global identifier selector | GTIN/ISBN/MPN selection (default gtin8) | `class-admin.php:73`; `class-woocommerce-pro.php:289`, `:290`, `:562` | option `general.gtin` | no |
| Frontend GTIN display | Product page GTIN + variation JS swap | `class-woocommerce-pro.php:323`, `:346`, `:360` | options `general.show_gtin`, `gtin_label` | no |
| ProductGroup for variable products | Variable products → ProductGroup with hasVariant | `class-woocommerce-pro.php:404`, `:520`, `:593` | frontend JSON-LD | no |
| ProductsCarousel on archives | Archive ItemList carousel | `class-woocommerce-pro.php:156`, `:202` | frontend JSON-LD | no |
| Noindex hidden products + sitemap prune | Noindexes hidden catalog products, prunes sitemap | `class-woocommerce-pro.php:219`, `:85`, `:101`; `class-admin.php:132`, `:162` | option `general.noindex_hidden_products` | no |
| Additional Product props | manufacturer @id/url/brand.url (opt-in) | `class-woocommerce-pro.php:265` | filter `rank_math/schema/woocommerce/additional_properties` | no |
| OG retailer item id | `product:retailer_item_id` = SKU | `class-woocommerce-pro.php:451` | `rank_math/opengraph/facebook` | no |
| GTIN editor fields + migration | `_rank_math_gtin_code` inputs + background migrate to native | `class-admin.php:178`, `:204`, `:228`; `class-woocommerce-pro.php:467`, `:489`; `class-migrate-gtin.php:75`, `:100`, `:26` | meta `_rank_math_gtin_code`; tool `migrate_gtin_values`; option `rank_math_gtin_migrated` | no |

---

# PRO-only features with no free counterpart

These have **no free module** at all (free module list: 404-monitor, acf, ai-visibility, analytics, buddypress, content-ai, database-tools, image-seo, instant-indexing, links, llms, local-seo, redirections, robots-txt, role-manager, schema, seo-analysis, sitemap, status, version-control, web-stories, woocommerce):

1. **bbPress module** — solved-answer button + QAPage schema (`modules/bbPress/*`).
2. **News Sitemap module** — entire module (`modules/news-sitemap/*`).
3. **Video Sitemap module** — entire module (`modules/video-sitemap/*`).
4. **Podcast module** — entire module incl. `feed/podcast` + WebSub (`modules/podcast/*`).
5. **Link Genius module** — entire module: SPA, broken-link audit, bulk-update, Keyword Maps, related posts, CSV export, 5 PRO tables (`modules/link-genius/*`). Free `modules/links/` is only the basic link counter/report.
6. **Schema Templates CPT + display conditions** — `modules/schema/class-post-type.php`, `class-display-conditions.php`.
7. **PRO-only schema types** — DataSet, FactCheck/ClaimReview, Movie, PodcastEpisode (`class-admin.php:109-111`; `shortcode/*`).
8. **Video schema autodetect + 6 providers** — `modules/schema/class-video.php`, `video/*`.
9. **Media RSS (MRSS) + Yandex video OG** — `class-media-rss.php`, `class-video.php:135`.
10. **Schema preview endpoint + import JSON-LD from URL** — `class-frontend.php:66`, `class-parser.php:29`.
11. **Term (taxonomy) schema + CollectionPage** — `class-taxonomy.php`.
12. **Taxonomy SEO quick-edit + SEO Details column** — `includes/admin/class-quick-edit.php:61`.
13. **Bulk SEO actions** (robots/schema/canonical/redirect/intent) — `includes/admin/class-bulk-actions.php`.
14. **Post-list SEO filters** incl. orphan + schema-type — `class-post-filters.php`.
15. **Media Library SEO filters** (missing alt/title/caption) — `class-media-filters.php`.
16. **CSV metadata import/export** (posts/terms/users + redirections) — `includes/admin/csv-import-export/*`, `modules/redirections/csv-import-redirections/*`.
17. **Redirection Categories taxonomy + scheduled redirections + htaccess sync** — `modules/redirections/class-categories.php`, `class-schedule.php`, `class-redirections.php`.
18. **404 date-range CSV export + aggregated hits** — `modules/404-monitor/class-monitor-pro.php`.
19. **Thumbnail Overlays / custom watermarks** — `includes/class-thumbnail-overlays.php`.
20. **Google Trends tool** — `class-trends-tool.php`.
21. **Search Intent (Content AI)** — `class-common.php:220`, REST `/searchIntent`.
22. **Multi-location Local SEO** (Locations CPT, store locator, maps shortcodes/block, KML tab) — `modules/local-seo/*` (free only has single-location).
23. **Rank Tracker / GA / AdSense / PageSpeed / AI-referrer analytics** — `modules/analytics/*` (free only has GSC dashboard).
24. **Email report branding + winning/losing sections + 15-day/weekly frequencies** — `modules/analytics/class-email-reports.php`.
25. **SEO Analyst Competitor tab** — `modules/seo-analysis/class-competitor-analysis.php`.
26. **Analytics admin-bar links + search-traffic column** — `class-analytics.php:221`, `:304`, `:751`.
27. **Image SEO PRO extras** (caption, description, avatar alt, casing, find/replace, `%imagealt%`) — `modules/image-seo/*`.
28. **Setup wizard PRO steps + settings import/export** — `includes/class-setup-wizard.php`.
29. **Random-word replacement variables** — `includes/class-register-vars.php`.
30. **Affiliate-link prefix handling** — `class-links.php`, `class-common.php`.
31. **Elementor breadcrumbs widget + FAQ accordion schema** — `includes/3rdparty/elementor/*`.
32. **Divi FAQ accordion schema + builder tab** — `includes/3rdparty/divi/class-divi.php`.
33. **Imagify plugin-family cross-sell** — `includes/3rdparty/plugin-family/class-plugin-family.php`.

---

# Features requiring external paid services

| Feature | Service | PRO file:line |
|---|---|---|
| Content AI editor features / credit upsell | Rank Math Content AI credits (Business plan) | `modules/content-ai/class-content-ai.php:30`; `assets/js/content-ai.js` |
| Search Intent keyword analysis | Rank Math Content AI (`CONTENT_AI_URL/ai/keyword_intent`) | `includes/admin/class-admin-helper.php:626`; `includes/class-rest.php:167`; bulk intent at `includes/admin/class-bulk-actions.php:202` |
| Link Genius AI link suggestions / opportunities | Rank Math Content AI | `modules/link-genius/Api/class-editor-controller.php:33`, `:61` |
| Link Genius AI keyword variations | Rank Math Content AI (`/ai/keyword_variations`), 402 = out of credits | `modules/link-genius/Features/KeywordMaps/class-ai-client.php:31`, `:132` |
| Link Genius AI related-post similarity | Rank Math Content AI (`/ai/*`) | `modules/link-genius/services/class-content-similarity.php:726` |
| Rank Tracker keywords + quota | RankMath.com account quota | `modules/analytics/class-keywords.php:197`; `class-rest.php:346`; option `rank_math_keyword_quota` |
| Analytics summary / settings sync / Google Updates feed | RankMath.com (`send_summary`, `sync_setting`, `/versionCheck`) | `modules/analytics/class-analytics.php:425`, `:507`; `class-summary.php:278`; `includes/admin/class-api.php:341` |
| AdSense connect + data | Google AdSense API (free API, needs OAuth/account) | `modules/analytics/class-analytics.php:122`; `class-ajax.php:53`; `google/class-adsense.php:37` |
| PageSpeed scores | Google PageSpeed Insights API | `modules/analytics/class-rest.php:426`; `google/class-pagespeed.php:32` |
| SEO Analyst competitor analysis | RankMath.com SEO Analyzer API (`?ca=1`) | `modules/seo-analysis/class-competitor-analysis.php:56` |
| Plugin updates + beta channel + license | RankMath.com (`versionCheck`, `updateCheck2`, `pluginInfo`, `enrollSite`) | `includes/plugin-update/class-plugin-update.php:739`, `:794`, `:851`; `includes/admin/class-licence-activation.php:92` |
| Local SEO map + store locator | Google Maps JS/Embed API key (billing-enabled Cloud project; user's own key) | `modules/local-seo/class-location-shortcode.php:79`, `:100` |
| Google Analytics (GA4) pageviews + AI-referrer mode | Google Analytics Data API (free API, OAuth) | `modules/analytics/workflows/class-jobs.php:126`; `class-analytics.php:558` |
| Email reports delivery | WordPress cron + RankMath.com report data | `modules/analytics/class-email-reports.php:177` |

(Google Analytics/Search Console/PageSpeed/AdSense are free Google APIs that require OAuth credentials, not paid Rank Math services; they are marked above for completeness. The hard-paywalled Rank Math services are: **Content AI credits**, **Rank Tracker quota**, **RankMath.com sync/update/license**, and the **SEO Analyst competitor API**.)

---

# Database tables and options added by PRO

## Tables (created/owned by PRO)

| Table | Purpose | Citation |
|---|---|---|
| `{prefix}rank_math_analytics_ga` | GA4 pageviews incl. referrer (AI traffic) | `includes/modules/analytics/workflows/class-analytics.php:52`; `includes/updates/update-3.0.97.php:25` |
| `{prefix}rank_math_analytics_adsense` | Daily AdSense earnings | `includes/modules/analytics/workflows/class-adsense.php:52`; `includes/class-installer.php:91` |
| `{prefix}rank_math_analytics_keyword_manager` | Rank Tracker keyword list | `includes/modules/analytics/workflows/class-keywords.php:40`; `includes/class-installer.php:92` |
| `{prefix}rank_math_link_genius_audit` | Broken-link HTTP status per URL | `includes/modules/link-genius/Data/class-table-extension.php:121` |
| `{prefix}rank_math_link_genius_history` | Bulk/keyword-map operation history | `class-table-extension.php:253` |
| `{prefix}rank_math_link_genius_snapshots` | Content snapshots for rollback/undo | `class-table-extension.php:296` |
| `{prefix}rank_math_link_genius_maps` | Keyword map headers | `class-table-extension.php:304` |
| `{prefix}rank_math_link_genius_map_variations` | Keyword map synonym rows | `class-table-extension.php:336` |

## Columns added to free tables

| Table | Columns added | Citation |
|---|---|---|
| `{prefix}rank_math_internal_links` | `anchor_text`, `anchor_type`, `is_nofollow`, `target_blank`, `url_hash`, `created_at` (+ indexes) | `class-table-extension.php:48`, `:89` |

## Option / meta keys added by PRO (non-exhaustive of module views)

| Key | Citation |
|---|---|
| `rank_math_pro_version` | `includes/class-updates.php:58`; `modules/status/class-system-status.php:44` |
| `rank_math_reseller_data` | `includes/admin/class-licence-activation.php:62` |
| `rank_math_keyword_quota` | `includes/class-rest.php:206`; `includes/admin/class-api.php:291`; `modules/analytics/class-keywords.php:197` |
| `rank_math_search_intent` | `includes/admin/class-admin-helper.php:673` |
| `rank_math_csv_import`, `_total`, `_status`, `_settings` | `includes/admin/csv-import-export/class-csv-import-export.php:302`, `:315` |
| `rank_math_csv_import_redirections`, `_total`, `_status`, `_settings` | `modules/redirections/csv-import-redirections/class-csv-import-export-redirections.php` |
| `rank_math_pro_updates`, `rank_math_pro_versions`, `rank_math_pro_info_{locale}`, `rank_math_pro_prime_throttle` | `includes/plugin-update/class-plugin-update.php:761`, `:814`, `:874`, `:338` |
| `rank_math_pro_google_updates` | `includes/plugin-update/class-plugin-update.php:762` |
| `rank_math_google_analytic_options` | `modules/analytics/class-analytics.php:405` |
| `rank_math_analytics_pro_installed`, `rank_math_analytics_opt_in_for_ai`, `rank_math_analytics_empty_dates` | `modules/analytics/workflows/class-keywords.php:28`; `class-jobs.php:104`, `:138` |
| `rank_math_seo_analysis_competitor_results` / `_url` / `_date` | `modules/seo-analysis/class-competitor-analysis.php:35` |
| `rank_math_video_posts`, `rank_math_gtin_products`, `rank_math_gtin_migrated` | `modules/schema/class-video-schema-generator.php:76`; `modules/woocommerce/class-migrate-gtin.php:101`, `:144` |
| `rank_math_update_notifications_sent` | `includes/plugin-update/class-plugin-update.php:1047` |
| `imagifyp_id` | `includes/3rdparty/plugin-family/class-plugin-family.php:70` |
| `general.determine_search_intent` | `includes/admin/class-admin.php:120` |
| `general.affiliate_link_prefixes` | `includes/admin/class-links.php:78` |
| `general.console_email_*` (frequency, send_to, tracked_keywords, subject, logo, header/footer, custom_css) | `modules/analytics/class-email-reports.php:177`, `:205` |
| `general.auto_add_focus_keywords` | `includes/class-installer.php:176`; `modules/analytics/class-keywords.php:792` |
| `general.sync_global_setting`, `general.google_updates` | `includes/class-rest.php:210`; `modules/analytics/class-analytics.php:461` |
| `general.noindex_hidden_products` | `includes/class-installer.php:184`; `modules/woocommerce/class-woocommerce-pro.php:61` |
| `general.podcast_*` (title/description/owner/owner_email/category/image/tracking_prefix/explicit/copyright_text) | `includes/class-installer.php:187`; `modules/podcast/views/options.php:13` |
| `general.product_brand`, `general.custom_product_brand`, `general.gtin`, `general.show_gtin`, `general.gtin_label` | `modules/woocommerce/class-admin.php:49`, `:73`; `class-woocommerce-pro.php:143`, `:717` |
| `general.add_img_caption`, `img_caption_format`, `add_img_description`, `img_description_format`, `add_avatar_alt`, `image_replacements`, `img_{title,alt,description,caption}_change_case` | `modules/image-seo/options.php:13`, `:39`, `:65`, `:138`, `:149` |
| `general.keyword_maps_*` (case_sensitive, whole_word_only, exclude_headings, max_links_per_post, excluded_post_types, excluded_post_ids, exclude_{type}_terms) | `modules/link-genius/Features/KeywordMaps/class-keyword-maps.php:81`, `:125` |
| `general.disable_media_rss` | `modules/schema/class-media-rss.php:30` |
| `titles.custom_image_overlays`, `default_image_overlay`, `pt_{type}_image_overlay`, `tax_{tax}_image_overlay` | `includes/class-thumbnail-overlays.php:393`, `:442` |
| `titles.pt_{type}_autodetect_video`, `titles.pt_{type}_autogenerate_image` | `modules/schema/class-video.php:75`, `:88` |
| `titles.use_multiple_locations`, `local_address`, `opening_hours`, `map_unit`, `map_style`, `limit_results`, `primary_country`, `route_label`, `enable_location_detection`, `same_organization_locations`, `locations_enhanced_search`, `maps_api_key`, `geo`, `locations_post_type_base`, `locations_category_base`, `local_seo_about_page`, `local_seo_contact_page` | `modules/local-seo/views/titles-options.php:272`-`:800` |
| `sitemap.news_sitemap_publication_name`, `sitemap.news_sitemap_post_type`, `sitemap.news_sitemap_exclude_{type}_terms` | `modules/news-sitemap/settings-news.php:19`, `:31`, `:71` |
| `sitemap.video_sitemap_post_type`, `sitemap.hide_video_sitemap`, `sitemap.youtube_api_key`, `sitemap.video_sitemap_custom_fields` | `modules/video-sitemap/settings-video.php:29`, `:15`, `:40`, `:57` |
| `sitemap.include_acf_images` | `modules/acf/class-acf.php:37` |
| `sitemap.local_sitemap` | `modules/local-seo/views/sitemap-settings.php:15` |

## Meta keys added by PRO

| Meta key | Citation |
|---|---|
| `rank_math_auto_redirect` (post/term) | `modules/redirections/class-redirections.php:287`, `:307` |
| `rank_math_primary_{taxonomy}` | `includes/admin/class-admin-helper.php:38` |
| `rank_math_bbpress_solved_answer` | `modules/bbPress/class-bbpress.php:40` |
| `rank_math_schema_VideoObject` | `modules/schema/class-video.php:164` |
| `rank_math_related_posts`, `rank_math_ai_link_suggestions`, `rank_math_auto_linking_disabled` | `modules/link-genius/Services/class-related-posts.php`; `Api/class-editor-controller.php:190`, `:238`; `Features/KeywordMaps/class-auto-linker.php:361` |
| `_rank_math_gtin_code` | `modules/woocommerce/class-admin.php:186` |
| `rank_math_news_sitemap_robots` | `modules/news-sitemap/class-news-sitemap.php:84` |
| `rank_math_local_business_latitide` / `_longitude` | `modules/local-seo/class-local-seo.php:75` |
| `rank_math_schema_{@type}` (templates + terms) | `modules/schema/class-rest.php:103`; `class-taxonomy.php:112` |

---

# REST routes added by PRO

Namespace constant: `rank-math/v1` (`RankMath\Rest\Rest_Helper::BASE`). Link Genius namespace is `rank-math/v1/link-genius`; analytics is `rank-math/v1/an`; sitemap is `rank-math/v1`.

## Core (`RankMathPro\Rest\Rest`)
| Route | Method | Citation |
|---|---|---|
| `/pingSettings` | GET | `includes/class-rest.php:42` |
| `/searchIntent` | POST | `includes/class-rest.php:53` |
| `/importCSV` | POST | `includes/class-rest.php:64` |
| `/cancelCsvImport` | POST | `includes/class-rest.php:74` |

## Schema (`RankMathPro\Schema\Rest`)
| Route | Method | Citation |
|---|---|---|
| `/saveTemplate` | POST | `modules/schema/class-rest.php:42` |
| `/getVideoData` | POST | `modules/schema/class-rest.php:53` |

## Analytics (`rank-math/v1/an`, `RankMathPro\Analytics\Rest`)
`/getKeywordPages`, `/postsOverview`, `/getTrackedKeywords`, `/getTrackedKeywordsRows`, `/getTrackedKeywordSummary`, `/trackedKeywordsOverview`, `/addTrackKeyword`, `/autoAddFocusKeywords`, `/removeTrackKeyword`, `/deleteTrackedKeywords`, `/getDesktopPagespeed`, `/getMobilePagespeed`, `/getPageSEOScore`, `/postsRows`, `/inspectionStats` — `modules/analytics/class-rest.php:44`, `:55`, `:65`, `:75`, `:86`, `:96`, `:106`, `:117`, `:128`, `:139`, `:149`, `:160`, `:171`, `:182`, `:192`.

## News Sitemap
| Route | Method | Citation |
|---|---|---|
| `/sitemap/getTerms` | GET | `modules/news-sitemap/class-rest.php:31` |

## Link Genius (`rank-math/v1/link-genius`)
| Route | Method | Citation |
|---|---|---|
| `/links` | GET | `modules/link-genius/Api/class-links-controller.php:36` |
| `/links/{id}/details` | GET | `class-links-controller.php:48` |
| `/delete` | POST | `class-links-controller.php:66` |
| `/undo-delete` | POST | `class-links-controller.php:89` |
| `/remove-nofollow` | POST | `class-links-controller.php:112` |
| `/update` | POST | `class-links-controller.php:130` |
| `/bulk-actions/restore` | POST | `class-links-controller.php:170` |
| `/posts` | GET | `Api/class-posts-controller.php:173` |
| `/posts-stats` | GET | `class-posts-controller.php:185` |
| `/stats` | GET | `class-posts-controller.php:196` |
| `/regenerate` | POST | `class-posts-controller.php:207` |
| `/audit/stats` | GET | `Api/class-audit-controller.php:35` |
| `/audit/start` | POST | `class-audit-controller.php:46` |
| `/audit/recheck` | POST | `class-audit-controller.php:64` |
| `/audit/mark-safe` | POST | `class-audit-controller.php:87` |
| `/export` | POST | `Api/class-export-controller.php:31` |
| `/link-suggestions` | POST | `Api/class-editor-controller.php:33` |
| `/link-opportunities` | POST | `class-editor-controller.php:61` |
| `/related-posts` | POST | `class-editor-controller.php:84` |
| `/operations/progress`, `/cancel`, `/clear` | GET/POST | `Api/class-operations-rest.php:69`, `:92`, `:115` |
| `/bulk-update/apply`, `/rollback`, `/changes/{batch_id}` | POST/GET | `Features/BulkUpdate/class-rest.php:53`, `:95`, `:121` |
| `/keyword-maps`, `/keyword-maps/{id}` | GET/POST/PUT/DELETE | `Features/KeywordMaps/class-rest.php:76`, `:130` |
| `/keyword-maps/bulk-toggle`, `/bulk-delete`, `/{id}/execute`, `/{id}/variations`, `/generate-variations`, `/accept-variations` | POST | `class-rest.php:154`, `:178`, `:197`, `:210`, `:252`, `:285` |

## AJAX endpoints (not REST, listed for completeness)
`wp_ajax_rank_math_mark_answer_solved` (`modules/bbPress/class-bbpress.php:42`), `wp_ajax_fetch_from_url` + `get_conditions_data` (`modules/schema/class-ajax.php:36`, `:58`), `wp_ajax_rank_math_save_adsense_account` / `check_adsense_request` (`modules/analytics/class-ajax.php:31`), `wp_ajax_csv_import_progress` (`includes/admin/csv-import-export/class-csv-import-export.php:44`), `wp_ajax_csv_import_redirections_progress` (`modules/redirections/csv-import-redirections/class-csv-import-export-redirections.php:413`), `wp_ajax_rank_math_import_settings` (`includes/class-setup-wizard.php:44`), Imagify `install_imagify` / `dismiss_promote_imagify` (`includes/3rdparty/plugin-family/class-plugin-family.php:54`).

## Cron / Action Scheduler hooks added by PRO
`rank_math_cleanup_exports`, `rank_math_cleanup_orphaned_link_status`, `rank_math_link_genius_start_crawler`, `rank_math_link_genius_dispatch_crawler`, `rank_math_link_genius_queue_pending_links`, `rank_math_keyword_maps_auto_link` (Link Genius); `rank_math/redirections/scheduled_activate`, `rank_math/redirections/scheduled_deactivate` (redirections, Action Scheduler group `rank-math`); `rank_math/analytics/workflow`, `rank_math/analytics/analytics`, `rank_math/analytics/adsense`, `rank_math/analytics/data_fetch`, `rank_math/analytics/get_analytics_data`, `rank_math/analytics/get_adsense_data` (analytics).

---

# Unverified / caveats

- Line numbers are exact as of plugin versions PRO 3.0.109 / free 1.0.278; they move with updates.
- Some module sub-views (e.g. `modules/local-seo/views/titles-options.php`, `modules/podcast/views/*`) were cited by the exploration pass at the option-field level, not every helper function; option key names are verified but individual default values were not all confirmed.
- Email report template files and `views/email-reports/*` section files are referenced but their full field lists were sampled, not exhaustively diffed.
- The content-ai module's PRO JS is minified/only sets copy filters; its exact hook names were identified via the src mapping, not line-traced in the built bundle.
- "Free API" Google services (GA/GSC/PageSpeed/AdSense/Maps) still require OAuth/account setup; only genuinely paid Rank Math services are Content AI credits, Rank Tracker quota, RankMath.com sync/updates/license, and the SEO Analyst competitor API.
