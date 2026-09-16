# Yoast SEO — custom tables, schema, sitemaps, analysis assessments (code-verified, Free + Premium)

Scope (the two plugins present on disk):
- Free: `wp-content/plugins/wordpress-seo/` — **v28.4** (`wordpress-seo/wp-seo.php:11`, `define( 'WPSEO_VERSION', '28.4' )` in `wordpress-seo/wp-seo-main.php:18`)
- Premium: `wp-content/plugins/wordpress-seo-premium/` — **v27.8** (`wordpress-seo-premium/wp-seo-premium.php:13`, `define( 'WPSEO_PREMIUM_VERSION', '27.8' )` in `wordpress-seo-premium/wp-seo-premium.php:60`)

All paths are relative to `wp-content/plugins/`. Every row cites `file:line` read from the on-disk source. Anything not verifiable is omitted.

---

## Custom database tables

All custom tables are created through the migrations library (`create_table()` → `Table` → `CREATE TABLE`), not `dbDelta`. The naming convention is `$wpdb->prefix . 'yoast_' . strtolower($name)` (`wordpress-seo/lib/model.php:136-144`). Migration classes are discovered from the `register_migration()` calls in the generated container.

| Item | Purpose or type | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `wp_yoast_indexable` | Central indexable store (one row per post / term / author / archive / system page). Notable columns: `permalink`, `permalink_hash`, `object_id`, `object_type`, `object_sub_type`, `author_id`, `post_parent`, `title`, `description`, `breadcrumb_title`, `post_status`, `is_public`, `is_protected`, `canonical`, `primary_focus_keyword`, `primary_focus_keyword_score`, `readability_score`, `is_cornerstone`, `is_robots_noindex/nofollow/noarchive/noimageindex/nosnippet`, `twitter_*`, `open_graph_*`, `link_count`, `incoming_link_count`, `prominent_words_version`, `number_of_pages`, `has_ancestors`, `language`, `region`, `schema_page_type`, `schema_article_type`, `inclusive_language_score`, `version`, `object_last_modified`, `object_published_at`, `estimated_reading_time_minutes`, `seo_title_score`, `meta_description_score`, `created_at`/`updated_at` | Free | created `wordpress-seo/src/config/migrations/20171228151840_WpYoastIndexable.php:43` (table name `:361-362`); later columns `wordpress-seo/src/config/migrations/20200420073606_AddColumnsToIndexables.php:51-54`, `wordpress-seo/src/config/migrations/20200609154515_AddHasAncestorsColumn.php:27`, `wordpress-seo/src/config/migrations/20201202144329_AddEstimatedReadingTime.php:28`, `wordpress-seo/src/config/migrations/20210817092415_AddVersionColumnToIndexables.php:26`, `wordpress-seo/src/config/migrations/20211020091404_AddObjectTimestamps.php:26-44`, `wordpress-seo/src/config/migrations/20230417083836_AddInclusiveLanguageScore.php:28`, `wordpress-seo/src/config/migrations/20260709144332_AddSeoTitleAndMetaDescriptionScores.php:28-38` |
| `wp_yoast_primary_term` | Primary taxonomy term chosen per post (`post_id`, `term_id`, `taxonomy`; indexes `post_taxonomy`, `post_term`) | Free | created `wordpress-seo/src/config/migrations/20171228151841_WpYoastPrimaryTerm.php:25` (name `:99-100`) |
| `wp_yoast_indexable_hierarchy` | Ancestor/depth graph between indexables (`indexable_id`, `ancestor_id`, `depth`; composite primary key, indexes on each column) | Free | created `wordpress-seo/src/config/migrations/20191011111109_WpYoastIndexableHierarchy.php:25` (name `:80-81`) |
| `wp_yoast_seo_links` | Internal/external link index (`id`, `url`, `post_id`, `target_post_id`, `type`, `indexable_id`, `target_indexable_id`, `height`, `width`, `size`, `language`, `region`; indexes `link_direction`, `indexable_link_direction`) | Free | created `wordpress-seo/src/config/migrations/20200617122511_CreateSEOLinksTable.php:25` (name `:93-94`) |
| `wp_yoast_migrations` | Applied-migration version tracking (`version` unique index); schema version table | Free | name/creation `wordpress-seo/lib/migrations/adapter.php:122` and `:132-136` |
| `wp_yoast_expiring_store` | Network-wide (uses `$wpdb->base_prefix`) key/value store with expiry (`key_name` PK, `value`, `exp`, index `exp_index`) | Free | created `wordpress-seo/src/config/migrations/20260325155530_CreateExpiringStoreTable.php:26` (name `:69-72`) |
| `wp_yoast_prominent_words` | Prominent words per indexable for internal linking (`stem` limit 191, `indexable_id`, `weight` float; indexes `stem`, `indexable_id`, later `indexable_id_and_stem`) | Premium | created `wordpress-seo-premium/src/config/migrations/20190715101200_WpYoastPremiumImprovedInternalLinking.php:25` (name `:94-95`); extra index `wordpress-seo-premium/src/config/migrations/20210827093024_AddIndexOnIndexableIdAndStem.php:35-50` |
| `wp_yoast_indexable_meta` (legacy) | Obsolete indexable-meta table; explicitly dropped via `DROP TABLE IF EXISTS` | Free | `wordpress-seo/src/config/migrations/20190529075038_WpYoastDropIndexableMetaTableIfExists.php:25` |
| Free migration registry | The migrations above are registered against plugin `free` (incl. `CreateExpiringStoreTable`, `CreateSEOLinksTable`, `WpYoastIndexable`, `WpYoastIndexableHierarchy`, `WpYoastPrimaryTerm`) | Free | `wordpress-seo/src/generated/container.php:5946-5971` |
| Premium migration registry | Premium migrations registered against plugin `premium` | Premium | `wordpress-seo-premium/src/generated/container.php:751-752` |

---

## Schema pieces and types

Free graph pieces live in `wordpress-seo/src/generators/schema/`. Premium adds **no** schema-piece classes; it only filters the free pieces (see integration rows). The graph is assembled by `Schema_Generator`.

### Schema piece classes and graph assembly

| Item | Purpose or type | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `Schema\Article` | Article node; `@type` = dynamic article type | Free | class `wordpress-seo/src/generators/schema/article.php:11`; `@type` `:44`; `CommentAction` `:183` |
| `Schema\WebPage` | WebPage node; `@type` = dynamic page type; adds `ReadAction` | Free | class `wordpress-seo/src/generators/schema/webpage.php:11`; `@type` `:32`; `ReadAction` `:151` |
| `Schema\Main_Image` | Primary image node; emits `ImageObject` via image helper | Free | class `wordpress-seo/src/generators/schema/main-image.php:10`; `ImageObject` `wordpress-seo/src/helpers/schema/image-helper.php:179` |
| `Schema\Breadcrumb` | `BreadcrumbList` + `ListItem` nodes | Free | class `wordpress-seo/src/generators/schema/breadcrumb.php:10`; `BreadcrumbList` `:98`; `ListItem` `:114` |
| `Schema\Website` | `WebSite` node plus `SearchAction` / `EntryPoint` / `PropertyValueSpecification` | Free | class `wordpress-seo/src/generators/schema/website.php:10`; `WebSite` `:28`; `SearchAction` `:88`; `EntryPoint` `:90`; `PropertyValueSpecification` `:94` |
| `Schema\Organization` | `Organization` node | Free | class `wordpress-seo/src/generators/schema/organization.php:10`; `@type` `:37` |
| `Schema\Person` | `Person`/`Organization` node (type property `[ 'Person', 'Organization' ]`) | Free | class `wordpress-seo/src/generators/schema/person.php:11`; type property `:27`; `@type` `:136` |
| `Schema\Author` | Author `Person` node (extends `Person`) | Free | class `wordpress-seo/src/generators/schema/author.php:8`; `@type` `:45` |
| `Schema\FAQ` | `Question` + `Answer` nodes; injects `FAQPage` into the page type | Free | class `wordpress-seo/src/generators/schema/faq.php:8`; `Question` `:86`; `Answer` `:107`; `FAQPage` `:14` |
| `Schema\HowTo` | `HowTo` / `HowToStep` / `HowToDirection` from HowTo block | Free | class `wordpress-seo/src/generators/schema/howto.php:10`; `HowTo` `:178`; `HowToStep` `:70`; `HowToDirection` `:135` |
| Graph assembly | `generate()` returns `{ '@context': 'https://schema.org', '@graph': [...] }` | Free | `wordpress-seo/src/generators/schema-generator.php:48` |
| Piece selection per page | `get_graph_pieces()` returns the default list (`Article`, `WebPage`, `Main_Image`, `Breadcrumb`, `Website`, `Organization`, `Person`, `Author`, `FAQ`, `HowTo`); password-protected posts get only `WebPage` + `Website` + `Organization` | Free | `wordpress-seo/src/generators/schema-generator.php:291-314`; password branch `:292-300` |
| `is_needed()` filtering | `filter_graph_pieces_to_generate()` calls each piece's `is_needed()`, filterable via `wpseo_schema_needs_<identifier>` | Free | `wordpress-seo/src/generators/schema-generator.php:90-112` |
| Piece registration hook | `wpseo_schema_graph_pieces` filter lets addons add pieces | Free | `wordpress-seo/src/generators/schema-generator.php:322` |
| Graph output | `Schema_Presenter::present()` prints `<script type="application/ld+json" class="yoast-schema-graph">` | Free | `wordpress-seo/src/presenters/schema-presenter.php:49`; source graph `wordpress-seo/src/presentations/indexable-presentation.php:719` |
| Forced `WebPage` for protected posts | `protected_webpage_schema()` overrides `@type` to `WebPage` | Free | `wordpress-seo/src/generators/schema-generator.php:279` |

### `@type` values the plugin can emit

| Item | Purpose or type | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Page-type catalog: `WebPage`, `ItemPage`, `AboutPage`, `FAQPage`, `QAPage`, `ProfilePage`, `ContactPage`, `MedicalWebPage`, `CollectionPage`, `CheckoutPage`, `RealEstateListing`, `SearchResultsPage` | allowed schema page types | Free | `wordpress-seo/src/config/schema-types.php:17-30` |
| Article-type catalog: `Article`, `BlogPosting`, `SocialMediaPosting`, `NewsArticle`, `AdvertiserContentArticle`, `SatiricalArticle`, `ScholarlyArticle`, `TechArticle`, `Report`, `None` | allowed schema article types | Free | `wordpress-seo/src/config/schema-types.php:39-50` |
| `WebPage`, `CollectionPage`, `SearchResultsPage`, `ProfilePage` | page type chosen per object type (system `search-result` → `CollectionPage`+`SearchResultsPage`; `user` → `ProfilePage`; `home-page`/`date-archive`/`term`/`post-type-archive` → `CollectionPage`; else `WebPage`+additional type) | Free | `wordpress-seo/src/context/meta-tags-context.php:504-537` |
| Article type (dynamic) | `schema_article_type` resolved from indexable/options; must be in `ARTICLE_TYPES`; `None` suppresses the Article node | Free | `wordpress-seo/src/context/meta-tags-context.php:552-589`; `Article::is_needed()` `wordpress-seo/src/generators/schema/article.php:18-24` |
| `FAQPage` | added to page type when FAQ piece is needed | Free | `wordpress-seo/src/generators/schema/faq.php:14` |
| `WebSite` | emitted by Website piece | Free | `wordpress-seo/src/generators/schema/website.php:28` |
| `SearchAction`, `EntryPoint`, `PropertyValueSpecification` | site search markup | Free | `wordpress-seo/src/generators/schema/website.php:88-94` |
| `Organization` | emitted by Organization piece, and by Person when site represents a person | Free | `wordpress-seo/src/generators/schema/organization.php:37`; `wordpress-seo/src/generators/schema/person.php:27` |
| `Person` | emitted by Author piece and by Person piece | Free | `wordpress-seo/src/generators/schema/author.php:45`; `wordpress-seo/src/generators/schema/person.php:136` |
| `BreadcrumbList`, `ListItem` | breadcrumb trail | Free | `wordpress-seo/src/generators/schema/breadcrumb.php:98`, `:114` |
| `Question`, `Answer` | FAQ block | Free | `wordpress-seo/src/generators/schema/faq.php:86`, `:107` |
| `HowTo`, `HowToStep`, `HowToDirection` | HowTo block | Free | `wordpress-seo/src/generators/schema/howto.php:178`, `:70`, `:135` |
| `ImageObject` | primary image | Free | `wordpress-seo/src/helpers/schema/image-helper.php:179` |
| `ReadAction` | WebPage interaction | Free | `wordpress-seo/src/generators/schema/webpage.php:151` |
| `CommentAction` | Article comment interaction | Free | `wordpress-seo/src/generators/schema/article.php:183` |
| `QuantitativeValue` | `numberOfEmployees` value on Organization | Premium | `wordpress-seo-premium/src/integrations/organization-schema-integration.php:105` (mapping `:17-30`, hook `:65`) |
| `Organization`, `Brand` | product seller node added by EDD integration | Premium | `wordpress-seo-premium/src/integrations/third-party/edd.php:172` (hook `:62`) |
| Organization detail properties (`description`, `email`, `telephone`, `legalName`, `foundingDate`, `vatID`, `taxID`, `iso6523Code`, `duns`, `leiCode`, `naics`) | merged into the free `Organization` node (no new `@type`) | Premium | `wordpress-seo-premium/src/integrations/organization-schema-integration.php:65-85` |
| Publishing/ownership/funding policy properties (`publishingPrinciples`, `ownershipFundingInfo`, `actionableFeedbackPolicy`, `correctionsPolicy`, `ethicsPolicy`, `diversityPolicy`, `diversityStaffingReport`) | merged into the free `Organization` node (no new `@type`) | Premium | `wordpress-seo-premium/src/integrations/publishing-principles-schema-integration.php:22-30`, `:97` |
| User schema meta merged into `Person` (`wpseo_user_schema`) | filter only, no new `@type` | Premium | `wordpress-seo-premium/src/integrations/user-profile-integration.php:21`, `:34-38` |
| Mastodon social profile on `Person` | filter only, no new `@type` | Premium | `wordpress-seo-premium/src/integrations/third-party/mastodon.php:53` |

---

## Sitemaps

All sitemap code is procedural in `wordpress-seo/inc/sitemaps/`. Three core providers are registered in the free plugin; additional providers can be injected by addons through a filter. There are **no** news or video sitemap providers anywhere in these two plugins (only `WPSEO_Post_Type_Sitemap_Provider`, `WPSEO_Taxonomy_Sitemap_Provider`, `WPSEO_Author_Sitemap_Provider` are registered at `wordpress-seo/inc/sitemaps/class-sitemaps.php:123-127`, and no `news`/`video` sitemap class exists in either plugin).

| Item | Purpose or type | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `WPSEO_Post_Type_Sitemap_Provider` | Post-type sitemaps (one type per public post type); `handles_type()` | Free | class `wordpress-seo/inc/sitemaps/class-post-type-sitemap-provider.php:13`; `handles_type` `:83`; `get_index_links` `:95`; `get_sitemap_links` `:157` |
| `WPSEO_Taxonomy_Sitemap_Provider` | Taxonomy/term sitemaps; `handles_type()` | Free | class `wordpress-seo/inc/sitemaps/class-taxonomy-sitemap-provider.php:11`; `handles_type` `:46`; `get_index_links` `:64`; `get_sitemap_links` `:181` |
| `WPSEO_Author_Sitemap_Provider` | Author-archive sitemap (`author`); `handles_type()` | Free | class `wordpress-seo/inc/sitemaps/class-author-sitemap-provider.php:11`; `handles_type` `:20`; `get_index_links` `:36`; `get_sitemap_links` `:147` |
| Provider registry | Core providers instantiated in `init_sitemaps_providers()` | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:121-127` |
| Addon provider injection | `wpseo_sitemaps_providers` filter; objects implementing `WPSEO_Sitemap_Provider` are appended | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:129-135` |
| `WPSEO_Sitemap_Provider` interface | Contract: `handles_type()`, `get_index_links()`, `get_sitemap_links()` | Free | `wordpress-seo/inc/sitemaps/interface-sitemap-provider.php:11`, `:20`, `:29`, `:40` |
| `WPSEO_Sitemaps::register_sitemap()` | Addon registration by type name; hooks `wpseo_do_sitemap_<name>` and optional rewrite | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:163-168` |
| `WPSEO_Sitemaps::register_xsl()` | Addon XSL registration; hooks `wpseo_xsl_<name>` | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:181-187` |
| Sitemap index (`sitemap_index.xml`) | `build_root_map()` builds the index from each provider's `get_index_links()`; `wpseo_sitemap_index_links` filter | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:408-422`; index type constant `:20` |
| Index rewrite rule | `sitemap_index\.xml$` → `index.php?sitemap=1` | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-router.php:38-41` |
| XSL rewrite rule | `([a-z]+)?-?sitemap\.xsl$` → `index.php?yoast-sitemap-xsl=$matches[1]` | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-router.php:41` |
| `sitemap.xml` → `sitemap_index.xml` redirect | `template_redirect()` 301-redirects the bare `sitemap.xml` | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-router.php:92-97` |
| XSL stylesheet file (`main-sitemap.xsl`) | Served for the sitemap XML; readfile / URL resolution | Free | `wordpress-seo/css/main-sitemap.xsl:1`; `wordpress-seo/inc/sitemaps/class-sitemaps.php:467`; `wordpress-seo/inc/sitemaps/class-sitemaps-renderer.php:340-353` |
| XSL output dispatcher | `xsl_output()` fires `wpseo_xsl_<type>` then outputs the file | Free | `wordpress-seo/inc/sitemaps/class-sitemaps.php:443-467` |
| Index renderer | `get_index()` outputs `<sitemapindex …>` with `<sitemap>` entries | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-renderer.php:68-82`; `sitemap_index_url()` `:190` |
| URL-set renderer | `get_sitemap()` outputs `<urlset …>` (incl. image namespace); `wpseo_sitemap_urlset` / `wpseo_sitemap_<type>_urlset` filters | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-renderer.php:96-132` |
| Image parser | `WPSEO_Sitemap_Image_Parser` (featured/content/gallery images) | Free | `wordpress-seo/inc/sitemaps/class-sitemap-image-parser.php:11` |
| Sitemap cache | `WPSEO_Sitemaps_Cache` (per-type/page caching) | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-cache.php:13` |
| Cache validator | `WPSEO_Sitemaps_Cache_Validator` (invalidates cache on content change) | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-cache-validator.php:13` |
| Sitemap admin | `WPSEO_Sitemaps_Admin` (status transitions, `ping_search_engines()`) | Free | `wordpress-seo/inc/sitemaps/class-sitemaps-admin.php:11`, `:66` |
| News / video providers | none present in free or premium | n/a | only the three providers are registered at `wordpress-seo/inc/sitemaps/class-sitemaps.php:123-127` |
| Premium sitemap code | no premium sitemap provider; only a redirect-content filter | Premium | `wordpress-seo-premium/classes/redirect/redirect-sitemap-filter.php:1` (redirect feature, not a sitemap provider) |

---

## Analysis assessments

Core assessments ship inside the shared `yoastseo` bundle loaded by both plugins: `wordpress-seo/js/dist/externals/analysis.js`. The full catalog of assessment classes is exported on line 85 of that bundle as `assessments.readability`, `assessments.seo`, and `assessments.inclusiveLanguage` (verified by extracting the object literals). Premium re-registers four of them (plus a research) at runtime from `register-premium-assessments-2780.min.js`.

Registration / catalog evidence for all Free-class assessments below: `wordpress-seo/js/dist/externals/analysis.js:85`. Assessment identifier strings (`this.identifier="…"`) are also in that bundle (same file, scattered lines).

### SEO assessments (Free bundle catalog)

| Assessment (class) | Identifier | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `FunctionWordsInKeyphraseAssessment` | `functionWordsInKeyphrase` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ImageAltTagsAssessment` | `imageAltTags` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ImageCountAssessment` | `images` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ImageKeyphraseAssessment` | `imageKeyphrase` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `InternalLinksAssessment` | `internalLinks` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `IntroductionKeywordAssessment` | `introductionKeyword` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `KeyphraseDensityAssessment` | `keyphraseDensity` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `KeyphraseDistributionAssessment` | `keyphraseDistribution` | Premium-gated (re-registered by Premium with premium scores/research) | class catalog `wordpress-seo/js/dist/externals/analysis.js:85`; registration `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23` |
| `KeyphraseInSEOTitleAssessment` | `keyphraseInSEOTitle` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `KeyphraseLengthAssessment` | `keyphraseLength` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `KeywordDensityAssessment` | `keywordDensity` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `MetaDescriptionKeywordAssessment` | `metaDescriptionKeyword` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `MetaDescriptionLengthAssessment` | `metaDescriptionLength` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `OutboundLinksAssessment` | `externalLinks` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `PageTitleWidthAssessment` | `titleWidth` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ProductIdentifiersAssessment` | `productIdentifier` | Free (WooCommerce) | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ProductSKUAssessment` | `productSKU` | Free (WooCommerce) | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `SingleH1Assessment` | `singleH1` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `SubheadingsKeywordAssessment` | `subheadingsKeyword` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `TextCompetingLinksAssessment` | `textCompetingLinks` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `TextLengthAssessment` | `textLength` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `TextTitleAssessment` | `textTitleAssessment` | Premium-gated (activated only when Premium sets the flag) | class catalog `wordpress-seo/js/dist/externals/analysis.js:85`; registration `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23`; flag `wordpress-seo-premium/classes/premium-metabox.php:218` |
| `SlugKeywordAssessment` | `slugKeyword` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `UrlKeywordAssessment` | `urlKeyword` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |

### Readability assessments (Free bundle catalog)

| Assessment (class) | Identifier | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| `ListAssessment` | `listsPresence` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `ParagraphTooLongAssessment` | `textParagraphTooLong` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `PassiveVoiceAssessment` | `passiveVoice` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `SentenceBeginningsAssessment` | `sentenceBeginnings` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `SentenceLengthInTextAssessment` | `textSentenceLength` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `SubheadingDistributionTooLongAssessment` | `subheadingsTooLong` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `TextAlignmentAssessment` | `textAlignment` | Premium-gated (registered for readability + cornerstoneReadability) | class catalog `wordpress-seo/js/dist/externals/analysis.js:85`; registration `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23`; flag `wordpress-seo-premium/classes/premium-metabox.php:258` |
| `TextPresenceAssessment` | `textPresence` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `TransitionWordsAssessment` | `textTransitionWords` | Free | `wordpress-seo/js/dist/externals/analysis.js:85` |
| `WordComplexityAssessment` | `wordComplexity` | Premium-gated (registered for readability + cornerstoneReadability) | class catalog `wordpress-seo/js/dist/externals/analysis.js:85`; registration `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23` |

### Premium registration and inclusive language

| Item | Purpose or type | Free or Premium | Evidence (file:line) |
|---|---|---|---|
| Premium assessment registration bundle | `registerAssessment()` calls for `TextTitleAssessment` (seo), `keyphraseDistributionAssessment` (seo), `wordComplexity` (readability + cornerstoneReadability), `textAlignment` (readability + cornerstoneReadability); also registers researches `keyphraseDistribution`, `wordComplexity`, `getLongCenterAlignedTexts` and helpers/researcher config | Premium | `wordpress-seo-premium/assets/js/dist/register-premium-assessments-2780.min.js:23` |
| Premium title-assessment flag | `isTitleAssessmentAvailable` passed in editor config | Premium | `wordpress-seo-premium/classes/premium-metabox.php:218`, `:258` |
| Text formality research | `analysisWorker.registerResearch("textFormality", …)` (used by inclusive language) | Premium | `wordpress-seo-premium/assets/js/dist/register-text-formality-2780.min.js:1` |
| `InclusiveLanguageAssessment` | inclusive language assessment class (`assessments.inclusiveLanguage`) | Free bundle (premium/original feature; Premium overrides the loading path) | class catalog `wordpress-seo/js/dist/externals/analysis.js:85` |
| Inclusive language feature class | `Inclusive_Language_Analysis` (`inclusiveLanguageAnalysis`); enabled via `inclusive_language_analysis_active` option + user meta + language support; loaded from Free when Premium inactive | Free | `wordpress-seo/src/editors/framework/inclusive-language-analysis.php:61-95`; option default `wordpress-seo/inc/options/class-wpseo-option-wpseo.php:56` |
| Inclusive language Premium conditional | `Inclusive_Language_Enabled_Conditional` gating Premium inclusive-language column/filter integrations | Premium | `wordpress-seo-premium/src/conditionals/inclusive-language-enabled-conditional.php:23-25` |
| Used-keywords assessment | `UsedKeywordsAssessment` registered via `registerAssessment("usedKeywords", …)` (related-keyphrase/bulk editor) | Free | `wordpress-seo/js/dist/externals/analysis.js:85`; `wordpress-seo/js/dist/used-keywords-assessment.js:1` |
