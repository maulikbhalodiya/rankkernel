# Yoast SEO — Reverse-Engineering Audit (Free 27.8 + Premium 27.8)

Consolidated master audit compiled from five source reports: frontend head pipeline, database/data layer, sitemaps + REST/Gutenberg, premium feature mechanics + bloat inventory, and premium deep-dive (bootstrap/licensing/integration inventory/CLI/DB touchpoints/cleanup).

All paths under `/home/web-dev-3/Local Sites/wordpress-test-site/app/public/wp-content/plugins/`. Free plugin root: `wordpress-seo/`. Premium plugin root: `wordpress-seo-premium/`.

Custom-table prefix: `yoast_` + `$wpdb->prefix` (free `lib/model.php:141,144`) → `wp_yoast_*`. The premium prominent-words table uses the same `yoast_` prefix → `{wpdb_prefix}yoast_prominent_words` (e.g. `wp_yoast_prominent_words`).

---

## 1. Executive Summary (10 key findings)

1. **Uninstall is a deliberate no-op.** Free `register_uninstall_hook( WPSEO_FILE, '__return_false' )` at `wp-seo-main.php:151`; the `Uninstall_Integration` only resets `wpseo['importing_completed']`. No `uninstall.php` exists. Premium has no `uninstall.php` and no `register_uninstall_hook` either. Yoast retains ALL data on uninstall (data retention by design).
2. **Premium redirects are NOT a custom table.** They are stored in `wp_options` as serialized arrays: `wpseo-premium-redirects-base`, `wpseo-premium-redirects-export-plain`, `wpseo-premium-redirects-export-regex` (and legacy `wpseo-premium-redirects` / `wpseo-premium-redirects-regex`). Saved with `autoload = false` (`redirect-option.php:203`), so every request does a `get_option()` read + in-PHP `foreach`/`preg_match` loop.
3. **Only one premium custom table exists: `wp_yoast_prominent_words`** (columns `id`, `stem`, `indexable_id`, `weight`). Everything else premium stores in options or post/term/user meta.
4. **Sitemap transient caching is OFF by default.** `WPSEO_Sitemaps_Cache::is_enabled()` returns `apply_filters('wpseo_enable_xml_sitemap_transient_caching', false)` (`class-sitemaps-cache.php:81-89`). Sitemaps are rebuilt on every hit unless a site enables the filter.
5. **Search-engine pinging was removed in 22.0.** `ping_search_engines()` is `@deprecated 22.0` and a no-op (`class-sitemaps-admin.php:66-68`). The only remaining behavior is self-cache warming via `wp_remote_get` to `sitemap_index.xml` on publish. The old `wpseo_hit_sitemap_index` cron was cleared in `inc/class-upgrade.php:653`.
6. **The frontend head pipeline is heavy and always-on.** `Front_End_Conditional::is_met()` = `!is_admin()` (`src/conditionals/front-end-conditional.php:15-17`), so the entire Symfony DI container is booted and `register_hooks()` runs on every non-admin request. `replace_vars()` runs 4+ times per page (once per presenter), re-resolving tokens each time with no cross-presenter cache.
7. **Breadcrumbs generate 4–6 extra `wp_yoast_indexable` queries per request even when not displayed** (only used for the `breadcrumb` schema property). `Breadcrumbs_Generator::generate()` (`breadcrumbs-generator.php:94-197`).
8. **The single biggest TTFB landmine is the indexable rebuild.** `Indexable_Repository::for_current_page()` auto-builds the indexable if missing/upgraded (`upgrade_indexable()` → `Indexable_Builder::build()`), turning one read into a write + many reads mid-request (`indexable-repository.php:128-172, 779-784`).
9. **Editor analysis is 100% client-side** (Web Worker `yoast-seo-analysis-worker`). The server only supplies indexable/head data, replace vars, shortcode list, and existing keyword usage (via admin-ajax, not REST). Link suggestions + prominent words are premium-only.
10. **Global admin bloat:** `admin-global` script + style are enqueued on EVERY admin page with no screen condition (`class-admin.php:279,290`), plus `menu-badge-integration.php` injects inline "Premium" badge CSS into `admin-global` on every admin page. Frontend is essentially clean (premium's only frontend asset is editor-gated).

---

## 2. Database Storage Schema

### 2.1 Post meta keys

Registry: `WPSEO_Meta` — `inc/class-wpseo-meta.php`. Prefix constant `WPSEO_Meta::$meta_prefix = '_yoast_wpseo_'` (line 45). Field definitions in `WPSEO_Meta::$meta_fields` (lines 101-217). Social fields injected dynamically in `WPSEO_Meta::init()` (lines 266-275) from `$social_networks` (`opengraph`, `twitter`) × `$social_fields` (`title`, `description`, `image`, `image-id`).

| Meta key | Purpose | Notes |
|---|---|---|
| `_yoast_wpseo_focuskw` | Focus keyword (primary) | default `'0'` |
| `_yoast_wpseo_title` | SEO title | |
| `_yoast_wpseo_metadesc` | Meta description | |
| `_yoast_wpseo_linkdex` | SEO link/score | default `'0'` |
| `_yoast_wpseo_content_score` | Content analysis score | default `'0'` |
| `_yoast_wpseo_inclusive_language_score` | Inclusive-language score | default `'0'` |
| `_yoast_wpseo_seo_title_score` | SEO title score | default `'0'` |
| `_yoast_wpseo_meta_description_score` | Meta description score | default `'0'` |
| `_yoast_wpseo_is_cornerstone` | Cornerstone flag | default `'false'` |
| `_yoast_wpseo_meta-robots-noindex` | noindex | default `'0'` (= post-type default); `'2'`=index, `'1'`=noindex |
| `_yoast_wpseo_meta-robots-nofollow` | nofollow | default `'0'`; `'1'`=nofollow |
| `_yoast_wpseo_meta-robots-adv` | Combined advanced robots | `noimageindex`,`noarchive`,`nosnippet` comma-joined; default `''` |
| `_yoast_wpseo_bctitle` | Breadcrumb title | |
| `_yoast_wpseo_canonical` | Canonical URL | |
| `_yoast_wpseo_redirect` | Redirect URL | |
| `_yoast_wpseo_opengraph-title` | OG title | dynamic (init) |
| `_yoast_wpseo_opengraph-description` | OG description | dynamic |
| `_yoast_wpseo_opengraph-image` | OG image | dynamic |
| `_yoast_wpseo_opengraph-image-id` | OG image ID | dynamic |
| `_yoast_wpseo_twitter-title` | Twitter title | dynamic |
| `_yoast_wpseo_twitter-description` | Twitter description | dynamic |
| `_yoast_wpseo_twitter-image` | Twitter image | dynamic |
| `_yoast_wpseo_twitter-image-id` | Twitter image ID | dynamic |
| `_yoast_wpseo_schema_page_type` | Schema page type | |
| `_yoast_wpseo_schema_article_type` | Schema article type | |
| `_yoast_wpseo_is_content_planner_banner_rendered` | Content planner banner | default `'0'` |
| `_yoast_wpseo_is_content_planner_banner_dismissed` | Content planner banner | default `'0'` |

**Premium additions (post meta):**
- `_yoast_wpseo_focuskeywords` — related/additional keyphrases (JSON array, up to 5). `multi-keyword.php:113`; `keyword-integration.php:69`.
- `_yoast_wpseo_keywordsynonyms` — synonyms. `multi-keyword.php:114`.
- `_yoast_post_redirect_info` — post redirect info. `post-watcher.php:164`.
- `_yoast_term_redirect_info` — term redirect info. `term-watcher.php`.
- `_yoast_indexnow_last_ping` — IndexNow ping timestamp. `index-now-ping.php:155`.
- `wpseo_user_schema` — premium user profile schema (user meta). `user-profile-integration.php:188`.
- `footnotes` — extension importer. `extension-importer/importer.php:176`.
- `_yst_prominent_words_version` — legacy prominent-words version (postmeta). `cleanup-integration.php:227`.
- `_yst_optimize_attribute`, `_yoast_wpseo_ai_consent`, `_yoast_wpseo_ai_generator_access_jwt`, `_yoast_wpseo_ai_generator_refresh_jwt`, `_yoast_wpseo_words_for_linking` — AI-related meta.

**Defaults / inheritance logic:** Meta registered via `register_meta('post', '_yoast_wpseo_' . $key, ...)` (lines 292-314). Values persisted only when they differ from default — `remove_meta_if_default()` (filter `update_post_metadata`, line 590) and `dont_save_meta_if_default()` (filter `add_post_metadata`, line 616) delete/skip-save when `meta_value_is_default()` is true (line 633). `WPSEO_Meta::get_value()` (line 657) returns the default from `WPSEO_Meta::$defaults` when no row exists (special-cased to `''` for `schema_page_type`/`schema_article_type`, lines 698-702). Empty meta falls back to the `wpseo_titles` template options (`title-{post_type}`, `metadesc-{post_type}`, etc.) at render time — NOT stored.

**Legacy keys NOT present in this version:** `google-plus` and `bingverify` do not exist. Webmaster-verification strings live in the `wpseo` option as `baiduverify`, `googleverify`, `msverify`, `yandexverify`, `ahrefsverify` (`class-wpseo-option-wpseo.php:46-50`).

### 2.2 wp_options groups

Registry: `WPSEO_Options::$options` (`inc/options/class-wpseo-options.php:27-35`). Each maps `option_name → class`. All extend `WPSEO_Option` (`inc/options/class-wpseo-option.php`), which saves via `update_option()`/`add_option()` with **no explicit `autoload` argument** — they inherit WordPress's default (`autoload = 'yes'`). No `autoload=` flag anywhere in the option classes.

| option_name | Class | File | Notes |
|---|---|---|---|
| `wpseo` | `WPSEO_Option_Wpseo` | `inc/options/class-wpseo-option-wpseo.php:18` | Main settings; large array |
| `wpseo_titles` | `WPSEO_Option_Titles` | `inc/options/class-wpseo-option-titles.php:20` | Title/desc templates + per-PT/tax vars |
| `wpseo_social` | `WPSEO_Option_Social` | `inc/options/class-wpseo-option-social.php:18` | Social profiles + OG/Twitter defaults |
| `wpseo_ms` | `WPSEO_Option_MS` | `inc/options/class-wpseo-option-ms.php:21` | `multisite_only=true`, `include_in_all=false` (line 35) |
| `wpseo_taxonomy_meta` | `WPSEO_Taxonomy_Meta` | `inc/options/class-wpseo-taxonomy-meta.php:18` | `include_in_all=false` (line 25) — **large serialized payload** |
| `wpseo_llmstxt` | `WPSEO_Option_Llmstxt` | `inc/options/class-wpseo-option-llmstxt.php:20` | llms.txt settings |
| `wpseo_tracking_only` | `WPSEO_Option_Tracking_Only` | `inc/options/class-wpseo-option-tracking-only.php:20` | Tracking-only option |

**`wpseo_rss` and `wpseo_permalinks` are not registered in `WPSEO_Options::$options` in this version** — RSS content moved into `wpseo_titles` (`rssbefore`, `rssafter`, lines 53-54); permalink settings are split: `wpseo_titles` holds `stripcategorybase` (line 89); `wpseo` holds `permalink_structure`, `category_base_url`, `tag_base_url`, `custom_taxonomy_slugs`, `dynamic_permalinks`, `clean_permalinks`, `clean_permalinks_extra_variables` (`class-wpseo-option-wpseo.php:76-119`). Note: `inc/class-upgrade.php:491-493,540-541` still reads and deletes the legacy `wpseo_rss`/`wpseo_permalinks` rows during upgrades, so they can persist in the DB on legacy-upgrade sites.

**Large serialized payloads:** `wpseo_taxonomy_meta` is a single option keyed `[taxonomy_name][term_id][field]` (`class-wpseo-taxonomy-meta.php:32-76`; per-term defaults `wpseo_title`, `wpseo_desc`, `wpseo_canonical`, `wpseo_bctitle`, `wpseo_noindex`, `wpseo_focuskw`, `wpseo_linkdex`, `wpseo_content_score`, `wpseo_inclusive_language_score`, `wpseo_focuskeywords` (`'[]'`), `wpseo_keywordsynonyms` (`'[]'`), `wpseo_is_cornerstone`, plus `wpseo_opengraph-*`/`wpseo_twitter-*` social fields). This is the heaviest single option.

**`wpseo_titles` variable key patterns** (`class-wpseo-option-titles.php:152-167`) generate per-post-type/taxonomy subkeys at `enrich_defaults()` (lines 290-365): `title-`, `metadesc-`, `noindex-`, `display-metabox-pt-`, `bctitle-ptarchive-`, `post_types-`, `taxonomy-`, `schema-page-type-`, `schema-article-type-`, `social-title-`, `social-description-`, `social-image-url-`, `social-image-id-`, `org-`. Concrete examples: `title-post`, `metadesc-product`, `noindex-media` (attachment), `title-tax-category`, `title-ptarchive-product`.

**Migration-status option** (separate from the registry): `yoast_migrations_free` — `Migration_Status::MIGRATION_OPTION_KEY = 'yoast_migrations_'` + `'free'` (`src/config/migration-status.php:17`). Autoloaded by default.

**Premium options (option_name):**
- `wpseo_premium` — premium option (`classes/premium-option.php:18`); defaults: `prominent_words_indexing_completed`, `workouts`, `should_redirect_after_install`, `activation_redirect_timestamp`, `dismiss_update_premium_notification`.
- `wpseo_premium_version` — upgrade-manager version (`classes/upgrade-manager.php:18`).
- `yoast_premium_as_an_addon_installer` — dependency-install state (`src/addon-installer.php:22`); holds `'started'`/`'completed'`.
- `wpseo_redirect` — main redirect storage (`premium.php:385`; `premium-redirect-service.php:254`; `redirect-handler.php:353`).
- `wpseo-premium-redirects-base` (`OPTION`), `wpseo-premium-redirects-export-plain` (`OPTION_PLAIN`), `wpseo-premium-redirects-export-regex` (`OPTION_REGEX`) — `redirect-option.php:28,35,42`.
- `wpseo-premium-redirects`, `wpseo-premium-redirects-regex` — OLD pre-3.1 options (`redirect-option.php:16,21`).
- `index_now_key` (under `wpseo` options), `enable_index_now` — `index-now-key.php:61,121`.
- `auto_update_plugins` (site option) — premium→free auto-update transfer (`src/addon-installer.php:589-594`).
- `wpseo_site_information`, `wpseo_site_information_quick` — FREE addon-manager license cache (`wordpress-seo/inc/class-addon-manager.php:22-29`).

### 2.3 Custom tables

Created by `Migration_Runner` (`src/initializers/migration-runner.php`) → `run_free_migrations()` (line 86) → `Adapter::create_schema_version_table()` then each migration's `up()`. Migrations live in `src/config/migrations/`. Triggered on every request via `initialize()` (line 73) and the `_yoast_run_migrations` action (line 76).

**`wp_yoast_indexable`** (`20171228151840_WpYoastIndexable.php` + later column migrations). Columns (final state):
- `id` — PK auto-increment (bigint, limit 20 after `20201216124002_ExpandIndexableIDColumnLengths`)
- `permalink` mediumtext NULL
- `permalink_hash` varchar(40) NULL (was 191; `20200616130143_ReplacePermalinkHashIndex`)
- `object_id` bigint(20) unsigned NULL (was int; `20201216124002`)
- `object_type` varchar(32) NOT NULL
- `object_sub_type` varchar(32) NULL
- `author_id` bigint(20) unsigned NULL
- `post_parent` bigint(20) unsigned NULL
- `title` text NULL (was varchar(191); `20200428194858_ExpandIndexableColumnLengths`)
- `description` text NULL
- `breadcrumb_title` varchar(191) NULL
- `post_status` varchar(20) NULL (was 191; `20200702141921_CreateIndexableSubpagesIndex`)
- `is_public` tinyint(1) NULL DEFAULT NULL
- `is_protected` tinyint(1) DEFAULT 0
- `has_public_posts` tinyint(1) NULL DEFAULT NULL
- `number_of_pages` int(11) unsigned NULL DEFAULT NULL
- `canonical` mediumtext NULL
- `primary_focus_keyword` varchar(191) NULL
- `primary_focus_keyword_score` int(3) NULL
- `readability_score` int(3) NULL
- `is_cornerstone` tinyint(1) DEFAULT 0
- `is_robots_noindex` tinyint(1) DEFAULT 0
- `is_robots_nofollow` tinyint(1) DEFAULT 0
- `is_robots_noarchive` tinyint(1) DEFAULT 0
- `is_robots_noimageindex` tinyint(1) DEFAULT 0
- `is_robots_nosnippet` tinyint(1) DEFAULT 0
- `twitter_title` text NULL
- `twitter_image` mediumtext NULL
- `twitter_description` mediumtext NULL
- `twitter_image_id` varchar(191) NULL
- `twitter_image_source` text NULL
- `twitter_card` varchar — declared on model (`src/models/indexable.php:63`) but NOT in the migrations reviewed; verify against a migration outside the read set if exact provenance is needed
- `open_graph_title` text NULL
- `open_graph_description` mediumtext NULL
- `open_graph_image` mediumtext NULL
- `open_graph_image_id` varchar(191) NULL
- `open_graph_image_source` text NULL
- `open_graph_image_meta` text NULL
- `link_count` int(11) NULL
- `incoming_link_count` int(11) NULL
- `prominent_words_version` int(11) unsigned NULL DEFAULT NULL
- `blog_id` bigint(20) NOT NULL DEFAULT current_blog_id (`20200420073606_AddColumnsToIndexables`)
- `language` varchar(32) NULL (`20200420073606`)
- `region` varchar(32) NULL (`20200420073606`)
- `schema_page_type` varchar(64) NULL (`20200420073606`)
- `schema_article_type` varchar(64) NULL (`20200420073606`)
- `has_ancestors` tinyint(1) DEFAULT 0 (`20200609154515_AddHasAncestorsColumn`)
- `estimated_reading_time_minutes` int NULL DEFAULT NULL (`20201202144329_AddEstimatedReadingTime`)
- `object_last_modified` datetime NULL (`20211020091404_AddObjectTimestamps`)
- `object_published_at` datetime NULL (`20211020091404_AddObjectTimestamps`)
- `inclusive_language_score` int(3) NULL (`20230417083836_AddInclusiveLanguageScore`)
- `version` int DEFAULT 1 (`20210817092415_AddVersionColumnToIndexables`)
- `seo_title_score` int(3) NULL (`20260709144332_AddSeoTitleAndMetaDescriptionScores`)
- `meta_description_score` int(3) NULL (`20260709144332_AddSeoTitleAndMetaDescriptionScores`)
- `created_at`, `updated_at` (add_timestamps)

Indexes: PRIMARY(`id`); `object_type_and_sub_type`(`object_type`,`object_sub_type`); `permalink_hash_and_object_type`(`permalink_hash`,`object_type`) (replaced old `permalink_hash` in `20200616130143`); `subpages`(`post_parent`,`object_type`,`post_status`,`object_id`) (`20200702141921`); `prominent_words`(`prominent_words_version`,`object_type`,`object_sub_type`,`post_status`) (`20200728095334`); `published_sitemap_index`(`object_published_at`,`is_robots_noindex`,`object_type`,`object_sub_type`) (`20211020091404`).

**`wp_yoast_indexable_hierarchy`** (`20191011111109_WpYoastIndexableHierarchy.php`):
- `indexable_id` int(11) unsigned PK NULL
- `ancestor_id` int(11) unsigned PK NULL
- `depth` int(11) unsigned NULL
- `blog_id` (added via AddColumnsToIndexables loop)
- Indexes: `indexable_id`, `ancestor_id`, `depth`. No timestamps.

**`wp_yoast_primary_term`** (`20171228151841_WpYoastPrimaryTerm.php`):
- `id` PK auto-increment
- `post_id` int(11) unsigned NOT NULL
- `term_id` int(11) unsigned NOT NULL
- `taxonomy` varchar(32) NOT NULL
- `blog_id` (added via AddColumnsToIndexables)
- Indexes: `post_taxonomy`(`post_id`,`taxonomy`), `post_term`(`post_id`,`term_id`); timestamps present.

**`wp_yoast_seo_links`** (`20200617122511_CreateSEOLinksTable.php` + `20260105111111_AddSeoLinksIndex.php`):
- `id` bigint(20) unsigned PK auto-increment
- `url` varchar(255)
- `post_id` bigint(20) unsigned
- `target_post_id` bigint(20) unsigned
- `type` varchar(8)
- `indexable_id` int unsigned (added)
- `target_indexable_id` int unsigned (added)
- `height` int unsigned (added)
- `width` int unsigned (added)
- `size` int unsigned (added)
- `language` varchar(32) (added)
- `region` varchar(32) (added)
- Indexes: `link_direction`(`post_id`,`type`); `indexable_link_direction`(`indexable_id`,`type`); `url_index`(`url`); `target_indexable_id_index`(`target_indexable_id`).

**`wp_yoast_expiring_store`** (`20260325155530_CreateExpiringStoreTable.php`) — NETWORK-WIDE:
- Uses `$wpdb->base_prefix` (line 72) → shared across multisite.
- `key_name` varchar(255) NOT NULL PK
- `value` text NOT NULL
- `exp` datetime NOT NULL
- Index: `exp_index`(`exp`).

**`wp_yoast_migrations`** (`lib/migrations/adapter.php:122-136`):
- Single-column tracking table: `version` varchar with UNIQUE index. Created by `Adapter::create_schema_version_table()`. Schema-version ledger; per-plugin status mirrored in `yoast_migrations_free` option.

**`wp_yoast_prominent_words`** (premium; `{wpdb_prefix}yoast_prominent_words`, e.g. `wp_yoast_prominent_words`) — `src/models/prominent-words.php` (model `Prominent_Words`):
- `id`, `stem` (varchar), `indexable_id` (int), `weight` (float) (lines 10-24).
- Auto-created by Yoast's ORM migration (model-registered). Index added by free migration `20200728095334_AddIndexesForProminentWordsOnIndexables.php`. The `indexable` table carries a `prominent_words_version` column (`src/models/indexable.php:65,141`).
- Repository: `src/repositories/prominent-words-repository.php` — `find_ids_by_stems()` uses a subquery against `Prominent_Words` (lines 91-126); `count_document_frequencies()` (175-205).
- Legacy taxonomy `yst_prominent_words` (`cleanup-integration.php:170,189`).

**`wp_yoast_seo_meta`** — model exists (`src/models/seo-meta.php`: `object_id` PK, `internal_link_count`, `incoming_link_count`) but **no creation migration present** in `src/config/migrations/`. Treat as legacy/unused in this version (superseded by `wp_yoast_seo_links` + indexable `link_count`/`incoming_link_count`).

**Read/write on page load:** `Indexable_Repository` (`src/repositories/indexable-repository.php`). `for_current_page()` (line 128) resolves the indexable for the current URL and **auto-builds it if missing** (`find_by_id_and_type(..., auto_create=true)` → `Indexable_Builder`, lines 368-382). Indexables written on every front-end load when indexing enabled. **Object cache:** home-page indexable cached in `yoast-seo-indexables` cache group for 5 minutes (`wp_cache_get/set`, lines 264-279); author-archive existence also cached (`author-archive-helper.php:129-173`). The ORM (`Yoast\WP\Lib\ORM`) additionally uses WP's per-row object cache.

### 2.4 Cron / background jobs

| Hook | Schedule | Purpose | File:line |
|---|---|---|---|
| `wpseo-reindex` | `daily` | Indexing-notification check | `src/integrations/admin/cron-integration.php:41-47` (constant `Indexing_Notification_Integration::NOTIFICATION_ID = 'wpseo-reindex'`, `src/integrations/admin/indexing-notification-integration.php:32`) |
| `wpseo_cleanup_cron` | `hourly` | Orphaned indexable cleanup | `src/integrations/cleanup-integration.php:22`, scheduled at `:270` |
| `wpseo_start_cleanup_indexables` | single (+5 min) | Triggers cleanup | `src/integrations/cleanup-integration.php:27`; fired from `inc/class-upgrade.php:961,1088,1109,1120,1143` and watchers (`indexable-post-watcher.php:268`, `attachment-watcher.php:138`, `taxonomy-change-watcher.php:155`, `author-archive-watcher.php:74`, `post-type-change-watcher.php:153`, `activation-cleanup-integration.php:65`) |
| `wpseo_indexable_index_batch` | `fifteen_minutes` | Background indexing | `src/integrations/admin/background-indexing-integration.php:212` (adds `fifteen_minutes` schedule at `:184`) |
| `wpseo_llms_txt_population` | `weekly` | llms.txt population | `src/llms-txt/application/file/llms-txt-cron-scheduler.php:16` |
| `wpseo_detect_default_seo_data` | `daily` | Default SEO data detection | `src/alerts/user-interface/default-seo-data/default-seo-data-cron-scheduler.php:19` |
| `wpseo_expiring_store_cleanup` | `weekly` | Expiring store cleanup | `src/expiring-store/user-interface/expiring-store-cleanup-integration.php:16` |
| `wpseo_permalink_structure_check` | `daily` | Permalink structure check | `src/integrations/watchers/indexable-permalink-watcher.php:264` |
| `wpseo_send_tracking_data_after_core_update` | single (+6 h) | Tracking after core update | `admin/tracking/class-tracking.php:106` |
| `wpseo_myyoast_key_rotation` | `wpseo_myyoast_90days` (custom) | MyYoast key rotation | `src/myyoast-client/user-interface/myyoast-client-integration.php:19-20,89` |

**Cleanup scope** (`wpseo_cleanup_cron`): orphaned indexables for `shop_order`, `auto-draft`, non-public post types/taxonomies/PT-archives, disabled author archives, reassigned authors, orphaned user/term indexables, and orphaned rows in `wp_yoast_indexable_hierarchy` and `wp_yoast_seo_links` (`cleanup-integration.php:118-167`). Batch limit filter `wpseo_cron_query_limit_size` default 1000 (line 241).

**Sitemap pings:** NOT a recurring cron. `ping_search_engines()` (`inc/sitemaps/class-sitemaps-admin.php:66`) fires `do_action('wpseo_hit_sitemap_index')` → `hit_sitemap_index` (`inc/sitemaps/class-sitemaps.php:103`). The old recurring `wpseo_hit_sitemap_index` scheduled hook is cleared in `inc/class-upgrade.php:653`. Pings are event-driven (on publish), not scheduled.

### 2.5 Uninstall / cleanup behavior

- **Free uninstall is a deliberate no-op.** `register_uninstall_hook( WPSEO_FILE, '__return_false' );` at `wp-seo-main.php:151`. The `Uninstall_Integration` (`src/integrations/uninstall-integration.php`) hooks `uninstall_` . `WPSEO_BASENAME` → `wpseo_uninstall()` (line 30), which only resets `wpseo['importing_completed']` to `[]` (lines 39-47). **No options, meta, or tables are deleted.** No `uninstall.php` exists in the plugin root.
- **Premium uninstall:** no `uninstall.php` in the premium root and no `register_uninstall_hook` in `wp-seo-premium.php`. Premium does NOT self-delete its options/tables. Leftover data: `wpseo_premium`, `wpseo_premium_version`, `wpseo_redirect` + `wpseo-premium-redirects*` options, `wp_yoast_prominent_words` table, `yst_prominent_words` taxonomy, and the post/term/user meta listed in §2.1/§2.3.
- **Deactivate behavior (free):** `wpseo_deactivate` action unschedules some crons (`cleanup-integration` `reset_cleanup`, `default-seo-data` `unschedule_default_seo_data_detection`, `expiring-store` `unschedule_cleanup`) — data is NOT deleted.
- **Deactivate behavior (premium):** `register_deactivation_hook` → `src/initializers/plugin.php:63-69` `wpseo_premium_deactivate()` fires `wpseo_register_capabilities_premium`, removes the `premium` capability set, and disables tracking (unless user toggled it). **No options/tables deleted.**
- **Routine cleanup (while active, premium):** `Cleanup_Integration` (`src/integrations/cleanup-integration.php`) hooks `wpseo_cleanup_tasks` and adds: `clean_orphaned_indexables_prominent_words` (deletes orphaned rows in `yoast_prominent_words` by `indexable_id`), `clean_old_prominent_word_entries` (deletes `yst_prominent_words` taxonomy + terms), `clean_old_prominent_word_version_numbers` (deletes `_yst_prominent_words_version` postmeta). Counts via `wpseo_add_cleanup_counts_to_indexable_bucket`.
- **Upgrade-time cleanup (premium):** `WPSEO_Upgrade_Manager` removes stale notifications (`wpseo-inclusive-language-notice`, `wpseo-premium-orphaned-content-{type}`, `wpseo-stale-content-notification`) and runs versioned upgrade routines (`classes/upgrade-manager.php:232,349,363`).

---

## 3. Frontend Head Pipeline

The legacy `inc/class-wpseo-frontend.php` and `inc/class-wpseo-opengraph.php` are **no longer part of the head pipeline** (no `wp_head`/`pre_get_document_title` hooks there). The active pipeline is entirely in `src/`.

### Hook pipeline table

| Order | Hook | Priority | Callback (file:line) | Output |
|---|---|---|---|---|
| 1 | `wp_head` | **1** | `call_wpseo_head()` (`front-end-integration.php:255`, def `:450`) | `wp_reset_query()` then `do_action('wpseo_head')` |
| 2 | `wpseo_head` | **-10000** | `update_outdated_permalink()` (`:265`, def `:306`) | WooCommerce permalink mismatch purge (conditional; no HTML) |
| 3 | `wpseo_head` | **-9999** | `present_head()` (`:264`, def `:468`) | **All meta tags** (loop over presenters) |
| — | `pre_get_document_title` | 15 | `filter_title()` | `<title>` value for core title machinery |
| — | `wp_title` | 15 | `filter_title()` | legacy title |

`present_head()` (`:468-493`): gets context, builds presenter list via `get_presenters()`, applies `wpseo_frontend_presentation` filter (`:478`), then `foreach` echoes each presenter's `present()` wrapped in tabs, bracketed by `Marker_Open_Presenter` / `Marker_Close_Presenter` (the `<!-- Yoast SEO plugin ... -->` comments, `:534`,`:536`).

**Presenter assembly** (`get_presenters()` `:503-538` → `get_needed_presenters()` `:547-566` → `get_presenters_for_page_type()` `:575-600` → `get_all_presenters()` `:607-620`):
- Base (always): `Title`, `Meta_Description`, `Robots` (`:81-85`)
- Indexing directives: `Canonical`, `Rel_Prev`, `Rel_Next` (`:92-96`)
- OG (if `opengraph` option true): `Open_Graph\Locale/Type/Title/Description/Url/Site_Name/Article_Publisher/Article_Author/Article_Published_Time/Article_Modified_Time/Image`, `Meta_Author` (`:103-116`)
- OG error-page subset: `Locale/Title/Site_Name` (`:123-127`)
- Twitter (if `twitter` option true AND `wpseo_output_twitter_card` filter ≠ false): `Twitter\Card/Title/Description/Image/Creator/Site` (`:134-141`)
- Slack (if `enable_enhanced_slack_sharing` true): `Slack\Enhanced_Data` (`:148-150`)
- Webmaster verification (home/static-home only): `Webmaster\Ahrefs/Baidu/Bing/Google/Pinterest/Yandex` (`:157-164`)
- Singular-only: `Meta_Author`, `Open_Graph\Article_*`, `Twitter\Creator`, `Slack\Enhanced_Data` (`:171-179`)
- Closing: `Schema` (`:186-188`)

**Filtering hooks (extension points):**
- `wpseo_frontend_presenter_classes` (`:563`) — filter class names in/out before instantiation.
- `wpseo_frontend_presenters` (`:522`) — filter presenter *instances* after instantiation.
- `wpseo_frontend_presentation` (`:478`, `:288`) — filter the presentation object.
- `filter_robots_presenter()` (`:427-441`) removes `Robots_Presenter` when core `wp_robots` is attached to `wp_head` (avoids duplicate robots).

**Removals of core WP head output** (`:267-274`): `rel_canonical`, `index_rel_link`, `start_post_rel_link`, `adjacent_posts_rel_link_wp_head`, `noindex`, `_wp_render_title_tag`, `_block_template_render_title_tag`, `gutenberg_render_title_tag` — Yoast takes over all of these.

**Presenter → output map:**
- `Title_Presenter` → `<title>` (`title-presenter.php:24`)
- `Meta_Description_Presenter` → `<meta name="description">` (`meta-description-presenter.php:17`)
- `Robots_Presenter` → `<meta name="robots">` (`robots-presenter.php:26`)
- `Canonical_Presenter` → `<link rel="canonical">` (`canonical-presenter.php:24`; suppressed when `noindex`, `:39`)
- `Rel_Prev`/`Rel_Next` → `<link rel="prev/next">` (values from `Archive_Adjacent` trait, `archive-adjacent-trait.php:44-78`)
- `Open_Graph\*` → `<meta property="og:*">` / `article:*` (e.g. `og:title` `open-graph/title-presenter.php:18`; `article:publisher` `open-graph/article-publisher-presenter.php:18`; `article:author` `:18`; `article:published_time`/`:modified_time`)
- `Twitter\*` → `<meta name="twitter:*">` (e.g. `twitter:title` `twitter/title-presenter.php:18`; `twitter:card/description/image/creator/site`)
- `Schema_Presenter` → `<script type="application/ld+json" class="yoast-schema-graph">` (`schema-presenter.php:49`)
- `Meta_Author` → `<meta name="author">`
- Webmaster presenters → verification `<meta>` tags

**Note on `fb:app_id`:** declared as a property (`indexable-presentation.php:45`, `surfaces/values/meta.php:34`) but **no `generate_open_graph_fb_app_id()` and no `fb:app_id` presenter exist** in the current `src/` pipeline — effectively dead in head output (not emitted by any presenter in the OG list).

### Title override chain

**Hook registration** — `src/integrations/front-end-integration.php:257-259` (`register_hooks()`):
- `add_filter('wp_title', [$this,'filter_title'], 15)` — legacy/compat (line 257)
- `add_filter('pre_get_document_title', [$this,'filter_title'], 15)` — block-theme/modern title (line 259)

**Callback chain** (`filter_title()` at line 282-297):
1. Builds context via `context_memoizer->for_current_page()` (line 283).
2. Instantiates `Title_Presenter` and sets `presentation`, `replace_vars`, `helpers` (lines 285-290).
3. Applies `wpseo_frontend_presentation` filter (line 288).
4. Calls `Title_Presenter::get()` → `wp_get_document_title()` (line 41).
5. Inside `Title_Presenter::get()` (`src/presenters/title-presenter.php:38-44`) it temporarily adds its own `pre_get_document_title` callback `get_title` (priority 15, line 40), calls core `wp_get_document_title()`, then removes it (line 42).
6. `get_title()` (line 66-78): `replace_vars($this->presentation->title)` → `wpseo_title` filter → `strip_all_tags` → `trim`.
7. `filter_title()` wraps in `esc_html()` and returns (line 293). A `remove_filter`/`add_filter` dance (lines 292, 294) prevents infinite recursion.

The `<title>` tag is also emitted by the `Title` presenter inside `present_head()` (see §2), so the title value is resolved once and reused. `Title_Presenter` is in `$base_presenters` (line 82) and is conditionally removed when the theme hardcodes a title tag (`maybe_remove_title_presenter()`, lines 638-650; `should_title_presenter_be_removed()` checks `get_theme_support('title-tag')`).

### Replacement vars list + perf criticism

Engine: `inc/class-wpseo-replace-vars.php` (`WPSEO_Replace_Vars`). Invoked from presenters via `Abstract_Indexable_Presenter::replace_vars()` (`src/presenters/abstract-indexable-presenter.php:77-79`) → `$this->replace_vars->replace($string, $this->presentation->source)`.

**`replace()` pipeline** (`:141-213`):
1. `wp_strip_all_tags` (`:143`); early bail if no `%%` (`:146`).
2. `preg_match_all('`%%([^%]+(%%single)?)%%?`iu', ...)` to find all tokens (`:165`) → `set_up_replacements()` calls a `retrieve_<var>()` method per token.
3. `wpseo_replacements` filter (`:176`).
4. `str_replace` of all tokens (`:180-185`) — bulk, not per-token regex.
5. `wpseo_replacements_final` filter removes unmatched tokens (`:196-201`).
6. `preg_replace` to undouble separators (`:204-207`).

**Main variables** (from `retrieve_*` methods, `:332-1633`):
- `%%title%%` (`:593`), `%%sep%%` (`:482`), `%%sitename%%` (`:515`), `%%sitedesc%%` (`:494`)
- `%%excerpt%%` (`:392`), `%%excerpt_only%%` (`:429`), `%%caption%%` (`:747`)
- `%%category%%` (`:332`), `%%category_description%%` (`:354`), `%%category_title%%` (`:1247`), `%%primary_category%%` (`:608`)
- `%%tag%%` (`:533`), `%%tag_description%%` (`:551`), `%%term_description%%` (`:560`), `%%term_title%%` (`:578`), `%%term404%%` (`:1104`), `%%term_hierarchy%%` (`:1633`)
- `%%date%%` (`:363`), `%%parent_title%%` (`:448`), `%%searchphrase%%` (`:466`), `%%archive_title%%` (`:630`)
- `%%currentdate%%` (`:841`), `%%currentday%%`, `%%currentmonth%%`, `%%currenttime%%`, `%%currentyear%%`, `%%post_year%%`/`%%post_month%%`/`%%post_day%%`
- `%%focuskw%%` (`:928`), `%%id%%` (`:955`), `%%modified%%` (`:971`), `%%name%%` (`:986`), `%%userid%%` (`:1131`)
- `%%page%%` (`:1020`), `%%pagenumber%%` (`:1040`), `%%pagetotal%%` (`:1056`), `%%pt_single%%`/`%%pt_plural%%`
- `%%author_first_name%%` (`:1182`), `%%author_last_name%%` (`:1199`), `%%user_description%%` (`:1003`)
- `%%permalink%%` (`:1216`), `%%post_content%%` (`:1229`)
- Dynamic: `%%cf_<field>%%` (custom field, `:759`), `%%ct_<tax>%%` (custom taxonomy, `:791`)

**Perf criticism (substantiated):**
- The token scan is a single `preg_match_all` + bulk `str_replace` (acceptable), **but** each distinct `%%var%%` triggers a `retrieve_*()` call that can hit the DB: `retrieve_category()` → `get_terms()` (`:336`); `retrieve_parent_title()` → `wp_get_post_parent_id()` (`:452`); `cf_*` → `get_post_meta`; `ct_*` → term meta. On a template using many vars (title + meta + OG + Twitter), the same `replace()` runs **4+ times per page** (once per presenter that calls `replace_vars`), re-scanning and re-resolving tokens each time — no caching of the resolved string across presenters.
- `replace()` is called from `Meta_Tags_Context::generate_title/description` (`context/meta-tags-context.php:209-220`) **and** again from each presenter's `get()` — double resolution of title/meta_description in some paths.

### Schema generation

**Assembly**: `Schema_Presenter` (in `$closing_presenters`) calls `presentation->schema` → `Indexable_Presentation::generate_schema()` (`indexable-presentation.php:719-721`) → `Schema_Generator::generate()` (`src/generators/schema-generator.php:48-80`).

**Piece list** (`get_graph_pieces()`, `:291-323`):
- Normal: `Article`, `WebPage`, `Main_Image`, `Breadcrumb`, `Website`, `Organization`, `Person`, `Author`, `FAQ`, `HowTo` (`:302-313`)
- Password-protected post: `WebPage`, `Website`, `Organization` only (`:293-297`)
- Filter `wpseo_schema_graph_pieces` (`:322`) to add/remove pieces.

**Gating**: each piece's `is_needed()` is checked via `wpseo_schema_needs_<identifier>` filter (`filter_graph_pieces_to_generate()`, `:90-112`).

**Per-piece filtering**: `wpseo_schema_<identifier>` and `wpseo_schema_<@type>` (`:145`, `:354`), then `wpseo_schema_graph` (`:161`) on the whole graph. Block-based pieces via `wpseo_schema_block_<type>` (`:186`). Final cleanup removes empty breadcrumbs (`finalize_graph()`, `:205-251`).

**When it runs**: same `wpseo_head` (priority -9999) block as all other meta — i.e. effectively `wp_head` priority 1. No separate scheduling.

**Caching**: none persistent. Schema is rebuilt every request (memoized only within the single request via `Presentation_Memoizer`). `Schema_Generator::generate()` also calls `register_replace_vars()` and iterates `$context->blocks` (`:53-61`) — block parsing already happened in the memoizer.

**Data sources** (mostly context/options, low DB): `Website` uses `get_bloginfo('description')` (cached) + context (`website.php:32`); `Organization`/`Person`/`Author` use options + `get_user_meta` (`person.php:233` `get_option('show_avatars')`, `author.php:58`); `Main_Image` uses `get_post_thumbnail_id` (post meta). The heaviest schema dependency is the **Breadcrumb** piece (see §5 DB triggers).

### DB query triggers per request

1. **Primary indexable query** — `Indexable_Repository::for_current_page()` (`indexable-repository.php:128-172`) → `find_by_id_and_type()` / `find_for_home_page()` / `find_for_post_type_archive()` etc. → one ORM `SELECT` against `wp_yoast_indexable`. Unavoidable per-request query.
2. **Indexable rebuild risk** — `upgrade_indexable()` (`:779-784`) calls `Indexable_Builder::build()` if `version_manager->indexable_needs_upgrade()` is true. A version mismatch turns the single read into a **write + many reads** (post meta, term meta, hierarchy) mid-request. Single biggest TTFB landmine.
3. **Breadcrumbs** (`Breadcrumbs_Generator::generate()`, `breadcrumbs-generator.php:94-197`): fires **multiple** repository queries — `find_for_home_page()` (`:100`), `find_by_id_and_type()` for front page (`:106`) and `page_for_posts` (`:114`), `find_for_post_type_archive()` (`:125`,`:133`), and `get_ancestors()` (`:142`) which itself does a hierarchy query + a `where_in` indexable query (`indexable-repository.php:461-488`). On singular/archive pages ~4–6 extra `wp_yoast_indexable` queries, **even when breadcrumbs are not displayed in the theme** (only used for the `breadcrumb` schema property).
4. **OG image** (`Open_Graph_Image_Generator`, `open-graph-image-generator.php`): `add_image_by_id()` → attachment `get_post_meta` (`image-helper.php:220` `_wp_attachment_image_alt`, plus WP attachment meta). Runs whenever OG is enabled.
5. **Twitter image** — same path via `Twitter_Image_Generator`.
6. **Replace vars** — `%%category%%`→`get_terms`, `%%parent_title%%`→`wp_get_post_parent_id`, `cf_*`→`get_post_meta`, `ct_*`→term meta (see §3). Re-resolved per presenter.
7. **Schema** — `get_post_thumbnail_id` (Main_Image), `get_user_meta` (Person/Author), `get_bloginfo` (cached).

### Caching behavior

- **Persistent (cross-request):** ONLY `find_for_home_page()` uses `wp_cache_get/set('home-page','yoast-seo-indexables', 5*MINUTE_IN_SECONDS)` (`indexable-repository.php:264-279`). Every other indexable (posts, terms, archives, authors) is **re-queried every request** unless an external object cache is installed.
- **Per-request memoization (no cross-request benefit):** `Meta_Tags_Context_Memoizer::$cache` (`meta-tags-context-memoizer.php:56`,`:88-123`) and `Presentation_Memoizer::$cache` (`presentation-memoizer.php:27`,`:49-67`). Prevent duplicate work *within* one request but nothing for TTFB under cache miss / no object cache.
- **No transients** used anywhere in the head pipeline.

### Always-on / init-time cost

- `Front_End_Conditional::is_met()` = `!is_admin()` (`src/conditionals/front-end-conditional.php:15-17`) — the integration is loaded and its `register_hooks()` runs on **every non-admin request**, regardless of whether the page needs SEO output.
- The entire Symfony DI container is compiled/booted on every frontend request to wire this integration and all its dependencies (context, memoizers, generators, presenters).
- `call_wpseo_head()` does a `wp_reset_query()` + global `$wp_query`/`$post` save-restore on every request (`:450-461`).
- `Meta_Tags_Context_Memoizer::get()` parses **all blocks** from `post_content` via `get_all_blocks_from_content()` for every post page (`:139-140`) — pure CPU, scales with post length, runs every request.

**Implications for a leaner plugin:** collapse the 4+ `replace_vars()` calls into one resolved-string cache keyed by (presentation, field); skip `Breadcrumbs_Generator` unless `breadcrumb` schema is needed AND the theme renders breadcrumbs; add an object-cache/`wp_cache` layer for per-page indexables (mirror the home-page pattern); gate the whole integration behind a more specific conditional (skip feeds/robots/health endpoints) and lazy-boot the DI container; drop the computed-but-never-emitted `fb:app_id`.

---

## 4. XML Sitemap Architecture

NOTE on layout: the sitemap code is in `inc/sitemaps/` (legacy `WPSEO_*` classes), NOT `src/sitemaps/` (that directory does not exist in this build). The REST layer is in `src/routes/`.

### Ordered rewrite flow

**Step 0 — Dynamic rewrite registration (no `flush_rewrite_rules` needed).**
- `Yoast_Dynamic_Rewrites` instantiated on `setup_theme` priority 1 — `wp-seo-main.php:413`.
- Hooks `init` priority 1 → `trigger_dynamic_rewrite_rules_hook()` fires `do_action('yoast_add_dynamic_rewrite_rules', $this)` — `inc/class-yoast-dynamic-rewrites.php:82,133,141`.
- Rules injected into the `rewrite_rules` option at read time via `filter_rewrite_rules_option()` (array_merge top) and stripped on write via `sanitize_rewrite_rules_option()` — `inc/class-yoast-dynamic-rewrites.php:152,171`. Avoids requiring a rewrite flush.

**Step 1 — The three sitemap rewrite rules** — `inc/sitemaps/class-sitemaps-router.php:38-42` (`add_rewrite_rules`):
- `sitemap_index\.xml$` → `index.php?sitemap=1` (top)
- `([^/]+?)-sitemap([0-9]+)?\.xml$` → `index.php?sitemap=$matches[1]&sitemap_n=$matches[2]` (top)
- `([a-z]+)?-?sitemap\.xsl$` → `index.php?yoast-sitemap-xsl=$matches[1]` (top)

**Step 2 — Query-var registration** — `inc/sitemaps/class-sitemaps-router.php:51-57` (`query_vars` filter): `sitemap`, `sitemap_n`, `yoast-sitemap-xsl`. (No `sitemap-subtype` var; subtype encoded in the `sitemap` value itself, e.g. `post`, `page`, `category`, `author`.)

**Step 3 — Early interception (how the full theme is bypassed).**
- `WPSEO_Sitemaps::__construct` hooks `pre_get_posts` priority 1 → `redirect()` — `inc/sitemaps/class-sitemaps.php:102,243`.
- `redirect()` runs on the main query only. If `yoast-sitemap-xsl` set → `xsl_output()` then `sitemap_close()` (exit). If `sitemap` set → redirects `sitemap_n` 0/1 to canonical, sets page, then `get_sitemap_from_cache()` or `build_sitemap()`, then `output()` then `sitemap_close()` — `inc/sitemaps/class-sitemaps.php:249-291`.
- `sitemap_close()` does `remove_all_actions('wp_footer')` + `exit()` — `inc/sitemaps/class-sitemaps.php:231-234`. Because this fires at `pre_get_posts` (before template selection), the theme never loads.
- `reduce_query_load()` (hooked `after_setup_theme` priority 99) removes all `widgets_init` actions when the URI ends in `.xml`/`.xsl` — `inc/sitemaps/class-sitemaps.php:101,143-152`.
- `redirect_canonical` filter returns `false` for sitemap/xsl requests — `inc/sitemaps/class-sitemaps-router.php:78-85`.
- `template_redirect` priority 0 in the router only handles `sitemap.xml` → `sitemap_index.xml` 301 — `inc/sitemaps/class-sitemaps-router.php:28,92-98`.

**Step 4 — robots.txt Sitemap: lines** — `src/integrations/front-end/robots-txt-integration.php`:
- `add_filter('robots_txt', [$this,'filter_robots'], 99_999)` — line 68.
- `filter_robots()` fires `do_action('Yoast\WP\SEO\register_robots_rules', $helper)` (line 108) and appends `Robots_Txt_Presenter::present()` emitting `Sitemap: <url>` lines — presenter at `src/presenters/robots-txt-presenter.php:141-148` (`handle_site_maps`).
- Sitemap index URL registered via `add_sitemap( WPSEO_Sitemaps_Router::get_base_url('sitemap_index.xml') )` — `src/integrations/front-end/robots-txt-integration.php:174` (multisite variant line 194).

**Step 5 — XSL stylesheet route.**
- Query var `yoast-sitemap-xsl` → `xsl_output()` reads physical file `css/main-sitemap.xsl` via `readfile()` with 1-year cache headers — `inc/sitemaps/class-sitemaps.php:443-468`.
- XSL URL computed in `WPSEO_Sitemaps_Renderer::get_xsl_url()` (handles home_url≠site_url cross-domain case) — `inc/sitemaps/class-sitemaps-renderer.php:340-354`; injected as `<?xml-stylesheet?>` in `get_output()` line 144-155.

**Query strategy (WP_Query vs direct $wpdb):** Providers use **direct `$wpdb` queries** for the heavy lifting, not WP_Query:
- Post type: optimized "late row lookup" `SELECT ID … LIMIT/OFFSET` subquery joined back — `inc/sitemaps/class-post-type-sitemap-provider.php:544-557` (`get_posts`); count via `$wpdb->get_var` line 348; last-modified via `$wpdb->get_results` in `WPSEO_Sitemaps::get_last_modified_gmt` (`class-sitemaps.php:547`). MySQL 8.0 path uses a `WITH` clause — lines 696-738.
- Taxonomy: `get_terms()` for the list (line 198) but **direct `$wpdb`** for per-term `MAX(post_modified_gmt)` lastmod join — lines 267-284; index lastmod via a `WP_Query` (line 151).
- Author: `get_users()` / `WP_User_Query` (no raw SQL) — `class-author-sitemap-provider.php:89-111`.
- Image parser uses `WP_Query` only for gallery attachments — `class-sitemap-image-parser.php:517-537`.

**Pagination / 1000 entries:** `get_entries_per_page()` applies filter `wpseo_sitemap_entries_per_page` default **1000** — `inc/sitemaps/class-sitemaps.php:597-609`. Providers slice by `sitemap_n` × 1000 (`get_sitemap_links` offset math, e.g. post-type lines 166-192). Invalid page → `OutOfBoundsException` → 404.

**Hooks to alter entries:**
- Per-entry: `wpseo_sitemap_entry` (post/term/user) — `class-post-type-sitemap-provider.php:224`, `class-taxonomy-sitemap-provider.php:295`, `class-author-sitemap-provider.php:198`.
- Index: `wpseo_sitemap_index_links` (`class-sitemaps.php:422`), `wpseo_sitemap_index` (renderer line 81).
- urlset: `wpseo_sitemap_urlset`, `wpseo_sitemap_{$type}_urlset`, `wpseo_sitemap_{$type}_content` (first page only), `wpseo_sitemap_url` — `class-sitemaps-renderer.php:108,115,129,254`.
- Images: `wpseo_xml_sitemap_include_images` (default true, gates image tags), `wpseo_sitemap_urlimages`, `wpseo_sitemap_urlimages_term`, `wpseo_sitemap_urlimages_front_page`, `wpseo_xml_sitemap_img_src`, `wpseo_xml_sitemap_img` — `class-sitemap-image-parser.php:47,97,128,188,354,367`. Image tags emitted as `<image:image><image:loc>` in `sitemap_url()` — `class-sitemaps-renderer.php:234-243`.

### Cache lifecycle

- **Enable gate:** `WPSEO_Sitemaps_Cache::is_enabled()` returns `apply_filters('wpseo_enable_xml_sitemap_transient_caching', false)` — `inc/sitemaps/class-sitemaps-cache.php:81-89`. **Default is `false`** → in a stock install the transient cache is OFF and sitemaps are rebuilt on every hit (notable audit finding; the whole cache layer is opt-in).
- **Storage key:** prefix `yst_sm_` (`STORAGE_KEY_PREFIX`), format `yst_sm_{type}_{page}:{global_validator}_{type_validator}` — `inc/sitemaps/class-sitemaps-cache-validator.php:20,50-72`. Validators are base61-encoded `microtime() % DAY_IN_SECONDS` stored in options `wpseo_sitemap_cache_validator_global` and `wpseo_sitemap_{type}_cache_validator` — lines 27,34,262-283. Key length capped at 45 chars (truncates long post-type names mid-string) — lines 90-127.
- **Payload object:** `WPSEO_Sitemap_Cache_Data` (implements `Serializable` + `__serialize/__unserialize`); holds `sitemap` string + `status` (`OK`/`ERROR`/`UNKNOWN`). Empty sitemap → ERROR — `inc/sitemaps/class-sitemap-cache-data.php:11-107`. Read path unserializes `C:`-format with `allowed_classes=>false` (security) — lines 134-137, 211-215.
- **Expiry:** `set_transient($key, $data, DAY_IN_SECONDS)` → **24h** — `inc/sitemaps/class-sitemaps-cache.php:173`. (No 12h tier exists in this build; the only expiry is 1 day.)
- **Read path:** `WPSEO_Sitemaps::get_sitemap_from_cache()` → `cache->get_sitemap_data()` → if empty, `refresh_sitemap_cache()` (build + store) — `inc/sitemaps/class-sitemaps.php:301-345`.
- **Invalidation (event-driven, queued to `shutdown`):**
  - `deleted_term_relationships` → `invalidate` (line 50)
  - `update_option` → `clear_on_option_update` (line 52); `wpseo_titles` and `wpseo` registered via `register_clear_on_option_update('', …)` → clear ALL — `class-sitemaps-admin.php:27-28`.
  - `edited_terms` / `clean_term_cache` / `clean_object_term_cache` → `invalidate_helper` (lines 54-56)
  - `user_register` / `delete_user` → `invalidate_author` (lines 58-59)
  - `save_post` in post-type provider → `invalidate_post` (line 244-249)
  - All queued invalidations flushed in `clear_queued()` on `shutdown` (line 61, 302-318) → `WPSEO_Sitemaps_Cache_Validator::invalidate_storage()` regenerates the validator (orphans old transients) and, when not using an external object cache, `DELETE FROM options WHERE option_name LIKE '_transient_yst_sm_%'` — `class-sitemaps-cache-validator.php:138-203`.
- **Last-modified based invalidation:** not a time-based cache key; the sitemap `<lastmod>` is derived from `get_last_modified_gmt()` (max `post_modified_gmt` per type) — `class-sitemaps.php:504-590`. Cache freshness governed by the validator option + 24h transient TTL, not by content mtime.

### Ping flow

- **Direct search-engine ping is REMOVED in this version.** `WPSEO_Sitemaps_Admin::ping_search_engines()` is `@deprecated 22.0` and now a no-op — `inc/sitemaps/class-sitemaps-admin.php:66-68`. No `google.com/ping`/`bing.com/ping` URLs exist anywhere in the free codebase (grep confirms zero matches).
- **What remains — cache warming on publish:** `transition_post_status` → `status_transition` (only when `$new_status === 'publish'`) — `class-sitemaps-admin.php:24,42-56`. During bulk import (`WP_IMPORTING`) it collects post types and on `admin_footer` `status_transition_bulk_finished()` fires `do_action('wpseo_hit_sitemap_index')` **only if `WP_CACHE` is true** — lines 92-124.
- `wpseo_hit_sitemap_index` → `WPSEO_Sitemaps::hit_sitemap_index()` → `wp_remote_get( get_base_url('sitemap_index.xml') )` (warms the cache, does NOT notify engines) — `inc/sitemaps/class-sitemaps.php:103,486-492`.
- **Cron regeneration:** the scheduled cron hook `wpseo_hit_sitemap_index` is **cleared** in `inc/class-upgrade.php:653` (`wp_clear_scheduled_hook`), i.e. the old daily cron that pre-generated sitemaps was removed. No sitemap-regeneration cron in this build; regeneration is purely on-demand (cache miss / validator change).

---

## 5. REST API & Gutenberg Integration

Namespace `yoast/v1` = `Main::API_V1_NAMESPACE` (`src/main.php:32`). All routes implement `Route_Interface` (`src/routes/route-interface.php`) and are gated by conditionals; most require `manage_options`/edit capability via `permission_callback`.

### yoast/v1 route table

| Route (yoast/v1/…) | File:line | Purpose | Payload |
|---|---|---|---|
| `get_head` | `src/routes/indexables-head-route.php:22,73` | Head (HTML+JSON) for any URL | GET `url`; returns `Indexable_Head_Action` html/json, `permission_callback=>__return_true` |
| `yoast_head` / `yoast_head_json` (REST fields) | `src/routes/yoast-head-rest-field.php:24,31,194-198` | Per-object head metadata injected into post/term/tag/user/type REST responses | `register_rest_field` get_callback → `head_action->for_post/term/author/post_type_archive`; returns `$head->html` or `$head->json` |
| `workouts/...` | `src/routes/workouts-route.php:65` | Workout/configuration steps | workout data |
| `semrush/authentication`, `semrush/country-code`, `semrush/related_keyphrases` | `src/routes/semrush-route.php:125,139,156` | Semrush keyphrase auth + related keyphrases (free, proxied to Semrush API; cached in transient `wpseo_semrush_related_keyphrases_%s_%s`) | keyphrase + database → suggestions |
| `wincher/...` (authorize, authenticate, track, tracked, untrack, check-limit, upgrade-campaign) | `src/routes/wincher-route.php:132-206` | Wincher rank-tracking integration | keyphrase tracking |
| `indexing/posts`, `indexing/terms`, `indexing/post-type-archives`, `indexing/general`, `indexing/prepare`, `indexing/indexables-complete`, `indexing/complete`, `indexing/post-links`, `indexing/term-links` | `src/routes/indexing-route.php:288-312` | Builds/completes the indexables table (feeds analysis) | batch indexable objects |
| `first-time-configuration/...` (site-representation, social-profiles, check-capability, enable-tracking, save/get state) | `src/routes/first-time-configuration-route.php:125-190` | Setup wizard | config values |
| `importing/...` | `src/routes/importing-route.php:64` | Import from other SEO plugins | import status |
| `supported-features` | `src/routes/supported-features-route.php:44` | Feature flags for editor | feature list |
| `meta-search` | `src/routes/meta-search-route.php:38` | Search post meta | results |
| `integrations/set-active` | `src/routes/integrations-route.php:69` | Toggle 3rd-party integrations | bool |
| `alert-dismissal` | `src/routes/alert-dismissal-route.php:73` | Dismiss notices | ok |
| `ai_content_planner/get_suggestions`, `ai_content_planner/get_outline` | `src/ai/content-planner/user-interface/*.php` | AI content suggestions (free AI) | suggestions |
| `ai_generator/get_suggestions`, `ai_generator/get_usage`, `ai_generator/bust_subscription_cache` | `src/ai/generator/user-interface/*.php` | AI text generation | generated text |
| `schema-aggregator/get-schema(/<post_type>[/<page>])`, `schema-aggregator/get-schema-xml` | `src/schema-aggregator/user-interface/*.php:134-135,68` | Schema graph for a type | schema JSON/XML |
| `llms-txt/available-posts` | `src/llms-txt/user-interface/available-posts-route.php:73` | llms.txt population | post list |
| `myyoast/...` (management) | `src/myyoast-client/user-interface/management-route.php:145-192` | MyYoast connection | tokens |
| `bulk-editor/posts`, `bulk-editor/posts-content`, `bulk-editor/scores` | `src/bulk-editor/user-interface/*.php` | Bulk editor | posts/scores |
| `tasks/get-tasks`, `tasks/complete-task` | `src/task-list/user-interface/*.php` | Task list | tasks |
| `introductions/seen`, `introductions/wistia-embed-permission` | `src/introductions/user-interface/*.php` | Intro tours | ok |
| `tracking/action` | `src/tracking/user-interface/action-tracking-route.php:83` | Usage tracking | ok |
| `opt-in/seen` | `src/general/user-interface/opt-in-route.php:88` | Tracking opt-in | ok |
| `content-type-visibility/dismiss-post-type`, `.../dismiss-taxonomy` | `src/content-type-visibility/user-interface/*.php:85-86` | Hide CTV notices | ok |
| `dashboard/...` (scores, time-based-seo-metrics, site-kit-*) | `src/dashboard/user-interface/*.php` | Dashboard widgets | metrics |
| `ai/consent`, `ai/free-sparks`, `ai/authorization/callback`, `ai/authorization/refresh` | `src/ai/**/user-interface/*.php` | AI auth/consent | tokens |

**Premium routes (namespace `yoast/v1`):**
- `link_suggestions` — `src/routes/link-suggestions-route.php:24,76`
- `prominent_words/get_content` (POST, line 129), `/save` (line 174), `/complete` (line 139) — `src/routes/prominent-words-route.php`; permission `edit_posts` (line 224)
- `ai/optimize` (POST) — `src/ai/optimize/optimizer/user-interface/ai-optimize-route.php:33,134-136`
- `ai/summarize` — `src/ai/summarize/user-interface/ai-summarize-route.php:34,112`
- `workouts/noindex`, `/remove_redirect`, `/link_suggestions`, `/last_updated`, `/cornerstone_data`, `/enable_cornerstone`, `/most_linked` — `src/routes/workouts-route.php:34-76`
- Premium redirect endpoints (create/undo) — `classes/premium-redirect-endpoint.php` (`register_rest_route` at lines 87,104,130,158,222,238), `classes/redirect-undo-endpoint.php:47`

### yoast_head / yoast_head_json REST fields

`src/routes/yoast-head-rest-field.php` registers `yoast_head` and `yoast_head_json` via `register_rest_field` (lines 24,31,194-198). The get_callback dispatches to `head_action->for_post/term/author/post_type_archive` and returns `$head->html` or `$head->json`. These power the editor's head/social preview.

### register_meta with auth_callback round-trip

`inc/class-wpseo-meta.php` registers every `wpseo_*` meta key twice (lines 292-314):
- Once for all post types with `sanitize_callback` only (REST disabled).
- Once for the `post` subtype with `show_in_rest => true`, `type => string`, `single`, `sanitize_callback`, and **`auth_callback` = `current_user_can('edit_post', $object_id)`** (lines 300-313). This is the REST meta write gate.
- Read access for non-editors is separately restricted via `rest_prepare_post` filter `hide_meta_from_unauthorized_rest_response` (line 338) because `auth_callback` only guards writes.
- Defaults skipped on save via `remove_meta_if_default` / `dont_save_meta_if_default` (lines 342-343).

### Client-side analysis (Web Worker)

The content analysis runs **client-side** in the `yoast-seo-analysis-worker` Web Worker (asset URL localized in `wpseoScriptData.analysis.worker`, `admin/metabox/class-metabox.php:931`). It executes readability, keyphrase-density and assessment logic. The server does NOT compute scores; it only supplies indexable/head data, replace vars, shortcode list, and existing keyword usage.

### used-keywords via admin-ajax

**Used-keywords is NOT a REST route** — it is an **admin-ajax** call: `wp_ajax_get_focus_keyword_usage_and_post_types` (`admin/ajax.php:316-345`), nonce `wpseo-keyword-usage-and-post-types` (localized at `class-metabox.php:914`). Returns `WPSEO_Meta::keyword_usage()` (posts already using the focus keyphrase).

### What's premium-only

- **Link suggestions** and **prominent words** are **PREMIUM-ONLY** in this free build: the free code only carries the `enable_link_suggestions` option flag + upsell URL (`admin/views/class-yoast-feature-toggles.php:123-128`) and the `prominent_words_version` column on the indexables model (`src/models/indexable.php:141`); the actual suggestion/prominent-words REST endpoints live in the premium add-on.
- Premium AI Optimize / AI Summarize routes throw `Payment_Required_Exception` (return `missingLicenses`) when unlicensed.

### Editor (Gutenberg) data flow

**Registration model:** Yoast registers a classic metabox via `add_meta_box('wpseo_meta', …)` on `add_meta_boxes` (`admin/metabox/class-metabox.php:76,127`) AND enqueues the block-editor bundle. The block editor mounts the sidebar through JS (`PluginSidebar`/`registerPlugin` in the `post-edit` script). The `Block_Editor_Integration` (`src/integrations/blocks/block-editor-integration.php`) hooks `enqueue_block_assets` → enqueues the `block-editor` stylesheet (line 47-57) so styles load inside the iframe.

**Assets enqueued on the editor screen** (`admin/metabox/class-metabox.php:857-936`): styles `metabox-css`, `scoring` (if readability on), `monorepo`, `ai-generator`, `ai-fix-assessments`, `admin-css`, (classic: `featured-image`); script `post-edit` (or `post-edit-classic`); emoji detection script removed (line 877). `class-metabox-editor.php:25` hooks `enqueue_block_editor_assets` → `inside-editor` style.

**Localized data passed to the client** (`wpseoScriptData`, `class-metabox.php:936`): `metabox` (tab config), `isBlockEditor`, `postId`, `postStatus`, `postType`, `isPage`, `isFrontPage`; `analysis.plugins` → `replaceVars`, `shortcodes` (+ nonce `wpseo-filter-shortcodes`); `analysis.worker` → worker URL, dependencies, `keywords_assessment_url` (`yoast-seo-used-keywords-assessment` JS), `log_level`; `usedKeywordsNonce` (`wpseo-keyword-usage-and-post-types`); site info from `Website_Information_Repository::get_post_site_information()` (lines 927-930).

**Text diagram of the editor data flow:**
```
[Gutenberg editor]
   │  loads `post-edit` bundle (React + PluginSidebar)
   ▼
[Analysis Web Worker]  ◄── localized: replaceVars, shortcodes, worker URL,
   │  (client-side readability / keyphrase-density / assessments)      keywords_assessment_url, usedKeywordsNonce
   │        │
   │        ├── used-keywords check ──► wp_ajax_get_focus_keyword_usage_and_post_types
   │        │                              (nonce wpseo-keyword-usage-and-post-types) ─► WPSEO_Meta::keyword_usage()
   │        │
   │        └── head/social preview ──► GET yoast/v1/get_head?url=…  (Indexables_Head_Route)
   │                                     and reads `yoast_head`/`yoast_head_json` REST fields
   │                                     on the post object (Yoast_Head_REST_Field → Indexable_Head_Action)
   ▼
[User edits SEO meta in sidebar]
   ▼  autosave / save  ──► REST POST /wp/v2/{post_type}/{id}  (meta `wpseo_*` keys)
                                          │  write guarded by register_meta auth_callback:
                                          │  current_user_can('edit_post')  (class-wpseo-meta.php:309)
                                          ▼
                                   indexables updated via yoast/v1/indexing/* (feeds future analysis)
```
Server endpoints the editor calls: `wp/v2` (core, for meta save), `yoast/v1/get_head`, the `yoast_head`/`yoast_head_json` fields, `yoast/v1/semrush/related_keyphrases`, `yoast/v1/indexing/*`, and the `admin-ajax` used-keywords endpoint. Link suggestions + prominent words are premium and absent from free.

---

## 6. Premium Feature Mechanics

### Redirects (stored in OPTIONS, not a table)

`/home/web-dev-3/Local Sites/wordpress-test-site/app/public/wp-content/plugins/wordpress-seo-premium/classes/redirect/redirect-option.php`
- `OPTION = 'wpseo-premium-redirects-base'` (line 28) — the live store
- `OPTION_PLAIN = 'wpseo-premium-redirects-export-plain'` (line 35)
- `OPTION_REGEX = 'wpseo-premium-redirects-export-regex'` (line 42)
- Legacy: `wpseo-premium-redirects` / `wpseo-premium-redirects-regex` (lines 16–21)
- Saved via `update_option( self::OPTION, $redirects, false )` (line 203) — **autoload = `false`**, so it is a per-request `get_option()` read, not autoloaded.
- Each row shape: `['origin'=>, 'url'=>, 'type'=>, 'format'=>]` (lines 290–297). `format` is `plain` or `regex`.
- Main redirect option `wpseo_redirect` (`premium.php:385`; `premium-redirect-service.php:254`; `redirect-handler.php:353`).

**Redirect types (301/302/307/410/451):** `classes/redirect/redirect-types.php` — constants `PERMANENT=301`, `FOUND=302`, `TEMPORARY=307`, `DELETED=410`, `UNAVAILABLE=451` (lines 13–17); `get()` returns the 5 labels (lines 24–31); filter `Yoast\WP\SEO\redirect_types` (line 42). 410/451 have no target — handled specially.

**Regex support:** `src/initializers/redirect-handler.php` — `handle_regex_redirects()` (lines 207–215) loads the regex option and loops every rule; `match_regex_redirect()` (lines 225–251): backticks used as PCRE delimiter, backticks escaped (line 232), `@preg_match` suppressed (line 237), `$0–$9` in target replaced via `format_regex_redirect_url()` (lines 136–144).

**Load-on-init matching flow + hook priority:** `redirect-handler.php` (class `Redirect_Handler implements Initializer_Interface`, gated by `Front_End_Conditional`):
- `initialize()` (lines 66–82): if network-activated → `add_action('plugins_loaded', [$this,'handle_redirects'], 16)`; otherwise `handle_redirects()` runs immediately when the initializer boots (container initializes initializers on `init`/`rest_api_init`).
- `handle_redirects()` (lines 668–679): `set_request_url()` → `handle_normal_redirects()` (plain) → if not yet redirected, `handle_regex_redirects()`.
- Plain match: `get_redirects()` (lines 260–272) — **static per-request cache** (no persistent/cross-request cache). `find_url()` → `search()` → `find_url_fallback()` trailing/non-trailing slash variants (lines 405–456). `do_redirect()` → `wp_redirect($loc, $status, 'Yoast SEO Premium')` (line 637).
- 410/451: deferred to `add_action('wp', [$this,'do_410'/'do_451'])` (lines 615, 619) + `template_include` filter (lines 568–577).
- `redirect_canonical_fix` filter at **priority 1** (`premium.php:173`) intercepts WP canonical redirects.
- **Regex caching:** none beyond the per-request static cache in `get_redirects()`. Every request re-runs `preg_match` over all regex rules.

**Pagination / admin:** Admin list table `WPSEO_Redirect_Table extends WP_List_Table` (`classes/redirect/redirect-table.php:15`); `per_page = get_items_per_page('redirects_per_page', 25)` (line 180). This is admin-list pagination, not runtime matching. Admin page `WPSEO_Redirect_Page` (`classes/redirect/redirect-page.php`); submenu registered in `premium.php:336–344` (cap `wpseo_manage_redirects`); enqueues `wp-seo-premium-admin-redirects` + `redirects` style (lines 121, 137).

**REST endpoints:** `classes/premium-redirect-endpoint.php` (`register_rest_route` at lines 87, 104, 130, 158, 222, 238) and `classes/redirect-undo-endpoint.php:47` — namespace `yoast/v1`.

**Watchers (auto-create redirects on slug change):** `classes/post-watcher.php`, `classes/term-watcher.php`, wired in `premium.php:164–167` on `admin_init` + `rest_api_init`.

**Admin-bar "Create Redirect" on 404:** `premium.php:255–283` (`admin_bar_menu` priority 96).

**File export (Apache/Nginx):** `redirect-file-util.php`; bypassed when `disable_php_redirect` option set (`redirect-handler.php:320–344`).

### Multiple keyphrases storage

- Primary keyphrase: free meta `_yoast_wpseo_focuskw`.
- **Related/additional keyphrases: post meta `_yoast_wpseo_focuskeywords`** (JSON array of `{keyword, ...}`). `src/integrations/admin/keyword-integration.php:69` (meta_query key in usage check).
- `WPSEO_Multi_Keyword` (`classes/multi-keyword.php`) injects hidden metabox fields `focuskeywords` + `keywordsynonyms` (lines 36–63) and taxonomy fields (72–103); `register_taxonomy_metafields()` sets `wpseo_focuskeywords`/`wpseo_keywordsynonyms` term-meta defaults (112–117).
- The "5 keyphrase" cap is enforced client-side in `premium-metabox` JS (`classes/premium-metabox.php:113` enqueues `premium-metabox`).
- **Sync with free's analysis:** `keyword-integration.php` hooks two filters used by the free analyzer's duplicate-keyword detection:
  - `wpseo_posts_for_focus_keyword` → `add_posts_for_focus_keyword()` (lines 26, 65–88) — `WP_Query` over `_yoast_wpseo_focuskeywords` meta with `LIKE '"keyword":"…"'`, `posts_per_page => 2`.
  - `wpseo_posts_for_related_keywords` → `add_posts_for_related_keywords()` (lines 27, 38–54) — decodes the JSON meta and runs `WPSEO_Meta::keyword_usage()` per related keyword.

### Prominent words + internal linking

**Storage — custom table `wp_yoast_prominent_words`** (`src/models/prominent-words.php`, model `Prominent_Words`): columns `id`, `stem` (varchar), `indexable_id` (int), `weight` (float) (lines 10–24). Auto-created by Yoast's ORM migration; index added by free migration `20200728095334_AddIndexesForProminentWordsOnIndexables.php`. The `indexable` table carries a `prominent_words_version` column (`src/models/indexable.php:65,141`). Repository `src/repositories/prominent-words-repository.php` — `find_ids_by_stems()` uses a subquery against `Prominent_Words` (lines 91–126); `count_document_frequencies()` (175–205).

**Indexation (REST + cron):** `src/routes/prominent-words-route.php` — `yoast/v1/prominent_words/get_content` (POST, line 129), `/save` (line 174), `/complete` (line 139). Permission: `edit_posts` (line 224). Actions: `src/actions/prominent-words/{Content_Action,Save_Action,Complete_Action}.php`. Indexing UI: `src/integrations/admin/prominent-words/indexing-integration.php` enqueues `yoast-premium-prominent-words-indexation` (line 207). Background throttle: free `src/integrations/admin/background-indexing-integration.php:135` filters `wpseo_prominent_words_indexation_limit`. Watcher: `src/integrations/watchers/prominent-words-watcher.php` deletes rows on `wpseo_indexable_deleted` (line 39). Versioning/unindexed query: `classes/premium-prominent-words-versioning.php`, `classes/premium-prominent-words-unindexed-post-query.php`.

**Link-suggestions endpoints + TF-IDF:** Endpoint `src/routes/link-suggestions-route.php:76` (`yoast/v1/link_suggestions`). Metabox `classes/metabox-link-suggestions.php:99` (`add_meta_box`). Computation (TF-IDF cosine similarity): `src/actions/link-suggestions-action.php` — `compute_tf_idf_score = term_frequency * (1 / doc_frequency)` (`src/helpers/prominent-words-helper.php:37–42`); `compute_raw_score()` (lines 263–284): sum of products of request vs candidate tf-idf; `normalize_score()` (441–450): divide by product of vector lengths; `compute_vector_length()` (helper 51–65) = Euclidean norm of tf-idf weights. `BATCH_SIZE = 1000` (line 22); `retrieve_suggested_indexable_ids()` loops batches (386–430), keeps top-`$limit` via `get_top_suggestions()` (461–476).

### Other premium features

- **Social previews:** `classes/social-previews.php` — enqueues `yoast-social-metadata-previews` on `admin_enqueue_scripts` (lines 21, 32). (Free already ships social previews; premium re-enqueues the script.)
- **News / Video SEO:** **Not present** in premium (grep for `video_seo`/`news_seo` returned nothing). Separate paid add-ons; premium only references them in the HelpScout beacon config (`premium.php:404–406`).
- **Stale cornerstone content:** `classes/premium-stale-cornerstone-content-filter.php` — registered in `premium.php:109–111` only if `enable_cornerstone_content` is on; 6-month threshold (lines 127–128); post-list filter + cached count.
- **Orphaned content:** `classes/premium-orphaned-content-support.php`, `premium-orphaned-post-filter.php`, `premium-orphaned-post-query.php`, `premium-orphaned-content-utils.php` — post-list filter for unlinked content.
- **Premium upsells embedded in the FREE base:**
  - `wordpress-seo/src/presenters/admin/premium-badge-presenter.php` — `yoast-premium-badge` markup.
  - `wordpress-seo/src/presenters/admin/sidebar-presenter.php` — sidebar CTA.
  - `wordpress-seo/src/integrations/admin/menu-badge-integration.php` — injects "Premium" text into submenu + admin bar via inline style on `admin-global` (no screen condition).
  - `wordpress-seo/admin/class-admin.php:266` — "Get Premium" plugin-row link; `:254–260` "Activate your subscription".
  - `workouts-integration.php`, `installation-success-integration.php`, `first-time-configuration-integration.php`, `brand-insights-page.php`, `redirects-page-integration.php` (free) all carry premium CTAs.
- **MyYoast license / subscription API (in FREE base):**
  - `wordpress-seo/inc/class-my-yoast-api-request.php:57` → `https://my.yoast.com/api/`; `wp_remote_request` at line 110; parses `subscriptions` (line 156).
  - `wordpress-seo/inc/class-addon-manager.php` — `get_myyoast_site_information()` (line 278), `get_subscriptions()` (156), caches subscription data.
  - Premium `classes/product-premium.php:27` `EDD_STORE_URL = 'http://my.yoast.com'`; `:56` license URL `https://my.yoast.com/licenses/`.
- **Google Site Kit integration (FREE):** `wordpress-seo/src/dashboard/infrastructure/site-kit.php`, `site-kit-search-console-api-call.php`, `site-kit-search-console-adapter.php`, `site-kit-analytics-4-adapter.php`, `site-kit-consent-repository.php` (Search Console + GA4 pulls).
- **Semrush (FREE):** `wordpress-seo/src/config/semrush-client.php` (oauth at `oauth.semrush.com`); options `semrush_tokens`, `semrush_integration_active`; admin-bar submenu `class-wpseo-admin-bar-menu.php:476`.
- **Zapier:** not found in 27.8 (no matches) — appears removed/absent in this version.
- **HelpScout beacon:** free `src/integrations/admin/helpscout-beacon.php:178` enqueues `help-scout-beacon` only on Yoast admin pages (`is_beacon_page()`); premium adds product/page IDs in `premium.php:404–406`.
- **Tracking:** free `WPSEO_Tracking` to `https://tracking.yoast.com/stats` (`class-admin.php:92`); premium `install()` force-enables tracking (`premium.php:73–78`).
- **IndexNow ping:** `src/integrations/index-now-ping.php:155` pings IndexNow on post publish/update; stores `_yoast_indexnow_last_ping` post meta; key in option `index_now_key` gated by `enable_index_now` (`index-now-key.php:61,121`).
- **Extension importer (competitor SEO → Yoast):** `src/integrations/admin/extension-importer/importer.php:176` imports footnotes/Gutenberg/media; writes `footnotes` post meta.
- **Premium blocks:** `src/integrations/blocks/estimated-reading-time-block.php`, `related-links-block.php`.
- **Inclusive language (list column + filter):** `src/integrations/admin/inclusive-language-*.php`.
- **Wincher rank tracking (premium enhancement):** `src/integrations/third-party/wincher-keyphrases.php:33-34,45-58` adds additional keyphrases to Wincher tracking via `wpseo_wincher_keyphrases_from_post` / `wpseo_wincher_all_keyphrases` filters (reads `_yoast_wpseo_focuskeywords`).
- **Third-party integrations:** `src/integrations/third-party/{algolia,edd,elementor-premium,elementor-preview,mastodon}.php`.
- **Premium schema / OG:** `src/integrations/organization-schema-integration.php`, `publishing-principles-schema-integration.php`, `src/integrations/opengraph-*.php` (author/date/post-type/term archive OG).
- **AI Optimize / AI Summarize:** REST `ai/optimize` (POST) and `ai/summarize`; license-gated via `Payment_Required_Exception` + `missingLicenses` (`ai-optimize-route.php:134-136`; `ai-summarize-route.php:112`); metabox flag `isAiFeatureEnabled => WPSEO_Options::get('enable_ai_generator')` (`premium-metabox.php:158`).

### Licensing (MyYoast connection)

- **License authority lives in the FREE plugin:** `WPSEO_Addon_Manager` (`wordpress-seo/inc/class-addon-manager.php`). Premium only *queries* it. Slug constant `WPSEO_Addon_Manager::PREMIUM_SLUG = 'yoast-seo-wordpress-premium'` (`inc/class-addon-manager.php:43`).
- **Endpoint definition (premium side):** `WPSEO_Product_Premium` (`classes/product-premium.php`) extends free `Yoast_Product`; sets `EDD_STORE_URL = 'http://my.yoast.com'`, API path `edd-sl-api`, product name `'Yoast SEO Premium'`, license page `admin.php?page=wpseo_licenses#top#licenses`, extension URL `https://my.yoast.com/licenses/`.
- **Where state is stored:** `WPSEO_Addon_Manager` fetches subscriptions from MyYoast and caches them in transients **`wpseo_site_information`** and **`wpseo_site_information_quick`** (`inc/class-addon-manager.php:22-29`); the underlying site/subscription data is part of the free `wpseo` option set. Premium does NOT keep its own license copy.
- **Validity check used by premium:** `WPSEO_Addon_Manager::has_valid_subscription( WPSEO_Addon_Manager::PREMIUM_SLUG )` — e.g. `src/integrations/admin/plugin-links-integration.php:60` (shows "activate subscription" link only when invalid), and the deprecated AI generator passed `premiumSubscription`/`wooCommerceSubscription` flags to JS.
- **What gates on license:**
  - **WP-CLI premium commands:** `WPSEO_CLI_Premium_Requirement::enforce()` → `YoastSEO()->helpers->product->is_premium()`; otherwise `WP_CLI::error('This command can only be run with an active Yoast SEO Premium license.')` (`cli/cli-premium-requirement.php:19-28`).
  - **AI Optimize / AI Summarize REST routes:** action layer throws `Payment_Required_Exception`; route returns `missingLicenses`.
  - **AI feature flag in metabox:** `isAiFeatureEnabled => WPSEO_Options::get('enable_ai_generator')`.
  - **Free-plugin upsell ads:** suppressed automatically because `is_premium()` returns true in the free plugin (the "ad-free experience" is a side-effect of the premium indicator being set to yes — `premium.php:356-366` `change_premium_indicator`/`change_premium_indicator_text`).
- **24/7 support:** HelpScout beacon — `filter_helpscout_beacon()` adds `WPSEO_Addon_Manager::PREMIUM_SLUG` to `products` and a dedicated beacon page id for `wpseo_redirects` (`premium.php:404-409`).
- **Dependency-install state option:** `yoast_premium_as_an_addon_installer` (`src/addon-installer.php:22`) holds `'started'`/`'completed'`; deleted when free is out of date (`validate_installation_status`, `src/addon-installer.php:294-298`).
- **Free auto-installer behavior:** `Addon_Installer` (`src/addon-installer.php`) silently downloads+installs+activates the FREE `wordpress-seo` if missing/too old (minimum free `27.8`, `MINIMUM_YOAST_SEO_VERSION = '27.8'`, `:47`; `is_yoast_seo_up_to_date()` compares `WPSEO_VERSION` against `27.8-RC0`, `:285-287`). Uses a lock transient (`yoast_premium_addon_install_lock`, 5 min) and a 24h cooldown (`yoast_premium_addon_install_cooldown`) on failure (`:321-398`). If it still can't install, an admin/network notice is shown on Yoast pages, plugins.php, update-core.php, options-permalink.php (`show_install_yoast_seo_notification`, `:165-205`). Premium itself does NOT fatal — it simply never instantiates `Main` because `is_yoast_seo_up_to_date()` is false.

---

## 7. Bloat Inventory

### Admin enqueues (with screen conditions)

**FREE** (`wordpress-seo/`):

| Handle / file:line | Screen condition |
|---|---|
| `admin-global` (script+style) — `admin/class-admin.php:279,290` | **ALL admin pages** (no `get_current_screen` gate) |
| `dismissible` style — `admin/class-admin-init.php:60` | all admin (`admin_enqueue_scripts`) |
| `notifications` style — `admin/class-yoast-notifications.php:108` | all admin |
| `settings` + `admin-css` + `monorepo` — `admin/class-config.php:87,97` | Yoast settings pages |
| `metabox-css`,`scoring`,`monorepo`,`ai-generator`,`ai-fix-assessments`,`post-edit`,`admin-css` — `admin/metabox/class-metabox.php:857–871` | post edit |
| `inside-editor` — `admin/metabox/class-metabox-editor.php:61` | block editor |
| `primary-category` — `admin/class-primary-term-admin.php:130` | post edit |
| `edit-page` (script+style) — `admin/class-meta-columns.php:107–108` | post/term list |
| `monorepo`,`metabox-css`,`scoring`,`ai-generator`,`term-edit`,`edit-page` — `admin/taxonomy/class-taxonomy.php:141–208` | term edit |
| `filter-explanation` — `admin/filters/class-abstract-post-filter.php:93–94` | post filters |
| `quick-edit-handler` — `admin/watchers/class-slug-change-watcher.php:49` | edit screens |
| `dashboard-widget`+`wp-dashboard`+`monorepo` — `admin/class-yoast-dashboard-widget.php:114–116` | dashboard |
| `wincher-dashboard-widget`+`wp-dashboard` — `admin/class-wincher-dashboard-widget.php:95–96` | dashboard |
| `network-admin` — `admin/class-yoast-network-admin.php:202` | network admin |
| `help-scout-beacon` — `src/integrations/admin/helpscout-beacon.php:178` | Yoast admin pages only |

**PREMIUM** (`wordpress-seo-premium/`):

| Handle / file:line | Screen |
|---|---|
| `wp-seo-premium-update-plugins` — `src/integrations/admin/update-plugins-integration.php:42` | plugins update |
| `premium-post-overview` (style) — `inclusive-language-taxonomy-column-integration.php:95`, `inclusive-language-column-integration.php:138`, `cornerstone-column-integration.php:119`, `cornerstone-taxonomy-column-integration.php:87` | post/term list columns |
| `yoast-seo-premium-draft-js-plugins` — `src/integrations/admin/replacement-variables-integration.php:71,73,81` | post/term edit |
| `yoast-seo-premium-workouts` — `src/integrations/admin/workouts-integration.php:126` | workouts |
| `yoast-premium-prominent-words-indexation` — `src/integrations/admin/prominent-words/indexing-integration.php:207` | indexing |
| `monorepo`+`yoast-seo-premium-thank-you` — `src/integrations/admin/thank-you-page-integration.php:106–107` | thank-you |
| `monorepo` — `src/integrations/admin/update-premium-notification.php:116` | update notice |
| `wp-seo-premium-redirect-notifications`(+gutenberg),`wp-seo-premium-quickedit-notification` — `classes/post-watcher.php:82–97`, `classes/term-watcher.php:81,84` | edit/quick-edit |
| `premium-metabox` (script+style) — `classes/premium-metabox.php:113–114` | post/term edit (gated `load_metabox`) |
| `wp-seo-premium-admin-redirects`+`premium-tailwind`+`redirects` — `classes/redirect/redirect-page.php:121,136–137` | redirects page |
| `yoast-social-metadata-previews` — `classes/social-previews.php:32` | admin (all `admin_enqueue_scripts`) |
| `wp-seo-premium-custom-fields-plugin` — `classes/custom-fields-plugin.php:39` | post edit |
| elementor assets — `src/integrations/third-party/elementor-premium.php` | elementor editor |

**Call out:** `admin-global` script + style is enqueued on **ALL admin pages** (no screen condition) — the single biggest "loads everywhere" item. Premium's `yoast-social-metadata-previews` is also enqueued on all `admin_enqueue_scripts` with no screen gate.

### Frontend enqueues

- **FREE:** enqueues nothing on the frontend. `structured-data-blocks` style (`admin/class-admin-asset-manager.php:660–667`) is registered for the **block editor only**, not front output.
- **PREMIUM:** `src/integrations/frontend-inspector.php` hooks `wp_enqueue_scripts` (line 75) and enqueues `yoast-seo-premium-frontend-inspector` (line 153) — **but gated** to `is_admin_bar_showing() && current_user_can('edit_posts')` (lines 108–115). So it only loads for logged-in editors viewing the bar. This is the one frontend asset and it is conditional. (Verify: near-zero free frontend enqueues; premium frontend inspector is editor-gated.)

### External HTTP requests

| Request / file:line | Endpoint | Trigger |
|---|---|---|
| `inc/class-my-yoast-api-request.php:110` (free) | `https://my.yoast.com/api/...` | license/subscription check |
| `inc/class-addon-manager.php:278` (free) | my.yoast.com subscriptions | addon status |
| `src/config/semrush-client.php` (free) | `oauth.semrush.com` | token exchange (when connected) |
| `inc/sitemaps/class-sitemaps.php:491` (free) | self `sitemap_index.xml` ping | sitemap save (cache warming) |
| `WPSEO_Tracking` `admin/class-admin.php:92` (free) | `https://tracking.yoast.com/stats` | usage tracking (2-week) |
| `classes/product-premium.php:27,56` (premium) | `my.yoast.com` | EDD license/updates |
| `src/integrations/third-party/translationspress.php:194` (premium) | translations API | translation updates |
| `src/integrations/admin/extension-importer/media-manager.php:314` (premium) | remote media URL | extension import |
| `wp-seo-premium.php:71` + `src/addon-installer.php` (premium) | Yoast repo | downloads/installs free base |

### Dashboard widgets / admin bar / post columns / notification center

- **Dashboard widgets (free):** `admin/class-yoast-dashboard-widget.php:74` (`wp_add_dashboard_widget`), `admin/class-wincher-dashboard-widget.php:55`. Premium adds none at dashboard level (link suggestions are a metabox).
- **Admin bar (free):** `inc/class-wpseo-admin-bar-menu.php` (Yoast menu + Semrush submenu `:476`). **Premium:** `frontend-inspector.php:87` (frontend inspector submenu) + `premium.php:255` "Create Redirect" on 404 (priority 96).
- **Post/term columns (free):** `admin/class-meta-columns.php`, `admin/class-yoast-columns.php` (SEO score, readability). **Premium adds:** cornerstone + inclusive-language columns (`cornerstone-column-integration.php`, `inclusive-language-column-integration.php`, + taxonomy variants).
- **Notification center (free):** `admin/class-yoast-notification-center.php` — `setup_current_notifications` on `init` (line 76), renders on `all_admin_notices` (line 78), AJAX get/dismiss (lines 80, 103), dismissal persisted in **user meta** (`dismiss_notification` line 218, `update_user_option`), `update_storage` on `shutdown` (line 83).

### Premium badge / menu-badge global style injection

- `wordpress-seo/src/integrations/admin/menu-badge-integration.php` (`menu-badge-integration.php:31–37`) appends inline CSS to `admin-global` on **every admin page** (the "Premium" badge text in submenus/admin bar). No screen condition.
- `wordpress-seo/src/presenters/admin/premium-badge-presenter.php` — `yoast-premium-badge` markup in free UI.
- `wordpress-seo/src/presenters/admin/sidebar-presenter.php` — sidebar CTA.

---

## 8. Performance Bottleneck Summary

1. **Indexable auto-rebuild on every miss/upgrade** — `Indexable_Repository::for_current_page()` auto-builds via `Indexable_Builder::build()` when missing or `indexable_needs_upgrade()` is true (`indexable-repository.php:128-172, 779-784`). A version mismatch turns one read into a write + many reads (post meta, term meta, hierarchy) mid-request. Single biggest TTFB landmine.
2. **Breadcrumbs generate 4–6 extra `wp_yoast_indexable` queries per request** even when not displayed (`breadcrumbs-generator.php:94-197`; `indexable-repository.php:461-488`). Only used for the `breadcrumb` schema property.
3. **`replace_vars()` runs 4+ times per page** (once per presenter that calls `replace_vars`), re-scanning and re-resolving tokens each time with no cross-presenter cache (`class-wpseo-replace-vars.php:141-213`; `abstract-indexable-presenter.php:77-79`). DB-hitting retrievers (`get_terms`, `wp_get_post_parent_id`, `get_post_meta`, term meta) re-execute per presenter.
4. **Double resolution of title/meta_description** — `replace()` called from `Meta_Tags_Context::generate_title/description` (`meta-tags-context.php:209-220`) AND again from each presenter's `get()`.
5. **Always-on DI container boot + `register_hooks()` on every non-admin request** — `Front_End_Conditional::is_met()` = `!is_admin()` (`src/conditionals/front-end-conditional.php:15-17`); the entire Symfony DI container is compiled/booted on every frontend request.
6. **Per-request block parsing of full `post_content`** — `Meta_Tags_Context_Memoizer::get()` calls `get_all_blocks_from_content()` for every post page (`:139-140`); pure CPU, scales with post length, every request.
7. **No persistent cache for non-home indexables** — only `find_for_home_page()` uses `wp_cache` (5 min, `indexable-repository.php:264-279`); posts/terms/archives/authors re-queried every request unless an external object cache is installed.
8. **Sitemap transient caching OFF by default** — `wpseo_enable_xml_sitemap_transient_caching` defaults `false` (`class-sitemaps-cache.php:81-89`); sitemaps rebuilt on every hit.
9. **Redirects stored in options, looped in PHP** — every request does `get_option('wpseo-premium-redirects-export-plain')` + a `foreach`/`preg_match` over all rules with only a per-request static cache (`redirect-option.php:203`; `redirect-handler.php:260-272`). No DB-layer index, no persistent cache.
10. **Global admin `admin-global` script+style on every admin page** with no screen condition (`class-admin.php:279,290`), plus `menu-badge-integration.php` inline CSS on every admin page — wasted payload on unrelated admin screens.
11. **Prominent-words indexation workload** — extra custom table + per-batch SQL (BATCH_SIZE=1000) TF-IDF cosine similarity (`link-suggestions-action.php:22,386-430`), adding background-indexing load.

---

## 9. Source File Index

### Free plugin (`wordpress-seo/`)

- `inc/class-wpseo-meta.php` — Post meta registry (`WPSEO_Meta::$meta_fields`, `$meta_prefix`, defaults, save-if-not-default logic, `register_meta` auth_callback)
- `inc/options/class-wpseo-options.php` — Options registry (`WPSEO_Options::$options`)
- `inc/options/class-wpseo-option.php` — Abstract `WPSEO_Option` base (update_option, no explicit autoload)
- `inc/options/class-wpseo-option-wpseo.php` — `wpseo` option + subkeys (webmaster verify keys)
- `inc/options/class-wpseo-option-titles.php` — `wpseo_titles` option + variable post-type/taxonomy key patterns
- `inc/options/class-wpseo-option-social.php` — `wpseo_social` option
- `inc/options/class-wpseo-option-ms.php` — `wpseo_ms` (multisite-only)
- `inc/options/class-wpseo-taxonomy-meta.php` — `wpseo_taxonomy_meta` (large serialized payload)
- `inc/options/class-wpseo-option-llmstxt.php`, `class-wpseo-option-tracking-only.php` — additional options
- `lib/model.php` — Table prefix logic (`yoast_` + `$wpdb->prefix`)
- `src/config/migrations/20171228151840_WpYoastIndexable.php` — Creates `wp_yoast_indexable`
- `src/config/migrations/20171228151841_WpYoastPrimaryTerm.php` — Creates `wp_yoast_primary_term`
- `src/config/migrations/20191011111109_WpYoastIndexableHierarchy.php` — Creates `wp_yoast_indexable_hierarchy`
- `src/config/migrations/20200617122511_CreateSEOLinksTable.php` — Creates `wp_yoast_seo_links`
- `src/config/migrations/20260325155530_CreateExpiringStoreTable.php` — Creates `wp_yoast_expiring_store` (network-wide)
- `src/config/migrations/20200728095334_AddIndexesForProminentWordsOnIndexables.php` — Prominent-words index (also used by premium table)
- `src/models/indexable.php` — Indexable column/type declarations (incl. `prominent_words_version`, `twitter_card`)
- `src/models/seo-meta.php` — SEO_Meta model (legacy/unused table)
- `src/initializers/migration-runner.php` — Migration execution path
- `src/config/migration-status.php` — `yoast_migrations_free` option + `wp_yoast_migrations` table tracking
- `src/repositories/indexable-repository.php` — Indexable read/write on page load + object cache
- `src/integrations/front-end-integration.php` — Head pipeline hooks, title override, presenter assembly
- `src/presenters/title-presenter.php`, `meta-description-presenter.php`, `robots-presenter.php`, `canonical-presenter.php`, `schema-presenter.php`, `open-graph/*`, `twitter/*` — Presenters
- `src/generators/schema-generator.php` — Schema piece assembly
- `src/integrations/uninstall-integration.php` — Uninstall behavior
- `wp-seo-main.php` — `register_uninstall_hook(..., '__return_false')` (line 151); `Yoast_Dynamic_Rewrites` (line 413)
- `inc/sitemaps/class-sitemaps-router.php` — Rewrite rules, query-var registration, template_redirect
- `inc/sitemaps/class-sitemaps.php` — Main controller: `pre_get_posts` interception, cache read, build, output, XSL, entries-per-page, last-modified
- `inc/class-yoast-dynamic-rewrites.php` — Dynamic rewrite API (no flush needed)
- `inc/sitemaps/class-sitemaps-cache.php` — Transient cache, invalidation, shutdown clear
- `inc/sitemaps/class-sitemaps-cache-validator.php` — Storage key (`yst_sm_`), validator options, base61
- `inc/sitemaps/class-sitemap-cache-data.php` — Cached payload object
- `inc/sitemaps/class-post-type-sitemap-provider.php` — Direct `$wpdb` late-row-lookup query, 1000 pagination, image inclusion
- `inc/sitemaps/class-taxonomy-sitemap-provider.php` — `get_terms` + `$wpdb` lastmod
- `inc/sitemaps/class-author-sitemap-provider.php` — `get_users`/author eligibility
- `inc/sitemaps/class-sitemaps-renderer.php` — XML/XSL output, urlset, image tags
- `inc/sitemaps/class-sitemap-image-parser.php` — Image extraction (DOMDocument, galleries)
- `inc/sitemaps/class-sitemaps-admin.php` — Ping flow (deprecated) + `wpseo_hit_sitemap_index`
- `src/integrations/front-end/robots-txt-integration.php` — robots_txt filter adding `Sitemap:` lines
- `src/routes/yoast-head-rest-field.php` — `register_rest_field` `yoast_head` / `yoast_head_json`
- `src/routes/indexables-head-route.php` — `yoast/v1` `get_head` route
- `src/main.php` — `API_V1_NAMESPACE = 'yoast/v1'`
- `admin/metabox/class-metabox.php` — Metabox/sidebar enqueue + localized analysis data
- `admin/ajax.php` — used-keywords AJAX endpoint
- `inc/class-wpseo-admin-bar-menu.php` — Admin bar menu (Semrush submenu `:476`)
- `admin/class-admin.php` — Global `admin-global` enqueue (no screen gate, `:279,290`); "Get Premium" link (`:266`)
- `admin/class-admin-asset-manager.php` — All registered admin scripts/styles
- `src/integrations/admin/menu-badge-integration.php` — Global inline "Premium" badge CSS
- `src/presenters/admin/premium-badge-presenter.php`, `sidebar-presenter.php` — Upsell presenters
- `src/integrations/admin/helpscout-beacon.php` — HelpScout beacon
- `inc/class-my-yoast-api-request.php`, `inc/class-addon-manager.php` — MyYoast license API + subscription cache
- `src/dashboard/infrastructure/site-kit.php` — Google Site Kit integration
- `src/config/semrush-client.php` — Semrush client
- `admin/class-yoast-notification-center.php` — Notification center (user-meta dismissal, shutdown storage)
- `src/integrations/cleanup-integration.php`, `background-indexing-integration.php`, `cron-integration.php` — Cron/cleanup
- `inc/class-upgrade.php` — Clears `wpseo_hit_sitemap_index` cron (`:653`); fires `wpseo_start_cleanup_indexables`

### Premium plugin (`wordpress-seo-premium/`)

- `wp-seo-premium.php` — Bootstrap, autoloader, `Addon_Installer` call, activation hook
- `premium.php` — `WPSEO_Premium` main class, integration registration, submenu, HelpScout beacon, `change_premium_indicator`
- `src/functions.php` — `YoastSEOPremium()` entry, gates on `wpseo_loaded` + `is_yoast_seo_up_to_date()`
- `src/addon-installer.php` — Free-plugin dependency auto-installer + `yoast_premium_as_an_addon_installer` option
- `src/initializers/plugin.php` — Loads `WPSEO_Premium` on `plugins_loaded`, deactivation cleanup
- `src/generated/container.php` — Compiled DI container (every registered integration/route/initializer)
- `classes/redirect/redirect-option.php` — Redirect storage in OPTIONS (`wpseo-premium-redirects-base/plain/regex`), autoload=false
- `classes/redirect/redirect-types.php` — 301/302/307/410/451 type constants + labels
- `src/initializers/redirect-handler.php` — Runtime matching flow, hook priority 16 on `plugins_loaded` (network) / init, regex `preg_match`, 410/451 via `wp` action, per-request static cache
- `classes/redirect/redirect-table.php` — Admin list table, 25 per page pagination
- `classes/premium-redirect-endpoint.php`, `redirect-undo-endpoint.php` — Redirect REST endpoints
- `classes/post-watcher.php`, `term-watcher.php` — Auto-create redirects on slug change
- `src/integrations/admin/keyword-integration.php` — `_yoast_wpseo_focuskeywords` meta + sync filters with free analysis
- `classes/multi-keyword.php` — Hidden `focuskeywords`/`keywordsynonyms` metabox + taxonomy fields
- `src/models/prominent-words.php` — Custom table `wp_yoast_prominent_words` schema (id/stem/indexable_id/weight)
- `src/repositories/prominent-words-repository.php` — Stem/indexable queries + document frequency
- `src/routes/prominent-words-route.php` — Indexation REST routes (get_content/save/complete)
- `src/routes/link-suggestions-route.php` — `yoast/v1/link_suggestions`
- `src/actions/link-suggestions-action.php` — TF-IDF cosine similarity link suggestions, BATCH_SIZE=1000
- `src/helpers/prominent-words-helper.php` — `compute_tf_idf_score` + vector length
- `classes/social-previews.php` — Social previews enqueue
- `classes/premium-stale-cornerstone-content-filter.php` — Stale cornerstone (6-month) filter
- `classes/premium-orphaned-content-support.php` (+ `-post-filter`, `-post-query`, `-content-utils`) — Orphaned content
- `src/integrations/frontend-inspector.php` — Only frontend asset (gated to editors w/ admin bar)
- `src/integrations/index-now-ping.php` — IndexNow ping (`_yoast_indexnow_last_ping`)
- `src/initializers/index-now-key.php` — `index_now_key` storage
- `src/integrations/cleanup-integration.php` — DB cleanup tasks for prominent words
- `classes/product-premium.php` — MyYoast/EDD license endpoint definition
- `classes/premium-option.php` — `wpseo_premium` option defaults
- `classes/upgrade-manager.php` — `wpseo_premium_version`; upgrade-time notification cleanup
- `src/initializers/wp-cli-initializer.php` — WP-CLI premium command registration (license-gated)
- `cli/cli-redirect-*.php` — `yoast redirect list/create/update/delete/has/follow`
- `src/ai/optimize/optimizer/user-interface/ai-optimize-route.php`, `src/ai/summarize/user-interface/ai-summarize-route.php` — AI routes (license-gated)
- `src/integrations/admin/plugin-links-integration.php` — "Activate subscription" link gated by `has_valid_subscription`
- `src/integrations/third-party/{wincher-keyphrases,algolia,edd,elementor-premium,mastodon}.php` — Third-party integrations
- `src/integrations/blocks/{estimated-reading-time-block,related-links-block}.php` — Premium blocks
- `src/integrations/admin/extension-importer/importer.php` — Competitor SEO import (`footnotes` meta)
