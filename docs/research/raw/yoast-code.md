# Yoast SEO — code-verified feature inventory (Free + Premium)

Scope: the two plugins actually present on disk.
- Free: `wp-content/plugins/wordpress-seo/` — **version 28.4** (`wordpress-seo/wp-seo.php:8`)
- Premium: `wp-content/plugins/wordpress-seo-premium/` — **version 27.8** (`wordpress-seo-premium/wp-seo-premium.php` header, `define( 'WPSEO_PREMIUM_VERSION', '27.8' )` in `wordpress-seo-premium/wp-seo-premium.php`)

All paths below are relative to `wp-content/plugins/`. Every row cites file:line. Line numbers were read from the on-disk source; where an agent claim could not be confirmed it is marked "unsure".

---

## Admin screens

### Top-level menus and submenus

| Feature | Behaviour | Citation | Capability | Premium? |
|---|---|---|---|---|
| Yoast SEO top menu (`wpseo_dashboard`) | `add_menu_page('Yoast SEO: Dashboard')` | `wordpress-seo/admin/menu/class-admin-menu.php:55` | `wpseo_manage_options` (dynamic: first accessible submenu cap) | no |
| General (`wpseo_dashboard`) | React General/Dashboard app, position 0 | `wordpress-seo/src/general/user-interface/general-page-integration.php:204` | `wpseo_manage_options` | no |
| Settings (`wpseo_page_settings`) | React Settings app, position 1 | `wordpress-seo/src/integrations/settings-integration.php:371` | `wpseo_manage_options` | no |
| Integrations (`wpseo_integrations`) | React integrations app | `wordpress-seo/src/integrations/admin/integrations-page.php:141` | `wpseo_manage_options` | no |
| Plans / Licenses (`wpseo_licenses`) | React Plans/Licenses app, priority 7 (also network) | `wordpress-seo/src/plans/user-interface/plans-page-integration.php:137` | `wpseo_manage_options` | no |
| Search Console (`wpseo_search_console`) | Legacy GSC page; routable, menu item hidden | `wordpress-seo/admin/menu/class-admin-menu.php:91`, `wordpress-seo/admin/menu/class-base-menu.php:172` | `wpseo_manage_options` | no |
| Tools (`wpseo_tools`) | Legacy Tools overview | `wordpress-seo/admin/menu/class-admin-menu.php:96`, `wordpress-seo/admin/menu/class-menu.php:79` | `wpseo_manage_options` | no |
| Bulk editor (`wpseo_page_bulk_edit`) | React bulk editor; registered then hidden via `remove_submenu_page`; opened from Tools | `wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:223`, `:212` | `wpseo_manage_options` | no |
| Workouts (`wpseo_workouts`) | Free upsell Workouts app | `wordpress-seo/src/integrations/admin/workouts-integration.php:89` | `edit_others_posts` | no (upsell) |
| Redirects upsell (`wpseo_redirects`) | Free placeholder, only when Premium inactive | `wordpress-seo/src/integrations/admin/redirects-page-integration.php:96` | `edit_others_posts` | no |
| Redirects (`wpseo_redirects`) | Premium Redirect manager (`WPSEO_Redirect_Page::display`) | `wordpress-seo-premium/premium.php:336` | `wpseo_manage_redirects` | **yes** |
| Upgrade button (`wpseo_upgrade_sidebar`) | Sidebar upsell, hidden if Premium active (non-Woo) or Woo addon installed | `wordpress-seo/src/plans/user-interface/upgrade-sidebar-menu-integration.php:133` | `edit_posts` | no |
| AI Brand Insights (`wpseo_brand_insights` / `_premium`) | Sidebar external-link item; slug depends on `is_premium()` | `wordpress-seo/src/integrations/admin/brand-insights-page.php:65` | `edit_posts` | no (slug varies) |
| Tools > Yoast Redirects (`wpseo_redirects_tools`) | Hidden Tools entry redirecting to Redirects page | `wordpress-seo/src/integrations/admin/redirections-tools-page.php:64` | `edit_others_posts` | no |
| Installation success free (`wpseo_installation_successful_free`) | Hidden post-install redirect target | `wordpress-seo/src/integrations/admin/installation-success-integration.php:125` | `manage_options` | no |
| Old configurator (`wpseo_configurator`) | Hidden; 302 to `#/first-time-configuration` | `wordpress-seo/src/integrations/admin/old-configuration-integration.php:36` | `manage_options` | no |
| Site Kit setup (`wpseo_page_site_kit_set_up`) | Hidden; transient + safe-redirect interceptor | `wordpress-seo/src/dashboard/user-interface/setup/setup-url-interceptor.php:91` | `wpseo_manage_options` | no |
| Settings saved (`wpseo_page_settings_saved`) | Hidden dummy page for save flow | `wordpress-seo/src/integrations/settings-integration.php:402` | `wpseo_manage_options` | no |
| Premium thank-you (`wpseo_installation_successful`) | Hidden post-install redirect target | `wordpress-seo-premium/src/integrations/admin/thank-you-page-integration.php:82` | `manage_options` | **yes** |
| MyYoast proxy (`wpseo_myyoast_proxy`) | Hidden `add_dashboard_page('', '', ...)` serving analysis worker | `wordpress-seo/admin/class-my-yoast-proxy.php:55` | `read` | no |
| Network top menu | `add_menu_page('Network Settings - Yoast SEO')` | `wordpress-seo/admin/menu/class-network-admin-menu.php:33` | `wpseo_manage_network_options` | no |
| Network > General | Same slug as network top; renders `admin/pages/network.php` | `wordpress-seo/admin/menu/class-network-admin-menu.php:55` | `wpseo_manage_network_options` | no |
| Network > Edit Files (`wpseo_files`) | Only if `allow_system_file_edit() === true` | `wordpress-seo/admin/menu/class-network-admin-menu.php:62` | `wpseo_manage_network_options` | no |
| Capability normalizer | Rewrites `manage_options` submenu caps to `wpseo_manage_options` | `wordpress-seo/admin/menu/class-submenu-capability-normalize.php:35` | n/a (filter) | no |
| Free capability registration | Registers `wpseo_bulk_edit`, `wpseo_edit_advanced_metadata`, `wpseo_manage_options`, `view_site_health_checks` | `wordpress-seo/admin/capabilities/class-register-capabilities.php:39` | n/a | no |
| Premium capability registration | Registers `wpseo_manage_redirects` for admin/editor/wpseo_editor/wpseo_manager | `wordpress-seo-premium/classes/premium-register-capabilities.php:30` | n/a | **yes** |

### Settings pages, tabs, `register_setting`

| Feature | Behaviour | Citation | Capability | Premium? |
|---|---|---|---|---|
| Settings `register_setting` | Registers `wpseo`, `wpseo_titles`, `wpseo_social`, `wpseo_llmstxt` under `wpseo_page_settings` | `wordpress-seo/src/integrations/settings-integration.php:350` | `wpseo_manage_options` (`option_page_capability_*` filter at `wordpress-seo/admin/class-admin.php:164`) | no |
| Redirects `register_setting` | `register_setting('yoast_wpseo_redirect_options','wpseo_redirect')` | `wordpress-seo-premium/premium.php:384` | inherits `wpseo_manage_redirects` page | **yes** |
| Tools > Import/Export (`tool=import-export`) | Import SEO / WPSEO import / export views | `wordpress-seo/admin/pages/tools.php:88` | `wpseo_manage_options` | no |
| Tools > File editor (`tool=file-editor`) | robots.txt/.htaccess editor (only if `allow_system_file_edit && !multisite`) | `wordpress-seo/admin/pages/tools.php:34` | `wpseo_manage_options` | no |
| Tools > Bulk editor link | Links to `wpseo_page_bulk_edit`; legacy `tool=bulk-editor` list tables remain | `wordpress-seo/admin/pages/tools.php:41`, `wordpress-seo/admin/class-bulk-editor-list-table.php:228` | `wpseo_manage_options` (new) / per-post-type `edit_posts`,`edit_others_posts` (legacy, `class-bulk-editor-list-table.php:194`) | no |
| Network tabs | General, Features, Integrations, Crawl settings (save), Restore Site (no save) | `wordpress-seo/admin/pages/network.php:18` | `wpseo_manage_network_options` | no |
| Network Crawl tab content | Injected via `wpseo_settings_tab_crawl_cleanup_network` | `wordpress-seo/src/integrations/admin/crawl-settings-integration.php:97` | `wpseo_manage_network_options` | no |
| Dashboard First-time-config tab | Injected via `wpseo_settings_tabs_dashboard` | `wordpress-seo/src/integrations/admin/first-time-configuration-integration.php:114` | `wpseo_manage_options` | no |
| Redirects regex tab | `$_GET['tab']==='regex'` switches old/new URL rendering; full tab list not statically enumerated (unsure beyond plain/regex/settings) | `wordpress-seo-premium/classes/redirect/redirect-table.php:263` | `wpseo_manage_redirects` | **yes** |
| Settings React routes (`#/site-features`, `#/site-representation`, …) | Client-side only; no PHP tab registration found (unsure) | — | `wpseo_manage_options` | no |

### Admin columns

| Feature | Behaviour | Citation | Capability | Premium? |
|---|---|---|---|---|
| Posts `wpseo-score`, `wpseo-score-readability`, `wpseo-title`, `wpseo-metadesc`, `wpseo-focuskw` | Added/sorted; title/metadesc/focuskw hidden by default | `wordpress-seo/admin/class-meta-columns.php:102`, `:863`, `:216`, `:242` | none explicit (gated by `display_metabox()`) | no |
| Terms `wpseo-score`, `wpseo-score-readability` | Inserted after `description` | `wordpress-seo/admin/taxonomy/class-taxonomy-columns.php:86`, `:59` | none explicit | no |
| Posts `wpseo-links` (outgoing), `wpseo-linked` (incoming) | Link counts from indexables; sortable | `wordpress-seo/src/integrations/admin/link-count-columns-integration.php:132`, `:119`, `:252` | none explicit | no |
| Posts `wpseo-cornerstone` | Sortable cornerstone star column | `wordpress-seo-premium/src/integrations/admin/cornerstone-column-integration.php:107` | conditional `Cornerstone_Enabled_Conditional`:54 | **yes** |
| Terms cornerstone | Term cornerstone column | `wordpress-seo-premium/src/integrations/admin/cornerstone-taxonomy-column-integration.php:76` | none explicit | **yes** |
| Posts `wpseo-inclusive-language` | Sortable inclusive-language score | `wordpress-seo-premium/src/integrations/admin/inclusive-language-column-integration.php:126` | conditionals :65 | **yes** |
| Terms inclusive-language | Term inclusive-language column | `wordpress-seo-premium/src/integrations/admin/inclusive-language-taxonomy-column-integration.php:84` | none explicit | **yes** |

### Bulk actions

| Feature | Behaviour | Citation | Capability | Premium? |
|---|---|---|---|---|
| Posts overview `Bulk edit ↗` (`yoast_bulk_editor`, optgroup `Yoast SEO`) | Redirects selection to `wpseo_page_bulk_edit?content_type=&post_ids=&selected_count=`; hidden on trash | `wordpress-seo/src/bulk-editor/user-interface/posts-overview-bulk-actions-integration.php:21`, `:84`, `:109` | registered under `wpseo_manage_options`:79 | no |
| Redirects table `Delete` | Checkbox `wpseo_redirects_bulk_delete[]` + `get_bulk_actions()->delete` | `wordpress-seo-premium/classes/redirect/redirect-table.php:314`, `:242` | `wpseo_manage_redirects` (page + ajax `redirect-ajax.php:189`) | **yes** |
| Legacy bulk title/description tables | No `get_bulk_actions`; inline AJAX save only | `wordpress-seo/admin/ajax.php:194`, `wordpress-seo/admin/class-bulk-editor-list-table.php:194` | per-post-type `edit_posts`/`edit_others_posts`; `wpseo_bulk_edit` role exists but not checked here | no |
| Redirects table columns/sort | `cb`, `type`, `old`, `new`; sortable `old,new,type` | `wordpress-seo-premium/classes/redirect/redirect-table.php:135`, `:211`, `:161` | `wpseo_manage_redirects` | **yes** |

---

## Options

WordPress option keys registered/created by the free plugin. Source of the canonical list: `WPSEO_Options::$options` at `wordpress-seo/inc/options/class-wpseo-options.php:28-35`.

| Option key | Source class / declaration | Purpose | Autoload | Premium? |
|---|---|---|---|---|
| `wpseo` | `wordpress-seo/inc/options/class-wpseo-option-wpseo.php:18` | Core settings (site features, tracking, etc.) | no explicit flag; written via `update_option()` without autoload arg (`wordpress-seo/inc/options/class-wpseo-option.php:588`, `:676`) | no |
| `wpseo_titles` | `wordpress-seo/inc/options/class-wpseo-option-titles.php:20` | Title/meta templates, per-post-type settings | no explicit flag (as above) | no |
| `wpseo_social` | `wordpress-seo/inc/options/class-wpseo-option-social.php:18` | Social profiles, OG/Twitter defaults | no explicit flag | no |
| `wpseo_ms` | `wordpress-seo/inc/options/class-wpseo-option-ms.php:21` | Multisite network settings (`multisite_only = true`, `include_in_all = false`) | no explicit flag | no |
| `wpseo_taxonomy_meta` | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:18` | Per-term SEO data (see Meta keys) | no explicit flag | no |
| `wpseo_llmstxt` | `wordpress-seo/inc/options/class-wpseo-option-llmstxt.php:20` | llms.txt selection settings | no explicit flag | no |
| `wpseo_tracking_only` | `wordpress-seo/inc/options/class-wpseo-option-tracking-only.php:20` | Tracking-only timestamps (task list, frontend inspector) | no explicit flag | no |
| `wpseo_premium` | `wordpress-seo-premium/classes/premium-option.php:18` | Premium state: `prominent_words_indexing_completed`, `workouts`, `should_redirect_after_install`, `activation_redirect_timestamp`, `dismiss_update_premium_notification` (`:29-33`) | no explicit flag | **yes** |
| `wpseo_redirect` | `wordpress-seo-premium/classes/premium-redirect-option.php:18` | Redirect settings `disable_php_redirect`, `separate_file` (`:29-30`) | no explicit flag | **yes** |
| `wpseo-premium-redirects-base` | `wordpress-seo-premium/classes/redirect/redirect-option.php:28` | Stored redirect table (actual redirects) | no explicit flag | **yes** |
| `wpseo-premium-redirects-export-plain` | `wordpress-seo-premium/classes/redirect/redirect-option.php:35` | Cached plain redirects for file export | no explicit flag | **yes** |
| `wpseo-premium-redirects-export-regex` | `wordpress-seo-premium/classes/redirect/redirect-option.php:42` | Cached regex redirects for file export | no explicit flag | **yes** |
| `wpseo-premium-redirects` / `wpseo-premium-redirects-regex` | `wordpress-seo-premium/classes/redirect/redirect-option.php:16`, `:21` | Legacy redirect options (migration only) | no explicit flag | **yes** |
| `index_now_key` | stored inside `wpseo` option; `wordpress-seo-premium/src/initializers/index-now-key.php:61`, `:121` | IndexNow verification key | with parent option | **yes** |

Note on autoload: neither plugin passes an explicit autoload argument anywhere (`grep autoload` in free `inc/` + `src/` finds only the reader `WPSEO_Options::get_autoloaded_option()`, `wordpress-seo/inc/options/class-wpseo-options.php:349`). Options therefore inherit the WordPress default autoload behaviour. Additional option keys **read but not created** by free code: `wpseo_indexation`, `wpseo_license_server_version`, `wpseo_local`, `wpseo_onpage`, `wpseo_premium`, `wpseo_ryte`, `wpseo_xml` (created by premium/add-ons or legacy).

---

## Meta keys

### Post meta (free)

All keys use the prefix `_yoast_wpseo_` (`wordpress-seo/inc/class-wpseo-meta.php:45`). The canonical key set is `WPSEO_Meta::$meta_fields` (`wordpress-seo/inc/class-wpseo-meta.php:102-215`); every key is registered with `register_meta('post', ...)` at `wordpress-seo/inc/class-wpseo-meta.php:293` and `:305`.

| Meta key (`_yoast_wpseo_` + suffix) | Purpose | Citation |
|---|---|---|
| `focuskw` | Focus keyphrase | `wordpress-seo/inc/class-wpseo-meta.php:103` |
| `title` | SEO title | `:109` |
| `metadesc` | Meta description | `:115` |
| `linkdex` | Legacy linkdex score | `:123`, `:202` |
| `content_score` | Readability/content score | `:127` |
| `inclusive_language_score` | Inclusive-language score | `:131` |
| `seo_title_score` | SEO-title score | `:135` |
| `meta_description_score` | Meta-description score | `:139` |
| `is_cornerstone` | Cornerstone flag | `:143` |
| `meta-robots-noindex` | noindex directive | `:149` |
| `meta-robots-nofollow` | nofollow directive | `:158` |
| `meta-robots-adv` | Advanced robots directives | `:166` |
| `bctitle` | Breadcrumb title | `:175` |
| `canonical` | Canonical URL override | `:179` |
| `redirect` | Legacy post redirect | `:183` |
| `schema_page_type` | WebPage type | `:190` |
| `schema_article_type` | Article type | `:194` |
| `is_content_planner_banner_rendered` | Content-planner banner state | `:208` |
| `is_content_planner_banner_dismissed` | Content-planner banner state | `:212` |

Additional post meta written directly by free code (not in the array above):

| Meta key | Purpose | Citation |
|---|---|---|
| `_yoast_wpseo_primary_{taxonomy}` | Primary term selection | `wordpress-seo/src/` primary-term save; write via `$meta_prefix . 'primary_' . $taxonomy` (grep `update_post_meta`) |
| `_yoast_wpseo_meta-robots-adv` | Written on save | `wordpress-seo/inc/class-wpseo-meta.php` (grep `update_post_meta` `_yoast_wpseo_meta-robots-adv`) |
| `_yoast_wpseo_meta-robots-{directive}` | Per-directive robots meta | grep `update_post_meta( $post_meta->post_id, '_yoast_wpseo_meta-robots-'` |
| `_yoast_wpseo_post_image_cache` | Cached post images for analysis | grep key in `wordpress-seo/src/` |
| `_yst_content_links_processed` | Link-indexing processed marker | grep key in `wordpress-seo/src/` |
| `_yoast_wpseo_wistia_embed_permission` | Wistia embed consent | grep key in `wordpress-seo/src/` |
| `_yoast_wpseo_ai_consent` | AI data consent | grep key in `wordpress-seo/src/ai/consent/` |
| `_yoast_wpseo_ai_content_planner_banner_permanently_dismissed` | Banner dismissal | grep key in `wordpress-seo/src/ai/content-planner/` |
| `_yoast_wpseo_bulk_editor_tour_opt_in_notification_seen` | Tour notification seen | grep key in `wordpress-seo/src/bulk-editor/` |
| `_yoast_wpseo_introductions` | Introductions dismissal state | grep key in `wordpress-seo/src/introductions/` |
| `_yoast_wpseo_sitemap-include`, `_yoast_wpseo_sitemap-prio` | Legacy sitemap inclusion/priority | grep keys |
| `yoast-structured-data-blocks-images-cache` | Structured-data block image cache | grep `update_post_meta( $post_id, 'yoast-structured-data-blocks-images-cache'` |

### Term meta (free)

Important: free Yoast stores per-term SEO data in the **option** `wpseo_taxonomy_meta`, not in `wp_termmeta`. Defaults per term: `wpseo_taxonomy_meta` → `WPSEO_Taxonomy_Meta::$defaults_per_term` (`wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:52-76`).

| Key (inside `wpseo_taxonomy_meta[taxonomy][term_id]`) | Purpose | Citation |
|---|---|---|
| `wpseo_title` | Term SEO title | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:54` |
| `wpseo_desc` | Term meta description | `:55` |
| `wpseo_canonical` | Term canonical override | `:56` |
| `wpseo_bctitle` | Term breadcrumb title | `:57` |
| `wpseo_noindex` | Term robots noindex | `:58` |
| `wpseo_focuskw` | Term focus keyphrase | `:59` |
| `wpseo_linkdex` | Legacy term score | `:60` |
| `wpseo_content_score` | Term content score | `:61` |
| `wpseo_inclusive_language_score` | Term inclusive-language score | `:62` |
| `wpseo_focuskeywords` | Multiple keyphrases JSON | `:63` |
| `wpseo_keywordsynonyms` | Synonyms JSON | `:64` |
| `wpseo_is_cornerstone` | Term cornerstone flag | `:65` |
| `wpseo_opengraph-title`, `-description`, `-image`, `-image-id` | Term OG overrides | `:68-71` |
| `wpseo_twitter-title`, `-description`, `-image`, `-image-id` | Term Twitter overrides | `:72-75` |

### User meta

| Meta key | Purpose | Citation |
|---|---|---|
| `wpseo_title` | Author archive SEO title | `wordpress-seo/src/user-meta/framework/custom-meta/author-title.php:45`, `:54` |
| `wpseo_metadesc` | Author archive meta description | `wordpress-seo/src/user-meta/framework/custom-meta/author-metadesc.php:45`, `:54` |
| `wpseo_noindex_author` | noindex author archives | `wordpress-seo/src/user-meta/framework/custom-meta/noindex-author.php:45`, `:54` |
| `wpseo_content_analysis_disable` | Disable content analysis for user | `wordpress-seo/src/user-meta/framework/custom-meta/content-analysis-disable.php:45`, `:54` |
| `wpseo_keyword_analysis_disable` | Disable keyphrase analysis for user | `wordpress-seo/src/user-meta/framework/custom-meta/keyword-analysis-disable.php:45`, `:54` |
| `wpseo_inclusive_language_analysis_disable` | Disable inclusive-language analysis | `wordpress-seo/src/user-meta/framework/custom-meta/inclusive-language-analysis-disable.php:45`, `:54` |
| `wpseo_pronouns` | Author pronouns | `wordpress-seo/src/user-meta/framework/custom-meta/author-pronouns.php:45`, `:54` |
| `wpseo_author_title`, `wpseo_author_metadesc`, `wpseo_author_pronouns` | Alternate meta-key getters | same files `:54` |
| `_yoast_wpseo_profile_updated` | Profile-update timestamp | grep `update_user_meta( $user->ID, '_yoast_wpseo_profile_updated'` |
| `_yoast_wpseo_ai_generator_access_jwt` | Yoast AI access JWT | `wordpress-seo/src/ai/authorization/infrastructure/access-token-user-meta-repository-interface.php:14` |
| `_yoast_wpseo_ai_generator_refresh_jwt` | Yoast AI refresh JWT | `wordpress-seo/src/ai/authorization/infrastructure/refresh-token-user-meta-repository-interface.php:14` |
| `_yoast_wpseo_ai_generator_code_verifier_*` | AI PKCE code verifier (per blog) | grep `'yoast_wpseo_ai_generator_code_verifier_for_blog_'` |
| Additional contact methods: `facebook`, `instagram`, `linkedin`, `myspace`, `pinterest`, `soundcloud`, `tumblr`, `twitter` (X), `wikipedia`, `youtube` | Social profile URLs (registered via `user_contactmethods`) | `wordpress-seo/src/user-meta/framework/additional-contactmethods/*.php` (e.g. `facebook.php:19`, `x.php:19`, `wikipedia.php:19`) |
| `wpseo_dismiss_{notice}`, `wpseo-remove-{id}` | Per-user notice dismissal | grep `update_user_meta( get_current_user_id(), 'wpseo_dismiss_'` / `'wpseo-remove-'` |
| `_yoast_wpseo_introductions` | Introduction modal dismissals | grep `_yoast_wpseo_introductions` |

### Premium meta keys

| Meta key | Purpose | Citation |
|---|---|---|
| `_yst_prominent_words_version` | Prominent-words analysis version marker (post meta) | `wordpress-seo-premium/classes/premium-prominent-words-versioning.php:16`, `:39` |
| `_yoast_post_redirect_info` | Auto-redirect info for a post (post meta) | grep `update_post_meta( $post_id, '_yoast_post_redirect_info'` |
| `_yoast_term_redirect_info` | Auto-redirect info for a term (term meta) | grep `update_term_meta( $term_id, '_yoast_term_redirect_info'` |
| `wpseo_old_post_url` | Old post URL for redirect creation | grep `wpseo_old_post_url` |
| `wpseo_old_term_url` | Old term URL for redirect creation | grep `wpseo_old_term_url` |
| `wpseo_words_for_linking` | Prominent words used for link suggestions | grep `wpseo_words_for_linking` |
| `_yoast_indexnow_last_ping` | Last IndexNow ping timestamp (post meta) | `wordpress-seo-premium/src/integrations/index-now-ping.php:114`, `:155` |
| `wpseo_user_schema` | Premium user schema setting (user meta) | grep `update_user_meta( $user_id, 'wpseo_user_schema'` |
| `mastodon` | Mastodon profile URL (user contact method) | `wordpress-seo-premium/src/user-meta/framework/additional-contactmethods/mastodon.php:19` |

---

## Custom tables

Table names resolve through `Yoast\WP\Lib\Model::get_table_name()` which prepends `$wpdb->prefix . 'yoast_'` (`wordpress-seo/lib/model.php:136-144`) unless a raw/`base_prefix` name is used.

| Table | Purpose | Citation | Premium? |
|---|---|---|---|
| `wp_yoast_indexable` | Primary indexable storage (per post/term/user/archive) | `wordpress-seo/src/config/migrations/20171228151840_WpYoastIndexable.php:46` | no |
| `wp_yoast_indexable_hierarchy` | Indexable ancestor hierarchy | `wordpress-seo/src/config/migrations/20191011111109_WpYoastIndexableHierarchy.php:28` | no |
| `wp_yoast_primary_term` | Primary term per post/taxonomy | `wordpress-seo/src/config/migrations/20171228151841_WpYoastPrimaryTerm.php:28` | no |
| `wp_yoast_seo_links` | Internal link graph | `wordpress-seo/src/config/migrations/20200617122511_CreateSEOLinksTable.php:32` | no |
| `wp_yoast_migrations` | Schema-migration bookkeeping table | `wordpress-seo/lib/migrations/adapter.php:123` (`Model::get_table_name( 'migrations' )`) | no |
| `wp_yoast_expiring_store` | Network-wide expiring key/value store (uses `$wpdb->base_prefix`) | `wordpress-seo/src/config/migrations/20260325155530_CreateExpiringStoreTable.php:27`, `:72` | no |
| `wp_yoast_prominent_words` | Prominent words per indexable (stem, weight) | `wordpress-seo-premium/src/config/migrations/20190715101200_WpYoastPremiumImprovedInternalLinking.php:95` (`Model::get_table_name( 'Prominent_Words' )`), model `wordpress-seo-premium/src/models/prominent-words.php:15` | **yes** |

Note: migration tracking also uses the option `yoast_migrations_` (`wordpress-seo/src/config/migration-status.php:17`); the `wp_yoast_migrations` table still exists via the migration adapter.

---

## Schema pieces and types

Schema pieces (free): `wordpress-seo/src/generators/schema/`. Emitted `@type` values observed:

| Piece class | `@type`(s) emitted | Citation | Premium? |
|---|---|---|---|
| `Article` | dynamic `schema_article_type` (one of `ARTICLE_TYPES`) | `wordpress-seo/src/generators/schema/article.php:44`; CommentAction at `:183` | no |
| `Author` (extends Person) | `Person` | `wordpress-seo/src/generators/schema/author.php:45` | no |
| `Person` | `Person` / `Organization` (`$this->type`) | `wordpress-seo/src/generators/schema/person.php:136` | no |
| `Organization` | `Organization` | `wordpress-seo/src/generators/schema/organization.php:37` | no |
| `WebPage` | dynamic `schema_page_type` (one of `PAGE_TYPES`); `ReadAction`; CollectionPage branch | `wordpress-seo/src/generators/schema/webpage.php:32`, `:139`, `:151` | no |
| `WebSite` | `WebSite`; `SearchAction` + `EntryPoint` + `PropertyValueSpecification` | `wordpress-seo/src/generators/schema/website.php:28`, `:88`, `:90`, `:94` | no |
| `Breadcrumb` | `BreadcrumbList` + `ListItem` | `wordpress-seo/src/generators/schema/breadcrumb.php:98`, `:114` | no |
| `FAQ` | `FAQPage` (context), `Question`, `Answer` | `wordpress-seo/src/generators/schema/faq.php:86`, `:107` | no |
| `HowTo` | `HowTo`, `HowToStep`, `HowToDirection` | `wordpress-seo/src/generators/schema/howto.php:178`, `:70`, `:135` | no |
| `MainImage` | `ImageObject` fragment | `wordpress-seo/src/generators/schema/main-image.php:8` | no |
| `Organization_Schema_Integration` | `QuantitativeValue` fragment added to Organization | `wordpress-seo-premium/src/integrations/organization-schema-integration.php:105` | **yes** |
| `Publishing_Principles_Schema_Integration` | `publishingPrinciples` policy property | `wordpress-seo-premium/src/integrations/publishing-principles-schema-integration.php:113` | **yes** |

Selectable types (config):
- `WPSEO\WP\SEO\Config\Schema_Types::PAGE_TYPES` — `WebPage`, `ItemPage`, `AboutPage`, `FAQPage`, `QAPage`, `ProfilePage`, `ContactPage`, `MedicalWebPage`, `CollectionPage`, `CheckoutPage`, `RealEstateListing`, `SearchResultsPage` (`wordpress-seo/src/config/schema-types.php:17`).
- `...::ARTICLE_TYPES` — `Article`, `BlogPosting`, `SocialMediaPosting`, `NewsArticle`, `AdvertiserContentArticle`, `SatiricalArticle`, `ScholarlyArticle`, `TechArticle`, `Report`, `None` (`wordpress-seo/src/config/schema-types.php:39`).

---

## Sitemaps

| Sitemap type | Behaviour | Citation | Premium? |
|---|---|---|---|
| post-type (posts/pages/CPT) | Per-post-type paged sitemaps; provider registered at plugin init | `wordpress-seo/inc/sitemaps/class-post-type-sitemap-provider.php:83`; registered `wordpress-seo/inc/sitemaps/class-sitemaps.php:124` | no |
| taxonomy (category/tag/terms) | Per-taxonomy paged sitemaps | `wordpress-seo/inc/sitemaps/class-taxonomy-sitemap-provider.php:46`; registered `class-sitemaps.php:125` | no |
| author | Author-archive sitemap | `wordpress-seo/inc/sitemaps/class-author-sitemap-provider.php:20`; registered `class-sitemaps.php:126` | no |
| post-type archive link | Archive URL prepended on first page | `wordpress-seo/inc/sitemaps/class-post-type-sitemap-provider.php:405` | no |
| index (`sitemap_index.xml`) | Root index listing sub-sitemaps | `wordpress-seo/inc/sitemaps/class-sitemaps.php:404`; rewrite `wordpress-seo/inc/sitemaps/class-sitemaps-router.php:39` | no |
| XSL stylesheet | `*-sitemap.xsl` rendering | `wordpress-seo/inc/sitemaps/class-sitemaps-router.php:41` | no |
| image (inline) | Image extensions inside post sitemaps, filterable | `wordpress-seo/inc/sitemaps/class-post-type-sitemap-provider.php:47` | no |
| External providers | Third parties can add providers via `wpseo_sitemaps_providers` (used by News/Video add-ons) | `wordpress-seo/inc/sitemaps/class-sitemaps.php:129` | no (add-on hook) |
| redirect filter | Premium removes redirected URLs and clears sitemap cache | `wordpress-seo-premium/classes/redirect/redirect-sitemap-filter.php:35`, `:46`, `:65` | **yes** |

---

## Analysis assessments

Free ships the analysis compiled into `wordpress-seo/js/dist/externals/analysis.js` (no `packages/yoastseo/src/scoring/assessments/` source is present in v28.4; `wordpress-seo/packages/` contains only `js/images/`). Premium ships `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js` which registers the premium-only assessments.

### SEO assessments

| Assessment | Behaviour | Citation | Premium-only? |
|---|---|---|---|
| `introductionKeyword` | Keyphrase/synonyms in first paragraph | `wordpress-seo/js/dist/externals/analysis.js:155` | no (synonym-aware in Premium) |
| `keyphraseLength` | Keyphrase word-count within limits | `wordpress-seo/js/dist/externals/analysis.js:90` | no |
| `keyphraseDensity` | Keyphrase/synonym density within min/max | `wordpress-seo/js/dist/externals/analysis.js:242` | no (synonym-aware in Premium) |
| `keywordDensity` | Related-keyphrase variant (`isRelatedKeyphrase`) | `wordpress-seo/js/dist/externals/analysis.js:353` | no (Premium scores extra keyphrases) |
| `metaDescriptionKeyword` | Keyphrase/synonyms in meta description | `wordpress-seo/js/dist/externals/analysis.js:371` | no |
| `metaDescriptionLength` | Meta description length 120–156 chars | `wordpress-seo/js/dist/externals/analysis.js:1` | no |
| `slugKeyword` / `urlKeyword` | Keyphrase in slug/URL | `wordpress-seo/js/dist/externals/analysis.js:85`, `:90` | no |
| `keyphraseInSEOTitle` | Exact keyphrase at start of SEO title | `wordpress-seo/js/dist/externals/analysis.js:109` | no |
| `titleWidth` | SEO title pixel width fits SERP | `wordpress-seo/js/dist/externals/analysis.js:366` | no |
| `imageKeyphrase` | Keyphrase/synonyms in image alts | `wordpress-seo/js/dist/externals/analysis.js:33` | no |
| `images` (`ImageCountAssessment`) | Enough content images | `wordpress-seo/js/dist/externals/analysis.js:201` | no |
| `imageAltTags` (`ImageAltTagsAssessment`) | Images have alt attributes | `wordpress-seo/js/dist/externals/analysis.js:227` | no |
| `subheadingsKeyword` | Keyphrase/synonyms in H2/H3 | `wordpress-seo/js/dist/externals/analysis.js:15` | no |
| `keyphraseDistribution` | Keyphrase/synonyms evenly distributed | `wordpress-seo/js/dist/externals/analysis.js:33`; registered `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23` | **yes** |
| `functionWordsInKeyphrase` | Keyphrase is not only function words | `wordpress-seo/js/dist/externals/analysis.js:397` | no |
| `textCompetingLinks` | No competing links with same anchor/keyphrase | `wordpress-seo/js/dist/externals/analysis.js:55` | no |
| `internalLinks` | Enough internal links | `wordpress-seo/js/dist/externals/analysis.js:176` | no |
| `externalLinks` (`OutboundLinksAssessment`) | Outbound links present/followed | `wordpress-seo/js/dist/externals/analysis.js:362` | no |
| `productSKU` (`ProductSKUAssessment`) | WooCommerce SKU present | `wordpress-seo/js/dist/externals/analysis.js:362` | no |
| `productIdentifier` (`ProductIdentifiersAssessment`) | Product GTIN/identifier present | `wordpress-seo/js/dist/externals/analysis.js:90` | no |
| `textTitleAssessment` | Post/term title block exists | `wordpress-seo/js/dist/externals/analysis.js:85`; registered `register-premium-assessments-2780.min.js:23` | **yes** |
| `listsPresence` (`ListAssessment`) | Content contains a list where relevant | `wordpress-seo/js/dist/externals/analysis.js:85` | no |
| `textLength` (`TextLengthAssessment`) | Word count meets minimum (cornerstone stricter) | `wordpress-seo/js/dist/externals/analysis.js:232` | no |
| `textPresence` (`TextPresenceAssessment`) | Boilerplate: text present at all | `wordpress-seo/js/dist/externals/analysis.js:227` | no |

### Readability assessments

| Assessment | Behaviour | Citation | Premium-only? |
|---|---|---|---|
| `textTransitionWords` | Enough transition words | `wordpress-seo/js/dist/externals/analysis.js:353` | no |
| `passiveVoice` | Passive-voice share under max | `wordpress-seo/js/dist/externals/analysis.js:27` | no |
| `sentenceBeginnings` | No 3+ consecutive same-word starts | `wordpress-seo/js/dist/externals/analysis.js:77` | no |
| `textSentenceLength` (`SentenceLengthInTextAssessment`) | Sentences under recommended words | `wordpress-seo/js/dist/externals/analysis.js:161` | no |
| `textParagraphTooLong` (`ParagraphTooLongAssessment`) | Paragraphs under max words | `wordpress-seo/js/dist/externals/analysis.js:189` | no |
| `subheadingsTooLong` (`SubheadingDistributionTooLongAssessment`) | Sections under max words with subheadings | `wordpress-seo/js/dist/externals/analysis.js:180` | no |
| `wordComplexity` | Complex-word share under max (language-gated) | registered `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:1`, `:23` | **yes** |
| `textAlignment` (`TextAlignmentAssessment`) | No long center-aligned sections | registered `register-premium-assessments-2780.min.js:23` | **yes** |

Not scored assessments: `prominentWords` is research/indexation only (`wordpress-seo-premium/src/integrations/admin/prominent-words/indexing-integration.php:183`; bundle enqueued `wordpress-seo-premium/classes/premium-metabox.php:154`). `InclusiveLanguageAssessment` is a separate inclusive-language category. Multi-keyword/synonyms are not a separate assessment — they modify existing keyphrase assessments via `wordpress-seo-premium/classes/multi-keyword.php:11`, `:20-26`, `:54-58`, `:92-94`, `:112-115`.

---

## REST routes

Namespace `yoast/v1` — `wordpress-seo/src/main.php:32`.

### Free

| Route | Behaviour | Citation |
|---|---|---|
| `POST get_head` | Returns rendered `<head>` for a URL (headless) | `wordpress-seo/src/routes/indexables-head-route.php:22` / reg `:73` |
| `GET supported-features` | Lists active editor/support features | `wordpress-seo/src/routes/supported-features-route.php:19` / `:44` |
| `GET workouts` | Lists SEO workouts / cornerstone config | `wordpress-seo/src/routes/workouts-route.php:23` / `:65` |
| `POST semrush/authenticate` | Stores SEMrush OAuth token | `wordpress-seo/src/routes/semrush-route.php:30` / `:125` |
| `POST semrush/country_code` | Saves SEMrush country code | `wordpress-seo/src/routes/semrush-route.php:37` / `:139` |
| `GET semrush/related_keyphrases` | Fetches SEMrush related keyphrases | `wordpress-seo/src/routes/semrush-route.php:44` / `:156` |
| `POST indexing/posts` | Indexes posts batch | `wordpress-seo/src/routes/indexing-route.php:80` / `:288` |
| `POST indexing/terms` | Indexes terms batch | `wordpress-seo/src/routes/indexing-route.php:94` / `:291` |
| `POST indexing/post-type-archives` | Indexes post-type archives | `wordpress-seo/src/routes/indexing-route.php:108` / `:294` |
| `POST indexing/general` | General indexables pass | `wordpress-seo/src/routes/indexing-route.php:122` / `:297` |
| `POST indexing/prepare` | Prepares background indexing | `wordpress-seo/src/routes/indexing-route.php:66` / `:300` |
| `POST indexing/indexables-complete` | Marks indexables pass complete | `wordpress-seo/src/routes/indexing-route.php:52` / `:303` |
| `POST indexing/complete` | Marks full indexing complete | `wordpress-seo/src/routes/indexing-route.php:38` / `:306` |
| `POST link-indexing/posts` | Inbound-link counts for posts | `wordpress-seo/src/routes/indexing-route.php:136` / `:309` |
| `POST link-indexing/terms` | Inbound-link counts for terms | `wordpress-seo/src/routes/indexing-route.php:150` / `:312` |
| `GET wincher/authorization-url` | Returns Wincher OAuth URL | `wordpress-seo/src/routes/wincher-route.php:30` / `:132` |
| `POST wincher/authenticate` | Stores Wincher token | `wordpress-seo/src/routes/wincher-route.php:37` / `:150` |
| `POST wincher/keyphrases/track` | Starts tracking keyphrase | `wordpress-seo/src/routes/wincher-route.php:44` / `:163` |
| `GET wincher/keyphrases` | Lists tracked keyphrases/rankings | `wordpress-seo/src/routes/wincher-route.php:51` / `:182` |
| `POST wincher/keyphrases/untrack` | Stops tracking keyphrase | `wordpress-seo/src/routes/wincher-route.php:58` / `:190` |
| `GET wincher/account/limit` | Checks Wincher limit | `wordpress-seo/src/routes/wincher-route.php:65` / `:198` |
| `GET wincher/account/upgrade-campaign` | Wincher upsell campaign | `wordpress-seo/src/routes/wincher-route.php:72` / `:206` |
| `POST import/{plugin}/{type}` | Imports SEO data from other plugins | `wordpress-seo/src/routes/importing-route.php:28` / `:64` |
| `GET meta/search` | Searches posts/terms for metabox linking | `wordpress-seo/src/routes/meta-search-route.php:22` / `:38` |
| `POST integrations/set_active` | Enables/disables integration toggle | `wordpress-seo/src/routes/integrations-route.php:23,30` / `:69` |
| `POST configuration/site_representation` | FTC org/person | `wordpress-seo/src/routes/first-time-configuration-route.php:23,30` / `:125` |
| `POST configuration/social_profiles` | FTC social URLs | `wordpress-seo/src/routes/first-time-configuration-route.php:37` / `:143` |
| `GET configuration/check_capability` | FTC capability check | `wordpress-seo/src/routes/first-time-configuration-route.php:51` / `:155` |
| `POST configuration/enable_tracking` | FTC tracking opt-in | `wordpress-seo/src/routes/first-time-configuration-route.php:44` / `:168` |
| `POST configuration/save_configuration_state` | Persists FTC step | `wordpress-seo/src/routes/first-time-configuration-route.php:58` / `:181` |
| `GET configuration/get_configuration_state` | Reads FTC state | `wordpress-seo/src/routes/first-time-configuration-route.php:65` / `:190` |
| `POST alerts/dismiss` | Dismisses dashboard alert | `wordpress-seo/src/routes/alert-dismissal-route.php:30` / `:73` |
| `POST ai_generator/callback` | Yoast AI OAuth callback | `wordpress-seo/src/ai/authorization/user-interface/callback-route.php:20` / `:28` |
| `POST ai_generator/refresh_callback` | Refreshes AI OAuth token | `wordpress-seo/src/ai/authorization/user-interface/refresh-callback-route.php:20` / `:28` |
| `POST ai_generator/consent` | Stores AI consent | `wordpress-seo/src/ai/consent/user-interface/consent-route.php:38` / `:90` |
| `GET ai_generator/get_suggestions` | AI title/meta suggestions | `wordpress-seo/src/ai/generator/user-interface/get-suggestions-route.php:40` / `:73` |
| `GET ai_generator/get_usage` | AI credit usage | `wordpress-seo/src/ai/generator/user-interface/get-usage-route.php:43` / `:94` |
| `POST ai_generator/bust_subscription_cache` | Clears AI subscription cache | `wordpress-seo/src/ai/generator/user-interface/bust-subscription-cache-route.php:35` / `:68` |
| `POST ai_content_planner/get_outline` | Content-planner outline | `wordpress-seo/src/ai/content-planner/user-interface/get-outline-route.php:41` / `:74` |
| `POST ai_content_planner/get_suggestions` | Content-planner ideas | `wordpress-seo/src/ai/content-planner/user-interface/get-suggestions-route.php:41` / `:74` |
| `POST ai_content_planner/banner_permanent_dismissal` | Hides planner banner | `wordpress-seo/src/ai/content-planner/user-interface/banner-permanent-dismissal-route.php:39` / `:74` |
| `POST ai/free_sparks` | Claims free AI sparks | `wordpress-seo/src/ai/free-sparks/user-interface/free-sparks-route.php:29` / `:62` |
| REST field `yoast_head` / `yoast_head_json` on post/term/user/post-type-archive | Exposes SEO head + JSON in WP REST | `wordpress-seo/src/routes/yoast-head-rest-field.php:194`; hooks `:99`, `:108`, `:111`, `:112` |

Free WP-AJAX handlers: `wp_ajax_wpseo_set_option`, `yoast_dismiss_notification`, `wpseo_set_ignore`, `wpseo_save_title`, `wpseo_save_metadesc`, `wpseo_save_all_titles`, `wpseo_save_all_descriptions`, `get_focus_keyword_usage_and_post_types`, `get_term_keyword_usage` (`wordpress-seo/admin/ajax.php:52`, `:57`, `:81`, `:96`, `:111`, `:246`, `:261`, `:345`, `:390`); `wpseo_filter_shortcodes` (`wordpress-seo/admin/ajax/class-shortcode-filter.php:19`); `yoast_get_notifications` (`wordpress-seo/admin/class-yoast-notification-center.php:80`).

### Premium

| Route | Behaviour | Citation |
|---|---|---|
| `POST link_suggestions` | Internal-link suggestions from prominent words | `wordpress-seo-premium/src/routes/link-suggestions-route.php:24` / `:76` |
| `POST prominent_words/get_content` | Fetches unindexed content | `wordpress-seo-premium/src/routes/prominent-words-route.php:38` / `:129` |
| `POST prominent_words/complete` | Marks indexing done | `wordpress-seo-premium/src/routes/prominent-words-route.php:66` / `:139` |
| `POST prominent_words/save` | Saves computed prominent words | `wordpress-seo-premium/src/routes/prominent-words-route.php:52` / `:174` |
| `POST workouts/noindex` | Orphaned-workout noindex | `wordpress-seo-premium/src/routes/workouts-route.php:34` / `:172` |
| `POST workouts/remove_redirect` | Orphaned-workout remove redirect | `wordpress-seo-premium/src/routes/workouts-route.php:41` / `:204` |
| `GET workouts/link_suggestions` | Orphaned-workout link suggestions | `wordpress-seo-premium/src/routes/workouts-route.php:48` / `:220` |
| `GET workouts/last_updated` | Orphaned-workout last-updated list | `wordpress-seo-premium/src/routes/workouts-route.php:76` / `:236` |
| `GET workouts/cornerstone_data` | Cornerstone-workout data | `wordpress-seo-premium/src/routes/workouts-route.php:55` / `:246` |
| `POST workouts/enable_cornerstone` | Marks post as cornerstone | `wordpress-seo-premium/src/routes/workouts-route.php:62` / `:266` |
| `POST ai/optimize` | AI fix-readability/SEO assessment | `wordpress-seo-premium/src/ai/optimize/optimizer/user-interface/ai-optimize-route.php:33` / `:66` |
| `POST ai/summarize` | AI Key-Takeaways summary | `wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-route.php:34` / `:67` |
| `POST redirects` | Creates redirect | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:14` / `:87` |
| `POST redirects/delete` | Deletes redirect | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:15` / `:104` |
| `GET redirects/list` | Lists redirects by format | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:16` / `:130` |
| `PUT redirects/update` | Updates redirect | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:17` / `:158` |
| `GET+PUT redirects/settings` | Reads/writes redirect settings | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:18` / `:222`, `:238` |
| `POST redirects/undo-for-object` | Deletes auto-redirect for object | `wordpress-seo-premium/classes/redirect-undo-endpoint.php:14` / `:47` |
| Deprecated `ai_generator/*`, `ai/optimize` shims | Inert; `register_routes()` no-op | `wordpress-seo-premium/src/deprecated/integrations/routes/ai-generator-route.php:24`, `:31`, `:38`, `:45`, `:52`, `:59`, `:120`; `ai-optimizer-route.php:34` |

Premium WP-AJAX: `wpseo_add_redirect_*`, `wpseo_update_redirect_*`, `wpseo_check_url` (`wordpress-seo-premium/classes/redirect/redirect-ajax.php:156`, `:159`, `:163`); `inline-save-tax` (`wordpress-seo-premium/classes/term-watcher.php:57`); `dismiss_update_premium_notification` (`wordpress-seo-premium/src/integrations/admin/update-premium-notification.php:89`).

---

## Blocks and editor integrations

| Feature | Behaviour | Citation | Premium? |
|---|---|---|---|
| Block `yoast/faq-block` | FAQ structured-data block | `wordpress-seo/blocks/structured-data-blocks/faq/block.json:5`; registered `wordpress-seo/src/integrations/blocks/structured-data-blocks.php:87` | no |
| Block `yoast/how-to-block` | How-To structured-data block | `wordpress-seo/blocks/structured-data-blocks/how-to/block.json:5`; registered `wordpress-seo/src/integrations/blocks/structured-data-blocks.php:93` | no |
| Block `yoast-seo/breadcrumbs` | Renders breadcrumbs | `wordpress-seo/blocks/dynamic-blocks/breadcrumbs/block.json:6`; impl `wordpress-seo/src/integrations/blocks/breadcrumbs-block.php:21` | no |
| Block `yoast-seo/siblings` | Lists sibling pages | `wordpress-seo-premium/assets/blocks/dynamic-blocks/siblings/block.json:5`; impl `wordpress-seo-premium/classes/blocks/siblings-block.php:19` | **yes** |
| Block `yoast-seo/subpages` | Lists child pages | `wordpress-seo-premium/assets/blocks/dynamic-blocks/subpages/block.json:5`; impl `wordpress-seo-premium/classes/blocks/subpages-block.php:19` | **yes** |
| Block `yoast-seo/table-of-contents` | Auto-TOC from headings | `wordpress-seo-premium/assets/blocks/dynamic-blocks/table-of-contents/block.json:5` | **yes** |
| Block `yoast-seo/related-links` | Related internal links | `wordpress-seo-premium/assets/blocks/dynamic-blocks/related-links-block/block.json:5` | **yes** |
| Block `yoast-seo/estimated-reading-time` | Reading-time badge | `wordpress-seo-premium/assets/blocks/dynamic-blocks/estimated-reading-time/block.json:5` | **yes** |
| Block `yoast-seo/ai-summarize` | AI Key-Takeaways summary block | `wordpress-seo-premium/assets/blocks/ai-blocks/summary/block.json:5` | **yes** |
| Gutenberg sidebar (document sidebar app) | SEO/readability sidebar + pre-publish panel | loader `wordpress-seo/src/integrations/blocks/block-editor-integration.php:1`; bundles `wordpress-seo/js/dist/post-edit.js:1`, `block-editor.js` | no |
| Classic editor metabox `wpseo_meta` (`WPSEO_Metabox`) | Classic-editor SEO metabox | `wordpress-seo/admin/metabox/class-metabox.php:76`, `:127`, `:247` | no |
| Metabox tabs/sections | SEO / Readability / Social / Schema / Advanced renderers | `wordpress-seo/admin/metabox/class-metabox-form-tab.php:11`, `class-metabox-section-react.php:11`, `class-metabox-analysis-seo.php:11`, `class-metabox-analysis-readability.php:11` | no |
| Elementor editor bridge | Mirrors Yoast analysis into Elementor | `wordpress-seo/src/integrations/third-party/elementor.php:1`; premium panel `wordpress-seo-premium/src/integrations/third-party/elementor-premium.php:61` | mixed (premium part **yes**) |
| Classic link-suggestions metabox | Link-suggestions meta box | `wordpress-seo-premium/classes/metabox-link-suggestions.php:19`, `:99` | **yes** |
| Prominent-words metabox integration | Saves `_yoast_wpseo_words_for_linking`-style field | `wordpress-seo-premium/src/integrations/admin/prominent-words/metabox-integration.php:39`, `:47` | **yes** |

---

## Integrations

### Third-party / builder / ecommerce

| Integration | Behaviour | Citation | Premium? |
|---|---|---|---|
| Jetpack | Sitemap/OG compatibility | `wordpress-seo/src/integrations/third-party/jetpack.php:1` | no |
| Web Stories | Schema/metadata compat | `wordpress-seo/src/integrations/third-party/web-stories.php:1` | no |
| Web Stories post edit | Stories editor SEO panel | `wordpress-seo/src/integrations/third-party/web-stories-post-edit.php:1` | no |
| WooCommerce | SEO output/schema/breadcrumbs | `wordpress-seo/src/integrations/third-party/woocommerce.php:1` | no |
| WooCommerce product editor | Product metabox | `wordpress-seo/src/integrations/third-party/woocommerce-post-edit.php:1` | no |
| WooCommerce permalinks | Product-category permalink handling | `wordpress-seo/src/integrations/third-party/woocommerce-permalinks.php:1` | no |
| WooCommerce post types exclusion | Excludes Woo internals from indexing | `wordpress-seo/src/integrations/third-party/exclude-woocommerce-post-types.php:1` | no |
| Elementor | Editor data bridge | `wordpress-seo/src/integrations/third-party/elementor.php:1` | no |
| Elementor post types exclusion | Excludes library/templates | `wordpress-seo/src/integrations/third-party/exclude-elementor-post-types.php:1` | no |
| WPML | Language/duplicate sync | `wordpress-seo/src/integrations/third-party/wpml.php:1` | no |
| WPML add-on notification | WPML add-on upsell | `wordpress-seo/src/integrations/third-party/wpml-wpseo-notification.php:111` | no |
| bbPress | Forum/breadcrumb/title fixes | `wordpress-seo/src/integrations/third-party/bbpress.php:1` | no |
| AMP | Canonical/front-end handling | `wordpress-seo/src/integrations/third-party/amp.php:1` | no |
| W3 Total Cache | Cache-flush hooks | `wordpress-seo/src/integrations/third-party/w3-total-cache.php:1` | no |
| Wincher publish | Auto-track keyphrase on publish | `wordpress-seo/src/integrations/third-party/wincher-publish.php:25` | no |
| Algolia toggle (upsell only) | Algolia integration toggle | `wordpress-seo/admin/views/class-yoast-integration-toggles.php:72` | no (feature premium) |
| Polylang conditional | Detection conditional | `wordpress-seo/src/conditionals/third-party/polylang-conditional.php:1` | no |
| EDD conditional | Detection conditional | `wordpress-seo/src/conditionals/third-party/edd-conditional.php:1` | no |
| Premium: Algolia | Searchable attributes + noindex blacklist | `wordpress-seo-premium/src/integrations/third-party/algolia.php:65` | **yes** |
| Premium: EDD | Download schema filtering | `wordpress-seo-premium/src/integrations/third-party/edd.php:61` | **yes** |
| Premium: Elementor (premium) | Link suggestions / prominent-words panel | `wordpress-seo-premium/src/integrations/third-party/elementor-premium.php:96` | **yes** |
| Premium: Elementor preview | Preview styles | `wordpress-seo-premium/src/integrations/third-party/elementor-preview.php:47` | **yes** |
| Premium: Mastodon | Schema/contactmethods + `wpseo_mastodon_active` | `wordpress-seo-premium/src/integrations/third-party/mastodon.php:51`, `:54` | **yes** |
| Premium: TranslationsPress | Premium translations feed | `wordpress-seo-premium/src/integrations/third-party/translationspress.php:60` | **yes** |
| Premium: Wincher keyphrases | Appends related keyphrases to tracking | `wordpress-seo-premium/src/integrations/third-party/wincher-keyphrases.php:33` | **yes** |
| Premium: WooCommerce HPOS | Custom-order-tables compat | `wordpress-seo-premium/src/initializers/woocommerce.php:22` | **yes** |
| Premium: IndexNow ping | Submit on publish | `wordpress-seo-premium/src/integrations/index-now-ping.php:59` | **yes** |
| Premium: add-on installer | Installs add-ons from wordpress.org | `wordpress-seo-premium/src/addon-installer.php:52` | **yes** |

### Importers

| Integration | Behaviour | Citation | Premium? |
|---|---|---|---|
| Rank Math | SEO data importer | `wordpress-seo/admin/import/plugins/class-import-rankmath.php:25` | no |
| AIOSEO v3 | `_aioseop_*` importer | `wordpress-seo/admin/import/plugins/class-import-aioseo.php:25` | no |
| AIOSEO v4 | `_aioseo_*` importer + cleanup | `wordpress-seo/admin/import/plugins/class-import-aioseo-v4.php:28` | no |
| AIOSEO actions | Settings/posts/taxonomy import actions | `wordpress-seo/src/actions/importing/aioseo/aioseo-posts-importing-action.php:29` | no |
| SEO Framework / Genesis | `_genesis_*` importer | `wordpress-seo/admin/import/plugins/class-import-seo-framework.php:25` | no |
| Import integration | Registers aioseo/rankmath import tabs | `wordpress-seo/src/integrations/admin/import-integration.php:113` | no |
| Premium extension importer | Divi/Elementor Gutenberg converter + media importer | `wordpress-seo-premium/src/integrations/admin/extension-importer/importer.php:1`, `media-manager.php:314` | **yes** |

### Search / analytics / SEO-tool integrations

| Integration | Behaviour | Citation | Premium? |
|---|---|---|---|
| Google Search Console (legacy) | Deprecated admin page stub | `wordpress-seo/admin/google_search_console/class-gsc.php:26` | no |
| Site Kit | Google consent/config/dashboard data | `wordpress-seo/src/dashboard/infrastructure/integrations/site-kit.php:25` | no |
| Site Kit Analytics-4 | GA4 adapter | `wordpress-seo/src/dashboard/infrastructure/analytics-4/site-kit-analytics-4-adapter.php` | no |
| Site Kit Search Console | Search-console adapter | `wordpress-seo/src/dashboard/infrastructure/search-console/site-kit-search-console-adapter.php` | no |
| SEMrush | Related-keyphrase OAuth + editor panel | `wordpress-seo/src/editors/framework/integrations/semrush.php:40` | no |
| Wincher | Rank tracking OAuth + editor panel | `wordpress-seo/src/editors/framework/integrations/wincher.php:20` | no |
| HelpScout beacon | Support beacon loader | `wordpress-seo/src/integrations/admin/helpscout-beacon.php:26` | no |
| Integration toggles registry | Semrush + Algolia toggles + `wpseo_integration_toggles` filter | `wordpress-seo/admin/views/class-yoast-integration-toggles.php:60` | no |
| MyYoast client | OAuth OIDC connect/management | `wordpress-seo/src/myyoast-client/user-interface/myyoast-client-integration.php:1` | no |
| Integrations page | ACF/Algolia/EDD/Mastodon/Woo install/active matrix | `wordpress-seo/src/integrations/admin/integrations-page.php:174` | no |
| Absent | No dedicated Divi/Avada/WPBakery/Beaver/Astra/Oxygen/Thrive/Bricks/Zapier/BuddyPress integration files (only Genesis importer + conflicting-plugins list) | `wordpress-seo/src/config/conflicting-plugins.php:93` | n/a |

---

## Premium additions over free

| Feature | Behaviour | Citation | Surface |
|---|---|---|---|
| Redirect manager core | CRUD + lookup for plain/regex redirects | `wordpress-seo-premium/classes/redirect/redirect-manager.php:11` | Tools > Redirects, PHP API |
| Redirect model | Value object origin/target/type/format | `wordpress-seo-premium/classes/redirect/redirect.php:13` | PHP API |
| Redirect types | 301/302/307/410/451 constants | `wordpress-seo-premium/classes/redirect/redirect-types.php:11` | Admin dropdown |
| Redirect option store | Stored redirects | `wordpress-seo-premium/classes/redirect/redirect-option.php:28` | option `wpseo-premium-redirects-base` |
| Premium redirect option | `disable_php_redirect`, `separate_file` | `wordpress-seo-premium/classes/premium-redirect-option.php:11` | option `wpseo_redirect` |
| Front-end redirect execution | Handles redirects on `template_redirect` | `wordpress-seo-premium/src/initializers/redirect-handler.php:15` | Front-end |
| Redirect AJAX | Create/update/delete via admin-ajax | `wordpress-seo-premium/classes/redirect/redirect-ajax.php:11` | Admin JS |
| Redirect table UI | WP_List_Table | `wordpress-seo-premium/classes/redirect/redirect-table.php:15` | Tools > Redirects |
| Redirect REST endpoint | `redirects` + list/update/settings/delete | `wordpress-seo-premium/classes/premium-redirect-endpoint.php:14` | REST |
| Redirect undo endpoint | Undo auto-redirect | `wordpress-seo-premium/classes/redirect-undo-endpoint.php:14` | REST + editor notice |
| Redirect service | Orchestration facade | `wordpress-seo-premium/classes/premium-redirect-service.php:11` | PHP API |
| Redirect import | CSV + Redirection/SRM/Simple301 loaders | `wordpress-seo-premium/classes/redirect/redirect-importer.php:11` | Tools > Import |
| Redirect import UI | Import tab | `wordpress-seo-premium/classes/premium-import-manager.php:32` | Tools > Import |
| Redirect exporters | CSV/Apache/Nginx/htaccess | `wordpress-seo-premium/classes/redirect/exporters/redirect-csv-exporter.php:13` | Tools > Import/Export |
| Redirect export manager | CSV download | `wordpress-seo-premium/classes/premium-redirect-export-manager.php:57` | Tools > Import |
| Redirect sitemap filter | Strips redirected URLs | `wordpress-seo-premium/classes/redirect/redirect-sitemap-filter.php:11` | XML sitemap |
| Redirect validators | 9 validators | `wordpress-seo-premium/classes/redirect/redirect-validator.php:11` | Admin + CLI + REST |
| Auto-redirect on post slug change | 301 + undo notice | `wordpress-seo-premium/classes/post-watcher.php:11` | Editor + notice |
| Auto-redirect on term slug change | 301 + undo notice | `wordpress-seo-premium/classes/term-watcher.php:11` | Terms admin |
| Watcher base | Shared redirect-notification/create logic | `wordpress-seo-premium/classes/watcher.php:11` | Admin notices |
| Redirect CLI | `wp yoast redirect list/create/update/delete/has/follow` | `wordpress-seo-premium/cli/cli-redirect-command-namespace.php:11` | WP-CLI |
| Internal linking suggestions metabox | Availability check | `wordpress-seo-premium/classes/metabox-link-suggestions.php:11` | Editor metabox |
| Link suggestions API | Prominent-words-powered endpoint | `wordpress-seo-premium/src/routes/link-suggestions-route.php:15` | REST `link_suggestions` |
| Link suggestions action | Query indexables by prominent words | `wordpress-seo-premium/src/actions/link-suggestions-action.php:16` | REST backend |
| AI Optimize integration | Fix-assessments suggestions | `wordpress-seo-premium/src/ai/optimize/optimizer/user-interface/ai-optimize-integration.php:22` | Editor AI modal |
| AI Optimize route | `ai/optimize` | `wordpress-seo-premium/src/ai/optimize/optimizer/user-interface/ai-optimize-route.php:33` | REST |
| AI Summarize | Crawl-settings summarizer | `wordpress-seo-premium/src/ai/summarize/application/summarizer.php:27` | Admin |
| AI Summarize route/integration | `ai/summarize` | `wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-route.php:34` | REST |
| Prominent words support | Supported post types/taxonomies | `wordpress-seo-premium/classes/premium-prominent-words-support.php:11` | Editor analysis |
| Prominent words versioning | `_yst_prominent_words_version` migration | `wordpress-seo-premium/classes/premium-prominent-words-versioning.php:16` | post meta |
| Prominent words indexation API | `prominent-words/*` REST + actions | `wordpress-seo-premium/src/routes/prominent-words-route.php:22` | REST + background |
| Prominent words watcher/store | Re-index on save | `wordpress-seo-premium/src/integrations/watchers/prominent-words-watcher.php:13` | Background |
| Prominent words table | `wp_yoast_prominent_words` | `wordpress-seo-premium/src/config/migrations/20190715101200_WpYoastPremiumImprovedInternalLinking.php:95` | table |
| Social previews (classic) | FB/X preview enqueue | `wordpress-seo-premium/classes/social-previews.php:11` | Editor |
| Social appearance per type/archive/author | OpenGraph template integrations | `wordpress-seo-premium/src/integrations/opengraph-post-type.php:8`; siblings `opengraph-term-archive.php`, `opengraph-author-archive.php`, `opengraph-date-archive.php`, `opengraph-posttype-archive.php`, `opengraph-post-type.php`, `abstract-opengraph-integration.php` | Settings + front-end meta |
| Organization schema data | Extra org fields + workout task | `wordpress-seo-premium/src/integrations/organization-schema-integration.php:12` | Schema + Workouts |
| Publishing principles schema | Site-policy schema output | `wordpress-seo-premium/src/integrations/publishing-principles-schema-integration.php:17` | Front-end schema |
| Keyword CSV export (bulk) | Posts/terms + related keyphrases to CSV | `wordpress-seo-premium/classes/premium-keyword-export-manager.php:13` | Tools > Import |
| Orphaned content support/query/filter | Supported types + query + post-list filter | `wordpress-seo-premium/classes/premium-orphaned-content-support.php:11`; `premium-orphaned-post-query.php`, `premium-orphaned-post-filter.php` | Posts overview |
| Stale cornerstone filter | `stale-cornerstone-content` filter + cache invalidation | `wordpress-seo-premium/classes/premium-stale-cornerstone-content-filter.php:19` | Posts overview |
| Cornerstone columns (posts/terms) | Sortable cornerstone column | `wordpress-seo-premium/src/integrations/admin/cornerstone-column-integration.php:21` | Posts/terms overview |
| Workouts (orphaned/cornerstone/social) | Workout data + tasks-collector + routes | `wordpress-seo-premium/src/routes/workouts-route.php:25` | SEO > Workouts |
| Multiple keyphrases + synonyms | Extra keyphrase/synonym analysis wiring | `wordpress-seo-premium/classes/multi-keyword.php:11` | Editor analysis |
| Related keyphrase filter | Related-keyphrase admin column filter | `wordpress-seo-premium/src/integrations/admin/related-keyphrase-filter-integration.php:14` | Posts overview |
| IndexNow ping | `transition_post_status` → IndexNow | `wordpress-seo-premium/src/integrations/index-now-ping.php:81` | Background |
| IndexNow key | `yoast-index-now-*.txt` rewrite + key gen | `wordpress-seo-premium/src/initializers/index-now-key.php:80` | Rewrite + `index_now_key` |
| Crawl (AI bot) robots.txt | Deny CCBot/Google-Extended/GPTBot toggles | `wordpress-seo-premium/src/integrations/front-end/robots-txt-integration.php:52` | robots.txt |
| Custom fields analysis | ACF-like custom-field content analysis | `wordpress-seo-premium/classes/custom-fields-plugin.php:12` | Editor analysis |
| Dynamic Gutenberg blocks | Siblings/Subpages/Related/Reading-time/TOC | `wordpress-seo-premium/classes/blocks/siblings-block.php:12` | Block editor |
| Mastodon verification + profile fields | `rel=me` + contact method + user schema | `wordpress-seo-premium/src/presenters/mastodon-link-presenter.php:10`; `src/user-meta/framework/additional-contactmethods/mastodon.php` | Front-end + Users > Profile |
| Upgrade manager / integration | Versioned DB/option upgrades, orphaned-workout reset | `wordpress-seo-premium/classes/upgrade-manager.php:13` | `admin_init`/`wp` |
| Add-on installer | Install add-ons from zip | `wordpress-seo-premium/src/addon-installer.php:17` | Plugins admin |
| Premium option container | `wpseo_premium` | `wordpress-seo-premium/classes/premium-option.php:29` | option |
| DI container | Registers all premium integrations/routes | `wordpress-seo-premium/src/generated/container.php:747` | PHP DI |
| Premium main wiring | Registers premium integrations list | `wordpress-seo-premium/premium.php:92-106` | PHP |
| Premium user profile additions | Extra fields rendered only when premium | `wordpress-seo/src/user-profiles-additions/user-interface/user-profiles-additions-ui.php:68` | Users > Profile (gated in free) |
| Premium exports (keyword/redirect) | CSV exports | `wordpress-seo-premium/classes/export/export-keywords-csv.php:1` | Tools > Import |

Zapier: no Zapier class/hook found in the v27.8 tree (searched `wordpress-seo-premium/src` + `classes`). Treat as absent.

---

## Premium-gated items found in free code

| Gated feature | Gate mechanism | Citation |
|---|---|---|
| Central premium check | `is_premium()` = `WPSEO_Premium` class exists; `get_premium_version()` | `wordpress-seo/src/helpers/product-helper.php:37` |
| Premium_Active conditional | Enables premium-only integrations | `wordpress-seo/src/conditionals/premium-active-conditional.php:16` |
| Premium_Inactive conditional | Shows upsell page | `wordpress-seo/src/conditionals/premium-inactive-conditional.php:16` |
| Link suggestions | toggle `premium => true`, upsell `get-link-suggestions` | `wordpress-seo/admin/views/class-yoast-feature-toggles.php:122` |
| IndexNow | toggle `premium => true`, upsell `get-indexnow` | `wordpress-seo/admin/views/class-yoast-feature-toggles.php:192` |
| AI title & description generator | toggle `premium => true`, upsell `get-ai-generator` | `wordpress-seo/admin/views/class-yoast-feature-toggles.php:203` |
| Algolia integration | toggle `premium => true`, upsell `get-algolia-integration` | `wordpress-seo/admin/views/class-yoast-integration-toggles.php:75` |
| Feature/integration upsell renderer | `show_premium_upsell` + `premium_upsell_url` button when `!is_premium` | `wordpress-seo/admin/views/tabs/network/features.php:80` |
| Premium-version guard | Warns if `premium_version` < required | `wordpress-seo/admin/views/tabs/network/features.php:36` |
| Redirects upsell page | Free page under `Premium_Inactive_Conditional` | `wordpress-seo/src/integrations/admin/redirects-page-integration.php:85` |
| Slug-change redirect upsell | Suppress auto-redirect notice + Premium CTA when free | `wordpress-seo/admin/watchers/class-slug-change-watcher.php:20` |
| Bulk editor premium flag | `isPremium` + version check localizes editor | `wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:265` |
| Workouts premium flag | `isPremium` localizes workouts | `wordpress-seo/src/integrations/admin/workouts-integration.php:160` |
| Inclusive language lock | Returns locked when `!is_premium` | `wordpress-seo/src/editors/framework/inclusive-language-analysis.php:94` |
| AI editor gate | Blocks AI routes when `!is_premium` | `wordpress-seo/src/conditionals/ai-editor-conditional.php:110` |
| Elementor premium gate | Locks premium assessments unless premium ≥21.8 | `wordpress-seo/src/integrations/third-party/elementor.php:653` |
| Social preview upsells | `shortlinks.upsell.social_preview.*` card always in free | `wordpress-seo/admin/class-expose-shortlinks.php:31` |
| Prominent words / formality upsells | `shortlinks-insights-upsell-*-prominent_words/text_formality` | `wordpress-seo/admin/class-expose-shortlinks.php:62` |
| Premium admin-footer upsell block | `Buy Premium` button on Yoast pages when free | `wordpress-seo/admin/class-premium-upsell-admin-block.php:56` |
| Premium popup/button | Upsell modal trigger markup | `wordpress-seo/admin/class-premium-popup.php:96` |
| Product upsell notice | Hides notice when `is_premium` | `wordpress-seo/admin/class-product-upsell-notice.php:146` |
| Admin bar / Brand insights | Premium-only menu entries when `is_premium` | `wordpress-seo/inc/class-wpseo-admin-bar-menu.php:259` |
| Addon manager premium slug | `WPSEO_Premium` subscription validation | `wordpress-seo/inc/class-addon-manager.php:451` |
| Schema config premium flag | `isPremium` localizes site-policy/schema UI | `wordpress-seo/src/schema/application/configuration/schema-configuration.php:98` |
| Delayed/Black-Friday upsell | Shows only when `!is_premium` | `wordpress-seo/src/introductions/application/delayed-premium-upsell.php:87` |
| User profile additions | Extra fields only when `is_premium` | `wordpress-seo/src/user-profiles-additions/user-interface/user-profiles-additions-ui.php:68` |
| Indexing notifications | Premium copy/support link when `is_premium` | `wordpress-seo/src/presenters/admin/indexing-error-presenter.php:119` |
| Debug marker | Premium version + shop URL when `is_premium` | `wordpress-seo/src/presenters/debug/marker-open-presenter.php:27` |

---

## Separate paid addons not in these directories

These are separate downloads / products, **not** part of Premium and **not** present in either directory. Free code references them only for detection, upselling and tracking.

| Add-on | Evidence in free code (slug / constant) | Directory present? |
|---|---|---|
| Yoast WooCommerce SEO | `wpseo-woocommerce/wpseo-woocommerce.php` (`wordpress-seo/src/integrations/watchers/addon-update-watcher.php:31`, `wordpress-seo/admin/class-plugin-availability.php:99`); `WPSEO_Addon_Manager::WOOCOMMERCE_SLUG = 'yoast-seo-woocommerce'` (`wordpress-seo/inc/class-addon-manager.php:64`) | no |
| Yoast Local SEO | `wpseo-local/local-seo.php` (`addon-update-watcher.php:30`, `class-plugin-availability.php:79`); `LOCAL_SLUG = 'yoast-seo-local'` (`class-addon-manager.php:71`) | no |
| Yoast News SEO | `wpseo-news/wpseo-news.php` (`addon-update-watcher.php:32`, `class-plugin-availability.php:69`); `NEWS_SLUG = 'yoast-seo-news'` (`class-addon-manager.php:50`); `WPSEO_NEWS_VERSION` / `WPSEO_NEWS_FILE` conditionals (`wordpress-seo/src/conditionals/news-conditional.php:16`, `wordpress-seo/src/integrations/settings-integration.php:556`) | no |
| Yoast Video SEO | `wpseo-video/video-seo.php` (`addon-update-watcher.php:29`, `class-plugin-availability.php:59`); `VIDEO_SLUG = 'yoast-seo-video'` (`class-addon-manager.php:57`) | no |
| Yoast SEO Multilingual (WPML) | Referenced only as an add-on separate from the WPML integration; no dedicated slug constant found in code (unsure — no explicit constant located) | no |

Add-on manager slug map: `wordpress-seo/inc/class-addon-manager.php:80-83`. Availability list: `wordpress-seo/admin/class-plugin-availability.php:49-99`.

---

## Scheduled cron hooks

| Hook | Purpose | Citation | Premium? |
|---|---|---|---|
| `wpseo_cleanup_cron` | Recurring indexable cleanup | `wordpress-seo/src/integrations/cleanup-integration.php:22` (scheduled `:270`) | no |
| `wpseo_start_cleanup_indexables` | One-off trigger to start cleanup (`Cleanup_Integration::START_HOOK`) | `wordpress-seo/inc/class-upgrade.php:960`; `wordpress-seo/src/integrations/admin/activation-cleanup-integration.php:64` | no |
| `wpseo_indexable_index_batch` | Background indexing every 15 min | `wordpress-seo/src/integrations/admin/background-indexing-integration.php:212` | no |
| `wpseo_permalink_structure_check` | Daily permalink-structure check | `wordpress-seo/src/integrations/watchers/indexable-permalink-watcher.php:264` | no |
| `wpseo_send_tracking_data_after_core_update` | One-off tracking 6h after core update | `wordpress-seo/admin/tracking/class-tracking.php:101`, `:106` | no |
| `wpseo_detect_default_seo_data` | Daily default-SEO-data detection | `wordpress-seo/src/alerts/user-interface/default-seo-data/default-seo-data-cron-scheduler.php:37` | no |
| `wpseo_expiring_store_cleanup` | Weekly expiring-store cleanup | `wordpress-seo/src/expiring-store/user-interface/expiring-store-cleanup-integration.php:51` | no |
| `wpseo_llms_txt_population` | Weekly llms.txt regeneration | `wordpress-seo/src/llms-txt/application/file/llms-txt-cron-scheduler.php:46` | no |
| `wpseo_myyoast_key_rotation` | MyYoast key rotation | `wordpress-seo/src/myyoast-client/user-interface/myyoast-client-integration.php:88` | no |
| `wpseo-reindex` (notification) | Indexing-notification schedule | `wordpress-seo/src/integrations/admin/cron-integration.php:41` | no |
| `yoast_tracking` | Legacy tracking hook (cleared on upgrade) | `wordpress-seo/inc/class-upgrade.php:233` | no |
| `wpseo_hit_sitemap_index` | Sitemap-index ping action (not wp-scheduled directly) | `wordpress-seo/inc/sitemaps/class-sitemaps.php:103` | no |
| `wpseo_ryte_fetch` | Legacy Ryte fetch (cleared on upgrade) | `wordpress-seo/inc/class-upgrade.php:795` | no |
| `wpseo-premium-orphaned-content` | Premium orphaned-content reset; **only cleared**, not scheduled in this tree | `wordpress-seo-premium/classes/upgrade-manager.php:353`; premium schedules the shared cleanup hook at `:299` | **yes** |

---

## External HTTP calls

| Call / service | Endpoint / domain | Citation | Premium? |
|---|---|---|---|
| AI API client | base URL via `src/ai/http-request/infrastructure/api-client.php:24` (Yoast AI endpoint) | `wordpress-seo/src/ai/http-request/infrastructure/api-client.php:24` | no |
| AI `wp_remote_post` | AI request body | `wordpress-seo/src/ai/http-request/infrastructure/api-client.php:58` | no |
| AI `wp_remote_get` | AI resource fetch | `wordpress-seo/src/ai/http-request/infrastructure/api-client.php:61` | no |
| AI `wp_remote_request` DELETE | Delete AI resource | `wordpress-seo/src/ai/http-request/infrastructure/api-client.php:64` | no |
| MyYoast legacy API | `https://my.yoast.com/api/` | `wordpress-seo/inc/class-my-yoast-api-request.php:57`, `:110` | no |
| MyYoast OIDC issuer | `https://my.yoast.com` discovery/token/userinfo | `wordpress-seo/src/myyoast-client/infrastructure/oidc/issuer-config.php:33` | no |
| MyYoast HTTP client | `wp_remote_request` (+DPoP) | `wordpress-seo/src/myyoast-client/infrastructure/http/http-client.php:217` | no |
| Guzzle→WP bridge | `wp_remote_request` for Semrush/Wincher OAuth | `wordpress-seo/src/wrappers/wp-remote-handler.php:49` | no |
| Semrush OAuth | `https://oauth.semrush.com/oauth2/authorize`, `/access_token`, `/resource`, `/yoast/success` | `wordpress-seo/src/config/semrush-client.php:37` | no |
| Semrush keyphrases | `https://oauth.semrush.com/api/v1/keywords/phrase_fullsearch` | `wordpress-seo/src/actions/semrush/semrush-phrases-action.php:23` | no |
| Wincher OAuth | `https://auth.wincher.com/connect/authorize`, `/connect/token`, `/yoast/setup` | `wordpress-seo/src/config/wincher-client.php:49` | no |
| Wincher API user | `https://api.wincher.com/beta/user` | `wordpress-seo/src/config/wincher-client.php:52` | no |
| Wincher track/bulk | `https://api.wincher.com/beta/websites/%s/keywords/bulk` | `wordpress-seo/src/actions/wincher/wincher-keyphrases-action.php:22` | no |
| Wincher get | `https://api.wincher.com/beta/yoast/%s` | `wordpress-seo/src/actions/wincher/wincher-keyphrases-action.php:29` | no |
| Wincher untrack | `https://api.wincher.com/beta/websites/%s/keywords/%s` | `wordpress-seo/src/actions/wincher/wincher-keyphrases-action.php:36` | no |
| Wincher account/limits | `https://api.wincher.com/beta/account`, `https://api.wincher.com/v1/yoast/upgrade-campaign` | `wordpress-seo/src/actions/wincher/wincher-account-action.php:14` | no |
| Generic remote wrapper | transport for tracking + IndexNow | `wordpress-seo/admin/class-remote-request.php:147` | no |
| Usage tracking send | weekly telemetry (`WPSEO_Remote_Request`) | `wordpress-seo/admin/tracking/class-tracking.php:132` | no |
| MyYoast proxy GET | server-side proxy | `wordpress-seo/admin/class-my-yoast-proxy.php:120` | no |
| Sitemap self-ping GET | warms sitemap cache (first-party) | `wordpress-seo/inc/sitemaps/class-sitemaps.php:491` | no |
| Site Kit proxy | `rest_do_request` (no direct `googleapis.com`) | `wordpress-seo/src/dashboard/infrastructure/analytics-4/site-kit-analytics-4-api-call.php:30` | no |
| HelpScout beacon JS | client-side `beacon-v2.helpscout.net` | `wordpress-seo/src/integrations/admin/helpscout-beacon.php:178` | no |
| GSC legacy link | `https://search.google.com/search-console/index` (page link only) | `wordpress-seo/admin/google_search_console/views/gsc-display.php:12` | no |
| IndexNow endpoint | `https://api.indexnow.org/indexnow` | `wordpress-seo-premium/src/integrations/index-now-ping.php:59` | **yes** |
| TranslationsPress feed | `https://packages.translationspress.com/yoast/wordpress-seo-premium/packages.json` | `wordpress-seo-premium/src/integrations/third-party/translationspress.php:60`, `:194` | **yes** |
| Extension importer media | dynamic remote image URL | `wordpress-seo-premium/src/integrations/admin/extension-importer/media-manager.php:314` | **yes** |
| Add-on installer trunk | `https://downloads.wordpress.org/plugin/wordpress-seo.zip` | `wordpress-seo-premium/src/addon-installer.php:52` | **yes** |
| Add-on installer version check | `wp_remote_head(https://downloads.wordpress.org/plugin/wordpress-seo.{ver}.zip)` | `wordpress-seo-premium/src/addon-installer.php:371` | **yes** |
| EDD license server | `http://my.yoast.com` + `edd-sl-api`, `https://my.yoast.com/licenses/` | `wordpress-seo-premium/classes/product-premium.php:27` | **yes** |

`vendor/` and `vendor_prefixed/` were excluded from the HTTP-call sweep except to note they contain the Guzzle `curl_exec` shims.

---

## Unverified / notes

- Free analysis source is shipped only as compiled JS (`wordpress-seo/js/dist/externals/analysis.js`); assessment identifiers and line numbers come from that bundle, not per-assessment PHP/JS source files. Behaviour wording is inferred from identifiers.
- Autoload status: neither plugin passes an explicit `autoload` argument; the report states "no explicit flag" rather than a definite `yes`/`no`, because the value depends on the WordPress version default at write time.
- React Settings client-side tabs (e.g. `#/site-features`, `#/site-representation`) are not registered as PHP admin tabs in this version; left as "unsure".
- Yoast SEO Multilingual: no dedicated add-on slug constant located in code.
- Zapier: no class/hook found in Premium v27.8.
- `wp_yoast_migrations` is created through `lib/migrations/adapter.php:123`; the migration-version bookkeeping is also mirrored in the option `yoast_migrations_` (`src/config/migration-status.php:17`).
