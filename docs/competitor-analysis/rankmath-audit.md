# Rank Math SEO — Reverse-Engineering Audit (Free 1.0.277.2)

> Consolidated audit of `seo-by-rank-math` free build 1.0.277.2. Facts are drawn from six source reverse-engineering reports (database/data layer; frontend head + schema; sitemaps/redirects/404/instant-indexing/REST/always-on cost; module inventory + bloat; plus two targeted re-audits that superseded the thin head-pipeline/schema and module/bloat passes). All `file:line` citations point at the plugin source under `wp-content/plugins/seo-by-rank-math/`. Rank Math Pro source is not in this repository — Pro-side statements derive from the free code's own gating flags and public documentation.

---

## 1. Executive Summary

1. **Only four `wp_options` groups are registered** — `rank-math-options-general`, `rank-math-options-titles`, `rank-math-options-sitemap`, `rank-math-options-instant-indexing` (`includes/class-settings.php:43-46`, `includes/class-installer.php:368,475-476,735-743`). The expected `rank-math-options-links`, `rank-math-options-404`, `rank-math-options-imageseo`, `rank-math-options-woocommerce` groups **do not exist**; those module settings are folded into `rank-math-options-general`.
2. **Schema is stored as post/term/user meta** (`rank_math_schema_*`, `rank_math_shortcode_schema_*`) — there is **no `wp_rank_math_schema` table**. This is the primary post-meta bloat source (multiple serialized rows per object).
3. **All four settings groups are autoloaded** (`add_option` with no autoload arg → WordPress autoloads `yes`), so the large serialized payloads load on every request. Among analytics options, only `rank_math_analytics_cron_notice_dismissed` is explicitly `autoload=no` (`includes/modules/analytics/class-analytics.php:227`).
4. **Module gating is hard.** When a module is off, `can_load_module()` returns false and its class is never instantiated, so its constructor (which registers every hook) never runs → zero hooks leak (`includes/module/class-module.php:231-242`, `includes/module/class-manager.php:493-501`).
5. **Redirections is the dominant always-on cost:** `do_redirection` fires on `wp` priority 11 on every frontend request and runs 1–2 DB queries minimum (`rank_math_redirections_cache` then `rank_math_redirections` on miss) even for non-redirected URLs (`includes/modules/redirections/class-redirections.php:41-45`, `class-redirector.php:224-262`).
6. **The sitemap "ping" is a misnomer.** `rank_math/sitemap/hit_index` only does `wp_remote_get()` of the sitemap index to warm the cache; there is **no real HTTP ping to Google/Bing** (`includes/modules/sitemap/class-sitemap.php:159-161`, `class-cache-watcher.php:106,114-131`).
7. **Pro-locked features in free code:** `link-genius`, `news-sitemap`, `video-sitemap`, `podcast` are registered `probadge`+`disabled` with **no `class`** and cannot be activated in free (`includes/module/class-manager.php:164-171,208-233`); Google Indexing API is Pro (free uses IndexNow only); the advanced schema builder is `upgradeable=>true` (full builder in Pro); analytics depth is capped at 90 days free (`class-analytics.php:624`, `options.php:86`); Content AI is `upgradeable=>true` with a free tier. **Header/footer code injection has no frontend output in free — it is a PRO feature** (the only `header_code` in the free code is the redirections HTTP status; `footer_code` does not exist).
8. **Uninstall leaves all data by default.** Cleanup is gated by the filter `rank_math_clear_data_on_uninstall` (default `false`), not an option (`uninstall.php:37`).
9. **Frontend `rank-math.css`/`rank-math.js` only load when the admin bar is showing AND the user has the `admin_bar` cap** (`includes/class-frontend.php:111-129`) — otherwise zero frontend assets. **All admin assets are screen-gated** — no admin script/style is enqueued unconditionally; every handle is gated by screen id or taxonomy (`includes/admin/class-assets.php:126-171`).
10. **Content analysis is client-side.** Readability, keyword density, and link counts run in `assets/admin/js/analyzer.js`; the site-wide SEO analyzer hits the remote `https://rankmath.com/analyze/v2/json/` API. There is no server-side per-post analyze endpoint (`includes/modules/seo-analysis/class-seo-analyzer.php:105`; `class-metabox.php:437`).

---

## 2. Database Storage Schema

### 2.1 Post / Term / User Meta Keys (`wp_*meta`)

All keys are prefixed `rank_math_`. The canonical registry is the `metabox/{type}/meta_keys` map in `includes/admin/metabox/class-screen.php:242-290` (each value is prefixed with `rank_math_` at line 298).

**Core SEO / Social (verbatim, from `class-screen.php:245-289`):**

| Meta key | Purpose |
|---|---|
| `rank_math_title` | SEO title |
| `rank_math_description` | Meta description |
| `rank_math_focus_keyword` | Primary focus keyword |
| `rank_math_pillar_content` | Pillar-content flag (`on`) |
| `rank_math_canonical_url` | Canonical URL override |
| `rank_math_breadcrumb_title` | Breadcrumb label |
| `rank_math_robots` | Robots array (index/nofollow/etc.) (line 302) |
| `rank_math_advanced_robots` | Advanced robots (max-snippet/image/video/preview) |
| `rank_math_facebook_title` | OG title |
| `rank_math_facebook_description` | OG description |
| `rank_math_facebook_image` | OG image URL |
| `rank_math_facebook_image_id` | OG image attachment ID |
| `rank_math_facebook_enable_image_overlay` | OG image overlay toggle |
| `rank_math_facebook_image_overlay` | OG image overlay text |
| `rank_math_facebook_author` | FB author |
| `rank_math_twitter_card_type` | Twitter card type |
| `rank_math_twitter_use_facebook` | Reuse FB data for Twitter |
| `rank_math_twitter_title` | Twitter title |
| `rank_math_twitter_description` | Twitter description |
| `rank_math_twitter_image` | Twitter image URL |
| `rank_math_twitter_image_id` | Twitter image attachment ID |
| `rank_math_twitter_enable_image_overlay` | Twitter image overlay toggle |
| `rank_math_twitter_image_overlay` | Twitter image overlay text |
| `rank_math_twitter_player_url` | Twitter player URL |
| `rank_math_twitter_player_size` | Twitter player size |
| `rank_math_twitter_player_stream` | Twitter player stream |
| `rank_math_twitter_player_stream_ctype` | Twitter player stream content-type |
| `rank_math_twitter_app_description` | Twitter app description |
| `rank_math_twitter_app_iphone_name` / `_id` / `_url` | Twitter iPhone app |
| `rank_math_twitter_app_ipad_name` / `_id` / `_url` | Twitter iPad app |
| `rank_math_twitter_app_googleplay_name` / `_id` / `_url` | Twitter Google Play app |
| `rank_math_twitter_app_country` | Twitter app country |

**Schema (serialized JSON, multiple rows per object — `includes/modules/schema/class-db.php`):**

| Meta key | Purpose |
|---|---|
| `rank_math_schema_<meta_id hash>` | Serialized schema graph per block (queried via `whereLike('meta_key','rank_math_schema')`, line 65) |
| `rank_math_shortcode_schema_<id>` | Shortcut pointer to a saved schema (line 165) |
| `rank_math_snippet_*` | Schema subtype fields (e.g. `rank_math_snippet_name`, `rank_math_snippet_desc`, `rank_math_snippet_recipe_ingredients`, `rank_math_snippet_recipe_instructions`, `rank_math_snippet_recipe_single_instructions`, `rank_math_snippet_job_description`, `rank_math_snippet_answer`) |

**Primary term (per hierarchical taxonomy — `includes/admin/metabox/class-metabox.php:466-469`):**

| Meta key | Purpose |
|---|---|
| `rank_math_primary_<taxonomy>` | e.g. `rank_math_primary_category` |

**Legacy / control:**

| Meta key | Purpose |
|---|---|
| `rank_math_rich_snippet` | Written `'off'` when schema disabled (`includes/rest/class-shared.php:440`); legacy shortcode `[rank_math_rich_snippet]` still supported (`includes/modules/schema/class-snippet-shortcode.php:47`) |

**Sanitization (`includes/rest/class-sanitize.php:49-114`):**
- `wp_filter_nohtml_kses`: `rank_math_title`, `rank_math_description`, `rank_math_snippet_name`, `rank_math_snippet_desc`, `rank_math_facebook_title`/`_description`, `rank_math_twitter_title`/`_description`.
- `esc_url_raw`: `rank_math_canonical_url`.
- `sanitize_textarea_field`: `rank_math_snippet_recipe_ingredients`/`_instructions`/`_single_instructions`.
- `wp_kses` (br,p,ul,li): `rank_math_snippet_job_description`; `wp_kses` (h1-h6,ol,ul,li,a,p,b,i,div,strong,em): `rank_math_snippet_answer`.
- Default (arrays like `rank_math_robots`, `rank_math_advanced_robots`, image IDs): `CMB2::sanitize_textfield` / recursive `loop_sanitize`.
- Write path guarded by `is_protected_meta()` in `includes/traits/class-meta.php:56`.

**Template / inheritance logic:** Defaults are template strings with `%variable%` placeholders set in `includes/class-installer.php` (e.g. `homepage_title` = `'%sitename% %page% %sep% %sitedesc%'`, `pt_<post_type>_title` = `'%title% %sep% %sitename%'`, `tax_<tax>_title` = `'%term% %sep% %sitename%'`). Resolution chain at render: **per-object meta → post-type/taxonomy default (`pt_*`/`tax_*`) → global**. Robots: per-post `rank_math_robots` array overrides `pt_<pt>_robots` (defaults in `class-installer.php:544-566,615-628`). Replacement engine: `includes/replace-variables/` (Manager + variable classes).

**No `register_meta` for the classic editor.** The REST write path is the custom `/updateMeta` route (see §8).

### 2.2 `wp_options` Groups

**Registered settings groups (all `add_option` with NO autoload arg → WordPress autoloads them = `yes`):**
- `rank-math-options-general` (`class-installer.php:367-423`) — ~60 keys (breadcrumbs, redirections, 404-monitor, image-seo, WooCommerce, RSS, content-ai, llms, analytics toggles). **Large serialized payload, autoloaded on every request.**
- `rank-math-options-titles` (`class-installer.php:475`) — homepage + per-post-type (`pt_*`) + per-taxonomy (`tax_*`) keys. **Largest group; autoloaded.**
- `rank-math-options-sitemap` (`class-installer.php:476`) — sitemap + per-PT/per-tax sitemap keys. Autoloaded.
- `rank-math-options-instant-indexing` (`class-installer.php:735-743`) — `bing_post_types` etc. Autoloaded.

**Standalone options (autoload status noted):**
- `rank_math_modules` (array of active module ids) — autoload **yes** (`class-installer.php:354`).
- `rank_math_known_post_types` — autoload yes (`class-installer.php:320`).
- `rank_math_version`, `rank_math_db_version` — autoload **NO** (`class-updates.php:101-102`, explicit `false`).
- `rank_math_install_date`, `rank_math_wizard_completed`, `rank_math_registration_skip`, `rank_math_flush_rewrite`, `rank_math_local_seo_update`, `rank_math_prompts_updated`, `rank_math_console_empty_dates`, `rank_math_viewed_index_status`, `rank_math_analytics_first_fetch`, `rank_math_analytics_installed`, `rank_math_analytics_last_updated`, `rank_math_analytics_last_single_action_schedule_time`, `rank_math_google_analytic_profile`, `rank_math_google_analytic_options`, `rank_math_analytics_all_services` (OAuth tokens — **large, but autoloaded yes**), `rank_math_content_ai_posts` — autoload yes (no 3rd param).
- Explicitly **NO-autoload** (`update_option(..., false)`): `rank_math_analytics_cron_notice_dismissed` (`class-analytics.php:227`), `rank_math_seo_analysis_results`/`_date`, `rank_math_ca_data`, `rank_math_content_ai_posts_processed`, `rank_math_content_ai_viewed`, `rank_math_backups`, `rank_math_indexnow_log`, `rank_math_yoast_block_posts`, `rank_math_aioseo_block_posts`, `rank_math_review_notice_*`, `rank_math_pro_notice_*`, `rank_math_already_reviewed`, `rank_math_already_upgraded`, `rank_math_view_modules`, `rank_math_react_settings_ui`, `rank_math_remove_nginx_notice`, `rank_math_update_notifications_sent`.
- **Transients** (stored in options, autoload no): `rank_math_analytics_data_info`, `top_keywords`, `posts_summary`, `top_keywords_graph`, `dashboard_stats_widget` — purged in `includes/modules/analytics/class-db.php:108-119`.

### 2.3 Custom Tables

Tables are created by `Installer::create_tables($modules)` (`class-installer.php:188-284`), called at activation with the active-modules list, and again per-module when a module is toggled on (`includes/class-helper.php:193` → `Installer::create_tables([$module])`). **Note:** the default activation module list (`class-installer.php:322-336`) does **not** include `404-monitor` or `redirections` — those tables are created only when those modules are switched on. Analytics tables are created by the workflow classes (not the installer switch).

**`wp_rank_math_404_logs`** (404-monitor): `id` bigint(20) unsigned AI PK; `uri` varchar(255); `accessed` datetime; `times_accessed` bigint(20) unsigned def 1; `referer` varchar(255) def ''; `user_agent` varchar(255) def ''; KEY `uri`(uri(191)).

**`wp_rank_math_redirections`** (redirections): `id` bigint(20) unsigned AI PK; `sources` longtext (binary collation); `url_to` text; `header_code` smallint(4) unsigned; `hits` bigint(20) unsigned def 0; `status` varchar(25) def 'active'; `created`/`updated`/`last_accessed` datetime; KEY `status`, KEY `idx_rm_status_updated`(status,updated).

**`wp_rank_math_redirections_cache`** (redirections): `id` bigint(20) unsigned AI PK; `from_url` text (binary); `redirection_id` bigint(20) unsigned; `object_id` bigint(20) unsigned def 0; `object_type` varchar(10) def 'post'; `is_redirected` tinyint(1) def 0; KEY `redirection_id`.

**`wp_rank_math_internal_links`** (link-counter): `id` bigint(20) unsigned AI PK; `url` varchar(255); `post_id` bigint(20) unsigned; `target_post_id` bigint(20) unsigned; `type` varchar(8); KEY `link_direction`(post_id,type), KEY `target_post_id`.

**`wp_rank_math_internal_meta`** (link-counter): `object_id` bigint(20) unsigned PK; `internal_link_count` int(10) unsigned def 0; `external_link_count` int(10) unsigned def 0; `incoming_link_count` int(10) unsigned def 0.

**`wp_rank_math_analytics_gsc`** (`workflows/class-console.php:55-79`): `id` bigint(20) unsigned AI PK; `created` timestamp; `query` varchar(1000); `page` varchar(500); `clicks` mediumint(6); `impressions` mediumint(6); `position` double; `ctr` double; KEY `analytics_query`(query(190)), `analytics_page`(page(190)), `clicks`, `rank_position`.

**`wp_rank_math_analytics_objects`** (`workflows/class-objects.php:47-69`): `id` bigint(20) unsigned AI PK; `created` timestamp; `title` text; `page` varchar(500); `object_type`/`object_subtype` varchar(100); `object_id` bigint(20) unsigned; `primary_key` varchar(255); `seo_score`/`page_score` tinyint def 0; `is_indexable` tinyint(1) def 1; `schemas_in_use` varchar(500); `desktop_interactive`/`desktop_pagescore`/`mobile_interactive`/`mobile_pagescore` double; `pagespeed_refreshed` timestamp; KEY `analytics_object_page`(page(190)).

**`wp_rank_math_analytics_inspections`** (`workflows/class-inspections.php:74-104`): `id` bigint(20) unsigned AI PK; `page` varchar(500); `created` timestamp; `index_verdict`/`indexing_state`/`page_fetch_state`/`robots_txt_state`/`rich_results_verdict` varchar(64); `coverage_state` text; `rich_results_items`/`referring_urls`/`raw_api_response` longtext; `last_crawl_time` timestamp; `crawled_as` varchar(64); `google_canonical`/`user_canonical`/`sitemap` text; KEYs `analytics_object_page`, `created`, `index_verdict`, `page_fetch_state`, `robots_txt_state`, `rich_results_verdict`.

**Schema:** no table — stored as meta (see §2.1).

**Action Scheduler vendor tables:** `woocommerce/action-scheduler` provides `actionscheduler_actions`, `actionscheduler_claims`, `actionscheduler_groups`, `actionscheduler_logs` (vendored, not Rank Math-owned).

### 2.4 Module Registry

- Active modules stored as an **array of IDs in `rank_math_modules`** (`class-installer.php:354`).
- `includes/module/class-manager.php` registers every module's metadata via filters `setup_core` (`:112-245`), `setup_admin_only` (`:254-286`), `setup_internals` (`:295-322`), `setup_3rd_party` (`:331-393`); registration is filtered through `rank_math/modules` and sorted with `ai-visibility` + `content-ai` forced to top (`setup_modules` `:83-103`).

**Full module registry (28 modules):**

| id | name | class | probadge | upgradeable | disabled | disabled_text | only | deps |
|----|------|-------|----------|-------------|----------|---------------|------|------|
| 404-monitor | 404 Monitor | `RankMath\Monitor\Monitor` | – | true | – | – | – | – |
| local-seo | Local SEO | `RankMath\Local_Seo\Local_Seo` | – | true | – | – | – | – |
| redirections | Redirections | `RankMath\Redirections\Redirections` | – | true | – | – | – | – |
| rich-snippet | Schema (Structured Data) | `RankMath\Schema\Schema` | – | true | – | – | – | – |
| sitemap | Sitemap | `RankMath\Sitemap\Sitemap` | – | – | – | – | – | – |
| link-counter | Link Counter | `RankMath\Links\Links` | – | – | – | – | – | – |
| link-genius | AI Link Genius | (none) | true | – | true | "This module is available in the PRO version." | – | – |
| image-seo | Image SEO | `RankMath\Image_Seo\Image_Seo` | – | true | – | – | – | – |
| instant-indexing | Instant Indexing | `RankMath\Instant_Indexing\Instant_Indexing` | – | – | – | – | – | – |
| content-ai | Content AI | `RankMath\ContentAI\Content_AI` | – | true | – | – | – | – |
| llms-txt | LLMS Txt | `RankMath\LLMS\LLMS_Txt` | – | – | – | – | – | – |
| news-sitemap | News Sitemap | (none) | true | – | true | "This module is available in the PRO version." | – | – |
| video-sitemap | Video Sitemap | (none) | true | – | true | "This module is available in the PRO version." | – | – |
| podcast | Podcast | (none) | true | – | true | "This module is available in the PRO version." | – | – |
| ai-visibility | AI Visibility | `RankMath\AI_Visibility\AI_Visibility` | – (betabadge=true) | – | – | – | – | – |
| role-manager | Role Manager | `RankMath\Role_Manager\Role_Manager` | – | – | – | – | admin | – |
| analytics | Analytics | `RankMath\Analytics\Analytics` | – | true | – | – | admin | – |
| seo-analysis | SEO Analyzer | `RankMath\SEO_Analysis\SEO_Analysis` | – | true | – | – | admin | – |
| robots-txt | Robots Txt | `RankMath\Robots_Txt` | – | – | – | – | internal | – |
| version-control | Version Control | `RankMath\Version_Control` | – | – | – | – | internal | – |
| database-tools | Database Tools | `RankMath\Tools\Database_Tools` | – | – | – | – | internal | – |
| status | Status | `RankMath\Status\Status` | – | – | – | – | internal | – |
| amp | AMP | (none) | – | – | – | – | skip | – |
| bbpress | bbPress | (none) | `defined('RANK_MATH_PRO_FILE')` | – | `!function_exists('is_bbpress')` | "Please activate bbPress plugin to use this module." | skip | – |
| buddypress | BuddyPress | `RankMath\BuddyPress\BuddyPress` | – | – | `!class_exists('BuddyPress')` | "Please activate BuddyPress plugin to use this module." | – | – |
| woocommerce | WooCommerce | `RankMath\WooCommerce\WooCommerce` | – | true | `!Helper::is_woocommerce_active()` | "Please activate WooCommerce plugin to use this module." | – | – |
| acf | ACF | `RankMath\ACF\ACF` | – | – | `!function_exists('acf')` | "Please activate ACF plugin to use this module." | – | – |
| web-stories | Google Web Stories | `RankMath\Web_Stories\Web_Stories` | – | – | `!defined('WEBSTORIES_VERSION')` | "Please activate Web Stories plugin to use this module." | – | – |

**Dependencies:** No module in the registry declares a `dep_modules` key. `Module::get_dependencies()` (`class-module.php:159-161`) therefore returns `[]` for every module; the `data-depmodules` JSON in the UI form (`class-manager.php:474`) is always empty.

**Default active modules on install** (`class-installer.php:322-336`, `create_misc_options`): `link-counter, analytics, seo-analysis, sitemap, rich-snippet, woocommerce, buddypress, bbpress, acf, web-stories, content-ai, instant-indexing, ai-visibility`. Plus `role-manager` only if >1 user exists (`:340-342`); `amp` if an AMP plugin is detected (`:345-347`); `404-monitor` only if a prior `rank_math_monitor_version` option exists (`:350-352`). **Off by default:** `404-monitor, local-seo, redirections, image-seo, llms-txt, link-genius, news-sitemap, video-sitemap, podcast` (the four PRO-badge modules are disabled and cannot be activated in free).

- `is_active()` (`class-module.php:208-215`) = `in_array($id, get_option('rank_math_modules'))`.
- **What happens when OFF:** `can_load_module()` (`class-module.php:231-242`) returns `false` when `!is_active()` (or `is_skip()`). In `Manager::load_modules()` (`class-manager.php:493-501`) the module class is only instantiated (`new $object_class()`) if `can_load_module()` is true. So an **off module's class is never constructed → its hooks/actions are never registered** (functionality fully disabled). Class files are PSR-4 autoloaded on-demand, so they are not eagerly loaded. **Internal modules are always loaded** (`is_internal()` → `can_load_module` true).
- Toggling analytics off fires `watch_for_analytics` (`class-manager.php:65-78`) which **unschedules all `rank_math/analytics/data_fetch` actions and kills workflows**. Newly activated modules trigger `Helper::create_tables([$module])` (table creation).
- **Pro-locked in free code:** `link-genius`, `news-sitemap`, `video-sitemap`, `podcast` are registered `probadge`+`disabled` with **no `class`** and cannot be activated in free (`class-manager.php:164-171,208-233`). `bbpress` shows a `probadge` only once Pro is installed (`class-manager.php:348`).

### 2.5 Cron / Background Processing

**WP-Cron** (registered in `Installer::get_cron_jobs()` `class-installer.php:669-675`, scheduled on `wp` and on activation):
- `rank_math/redirection/clean_trashed` (daily) → `DB::periodic_clean_trash` (`redirections/class-db.php:501-515`, hooked `redirections/class-admin.php:118`) — deletes trashed redirects >30 days.
- `rank_math/links/internal_links` (daily) — recounts internal/external/incoming link counts.
- `rank_math/content-ai/update_prompts` (daily) — refreshes Content AI prompt data (timestamp = midnight + random 60–86400s).

**Sitemap ping** (`includes/modules/sitemap/class-cache-watcher.php:114-199`): NOT a recurring cron. On `transition_post_status` to `publish`, if `WP_CACHE` is on it schedules a single event `wp_schedule_single_event(time()+300, 'rank_math/sitemap/hit_index')` (line 129); bulk import path calls `maybe_ping_search_engines()` + `do_action('rank_math/sitemap/hit_index')` (lines 172-178).

**Action Scheduler** (vendored `woocommerce/action-scheduler`; PHP wrappers in `includes/helpers/class-schedule.php`): all analytics jobs use group **`rank-math`**. Hooks: `rank_math/analytics/data_fetch` (recurring, via `Helper::schedule_data_fetch`), `rank_math/analytics/flat_posts`, `rank_math/analytics/flat_posts_completed`, `rank_math/analytics/get_inspections_data`, `rank_math/analytics/get_analytics`, `rank_math/analytics/workflow/create_tables`/`/console`/`/inspections`. Table creation for analytics is triggered by these workflow hooks (not the installer switch).

### 2.6 Uninstall Behavior (`uninstall.php`)

- **Gated by `apply_filters('rank_math_clear_data_on_uninstall', false)` (line 37). Default `false` → NOTHING is deleted.** There is no `rank_math_db_cleanup` option in this free build.
- When the filter returns true: `rank_math_remove_data()` (line 61) on single site or looped across blogs (multisite):
  - `rank_math_delete_options()` (line 97) — `DELETE FROM options WHERE option_name LIKE '%rank-math%' OR '%rank_math%'`.
  - `rank_math_delete_meta('post'|'user'|'term')` (line 110) — `DELETE FROM {type}meta WHERE meta_key LIKE '%rank-math%' OR '%rank_math%'` (this covers schema meta too).
  - `rank_math_drop_table(...)` for: `404_logs`, `redirections`, `redirections_cache`, `internal_links`, `internal_meta`, `analytics_gsc`, `analytics_objects`, `analytics_inspections` (lines 71-78).
  - `Capability_Manager::remove_capabilities()` (line 86); `wp_cache_flush()`.
- Before deletion: `ActionScheduler_DBStore::cancel_actions_by_group('rank-math')` and `ActionScheduler_QueueRunner::unhook_dispatch_async_request()` (lines 29-34) — clears all queued background jobs.

---

## 3. Frontend Head Pipeline

**Bootstrap & integration** (`includes/frontend/class-frontend.php`): `integrations()` (`:73-106`) registers `Head` (`:37-49`), `Paper` (`:51-71`), `OpenGraph` (`:108-110`) and `JsonLD` (`:112-114`) on `init` priority **99**. `Paper` is a singleton — `class-paper.php:84-92` `get_current()` returns a cached object; the per-field getters are `get_title :146`, `get_description :183`, `get_robots :211`, `get_canonical :352`.

**Title override** (`includes/frontend/class-head.php`): `wp_title`, `thematic_doctitle` and `pre_get_document_title` are ALL hooked at priority **15** (`:67-69`) → `filter_title()` (`:205-212`). Line `:72` removes `_wp_render_title_tag` and `:73` re-adds it to the `rank_math/head` action so Rank Math fully owns title output.

**Head tag output — the `rank_math/head` action:** `Head::head()` is hooked to `wp_head` priority **1** (`:48`) and simply calls `do_action('rank_math/head')` (`:189`). The sub-actions (all inside `Head`) fire at fixed priorities:

| tag | priority | method | notes |
|-----|----------|--------|-------|
| title | 1 | `head_title()` `:217` | |
| metadesc | 6 | `head_description()` `:217` | |
| robots | 10 | `head_robots()` `:228` | removes canonical on noindex `:237-240` |
| canonical | 20 | `head_canonical()` `:246` | |
| adjacent rel links | 21 | `:64` | prev/next |
| metakeywords | 22 | `:65` | gated by `frontend/show_keywords` (default **false**) |
| webmaster | 90 | `:66` | front page only |

Core WP tag removals: `:174-178` removes `wp_generator`, `wlwmanifest_link`, `rsd_link`, `feed_links_extra`; `:82-84` removes `index_rel_link`, `start_post_rel_link`, `adjacent_posts_rel_link_wp_head`.

**Replace variables** (directory `includes/replace-variables/`): `class-manager.php` `setup()` hooks `wp` priority **25** (`:58-62`); variable token sets are `class-basic-variables.php:54-105`, `class-post-variables.php:28-273`, `class-term-variables.php:26-77`. The cache is **in-memory only** (`class-cache.php:18-57`) — no transients are used on the frontend.

**Header / footer code injection (RESOLVED):** There are **zero `footer_code` matches** in the free code and no `general.header_code` / `general.footer_code` settings keys. Header/footer code injection is a **PRO-only** feature — the free build has no frontend output path for it.

**Pro gating:** there is **no `class-licenses.php`** in the free build; Pro gating is via `Helper::is_pro()` / the `RANK_MATH_PRO_FILE` constant (see §9).

**Performance:** `Paper` reads only autoloaded options; the head pipeline issues no extra DB queries on a normal request (replace-vars are in-memory, transients are admin-only). `JsonLD` runs on `rank_math/head` priority **90** (see §4).

---

## 4. Schema (JSON-LD) Architecture

**Snippet classes (auto-generated, `includes/modules/schema/snippets/`):** `Article`, `Author`, `Breadcrumbs`, `PrimaryImage`, `Product` (+ `EDD`, `Woo`, `WC_Attributes` variants), `Products_Page`, `Publisher`, `Singular`, `WebPage`, `Website`. These are attached automatically per content type.

**FAQ / HowTo are Gutenberg blocks, not snippets:** `blocks/faq` and `blocks/howto` emit their JSON-LD via the `rank_math/schema/block/faq-block` (`:69`) and `rank_math/schema/block/howto-block` (`:72`) filters, parsed by `Block_Parser` hooked to `rank_math/json_ld` priority **8**.

**Recipe / Event are not auto snippets:** they have no automatic `snippets/` classes. `shortcode/recipe.php` and `shortcode/event.php` are **`@type`-keyed view templates** included by `Snippet_Shortcode::get_snippet_content()` (`class-snippet-shortcode.php:148-151`) when rendering a Recipe/Event schema; the actual registered shortcodes are `[rank_math_rich_snippet]` and `[rank_math_review_snippet]` (`class-snippet-shortcode.php:47-48`) — there is no `[rank_math_recipe]`/`[rank_math_event]` shortcode.

**JSON-LD generator** (`includes/modules/schema/class-jsonld.php`): `setup()` (`:53-59`) hooks `json_ld()` (`:134-166`) to `rank_math/head` priority **90**. `json_ld()` collects every provider via `do_filter('rank_math/json_ld')` (`:149`) and wraps the result in a top-level `@graph` (`:157-160`). `add_context_data()` (`:233-255`) injects the global `@context` / `WebSite` / `Organization` entities. Custom schema saved per-object is merged in `includes/frontend/class-frontend.php` `add_schema()` (`:64-84`) via `DB::get_schemas()` `array_merge` (`:83`).

**Storage:** schema is **post/term/user meta**, not a table. Rows are keyed `schema-{meta_id}` and queried with `whereLike('meta_key','rank_math_schema')` (`includes/modules/schema/class-db.php:61-66`); the shortcut pointer `rank_math_shortcode_schema_{id}` is at `:165`.

**Verbatim filter names (schema):**
- `rank_math/json_ld` — the collection filter every provider appends to (`:149`).
- `rank_math/schema/validated_data` — `class-jsonld.php:153` (post-validation hook).
- `rank_math/schema/update` — `includes/rest/class-shared.php:232` (REST write path `/updateSchemas`, gated by `get_schema_permissions_check`).
- `rank_math/schema/filter_data` — `class-jsonld.php:189`.
- `rank_math/schema/block/{faq-block,howto-block}` — block JSON-LD parsers.
- `rank_math/schema/default_type` — `includes/helpers/class-schema.php:55`.

**Free vs Pro split:** the schema module is registered `upgradeable => true` (`includes/module/class-manager.php:145`). The "advanced schema builder" UI is a Pro feature — teased in free, full builder in Pro. The auto snippets, FAQ/HowTo blocks, and Recipe/Event shortcodes above are all present in the free build.

---

## 5. XML Sitemap Architecture

### 5a. Rewrite rules / query vars / theme bypass
- `Router::__construct` hooks: `init` (p1) → `init()`; `parse_query` (p1) → `request_sitemap`; `template_redirect` (p0) → `template_redirect`; `after_setup_theme` (p99) → `reduce_query_load` (`class-router.php:33-38`).
- Query vars registered on `init`: `sitemap`, `sitemap_n`, `xsl` (`class-router.php:47-49`).
- Rewrite rules (priority `top`): `sitemap_index.xml` → `index.php?sitemap=1`; `([^/]+?)-sitemap([0-9]+)?\.xml` → `sitemap=$1&sitemap_n=$2`; `([a-z]+)?-?sitemap.xsl` → `xsl=$1` (`class-router.php:51-53`).
- Theme bypass: `request_sitemap()` instantiates `Sitemap_XML` which calls `remove_all_actions('widgets_init')` and at output calls `remove_all_actions('wp_footer')` then `die` (`class-sitemap-xml.php:76,105-106`). `reduce_query_load()` also strips `widgets_init` when "sitemap" is in the request URI (`class-router.php:85-94`). `Sitemap_Index::redirect_canonical` returns `false` for sitemap/xsl query vars so WP doesn't add trailing-slash redirects (`class-sitemap-index.php:65-71`).

### 5b. Providers
- `Generator::instantiate()` builds `Post_Type`, `Taxonomy`, and `Author` (Author only if `Helper::is_author_archive_indexable()`) providers; external providers via `sitemap/providers` filter (`class-generator.php:96-114`).
- `Post_Type::handles_type` gated by `sitemap.pt_{type}_sitemap` setting (`class-post-type.php:75-91`). `Author::handles_type` gated by `sitemap.authors_sitemap` (`class-author.php:58-60`). Taxonomy provider mirrors post-type gating.
- **Users:** yes (author archive sitemap). **Images:** embedded inline in the post sitemap via `Image_Parser::get_images()` (image namespace declared in `urlset`, `class-generator.php:231-234`; parser `class-image-parser.php:108-136`, gated by `sitemap.include_images`). There is **no separate image sitemap file**.
- **News / Video sitemaps:** PRO-ONLY. In `class-manager.php:208-224` both `news-sitemap` and `video-sitemap` are registered with `'probadge'=>true,'disabled'=>true` and **no `class`** — they never load in free. No news/video hooks exist in the free sitemap code.

### 5c. XSL stylesheet
- Served when `xsl` query var is set: `Router::request_sitemap` → `Stylesheet::output($xsl)` (`class-router.php:66-72`). Stylesheet file `sitemap-xsl.php` is required for type `main`; other types fire `sitemap/xsl_{$type}` action and `die` (`class-stylesheet.php:39-68`). Referenced as `main-sitemap.xsl` (`class-generator.php:75`).

### 5d. Caching
- `Cache` mode is `file` (uploads `rank-math/` dir) or `db` (transient fallback) (`class-cache.php:50-58`).
- Storage key prefix `rank_math_`; filename = `rank_math_` + `md5("{type}_{page}_" . home_url()) . '.xml'` (`class-cache.php:45,154-160`).
- Transient name pattern: `sitemap_{type}_{md5}`; expiry `DAY_IN_SECONDS * 100` (`class-cache.php:114-116,142-143`).
- `Cache::invalidate_storage()` deletes cache files + `DELETE FROM options WHERE option_name LIKE '_transient_sitemap_'` (`class-cache.php:209-270`). File manifest tracked in option `sitemap_cache_files`.
- `Cache_Watcher` registers `clear_on_option_update` for `home`, `permalink_structure`, `rank_math_modules`, `rank-math-options-titles/general/sitemap`, `date_format` (`class-cache-watcher.php:80-86`) — so **toggling the sitemap module off flushes its cache**.

### 5e. The `hit_index` "ping" finding (NO real search-engine ping)
- `Cache_Watcher::status_transition` (hook `transition_post_status`, p10,3) on publish schedules `wp_schedule_single_event( time()+300, 'rank_math/sitemap/hit_index' )` **only if `WP_CACHE`** (`class-cache-watcher.php:114-131`).
- `Sitemap::hit_index()` simply does `wp_remote_get( sitemap_index_url )` to pre-warm the cache (`class-sitemap.php:159-161`).
- **There is NO actual HTTP ping to Google/Bing.** Grep for `google.com/ping`, `bing.com/ping`, `webmasters/tools/ping`, `pingomatic` returns zero matches in the plugin. The "search engine ping" comment in `class-cache-watcher.php:106` is misleading — it only regenerates the cache; discovery is left to the `Sitemap:` directive in robots.txt (`class-sitemap-index.php:42-53`).

---

## 6. Redirects & 404 Monitor

### 6a. Frontend hook point & priority (Redirects)
- `Redirections::__construct`: if `! is_admin()`, hook `do_redirection` on **`wp` priority 11** (or `template_redirect` if BuddyPress is active) (`class-redirections.php:41-45`). This runs on **every frontend request**.
- `do_redirection()` guards: wp-login, customize preview, ajax, empty REQUEST_URI, script URI / `HTTP_X_REQUESTED_WITH`, Elementor preview — then `new Redirector()` (`class-redirections.php:105-119`).

### 6b. Matching algorithm (ordered flow)
`Redirector::__construct` → `start()` → `flow()` → `redirect()` (`class-redirector.php:79-146`). `flow()` array = `['pre_filter','from_cache','everything','fallback']`, stops at first match (`class-redirector.php:107-116`):
1. **pre_filter** — `apply_filters('redirection/pre_search', …)` (`class-redirector.php:205-219`).
2. **from_cache** — `Cache::get_by_object_id_or_url( queried_object_id, type, uri )` against `rank_math_redirections_cache`; matches by `object_id` or exact `trim(from_url,'/') === uri` (`class-redirector.php:224-239`).
3. **everything** — `DB::match_redirections($uri)` against `rank_math_redirections` (active only); on hit, **warms the cache** via `Cache::add(...)` (`class-redirector.php:244-262`).
4. **fallback** — `general.redirections_fallback` → homepage / custom URL (`class-redirector.php:267-288`).
- `DB::match_redirections()` builds a `WHERE … LIKE` over serialized `sources` using URI "words" (slashes/dots → dashes), then `compare_redirections()` runs `compare_sources()` (exact/contains/start/end/regex) (`class-db.php:114-212`). If no word-match, falls back to full active scan (`match_redirections($uri,true)`).
- On match: `DB::update_access()` increments `hits` + `last_accessed`, then `wp_redirect( esc_url_raw($url_to), $code )` + `exit` (`class-redirector.php:121-146`). Codes **410/451** skip the redirect and render a template / set 404 (`class-redirector.php:155-168`).

### 6c. Sources / header codes
- Comparison types: `exact`, `contains`, `start`, `end`, `regex` (`class-db.php:186-212`). Header codes **301/302/307/410/451** (`class-db.php:371-373,412-414`; REST enum `class-shared.php:294-302`). 410/451 force empty `url_to`.

### 6d. Cache table (`rank_math_redirections_cache`)
- `Cache::table()` = `rank_math_redirections_cache` (`class-cache.php:27-29`). Written on cache miss (flow step 3) and by `Watcher::create_redirection` (`class-watcher.php:199-211`). Purged via `Cache::purge($ids)` on `DB::update`/`delete` (`class-db.php:416,471`).
- **Per-request cost:** `from_cache` = 1 query to `rank_math_redirections_cache`; on miss, `everything` = 1+ query to `rank_math_redirections`. So ~1–2 DB queries per frontend hit whenever the module is active.

### 6e. Auto-redirect on slug change
- `Watcher` hooks (only if pretty permalinks + `general.redirections_post_redirect`): `pre_post_update` (capture old permalink), `post_updated` (p10,3) → `handle_post_update` creates a **301** redirect when slug actually changed (`class-watcher.php:48-55,73-116`). Same for terms (`edited_term`) and invalidation on trash/delete/user-delete (`class-watcher.php:56-65,308-334`).

### 6f. CSV import
- **Not present in free.** `Import_Export` only renders an **export** tab; `Export` writes Apache `.htaccess` or Nginx `.conf` (`class-import-export.php:62-99`, `class-export.php:38-81`). CSV import is a PRO feature — there is no `fputcsv`/`fgetcsv`/CSV path in the free redirections module.

### 6g. 404 Monitor — hook point & priority
- `Monitor::__construct`: `save_404_flag` on **`wp`** (captures `is_404()` before themes swap `$wp_query`); `capture_404` on a theme-dependent hook via `get_hook()` (`class-monitor.php:64-65,80-82,290-303`).
- `get_hook()` returns `oxygen_enqueue_frontend_scripts` (Oxygen), `wp_head` (block theme), or `get_header` otherwise; filterable via `404_monitor/hook` (`class-monitor.php:290-303`).

### 6h. 404 Monitor — what gets logged
- `capture_404` bails if `!is_404()` or `http_response_code()` is 410/451 (`class-monitor.php:157-160`).
- Logs: `uri` (always), plus `referer` + `user_agent` in **advanced** mode (`general.404_monitor_mode`, default `simple`) (`class-monitor.php:174-187`). User-agent parsed via `donatj/UserAgent` (`class-monitor.php:239-283`).
- **No IP address is stored** (privacy by omission — only URI/referer/UA). No anonymization routine exists because IP is never collected.

### 6i. 404 Monitor — storage & purge
- Table `rank_math_404_logs` (`class-db.php:29`). `DB::add` truncates the whole table when count ≥ `general.404_monitor_limit` (`class-db.php:84-103`); `DB::update` increments `times_accessed` (`class-db.php:110-118`). **No scheduled purge** in free — only the limit-based truncate.

### 6j. 404 Monitor — one-click redirect from log
- 404 row "Redirect" action links to the Redirections manager pre-filled from the log. `Redirections\Admin::get_sources_for_log()` reads `Monitor_DB::get_logs(['ids'=>…])` and builds exact-match sources (`class-admin.php:392-414`); the 404 admin exposes `redirectionsUri` to JS (`class-admin.php:179`). The redirections form is pre-populated via `?url=` / `?log[]=` params (`class-admin.php:337-385`).

---

## 7. Instant Indexing (IndexNow)

- Trigger: `save_post_{$type}` (p10,3) and `before_save_post` (`wp_insert_post_data` p10,4) for post types in `instant_indexing.bing_post_types` (`class-instant-indexing.php:75-89,288-359`). Only on `publish`/`trash` transitions; respects `is_post_indexable`, WooCommerce visibility, WPML.
- API: `Api` posts JSON to **`https://api.indexnow.org/indexnow/`** with `host/key/keyLocation/urlList` (`class-api.php:116-156`). Key = `wp_generate_uuid4()` stored in option `rank-math-options-instant-indexing[indexnow_api_key]`; served at `home_url()/{key}.txt` via `serve_api_key` on `wp` (`class-instant-indexing.php:373-389`). Log = option `rank_math_indexnow_log` (last 100). Throttle = 5s (`class-api.php:63,255-275,416`).
- **Engines:** IndexNow (Bing/Yandex/Seznam/Yep). The dedicated **Google Indexing API is PRO** — free only uses IndexNow.
- REST: namespace `rankmath/v1/in` — `/submitUrls`, `/getLog`, `/clearLog`, `/resetKey` (`class-rest.php:45-114`).

---

## 8. REST API & Gutenberg Integration

### 8a. Namespace & controllers
- Base = `rankmath/v1` (`Rest_Helper::BASE`, `class-rest-helper.php:29`). Controllers registered in `rank-math.php:358-371` on `rest_api_init`: `Admin`, `Front`, `Shared`, `Post`, `Headless`, `Setup_Wizard`. Module controllers (instant-indexing) self-register on `rest_api_init` (`class-instant-indexing.php:91-92`).

### 8b. REST route table
| Method | Route | Callback | Permission |
|---|---|---|---|
| CREATABLE | `/updateRedirection` | `Shared::update_redirection` | `get_redirection_permissions_check` (module active + cap) |
| CREATABLE | `/updateMeta` | `Shared::update_metadata` | `get_object_permissions_check` |
| CREATABLE | `/updateSchemas` | `Shared::update_schemas` | `get_schema_permissions_check` (rich-snippet module) |
| EDITABLE | `/saveModule` | `Admin::save_module` | `can_manage_options` |
| EDITABLE | `/toolsAction` | `Admin::tools_actions` | `can_manage_options` |
| EDITABLE | `/updateMode` | `Admin::update_mode` | `can_manage_options` |
| READABLE | `/dashboardWidget` | `Admin::dashboard_widget_items` | `read` |
| EDITABLE | `/updateSeoScore` | `Admin::update_seo_score` | `can_edit_posts` |
| EDITABLE | `/updateSettings` | `Admin::update_settings` (also creates redirections/instant-indexing) | `can_manage_settings` |
| ALLMETHODS | `/searchPage` | `Admin::search_page` | `can_manage_options` |
| EDITABLE | `/updateMetaBulk` | `Post::update_bulk_meta` | `has_cap('onpage_general')` |
| (field) | `register_rest_field('page', 'rankMath', …)` | `Post::get_post_screen_meta` | `read` (Site Editor only) |
| READABLE | `/disconnectSite` | `Front::disconnect_site` | api-key token |
| EDITABLE | `/getFeaturedImageId` | `Front::get_featured_image_id` | `has_cap('onpage_general')` |
| CREATABLE | `/in/submitUrls` | `Instant_Indexing\Rest::submit_urls` | `has_cap('general')` |
| CREATABLE | `/in/getLog` | `…::get_log` | `has_cap('general')` |
| EDITABLE | `/in/clearLog` | `…::clear_log` | `has_cap('general')` |
| EDITABLE | `/in/resetKey` | `…::reset_key` | `has_cap('general')` |

### 8c. Gutenberg meta flow
- The classic editor sidebar does **NOT** use `register_meta`/`register_rest_field` for rank_math keys (only `register_rest_field('page',…)` exists, and only in the Site Editor — `class-post.php:52-64`). Instead the sidebar JS collects all `rank_math_*` meta + content and **POSTs to `/updateMeta`** (`Shared::update_metadata`, `class-shared.php:123-177`): it sanitizes via `Sanitize`, then `update_metadata`/`delete_metadata` per key, temporarily forcing `is_protected_meta` to only allow `rank_math_` keys (`class-shared.php:154-170`). Permalink changes call `wp_update_post`. Schema saved via `/updateSchemas` (`class-shared.php:186-235`). Redirection created via `/updateRedirection` → `Metabox::save_advanced_meta` (`class-shared.php:90-114`).

### 8d. Analyzer engine
- **Client-side.** Readability, keyword density, and link counts run in `assets/admin/js/analyzer.js` (`rank-math-analyzer` handle, enqueued in `class-metabox.php:437` and `class-post-screen.php:309-333`). There is **no server-side per-post analyze endpoint** — the `seo-analysis` module's analyzer hits a remote API `https://rankmath.com/analyze/v2/json/` for the site-wide audit (`class-seo-analyzer.php:105`), and the new `rank-math/analyze-post-content` Ability (`includes/abilities/content-analysis/`) is a separate AI/Abilities feature, not the Gutenberg sidebar.

---
## 9. Free vs Pro Gating Mechanics

- **No `class-licenses.php`** in the free build. Pro gating is done via `Helper::is_pro()` / the `RANK_MATH_PRO_FILE` constant and per-module flags.
- `rank_math_modules` option holds the active module IDs. The manager exposes:
  - `is_pro_module()` = `probadge && !RANK_MATH_PRO_FILE` (`includes/module/class-module.php:150-152`) — true for `link-genius`, `news-sitemap`, `video-sitemap`, `podcast` in free.
  - `upgradeable` flag = feature teased in free but fuller in Pro (e.g. `404-monitor`, `local-seo`, `redirections`, `rich-snippet`, `image-seo`, `content-ai`, `analytics`, `seo-analysis`, `woocommerce`).
  - `disabled` + `disabled_text` = Pro-only modules (`link-genius`, `news-sitemap`, `video-sitemap`, `podcast`) and plugin-dependent modules — these register **no `class`** and never load in free (`class-manager.php:164-171,208-233`).

- **Load gate (`can_load_module()`, `class-module.php:231-242`):** returns `false` when `!is_active()`, or `is_skip()`, or `is_pro_module()` (Pro not installed), or the dependency check fails (`!class_exists($object_class)` for plugin-dependent modules). In `Manager::load_modules()` (`class-manager.php:493-501`) the loop `continue`s when `can_load_module()` is false; `load_module()` (`:509-521`) instantiates `new $object_class()` only if `can_load_module()` **and** `class_exists($object_class)`. So an off / Pro / plugin-gated module's class is never constructed → its hooks never register.
- **Upgrade tease UI:** `class-manager.php:454-466` renders the `upgradeable` badge; `:552-577` renders the CTA box linking to the Pro purchase.

- **Content AI:** module is `upgradeable => true` and also has a free tier; the `is_free` plan badge is shown in the module manager `display_form` (`class-manager.php:443`). Gating uses `Helper::get_content_ai_plan()` and `is_pro` checks in the content-ai module.
- **Schema:** module is `upgradeable => true` (`class-manager.php:145`); the advanced schema builder is Pro.
- **Analytics depth:** free is capped at **90 days** (`class-analytics.php:624`, `options.php:86`).
- **Sitemaps:** news-sitemap and video-sitemap are `disabled=>true` with no class (§5b).
- **Instant Indexing:** Google Indexing API is Pro; free uses IndexNow only (§7).
- **Missing `RANK_MATH_PRO_FILE`** is the runtime signal that Pro is not installed; any `is_pro_module()` / `is_pro()` check returns false, so Pro UI/features stay stubbed.

---

## 10. Bloat Inventory

**Admin assets (all screen-gated — no unconditional enqueue):** `includes/admin/class-assets.php` `register()` (`:57-121`) registers every handle; `enqueue()` (`:126-171`) gates each handle by screen id / taxonomy. There is **no admin script/style enqueued unconditionally** on every admin page. The only inline script outside screen gating is the permalink "convert to Rank Math" notice, enqueued only on `options-permalink` via `includes/admin/class-notices.php:310-311`.

**Frontend assets (admin-bar only):** `includes/class-frontend.php:111-129` enqueues `rank-math` css/js **only** when `is_admin_bar_showing() && has_cap('admin_bar')`. Otherwise zero frontend assets. (The SEO-score frontend CSS and breadcrumbs block styles are additional conditional frontend assets.)

**External HTTP requests (enumerated endpoints):**
- `rankmath.com` — dashboard feed (cached in a 12h transient) + `deactivateSite` ping on deactivation.
- `api.rankmath.com` — site registration / analytics.
- `cai.rankmath.com` — Content AI wallet + `generate` calls.
- `oauth.rankmath.com/get.php` — OAuth token fetch.
- Google APIs — analytics/Search Console via Action Scheduler jobs.
- `https://rankmath.com/analyze/v2/json/` — remote SEO analyzer (`includes/modules/seo-analysis/class-seo-analyzer.php:416`).
- `graph.facebook.com` — OG scrape.
- IndexNow `https://api.indexnow.org/indexnow/` (§7).
- `api.wordpress.org` — update check.
- Mixpanel — usage tracking, project `517e881edc2636e99a2ecf013d8134d3` (`includes/modules/analytics/class-tracking.php:42`).

**Admin UI / upsells:**
- Dashboard widget with a `rankmath.com` blog feed + "Go Pro" links.
- Admin-bar menu with external tools; admin-menu seasonal promo offers (`includes/admin/class-admin-menu.php:220-227`).
- `class-pro-notice.php` — 2 banner variants, delayed 7–30 days; plus a module CTA box (`class-manager.php:552-577`) and plugin action links "Unlock PRO".
- Setup wizard (`class-setup-wizard.php`) on first activation.

**SEO analyzer:** local tests (`site_description`, `blog_public`, `permalink_structure`, `focus_keywords`, `post_titles`, `search_console`, `sitemaps`, `auto_update`) **plus** the REMOTE `https://rankmath.com/analyze/v2/json/` call (`class-seo-analyzer.php:416`). Results stored in `rank_math_seo_analysis_results` / `_date` / `_url` (`:201-205`).

**Analytics footprint:** tables `wp_rank_math_analytics_gsc` / `_objects` / `_inspections` (schemas in `workflows/class-console.php:55`, `class-objects.php:47`, `class-inspections.php:74`). Action Scheduler jobs in group `rank-math`: `rank_math/analytics/data_fetch` (recurring every 3 days), `email_report_event` (monthly/weekly/daily), `clear_cache`, and workflow jobs `get_console_data` / `get_inspections_data` / `get_analytics_data` / `get_adsense_data`. OAuth via `oauth.rankmath.com` (`includes/modules/analytics/class-authentication.php:127`) + Google APIs.

---

## 11. Performance Bottleneck Summary

1. **Every-request autoloaded settings (dominant, always on).** All four `rank-math-options-*` groups are autoloaded (`class-installer.php:367-423,475-476,735-743`); `rank-math-options-titles` is the largest (per-PT `pt_*` + per-tax `tax_*` keys) and `rank-math-options-general` holds ~60 keys including large analytics/OAuth data. Loaded on every page load regardless of module state.
2. **Redirections per-request DB hit (dominant, module-active).** `do_redirection` on `wp` p11 (`class-redirections.php:41-45`) runs `from_cache` query on `rank_math_redirections_cache` and, on miss, `match_redirections` on `rank_math_redirections` (`class-redirector.php:224-262`) → **1–2 DB queries per frontend request minimum**, even for non-redirected URLs.
3. **Schema-as-meta bloat (storage).** `rank_math_schema_*` serialized rows (multiple per object) are the main post-meta growth source (`class-db.php:65`); no single JSON column/table compresses them.
4. **Analytics OAuth option autoloaded despite size.** `rank_math_analytics_all_services` (OAuth tokens) is autoloaded `yes` though large (`class-installer.php` default, no 3rd param) — candidate for `autoload=no` (only `rank_math_analytics_cron_notice_dismissed` is explicitly `no`, `class-analytics.php:227`).
5. **Always-loaded bootstrap (not module-gated).** `rank-math.php:301-307` always loads `RankMath\Common`, `Rewrite`, `Frontend_SEO_Score`, `Tracking`, `Compatibility`, plus the 6 REST controllers on every `rest_api_init` — these run regardless of module toggles (`class-manager.php:493-501` only gates module classes, not bootstrap).
6. **404 monitor write path.** `save_404_flag` on `wp` + `capture_404` on `get_header`/`wp_head` is cheap (flag + `is_404()` check); INSERT only on actual 404s into `rank_math_404_logs` (`class-monitor.php:64-65,157-160`). Limit-based truncate (no scheduled purge) (`class-db.php:84-103`).
7. **Sitemap cache warm on publish.** `hit_index` single event only when `WP_CACHE` on (`class-cache-watcher.php:114-131`); otherwise no per-publish cost. Sitemap `Router` hooks early-return unless `sitemap`/`xsl` query var set (`class-router.php:33-94`).

---

## 12. Source File Index

Key plugin files referenced across the audit (paths relative to `wp-content/plugins/seo-by-rank-math/`):

- `includes/class-installer.php` — table DDL (188-284), option defaults (308-424), cron jobs (645-675), default modules (322-336,354).
- `includes/class-settings.php` — registered option groups (43-46).
- `includes/class-updates.php` — `rank_math_version`/`rank_math_db_version` autoload=no (101-102).
- `includes/class-helper.php` — `create_tables` on toggle (193), `schedule_data_fetch`, `is_pro`.
- `includes/replace-variables/` — replace-variable engine (`class-manager.php`, variable token classes, in-memory `class-cache.php`).
- `includes/traits/class-meta.php` — generic meta get/update + `is_protected_meta` guard (56).
- `includes/rest/class-rest-helper.php` — namespace `rankmath/v1` (29).
- `includes/rest/class-shared.php` — `update_metadata` (123-177), `update_schemas` (186-235), `update_redirection` (90-114), REST enums (294-302).
- `includes/rest/class-admin.php` — `save_module`, `update_settings`, `update_seo_score`, `dashboard_widget_items`.
- `includes/rest/class-post.php` — `update_bulk_meta`, `register_rest_field('page')` (52-64).
- `includes/admin/metabox/class-screen.php` — authoritative meta-key registry (242-290, 298-305).
- `includes/admin/metabox/class-metabox.php` — primary-term meta (466-469), analyzer enqueue (437).
- `includes/rest/class-sanitize.php` — per-key sanitization (49-114).
- `includes/module/class-manager.php` — module registry + load gating (493-501), Pro-disabled modules (208-224), `watch_for_analytics` (65-78), `display_form` is_free badge (443).
- `includes/module/class-module.php` — `is_active()` (208-215), `can_load_module()` (231-242).
- `includes/modules/schema/class-db.php` — schema stored as meta (65,165).
- `includes/modules/schema/class-snippet-shortcode.php` — legacy `[rank_math_rich_snippet]` (47).
- `includes/modules/sitemap/class-router.php` — rewrite/query vars (33-53), `reduce_query_load` (85-94).
- `includes/modules/sitemap/class-sitemap-xml.php` — theme bypass (76,105-106).
- `includes/modules/sitemap/class-cache.php` — file/db cache, `rank_math_` keys (45,50-58,114-116,154-160,209-270).
- `includes/modules/sitemap/class-cache-watcher.php` — cache invalidation + `hit_index` (80-86,106,114-131).
- `includes/modules/sitemap/class-sitemap.php` — `hit_index()` (159-161).
- `includes/modules/sitemap/class-generator.php` — providers (96-114), image namespace (231-234).
- `includes/modules/sitemap/providers/class-post-type.php` (75-91), `class-author.php` (58-60).
- `includes/modules/sitemap/class-image-parser.php` — inline image embedding (108-136).
- `includes/modules/redirections/class-redirections.php` — `wp` p11 hook (41-45), `do_redirection` guards (105-119).
- `includes/modules/redirections/class-redirector.php` — flow (79-146), `from_cache` (224-239), `everything` (244-262), 410/451 (155-168).
- `includes/modules/redirections/class-db.php` — `match_redirections` (114-212), `periodic_clean_trash` (501-515), cache purge (416,471).
- `includes/modules/redirections/class-watcher.php` — slug-change 301 (48-55,73-116), cache warm (199-211).
- `includes/modules/redirections/class-admin.php` — one-click redirect from 404 (179,337-385,392-414).
- `includes/modules/redirections/class-import-export.php` (62-99), `class-export.php` (38-81) — export only.
- `includes/modules/404-monitor/class-monitor.php` — hooks (64-65,80-82,290-303), logging (157-160,174-187,239-283).
- `includes/modules/404-monitor/class-db.php` — table (29), truncate (84-103), increment (110-118).
- `includes/modules/instant-indexing/class-instant-indexing.php` — triggers (75-89,288-359), key serve (373-389).
- `includes/modules/instant-indexing/class-api.php` — IndexNow endpoint (116-156), throttle (63,255-275,416).
- `includes/modules/instant-indexing/class-rest.php` — `rankmath/v1/in` routes (45-114).
- `includes/modules/analytics/class-analytics.php` — `autoload=no` notice (227), 90-day cap (624), `schedule_data_fetch` (552).
- `includes/modules/analytics/class-db.php` — transient purge (108-119).
- `includes/modules/analytics/workflows/class-console.php` (55-79), `class-objects.php` (47-69), `class-inspections.php` (74-104) — analytics table DDL.
- `includes/modules/seo-analysis/class-seo-analyzer.php` — remote analyzer (105), tests.
- `includes/modules/seo-analysis/seo-analysis-tests.php` — test list.
- `includes/helpers/class-schedule.php` — Action Scheduler wrappers (group `rank-math`).
- `includes/class-frontend.php` — frontend asset gating (112-117).
- `includes/class-tracking.php` — mixpanel tracking.
- `includes/admin/class-pro-notice.php` — Pro upsell banners (2 variants).
- `uninstall.php` — `rank_math_clear_data_on_uninstall` filter (37), `rank_math_remove_data` (61), delete options/meta/tables (71-110).
- `rank-math.php` — bootstrap always-loaded classes (301-307), REST controller registration (358-371).

**Re-audit source reports (this update):**
- `tool_07bcfcaf0001Njd5Kwhs70KnHg` — Frontend Head Pipeline & Schema re-audit (FINAL REPORT at lines 218-401; supersedes the thin §3/§4 from the original frontend audit).
- `tool_07bcfcb51001ZqCJ57mdbAiEIh` — Module Registry & Bloat re-audit (FINAL REPORT at lines 463-612; supersedes the thin §2.4/§9/§10 from the original module-inventory audit).
