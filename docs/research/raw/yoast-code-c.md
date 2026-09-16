# Yoast SEO — Code Inventory (Free v28.4 vs Premium v27.8)

Paths are relative to `wp-content/plugins/`. Free = `wordpress-seo/`, Premium = `wordpress-seo-premium/`.
`Main::API_V1_NAMESPACE` = `yoast/v1` (`wordpress-seo/src/main.php:32`).

## Premium additions over free

| Item | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Redirect manager (admin page + DB table) | Adds a Redirects submenu (`wpseo_redirects`) to create/manage 301/302/307/410/451 redirects. | Premium | wordpress-seo-premium/premium.php:336 (add_submenu_pages), wordpress-seo-premium/classes/redirect/redirect-page.php:13, wordpress-seo-premium/classes/redirect/redirect-manager.php:11 |
| Redirect REST endpoint | Exposes redirect CRUD over REST (`redirects`, `redirects/delete`, `redirects/list`, `redirects/update`, `redirects/settings`). | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:13 |
| Redirect undo endpoint | Deletes the auto-created redirect for a post/term (`redirects/undo-for-object`). | Premium | wordpress-seo-premium/classes/redirect-undo-endpoint.php:11 |
| Auto-redirect on slug change | Creates a redirect automatically when a post/term slug is changed or trashed. | Premium | wordpress-seo-premium/classes/post-watcher.php:110, wordpress-seo-premium/classes/term-watcher.php:127, wordpress-seo-premium/classes/watcher.php:154 |
| Redirect import (CSV/.htaccess/plugins) | Imports redirects from CSV, `.htaccess` and other plugins. | Premium | wordpress-seo-premium/classes/premium-import-manager.php:27, wordpress-seo-premium/classes/redirect/redirect-importer.php:11 |
| Redirect export (CSV/Apache/Nginx/.htaccess) | Exports redirects in multiple server formats. | Premium | wordpress-seo-premium/classes/premium-redirect-export-manager.php:11, wordpress-seo-premium/classes/redirect/exporters/redirect-apache-exporter.php:11 |
| Redirect sitemap filter | Removes redirected URLs from the XML sitemap. | Premium | wordpress-seo-premium/premium.php:205, wordpress-seo-premium/classes/redirect/redirect-sitemap-filter.php:11 |
| 404 admin-bar "create redirect" link | Adds a redirect action to the toolbar on 404 pages. | Premium | wordpress-seo-premium/premium.php:255 |
| Internal linking suggestions | Suggests internal links to add to a post from an indexed corpus. | Premium | wordpress-seo-premium/src/routes/link-suggestions-route.php:24, wordpress-seo-premium/src/actions/link-suggestions-action.php:16 |
| Link-suggestions metabox | Renders the internal-link suggestions metabox per post type. | Premium | wordpress-seo-premium/classes/metabox-link-suggestions.php:11 |
| Prominent words (indexing + storage) | Extracts prominent words per indexable and stores them in a custom table. | Premium | wordpress-seo-premium/src/routes/prominent-words-route.php:31, wordpress-seo-premium/src/models/prominent-words.php, wordpress-seo-premium/src/actions/prominent-words/content-action.php:19, wordpress-seo-premium/src/repositories/prominent-words-repository.php |
| Prominent-words indexing integration | Tab/indexing UI and "missing indexables" bucket for prominent words. | Premium | wordpress-seo-premium/src/integrations/admin/prominent-words/indexing-integration.php:23, wordpress-seo-premium/src/integrations/missing-indexables-count-integration.php:15 |
| Prominent-words supported post types/taxonomies | Declares which content types support prominent words (related keyphrases). | Premium | wordpress-seo-premium/classes/premium-prominent-words-support.php:11 |
| Related keyphrases (multi-keyword) | Adds extra focus keyphrases and synonyms inputs; takes them into account in analysis. | Premium | wordpress-seo-premium/classes/multi-keyword.php:11, wordpress-seo-premium/src/integrations/admin/related-keyphrase-filter-integration.php:14 |
| Bulk editing (related keyphrases) | Extends the free bulk editor so keyphrase filtering also matches related keyphrases; base bulk editor itself is free. | Premium | wordpress-seo-premium/src/integrations/admin/related-keyphrase-filter-integration.php:14; free base at wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:27 |
| Bulk editing (keyphrase column) | Adds the focus/related keyphrase column integration for list tables. | Premium | wordpress-seo-premium/src/integrations/admin/keyword-integration.php:13 |
| Social previews | Live Facebook/X/Slack preview of the snippet in the editor. | Premium | wordpress-seo-premium/classes/social-previews.php:20 |
| OpenGraph for archives | Adds OpenGraph output for author, date, post-type and term archives. | Premium | wordpress-seo-premium/src/integrations/opengraph-author-archive.php:8, wordpress-seo-premium/src/integrations/opengraph-date-archive.php:8, wordpress-seo-premium/src/integrations/opengraph-posttype-archive.php:8, wordpress-seo-premium/src/integrations/opengraph-term-archive.php:8, wordpress-seo-premium/src/integrations/opengraph-post-type.php:8 |
| Organization schema detail | Adds organization fields (name, logo, social) to the Schema graph. | Premium | wordpress-seo-premium/src/integrations/organization-schema-integration.php:12 |
| Publishing principles schema | Adds `publishingPrinciples` to the Schema graph. | Premium | wordpress-seo-premium/src/integrations/publishing-principles-schema-integration.php:17 |
| User profile / additional Schema fields | Adds user Schema fields and extra contact methods (e.g. Mastodon) to the user profile. | Premium | wordpress-seo-premium/src/integrations/user-profile-integration.php:11, wordpress-seo-premium/src/user-meta/framework/additional-contactmethods |
| Orphaned content filter | Adds an "Orphaned content" post-list filter for posts with no internal links. | Premium | wordpress-seo-premium/classes/premium-orphaned-post-filter.php:14, wordpress-seo-premium/classes/premium-orphaned-post-query.php:13 |
| Stale cornerstone content filter | Adds a "Stale cornerstone content" post-list filter (older than 6 months). | Premium | wordpress-seo-premium/classes/premium-stale-cornerstone-content-filter.php:11, wordpress-seo-premium/src/integrations/watchers/stale-cornerstone-content-watcher.php:12 |
| Cornerstone admin columns | Adds Cornerstone columns/filters for posts and taxonomies. | Premium | wordpress-seo-premium/src/integrations/admin/cornerstone-column-integration.php:21, wordpress-seo-premium/src/integrations/admin/cornerstone-taxonomy-column-integration.php:20 |
| Workouts (extra routes/UI) | Site-wide SEO "workouts" with premium noindex/link-suggestion/cornerstone workflows. | Premium | wordpress-seo-premium/src/routes/workouts-route.php:25, wordpress-seo-premium/src/integrations/admin/workouts-integration.php:17, wordpress-seo-premium/src/integrations/routes/workouts-routes-integration.php:18 |
| AI Optimize | AI-powered rewrite/optimization of content against an SEO assessment. | Premium | wordpress-seo-premium/src/ai/optimize/optimizer/application/optimizer.php:28, wordpress-seo-premium/src/ai/optimize/optimizer/user-interface/ai-optimize-route.php:19 |
| AI Summarize | Generates a key-takeaways summary of the content. | Premium | wordpress-seo-premium/src/ai/summarize/application/summarizer.php:27, wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-route.php:20 |
| AI Summarize block (`yoast-seo/ai-summarize`) | Gutenberg block rendering the AI summary. | Premium | wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-integration.php:113, wordpress-seo-premium/assets/blocks/ai-blocks/summary/block.json |
| Keyword export (CSV) | Exports focus/related keyphrases for posts and terms as CSV. | Premium | wordpress-seo-premium/classes/premium-keyword-export-manager.php:13, wordpress-seo-premium/classes/export/export-keywords-csv.php:13 |
| IndexNow ping | Pings IndexNow (Bing/Yandex) on publish/update/delete in production. | Premium | wordpress-seo-premium/src/integrations/index-now-ping.php:15, wordpress-seo-premium/src/initializers/index-now-key.php:12 |
| Related links block (`yoast-seo/related-links`) | Gutenberg block listing related internal links from link suggestions. | Premium | wordpress-seo-premium/src/integrations/blocks/related-links-block.php:11 |
| Estimated reading time block (`yoast-seo/estimated-reading-time`) | Gutenberg block showing an estimated reading time. | Premium | wordpress-seo-premium/src/integrations/blocks/estimated-reading-time-block.php:12 |
| Siblings block (`yoast-seo/siblings`) | Gutenberg block listing sibling pages sharing the same parent. | Premium | wordpress-seo-premium/classes/blocks/siblings-block.php:12, wordpress-seo-premium/premium.php:105 |
| Subpages block (`yoast-seo/subpages`) | Gutenberg block listing child pages of the current page. | Premium | wordpress-seo-premium/classes/blocks/subpages-block.php:12, wordpress-seo-premium/premium.php:106 |
| Table of contents block (`yoast-seo/table-of-contents`) | Gutenberg block that builds a TOC from headings. | Premium | wordpress-seo-premium/assets/blocks/dynamic-blocks/table-of-contents/block.json |
| Premium metabox | Loads premium metabox configuration/assets (social previews, related keyphrases, etc.). | Premium | wordpress-seo-premium/classes/premium-metabox.php:15, wordpress-seo-premium/premium.php:92 |
| Inclusive language columns/filters | Adds inclusive-language assessment columns and filters to list tables. | Premium | wordpress-seo-premium/src/integrations/admin/inclusive-language-column-integration.php:23, wordpress-seo-premium/src/integrations/admin/inclusive-language-filter-integration.php:18, wordpress-seo-premium/src/integrations/admin/inclusive-language-taxonomy-column-integration.php:19 |
| Replacement variables integration | Provides additional replacement variables in the editor. | Premium | wordpress-seo-premium/src/integrations/admin/replacement-variables-integration.php:17 |
| Robots.txt integration | Adds premium rules (e.g. sitemap) to `robots.txt`. | Premium | wordpress-seo-premium/src/integrations/front-end/robots-txt-integration.php:13 |
| Mastodon social profile | Outputs Mastodon `rel="me"` links and adds Mastodon to Schema/person profiles. | Premium | wordpress-seo-premium/src/integrations/third-party/mastodon.php:13, wordpress-seo-premium/src/presenters/mastodon-link-presenter.php |
| WooCommerce HPOS compatibility | Declares compatibility with WooCommerce HPOS. | Premium | wordpress-seo-premium/src/initializers/woocommerce.php:12 |
| Elementor (premium editor) | Integrates Premium features (social previews, link suggestions) into Elementor. | Premium | wordpress-seo-premium/src/integrations/third-party/elementor-premium.php:30 |
| Elementor preview | Premium asset handling in the Elementor preview iframe. | Premium | wordpress-seo-premium/src/integrations/third-party/elementor-preview.php:12 |
| Algolia integration | Syncs noindex state and attributes to Algolia search. | Premium | wordpress-seo-premium/src/integrations/third-party/algolia.php:17 |
| Easy Digital Downloads schema | Adds EDD product schema integration. | Premium | wordpress-seo-premium/src/integrations/third-party/edd.php:15 |
| Wincher keyphrases enhancement | Adds related keyphrases to the Wincher tracking arrays. | Premium | wordpress-seo-premium/src/integrations/third-party/wincher-keyphrases.php:14 |
| TranslationsPress | Loads Premium translations from TranslationsPress. | Premium | wordpress-seo-premium/src/integrations/third-party/translationspress.php:14 |
| Custom fields plugin | Adds Yoast-defined custom fields to the content analysis. | Premium | wordpress-seo-premium/classes/custom-fields-plugin.php:12 |
| WordPress (WXR) extension importer | Processes imported content (blocks/footnotes/media) on WP import. | Premium | wordpress-seo-premium/src/integrations/admin/extension-importer/importer.php:12 |
| Frontend inspector | Injects premium asset/attribute hooks into the front end. | Premium | wordpress-seo-premium/src/integrations/frontend-inspector.php:17 |
| Prominent-words watcher | Re-indexes prominent words when content changes. | Premium | wordpress-seo-premium/src/integrations/watchers/prominent-words-watcher.php:13 |
| Redirect capabilities | Registers `wpseo_manage_redirects` capability. | Premium | wordpress-seo-premium/classes/premium-register-capabilities.php:30 |
| Premium redirect settings | Registers `wpseo_redirect` setting. | Premium | wordpress-seo-premium/premium.php:384 |
| WP-CLI redirect commands | `wp yoast redirect` create/delete/follow/list/update commands. | Premium | wordpress-seo-premium/cli/cli-redirect-command-namespace.php, wordpress-seo-premium/src/initializers/wp-cli-initializer.php:12 |

## Premium-gated items found in free code

| Item | Gate | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Link suggestions feature toggle | `'premium' => true`, setting `enable_link_suggestions`, upsell `yoa.st/get-link-suggestions` | Free (upsell config) | wordpress-seo/admin/views/class-yoast-feature-toggles.php:121 |
| IndexNow feature toggle | `'premium' => true`, setting `enable_index_now`, upsell `yoa.st/get-indexnow` | Free (upsell config) | wordpress-seo/admin/views/class-yoast-feature-toggles.php:191 |
| AI title & description generator toggle | `'premium' => true`, setting `enable_ai_generator`, upsell `yoa.st/get-ai-generator` | Free (upsell config) | wordpress-seo/admin/views/class-yoast-feature-toggles.php:202 |
| Algolia integration toggle | `'premium' => true`, setting `algolia_integration_active` | Free (upsell config) | wordpress-seo/admin/views/class-yoast-integration-toggles.php:74 |
| Premium detection helper | `is_premium()` returns `defined('WPSEO_PREMIUM_FILE')` | Free | wordpress-seo/src/helpers/product-helper.php:37 |
| Premium-active conditional | Active when `product_helper->is_premium()` | Free | wordpress-seo/src/conditionals/premium-active-conditional.php:16 |
| Premium-inactive conditional | Active when NOT premium | Free | wordpress-seo/src/conditionals/premium-inactive-conditional.php:16 |
| Workouts subscription gate | `has_valid_subscription(PREMIUM_SLUG)` required to run workouts | Free | wordpress-seo/src/integrations/admin/workouts-integration.php:286 |
| Workouts update gate | `! is_premium() || ! should_update_premium()` | Free | wordpress-seo/src/integrations/admin/workouts-integration.php:295 |
| Indexing tool premium gate | `addon_manager->has_valid_subscription(PREMIUM_SLUG)` | Free | wordpress-seo/src/integrations/admin/indexing-tool-integration.php:193 |
| Bulk editor premium version gate | `is_premium_version_supported()` requires Premium `> 28.1-RC0` | Free | wordpress-seo/src/bulk-editor/user-interface/bulk-editor-integration.php:335 |
| Yoast form subscription gate | `is_premium() && has_valid_subscription(PREMIUM_SLUG)` | Free | wordpress-seo/admin/class-yoast-form.php:199 |
| Short-link helper premium branch | `is_premium()` chooses premium shortlinks | Free | wordpress-seo/src/helpers/short-link-helper.php:131 |
| Deactivated premium integration | Fires when `! defined('WPSEO_PREMIUM_FILE')` | Free | wordpress-seo/src/integrations/admin/deactivated-premium-integration.php:146 |
| Premium popup | Suppressed when `defined('WPSEO_PREMIUM_FILE')` | Free | wordpress-seo/admin/class-premium-popup.php:74 |
| Delayed premium upsell | Shows only when NOT premium on Yoast pages | Free | wordpress-seo/src/introductions/application/delayed-premium-upsell.php:87 |
| Black Friday premium announcement | `! is_premium()` && promotion active | Free | wordpress-seo/src/introductions/application/black-friday-announcement.php:79 |
| Product upsell notice | Shows when NOT premium | Free | wordpress-seo/admin/class-product-upsell-notice.php:146 |
| Upgrade sidebar menu | Premium/non-premium label branch | Free | wordpress-seo/src/plans/user-interface/upgrade-sidebar-menu-integration.php:123 |
| Brand insights page routing | Premium -> `wpseo_brand_insights_premium` | Free | wordpress-seo/src/integrations/admin/brand-insights-page.php:66 |
| Network feature toggle premium hide | Hides premium toggles when not premium | Free | wordpress-seo/admin/views/tabs/network/features.php:80 |
| Network integration toggle premium hide | Hides premium integrations when not premium | Free | wordpress-seo/admin/views/tabs/network/integrations.php:67 |
| Addon manager premium branch | `is_premium()` branch in subscription handling | Free | wordpress-seo/inc/class-addon-manager.php:687 |
| Admin init premium branch | `! is_premium()` branch | Free | wordpress-seo/admin/class-admin-init.php:241 |
| Admin bar menu premium branch | `! is_premium()` redirects link gating | Free | wordpress-seo/inc/class-wpseo-admin-bar-menu.php:259 |
| Slug-change watcher | Returns early when premium is active (premium does redirects) | Free | wordpress-seo/admin/watchers/class-slug-change-watcher.php:20 |
| AI editor conditional | Requires premium for AI editor | Free | wordpress-seo/src/conditionals/ai-editor-conditional.php:110 |
| Inclusive-language editor premium | `is_premium()` branch in analysis config | Free | wordpress-seo/src/editors/framework/inclusive-language-analysis.php:94 |
| Schema configuration premium | `is_premium()` passed to schema API integrations | Free | wordpress-seo/src/schema/application/configuration/schema-configuration.php:98 |
| First-time configuration premium | `isPremium` flag exposed | Free | wordpress-seo/src/integrations/admin/first-time-configuration-integration.php:214 |
| Settings integration premium flag | `isPremium` exposed to settings | Free | wordpress-seo/src/integrations/settings-integration.php:549 |

## REST routes

| Route (namespace) | Method / Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `yoast/v1/indexing/complete` | POST – mark indexing complete | Free | wordpress-seo/src/routes/indexing-route.php:38, :288 |
| `yoast/v1/indexing/indexables-complete` | POST – indexables complete | Free | wordpress-seo/src/routes/indexing-route.php:52, :303 |
| `yoast/v1/indexing/prepare` | POST – prepare indexing | Free | wordpress-seo/src/routes/indexing-route.php:66, :300 |
| `yoast/v1/indexing/posts` | POST – index posts | Free | wordpress-seo/src/routes/indexing-route.php:80, :288 |
| `yoast/v1/indexing/terms` | POST – index terms | Free | wordpress-seo/src/routes/indexing-route.php:94, :291 |
| `yoast/v1/indexing/post-type-archives` | POST – index post-type archives | Free | wordpress-seo/src/routes/indexing-route.php:108, :294 |
| `yoast/v1/indexing/general` | POST – index general | Free | wordpress-seo/src/routes/indexing-route.php:122, :297 |
| `yoast/v1/link-indexing/posts` | POST – index post links | Free | wordpress-seo/src/routes/indexing-route.php:136, :309 |
| `yoast/v1/link-indexing/terms` | POST – index term links | Free | wordpress-seo/src/routes/indexing-route.php:150, :312 |
| `yoast/v1/supported-features` | GET – supported features | Free | wordpress-seo/src/routes/supported-features-route.php:19, :44 |
| `yoast/v1/alerts/dismiss` | POST – dismiss alert | Free | wordpress-seo/src/routes/alert-dismissal-route.php:30, :73 |
| `yoast/v1/configuration/site_representation` | POST – save site representation | Free | wordpress-seo/src/routes/first-time-configuration-route.php:30, :125 |
| `yoast/v1/configuration/social_profiles` | POST – save social profiles | Free | wordpress-seo/src/routes/first-time-configuration-route.php:37, :143 |
| `yoast/v1/configuration/check_capability` | GET – check user capability | Free | wordpress-seo/src/routes/first-time-configuration-route.php:51, :155 |
| `yoast/v1/configuration/enable_tracking` | POST – toggle tracking | Free | wordpress-seo/src/routes/first-time-configuration-route.php:44, :168 |
| `yoast/v1/configuration/save_configuration_state` | POST – save finished steps | Free | wordpress-seo/src/routes/first-time-configuration-route.php:58, :181 |
| `yoast/v1/configuration/get_configuration_state` | GET – get finished steps | Free | wordpress-seo/src/routes/first-time-configuration-route.php:65, :190 |
| `yoast/v1/integrations/set_active` | POST – activate/deactivate integration | Free | wordpress-seo/src/routes/integrations-route.php:30, :69 |
| `yoast/v1/meta/search` | GET – search meta | Free | wordpress-seo/src/routes/meta-search-route.php:22, :38 |
| `yoast/v1/import/(?P<plugin>[\w-]+)/(?P<type>[\w-]+)` | POST – run importer | Free | wordpress-seo/src/routes/importing-route.php:28, :64 |
| `yoast/v1/wincher/authorization-url` | GET – Wincher OAuth authorization URL | Free | wordpress-seo/src/routes/wincher-route.php:30, :132 |
| `yoast/v1/wincher/authenticate` | POST – Wincher OAuth authenticate | Free | wordpress-seo/src/routes/wincher-route.php:37, :150 |
| `yoast/v1/wincher/keyphrases/track` | POST – track keyphrases | Free | wordpress-seo/src/routes/wincher-route.php:44, :163 |
| `yoast/v1/wincher/keyphrases` | POST – get tracked keyphrases | Free | wordpress-seo/src/routes/wincher-route.php:51, :182 |
| `yoast/v1/wincher/keyphrases/untrack` | DELETE – untrack keyphrase | Free | wordpress-seo/src/routes/wincher-route.php:58, :190 |
| `yoast/v1/wincher/account/limit` | GET – Wincher account limit | Free | wordpress-seo/src/routes/wincher-route.php:65, :198 |
| `yoast/v1/wincher/account/upgrade-campaign` | GET – Wincher upgrade campaign | Free | wordpress-seo/src/routes/wincher-route.php:72, :206 |
| `yoast/v1/semrush/authenticate` | POST – Semrush OAuth authenticate | Free | wordpress-seo/src/routes/semrush-route.php:30, :125 |
| `yoast/v1/semrush/country_code` | POST – set Semrush country code | Free | wordpress-seo/src/routes/semrush-route.php:37, :139 |
| `yoast/v1/semrush/related_keyphrases` | GET – fetch related keyphrases | Free | wordpress-seo/src/routes/semrush-route.php:44, :156 |
| `yoast/v1/workouts` | GET/POST – get/set workouts | Free | wordpress-seo/src/routes/workouts-route.php:23, :65 |
| `yoast/v1/get_head` | GET – head for URL | Free | wordpress-seo/src/routes/indexables-head-route.php:22, :73 |
| `yoast/v1/seen-opt-in-notification` | POST – mark opt-in notification seen | Free | wordpress-seo/src/general/user-interface/opt-in-route.php:30, :88 |
| `yoast/v1/schema-aggregator/get-schema/(?P<post_type>…)` | GET – schema aggregator by post type | Free | wordpress-seo/src/schema-aggregator/user-interface/site-schema-aggregator-route.php:37, :134 |
| `yoast/v1/schema-aggregator/get-xml` | GET – schema aggregator XML | Free | wordpress-seo/src/schema-aggregator/user-interface/site-schema-aggregator-xml-route.php:29, :68 |
| `yoast/v1/new-content-type-visibility/dismiss-post-type` | POST – dismiss new post-type notice | Free | wordpress-seo/src/content-type-visibility/user-interface/content-type-visibility-dismiss-new-route.php:32, :85 |
| `yoast/v1/new-content-type-visibility/dismiss-taxonomy` | POST – dismiss new taxonomy notice | Free | wordpress-seo/src/content-type-visibility/user-interface/content-type-visibility-dismiss-new-route.php:39, :86 |
| `yoast/v1/bulk_editor/posts` | GET – bulk editor posts | Free | wordpress-seo/src/bulk-editor/user-interface/posts-route.php:40, :130 |
| `yoast/v1/bulk_editor/posts_content` | GET – bulk editor post content | Free | wordpress-seo/src/bulk-editor/user-interface/posts-content-route.php:39, :73 |
| `yoast/v1/bulk_editor/update_scores` | POST – bulk update scores | Free | wordpress-seo/src/bulk-editor/user-interface/scores-route.php:35, :87 |
| `yoast/v1/bulk_editor/update_search` | POST – bulk update search settings | Free | wordpress-seo/src/bulk-editor/user-interface/search-bulk-update-route.php:18 (via abstract-bulk-update-route.php:105) |
| `yoast/v1/bulk_editor/update_social` | POST – bulk update social settings | Free | wordpress-seo/src/bulk-editor/user-interface/social-bulk-update-route.php:18 (via abstract-bulk-update-route.php:105) |
| `yoast/v1/action_tracking` | POST – action tracking | Free | wordpress-seo/src/tracking/user-interface/action-tracking-route.php:37, :83 |
| `yoast/v1/get_tasks` | GET – task list tasks | Free | wordpress-seo/src/task-list/user-interface/tasks/get-tasks-route.php:32, :89 |
| `yoast/v1/complete_task` | POST – complete task | Free | wordpress-seo/src/task-list/user-interface/tasks/complete-task-route.php:35, :92 |
| `yoast/v1/available_posts` | GET – llms.txt available posts | Free | wordpress-seo/src/llms-txt/user-interface/available-posts-route.php:37, :73 |
| `yoast/v1/introductions/(?P<introduction_id>[\w-]+)/seen` | POST – mark introduction seen | Free | wordpress-seo/src/introductions/user-interface/introductions-seen-route.php:30, :76 |
| `yoast/v1/wistia_embed_permission` | GET – Wistia embed permission | Free | wordpress-seo/src/introductions/user-interface/wistia-embed-permission-route.php:27, :63 |
| `yoast/v1/myyoast/status` | GET – MyYoast registration status | Free | wordpress-seo/src/myyoast-client/user-interface/management-route.php:42, :145 |
| `yoast/v1/myyoast/refresh-status` | POST – refresh registration status | Free | wordpress-seo/src/myyoast-client/user-interface/management-route.php:45, :155 |
| `yoast/v1/myyoast/register` | POST – register site | Free | wordpress-seo/src/myyoast-client/user-interface/management-route.php:46, :165 |
| `yoast/v1/myyoast/registration` | PUT – update registration | Free | wordpress-seo/src/myyoast-client/user-interface/management-route.php:47, :175 |
| `yoast/v1/myyoast/authorize` | POST – OAuth authorize | Free | wordpress-seo/src/myyoast-client/user-interface/management-route.php:48, :192 |
| `yoast/v1/setup_steps_tracking` | POST – setup steps tracking | Free | wordpress-seo/src/dashboard/user-interface/tracking/setup-steps-tracking-route.php:39, :75 |
| `yoast/v1/time_based_seo_metrics` | GET – time-based SEO metrics | Free | wordpress-seo/src/dashboard/user-interface/time-based-seo-metrics/time-based-seo-metrics-route.php:42, :127 |
| `yoast/v1/seo_scores` | GET – SEO scores | Free | wordpress-seo/src/dashboard/user-interface/scores/seo-scores-route.php:18 (base abstract-scores-route.php:124) |
| `yoast/v1/readability_scores` | GET – readability scores | Free | wordpress-seo/src/dashboard/user-interface/scores/readability-scores-route.php:18 (base abstract-scores-route.php:124) |
| `yoast/v1/site_kit_configuration_permanent_dismissal` | POST – dismiss Site Kit config | Free | wordpress-seo/src/dashboard/user-interface/configuration/site-kit-configuration-dismissal-route.php:39, :75 |
| `yoast/v1/site_kit_manage_consent` | POST – manage Site Kit consent | Free | wordpress-seo/src/dashboard/user-interface/configuration/site-kit-consent-management-route.php:37, :83 |
| `yoast/v1/ai_generator/consent` | POST – AI consent | Free | wordpress-seo/src/ai/consent/user-interface/consent-route.php:38, :90 |
| `yoast/v1/ai/free_sparks` | POST – free AI sparks | Free | wordpress-seo/src/ai/free-sparks/user-interface/free-sparks-route.php:29, :62 |
| `yoast/v1/ai_generator/refresh_callback` | POST – AI refresh callback | Free | wordpress-seo/src/ai/authorization/user-interface/refresh-callback-route.php:20, :28 |
| `yoast/v1/ai_generator/callback` | POST – AI OAuth callback | Free | wordpress-seo/src/ai/authorization/user-interface/callback-route.php:20, :28 |
| `yoast/v1/ai_generator/get_suggestions` | POST – AI title/description suggestions | Free | wordpress-seo/src/ai/generator/user-interface/get-suggestions-route.php:40, :73 |
| `yoast/v1/ai_generator/get_usage` | POST – AI usage | Free | wordpress-seo/src/ai/generator/user-interface/get-usage-route.php:43, :94 |
| `yoast/v1/ai_generator/bust_subscription_cache` | POST – bust AI subscription cache | Free | wordpress-seo/src/ai/generator/user-interface/bust-subscription-cache-route.php:35, :68 |
| `yoast/v1/ai_content_planner/get_suggestions` | GET – content planner suggestions | Free | wordpress-seo/src/ai/content-planner/user-interface/get-suggestions-route.php:41, :74 |
| `yoast/v1/ai_content_planner/banner_permanent_dismissal` | POST – dismiss content planner banner | Free | wordpress-seo/src/ai/content-planner/user-interface/banner-permanent-dismissal-route.php:39, :74 |
| `yoast/v1/ai_content_planner/get_outline` | POST – generate content outline | Free | wordpress-seo/src/ai/content-planner/user-interface/get-outline-route.php:41, :74 |
| `yoast/v1/file_size` | GET – indexable file size | Free | wordpress-seo/admin/endpoints/class-endpoint-file-size.php:25, :57, :74 |
| `yoast/v1/statistics` | GET – indexable statistics | Free | wordpress-seo/admin/endpoints/class-endpoint-statistics.php:25, :58, :62 |
| `yoast/v1/link_suggestions` | POST – internal link suggestions | Premium | wordpress-seo-premium/src/routes/link-suggestions-route.php:24, :76 |
| `yoast/v1/prominent_words/get_content` | POST – get content for prominent-words analysis | Premium | wordpress-seo-premium/src/routes/prominent-words-route.php:38, :129 |
| `yoast/v1/prominent_words/complete` | POST – complete prominent-words indexing | Premium | wordpress-seo-premium/src/routes/prominent-words-route.php:66, :139 |
| `yoast/v1/prominent_words/save` | POST – save prominent words | Premium | wordpress-seo-premium/src/routes/prominent-words-route.php:52, :174 |
| `yoast/v1/workouts/noindex` | POST – apply noindex workout | Premium | wordpress-seo-premium/src/routes/workouts-route.php:34, :172 |
| `yoast/v1/workouts/remove_redirect` | GET – remove redirect workout | Premium | wordpress-seo-premium/src/routes/workouts-route.php:41, :204 |
| `yoast/v1/workouts/link_suggestions` | GET – link-suggestion workout | Premium | wordpress-seo-premium/src/routes/workouts-route.php:48, :220 |
| `yoast/v1/workouts/last_updated` | GET – workouts last updated | Premium | wordpress-seo-premium/src/routes/workouts-route.php:76, :236 |
| `yoast/v1/workouts/cornerstone_data` | POST – cornerstone workout data | Premium | wordpress-seo-premium/src/routes/workouts-route.php:55, :246 |
| `yoast/v1/workouts/enable_cornerstone` | POST – enable cornerstone | Premium | wordpress-seo-premium/src/routes/workouts-route.php:62, :266 |
| `yoast/v1/ai/optimize` | POST – AI content optimization | Premium | wordpress-seo-premium/src/ai/optimize/optimizer/user-interface/ai-optimize-route.php:33, :66 |
| `yoast/v1/ai/summarize` | POST – AI content summary | Premium | wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-route.php:34, :67 |
| `yoast/v1/redirects` | POST – add redirect | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:14, :87 |
| `yoast/v1/redirects/delete` | POST – delete redirect | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:15, :104 |
| `yoast/v1/redirects/list` | GET – list redirects | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:16, :130 |
| `yoast/v1/redirects/update` | PUT – update redirect | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:17, :158 |
| `yoast/v1/redirects/settings` | GET/PUT – read/write redirect settings | Premium | wordpress-seo-premium/classes/premium-redirect-endpoint.php:18, :222, :238 |
| `yoast/v1/redirects/undo-for-object` | POST – delete redirect for object | Premium | wordpress-seo-premium/classes/redirect-undo-endpoint.php:14, :47 |

## Blocks and editor integrations

| Item | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `yoast-seo/breadcrumbs` | Dynamic block rendering breadcrumbs. | Free | wordpress-seo/blocks/dynamic-blocks/breadcrumbs/block.json, wordpress-seo/src/integrations/blocks/breadcrumbs-block.php |
| `yoast/faq-block` | Structured-data FAQ block. | Free | wordpress-seo/src/integrations/blocks/structured-data-blocks.php:87, wordpress-seo/blocks/structured-data-blocks/faq/block.json |
| `yoast/how-to-block` | Structured-data How-to block. | Free | wordpress-seo/src/integrations/blocks/structured-data-blocks.php:93, wordpress-seo/blocks/structured-data-blocks/how-to/block.json |
| Block categories `yoast-structured-data-blocks`, `yoast-internal-linking-blocks` | Registers Yoast block categories. | Free | wordpress-seo/src/integrations/blocks/block-categories.php:32 |
| Gutenberg block-editor assets | Enqueues Yoast block-editor styles in editor iframe. | Free | wordpress-seo/src/integrations/blocks/block-editor-integration.php:56 |
| Gutenberg sidebar plugin | Registers the Yoast SEO sidebar plugin in the block editor. | Free | wordpress-seo/js/dist/block-editor.js (registerPlugin) |
| Classic/Elementor metabox | Yoast SEO meta box in classic and Elementor editors. | Free | wordpress-seo/admin/metabox/class-metabox.php, wordpress-seo/src/integrations/third-party/elementor.php:30 |
| `yoast-seo/related-links` | Block listing related internal links. | Premium | wordpress-seo-premium/src/integrations/blocks/related-links-block.php:11 |
| `yoast-seo/estimated-reading-time` | Block showing estimated reading time. | Premium | wordpress-seo-premium/src/integrations/blocks/estimated-reading-time-block.php:12 |
| `yoast-seo/siblings` | Block listing sibling pages. | Premium | wordpress-seo-premium/classes/blocks/siblings-block.php:12 |
| `yoast-seo/subpages` | Block listing subpages. | Premium | wordpress-seo-premium/classes/blocks/subpages-block.php:12 |
| `yoast-seo/table-of-contents` | Block building a table of contents. | Premium | wordpress-seo-premium/assets/blocks/dynamic-blocks/table-of-contents/block.json |
| `yoast-seo/ai-summarize` | AI summary / key-takeaways block. | Premium | wordpress-seo-premium/src/ai/summarize/user-interface/ai-summarize-integration.php:113 |
| Premium block-editor styles | Enqueues premium block-editor styles. | Premium | wordpress-seo-premium/src/integrations/blocks/block-editor-integration.php:55 |
| Premium metabox store/editor | Loads premium metabox config and `yoast-seo-premium/editor` store data. | Premium | wordpress-seo-premium/classes/premium-metabox.php:113, :128 |
| Elementor premium editor | Embeds premium features in Elementor. | Premium | wordpress-seo-premium/src/integrations/third-party/elementor-premium.php:30 |

## Integrations

| Item | Purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| WooCommerce | WooCommerce-specific SEO/asset integration. | Free | wordpress-seo/src/integrations/third-party/woocommerce.php:20 |
| WooCommerce product permalinks | Handles product-category permalinks. | Free | wordpress-seo/src/integrations/third-party/woocommerce-permalinks.php:13 |
| Elementor | Embeds the Yoast metabox in the Elementor editor. | Free | wordpress-seo/src/integrations/third-party/elementor.php:30 |
| AMP | AMP compatibility. | Free | wordpress-seo/src/integrations/third-party/amp.php:12 |
| bbPress | bbPress compatibility. | Free | wordpress-seo/src/integrations/third-party/bbpress.php:12 |
| Jetpack | Jetpack compatibility. | Free | wordpress-seo/src/integrations/third-party/jetpack.php:13 |
| W3 Total Cache | Cache compatibility. | Free | wordpress-seo/src/integrations/third-party/w3-total-cache.php:11 |
| Web Stories | Web Stories integration. | Free | wordpress-seo/src/integrations/third-party/web-stories.php:16 |
| WPML | Ensures unmanipulated home URL with WPML. | Free | wordpress-seo/src/integrations/third-party/wpml.php:13 |
| WPML + Yoast SEO Multilingual notification | Warns if WPML installed but Yoast SEO Multilingual glue plugin missing. | Free | wordpress-seo/src/integrations/third-party/wpml-wpseo-notification.php:17 |
| Wincher | Keyword rank tracking (routes + actions). | Free | wordpress-seo/src/integrations/third-party/wincher-publish.php:18, wordpress-seo/src/routes/wincher-route.php:23 |
| Semrush | Related-keyphrase suggestions via Semrush OAuth. | Free | wordpress-seo/src/routes/semrush-route.php:23, wordpress-seo/src/config/semrush-client.php:37 |
| Google Search Console | GSC admin page/data surface. | Free | wordpress-seo/admin/google_search_console/class-gsc.php:11, wordpress-seo/src/dashboard/infrastructure/search-console/site-kit-search-console-api-call.php:20 |
| Google Site Kit (Search Console) | Pulls Search Console data via Site Kit internal REST. | Free | wordpress-seo/src/dashboard/infrastructure/search-console/site-kit-search-console-adapter.php, site-kit-search-console-api-call.php:20 |
| Google Site Kit (Analytics 4) | Pulls GA4 data via Site Kit internal REST. | Free | wordpress-seo/src/dashboard/infrastructure/analytics-4/site-kit-analytics-4-api-call.php:21 |
| Site Kit connection / consent | Site Kit connection + consent management. | Free | wordpress-seo/src/dashboard/infrastructure/connection/site-kit-is-connected-call.php, wordpress-seo/src/dashboard/infrastructure/integrations/site-kit.php |
| The Events Calendar (TEC) schema | Detects TEC events schema. | Free | wordpress-seo/src/schema/application/configuration/schema-configuration.php:102 |
| Seriously Simple Podcasting schema | Detects podcast episode schema. | Free | wordpress-seo/src/schema/application/configuration/schema-configuration.php:105 |
| WP Recipe Maker schema | Detects recipe schema. | Free | wordpress-seo/src/schema/application/configuration/schema-configuration.php:108 |
| Easy Digital Downloads | Detects EDD for schema API integrations. | Free | wordpress-seo/src/schema/application/configuration/schema-configuration.php:117 |
| ACF Content Analysis for Yoast SEO | Detects ACF analysis plugin on Integrations page. | Free | wordpress-seo/src/integrations/admin/integrations-page.php:192 |
| Algolia | Algolia search integration (premium feature). | Premium | wordpress-seo-premium/src/integrations/third-party/algolia.php:17 |
| Easy Digital Downloads (premium) | EDD product schema integration. | Premium | wordpress-seo-premium/src/integrations/third-party/edd.php:15 |
| Elementor (premium) | Premium features in Elementor editor. | Premium | wordpress-seo-premium/src/integrations/third-party/elementor-premium.php:30 |
| Mastodon | Mastodon profile/schema integration. | Premium | wordpress-seo-premium/src/integrations/third-party/mastodon.php:13 |
| Wincher keyphrases (premium) | Adds related keyphrases to Wincher. | Premium | wordpress-seo-premium/src/integrations/third-party/wincher-keyphrases.php:14 |
| TranslationsPress | Download Premium translations. | Premium | wordpress-seo-premium/src/integrations/third-party/translationspress.php:14 |
| WooCommerce HPOS | Declares HPOS compatibility. | Premium | wordpress-seo-premium/src/initializers/woocommerce.php:12 |

## External HTTP calls

| Host / endpoint | Trigger / purpose | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `https://ai.yoa.st/api/v1` | AI generator / content planner / free sparks / AI optimize / AI summarize; POST/GET/DELETE actions. | Free (host shared with Premium AI features) | wordpress-seo/src/ai/http-request/infrastructure/api-client.php:24, :58, :61, :64 |
| `https://my.yoast.com/api/` | Fetch current site subscriptions (`sites/current`). | Free | wordpress-seo/inc/class-my-yoast-api-request.php:57, :110; caller wordpress-seo/inc/class-addon-manager.php:726 |
| `https://my.yoast.com` (OIDC issuer) | OAuth/OIDC discovery + token calls for MyYoast client. | Free | wordpress-seo/src/myyoast-client/infrastructure/oidc/issuer-config.php:33 |
| `https://my.yoast.com/api/downloads/file/analysis-worker?plugin_version=…` | Fetch the analysis worker script (MyYoast proxy). | Free | wordpress-seo/admin/class-my-yoast-proxy.php:150 |
| `https://my.yoast.com` (proxy GET) | Proxy GET request to MyYoast (admin feature). | Free | wordpress-seo/admin/class-my-yoast-proxy.php:120 |
| `https://tracking.yoast.com/stats` | Anonymous usage tracking POST. | Free | wordpress-seo/admin/class-admin.php:92, wordpress-seo/admin/tracking/class-tracking.php:132 |
| `https://auth.wincher.com` | Wincher OAuth authorize/token/redirect. | Free | wordpress-seo/src/config/wincher-client.php:49, :50, :51 |
| `https://api.wincher.com` | Wincher account/keyphrase API (`/beta/…`, `/v1/yoast/upgrade-campaign`). | Free | wordpress-seo/src/actions/wincher/wincher-account-action.php:14, :15, wordpress-seo/src/actions/wincher/wincher-keyphrases-action.php:22, :29, :36 |
| `https://oauth.semrush.com` | Semrush OAuth authorize/token/resource + phrase search API. | Free | wordpress-seo/src/config/semrush-client.php:37, :38, :39, :40, wordpress-seo/src/actions/semrush/semrush-phrases-action.php:23 |
| `https://api.indexnow.org/indexnow` | Ping IndexNow on publish/update/delete (production + option enabled). | Premium | wordpress-seo-premium/src/integrations/index-now-ping.php:59, :67 |
| `https://packages.translationspress.com/yoast/wordpress-seo-premium/packages.json` | Fetch Premium translation packages. | Premium | wordpress-seo-premium/src/integrations/third-party/translationspress.php:60, :194 |
| `https://downloads.wordpress.org/plugin/wordpress-seo.zip` / `…wordpress-seo.<ver>.zip` | Addon installer downloads/upgrades Yoast SEO free when missing/outdated (HEAD check + install). | Premium | wordpress-seo-premium/src/addon-installer.php:52, :368, :371 |
| `http://my.yoast.com/edd-sl-api` | Legacy EDD licence check endpoint (`Yoast_Product`). | Premium | wordpress-seo-premium/classes/product-premium.php:27, :46 |
| Arbitrary media URL (from imported content) | `wp_safe_remote_get` to read remote media headers during WXR/extension import. | Premium | wordpress-seo-premium/src/integrations/admin/extension-importer/media-manager.php:314 |
| Arbitrary redirect target URL | `wp_remote_head` to validate a redirect target returns HTTP 200. | Premium | wordpress-seo-premium/classes/redirect/validation/redirect-accessible-validation.php:75, :87 |
| Own sitemap (`sitemap_index.xml`) | Self-request to prime sitemap cache (self-referential, excluded). | Free | wordpress-seo/inc/sitemaps/class-sitemaps.php:491 |

## Separate paid addons not in these directories

| Addon | Purpose | Free or Premium (referencing code) | Evidence (file:line) |
|---|---|---|---|
| Yoast WooCommerce SEO (`wpseo-woocommerce/wpseo-woocommerce.php`, slug `yoast-seo-woocommerce`) | WooCommerce-specific SEO addon. | Free (addon registry) | wordpress-seo/inc/class-addon-manager.php:64, :82; wordpress-seo/admin/class-plugin-availability.php:99; wordpress-seo/src/integrations/watchers/addon-update-watcher.php:31 |
| Yoast Video SEO (`wpseo-video/video-seo.php`, slug `yoast-seo-video`) | Video SEO addon. | Free (addon registry) | wordpress-seo/inc/class-addon-manager.php:57, :81; wordpress-seo/admin/class-plugin-availability.php:59; wordpress-seo/src/integrations/watchers/addon-update-watcher.php:29 |
| Yoast News SEO (`wpseo-news/wpseo-news.php`, slug `yoast-seo-news`) | News SEO addon. | Free (addon registry) | wordpress-seo/inc/class-addon-manager.php:50, :80; wordpress-seo/admin/class-plugin-availability.php:69; wordpress-seo/src/integrations/watchers/addon-update-watcher.php:32 |
| Yoast Local SEO (`wordpress-seo-local/local-seo.php`, `wpseo-local/local-seo.php`, slug `yoast-seo-local`) | Local SEO addon. | Free (addon registry) | wordpress-seo/inc/class-addon-manager.php:71; wordpress-seo/admin/class-plugin-availability.php:79; wordpress-seo/src/integrations/watchers/addon-update-watcher.php:30 |
| Yoast SEO Multilingual (glue plugin for WPML) | Makes Yoast SEO and WPML work together; referenced by a dashboard notification. | Free (detection) | wordpress-seo/src/integrations/third-party/wpml-wpseo-notification.php:13 |
| ACF Content Analysis for Yoast SEO (`acf-content-analysis-for-yoast-seo`) | Adds ACF field analysis to Yoast SEO; listed as an add-on. | Free (addon registry) | wordpress-seo/src/integrations/watchers/addon-update-watcher.php:33; wordpress-seo/src/integrations/admin/integrations-page.php:192 |
| Addon installation UI | Installs/activates owned add-ons via MyYoast. | Free | wordpress-seo/src/integrations/admin/addon-installation/installation-integration.php:24, wordpress-seo/src/actions/addon-installation/addon-install-action.php |
| Addon auto-update watcher | Keeps Yoast add-ons auto-update state in sync with free. | Free | wordpress-seo/src/integrations/watchers/addon-update-watcher.php:23 |
| Addon update watcher (Premium) | Syncs auto-update toggles for add-ons. | Free | wordpress-seo/src/integrations/watchers/addon-update-watcher.php:37 |
