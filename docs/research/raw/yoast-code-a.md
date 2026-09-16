# Yoast SEO — admin screens, option keys, post/term/user meta keys (code-verified, Free + Premium)

Scope (the two plugins present on disk):
- Free: `wp-content/plugins/wordpress-seo/` — **v28.4** (`wordpress-seo/wp-seo.php:11`, `define( 'WPSEO_VERSION', '28.4' )` in `wordpress-seo/wp-seo-main.php`)
- Premium: `wp-content/plugins/wordpress-seo-premium/` — **v27.8** (`wordpress-seo-premium/wp-seo-premium.php:13`, `define( 'WPSEO_PREMIUM_VERSION', '27.8' )` in `wordpress-seo-premium/wp-seo-premium.php:60`)

All paths are relative to `wp-content/plugins/`. Every row cites `file:line` read from the on-disk source. Anything not verifiable is omitted.

Notes that apply throughout:
- The Free pages all attach to the parent slug `wpseo_dashboard` (`wordpress-seo/admin/class-admin.php:22`, `wordpress-seo/admin/menu/class-menu.php:19`) and surface through the `wpseo_submenu_pages` filter collected in `wordpress-seo/admin/menu/class-admin-menu.php:104`.
- Capabilities are registered in `wordpress-seo/admin/capabilities/class-register-capabilities.php:40` (`wpseo_edit_advanced_metadata`) and `:42` (`wpseo_manage_options`); Premium adds `wpseo_manage_redirects` at `wordpress-seo-premium/classes/premium-register-capabilities.php:30`. Network menu uses `wpseo_manage_network_options` (`wordpress-seo/admin/menu/class-network-admin-menu.php:100`).
- "Autoload: none explicit" means the option is written with no third `$autoload` argument (`update_option( $name, $value )`) and therefore inherits the WordPress default for that WP version. Only explicit-autoload writes are called out.

---

## Admin screens

### Top-level menus

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_dashboard` (Yoast SEO root menu) | Top-level admin menu "Yoast SEO" holding all SEO submenus; `add_menu_page()` with the dynamically-lowest submenu capability (ends up `wpseo_manage_options`) | Free | `wordpress-seo/admin/menu/class-admin-menu.php:20` (hook), `:55-63` (`add_menu_page`), `:128-130` (`get_manage_capability`) |
| `wpseo_dashboard` (network root menu) | Network-admin top-level menu "Yoast SEO" on multisite (`network_admin_menu`); capability `wpseo_manage_network_options` | Free | `wordpress-seo/admin/menu/class-network-admin-menu.php:20` (hook), `:33-40` (`add_menu_page`), `:99-101` (capability) |

### Submenus and settings pages (registered on `wpseo_submenu_pages`)

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| General (`wpseo_dashboard`) | Dashboard/General settings screen, `#/` React app (`show_in_rest`); cap `wpseo_manage_options` | Free | `wordpress-seo/src/general/user-interface/general-page-integration.php:30` (`PAGE`), `:189` (filter), `:204-219` (array); display `:229-231` |
| Settings (`wpseo_page_settings`) | Unified React settings page ("Settings") spliced in at index 1; cap `wpseo_manage_options` | Free | `wordpress-seo/src/integrations/settings-integration.php:42` (`PAGE`), `:311` (filter), `:371-389` (array); display `:429-431` |
| Integrations (`wpseo_integrations`) | Third-party integrations screen; cap `wpseo_manage_options` | Free | `wordpress-seo/src/integrations/admin/integrations-page.php:26` (`PAGE`), `:141-154` (array) |
| Workouts (`wpseo_workouts`) | Workouts screen; cap `edit_others_posts` | Free | `wordpress-seo/src/integrations/admin/workouts-integration.php:89-99` |
| Redirects (`wpseo_redirects`) | Redirects screen (Free upsell stub; class only active with `Premium_Inactive_Conditional`); cap `edit_others_posts` | Free | `wordpress-seo/src/integrations/admin/redirects-page-integration.php:21` (`PAGE`), `:82-87` (conditionals), `:96-107` (array) |
| Brand Insights (`wpseo_brand_insights`) | AI Brand Insights sidebar button, last item (Free variant); cap `edit_posts` | Free | `wordpress-seo/src/integrations/admin/brand-insights-page.php:66` (page slug), `:76-84` (array) |
| Plans (`wpseo_licenses`) | Yoast plans/licensing screen; cap `wpseo_manage_options` | Free | `wordpress-seo/src/plans/user-interface/plans-page-integration.php:26` (`PAGE`), `:137-148` (array) |
| Upgrade (`wpseo_upgrade_sidebar`) | Upgrade upsell sidebar item; cap `edit_posts` | Free | `wordpress-seo/src/plans/user-interface/upgrade-sidebar-menu-integration.php:25` (`PAGE`), `:133-142` (array) |
| Support (`wpseo_page_support`) | Support screen; cap `wpseo_manage_options` | Free | `wordpress-seo/src/integrations/support-integration.php:20` (`PAGE`), `:22` (`CAPABILITY`), `:126-137` (array) |
| Bulk editor (`wpseo_page_bulk_edit`) | Bulk editor screen; cap `wpseo_manage_options` | Free | `wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:32` (`PAGE`), `:223-234` (array) |
| Academy (`wpseo_page_academy`) | Yoast Academy screen; cap `edit_posts` | Free | `wordpress-seo/src/integrations/academy-integration.php:17` (`PAGE`), `:101-118` (array) |
| Search Console (`wpseo_search_console`) | Google Search Console screen; rendered with lower cap via `get_submenu_page` (default `wpseo_manage_options`); parent slug faked to hide from menu | Free | `wordpress-seo/admin/menu/class-admin-menu.php:91-95`; special-case fake parent `wordpress-seo/admin/menu/class-base-menu.php:172-175` |
| Tools (`wpseo_tools`) | Tools page (import/export, file editor etc.); default `wpseo_manage_options` | Free | `wordpress-seo/admin/menu/class-admin-menu.php:96`; page loader `wordpress-seo/admin/menu/class-menu.php:76-79`; view `wordpress-seo/admin/pages/tools.php` |
| Edit Files (`wpseo_files`) | File editor (network admin, shown only when `allow_system_file_edit()`); cap `wpseo_manage_network_options` | Free | `wordpress-seo/admin/menu/class-network-admin-menu.php:62-64`; loader `wordpress-seo/admin/menu/class-menu.php:80-82` |

### Hidden / non-menu registered pages

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_page_settings_saved` | Dummy page under `options.php` so the settings `options.php` route can redirect to something (`add_submenu_page`); cap `wpseo_manage_options` | Free | `wordpress-seo/src/integrations/settings-integration.php:400-422` |
| `wpseo_configurator` | Legacy configuration wizard page; immediately redirects to `admin.php?page=wpseo_dashboard#/first-time-configuration`; cap `manage_options` | Free | `wordpress-seo/src/integrations/admin/old-configuration-integration.php:35-46` (register), `:62-70` (redirect) |
| `wpseo_installation_successful_free` | Free installation-success page; cap `manage_options` | Free | `wordpress-seo/src/integrations/admin/installation-success-integration.php:124-135` |
| `wpseo_page_site_kit_set_up` | Site Kit setup interim page (dummy, `add_submenu_page` on `options.php`); cap `wpseo_manage_options` | Free | `wordpress-seo/src/dashboard/user-interface/setup/setup-url-interceptor.php:20` (`PAGE`), `:90-100` |
| `wpseo_myyoast_proxy` | Dashboard page for MyYoast OAuth proxy (`add_dashboard_page`); cap `read` | Free | `wordpress-seo/admin/class-my-yoast-proxy.php:21` (`PAGE_IDENTIFIER`), `:54-56` |
| `wpseo_redirects_tools` | Tools → "Yoast Redirects" management page (`add_management_page`); cap `edit_others_posts` | Free | `wordpress-seo/src/integrations/admin/redirections-tools-page.php:57-71` |
| `wpseo_installation_successful` | Premium installation-success ("thank you") page; cap `manage_options`; also triggers the post-activation redirect | Premium | `wordpress-seo-premium/src/integrations/admin/thank-you-page-integration.php:81-92` (register), `:55-72` (redirect) |

### Premium submenus

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Redirects (`wpseo_redirects`) | Premium Redirects manager; cap `wpseo_manage_redirects` | Premium | `wordpress-seo-premium/premium.php:135` (filter), `:336-347` (array), `:341` (capability) |
| Brand Insights (`wpseo_brand_insights_premium`) | AI Brand Insights sidebar button, last item (Premium slug variant) | Premium | selected when `is_premium()` at `wordpress-seo/src/integrations/admin/brand-insights-page.php:66` |

### Settings tabs (PHP-registered)

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Network tab `general` | Network Settings → "General" tab | Free | `wordpress-seo/admin/pages/network.php:18` |
| Network tab `features` | Network Settings → "Features" tab | Free | `wordpress-seo/admin/pages/network.php:19` |
| Network tab `integrations` | Network Settings → "Integrations" tab | Free | `wordpress-seo/admin/pages/network.php:20` |
| Network tab `crawl-settings` | Network Settings → "Crawl settings" tab (`save_button => true`) | Free | `wordpress-seo/admin/pages/network.php:22-30` |
| Network tab `restore-site` | Network Settings → "Restore Site" tab (`save_button => false`) | Free | `wordpress-seo/admin/pages/network.php:31` |
| Dashboard tab `first-time-configuration` | "First-time configuration" tab added to the General sub-page via `wpseo_settings_tabs_dashboard` (`save_button => false`) | Free | `wordpress-seo/src/integrations/admin/first-time-configuration-integration.php:114` (hook), `:124-132` (tab) |

The Settings React page's in-page tabs (`Site basics`, `Site representation`, `Social profiles`, `Content types`, `Categories & tags`, `Advanced`, `llms.txt`, …) are defined only in the compiled `new-settings` JS bundle; no PHP registers them, so no file:line is given.

### Dashboard widgets

| Key or Screen | Purpose (capability) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo-dashboard-overview` | Dashboard widget "Yoast SEO Posts Overview" | Free | `wordpress-seo/admin/class-yoast-dashboard-widget.php:63` (hook), `:72-80` (`wp_add_dashboard_widget`) |
| `wpseo-wincher-dashboard-overview` | Dashboard widget "Yoast SEO / Wincher: Top Keyphrases" | Free | `wordpress-seo/admin/class-wincher-dashboard-widget.php:44` (hook), `:53-61` (`wp_add_dashboard_widget`) |

---

## Option keys

Yoast option classes are instantiated from the registry in `wordpress-seo/inc/options/class-wpseo-options.php:27-35` and, where missing, created by `maybe_add_option()` via `update_option( $this->option_name, $this->get_defaults() )` — **no explicit autoload argument** (`wordpress-seo/inc/options/class-wpseo-option.php:585-593`; non-multisite branch `:588`). Admin saving goes through `register_setting( $group, $option_name )` with **no autoload argument** (`wordpress-seo/inc/options/class-wpseo-option.php:470-484`).

### Free option keys

| Key or Screen | Purpose (autoload) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo` | Main Yoast SEO settings array (site settings, feature toggles, tokens, tracking). On multisite reads overridden by `wpseo_ms`. Autoload: none explicit (WP default; added `wordpress-seo/inc/options/class-wpseo-option.php:588`). | Free | `wordpress-seo/inc/options/class-wpseo-option-wpseo.php:18`; override `:222` |
| `wpseo_titles` | Title/meta templates, schema types, social image IDs, per-post-type settings. Autoload: none explicit. | Free | `wordpress-seo/inc/options/class-wpseo-option-titles.php:20` |
| `wpseo_social` | Social profiles, OpenGraph/Twitter output toggles, Facebook admin/app ID. Autoload: none explicit. | Free | `wordpress-seo/inc/options/class-wpseo-option-social.php:18` |
| `wpseo_ms` | Multisite network-level defaults for all sites (`multisite_only = true`, stored via `update_site_option` → sitemeta, always autoloaded). | Free | `wordpress-seo/inc/options/class-wpseo-option-ms.php:21`, `:42` |
| `wpseo_taxonomy_meta` | All term SEO values, keyed `[taxonomy][term_id][wpseo_*]`. Autoload: none explicit (written `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:547`). | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:18` |
| `wpseo_llmstxt` | llms.txt configuration (selection mode, included page IDs). Autoload: none explicit. | Free | `wordpress-seo/inc/options/class-wpseo-option-llmstxt.php:20` |
| `wpseo_tracking_only` | "Tracking only" onboarding flags (`task_list_first_opened_on`, `task_first_actioned_on`, `frontend_inspector_first_actioned_on`). Autoload: none explicit. | Free | `wordpress-seo/inc/options/class-wpseo-option-tracking-only.php:20` |
| `wpseo_upgrade_history` | Per-version snapshot of selected options before an upgrade. **Autoload: explicit `false`** | Free | `wordpress-seo/inc/class-upgrade-history.php:20` (name), `:114` (`update_option( ..., false )`) |
| `wpseo_tracking_last_request` | Unix time of the last anonymous tracking request. **Autoload: explicit `'yes'`** | Free | `wordpress-seo/admin/tracking/class-tracking.php:21` (name), `:136` (`update_option( ..., 'yes' )`), read `:165` |
| `wpseo-gsc` | Google Search Console cached data (managed through `Yoast_Form`, group `yoast_wpseo_gsc_options`). Autoload: none explicit. | Free | `wordpress-seo/admin/google_search_console/class-gsc.php:18`; form group `wordpress-seo/admin/google_search_console/views/gsc-display.php:9` |
| `yoast_migrations_free` | Migration status for the Free plugin (written as `MIGRATION_OPTION_KEY . $name` where name = `free`). Autoload: none explicit. | Free | key prefix `wordpress-seo/src/config/migration-status.php:17`; write `:174`; name source `wordpress-seo/src/initializers/migration-runner.php:87`; read `wordpress-seo/admin/tracking/class-tracking-default-data.php:29` |
| `wpseo-cleanup-current-task` | Name of the currently running cleanup task. Autoload: none explicit. | Free | `wordpress-seo/src/integrations/cleanup-integration.php:17` (const), `:269` (write), `:289` (read) |
| `wpseo_llms_txt_content_hash` | MD5 hash of the generated llms.txt file (permission gate). Autoload: none explicit. | Free | `wordpress-seo/src/llms-txt/application/file/commands/populate-file-command-handler.php:17`; write `:81`; read `wordpress-seo/src/llms-txt/infrastructure/file/wordpress-llms-txt-permission-gate.php:49` |
| `wpseo_llms_txt_file_failure` | Reason the llms.txt file could not be generated. Autoload: none explicit. | Free | `wordpress-seo/src/llms-txt/application/file/commands/populate-file-command-handler.php:18`; write `:86`, `:90` |
| `wpseo_myyoast_site_tokens_{resource_indicator}` | Encrypted site-wide MyYoast OAuth token sets, one option per resource indicator. **Autoload: explicit `false`** | Free | prefix `wordpress-seo/src/myyoast-client/infrastructure/token/token-storage.php:33`; write `:86` (`update_option( ..., false )`) |
| `wpseo_myyoast_client_registration_{issuer}` | MyYoast dynamic client-registration records per issuer. **Autoload: explicit `false`** | Free | prefix `wordpress-seo/src/myyoast-client/infrastructure/registration/client-registration.php:40`; write `:537` |
| `wpseo_myyoast_key_pair_{purpose}_{issuer}` | Base64 public key + encrypted Ed25519 private key per purpose/issuer. **Autoload: explicit `false`** | Free | prefix `wordpress-seo/src/myyoast-client/infrastructure/crypto/key-pair-manager.php:27`; write `:208-218` |
| `wpseo_dismiss_{notice_name}` | Per-notice dismissal flag stored as an option (`wpseo_dismiss_<name>`). On multisite stored as a site option; per-user variant stored as user meta. Autoload: none explicit. | Free | `wordpress-seo/admin/ajax/class-yoast-dismissable-notice.php:82` (option), `:88` (site option), `:93` (user meta) |
| `seo_woo_use_third_party_data` | Forced to `'true'` when WooThemes is detected during init. Autoload: none explicit. | Free | `wordpress-seo/inc/options/class-wpseo-options.php:417` |
| `blogdescription` | Core WP tagline, registered for the Settings page options group `wpseo_page_settings`. Autoload: core-managed. | Free | `wordpress-seo/src/integrations/settings-integration.php:49`; registered `:357-361` |
| `wp_attachment_pages_enabled` | Core attachment-pages flag created/updated by Yoast to mirror the `disable-attachment` setting. Autoload: none explicit. | Free | read `wordpress-seo/src/integrations/watchers/indexable-attachment-watcher.php:124`; write `:125` |

### Legacy Free option keys (created by old versions, read/deleted during upgrades)

| Key or Screen | Purpose (autoload) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_indexation` | Pre-1.5 indexing/robots settings migrated into `wpseo_social`/`wpseo_titles`; deleted after migration. Autoload: none explicit (legacy). | Free | `wordpress-seo/inc/options/class-wpseo-option-social.php:304`; `wordpress-seo/inc/options/class-wpseo-option-titles.php:776`; delete `wordpress-seo/inc/options/class-wpseo-options.php:394` |
| `wpseo_xml` | Old XML-sitemap settings migrated into `wpseo`/`wpseo_titles`; then deleted. | Free | `wordpress-seo/inc/class-upgrade.php:259`, `:276`, `:492`, `:539` |
| `wpseo_ryte` | Legacy Ryte indexability setting (migrated from `wpseo_onpage`). | Free | `wordpress-seo/inc/class-upgrade.php:779`, `:782` |
| `wpseo_onpage` | Legacy OnPage.org setting merged into `wpseo_ryte`; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:780`, `:783` |
| `wpseo_permalinks`, `wpseo_internallinks`, `wpseo_rss` | Legacy option keys migrated into the modern options, then deleted. | Free | `wordpress-seo/inc/class-upgrade.php:491`, `:495`, `:493` |
| `wpseo_license_server_version` | Legacy license server version; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:857`, `:859` |
| `wpseo_recalibration_beta_mailinglist_subscription` | Legacy beta mailing-list flag; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:677` |
| `whip_dismiss_timestamp` | Legacy "WHIP" (WordPress Hosting?) dismiss timestamp; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:586` |

### Premium option keys

| Key or Screen | Purpose (autoload) | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_premium` | Premium settings array (`prominent_words_indexing_completed`, `workouts`, `should_redirect_after_install`, `activation_redirect_timestamp`, `dismiss_update_premium_notification`). Autoload: none explicit. Also read by Free's upgrader. | Premium | `wordpress-seo-premium/classes/premium-option.php:18`; register `wordpress-seo-premium/premium.php:124`; read by Free `wordpress-seo/inc/class-upgrade.php:1060` |
| `wpseo_redirect` | Redirect-settings array (`disable_php_redirect`, `separate_file`); registered under group `yoast_wpseo_redirect_options`. Autoload: none explicit. | Premium | `wordpress-seo-premium/classes/premium-redirect-option.php:18`; register `wordpress-seo-premium/premium.php:125`; `register_setting` `wordpress-seo-premium/premium.php:385` |
| `wpseo_premium_version` | Last-run Premium plugin version (upgrade gate). Autoload: none explicit. | Premium | `wordpress-seo-premium/classes/upgrade-manager.php:18`; read `:32`; write `:37` |
| `wpseo_current_version` | Premium machine-readable version code (stored as a **site option**). | Premium | `wordpress-seo-premium/premium.php:24`; read `wordpress-seo-premium/classes/upgrade-manager.php:52`; write `:438` |
| `wpseo-premium-redirects-base` | Full redirect list base (origin/url/type/format). **Autoload: explicit `false`** | Premium | `wordpress-seo-premium/classes/redirect/redirect-option.php:28`; write `:203` (`update_option( ..., false )`) |
| `wpseo-premium-redirects-export-plain` | Plain (non-regex) redirects exported for the .htaccess/web-server file. **Autoload: explicit `true`** (filter `Yoast\WP\SEO\redirects_options_autoload` default `true`) | Premium | `wordpress-seo-premium/classes/redirect/redirect-option.php:35`; write `wordpress-seo-premium/classes/redirect/exporters/redirect-option-exporter.php:47` |
| `wpseo-premium-redirects-export-regex` | Regex redirects exported for the web-server file. **Autoload: explicit `true`** | Premium | `wordpress-seo-premium/classes/redirect/redirect-option.php:42`; write `wordpress-seo-premium/classes/redirect/exporters/redirect-option-exporter.php:48` |
| `wpseo-premium-redirects` | Legacy (pre-3.1) plain redirects option; migrated then deleted. | Premium | `wordpress-seo-premium/classes/redirect/redirect-option.php:16`; upgrade read `wordpress-seo-premium/classes/upgrade-manager.php:379` |
| `wpseo-premium-redirects-regex` | Legacy (pre-3.1) regex redirects option; migrated then deleted. | Premium | `wordpress-seo-premium/classes/redirect/redirect-option.php:21`; upgrade read `wordpress-seo-premium/classes/upgrade-manager.php:380` |
| `yoast_premium_as_an_addon_installer` | State of the "install Premium as add-on" flow (`started`/`completed`). **Autoload: explicit `true`** | Premium | `wordpress-seo-premium/src/addon-installer.php:22`; write `:129`, `:339`, `:394` (`update_option( ..., true )`) |
| `yoast_migrations_premium` | Migration status for the Premium plugin (same `MIGRATION_OPTION_KEY . 'premium'` scheme). | Premium | scheme `wordpress-seo/src/config/migration-status.php:17`; name source `wordpress-seo-premium/src/database/migration-runner-premium.php:35` |
| (Premium also mutates redirect autoload) | Upgrade manager forces `autoload = 'yes'` on the two export options and `autoload = 'no'` on the base option directly in `wp_options`. | Premium | `wordpress-seo-premium/classes/upgrade-manager.php:236-249` |

### Third-party / core WP option keys read (for import, wizard or defaults)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `aioseo_options`, `aioseo_options_dynamic` | All-in-One SEO import source data. | Free | `wordpress-seo/src/actions/importing/aioseo/aioseo-general-settings-importing-action.php:32`; `wordpress-seo/src/actions/importing/aioseo/aioseo-custom-archive-settings-importing-action.php:34` |
| `rank-math-options-titles` | Rank Math import source data. | Free | `wordpress-seo/admin/import/plugins/class-import-rankmath.php:152` |
| `301_redirects`, `301_redirects_wildcard` | Simple 301 Redirects plugin import source data. | Premium | `wordpress-seo-premium/classes/redirect/loaders/redirect-simple-301-redirect-loader.php:21-22` |
| `wpseo_local` | Local SEO add-on option (read for Local defaults). | Free (add-on read) | `wordpress-seo/src/integrations/settings-integration.php:540`, `:773` |
| `auto_update_plugins`, `active_plugins` | Plugin/auto-update state reads (tracking, conflict checks, upgrade). | Free | `wordpress-seo/inc/class-upgrade.php:922`; `wordpress-seo/admin/tracking/class-tracking-plugin-data.php:64`; `wordpress-seo/admin/class-yoast-plugin-conflict.php:74`; `wordpress-seo/src/services/importing/conflicting-plugins-service.php:76` |
| `show_on_front`, `page_on_front`, `page_for_posts` | Homepage/blog configuration reads for the Settings page. | Free | `wordpress-seo/src/integrations/settings-integration.php:527-529` |
| `permalink_structure`, `category_base`, `tag_base` | Permalink-structure reads (rewrite/crawl settings). | Free | `wordpress-seo/inc/class-rewrite.php:138`; `wordpress-seo/inc/class-upgrade.php:1489`; `wordpress-seo/src/services/health-check/postname-permalink-runner.php:30` |
| `blog_public` | Search-engine visibility flag read for indexation decisions. | Free | `wordpress-seo/src/integrations/front-end/indexing-controls.php:52`; `wordpress-seo/src/integrations/watchers/search-engines-discouraged-watcher.php:179`; `wordpress-seo/src/builders/indexable-home-page-builder.php:86` |
| `site_logo` | Site logo ID read for schema/fallbacks. | Free | `wordpress-seo/src/integrations/settings-integration.php:1080`; `wordpress-seo/src/context/meta-tags-context.php:698` |
| `wp_page_for_privacy_policy` | Core privacy-policy page ID read for page-type detection. | Free | `wordpress-seo/src/helpers/current-page-helper.php:456` |

### Transients (rows in `wp_options`, prefix `_transient_`; autoload `no` by nature)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo-dashboard-totals` | Dashboard-widget cached totals. | Free | `wordpress-seo/admin/class-yoast-dashboard-widget.php:18` |
| `wpseo-statistics-totals` | Admin statistics-service cache. | Free | `wordpress-seo/admin/statistics/class-statistics-service.php:18` |
| `wpseo_site_information`, `wpseo_site_information_quick` | Add-on/site information cache. | Free | `wordpress-seo/inc/class-addon-manager.php:22`, `:29` |
| `wpseo_site_kit_set_up_transient` | Site Kit setup interim state. | Free | `wordpress-seo/src/dashboard/user-interface/setup/setup-url-interceptor.php:25` |
| `wpseo_task_list_tasks` | Cached task-list tasks. | Free | `wordpress-seo/src/task-list/infrastructure/tasks-collectors/cached-tasks-collector.php:13` |
| `wpseo_readability_scores` | Cached readability score results. | Free | `wordpress-seo/src/dashboard/infrastructure/score-results/readability-score-results/cached-readability-score-results-collector.php:18` |
| `wpseo_seo_scores` | Cached SEO score results. | Free | `wordpress-seo/src/dashboard/infrastructure/score-results/seo-score-results/cached-seo-score-results-collector.php:18` |
| `wpseo_total_unindexed_general_items`, `wpseo_total_unindexed_posts`, `wpseo_total_unindexed_terms`, `wpseo_total_unindexed_post_type_archives`, `wpseo_unindexed_post_link_count`, `wpseo_unindexed_term_link_count` (plus `_limited` variants) | Unindexed-count caches. | Free | `wordpress-seo/src/actions/indexing/indexable-general-indexation-action.php:16`; `indexable-post-indexation-action.php:23`; `indexable-term-indexation-action.php:20`; `indexable-post-type-archive-indexation-action.php:21`; `post-link-indexing-action.php:18`; `term-link-indexing-action.php:18` |
| `wpseo_semrush_related_keyphrases_%s_%s` | Semrush related-keyphrase cache. | Free | `wordpress-seo/src/actions/semrush/semrush-phrases-action.php:16` |
| `yoast_wincher_pkce` | Wincher OAuth PKCE code. | Free | `wordpress-seo/src/config/wincher-client.php:28` |
| `wpseo_myyoast_oidc_{...}` | OIDC discovery cache. | Free | `wordpress-seo/src/myyoast-client/infrastructure/oidc/discovery-client.php:24` |
| `wpseo_myyoast_jwks_{...}` | JWKS cache. | Free | `wordpress-seo/src/myyoast-client/infrastructure/oidc/id-token-validator.php:28` |
| `wpseo_myyoast_dpop_nonce_{...}` | DPoP nonce cache. | Free | `wordpress-seo/src/myyoast-client/infrastructure/dpop/dpop-handler.php:23` |
| `wpseo_myyoast_refresh_throttle` | Token refresh throttle. | Free | `wordpress-seo/src/myyoast-client/user-interface/management-route.php:67` |
| `yoast_schema_aggregator{...}` | Schema-aggregator cache. | Free | `wordpress-seo/src/schema-aggregator/application/cache/manager.php:21`; `wordpress-seo/src/schema-aggregator/application/cache/xml-manager.php:21` |
| `total_unindexed_prominent_words` | Cached count of indexables missing prominent words. | Premium | `wordpress-seo-premium/src/actions/prominent-words/content-action.php:21` |
| `yoast_beacon_session_data` | HelpScout beacon session data. | Free | read `wordpress-seo/src/integrations/admin/helpscout-beacon.php:226`; write `:265` |

---

## Post meta keys

The canonical Free family is generated from `WPSEO_Meta::$meta_fields` with the prefix `_yoast_wpseo_` (`wordpress-seo/inc/class-wpseo-meta.php:45`). Each field is registered for all post types with a sanitize callback and, when `show_in_rest` is set, re-registered for the `post` subtype with REST exposure (`wordpress-seo/inc/class-wpseo-meta.php:288-314`).

### `_yoast_wpseo_*` meta fields (definition in `inc/class-wpseo-meta.php`)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `_yoast_wpseo_focuskw` | Focus keyphrase. | Free | definition `wordpress-seo/inc/class-wpseo-meta.php:103`; prefix `:45` |
| `_yoast_wpseo_title` | SEO title override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:109` |
| `_yoast_wpseo_metadesc` | Meta description override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:115` |
| `_yoast_wpseo_linkdex` | Legacy SEO (linkdex) score. | Free | `wordpress-seo/inc/class-wpseo-meta.php:123`; non-form duplicate `:202` |
| `_yoast_wpseo_content_score` | Readability score. | Free | `wordpress-seo/inc/class-wpseo-meta.php:127` |
| `_yoast_wpseo_inclusive_language_score` | Inclusive-language score. | Free | `wordpress-seo/inc/class-wpseo-meta.php:131` |
| `_yoast_wpseo_seo_title_score` | SEO title assessment score. | Free | `wordpress-seo/inc/class-wpseo-meta.php:135` |
| `_yoast_wpseo_meta_description_score` | Meta description assessment score. | Free | `wordpress-seo/inc/class-wpseo-meta.php:139` |
| `_yoast_wpseo_is_cornerstone` | Cornerstone-content flag. | Free | `wordpress-seo/inc/class-wpseo-meta.php:143`; migration from `_yst_is_cornerstone` `wordpress-seo/inc/class-upgrade.php:583` |
| `_yoast_wpseo_meta-robots-noindex` | Per-post index/noindex override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:149` |
| `_yoast_wpseo_meta-robots-nofollow` | Per-post follow/nofollow override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:158` |
| `_yoast_wpseo_meta-robots-adv` | Advanced robots directives (`noimageindex`, `noarchive`, `nosnippet`). | Free | `wordpress-seo/inc/class-wpseo-meta.php:166` |
| `_yoast_wpseo_bctitle` | Breadcrumb title override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:175` |
| `_yoast_wpseo_canonical` | Canonical URL override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:179` |
| `_yoast_wpseo_redirect` | Post-level redirect URL field. | Free | `wordpress-seo/inc/class-wpseo-meta.php:183` |
| `_yoast_wpseo_opengraph-title` | OpenGraph title override. | Free | generated from `$social_fields` `wordpress-seo/inc/class-wpseo-meta.php:253-258`, `:266-275` |
| `_yoast_wpseo_opengraph-description` | OpenGraph description override. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_opengraph-image` | OpenGraph image URL. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_opengraph-image-id` | OpenGraph image attachment ID. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_twitter-title` | Twitter title override. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_twitter-description` | Twitter description override. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_twitter-image` | Twitter image URL. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_twitter-image-id` | Twitter image attachment ID. | Free | same as above `wordpress-seo/inc/class-wpseo-meta.php:253-275` |
| `_yoast_wpseo_schema_page_type` | Schema `WebPage` subtype override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:190` |
| `_yoast_wpseo_schema_article_type` | Schema `Article` subtype override. | Free | `wordpress-seo/inc/class-wpseo-meta.php:194` |
| `_yoast_wpseo_is_content_planner_banner_rendered` | Content-planner banner rendered flag. | Free | `wordpress-seo/inc/class-wpseo-meta.php:208` |
| `_yoast_wpseo_is_content_planner_banner_dismissed` | Content-planner banner dismissed flag. | Free | `wordpress-seo/inc/class-wpseo-meta.php:212` |
| `_yoast_wpseo_primary_{taxonomy}` | Primary term selected for a post (e.g. `_yoast_wpseo_primary_category`). | Free | `wordpress-seo/inc/class-wpseo-primary-term.php:44` (read), `:68` (write); example `wordpress-seo/src/ai/content-planner/infrastructure/recent-content/recent-content-collector.php:129` |

### Other post meta keys (Free)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `_yoast_wpseo_meta-robots-{directive}` | Per-directive robots meta written by the Rank Math importer. | Free | `wordpress-seo/admin/import/plugins/class-import-rankmath.php:124` |
| `yoast-structured-data-blocks-images-cache` | Cached image URLs used by structured-data blocks. | Free | write `wordpress-seo/src/integrations/blocks/structured-data-blocks.php:347`; read `:387`; deleted `wordpress-seo/inc/class-upgrade.php:991` |
| `_yoast_wpseo_meta-robots` (legacy) | Old combined robots meta, split into the per-directive keys. | Free | cleanup `wordpress-seo/inc/class-wpseo-meta.php:808-814` |
| `_yoast_wpseo_sitemap-include`, `_yoast_wpseo_sitemap-prio` (legacy) | Old XML-sitemap include/priority meta; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:256`, `:280`, `:290` |
| `_yoast_wpseo_post_image_cache` (legacy) | Old post image cache meta; deleted. | Free | `wordpress-seo/inc/class-upgrade.php:627` |
| `_yst_is_cornerstone` (legacy) | Old cornerstone flag renamed to `_yoast_wpseo_is_cornerstone`. | Free | `wordpress-seo/inc/class-upgrade.php:583` |

### Post meta keys (Premium)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `_yoast_wpseo_focuskeywords` | JSON array of additional/related keyphrases for a post. | Premium | write/read `wordpress-seo-premium/src/integrations/admin/keyword-integration.php:69`; read via `WPSEO_Meta::get_value( 'focuskeywords', ... )` `:39` |
| `_yoast_wpseo_words_for_linking` | Hidden field whose value is saved to the prominent-words table (not to postmeta). | Premium | `wordpress-seo-premium/src/integrations/admin/prominent-words/metabox-integration.php:67` (comment), `:79` (key check) |
| `_yst_prominent_words_version` | Version of the prominent-words analysis stored per post. | Premium | `wordpress-seo-premium/classes/premium-prominent-words-versioning.php:16`; rename from public key `:36-41` |
| `_yoast_post_redirect_info` | Undo information for a redirect created by the post watcher. | Premium | write `wordpress-seo-premium/classes/post-watcher.php:164`; read/delete `wordpress-seo-premium/classes/redirect-undo-endpoint.php:141-142` |
| `_yoast_indexnow_last_ping` | Unix time of the last IndexNow ping for a post. | Premium | read `wordpress-seo-premium/src/integrations/index-now-ping.php:114`; write `:155` |
| `footnotes` | Footnotes imported from another SEO plugin. | Premium | `wordpress-seo-premium/src/integrations/admin/extension-importer/importer.php:176` |

### Indexable-related mapping (post meta → indexable columns)

Indexables themselves live in custom tables (see `yoast-code-b.md`), not post meta. The builder maps the `_yoast_wpseo_*` keys into indexable columns.

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `focuskw`→`primary_focus_keyword`, `canonical`, `title`, `metadesc`→`description`, `bctitle`→`breadcrumb_title`, `opengraph-title/image/image-id/description`, `twitter-title/image/image-id/description`, `estimated-reading-time-minutes` | Post-meta-to-indexable column map | Free | `wordpress-seo/src/builders/indexable-post-builder.php:325-341` |

---

## Term meta keys

Free stores term SEO values inside the single option `wpseo_taxonomy_meta` (not in real term meta), keyed `[taxonomy][term_id][wpseo_*]`. The per-term defaults are defined in `WPSEO_Taxonomy_Meta::$defaults_per_term`; reads prepend `wpseo_` (`wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:416-417`).

### Free term meta keys (stored within `wpseo_taxonomy_meta`)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_title` | Term SEO title template. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:58` |
| `wpseo_desc` | Term meta description. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:59` |
| `wpseo_canonical` | Term canonical URL. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:60` |
| `wpseo_bctitle` | Term breadcrumb title. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:61` |
| `wpseo_noindex` | Term index/noindex (`default`/`index`/`noindex`). | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:62`; options `:85-89` |
| `wpseo_focuskw` | Term focus keyphrase. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:63` |
| `wpseo_linkdex` | Term SEO score (legacy). | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:64` |
| `wpseo_content_score` | Term readability score. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:65` |
| `wpseo_inclusive_language_score` | Term inclusive-language score. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:66` |
| `wpseo_focuskeywords` | Term related keyphrases (JSON). | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:67` |
| `wpseo_keywordsynonyms` | Term keyphrase synonyms (JSON). | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:68` |
| `wpseo_is_cornerstone` | Term cornerstone flag. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:69` |
| `wpseo_opengraph-title` | Term OpenGraph title. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:72` |
| `wpseo_opengraph-description` | Term OpenGraph description. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:73` |
| `wpseo_opengraph-image` | Term OpenGraph image URL. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:74` |
| `wpseo_opengraph-image-id` | Term OpenGraph image attachment ID. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:75` |
| `wpseo_twitter-title` | Term Twitter title. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:76` |
| `wpseo_twitter-description` | Term Twitter description. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:77` |
| `wpseo_twitter-image` | Term Twitter image URL. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:78` |
| `wpseo_twitter-image-id` | Term Twitter image attachment ID. | Free | `wordpress-seo/inc/options/class-wpseo-taxonomy-meta.php:79` |

### Real WP term meta keys (Premium)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `_yoast_term_redirect_info` | Undo information for a redirect created for a term. | Premium | write `wordpress-seo-premium/classes/term-watcher.php:181`; read/delete `wordpress-seo-premium/classes/redirect-undo-endpoint.php:147-148` |

---

## User meta keys

### Free user meta (framework + core integrations)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `_yoast_wpseo_profile_updated` | Unix time the user profile was last updated (used for author sitemap `lastmod`). | Free | write `wordpress-seo/src/user-meta/user-interface/custom-meta-integration.php:66`; read `wordpress-seo/inc/sitemaps/class-author-sitemap-provider.php:75`, `:92` |
| `wpseo_title` | Author SEO title template. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/author-title.php:45` |
| `wpseo_metadesc` | Author meta description template. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/author-metadesc.php:45` |
| `wpseo_pronouns` | Author pronouns. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/author-pronouns.php:45` |
| `wpseo_noindex_author` | Noindex the author archive. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/noindex-author.php:45` |
| `wpseo_keyword_analysis_disable` | Disable keyphrase analysis for the user. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/keyword-analysis-disable.php:45`; read `wordpress-seo/src/editors/framework/keyphrase-analysis.php:46` |
| `wpseo_content_analysis_disable` | Disable readability analysis for the user. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/content-analysis-disable.php:45`; read `wordpress-seo/src/editors/framework/readability-analysis.php:46` |
| `wpseo_inclusive_language_analysis_disable` | Disable inclusive-language analysis for the user. | Free | `wordpress-seo/src/user-meta/framework/custom-meta/inclusive-language-analysis-disable.php:45`; read `wordpress-seo/src/editors/framework/inclusive-language-analysis.php:72` |
| `_yoast_wpseo_introductions` | JSON list of seen product introductions. | Free | `wordpress-seo/src/introductions/infrastructure/introductions-seen-repository.php:15` |
| `_yoast_wpseo_wistia_embed_permission` | Whether the user granted Wistia embed permission. | Free | `wordpress-seo/src/introductions/infrastructure/wistia-embed-permission-repository.php:15` |
| `_yoast_wpseo_ai_content_planner_banner_permanently_dismissed` | Permanent dismissal of the content-planner AI banner. | Free | `wordpress-seo/src/ai/content-planner/user-interface/banner-permanent-dismissal-route.php:25` |
| `_yoast_wpseo_ai_consent` | AI feature consent flag (legacy AI consent handler). | Free | `wordpress-seo/src/deprecated/src/ai-consent/application/consent-handler.php:119` |
| `_yoast_wpseo_ai_generator_access_jwt` | AI generator access token (JWT). | Free | `wordpress-seo/src/ai/authorization/infrastructure/access-token-user-meta-repository-interface.php:14` |
| `_yoast_wpseo_ai_generator_refresh_jwt` | AI generator refresh token (JWT). | Free | `wordpress-seo/src/ai/authorization/infrastructure/refresh-token-user-meta-repository-interface.php:14` |
| `_yoast_wpseo_ai_generator_callback_url_hash` | MD5 of the AI generator OAuth callback URL. | Free | `wordpress-seo/src/ai/authorization/application/token-manager.php:240` |
| `_yoast_alerts_dismissed` | JSON list of dismissed alerts. | Free | `wordpress-seo/src/actions/alert-dismissal-action.php:12` |
| `_wpseo_myyoast_user_tokens_{issuer}` | Per-user MyYoast OAuth token sets. | Free | `wordpress-seo/src/myyoast-client/infrastructure/token/user-token-storage.php:34` |
| `yoast_notifications` | Per-user stored notifications (`update_user_option` → `wp_<id>_yoast_notifications`). | Free | `wordpress-seo/admin/class-yoast-notification-center.php:20` (const), `:658` (write) |
| `wpseo_dismiss_{notice_name}` | Per-user notice dismissal variant. | Free | `wordpress-seo/admin/ajax/class-yoast-dismissable-notice.php:93` |
| `_yoast_wpseo_bulk_editor_tour_opt_in_notification_seen` | Whether the bulk-editor tour opt-in notification was seen. | Free | `wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:454`; `wordpress-seo/src/general/user-interface/general-page-integration.php:298` |
| `_yoast_wpseo_{key}_opt_in_notification_seen` | Generic per-feature opt-in notification seen flag. | Free | `wordpress-seo/src/general/user-interface/opt-in-route.php:101` |

### Free additional user contact methods (keys are the raw `user_contactmethods` meta keys)

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `facebook` | Facebook profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/facebook.php:19` |
| `instagram` | Instagram profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/instagram.php:19` |
| `linkedin` | LinkedIn profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/linkedin.php:19` |
| `myspace` | MySpace profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/myspace.php:19` |
| `pinterest` | Pinterest profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/pinterest.php:19` |
| `soundcloud` | SoundCloud profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/soundcloud.php:19` |
| `tumblr` | Tumblr profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/tumblr.php:19` |
| `wikipedia` | Wikipedia profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/wikipedia.php:19` |
| `twitter` | Twitter/X profile URL (class `X` returns key `twitter`). | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/x.php:19` |
| `youtube` | YouTube profile URL. | Free | `wordpress-seo/src/user-meta/framework/additional-contactmethods/youtube.php:19` |
| (registration) | The above are merged via the `user_contactmethods` filter. | Free | `wordpress-seo/src/user-meta/user-interface/additional-contactmethods-integration.php:38`, `:51-55` |

### Premium user meta

| Key or Screen | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wpseo_user_schema` | Serialized array of the user's schema fields (`honorificPrefix`, `honorificSuffix`, `birthDate`, `gender`, `award`, `knowsAbout`, `knowsLanguage`, `jobTitle`, `worksFor`). | Premium | read `wordpress-seo-premium/src/integrations/admin/user-profile-integration.php:148`; write `:188`; frontend read `wordpress-seo-premium/src/integrations/user-profile-integration.php:35` |
| `mastodon` | Mastodon profile URL user contact method (added via `user_contactmethods`; the helper `add_mastodon_to_user_contactmethods` is deprecated 22.6). | Premium | `wordpress-seo-premium/src/integrations/third-party/mastodon.php:139-147` |

---

## Unverified / omitted

- The in-page tabs of the React Settings app are not registered in PHP; only the compiled `new-settings` bundle defines them, so they are omitted.
- `Yoast_Form`-managed option groups (e.g. `wpseo-gsc` under `yoast_wpseo_gsc_options`, and the `wpseo_redirect` group `yoast_wpseo_redirect_options`) do not pass an explicit autoload argument to `register_setting`, so autoload follows the WordPress default.
- Transients created dynamically with non-literal names (e.g. the `_transient_` prefix is applied by WordPress) are listed by their constant/prefix only where a literal could be read.
