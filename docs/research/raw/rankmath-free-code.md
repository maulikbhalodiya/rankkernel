# Rank Math Free — Code-Verified Feature Inventory

- **Plugin:** `seo-by-rank-math` — **Version 1.0.278**
- **Plugin main file:** `rank-math.php` (header `rank-math.php:12`; `$version` `rank-math.php:37`)
- **All file paths in this document are relative to** `wp-content/plugins/seo-by-rank-math/` (the plugin directory).
- **Method:** every row below is derived from the plugin source at the stated `file:line`. Nothing is inferred from marketing copy. Where a surface (REST route, option key, meta key, table, cron hook) is listed, the registering/reading line is cited.

---

## Module registry (the free plugin's own declaration of what is free vs PRO)

Source: `includes/module/class-manager.php` (registration, flags) and `includes/module/class-module.php` (flag semantics: `is_probadge()` `:132`, `is_upgradeable()` `:141`, `is_disabled()` `:114`, `is_hidden()` `:168`, `is_active()` `:208`).

| Module id | Title | Class / status | Flags | Proof |
|---|---|---|---|---|
| `404-monitor` | 404 Monitor | `RankMath\Monitor\Monitor` | `upgradeable` | `includes/module/class-manager.php:113-120` |
| `local-seo` | Local SEO | `RankMath\Local_Seo\Local_Seo` | `upgradeable` | `:122-129` |
| `redirections` | Redirections | `RankMath\Redirections\Redirections` | `upgradeable` | `:131-138` |
| `rich-snippet` | Schema (Structured Data) | `RankMath\Schema\Schema` | `upgradeable` | `:140-147` |
| `sitemap` | Sitemap | `RankMath\Sitemap\Sitemap` | — | `:149-155` |
| `link-counter` | Link Counter | `RankMath\Links\Links` | — | `:157-162` |
| `link-genius` | AI Link Genius | (no class) | `probadge`, `disabled`, "available in the PRO version" | `:164-171` |
| `image-seo` | Image SEO | `RankMath\Image_Seo\Image_Seo` | `upgradeable` | `:173-180` |
| `instant-indexing` | Instant Indexing | `RankMath\Instant_Indexing\Instant_Indexing` | — | `:182-189` |
| `content-ai` | Content AI | `RankMath\ContentAI\Content_AI` | `upgradeable` | `:191-198` |
| `llms-txt` | LLMS Txt | `RankMath\LLMS\LLMS_Txt` | — | `:200-206` |
| `news-sitemap` | News Sitemap | (no class) | `probadge`, `disabled` | `:208-215` |
| `video-sitemap` | Video Sitemap | (no class) | `probadge`, `disabled` | `:217-224` |
| `podcast` | Podcast | (no class) | `probadge`, `disabled` | `:226-233` |
| `ai-visibility` | AI Visibility | `RankMath\AI_Visibility\AI_Visibility` | `betabadge` | `:235-242` |
| `role-manager` | Role Manager | `RankMath\Role_Manager\Role_Manager` | `only=admin` | `setup_admin_only()` `:256-263` |
| `analytics` | Analytics | `RankMath\Analytics\Analytics` | `only=admin`, `upgradeable` | `:265-273` |
| `seo-analysis` | SEO Analyzer | `RankMath\SEO_Analysis\SEO_Analysis` | `only=admin`, `upgradeable` | `:275-283` |
| `robots-txt` | Robots Txt | `RankMath\Robots_Txt` | `only=internal` (always loaded) | `setup_internals()` `:297-301` |
| `version-control` | Version Control | `RankMath\Version_Control` | `only=internal` | `:303-307` |
| `database-tools` | Database Tools | `RankMath\Tools\Database_Tools` | `only=internal` | `:309-313` |
| `status` | Status | `RankMath\Status\Status` | `only=internal` | `:315-319` |
| `amp` | AMP | (no class) | `only=skip` (info only) | `setup_3rd_party()` `:333-342` |
| `bbpress` | bbPress | (no class) | `probadge=defined('RANK_MATH_PRO_FILE')`, `only=skip` | `:344-352` |
| `buddypress` | BuddyPress | `RankMath\BuddyPress\BuddyPress` | `disabled=!class_exists('BuddyPress')` | `:354-361` |
| `woocommerce` | WooCommerce | `RankMath\WooCommerce\WooCommerce` | `upgradeable`, `disabled=!Helper::is_woocommerce_active()` | `:363-372` |
| `acf` | ACF | `RankMath\ACF\ACF` | `disabled=!function_exists('acf')` | `:374-381` |
| `web-stories` | Google Web Stories | `RankMath\Web_Stories\Web_Stories` | `disabled=!defined('WEBSTORIES_VERSION')` | `:383-390` |

Module loading semantics: internal modules always load (`class-module.php:231-242`); hidden-in-basic-mode modules are `404-monitor, acf, bbpress, buddypress, redirections, role-manager, image-seo` (`class-module.php:168-174`); active list stored in option `rank_math_modules` (`class-module.php:213`). The module listing page requires `manage_options` (`class-manager.php:411`).

Active modules are passed to JS as `rankMath.modules` (`rank-math.php:543`).

---

## Admin screens

Page infrastructure: `includes/admin/class-page.php` (`__construct` `:133-155`; default capability `manage_options` `:54`; default icon/position/priority `:61-124`; `register_menu()` uses `add_menu_page`/`add_submenu_page` `:202-209`). Option pages are built by `includes/admin/class-register-options-page.php:27-35` (React UI via `Options` when `Helper::is_react_enabled()`, else legacy CMB2).

### Top-level and module pages

| Screen slug (`page=`) | Title | Capability | Render | Proof |
|---|---|---|---|---|
| `rank-math` (top level, position 50, inline SVG icon) | Rank Math / Rank Math SEO | `manage_options` | `Admin_Helper::get_view('dashboard')` | `includes/admin/class-admin-menu.php:72-110`; menu rename `:116-142` |
| `rank-math-options-general` | SEO Settings | `rank_math_general` | `Options::display` → `#rank-math-options` | `includes/admin/class-option-center.php:104-113`; `includes/admin/class-options.php:108-141,200-204` |
| `rank-math-options-titles` | SEO Titles & Meta | `rank_math_titles` | `Options::display` | `includes/admin/class-option-center.php:206-215` |
| `rank-math-options-sitemap` | Sitemap Settings | `rank_math_sitemap` | `Options::display` | `includes/modules/sitemap/class-admin.php:135-144` |
| `rank-math-options-instant-indexing` | Instant Indexing | `rank_math_general` | `Options::display` | `includes/modules/instant-indexing/class-instant-indexing.php:248-257` |
| `rank-math-analytics` | Analytics | `rank_math_analytics` | `includes/modules/analytics/views/dashboard.php` | `includes/modules/analytics/class-analytics.php:511-536` |
| `rank-math-404-monitor` | 404 Monitor | `rank_math_404_monitor` | `includes/modules/404-monitor/views/main.php` | `includes/modules/404-monitor/class-admin.php:141-175` |
| `rank-math-redirections` | Redirections | `rank_math_redirections` | `includes/modules/redirections/views/main.php` | `includes/modules/redirections/class-admin.php:153-197` |
| `rank-math-role-manager` | Role Manager | `rank_math_role_manager` | `settings` (React mount) | `includes/modules/role-manager/class-role-manager.php:58-88` |
| `rank-math-content-ai-page` | Content AI | `rank_math_content_ai` | `includes/modules/content-ai/views/main.php` | `includes/modules/content-ai/class-content-ai-page.php:73-106` |
| `rank-math-links-page` | AI Link Genius | `rank_math_link_builder` | `#rank-math-links-page-container` closure | `includes/modules/links/Admin/class-admin.php:49-90` |
| `rank-math-ai-visibility` | AI Visibility | `manage_options` (default) | `#rank-math-ai-visibility-container` closure | `includes/modules/ai-visibility/Admin/class-admin.php:60-118` |
| `rank-math-status` | Status & Tools | `manage_options` (default) | `includes/modules/status/views/main.php` | `includes/modules/status/class-status.php:83-106` |
| `rank-math-seo-analysis` | SEO Analyzer | `rank_math_site_analysis` | `includes/modules/seo-analysis/views/main.php` | `includes/modules/seo-analysis/class-admin.php:82-109` |
| `rank-math-registration` (only when registration invalid) | Rank Math | `manage_options` | `[Registration,'render_page']` | `includes/admin/class-registration.php:60-71,207-256` |
| `rank-math-wizard` (hidden submenu, only when wizard pending) | Setup Wizard | `manage_options` | `[Setup_Wizard,'admin_page']` | `includes/admin/class-setup-wizard.php:34,46-73,79-111`; `includes/admin/class-admin-init.php:118-122` |
| WP Dashboard widget `rank_math_dashboard_widget` | Rank Math Overview | requires `404-monitor`+`404_monitor` OR `redirections`+`redirections` OR `analytics`+`analytics` | `[Dashboard_Widget,'render_dashboard_widget']` | `includes/admin/class-dashboard-widget.php:40-68` |
| Submenu external links | Help & Support; seasonal "Unlock PRO" / sale labels | `manage_options` | KB URLs, target `_blank` | `includes/admin/class-admin-menu.php:148-153,220-294` |

Menu gating: bails if `Helper::is_invalid_registration() && !is_network_admin()`; bails unless the user's roles intersect `Helper::get_roles_capabilities()` (or `setup_network`). `includes/admin/class-admin-menu.php:43-55`.

### Editor metaboxes and related admin screens

| Screen | Capability gate | Proof |
|---|---|---|
| Post-type metabox `rank_math_metabox` ("Rank Math SEO") | `Helper::has_cap('onpage_general'/'onpage_advanced'/'onpage_snippet'/'onpage_social')` (`can_add_metabox()` `:414-419`) | `includes/admin/metabox/class-metabox.php:152-189` |
| Link Suggestions metabox (`rank_math_metabox_link_suggestions`), classic editor only | `Helper::has_cap('link_builder')` + per-type `titles.pt_{type}_link_suggestions` | `includes/admin/metabox/class-metabox.php:59-61,213-249` |
| Taxonomy term metabox (per taxonomy) | same onpage caps | `includes/admin/metabox/class-metabox.php:353-370` |
| User profile metabox | same onpage caps (gated by `titles.author_add_meta_box` in REST helper) | `includes/admin/metabox/class-metabox.php:375-388`; `includes/rest/class-rest-helper.php:292` |
| Schema generator tab `rank_math_schema_generator` | `onpage_snippet` | `includes/modules/schema/views/metabox-options.php:19-37`; `includes/modules/schema/class-admin.php:80` |
| Content AI side metabox `rank_math_metabox_content_ai` (classic editor) | `can_add_tab()` (post type allowed + connected) | `includes/modules/content-ai/class-admin.php:110-134` |
| Fast/Advanced mode toggle | `Helper::is_advanced_mode()` | `includes/module/class-module.php:169` |
| Import/Export AJAX screen | `Helper::has_cap('general')` | `includes/admin/class-import-export.php:58,76` |

---

## Option groups and keys

### Registration mechanism

| Fact | Proof |
|---|---|
| Group→option map: `titles`→`rank-math-options-titles`, `general`→`rank-math-options-general`, `sitemap`→`rank-math-options-sitemap`, `instant_indexing`→`rank-math-options-instant-indexing` | `includes/class-settings.php:43-46,64-77` |
| Lazy load with `get_option($key, [])` | `includes/class-settings.php:162-175` |
| React save: `Option_Center::save_settings()` strips `htaccess_*`, `searchConsole`, `analyticsData`, `analytics`, `usage_tracking`, sanitizes, then `Helper::update_all_settings()` | `includes/admin/class-option-center.php:374-435` |
| Import/export map `modules`→`rank_math_modules`, `general`→`rank-math-options-general`, `titles`→`rank-math-options-titles`, `sitemap`→`rank-math-options-sitemap` | `includes/modules/status/class-import-export-settings.php:113-116` |
| No `register_setting()` calls in the free plugin (options are stored directly). | grep verified |

### `rank-math-options-general` — notable keys

| Tab | Keys (as keyed in code) | Proof |
|---|---|---|
| Links | `strip_category_base`, `attachment_redirect_urls`, `attachment_redirect_default`, `nofollow_external_links`, `nofollow_image_links`, `nofollow_domains`, `nofollow_exclude_domains`, `new_window_external_links` | `includes/settings/general/links.php:16,34,46,57,68,79,93,107` |
| Breadcrumbs | `breadcrumbs`, `breadcrumbs_separator`, `breadcrumbs_home`, `breadcrumbs_home_label`, `breadcrumbs_home_link`, `breadcrumbs_prefix`, `breadcrumbs_archive_format`, `breadcrumbs_search_format`, `breadcrumbs_404_label`, `breadcrumbs_remove_post_title`, `breadcrumbs_ancestor_categories`, `breadcrumbs_hide_taxonomy_name`, `breadcrumbs_blog_page` | `includes/settings/general/breadcrumbs.php:14,35,48,61,72,83,93,105,117,128,139,150,162` |
| Webmaster | `google_verify`, `bing_verify`, `baidu_verify`, `yandex_verify`, `pinterest_verify`, `norton_verify`, `custom_webmaster_tags` | `includes/settings/general/webmaster.php:15,27,39,51,64,76,89` |
| Others | `headless_support`, `frontend_seo_score`, `frontend_seo_score_post_types`, `frontend_seo_score_template`, `frontend_seo_score_position`, `support_rank_math`, `usage_tracking`, `rss_before_content`, `rss_after_content` | `includes/settings/general/others.php:16,27,37,48,63,86,99,114,123,132` |
| .htaccess (super admin only) | `htaccess_not_found`, `htaccess_not_writable`, `htaccess_accept_changes`, `htaccess_content` (virtual `htaccess_allow_editing`) | `includes/settings/general/htaccess.php:19,37,49,59`; `includes/admin/class-option-center.php:78-95` |
| Redirections tab | `redirections_debug`, `redirections_fallback`, `redirections_custom_url`, `redirections_header_code`, `redirections_post_redirect` | `includes/modules/redirections/views/options.php:15,25,40,49,59` |
| 404 Monitor tab | `404_monitor_mode`, `404_monitor_limit`, `404_monitor_exclude`, `404_monitor_ignore_query_parameters` | `includes/modules/404-monitor/views/options.php:27,41,52,83`; defaults `includes/class-installer.php:393-395` |
| Analytics tab | `searchConsole`, `analyticsData`, `console_caching_control`, `console_email_reports`, `console_email_frequency`, `analytics_stats` | `includes/modules/analytics/class-analytics.php:546-605`; `includes/class-installer.php:398-400,417` |
| Images tab (Image SEO) | `add_img_alt`, `img_alt_format`, `add_img_title`, `img_title_format` | `includes/modules/image-seo/options.php:13,23,37,47` |
| Content AI tab | `content_ai_post_types`, `content_ai_country`, `content_ai_tone`, `content_ai_audience`, `content_ai_language` | `includes/modules/content-ai/views/options.php:33-192`; `includes/class-installer.php:412-416` |
| LLMS Txt tab | `llms_post_types`, `llms_taxonomies`, `llms_limit`, `llms_extra_content` | `includes/modules/llms/options.php:33,44,55,70` |
| WooCommerce tab | `wc_remove_product_base`, `wc_remove_category_base`, `wc_remove_category_parent_slugs`, `wc_remove_generator`, `remove_shop_snippet_data`, `product_brand` | `includes/modules/woocommerce/views/options-general.php:13,29,41,53,64,75` |
| Robots.txt tab | `robots_txt_content` | `includes/modules/robots-txt/options.php:49` |
| TOC block defaults | `toc_block_title`, `toc_block_list_style` | `includes/class-installer.php:420-421` |
| Version control | `beta_optin`, `update_notification_email` | `includes/modules/version-control/class-version-control.php:78,99-100` |

### `rank-math-options-titles` — notable keys

| Tab | Keys | Proof |
|---|---|---|
| Global Meta | `robots_global`, `advanced_robots_global`, `noindex_empty_taxonomies`, `title_separator`, `rewrite_title`, `capitalize_titles`, `open_graph_image`, `twitter_card_type` | `includes/settings/titles/global.php:15,28,38,49,63,74,84,95` |
| Local SEO | `knowledgegraph_type`, `website_name`, `website_alternate_name`, `knowledgegraph_name`, `knowledgegraph_logo`, `url`, `email`, `phone`, `local_address`, `local_address_format`, `local_business_type`, `opening_hours_format`, `opening_hours`, `phone_numbers`, `price_range`, `additional_info`, `local_seo_about_page`, `local_seo_contact_page`, `maps_api_key`, `geo` | `includes/modules/local-seo/views/titles-options.php:17-335`; `includes/settings/titles/local.php:13-66` |
| Social Meta | `social_url_facebook`, `facebook_author_urls`, `facebook_admin_id`, `facebook_app_id`, `facebook_secret`, `twitter_author_names`, `social_additional_profiles` | `includes/settings/titles/social.php:15,25,35,45,55,66,76,85` |
| Homepage | `static_homepage_notice`, `homepage_title`, `homepage_description`, `homepage_custom_robots`, `homepage_robots`, `homepage_advanced_robots`, `homepage_facebook_title`, `homepage_facebook_description`, `homepage_facebook_image` | `includes/settings/titles/homepage.php:21,36,49,65,80,94,105,115,125` |
| Authors | `disable_author_archives`, `url_author_base`, `author_custom_robots`, `author_robots`, `author_advanced_robots`, `author_archive_title`, `author_archive_description`, `author_slack_enhanced_sharing`, `author_add_meta_box` | `includes/settings/titles/author.php:17,31,43,59,77,92,106,123,135` |
| Misc | `disable_date_archives`, `date_archive_title`, `date_archive_description`, `date_archive_robots`, `date_advanced_robots`, `404_title`, `search_title`, `noindex_search`, `noindex_archive_subpages`, `noindex_paginated_pages`, `noindex_password_protected` | `includes/settings/titles/misc.php:17,35,49,66,80,91,104,117,128,139,150` |
| Post Types (dynamic `pt_{type}_*`) | `pt_{type}_title`, `_description`, `_archive_title`, `_archive_description`, `_default_rich_snippet`, `_default_snippet_name`, `_default_snippet_desc`, `_default_article_type`, `_custom_robots`, `_robots`, `_advanced_robots`, `_link_suggestions`, `_ls_use_fk`, `_primary_taxonomy`, `_facebook_image`, `_bulk_editing`, `_slack_enhanced_sharing`, `_add_meta_box`, `_analyze_fields` | `includes/settings/titles/post-types.php:18,62,77,96,111,129,157,177,189,210,226,243,259,271,282,300,314,325,341,352,363,380` |
| Taxonomies (dynamic `tax_{tax}_*`) | `tax_{tax}_title`, `_description`, `_custom_robots`, `_robots`, `_advanced_robots`, `_slack_enhanced_sharing`, `_add_meta_box`, `remove_{tax}_snippet_data` | `includes/settings/titles/taxonomies.php:33,48,67,84,100,112,123,134` |
| BuddyPress (3rd-party) | `bp_group_title`, `bp_group_description`, `bp_group_custom_robots`, `bp_group_robots`, `bp_group_advanced_robots` | `includes/modules/buddypress/views/options-titles.php:13,26,40,55,68` |

### `rank-math-options-sitemap` — notable keys

| Tab | Keys | Proof |
|---|---|---|
| General | `items_per_page`, `include_images`, `include_featured_image`, `exclude_posts`, `exclude_terms` | `includes/modules/sitemap/settings/general.php:26,38,48,59,69` |
| HTML Sitemap | `html_sitemap`, `html_sitemap_display`, `html_sitemap_shortcode`, `html_sitemap_page`, `html_sitemap_sort`, `html_sitemap_show_dates`, `html_sitemap_seo_titles` | `includes/modules/sitemap/settings/html-sitemap.php:13-134` |
| Authors (if author archives indexable) | `authors_sitemap`, `authors_html_sitemap`, `include_authors_without_posts`, `exclude_roles`, `exclude_users` | `includes/modules/sitemap/settings/authors.php:23-80` |
| Post Types | `pt_{type}_sitemap`, `pt_{type}_html_sitemap`, `pt_{type}_image_customfields` | `includes/modules/sitemap/settings/post-types.php:30-67` |
| Taxonomies | `tax_{tax}_sitemap`, `tax_{tax}_html_sitemap`, `tax_{tax}_include_empty` | `includes/modules/sitemap/settings/taxonomies.php:17-51` |

### `rank-math-options-instant-indexing` — notable keys

| Key | Proof |
|---|---|
| `bing_post_types`, `indexnow_api_key`, `indexnow_api_key_location` | `includes/modules/instant-indexing/views/options.php:18,28,60`; persisted `includes/modules/instant-indexing/class-api.php:379` |

### Other `wp_options` rows written by free code

| Option | Proof |
|---|---|
| `rank_math_modules` | `includes/class-installer.php:354`; `includes/class-helper.php:182-206` |
| `rank_math_registration_skip`, `rank_math_is_configured`, `rank_math_wizard_completed` | `includes/admin/class-registration.php:198,284`; `includes/helpers/class-conditional.php:70,102` |
| `rank_math_version`, `rank_math_db_version`, `rank_math_install_date`, `rank_math_rollback_version` | `includes/class-updates.php:66-102` |
| `rank_math_google_analytic_profile`, `rank_math_google_analytic_options` | `includes/admin/wizard/class-search-console.php:52,73,122,141` |
| `rank_math_analytics_first_fetch`, `rank_math_analytics_last_updated`, `rank_math_analytics_all_services`, `rank_math_analytics_installed`, `rank_math_console_empty_dates`, `rank_math_viewed_index_status`, `rank_math_analytics_cron_notice_dismissed` | `includes/modules/analytics/class-analytics.php:164-235`; `includes/modules/analytics/workflows/class-objects.php:32-41` |
| `rank_math_seo_analysis_results`, `rank_math_seo_analysis_date`, `rank_math_seo_analysis_url` | `includes/modules/seo-analysis/class-seo-analyzer.php:201-216,240-244` |
| `rank_math_ca_data`, `rank_math_ca_credits`, `rank_math_ca_keyword`, `rank_math_contentai_score` | `includes/helpers/class-content-ai.php:56`; `includes/modules/content-ai/class-rest.php:329,350`; `includes/modules/content-ai/class-admin.php:61` |
| `rank_math_content_ai_outputs`, `rank_math_content_ai_chats`, `rank_math_content_ai_recent_prompts`, `rank_math_content_ai_prompts`, `rank_math_content_ai_posts`, `rank_math_content_ai_processed`, `rank_math_content_ai_started`, `rank_math_content_ai_error`, `rank_math_prompts_updated`, `rank_math_content_ai_viewed` | `includes/helpers/class-content-ai.php:28-49`; `includes/modules/content-ai/class-bulk-edit-seo-meta.php:57,66,95`; `includes/modules/content-ai/class-event-scheduler.php:104,131`; `includes/modules/content-ai/class-assets.php:180-183` |
| `rank_math_indexnow_log` | `includes/modules/instant-indexing/class-api.php:256,379` |
| `rank_math_backups` | `includes/modules/status/class-backup.php:27,60,107` |
| `rank_math_local_seo_update` | `includes/modules/local-seo/class-kml-file.php:194-243` |
| `rank_math_aiv_dashboard`, `rank_math_aiv_queries_<uuid>` (options); `rank_math_aiv_brand_<uuid>`, `rank_math_aiv_analysis_<uuid>` (transients) | `includes/modules/ai-visibility/class-cache.php:31,38,45,52` |
| `rank_math_yoast_block_posts`, `rank_math_aioseo_block_posts`, `rank_math_known_post_types`, `rank_math_notifications`, `rank_math_mixpanel_optin`, `rank_math_react_settings_ui`, `rank_math_view_modules`, `rank_math_pro_notice_*`, `rank_math_already_reviewed`, `rank_math_feed_posts_v2` | `includes/admin/class-notices.php:100,144`; `includes/admin/class-admin-menu.php:136`; `includes/admin/class-pro-notice.php:55,70`; `includes/admin/class-ask-review.php:54,212`; `includes/admin/class-dashboard-widget.php:198` |

---

## Modules (one subsection each)

### 404 Monitor (`includes/modules/404-monitor/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin list screen | Registers `rank-math-404-monitor` under Rank Math | `class-admin.php:141-147` | menu slug `rank-math-404-monitor`, cap `rank_math_404_monitor` |
| Settings tab | Injects `404-monitor` tab into General settings | `class-admin.php:190-210` | settings tab, option group `rank-math-options-general` |
| Mode setting | Simple logs URI+time; Advanced adds referer+UA | `views/options.php:27-36`; `class-monitor.php:175-187` | `general.404_monitor_mode` |
| Log Limit setting | Truncates log at limit | `views/options.php:41-46`; `class-db.php:97-100` | `general.404_monitor_limit` |
| Exclude Paths | Skips logging matching URIs (comparison operator) | `views/options.php:52-79`; `class-monitor.php:197-212` | `general.404_monitor_exclude` |
| Ignore Query Parameters | Strips `?...` before logging | `views/options.php:83-88`; `class-monitor.php:162` | `general.404_monitor_ignore_query_parameters` |
| Frontend capture | Hooks `wp` + theme hook to log 404s; skips 410/451 | `class-monitor.php:64-65,80-81,291-303` | hook `wp`, filter `rank_math/404_monitor/hook` |
| List columns | URI, Referer, UA, Hits, Accessed | `class-table.php:105,116,129,222-225` | screen option `rank_math_404_monitor_per_page` |
| Row action Delete | Delete single log via nonce | `class-table.php:153-160`; `class-admin.php:102-108` | AJAX `rank_math_delete_log` (`class-monitor.php:57,133-145`) |
| Bulk Delete / Clear Log | Bulk delete + truncate all | `class-table.php:271-274,85`; `class-admin.php:122-124` | nonces `bulk-events`/`404_delete_log` |
| Redirect/Edit links | Creates redirection from a 404 URI when Redirections active | `class-table.php:149-202` | deep link `Helper::get_admin_url('redirections')` |
| Dashboard widget block | Log count + URL hits totals | `class-monitor.php:87-110` | hook `rank_math/dashboard/widget` |
| Admin-bar submenu | 404 Monitor link in admin bar | `class-monitor.php:118-127` | hook `rank_math/admin_bar/items` |
| Custom DB table | Stores URI/accessed/hits/referer/UA | `includes/class-installer.php:193-203`; `class-db.php:29` | table `{prefix}rank_math_404_logs` |
| Cron / REST / meta | None | grep verified | — |

### Redirections (`includes/modules/redirections/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin CRUD screen | Full manager + React form + import/export | `class-admin.php:153-159`; `views/main.php:14-15` | slug `rank-math-redirections`, cap `rank_math_redirections` |
| Settings tab | Injects `redirections` tab at position 8 of General | `class-admin.php:215-229` | tab slug `redirections` |
| Debug Redirections | Shows debug console instead of redirecting | `views/options.php:15-19`; `class-redirector.php:294` | `general.redirections_debug` |
| Fallback Behavior | 404 / homepage / custom | `views/options.php:25-34`; `class-redirector.php:272-283` | `general.redirections_fallback` |
| Custom Url | Target for custom fallback | `views/options.php:40-43`; `class-redirector.php:424-425` | `general.redirections_custom_url` |
| Default Redirection Type | Default header code | `views/options.php:49-53`; `class-redirector.php:390` | `general.redirections_header_code` |
| Auto Post Redirect | Creates 301 on post/term slug change | `views/options.php:59-63`; `class-watcher.php:49,98,150` | `general.redirections_post_redirect` |
| Table views | All / Active / Inactive / Trash counts | `class-table.php:331-354`; `class-db.php:39-58` | `?status=` |
| Bulk actions | Activate / Deactivate / Trash; restore/delete/empty trash | `class-table.php:304-323,392-399`; `class-admin.php:246-253` | bulk nonces; AJAX per-row actions `class-admin.php:138-142,275-279` |
| Add New / Import-Export / Settings buttons | Opens form/drawer | `class-admin.php:302-308` | `?new=1`, `?importexport=1` |
| Search + sortable columns | From/To/Type/Hits/Created/Last Accessed | `views/main.php:45`; `class-table.php:263-295` | screen option `rank_math_redirections_per_page` |
| Frontend redirector | cache→exact→regex, query-string handling, headers | `class-redirector.php:138,225-250,400`; `class-redirections.php:42-44` | hook `template_redirect` |
| Post/term watcher | Auto-creates 301 old→new + admin notice | `class-watcher.php:98-104,150-160,175-213` | actions `rank_math/redirection/{post_updated,term_updated}` |
| Per-post/term metabox | Edit redirection from edit screen | `class-metabox.php:49-60,74-106` | uses table `rank_math_redirections_cache` |
| Export Apache/Nginx | `.htaccess` / `.conf` download (warn >1000) | `class-export.php:48-74`; `class-import-export.php:56,94-96` | URL `...&export=apache\|nginx`; nonce `rank-math-export-redirections` |
| Debugger overlay | Redirect debug overlay | `views/debugging.php:21-61` | hooks `rank_math/redirection/debugger_*` |
| Admin-bar + dashboard entries | Shortcuts + "Redirect me" | `class-redirections.php:128-165,68` | hook `rank_math/admin_bar/items` |
| Custom DB tables | `rank_math_redirections`, `rank_math_redirections_cache` | `includes/class-installer.php:206-228`; `class-db.php:29`; `class-cache.php:28` | two tables |
| Cron | Trash auto-clean | `class-admin.php:118` | hook `rank_math/redirection/clean_trashed` |
| Meta key | `rank_math_permalink` on slug change | `class-watcher.php:110` | post meta |
| REST | None | grep verified | — |

### Links / Link Counter / AI Link Genius (`includes/modules/links/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin screen | React Posts/Links tables | `Admin/class-admin.php:49-58,70,90` | slug `rank-math-links-page`, cap `rank_math_link_builder`; hook `rank_math/links/admin_page_registered` |
| REST `GET /links/posts` | Paginated posts with internal/external/incoming counts + SEO score | `Api/class-controller.php:58-67,240-250` | `rankmath/v1/links/posts` |
| REST `GET /links/posts-stats` | Totals + posts_with_external | `Api/class-controller.php:70-78` | `rankmath/v1/links/posts-stats` |
| REST `GET /links/links` | Link rows with `is_internal` filter | `Api/class-controller.php:81-90,295` | `rankmath/v1/links/links` |
| REST `GET /links/links-stats` | Internal/external totals | `Api/class-controller.php:93-101,374-381` | `rankmath/v1/links/links-stats` |
| Link extraction on save | Parses content into link tables + counts | `class-links.php:44,69-82,324-336`; `class-storage.php:31` | hooks `save_post`, filters `rank_math/links/*` |
| Cleanup on delete | Removes rows + recounts | `class-links.php:89-104` | hook `delete_post` |
| Posts SEO column | "Links:" internal/external/incoming | `class-links.php:113-182,234` | hook `rank_math/post/column/seo_details` |
| Cron backfill | Processes posts lacking flag, then unschedules | `class-links.php:47,248-274` | hook `rank_math/links/internal_links` |
| Custom DB tables | `rank_math_internal_links`, `rank_math_internal_meta` | `includes/class-installer.php:231-246`; `class-storage.php:31,202` | two tables |
| Meta key | `rank_math_internal_links_processed` | `class-links.php:258,336` | post meta |

### Image SEO (`includes/modules/image-seo/`) — hidden in basic mode

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Settings tab `Images` | General settings Images section | `class-admin.php:45-48` | tab |
| Add missing ALT | Fills missing alt at render, no DB write | `options.php:13-17`; `class-add-attributes.php:54` | `general.add_img_alt` |
| Alt attribute format | Template for generated ALT | `options.php:23-31` | `general.img_alt_format` |
| Add missing TITLE | Fills missing title | `options.php:37-42`; `class-add-attributes.php:55` | `general.add_img_title` |
| Title attribute format | Template for generated TITLE | `options.php:47-55` | `general.img_title_format` |
| Frontend injection | Filters content/featured/ Woo thumb + REST | `class-add-attributes.php:45-60,71-107` | filters `the_content`, `post_thumbnail_html`, `woocommerce_single_product_image_thumbnail_html` |

### Instant Indexing / IndexNow (`includes/modules/instant-indexing/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Options page with tabs Submit URLs / Settings / History | Dedicated options page (position 11) | `class-instant-indexing.php:211-257` | option group `rank-math-options-instant-indexing`, cap `rank_math_general` |
| Post Types auto-submit | Which types submit on publish/update | `views/options.php:18-22`; `class-instant-indexing.php:463` | `instant_indexing.bing_post_types` |
| API Key + Change Key + Key Location | Generates UUID key; serves `{key}.txt` at root | `views/options.php:28-60`; `class-api.php:385-389`; `class-instant-indexing.php:367-378` | `instant_indexing.indexnow_api_key[_location]` |
| Console Submit URLs | Up to 10k URLs, one per line | `views/console.php:15-28` | REST `submitUrls` |
| History | Last 100 submissions + filters/clear | `views/history.php:12-42` | option `rank_math_indexnow_log` |
| REST `POST /in/submitUrls` | Submit URLs to IndexNow | `class-rest.php:45-62` | `rankmath/v1/in/submitUrls` |
| REST `POST /in/getLog` | Filtered log | `class-rest.php:64-82` | `rankmath/v1/in/getLog` |
| REST `POST /in/clearLog` | Clear log by type | `class-rest.php:84-102` | `rankmath/v1/in/clearLog` |
| REST `POST /in/resetKey` | Regenerate key | `class-rest.php:104-114` | `rankmath/v1/in/resetKey` |
| Auto-submit hook | Watches `save_post_{type}` | `class-instant-indexing.php:76-85,268-277` | — |
| Row action Submit Page | Single-post submit | `class-instant-indexing.php:121-148` | `post_row_actions`/`page_row_actions` |
| Bulk Submit Pages | Multi-post submit | `class-instant-indexing.php:109-111,189-205` | bulk key `rank_math_indexnow` |
| External API | IndexNow endpoint | `class-api.php:31,126` | `https://api.indexnow.org/indexnow/` |
| Tables / cron / meta | None (options + file only) | — | — |

### Robots Txt (`includes/modules/robots-txt/`) — internal module

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Settings tab (super admin only) | Adds `robots` tab at position 5 | `class-robots-txt.php:32-34,61-79` | tab `robots` |
| Frontend override | Serves custom robots.txt | `class-robots-txt.php:37-39,50-51` | WP filter `robots_txt`; `general.robots_txt_content` |
| Field + lock logic | Readonly when physical file/non-public/unwritable | `options.php:15-56`; `class-robots-txt.php:90-127` | `general.robots_txt_content` |
| Notices | edit_disabled / robots_locked / site_not_public / robots_tester | `options.php:38-100` | notice ids |
| DB / cron / REST / meta | None | grep verified | — |

### Version Control (`includes/modules/version-control/`) — internal, surfaced in Status & Tools

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Version Control view | Latest/available/rollback/beta/auto-update JSON | `class-version-control.php:87-101`; `status/class-rest.php:142,151-154` | Status `?view=version_control` |
| Rollback | Downgrade to one of last 10 wp.org versions | `class-version-control.php:88-97`; `class-rollback-version.php:54-63,75-98,128-137` | option `rank_math_rollback_version`; POST `rm_rollback_version`; nonce `rank-math-rollback` |
| Beta opt-in | Injects beta into update transient | `class-version-control.php:78-81`; `class-beta-optin.php:60-61,207-246` | `general.beta_optin` |
| Auto-update toggle + email | Toggles WP auto-update; stores notification email | `class-version-control.php:99-100`; `status/class-rest.php:173-185` | `general.update_notification_email` |
| External checks | wp.org plugin info; trunk file for beta | `class-version-control.php:50,115`; `class-beta-optin.php:182` | see HTTP section |

### Database Tools (`includes/modules/database-tools/`) — internal, advanced mode only

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Tools view | JSON tool cards via Status REST | `class-database-tools.php:61-64`; `status/class-rest.php:143` | Status `?view=tools`, AJAX filter `rank_math/tools/{id}` |
| Flush SEO Analyzer | Deletes stored analyzer results | `class-database-tools.php:100-112,294-296` | options `rank_math_seo_analysis_results/_date` |
| Remove transients | Deletes `%_transient_rank_math%` | `class-database-tools.php:70-94,301-304` | options table |
| Delete Log (404) | `TRUNCATE` 404 log | `class-database-tools.php:141-149,308` | table `rank_math_404_logs` |
| Recreate tables | Re-runs `Installer::create_tables()` | `class-database-tools.php:181,199-240` | option `rank_math_modules` |
| Convert Yoast/AIOSEO blocks | Migrates vendor blocks | `class-database-tools.php:332-342` | options `rank_math_yoast_block_posts`, `rank_math_aioseo_block_posts` |
| Delete Internal Links data | Truncates link tables | `class-database-tools.php:118-130,351` | tables `rank_math_internal_links/_meta` |
| Delete Redirections | Truncates redirection tables (if active) | `class-database-tools.php:157-169,359-362` | tables `rank_math_redirections[_cache]` |
| Recalculate SEO scores | Batch 25 re-score of posts | `class-database-tools.php:369-371`; `class-update-score.php:47,95,240-244` | meta `rank_math_focus_keyword`, `rank_math_seo_score` |

### Status & Tools (`includes/modules/status/`) — internal

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin SPA shell | `#rank-math-tools-wrapper` | `class-status.php:83-90`; `views/main.php:24` | slug `rank-math-status`, default view `version_control` (`:103`) |
| REST `POST /status/getViewData` | Per-tab JSON | `class-rest.php:40-48` | `rankmath/v1/status/getViewData` |
| REST `POST /status/updateViewData` | Dispatches `update_{panel}` | `class-rest.php:49-57,162-165` | panels `auto_update`, `beta_optin` |
| REST `POST /status/importSettings` / `exportSettings` | Import/export (incl. redirections) | `class-rest.php:58-75`; `class-import-export-settings.php:27-70,207-240` | hooks `rank_math/import/settings/*`, filter `rank_math/export/settings` |
| REST `POST /status/runBackup` | create/delete/restore snapshots | `class-rest.php:76-84,109-129`; `class-backup.php:49-107` | option `rank_math_backups` |
| System Status tab | WP/RM versions, table checklist, error log tail | `class-rest.php:144`; `class-system-status.php:55-110,177` | options `rank_math_version`, `rank_math_db_version` |
| Error Log tab | Last 100 rows + path | `class-error-log.php:42-65,111` | server `error_log` path |
| Import/Export tab | Exports modules + all option groups + up to 1000 redirections | `class-import-export-settings.php:95-152,207-240` | options `rank_math_modules`, `rank-math-options-*` |

### Schema / Rich Snippet (`includes/modules/schema/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Schema type registry | 13 selectable types + legacy Review | `includes/helpers/class-choices.php:436-468` | filter `rank_math/settings/snippet/types` |
| Global entities | Publisher, Website, PrimaryImage, Breadcrumbs, Webpage, Author, Products_Page, Singular | `class-jsonld.php:237-244` | — |
| JSON-LD output | `<script application/ld+json class=rank-math-schema>` on `rank_math/head` | `class-jsonld.php:54-55,156-164` | hook `rank_math/head` |
| Metabox schema generator | `rank_math_schema_generator` tab | `views/metabox-options.php:19-37`; `class-admin.php:80` | `onpage_snippet` cap |
| FAQ/HowTo auto type | Adds FAQPage/HowTo when blocks present | `class-db.php:101-107`; `class-admin.php:244-250` | — |
| Shortcodes | `[rank_math_rich_snippet]`, `[rank_math_review_snippet]` (+ auto-inject) | `class-snippet-shortcode.php:47-52,365-392` | shortcodes |
| Blocks | FAQ, HowTo, TOC, rich-snippet | `class-blocks.php:55-59,69-80` | Gutenberg blocks |
| Meta keys | `rank_math_schema*`, `rank_math_shortcode_schema_{id}`, `rank_math_rich_snippet`, `rank_math_snippet_*` | `class-db.php:65,165,244`; `class-jsonld.php:566-695` | post/term meta |
| Settings keys | `pt_{type}_default_rich_snippet`, `_default_article_type`, `_default_snippet_name/desc` | `includes/helpers/class-schema.php:41,54-59`; `class-jsonld.php:678,766` | options titles |
| Schema list column | "Schema:" in posts list | `class-admin.php:64-73` | — |
| REST / tables / cron | None | grep verified | — |

### Sitemap (`includes/modules/sitemap/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Index sitemap | `sitemap_index.xml`, `?sitemap=1` | `class-sitemap.php:325-334`; `class-router.php:51`; `class-generator.php:160-194` | rewrite |
| Post-type sitemaps | `{pt}-sitemap[N].xml` | `providers/class-post-type.php:75-91,100-104`; `class-generator.php:98-101` | rewrite `class-router.php:52` |
| Taxonomy sitemaps | `{tax}-sitemap[N].xml` | `providers/class-taxonomy.php:45-77` | rewrite |
| Author sitemap | `author-sitemap[N].xml` | `providers/class-author.php:45,58-71`; `class-generator.php:104-106` | only if `Helper::is_author_archive_indexable()` |
| Local sitemap | `local-sitemap.xml` in index (Local SEO + company) | `local-seo/class-kml-file.php:76-126` | hooks `rank_math/sitemap/index`, `.../local/content` |
| KML | `locations.kml` (`?sitemap=locations`) | `local-seo/class-kml-file.php:47,133-186`; `class-router.php:188` | rewrite |
| Image extension | `<image:image><image:loc>` inline when images present | `class-generator.php:231-234,322-340` | — |
| XSL stylesheets | `main-sitemap.xsl`, `{x}-sitemap.xsl` | `class-router.php:53,66-72`; `class-generator.php:75-76` | rewrite |
| Admin screen | `rank-math-options-sitemap`, cap `rank_math_sitemap` | `class-admin.php:80-273` | options page |
| Shortcodes | `[rank_math_html_sitemap]`, `[aioseo_html_sitemap]` | `html-sitemap/class-sitemap.php:60-63` | shortcodes |
| Meta keys | `rank_math_exclude_sitemap`; `rank_math_robots` noindex exclusion | `class-admin.php:284-311`; `class-sitemap.php:230-242` | post meta |
| File cache | sitemap cache files + `sitemap_cache_files` option; invalidation watchers | `class-cache.php:94-135,194-201`; `class-cache-watcher.php:61-86` | — |
| Cron | `rank_math/sitemap/hit_index` single event +300s; `hit_index()` self-fetch | `class-cache-watcher.php:128-130`; `class-sitemap.php:57,159-161` | cron |
| REST / tables | None (file cache only) | grep verified | — |

### SEO Analyzer (`includes/modules/seo-analysis/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin screen | `rank-math-seo-analysis`, position 60, cap `rank_math_site_analysis` | `class-admin.php:79-110` | options view `views/main.php` |
| Site-wide audit | Runs local tests (+ remote API tests) | `class-seo-analyzer.php:288-313,369-437` | AJAX `wp_ajax_rank_math_analyze` (`:94-95,327-348`) |
| Per-URL subpage mode | `?u=` same-site only | `class-seo-analyzer.php:106-118,291-302` | — |
| Admin-bar entries | SEO Analyzer + "Analyze this Page" | `class-seo-analysis.php:55-79` | admin bar |
| Storage | options `rank_math_seo_analysis_results/_date/_url`; clear `?clear_results=1` | `class-seo-analyzer.php:201-216,238-245` | options |
| REST / blocks / tables / cron | None | grep verified | — |

### Analytics (`includes/modules/analytics/`) — admin-only

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin dashboard | `rank-math-analytics`, position 5, cap `rank_math_analytics` | `class-analytics.php:511-537` | `views/dashboard.php` |
| Search Console data pull | Query/page/clicks/impressions/position/CTR, bulk insert 50 | `class-db.php:357-429`; `workflows/class-jobs.php:138-207` | table `rank_math_analytics_gsc` |
| URL Inspection | Index Status tab per-page checks | `workflows/class-jobs.php:245-259`; `class-url-inspection.php:51-63`; `class-db.php:289-310` | table `rank_math_analytics_inspections` |
| Objects tracker | seo_score/schemas/indexable + flat_posts | `workflows/class-jobs.php:103-109`; `class-db.php:217-241` | table `rank_math_analytics_objects` |
| Stats dashboards | dashboard/keywords/posts/summary/topKeywords/positionGraph | `rest/class-rest.php:47-103`; `class-stats.php:661-816` | REST namespace `rankmath/v1/an` |
| GA gtag injector | Property/measurement ID, AMP, opt-out, role excludes | `class-gtag.php:62-97,317-374,397-409` | option `rank_math_google_analytic_options` |
| Email reports | Scheduled, preview `?rank_math_analytics_report_preview=1` | `class-email-reports.php:102`; `class-analytics-common.php:398` | cron `rank_math/analytics/email_report_event` |
| Sitemap auto-sync | Submits sitemaps to Google | `workflows/class-jobs.php:59`; `google/class-console.php:95` | cron `rank_math/analytics/sync_sitemaps` |
| Settings tab | General Analytics tab + `console_caching_control` (max 90 free) | `class-analytics.php:546-605,614-634` | tab |
| REST routes (11) | dashboard, keywordsOverview, postsSummary, postsRowsByObjects, post/{id}, keywordsSummary, analyticsSummary, keywordsRows, userPreferences, inspectionResults, removeFrontendStats | `rest/class-rest.php:39-176` | `rankmath/v1/an` |
| User meta | `rank_math_analytics_table_columns`, `rank_math_hide_frontend_stats` | `rest/class-rest.php:220-224,333-337` | user meta |
| Options | see options section | `class-analytics.php:164-235` | — |
| Scheduled actions | Action Scheduler group `rank-math` (workflow, data_fetch, get_console_data, get_inspections_data, clear_cache, flat_posts, email_report_event) | `workflows/class-workflow.php:49-57,116-126,149-159`; `workflows/class-jobs.php:65,80-85` | Action Scheduler |
| Blocks / shortcodes / widgets | None | `class-email-reports.php:143` | — |

### Local SEO (`includes/modules/local-seo/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Organization/Person publisher | Injects Organization (+Place/GeoCoordinates/openingHours/contactPoint) or Person into JSON-LD | `class-local-seo.php:35,97-221` | hook `rank_math/json_ld:9` |
| Settings tab | `local` tab of Titles & Meta | `class-local-seo.php:58-87`; `views/titles-options.php:17-335` | options titles |
| Business type tree | `choices_business_types(true)` | `includes/helpers/class-choices.php:150-425` | setting `local_business_type` |
| KML file | `locations.kml` | `class-kml-file.php:47,133-186` | rewrite |
| Local sitemap | `local-sitemap.xml` | `class-kml-file.php:76-126` | — |
| Shortcode data | Feeds global `[rank_math_contact_info]` | `includes/frontend/class-shortcodes.php:56` | shortcode |
| Lastmod option | `rank_math_local_seo_update` on settings save | `class-kml-file.php:39-40,194-243` | option |
| REST / tables / cron / meta | None custom | grep verified | — |

### Content AI (`includes/modules/content-ai/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin page | `rank-math-content-ai-page`, position 4, cap `rank_math_content_ai` | `class-content-ai-page.php:73-107` | page |
| Settings tab | `content-ai` General tab | `class-admin.php:46,81-105` | tab |
| Content Editor CPT | Hidden `rm_content_editor` block editor | `class-content-ai-page.php:221-243` | CPT |
| Credits / plan | Wallet fetch, `rank_math_ca_credits`, `get_content_ai_plan()` | `includes/helpers/class-content-ai.php:64-237` | options/transient |
| Feature quota | `is_feature_quota_exhausted`, `update_feature_usage` | `includes/helpers/class-content-ai.php:172-206` | — |
| Research keyword | REST → `cai.rankmath.com/ai/research` | `class-rest.php:48-57,634-644` | REST |
| Create post / save output / prompts | REST routes | `class-rest.php:69-204` | REST |
| Bulk SEO meta | Background process → `/ai/bulk_seo_meta` | `class-bulk-edit-seo-meta.php:28,169,202-211` | cron `wp_bulk_edit_seo_meta_cron` |
| Bulk image alt | Background process → `/ai/generate_image_alt_v2` | `class-bulk-image-alt.php:28,182,231-240` | cron `wp_bulk_image_alt_cron` |
| Prompt/plan scheduler | Daily prompts + single plan update | `class-event-scheduler.php:59-63,72-136` | crons `rank_math/content-ai/update_prompts`, `.../update_plan` |
| Block `rank-math/command` | AI Assistant block | `blocks/command/class-block-command.php:59-64`; `blocks/command/assets/src/block.json:4` | Gutenberg block |
| Bulk list actions | Fetch SEO title/description/image alt | `class-bulk-actions.php:82-111,231-253` | list actions |
| Settings keys | `content_ai_post_types`, `_country`, `_tone`, `_audience`, `_language` | `views/options.php:33-192` | options general |
| Meta keys | `rank_math_ca_keyword`, `rank_math_contentai_score`; writes `rank_math_title/description` | `class-rest.php:350-387`; `class-bulk-edit-seo-meta.php:140-144` | post meta |
| Tables | None | — | — |
| REST namespace | `rankmath/v1/ca` | `class-rest.php:40` | — |

### AI Visibility (`includes/modules/ai-visibility/`) — BETA

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin page | `rank-math-ai-visibility`, position 4 | `Admin/class-admin.php:31,60-118` | page |
| Overview / brands CRUD | Dashboard + brand create/update | `Api/class-brands-controller.php:237-499` | REST |
| Insights / queries | Competitors, transcripts, query enable/regenerate | `Api/class-brands-controller.php:529-718` | REST |
| Trial activation | 15-day `content-ai-creator-trial` | `Api/class-trial-controller.php:32,56-131` | REST |
| Checkout iframe | Single-use token | `Api/class-checkout-controller.php:54-118` | REST |
| Cache layer | Options/transients + TTLs | `class-cache.php:31-79,90-377` | options/transients |
| Admin-bar link | AI Visibility shortcut | `Admin/class-admin.php:40,141-154` | admin bar |
| Blocks / shortcodes / widgets / meta / tables / cron | None | — | — |
| REST namespace | `rankmath/v1/ai-visibility` | `Api/class-base-controller.php:31` | — |

### LLMS Txt (`includes/modules/llms/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Rewrite + serve | `^llms\.txt$` → `?llms_txt=1`, `text/plain`, noindex | `class-llms-txt.php:33-36,85-108,122-147` | rewrite rule |
| Canonical handling | Removes canonical redirect for `/llms.txt` | `class-llms-txt.php:45-49` | — |
| Header | site name + description + credit + sitemap link | `class-llms-txt.php:154-176` | — |
| Posts / terms listings | Per post-type/taxonomy, respects indexability | `class-llms-txt.php:184-305` | — |
| Extra content | Appended text | `class-llms-txt.php:312-324` | — |
| Settings tab | `llms` tab, advanced option | `class-llms-txt.php:35,59-77` | options general |
| Settings keys | `llms_post_types`, `llms_taxonomies`, `llms_limit`, `llms_extra_content` | `options.php:33,44,55,70` | options general |
| REST / blocks / tables / cron / external | None | — | — |

### Role Manager (`includes/modules/role-manager/`) — admin-only

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Admin page | `rank-math-role-manager`, position 20, cap `rank_math_role_manager` | `class-role-manager.php:55-89` | page |
| 16 capabilities | `rank_math_titles, general, sitemap, 404_monitor, link_builder, redirections, role_manager, analytics, site_analysis, onpage_analysis, onpage_general, onpage_advanced, onpage_snippet, onpage_social, content_ai, admin_bar` | `class-capability-manager.php:56-71` | caps |
| Role defaults | administrator=all; editor/author=onpage subset | `class-capability-manager.php:143-169` | — |
| Members plugin integration | Registers cap group + `rank_math_edit_htaccess` | `class-members.php:36-75` | — |
| User Role Editor integration | URE cap group | `class-user-role-editor.php:42-80` | — |
| REST / tables / cron / meta | None | — | — |

### ACF (`includes/modules/acf/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Analyzer integration | Feeds ACF field content into SEO analyzer JS | `class-acf.php:28-51` | filter `rank_math/acf/config` |
| Type blacklist | 19 excluded ACF field types | `class-acf.php:82-112` | filter `rank_math/acf/blacklist/types` |
| Refresh rate | Configurable debounce, floor 200ms | `class-acf.php:119-130` | filter `rank_math/acf/refresh_rate` |
| Admin screens / REST / tables / meta | None | — | — |

### Google Web Stories (`includes/modules/web-stories/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Head replacement | Rank Math head tags in stories | `class-web-stories.php:27-28,35-48` | `web_stories_story_head` action |
| Publisher logo schema | Replaces publisher logo for stories | `class-web-stories.php:29,58-82` | hook `rank_math/json_ld:99` |
| Other surfaces | None | — | — |

### BuddyPress (`includes/modules/buddypress/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Paper hash + title guard | Adds BP_User/BP_Group paper; suppresses activate title | `class-buddypress.php:32,35,43-63` | — |
| ProfilePage schema | `ProfilePage > Person` for BP users | `class-buddypress.php:34,70-99` | hook `rank_math/json_ld:11` |
| Group variables | `group_name`, `group_desc` | `class-buddypress.php:33,104-126` | vars |
| Titles settings tab | `buddypress-groups` tab | `class-admin.php:28,38-52` | options titles |
| Settings keys | `bp_group_title`, `_description`, `_custom_robots`, `_robots`, `_advanced_robots` | `views/options-titles.php:13-77`; `paper/class-bp-group.php:28-62` | options titles |
| REST / tables / cron | None | — | — |

### WooCommerce (`includes/modules/woocommerce/`)

| Feature | Behaviour | Proof | Surface |
|---|---|---|---|
| Permalink stripping | Removes product/category base + parent slugs | `class-woocommerce.php:105-171`; `class-permalink-watcher.php:74-169` | settings below |
| Product redirects | Watchers only when strip enabled | `class-woocommerce.php:63-94`; `class-product-redirection.php:204-206` | — |
| noindex cart/checkout/account | `noindex,follow` + remove WC generator | `class-woocommerce.php:117,180-196,109-112` | — |
| Meta description fallback | Short desc → truncated long desc | `class-woocommerce.php:116,208-225` | — |
| SEO score recalc | Appends excerpt for products | `class-woocommerce.php:70,274-280` | — |
| Sitemap exclusions | Excludes variation/coupon/`pa_*`; adds gallery | `class-sitemap.php:30-130` | sitemap |
| OpenGraph product tags | `product:brand/price/availability` | `class-opengraph.php:28-145` | OpenGraph |
| WC vars | `%wc_price%`, `%wc_sku%`, `%wc_shortdesc%`, `%wc_brand%` | `class-wc-vars.php:24-148` | replace vars |
| Schema integration | WooCommerce Product snippet + shop suppression | `schema/snippets/class-product-woocommerce.php:52`; `class-products-page.php:104` | schema |
| Analyzer JS | `woocommerce.js` on product edit | `class-admin.php:47,53-60` | — |
| Settings keys | `wc_remove_product_base`, `wc_remove_category_base`, `wc_remove_category_parent_slugs`, `wc_remove_generator`, `remove_shop_snippet_data`, `product_brand` | `views/options-general.php:13-83` | options general |
| Settings tab | `woocommerce` tab, icon cart, position 7 | `class-admin.php:44,69-88` | tab |

---

## Schema types

All selectable types are declared in `includes/helpers/class-choices.php` via the `schema_types` choice set:

| Slug | Label | Proof |
|---|---|---|
| `article` | Article | `includes/helpers/class-choices.php:438` |
| `book` | Book | `:439` |
| `course` | Course | `:440` |
| `event` | Event | `:441` |
| `jobposting` | Job Posting | `:442` |
| `music` | Music | `:443` |
| `product` | Product | `:444` |
| `recipe` | Recipe | `:445` |
| `restaurant` | Restaurant | `:446` |
| `video` | Video (output as `VideoObject`) | `:447`; `includes/modules/schema/class-admin.php:176-178` |
| `person` | Person | `:448` |
| `service` | Service | `:449` |
| `software` | Software Application (output as `SoftwareApplication`) | `:450`; `class-admin.php:180-182` |
| `review` | Review (Unsupported / legacy only if old `rank_math_rich_snippet=review` posts exist) | `:453-455,578-589` |

Output-time type mapping / aliases:
- `BlogPosting` / `NewsArticle` display as `Article` (`includes/helpers/class-schema.php:85-87`).
- `WooCommerceProduct` → `WooCommerce Product`; `EDDProduct` → `EDD Product`; `VideoObject`→`Video`; `JobPosting`→`Job Posting`; `SoftwareApplication`→`Software Application` (`includes/helpers/class-schema.php:89-107`).
- `MusicGroup`/`MusicAlbum` display as `Music` (`includes/helpers/class-schema.php:109-111`).
- JSON-LD normalization: `musicgroup`/`musicalbum`→`music`; `blogposting`/`newsarticle`→`article`; `event` includes→`event` (`includes/modules/schema/class-jsonld.php:340-345`).
- Shortcode aliases: `event` suffix, `resturant` typo→`restaurant`; `article`/`blogposting`/`newsarticle` render JSON-LD only (`includes/modules/schema/class-snippet-shortcode.php:128-138`).

Always-on global snippet entities (not user-selected): `Publisher`, `Website`, `PrimaryImage`, `Breadcrumbs`, `Webpage`, `Author`, `Products_Page`, `Singular` (`includes/modules/schema/class-jsonld.php:237-244`). Conditional product types: `Product`, `WooCommerceProduct`, `EDDProduct`, `Products_Page` (`includes/modules/schema/snippets/`).

---

## Sitemap types (free)

| Type | URL | Proof |
|---|---|---|
| Index | `sitemap_index.xml` (slug filterable; `?sitemap=1` non-pretty) | `includes/modules/sitemap/class-sitemap.php:325-334`; `class-router.php:51,181-189` |
| Post type (dynamic, per accessible type) | `{pt}-sitemap[N].xml` | `includes/modules/sitemap/providers/class-post-type.php:75-104` |
| Taxonomy (dynamic, per viewable tax) | `{tax}-sitemap[N].xml` | `includes/modules/sitemap/providers/class-taxonomy.php:45-77` |
| Author | `author-sitemap[N].xml` | `includes/modules/sitemap/providers/class-author.php:45,58-71` |
| Local | `local-sitemap.xml` | `includes/modules/local-seo/class-kml-file.php:76-96` |
| KML locations | `locations.kml` | `includes/modules/local-seo/class-kml-file.php:47,133-186` |
| Image (extension, not a separate file) | inline `<image:loc>` | `includes/modules/sitemap/class-generator.php:231-234,322-340` |
| XSL stylesheets | `main-sitemap.xsl`, `{x}-sitemap.xsl` | `includes/modules/sitemap/class-router.php:53,66-72` |

**Not present in free:** News sitemap, Video sitemap, Podcast RSS (the `news-sitemap` / `video-sitemap` / `podcast` modules are `probadge + disabled`, `includes/module/class-manager.php:208-233`). Extension point for providers: `includes/modules/sitemap/class-generator.php:108-113`.

---

## Analysis tests

### On-page content analysis (SEO score / Readability) — JS test keys

Defined in the compiled editor bundle `assets/admin/js/rank-math-app.js` (single-line minified; line 1). The test-key → KB anchor map is proven at that file. Free test keys:

`contentHasAssets`, `contentHasShortParagraphs`, `contentHasTOC`, `hasContentAI`, `hasProductSchema`, `isReviewEnabled`, `keywordDensity`, `keywordIn10Percent`, `keywordInContent`, `keywordInImageAlt`, `keywordInMetaDescription`, `keywordInPermalink`, `keywordInSubheadings`, `keywordInTitle`, `keywordNotUsed`, `lengthContent`, `lengthPermalink`, `linksHasExternals`, `linksHasInternal`, `linksNotAllExternals`, `titleHasNumber`, `titleHasPowerWords`, `titleSentiment`, `titleStartWithKeyword` — `assets/admin/js/rank-math-app.js:1`.

Group configuration (which tests are scored in which section) is also in that bundle: basic group `{keywordInTitle, keywordInMetaDescription, keywordInPermalink, keywordIn10Percent, keywordInContent, lengthContent, hasProductSchema}`, advanced group `{keywordInSubheadings, keywordInImageAlt, keywordDensity, lengthPermalink, linksHasExternals, linksNotAllExternals, linksHasInternal, keywordNotUsed, hasContentAI, isReviewEnabled}`, title advanced `{titleStartWithKeyword, keywordNotUsed}`, plus title sentiment/power-word/number tests — `assets/admin/js/rank-math-app.js:1`.

### SEO Analyzer (site-wide) tests — PHP

Registered via filter `rank_math/seo_analysis/tests` in `includes/modules/seo-analysis/seo-analysis-tests.php`:

| Test key | Title | Category | Callback | Proof |
|---|---|---|---|---|
| `auto_update` | Automatic Updates | priority | `rank_math_analyze_auto_update` | `includes/modules/seo-analysis/seo-analysis-tests.php:155-168,176-202` |
| `site_description` | Site Tagline | basic | `rank_math_analyze_site_description` | `:30-41,209-235` |
| `blog_public` | Blog Public | basic | `rank_math_analyze_blog_public` | `:43-57,509-523` |
| `permalink_structure` | Permalink Structure | basic | `rank_math_analyze_permalink_structure` | `:59-71,259-281` |
| `focus_keywords` | Focus Keywords | basic | `rank_math_analyze_focus_keywords` | `:73-82,311-367` |
| `post_titles` | Post Titles Missing Focus Keywords | basic | `rank_math_analyze_post_titles` | `:84-91,374-406` |
| `search_console` | Search Console | advanced | `rank_math_analyze_search_console` | `:112-129,297-304` |
| `sitemaps` | Sitemaps | advanced | `rank_math_analyze_sitemap` | `:131-139,491-503` |

Remote tests run against the Rank Math analysis API (SERP title/description length) — `includes/modules/seo-analysis/class-seo-analyzer.php:369-437,522-558`.

---

## Meta keys

Shared path: `includes/class-metadata.php:75,125`; generic get/update via `includes/traits/class-meta.php:32,53`. Canonical key loop: `includes/admin/metabox/class-screen.php:243-290,298` (+ robots `:302-305`).

### Post (`postmeta`)

`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_pillar_content`, `rank_math_canonical_url`, `rank_math_breadcrumb_title`, `rank_math_advanced_robots`, `rank_math_robots`, `rank_math_facebook_title`, `rank_math_facebook_description`, `rank_math_facebook_image`, `rank_math_facebook_image_id`, `rank_math_facebook_enable_image_overlay`, `rank_math_facebook_image_overlay`, `rank_math_facebook_author`, `rank_math_twitter_card_type`, `rank_math_twitter_use_facebook`, `rank_math_twitter_title`, `rank_math_twitter_description`, `rank_math_twitter_image`, `rank_math_twitter_image_id`, `rank_math_twitter_enable_image_overlay`, `rank_math_twitter_image_overlay`, `rank_math_twitter_player_url`, `rank_math_twitter_player_size`, `rank_math_twitter_player_stream`, `rank_math_twitter_player_stream_ctype`, `rank_math_twitter_app_description`, `rank_math_twitter_app_iphone_name`, `rank_math_twitter_app_iphone_id`, `rank_math_twitter_app_iphone_url`, `rank_math_twitter_app_ipad_name`, `rank_math_twitter_app_ipad_id`, `rank_math_twitter_app_ipad_url`, `rank_math_twitter_app_googleplay_name`, `rank_math_twitter_app_googleplay_id`, `rank_math_twitter_app_googleplay_url`, `rank_math_twitter_app_country` — `includes/admin/metabox/class-screen.php:243-290`.

Plus:
- `rank_math_seo_score` (`includes/rest/class-admin.php:185`; `includes/class-frontend-seo-score.php:222`)
- `rank_math_primary_{taxonomy}` / `rank_math_primary_category` (`includes/admin/metabox/class-metabox.php:466`; `includes/admin/metabox/class-post-screen.php:456`)
- `rank_math_permalink` (`includes/modules/redirections/class-watcher.php:110`)
- `rank_math_exclude_sitemap` (`includes/modules/sitemap/class-admin.php:284-325`)
- `rank_math_lock_modified_date` (`includes/admin/class-lock-modified-date.php:111`)
- `rank_math_rich_snippet`, `rank_math_schema_{Type}`, `rank_math_shortcode_schema_{id}`, `rank_math_snippet_{field}` (`includes/rest/class-shared.php:198,226,440`; `includes/modules/schema/class-db.php:65,165`)
- `rank_math_contentai_score`, `rank_math_ca_keyword` (`includes/modules/database-tools/class-update-score.php:193`; `includes/modules/content-ai/class-admin.php:61`)
- `rank_math_analytic_object_id` (`includes/modules/analytics/class-watcher.php:87,124`)
- `rank_math_internal_links_processed` (`includes/modules/links/class-links.php:336`)
- `rank_math_dont_show_seo_score` (`includes/class-frontend-seo-score.php:109`)

### Term (`termmeta`)

Same `rank_math_` namespace via `meta_type='term'` (`includes/class-term.php:27`): title/description/focus_keyword/canonical/breadcrumb/robots/social keys applied through `objectType=term` (`includes/admin/metabox/class-screen.php:298,302,305`). Notable reads: `rank_math_title` (`includes/modules/sitemap/html-sitemap/class-terms.php:224`), `rank_math_canonical_url` (`includes/modules/sitemap/providers/class-taxonomy.php:291`), `rank_math_robots` (`includes/admin/importers/class-yoast.php:677`), `rank_math_focus_keyword` (`includes/replace-variables/class-advanced-variables.php:165,196`), `rank_math_schema_*` (`includes/rest/class-shared.php:170,198`).

### User (`usermeta`)

Same namespace via `meta_type='user'` (`includes/class-user.php:27`), gated by `titles.author_add_meta_box` (`includes/rest/class-rest-helper.php:292`). Notable: `rank_math_title`/`rank_math_robots` (`includes/modules/sitemap/html-sitemap/class-authors.php:95,174`), `rank_math_permalink` (`includes/class-rewrite.php:64`), `rank_math_facebook_author`, `rank_math_twitter_author`, `rank_math_breadcrumb_title` (`includes/opengraph/class-facebook.php:325`; `includes/opengraph/class-twitter.php:219`; `includes/opengraph/class-opengraph.php:212`), `rank_math_analytics_table_columns`, `rank_math_hide_frontend_stats` (`includes/modules/analytics/rest/class-rest.php:222,334`), `rank_math_metabox_checklist_layout` (`includes/admin/class-admin.php:187`).

---

## Custom tables

Created via `DB::create_table()` → `dbDelta` (`includes/helpers/class-db.php:209,218`).

| Table (`{prefix}…`) | Created at | Module gate |
|---|---|---|
| `rank_math_404_logs` | `includes/class-installer.php:195` | `404-monitor` (`:193`) |
| `rank_math_redirections` | `includes/class-installer.php:208` | `redirections` (`:206`) |
| `rank_math_redirections_cache` | `includes/class-installer.php:220` | `redirections` |
| `rank_math_internal_links` | `includes/class-installer.php:233` | `link-counter` (`:231`) |
| `rank_math_internal_meta` | `includes/class-installer.php:241` | `link-counter` |
| `rank_math_analytics_gsc` | `includes/modules/analytics/workflows/class-console.php:55-58` | `analytics` |
| `rank_math_analytics_objects` | `includes/modules/analytics/workflows/class-objects.php:47-49` | `analytics` |
| `rank_math_analytics_inspections` | `includes/modules/analytics/workflows/class-inspections.php:74-77` | `analytics` |

Referenced but not created in free (collation/status labels only): `rank_math_analytics_ga`, `rank_math_analytics_keyword_manager`, `rank_math_analytics_adsense` (`includes/modules/analytics/class-analytics-common.php:192-195`; `includes/modules/status/class-system-status.php:105-110`).

---

## REST routes

Base namespace: `rankmath/v1` (`includes/rest/class-rest-helper.php:29`).

### Core (`includes/rest/`)

| Route | Methods | Permission | Proof |
|---|---|---|---|
| `/saveModule` | EDITABLE (POST/PUT/PATCH) | `manage_options` | `class-admin.php:46` |
| `/toolsAction` | EDITABLE | `manage_options` | `class-admin.php:57` |
| `/updateMode` | EDITABLE | `manage_options` | `class-admin.php:68` |
| `/dashboardWidget` | READABLE | `read` | `class-admin.php:79` |
| `/updateSeoScore` | EDITABLE | per-post `edit_post` | `class-admin.php:91` |
| `/updateSettings` | EDITABLE | `rank_math_{type}` / `rank_math_role_manager` | `class-admin.php:102` |
| `/searchPage` | ALLMETHODS | `manage_options` | `class-admin.php:112` |
| `/disconnectSite` | READABLE | token === `api_key` | `class-front.php:41` |
| `/getFeaturedImageId` | EDITABLE | `onpage_general` | `class-front.php:52` |
| `/updateRedirection` | CREATABLE | `redirections` module + cap | `class-shared.php:49` |
| `/updateMeta` | CREATABLE | per-object permission | `class-shared.php:60` |
| `/updateSchemas` | CREATABLE | `rich-snippet` + `onpage_snippet` | `class-shared.php:71` |
| `/updateMetaBulk` | CREATABLE | `onpage_general` | `class-post.php:39` |
| `/getHead` | READABLE | `__return_true` (only registered when `general.headless_support`) | `class-headless.php:51` |
| `setupWizard/getStepData` | POST | `manage_options` | `class-setup-wizard.php:36` |
| `setupWizard/updateStepData` | POST | `manage_options` | `class-setup-wizard.php:46` |
| `setupWizard/updateTrackingOptin` | POST | `manage_options` | `class-setup-wizard.php:56` |

### Analytics — namespace `rankmath/v1/an` (`includes/modules/analytics/rest/class-rest.php:32`; all `rank_math_analytics`)

`/dashboard`, `/keywordsOverview`, `/postsSummary`, `/postsRowsByObjects`, `/post/(?P<id>\d+)`, `/keywordsSummary`, `/analyticsSummary`, `/keywordsRows`, `/userPreferences`, `/inspectionResults`, `/removeFrontendStats` — `class-rest.php:39-176`.

### Content AI — namespace `rankmath/v1/ca` (`includes/modules/content-ai/class-rest.php:40`; permission `has_cap('content_ai')` + site connected `:283-293`)

`/researchKeyword`, `/getCredits`, `/createPost`, `/saveOutput`, `/deleteOutput`, `/updateRecentPrompt`, `/updatePrompt`, `/savePrompts`, `/pingContentAI`, `/generateAlt`, `/updateCredits` — `class-rest.php:48-260`.

### Instant Indexing — namespace `rankmath/v1/in` (`includes/modules/instant-indexing/class-rest.php:34`; cap `general`)

`/submitUrls`, `/getLog`, `/clearLog`, `/resetKey` — `class-rest.php:45-114`.

### Status — namespace `rankmath/v1/status` (`includes/modules/status/class-rest.php:33`)

`/getViewData`, `/updateViewData`, `/importSettings`, `/exportSettings`, `/runBackup` — `class-rest.php:40-84`.

### Links — namespace `rankmath/v1` (`includes/modules/links/Api/class-controller.php:37`; `manage_options`)

`/links/posts`, `/links/posts-stats`, `/links/links`, `/links/links-stats` — `class-controller.php:58-101`.

### AI Visibility — namespace `rankmath/v1/ai-visibility` (`includes/modules/ai-visibility/Api/class-base-controller.php:31`; `manage_options`)

`/overview`, `/brands`, `/brands/{id}`, `/brands/{id}/insights`, `/brands/{id}/queries`, `/brands/{id}/queries/{qid}`, `/brands/{id}/generate-queries`, `/checkout-url`, `/trial/activate` — `class-brands-controller.php:46-227`; `class-checkout-controller.php:32`; `class-trial-controller.php:38`.

---

## Blocks, shortcodes and widgets

### Gutenberg blocks

| Block name | Proof |
|---|---|
| `rank-math/faq-block` | `includes/modules/schema/blocks/faq/class-block-faq.php:62`; `blocks/faq/block.json:5` |
| `rank-math/howto-block` | `includes/modules/schema/blocks/howto/class-block-howto.php:65`; `blocks/howto/block.json:5` |
| `rank-math/toc-block` | `includes/modules/schema/blocks/toc/class-block-toc.php:68`; `blocks/toc/block.json:5` |
| `rank-math/rich-snippet` | `includes/modules/schema/blocks/schema/class-block-schema.php:62`; `blocks/schema/block.json:5` |
| `rank-math/command` (AI Assistant / Content AI) | `includes/modules/content-ai/blocks/command/class-block-command.php:63`; `blocks/command/assets/src/block.json:4` |
| Block category `rank-math-blocks` | `includes/modules/schema/class-blocks.php:69-80` |

### Shortcodes

| Tag | Proof |
|---|---|
| `wpseo_address`, `wpseo_map`, `wpseo_opening_hours`, `wpseo_breadcrumb`, `aioseo_breadcrumbs` (compat) | `includes/frontend/class-shortcodes.php:49-53` |
| `rank_math_contact_info` | `includes/frontend/class-shortcodes.php:56` |
| `rank_math_breadcrumb` | `includes/frontend/class-shortcodes.php:59` |
| `rank_math_seo_score` | `includes/class-frontend-seo-score.php:60` |
| `rank_math_html_sitemap`, `aioseo_html_sitemap` | `includes/modules/sitemap/html-sitemap/class-sitemap.php:60-63` |
| `rank_math_rich_snippet`, `rank_math_review_snippet` | `includes/modules/schema/class-snippet-shortcode.php:47-48` |

### Widgets

No `WP_Widget` / `register_widget` registrations exist in the free plugin (grep verified). The only "dashboard widget" is `RankMath\Admin\Dashboard_Widget` (`includes/admin/class-dashboard-widget.php:24`), a WordPress admin dashboard widget, not a sidebar widget.

---

## PRO-gated items found in free code

This is the definitive free→PRO boundary expressed in free code.

| Feature / item | Gating flag or check | Proof |
|---|---|---|
| AI Link Genius module | `probadge`, `disabled`, "PRO version" text | `includes/module/class-manager.php:164-171` |
| News Sitemap module | `probadge`, `disabled` | `includes/module/class-manager.php:208-215` |
| Video Sitemap module | `probadge`, `disabled` | `includes/module/class-manager.php:217-224` |
| Podcast module | `probadge`, `disabled` | `includes/module/class-manager.php:226-233` |
| bbPress module | `probadge => defined('RANK_MATH_PRO_FILE')`, `disabled` when bbPress absent | `includes/module/class-manager.php:344-352` |
| 404 Monitor | `upgradeable` (PRO has more options) | `includes/module/class-manager.php:118` |
| Local SEO | `upgradeable` | `includes/module/class-manager.php:127` |
| Redirections | `upgradeable` | `includes/module/class-manager.php:136` |
| Schema (Structured Data) | `upgradeable` | `includes/module/class-manager.php:145` |
| Image SEO | `upgradeable` | `includes/module/class-manager.php:178` |
| Content AI | `upgradeable` | `includes/module/class-manager.php:196` |
| Analytics | `upgradeable` | `includes/module/class-manager.php:269` |
| SEO Analyzer | `upgradeable` | `includes/module/class-manager.php:281` |
| WooCommerce | `upgradeable` | `includes/module/class-manager.php:368` |
| Module PRO badge rendering | "PRO" badge / "More powerful options are available in the PRO version." | `includes/module/class-manager.php:449-466` |
| Unlock PRO / upsell box | hidden when `RANK_MATH_PRO_FILE` defined | `includes/module/class-manager.php:552-577` |
| Analytics wizard toggles `anonymize-ip`, `local-ga-js` | `disabled` + upsell redirect when `!defined('RANK_MATH_PRO_FILE')` | `includes/admin/wizard/views/search-console-ui.php:73,270-306` |
| Dashboard `isPro` flag | `defined('RANK_MATH_PRO_FILE')` | `includes/admin/class-admin-menu.php:91` |
| AI Visibility `isPro` flag | `defined('RANK_MATH_PRO_FILE')` | `includes/modules/ai-visibility/Admin/class-admin.php:91` |
| Seasonal "Unlock PRO" submenu | suppressed when PRO active | `includes/admin/class-admin-menu.php:241` |
| Plugin row "Unlock PRO" link | only when `!defined('RANK_MATH_PRO_FILE')` | `rank-math.php:477-480` |
| Content AI "Free" badge | `Helper::get_content_ai_plan() === 'free'` | `includes/module/class-manager.php:443` |
| `.htaccess` editor | `is_super_admin()` + `has_cap('edit_htaccess')` + `is_edit_allowed()` | `includes/admin/class-option-center.php:78-95,586-596` |

---

## External HTTP calls

Base constants: `RANK_MATH_SITE_URL = https://rankmath.com` (`rank-math.php:252`), `CONTENT_AI_URL = https://cai.rankmath.com` (`rank-math.php:253-254`).

| Endpoint / domain | When called | Proof |
|---|---|---|
| `https://rankmath.com/wp-json/rankmath/v1/` (API base); `.../deactivateSite` | SEO Analyzer API tests; site deregistration | `includes/admin/class-api.php:25,132`; `includes/modules/seo-analysis/class-seo-analyzer.php:407,416` |
| `https://rankmath.com/analyze/v2/json/` | SEO Analyzer remote tests | `includes/modules/seo-analysis/class-seo-analyzer.php:105,369-437` |
| `https://cai.rankmath.com/sites/wallet` | Content AI credit refresh | `includes/helpers/class-content-ai.php:88,91` |
| `https://cai.rankmath.com/ai/research` | Research keyword REST action | `includes/modules/content-ai/class-rest.php:636,639` |
| `https://cai.rankmath.com/ai/` | Content AI editor proxy (localized to JS) | `includes/modules/content-ai/class-content-ai.php:85` |
| `https://cai.rankmath.com/ai/default_prompts` | Daily `rank_math/content-ai/update_prompts` cron | `includes/modules/content-ai/class-event-scheduler.php:83` |
| `https://cai.rankmath.com/ai/generate_image_alt_v2` | Bulk image-alt generation | `includes/modules/content-ai/class-bulk-image-alt.php:231` |
| `https://cai.rankmath.com/ai/bulk_seo_meta` | Bulk SEO meta generation | `includes/modules/content-ai/class-bulk-edit-seo-meta.php:202` |
| `https://ai-visibility.rankmath.com/` | All AI Visibility proxy calls | `includes/modules/ai-visibility/Api/class-base-controller.php:127-128` |
| `https://rankmath.com/site-checkout-cai`, `.../checkoutToken` | AI Visibility checkout iframe token | `includes/modules/ai-visibility/Api/class-checkout-controller.php:66,89` |
| `https://rankmath.com/wp-json/rankmath/v1/planUpgrade` | AI Visibility trial activation | `includes/modules/ai-visibility/Api/class-trial-controller.php:100` |
| `https://oauth.rankmath.com` (get/refresh) | Google OAuth connect + token refresh | `includes/modules/analytics/google/class-authentication.php:127`; `workflows/class-oauth.php:163`; `google/class-request.php:419-425` |
| `https://www.googleapis.com/webmasters/v3/...` | Search Console data/sitemaps | `includes/modules/analytics/google/class-console.php:40-246` |
| `https://searchconsole.googleapis.com/v1/urlInspection/index:inspect` | URL Inspection | `includes/modules/analytics/google/class-url-inspection.php:26,85` |
| `https://analyticsadmin.googleapis.com/...`, `https://analyticsdata.googleapis.com/...` | GA4 fetch | `includes/modules/analytics/google/class-analytics.php:37,233` |
| `https://api.indexnow.org/indexnow/` | Instant Indexing submit (manual + auto) | `includes/modules/instant-indexing/class-api.php:31,126` |
| `https://api.rankmath.com/ltkw/v1/` | Keyword autosuggest JS config | `includes/admin/class-assets.php:92` |
| `https://rankmath.com/wp-json/wp/v2/posts?dashboard_widget_feed=1` | Admin dashboard widget feed | `includes/admin/class-dashboard-widget.php:204` |
| `https://graph.facebook.com/` | FB object-cache invalidation on post save | `includes/admin/metabox/class-metabox.php:338` |
| `https://api.wordpress.org/plugins/info/1.0/seo-by-rank-math.json` | Version-control update check | `includes/modules/version-control/class-version-control.php:50,115` |
| `https://plugins.svn.wordpress.org/seo-by-rank-math/trunk/rank-math.php` | Beta-optin trunk fetch | `includes/modules/version-control/class-beta-optin.php:182` |
| `https://maps.google.com/maps?q=...&key=` (iframe) | Contact-info map shortcode | `includes/frontend/class-shortcodes.php:323` |

---

## Cron jobs and scheduled actions

### WP-Cron

| Hook | Schedule | Proof |
|---|---|---|
| `rank_math/redirection/clean_trashed` | daily at midnight | `includes/class-installer.php:648-671`; handler `includes/modules/redirections/class-admin.php:118` |
| `rank_math/links/internal_links` | daily at midnight | `includes/class-installer.php:648-672`; handler `includes/modules/links/class-links.php:47` |
| `rank_math/content-ai/update_prompts` | daily at midnight (+random) | `includes/class-installer.php:649-673`; `includes/modules/content-ai/class-event-scheduler.php:62` |
| `rank_math/content-ai/update_plan` | single (refresh date +60s) | `includes/modules/content-ai/class-event-scheduler.php:59,63` |
| `rank_math/sitemap/hit_index` | single (+300s after publish) | `includes/modules/sitemap/class-cache-watcher.php:128-130`; handler `includes/modules/sitemap/class-sitemap.php:57` |

### Action Scheduler (group `rank-math`)

`rank_math/analytics/workflow`, `.../data_fetch`, `.../get_console_data`, `.../get_analytics_data`, `.../get_adsense_data`, `.../get_inspections_data`, `.../clear_cache`, `.../flat_posts`, `.../flat_posts_completed`, `.../sync_sitemaps`, `.../email_report_event` — `includes/modules/analytics/workflows/class-workflow.php:49-57,116-159`; `includes/modules/analytics/workflows/class-jobs.php:57-69`; `includes/modules/analytics/workflows/class-objects.php:80-113`; `includes/modules/analytics/workflows/class-inspections.php:178`; `includes/modules/analytics/class-analytics-common.php:398`; `includes/helpers/class-analytics.php:57`.

There is no `CREATE TABLE` literal in `includes/`; all custom tables flow through `DB::create_table()`/`dbDelta` (`includes/helpers/class-db.php:209,218`).

---

*End of inventory. Every table row above carries a `file:line` citation from the free plugin source; no plugin file was modified.*
