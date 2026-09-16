# RankKernel Feature Parity Master

The single master reference for what Rank Math (free and PRO) and Yoast SEO (free and Premium) ship, and exactly where RankKernel stands against every one of them.

## 1. Purpose, method, and audited versions

This document is the authoritative feature-parity reference for RankKernel. It catalogs the complete feature surface of Rank Math (free and PRO) and Yoast SEO (free and Premium), then compares each catalogued feature against the code-verified state of RankKernel so that no competitor feature is missed in planning or implementation.

How it was produced. Raw research was completed first and stored in `docs/research/raw/`: official vendor documentation for both plugins, plus a full source-code pass over the on-disk copies of Rank Math free, Rank Math PRO, Yoast SEO free, and Yoast SEO Premium. This document consolidates that research with the prior analysis in `docs/competitor-analysis/` (feature matrix and gap analysis) and the RankKernel planning docs (`ROADMAP.md`, `feature-gap-research-gate.md`). It adds one deduplicated master parity matrix and a tiered gap register. Nothing here is copied from competitor code.

Audited versions:

| Product | Version | State |
|---|---|---|
| Rank Math free | 1.0.278 | installed, not activated |
| Rank Math PRO | 3.0.109 | installed, not activated |
| Yoast SEO free | 28.4 | installed, not activated |
| Yoast SEO Premium | 27.8 | installed, not activated |
| RankKernel | 0.1.0 | main commit 606fc95 |

Both competitor plugins were installed but never activated. Every competitor finding in this document is therefore code-derived, supplemented (for the vendor-page-only pass) by official vendor pages. RankKernel findings are code-derived from `docs/research/raw/rankkernel-current.md` and reflect what ships today, not aspiration.

Verdict vocabulary used in the RankKernel column throughout:

| Verdict | Meaning |
|---|---|
| DONE | implemented and shipping today |
| PARTIAL | partially implemented, the missing part is stated |
| PLANNED | written into the roadmap or planning docs but not built |
| MISSING | not implemented and not written down anywhere |
| EXTERNAL | needs a paid third-party API or data service, so it cannot be offered free; the service is always named |
| N/A | does not meaningfully apply to a free self-hosted plugin; the reason is always stated |

Accuracy rules applied throughout: the RankKernel column reflects code-verified reality, never aspiration. In particular, 13 module ids are declared in the registry but only 6 have classes and directories; `importer`, `instant-indexing`, `robots`, `image-seo`, `gutenberg`, `ai` and `headless` are registry placeholders with no code. Per-post SEO title, description, robots and Open Graph editing is not built (only the schema metabox exists). The token grammar is flat `%%lowercase%%` only. No performance benchmark is invented and no percentage or marketing claim appears anywhere.

## 2. Executive summary

RankKernel today is a lean technical SEO engine with a genuinely implemented core and a large open surface everywhere else. What ships and works: a single-pass metadata head (title, meta description, robots directives, canonical, Open Graph, Twitter cards, webmaster verification), XML sitemaps with object-cache caching on by default, a schema `@graph` with 26 selectable types across 29 pieces plus custom JSON, breadcrumbs with a block, tags and a shortcode, a full redirect manager (default off) with six match modes, five status codes, CSV import/export and a slug watcher, and a 404 monitor (default off) with deduplication, dual pruning, a flood guard and one-click redirect creation.

What is not there is the majority of what both competitors give away free. RankKernel declares 13 module ids in its registry but only 6 have classes: `importer`, `instant-indexing`, `robots`, `image-seo`, `gutenberg`, `ai` and `headless` are placeholders with no code. There is no per-post SEO title, description, robots or Open Graph editing UI (only a schema metabox), no content analysis or readability scoring, no internal linking, no llms.txt or AI crawler controls, no image SEO, no IndexNow, no head cleanup, no HTML sitemap, no WooCommerce or Local SEO, no importer, no settings export/import, and no admin columns, quick edit, bulk edit or setup wizard. The token grammar is flat `%%lowercase%%` with nine built-in tokens, so it has none of the parameterised tokens both competitors rely on.

The strategic picture: the hardest and most valuable technical pieces (sitemaps, schema, redirects, 404, breadcrumbs, metadata output) are done, and the gaps are concentrated in three clusters, the on-page content and analysis layer, the per-context metadata and crawl-control layer, and the vertical and ecosystem layer. Most of the remaining work is deterministic, local and free to build. A defined subset (Content AI, rank tracking quota, AI visibility, competitor analyzer API, and map or store-locator features backed by a billing-enabled key) genuinely cannot be offered free because it depends on a paid third-party service, and is recorded as EXTERNAL rather than quietly promised.

Counts across the master parity matrix (section 6):

| Measure | Count |
|---|---|
| Total competitor features catalogued | 379 |
| DONE | 78 |
| PARTIAL | 19 |
| PLANNED | 82 |
| MISSING | 163 |
| EXTERNAL | 14 |
| N/A | 23 |

Read the matrix as the source of truth for the numbers; this table is recomputed from it.

## 3. Rank Math complete feature inventory

Tier column: FREE = available in the free plugin; PRO = gated to paid Rank Math PRO/Business/Agency. Rows marked (code) are corroborated in the on-disk source by `rankmath-free-code.md` or `rankmath-pro-code.md`; the rest come from the official vendor-page pass in `rankmath-web.md`.

### 3.1 Content and on-page analysis

| Feature | What it does | Tier |
|---|---|---|
| Focus Keyword (primary) | Set the primary keyword to optimize a post against (code: `rank_math_focus_keyword`) | FREE |
| Multiple / secondary focus keywords | Add secondary keywords, tests run per keyword (free ships a comma list, PRO raises the max tags to 100) | FREE |
| Optimize unlimited keywords | No cap on keywords per post | FREE |
| Content analysis engine | Analyses content against keyword placement, length and density | FREE |
| SEO Analysis Tool (40 factors) | Site and content audit across roughly 40 SEO factors | FREE |
| 30+ detailed SEO tests | Itemised on-page tests per post | FREE |
| SEO Analysis Score | Numeric optimisation score | FREE |
| SEO warnings / failed tests | Distinguishes warnings from failed tests | FREE |
| Use Product Schema test | On-page test prompting Product schema | PRO |
| Allow customers to leave reviews test | Content test for Product review schema | PRO |
| Competitor SEO Analysis | Analyse a competitor's on-page SEO via the RankMath.com analyzer API | PRO (code) |
| Side-by-Side SEO Comparison | Compare your page against a competitor side by side | PRO |
| Search Intent Analysis | Determines the search intent of the focus keyword via Content AI | PRO (code) |
| Google Trends integration | In-dashboard Trends data for focus keywords | PRO (code) |
| Basic SEO tests | Keyword in SEO title, meta description, URL, first 10 percent, content, overall length | FREE |
| Additional SEO tests | Keyword in subheadings and image ALT, density, URL length, external, followed external, internal links, uniqueness | FREE |
| Title Readability tests | Keyword at start of title, sentiment, power word, number in title | FREE |
| Content Readability tests | Table of contents, short paragraphs, media usage | FREE |
| Pillar content selection | Mark a post as pillar content (code: `rank_math_pillar_content`) | FREE |
| Internal Linking Suggestions | Editor suggestions for internal links while writing | FREE |
| Link Suggestions per post type | Per-post-type toggle for link suggestions and their titles | FREE |
| AI Link Genius module | AI internal-linking suite (suggestions, audits, auto-linking) | PRO (code) |
| Centralised Links Dashboard | Single dashboard for internal-link health (code: Link Genius SPA) | PRO |
| AI Link Suggestions in editor | Context-aware anchor-text and link suggestions | PRO (code) |
| Auto-Link Keyword Variations | Keyword-to-URL maps auto-linked on publish | PRO (code) |
| Bulk Link Update tool | Update many links at once with rollback (code: snapshots table) | PRO |
| AI Recommended Related Posts | AI-curated related posts | PRO (code) |
| Related Posts Block and Shortcode | Frontend related-posts block and shortcode | PRO (code) |
| Orphan Pages detection | Detect posts with no incoming internal links | PRO (code) |
| Nofollow Link Detection | Find nofollow internal links | PRO |
| Redirected-link detection | Find redirected internal links | PRO |
| Internal-link HTTP status audit | Audit the HTTP status of internal links (code: audit table) | PRO |
| Link coverage / incoming-link analysis | Per-post incoming-link report and filters | PRO |
| Broken Link Checker | Automatically detects broken internal and external links | PRO (code) |
| Automated Keyword Linking | Keyword-to-URL maps auto-linked across new posts | PRO (code) |
| Link Genius configuration controls | Max links per post, case sensitivity, excluded post types/IDs/terms | PRO (code) |
| Exclude content from linking | Exclude post types, IDs and terms from AI Link Genius | PRO (code) |
| Affiliate-link external detection | Treat cloaked affiliate prefixes as external and add `sponsored` rel | PRO (code) |

### 3.2 Content AI (free module, metered by a paid plan)

The Content AI engine lives in the free plugin; usage is metered by a paid Content AI plan. It requires a Rank Math account and external service.

| Feature | What it does | Tier |
|---|---|---|
| Content AI module | AI content assistant panel inside the editor (code: free `content-ai` module) | FREE (metered) |
| Content AI Research | Latest-information research and recommendations for content | FREE (metered) |
| Content AI Writing | AI content generation inside WordPress | FREE (metered) |
| Content AI Images (alt text) | AI-generated image alt text | FREE (metered) |
| Free allowance | Free users get 10 uses per available feature | FREE (metered) |
| Feature usage / refresh tracking | Check Content AI usage and refresh date | FREE |
| Content AI global settings | Configure Content AI site-wide (post types, country, tone, audience, language) | FREE |
| Content AI editor integrations | Block editor, Classic editor, Elementor, Divi | FREE (metered) |
| Content AI History | Access previously generated content | FREE (metered) |
| Content AI plans | Starter, Creator, Expert annual plans with monthly feature uses that do not roll over | Add-on (paid) |
| Content AI trials | 15-day Starter (PRO), Creator (Business), Expert (Agency) | PRO / Biz / Agency |
| Content AI named tools | Command Center, Command tool, AI Blog Post Wizard, Blog Post Idea, Outline, Introduction, Conclusion, Topic Research, Keyword Research, Semantic Keyword Variations, SEO Title, SEO Description, SEO Meta, Fix SEO Tests, Bulk Edit SEO Meta, Generate alt text, Open Graph AI tool, Sentence Expander, Paragraph Rewriter, Paragraph Writing, Text Summarizer, Spin, Fix Grammar, Freeform Writing, RankBot chatbot, Product Description, Product Review, Product Pros and Cons, Product Features, Job Description, Recipe, Testimonial, Personal Bio, Company Bio, Customer Persona, Content Plan, Content Calendar, Email tool, Email Reply, Email Subject Lines, Outreach Email, Promo Email, Newsletter, Facebook Post, Facebook Comment Reply, Tweet, Tweet Reply, Instagram Caption, YouTube Video Script, YouTube Video Description, LinkedIn Bio, Podcast Episode Outline, AIDA, PAS, BAB, Hero, FAQ tool, Analogy | FREE (metered) or Add-on |
| 1-click long-form content | One-click long-form generation | Add-on |

### 3.3 Metadata and head control

| Feature | What it does | Tier |
|---|---|---|
| Control the SEO title | Per-post SEO title editing (code: `rank_math_title`) | FREE |
| Control the meta description | Per-post meta description editing (code: `rank_math_description`) | FREE |
| Snippet preview / Post Preview on Google | Live SERP snippet preview in the editor | FREE |
| Social previews | Preview Facebook and Twitter card appearance | FREE |
| Bulk edit titles and descriptions | Edit title and description across many posts | FREE |
| Auto add additional meta data | Auto-injects supplementary meta tags | FREE |
| Custom Fields per post type | Choose custom fields exposed in the meta box | FREE |
| Add SEO Controls (user roles) | Show Rank Math controls to selected roles | FREE |
| Bulk Editing per post type | Enable bulk SEO editing per post type | FREE |
| Remove Snippet Data (taxonomies) | Remove snippet data from archive templates | FREE |
| Custom HTML and verification meta tags | Add custom head meta tags | FREE |
| SEO title and description templates | Dynamic templates per post type and taxonomy | FREE |
| Variables (about 53) | Dynamic variables for titles, descriptions and schema | FREE |
| Variable: Random Word | Random-word variable (parameterised) | PRO (code) |
| Variable: Image Alt | Current image alt as a variable | PRO (code) |
| Variable: Image Title | Current image title as a variable | PRO (code) |
| Custom variables via code | Register custom variables through a filter | FREE |
| Separator character | Global title separator | FREE |
| Capitalize titles | Auto-capitalise titles | FREE |
| Rewrite titles | Rewrite titles globally | FREE |
| Modify global meta | Edit global meta output | FREE |
| Strip category base | Remove `/category/` from URLs | FREE |
| Redirect attachments | Redirect attachment URLs to the parent post | FREE |
| Auto canonical URLs | Automatic canonical tags | FREE |
| Custom canonical URL | Override canonical per post | FREE |
| Knowledge Graph meta | Output Knowledge Graph meta tags | FREE |
| Represent site as a Person | Site identity as a person | FREE |
| Represent site as a Company | Site identity as a company or organisation | FREE |
| Control ROBOTS meta | Per-post index, noindex, follow, nofollow | FREE |
| Advanced Robots Meta | noarchive, nosnippet, noimageindex, notranslate, max-snippet, max-video-preview, max-image-preview | FREE |
| Robots Meta defaults | Global, per post type, taxonomy, author and date defaults | FREE |
| Noindex empty category and tag archives | Auto-noindex empty archives | FREE |
| Noindex search results | Noindex internal search pages | FREE |
| Noindex subpages | Noindex archive subpages | FREE |
| Noindex paginated single pages | Noindex paginated single pages | FREE |
| Noindex password-protected pages | Noindex password-protected content | PRO (code) |
| Noindex paginated, archive, search pages | Prevent those URLs from indexing | FREE |
| rel=next and rel=prev tags | Pagination relationship tags | FREE |
| Facebook Open Graph | Automatic OG tags per post, homepage OG, authors, admin/app/secret settings | FREE |
| Twitter cards | Automatic card tags, homepage card, default card type, username | FREE |
| Additional social profiles | List additional social profiles | FREE |
| Default OpenGraph thumbnail and share image | Fallback share image and global default share image | FREE |
| Overlay icons on social images | Play and GIF overlay icons on share images | FREE |
| Watermarked social images | Watermark images shared on social | PRO (code) |
| Default Thumbnail Watermark | Auto-watermark the default thumbnail | PRO (code) |
| Slack Enhanced Sharing | Slack unfurl enhancement | FREE |
| Automatic flushing of Facebook thumbnails | Clears the Facebook image cache on update | FREE |
| Homepage title, description, robots and social | Homepage Title and Meta settings | FREE |
| Author archive controls | Author title, description, robots and author base | FREE |
| Date archive controls | Date archive title, description and robots | FREE |
| 404 title | Custom 404 title | FREE |
| Search results title | Custom search results title | FREE |
| Optimise archive and author pages | Archive SEO controls | FREE |
| Category, tag, product category and product tag meta | Archive titles, descriptions and robots per taxonomy | FREE |
| Forum, topic and reply meta | bbPress post-type SEO controls | FREE |
| Downloads (EDD) post-type and archive meta | EDD SEO controls | FREE |
| RM Locations post-type and taxonomy meta | Local-locations SEO (all sub-options) | PRO |
| Search engine verification | Google, Bing, Baidu, Yandex, Pinterest, Norton Safe Web | FREE |
| Custom webmaster verification tags | Add custom verification meta tags | FREE |

### 3.4 Schema and structured data

| Feature | What it does | Tier |
|---|---|---|
| Schema Generator | Structured-data generator in the editor | FREE |
| Pre-defined schema types | Built-in schema types | FREE |
| Extra schema types | Additional schema types unlocked by PRO | PRO |
| 840+ schema types | Access to the full schema.org set in the builder | PRO |
| Default schema per post type | Set default schema type per post type | FREE |
| Multiple schema types per page | Stack multiple schema graphs on one page | PRO |
| Custom Schema Builder | Build custom JSON-LD with properties, property groups and hierarchies | PRO |
| Custom schema using JSON-LD or HTML | Paste custom JSON-LD or HTML schema | PRO |
| Schema Templates | Reusable schema templates with a library | PRO |
| Schema display conditions | Show or hide schema by singular, archives or site, plus inclusion rules | PRO |
| Automate schema implementation | Automate schema via templates and conditions | PRO |
| Code validation with Google | Validate JSON-LD via the Google Rich Results test from the dashboard | PRO |
| Import schema from URL, HTML or JSON-LD | Import schema from any webpage, HTML source or raw markup | PRO |
| Advanced Schema Editor | Property groups, hierarchies, duplicate and delete | PRO |
| Schema search in generator | Search schema types quickly | FREE |
| ACF fields in Schema Generator | Use ACF values as schema variables | FREE |
| FAQ schema from ACF repeater | Generate FAQ schema from an ACF repeater | FREE |
| FAQ Schema Block (Gutenberg) | Schema-ready FAQ accordion block | FREE |
| HowTo Schema Block (Gutenberg) | Schema-ready HowTo block | FREE |
| Local Business Schema | LocalBusiness schema with 193 business types | FREE |
| Multiple schema options / advanced HowTo | Extended schema fields | PRO |
| Automatic Q&A Schema for bbPress | Auto Q&A schema for bbPress | PRO (code) |
| Automatic Video Detection for Video Schema | Auto-detect videos for schema | PRO (code) |
| Automatic Video Data Fill | Auto-fill video schema data | PRO (code) |
| Generate Video Schema for old posts | Database tool to backfill video schema | PRO (code) |
| Schema markup validator | Validate schema | PRO |
| Schema selection guide | Guidance on choosing schema | FREE |
| Free schema types | None, Article, Blog Posting, News Article, Book, CollectionPage, Course, Event, FAQ, HowTo, Job Posting, Music, Person, Product, ProfilePage, Recipe, Restaurant, Service, Software Application, Video, WebPage, WebSite, Breadcrumb, EDD, Local SEO (LocalBusiness), Sitelinks Search Box, WooCommerce, SiteNavigationElement | FREE |
| PRO schema types | Dataset, FactCheck (ClaimReview), Movie, Podcast Episode, About and Mentions, ItemList, Carousel, QandA Page, Speakable | PRO |

### 3.5 Technical SEO

| Feature | What it does | Tier |
|---|---|---|
| Powerful XML Sitemap | Search-engine-compatible XML sitemaps, auto-updated | FREE |
| Sitemap configuration | Include or exclude post types, taxonomies, individual posts | FREE |
| Per-post-type sitemap index | Separate sitemaps for posts, pages, categories, tags, products, forums, downloads, locations | FREE |
| Include images in sitemap | Add featured and content images to sitemaps | FREE |
| HTML Sitemap | Human-readable HTML sitemap with shortcode, sort and dates | FREE |
| KML Sitemap | Geo sitemap for local business locations | FREE |
| Custom Sitemap | Add custom URLs via a child-theme function | FREE |
| Google News SEO Sitemap | News sitemap for Google News (code: PRO news-sitemap module) | PRO |
| News sitemap config | Publication name, news post type, excluded terms, Googlebot-News index | PRO (code) |
| Google Video SEO Sitemap | Video sitemap (code: PRO video-sitemap module) | PRO |
| Video sitemap config | Post types, YouTube API key, custom fields, hide from humans | PRO (code) |
| Include ACF images in sitemap | Add ACF field images to sitemaps | PRO (code) |
| Sitemap submission | Submit sitemaps to Google and Bing | FREE |
| Sitemaps ping | Ping search engines on update | FREE |
| robots.txt Editor | Edit robots.txt from the dashboard | FREE |
| robots.txt rules and validator | Default rules, syntax help and tester (PRO adds the inline tester) | FREE |
| Advanced llms.txt Generator | Generate an llms.txt file for AI assistants | FREE |
| llms.txt configuration | Post types, taxonomies, limit, additional content, preview | FREE |
| .htaccess Editor | Edit .htaccess from the dashboard | FREE |
| Add sitemaps to robots.txt | Include a sitemap directive | FREE |
| Advanced Redirection Manager | Manage redirects in WordPress | FREE |
| Redirection types 301, 302, 307, 410, 451 | HTTP redirect and status types | FREE |
| Regex redirect support | Regex redirect matching | FREE |
| Match types | Exact, contains, start with, end with, regex, ignore case | FREE |
| Multiple sources per redirect | Combine source URLs | FREE |
| Destination URL and maintenance code | Redirect destination and maintenance codes | FREE |
| Debug Redirections | Built-in redirect debugger | FREE |
| Redirect attachments to parent | Redirect attachment URLs to the parent post | FREE |
| Redirect orphan attachments | Redirect orphan attachments | FREE |
| Smart and automatic post redirects | Auto-redirect when a post URL changes | FREE |
| Redirection statistics | Redirect hit statistics | FREE |
| Bulk actions in redirects | Bulk activate, deactivate and delete | FREE |
| Backing up redirects | Backup redirect rules | FREE |
| Advanced Redirections Module | Extra PRO redirection capabilities | PRO |
| Scheduled activation and deactivation | Schedule a redirect start and end date | PRO (code) |
| Organising and filtering redirections | Organise and filter redirect sets (code: category taxonomy) | PRO (code) |
| Redirections for parameterised URLs | Redirect URLs with parameters | PRO (code) |
| Export redirects as CSV | Export redirects | PRO |
| Import redirects from CSV | Bulk CSV import | PRO (code) |
| Sync redirections to .htaccess | Write redirects to .htaccess | PRO (code) |
| Simple 404 Monitor | Logs URI, access time and hit counts | FREE |
| Advanced 404 Monitor | Adds referer, user agent (OS, browser, version) and hit grouping | PRO (code) |
| Export 404 Log | Export the 404 log (date range) | PRO (code) |
| 404 to redirect workflow | Turn 404s into redirects | FREE |
| Bulk set 410 status | Set 410 in bulk | FREE |
| Instant Indexing module (IndexNow) | Submit URLs to IndexNow-participating engines | FREE |
| Automatic URL submission | Auto-submit new and updated URLs | FREE |
| Manual URL submission | Submit single or batched URLs | FREE |
| Instant Indexing bulk action | Submit pages from the posts list | FREE |
| API key management | Change and verify the IndexNow API key | FREE |
| Submission history | View submission history | FREE |
| Google Indexing API support | Support for Google's Indexing API | FREE |
| Submit-now from Index Status | Trigger Instant Indexing from Analytics Index Status | PRO |
| Automated Image SEO | Dynamically add missing ALT and title attributes | FREE |
| Add Missing ALT and ALT format | Generate ALT text with variables | FREE |
| Add Missing Title and format | Generate title attributes | FREE |
| Image SEO variable library | 39+ variables for alt, title, caption and description | FREE |
| Advanced Automated Image SEO Options | Extended image SEO controls | PRO |
| Add Missing Image Caption and format | Auto captions | PRO (code) |
| Add Missing Image Description and format | Auto descriptions | PRO (code) |
| Change title, ALT, description, caption casing | Case conversion per field | PRO (code) |
| Add ALT attributes for avatars | Auto alt for avatars | PRO (code) |
| Replacements (find and replace words) | Replace words in image fields | PRO (code) |
| Find and Replace image alt, title, caption text | Bulk find and replace image metadata | PRO (code) |
| Automate Image Captions | Automate caption generation | PRO (code) |
| Advanced Filtering for Images | Media library filters for missing alt, title and caption | FREE |

### 3.6 Analytics and reporting

| Feature | What it does | Tier |
|---|---|---|
| Google Search Console integration | Pull GSC data into WordPress | FREE |
| Install Google Analytics code | Insert the GA tracking code | FREE |
| Advanced Google Analytics 4 integration | GA4 data in the WordPress dashboard | PRO |
| Analytics Dashboard | Unified analytics dashboard with timeframe selection | PRO |
| Traffic source selection | Filter by traffic source, including AI traffic | PRO |
| AI Search Traffic Tracker | Track traffic from ChatGPT, Perplexity and others | PRO |
| Overall optimisation chart | Site-wide optimisation chart | PRO |
| SEO Performance Overview Report | High-level SEO performance report | PRO |
| Keyword Report Overview | Keywords report with clicks, impressions, CTR and position | PRO |
| Keyword Positions | Position data per keyword | PRO |
| Site Analytics | Sortable site analytics | PRO |
| Post Analytics | Per-post analytics report | PRO |
| Top 5 winning and losing keywords | Best and worst keywords, last 30 days | PRO |
| Top 5 winning and losing posts | Best and worst posts by search traffic | PRO |
| Advanced Content SEO Overview | Content SEO overview dashboard | PRO |
| Ranking keywords for each post | Per-post ranking keyword list | PRO |
| Position history | Historical ranking positions for keywords and posts | PRO |
| Single Post SEO Reports | Per-post SEO performance report | PRO |
| Single Post Performance Badges | Badges on top-performing posts | PRO |
| Track PageSpeed per post and page | PageSpeed and load time per URL in the dashboard | PRO |
| Google AdSense earning history | AdSense earnings in the dashboard | PRO |
| Import GSC and GA data by country | Country-filtered GSC and GA data | PRO |
| Google Algorithm Updates timeline | Google update markers in analytics graphs | PRO |
| Keyword Rank Tracker | Track keyword rankings from GSC and GA data | PRO |
| Tracked keyword quota | 500 PRO, 10000 Business, 50000 Agency | PRO / Biz / Agency |
| Client Management | Manage tracked-keyword quotas across client sites | Biz / Agency |
| Google Index Status (URL Inspection API) | Google index status in the dashboard (free is limited) | FREE (limited) |
| Index Status aggregates | Top statuses and presence on Google | PRO |
| Index Status of individual posts | Per-URL index status and last crawl | PRO |
| SEO Performance Email Reports | Periodic SEO reports by email (free is limited) | FREE (limited) |
| Email report sections | Search traffic, impressions, keywords, average position, winning and losing posts and keywords | FREE / PRO |
| White-labelled email reports | Branded client reports (heading, logo, colours, custom CSS) | Biz / Agency |
| Include only tracked keywords in report | Restrict the report to tracked keywords | PRO |
| Hide email reporting options | Hide report settings from roles | FREE |
| Anonymise IP addresses | GA IP anonymisation | PRO |
| Self-hosted Google Analytics JS file | Serve the GA JS locally | PRO |
| Exclude logged-in users from GA tracking | Do not track logged-in users | PRO |
| Google data fetch frequency | 3 days free, daily PRO and above | FREE / PRO |
| Days to preserve Google data | 90 free, 180 PRO, unlimited Business and above | FREE / PRO |
| Email report frequency | 30 free, 15 or 30 PRO, 7, 15 or 30 Business and above | FREE / PRO |
| AI Visibility module | Monitor brand presence across AI platforms | Add-on (paid) |
| AI Visibility tabs | Overview, Queries, Competitors, Raw Data and Transcripts | Add-on |
| Track brand mentions | Single, average across brands and competing brands | Add-on |
| Track brand sentiment | Brand, query and competitor sentiment in AI answers | Add-on |
| AI Brand Visibility Report | Generate a brand visibility report | Add-on |
| MCP module and tools | Expose Rank Math abilities to AI assistants (settings, status, robots and llms, audits, per-post analysis, redirects, GSC keywords, AI visibility) | FREE / PRO / Add-on |

### 3.7 Verticals

| Feature | What it does | Tier |
|---|---|---|
| WooCommerce: remove product base and category base | Drop `/product/` and the product-category base from URLs | FREE |
| WooCommerce: remove parent slugs | Remove parent slugs from product URLs | FREE |
| WooCommerce: remove generator tag | Remove the WooCommerce generator tag | FREE |
| WooCommerce: remove shop-archive schema | Remove shop archive schema | FREE |
| WooCommerce: brand category | Product brand taxonomy | FREE |
| WooCommerce: product schema fields | Add product brand, price, currency, availability | FREE |
| WooCommerce: gallery images in OG and sitemap | Gallery images in og:image and sitemap | FREE |
| WooCommerce: exclude hidden products from sitemap | Exclude hidden products | FREE |
| WooCommerce: product variables | Product-specific variables for meta and schema | FREE |
| WooCommerce: short description in analysis | Use the product short description in content analysis | FREE |
| WooCommerce: custom brand from settings | Configure a custom brand in settings | PRO (code) |
| WooCommerce: GTIN, MPN and variations | Global identifiers including variations | PRO (code) |
| WooCommerce: show global identifier on frontend | Display GTIN on the product page | PRO (code) |
| WooCommerce: noindex hidden products | Noindex hidden products and prune the sitemap | PRO (code) |
| WooCommerce: GTIN in Product schema | Include the GTIN value in Product schema | PRO (code) |
| WooCommerce: improved variation schema | One Offer entity per variation | PRO (code) |
| WooCommerce: product content analysis tests | Dedicated product-page content tests | PRO (code) |
| WooCommerce: duplicate-content handling | Prevent duplicate content in the store | FREE |
| WooCommerce: GTIN migration tool | Database tool for GTIN migration | PRO (code) |
| Local SEO module and Knowledge Graph | Local business and entity setup | FREE |
| Local SEO identity fields | Person or company, website name, alternate name, description, logo, URL | FREE |
| Local SEO contact fields | Email, phone number, price range, additional info | FREE |
| Local SEO address and geo | Address, address format, GeoCoordinates, Google Maps API key | FREE |
| Local SEO business type | Choose from 193 Local Business types | FREE |
| Local SEO opening hours | Opening hours and format | FREE |
| Local SEO pages | About page and contact page | FREE |
| Local SEO contact-info shortcode | Output contact info with schema anywhere | FREE |
| Local SEO multiple locations | Support multiple business locations (Locations CPT) | PRO (code) |
| Local SEO store-hours display options | Hide hours, closed label, open 24-7 label | PRO (code) |
| Local SEO display options | Measurement system, map style, max locations, primary country, route label, location detection | PRO (code) |
| Local SEO organisation grouping and search | All locations part of the same organisation, enhanced search | PRO (code) |
| Local SEO CPT configuration | Locations post-type base, category base, post-type names | PRO (code) |
| Local SEO advanced blocks | Local business Gutenberg blocks | PRO (code) |
| Local SEO store locator and map | Map, store locator, address, hours, multiple-location schema | PRO (code), maps key required |
| Local SEO builder integration | LocalBusiness schema in Elementor and Divi | PRO (code) |
| Local SEO multiple areaServed cities | Add multiple served cities to LocalBusiness schema | FREE |
| News sitemap and NewsArticle config | Google News sitemap and config | PRO (code) |
| Video sitemap and video schema autodetect | Video sitemap plus VideoObject detection and data fill | PRO (code) |
| Podcast module | Podcast schema, podcast RSS feed and frontend display | PRO (code) |
| Podcast episode schema and RSS | Episode markup and podcast RSS feed | PRO (code) |
| Easy Digital Downloads schema | EDD product schema | FREE |
| Complete EDD SEO | Full EDD SEO feature set | PRO |
| Google Web Stories module | SEO metadata for Web Stories | FREE |
| Web Stories metadata configuration | Per-story meta titles and descriptions | FREE |
| AMP module | Correct metadata and schema for AMP (AMP for WordPress, AMP for WP, weeblrAMP, AMP for WooCommerce, WP AMP) | FREE |
| bbPress post-type SEO | SEO controls for forums, topics and replies | FREE |
| Automatic QandA schema for bbPress | Auto QandA schema | PRO (code) |
| BuddyPress variables | BuddyPress-specific schema variables | FREE |

### 3.8 Integrations

| Feature | What it does | Tier |
|---|---|---|
| Elementor integration | Elementor SEO panel and integration; enable on Elementor templates | FREE |
| Elementor breadcrumbs widget | Dedicated Elementor breadcrumbs widget | PRO (code) |
| Elementor accordion to FAQ schema | Convert an Elementor accordion to FAQ schema | PRO (code) |
| Divi integration | Divi SEO panel and integration | FREE |
| Divi accordion to FAQ schema | Convert a Divi accordion to FAQ schema | PRO (code) |
| Block Editor (Gutenberg) integration | Meta box and blocks in Gutenberg | FREE |
| Classic Editor integration | Meta box in the Classic editor | FREE |
| Advanced Custom Fields (ACF) module | Use ACF in SEO meta and schema, ACF for focus keywords | FREE |
| ACF images in sitemap | ACF images in the sitemap | PRO (code) |
| Google Search Console | GSC connection | FREE |
| Google Analytics 4 | GA4 connection | PRO |
| Google AdSense | AdSense earnings integration | PRO |
| Google Trends | Trends integration | PRO |
| Google Indexing API | Indexing API support | FREE |
| MCP / AI assistants | ChatGPT, Claude Desktop, GitHub Copilot integration | FREE |
| WP Rocket integration | Install and use WP Rocket via Rank Math | PRO |
| Imagify install | Image optimisation integration | PRO |
| Rank Math plus WP Rocket bundle | Bundled WP Rocket licence | Add-on |
| WPML and TranslatePress compatibility | Multilingual plugin compatibility | FREE |
| Crocoblock and JetEngine compatibility | Dynamic content compatibility | FREE |
| Page builders and themes | Certified-compatible products list | FREE |
| WooCommerce | Store integration | FREE |
| Certified compatible plugins and themes | GeoDirectory, LearnPress, Modern Events Calendar, Ultimate Blocks, Stackable, FooGallery, Admin Columns, Joli TOC, Media Cleaner, Shoptimizer and about 40 more | FREE |
| Headless CMS support | REST API endpoints for SEO metadata | FREE |
| Table of Contents block | Rank Math TOC Gutenberg block | FREE |
| Related Posts block and shortcode | Related posts block and shortcode | PRO (code) |
| Local Business Info block | Local business Gutenberg block | PRO (code) |
| FAQ Schema Block | FAQ block with schema | FREE |
| HowTo Schema Block | HowTo block with schema | FREE |
| Breadcrumbs function | Theme-callable breadcrumbs | FREE |
| Breadcrumbs customisation | Separator, homepage, prefix, archive, search and 404 labels, categories, blog page | FREE |

### 3.9 Admin UX, tools and governance

| Feature | What it does | Tier |
|---|---|---|
| Simple Setup Wizard | Guided initial configuration | FREE |
| Custom Setup Wizard Mode | Advanced and custom wizard path | PRO (code) |
| Optimal settings pre-selected | Sensible defaults | FREE |
| Clean, simple user interface | Admin UI | FREE |
| Compatibility check | Detects conflicting plugins and settings | FREE |
| Module-based system | Enable and disable modules | FREE |
| SEO Score shown to visitors | Display a frontend SEO score | FREE |
| SEO Score post types, template, position | Frontend score-block configuration | FREE |
| Dashboard widgets and Rank Math column | SEO score column in post lists, dashboard widget | FREE |
| Advanced Bulk Edit Options | Bulk noindex and index, nofollow and follow, remove canonical, add or remove redirect, set or remove schema | PRO (code) |
| Bulk actions | AI write SEO title and description, AI alt text, determine search intent, Instant Indexing submit | FREE / PRO (code) |
| Advanced Quick Edit Options | Quick-edit SEO title, description, primary category, focus keyword, canonical, robots | PRO (code) |
| Advanced Post Filtering | Filter posts by SEO score, no focus keyword, noindexed, custom canonical, title or description, redirected, orphan, schema type | PRO (code) |
| Media Library SEO filters | Missing alt, missing title, missing caption filters | PRO (code) |
| Term SEO Details column | SEO details column on term lists | PRO (code) |
| Import and Export Settings | Export and import general, titles, sitemap, role manager, redirections, plus backups | FREE |
| Import SEO Data via CSV | CSV import of SEO metadata | PRO (code) |
| Import and Export Focus Keywords | CSV focus-keyword import and export | PRO |
| Complete Import and Export Options | Full export and import suite | PRO |
| Role Manager | Control Rank Math capabilities per user role, reset settings | FREE |
| Version Control (native rollback) | Roll back Rank Math to a previous version | FREE |
| Beta updates toggle | Opt into beta versions | FREE |
| Auto-update preferences | Enable or disable automatic updates | FREE |
| Database Tools | Maintenance utilities (flush SEO data, remove transients, purge analytics cache, rebuild analytics index, clear 404 log, recreate tables, convert Yoast blocks, delete internal-link data, delete redirects, update SEO scores, cancel Content AI bulk job, GTIN migration) | FREE (GTIN migration PRO) |
| System Status: system info | Environment and plugin info report | FREE |
| System Status: error log | Error log viewer | FREE |
| Contextual help and documentation | In-context documentation and knowledge base | FREE |
| Multisite compatible | WordPress multisite support | FREE |
| RSS optimisation | RSS feed optimisation, content before and after feed | FREE |
| Notify on plugin update available | Email or notice on updates | FREE |
| Rank Math Vault | Securely share site credentials with support | FREE |

### 3.10 Migration and importer tools

| Feature | What it does | Tier |
|---|---|---|
| One-click import from Yoast SEO | Import Yoast settings and meta | FREE |
| One-click import from All in One SEO | Import AIOSEO settings and meta | FREE |
| One-click import from SEOPress | Import SEOPress settings and meta | FREE |
| Import AIO schema rich snippets | Import AIOSEO schema | FREE |
| Import from the Redirection plugin | Import redirect rules from the Redirection plugin | FREE |
| Import Yoast Premium redirects | Import Yoast Premium redirects | FREE |
| Plugin Importers section | Central importer UI | FREE |
| Bulk import redirects | Bulk redirect import in the Redirections manager | FREE |
| Import redirections data via CSV | CSV redirect import | PRO (code) |
| Import SEO data via CSV | CSV SEO metadata import | PRO (code) |
| Import and export focus keywords | CSV keyword import and export | PRO |
| Export 404 log | CSV 404 export | PRO (code) |
| Export redirections CSV | CSV redirect export | PRO |
| Complete import and export options | Full settings import and export | PRO |
| Import Schema PRO data | Import schema from external or custom sources | PRO |
| Import All-in-One schema data | Import AIOSEO schema | FREE |
| Yoast block converter | Convert Yoast blocks in content | FREE |
| Convert Yoast and AIOSEO TOC blocks | Convert existing TOC blocks | FREE |

### 3.11 Licensing, support and plan limits

| Feature | What it does | Tier |
|---|---|---|
| Plans | Rank Math PRO EUR 7.99/mo, Business EUR 24.99/mo, Agency EUR 54.99/mo (billed annually, ex VAT) | Paid |
| Client sites per account | Excluded on PRO, 100 on Business, 500 on Agency | Biz / Agency |
| Tracked keyword quota | 500 PRO, 10000 Business, 50000 Agency | PRO / Biz / Agency |
| Support | Community and standard free, 24/7 PRO, 24/7 priority Business and Agency | FREE / PRO / Biz / Agency |
| White-labelled email reports | Business and Agency only | Biz / Agency |
| Client management | Business and Agency only | Biz / Agency |
| 30-day money-back guarantee | PRO and above | Paid |
| Exclusive Facebook club | PRO and above | Paid |
| One-click automatic updates | PRO and above | PRO |
| Non-profit discount | Available per the knowledge base | FREE |
| Upgrades and plan changes | PRO and above | Paid |
| No lifetime deal | Confirmed | Paid |
| Content AI trials bundled | Starter (PRO), Creator (Business), Expert (Agency) | PRO / Biz / Agency |

### 3.12 The 106 item PRO-only list, mapped

This is the consolidated PRO and Business-gated list from `rankmath-web.md` (duplicates removed). Each item is catalogued above; this table guarantees full representation.

| # | PRO item | Covered in |
|---|---|---|
| 1 | Advanced Google Analytics 4 Integration | 3.6 |
| 2 | Keyword Rank Tracker (500 / 10000 / 50000 tracked keywords) | 3.6 |
| 3 | Site Analytics dashboard | 3.6 |
| 4 | Traffic source filtering | 3.6 |
| 5 | AI Search Traffic Tracker | 3.6 |
| 6 | SEO Performance Email Reports (full, free is limited) | 3.6 |
| 7 | White Labelled Email Reports (Biz) | 3.6, 3.11 |
| 8 | Client Management (Biz) | 3.6, 3.11 |
| 9 | Track Top 5 Winning and Losing Keywords | 3.6 |
| 10 | Track Top 5 Winning and Losing Posts | 3.6 |
| 11 | Advanced Content SEO Overview | 3.6 |
| 12 | Check Ranking Keywords for Each Post | 3.6 |
| 13 | Position History for Keywords and Posts | 3.6 |
| 14 | Single Post SEO Reports | 3.6 |
| 15 | Single Post Performance Badges | 3.6 |
| 16 | Track PageSpeed for Each Post and Page | 3.6 |
| 17 | Google AdSense Earning History | 3.6 |
| 18 | Import GSC and GA data for a particular country | 3.6 |
| 19 | Google Algorithm Updates timeline | 3.6 |
| 20 | Index Status: top statuses and presence on Google aggregates | 3.6 |
| 21 | Anonymise IP addresses | 3.6 |
| 22 | Self-hosted Google Analytics JS file | 3.6 |
| 23 | Exclude logged-in users from GA tracking | 3.6 |
| 24 | Full Google Index Status report (free is limited) | 3.6 |
| 25 | Import schema from any website (URL, HTML, JSON-LD) | 3.4 |
| 26 | Speakable Schema | 3.4 |
| 27 | Google Trends integration | 3.1 |
| 28 | Google News SEO Sitemap plus config | 3.5 |
| 29 | Google Video SEO Sitemap plus config | 3.5 |
| 30 | Advanced Image SEO module | 3.5 |
| 31 | Find and Replace image alt, title, caption text | 3.5 |
| 32 | Automate Image Captions | 3.5 |
| 33 | Watermarked social media images | 3.3 |
| 34 | Default Thumbnail Watermark | 3.3 |
| 35 | Local SEO PRO with multiple locations | 3.7 |
| 36 | Advanced Local SEO Blocks | 3.7 |
| 37 | Multiple Location Schema via block or shortcode | 3.7 |
| 38 | LocalBusiness schema in Elementor and Divi | 3.7 |
| 39 | Local SEO display settings (hours, labels, units, map, country, route, detection, organisation, search, CPT bases) | 3.7 |
| 40 | RM Locations post type and taxonomy Titles and Meta | 3.3 |
| 41 | WooCommerce SEO PRO (brand, GTIN and MPN, show identifier, noindex hidden, GTIN in schema, variation entities, product tests) | 3.7 |
| 42 | GTIN Migration Tool for WooCommerce | 3.7, 3.9 |
| 43 | Complete EDD SEO | 3.7 |
| 44 | Podcast module plus settings, episode schema and RSS feed | 3.7 |
| 45 | 6 extra Schema Types | 3.4 |
| 46 | Auto-detect Video for Video Schema | 3.4, 3.7 |
| 47 | Automatic Video Data Fill for Video Schema | 3.4, 3.7 |
| 48 | Generate Video Schema for old posts (DB tool) | 3.4, 3.9 |
| 49 | 840+ Schema Types Supported | 3.4 |
| 50 | Add Custom Schema using JSON-LD or HTML | 3.4 |
| 51 | Custom Schema Builder | 3.4 |
| 52 | Schema Templates | 3.4 |
| 53 | Schema display conditions and automate schema implementation | 3.4 |
| 54 | Add unlimited multiple schemas per page | 3.4 |
| 55 | Validate schema with Google and code validation | 3.4 |
| 56 | Advanced Schema Editor (property groups, hierarchies) | 3.4 |
| 57 | Dataset Schema | 3.4 |
| 58 | Fact Check Schema | 3.4 |
| 59 | Podcast Schema | 3.4, 3.7 |
| 60 | Carousel Schema | 3.4 |
| 61 | Mentions and About Schema | 3.4 |
| 62 | ItemList Schema | 3.4 |
| 63 | QandA Page Schema | 3.4 |
| 64 | Movie Schema | 3.4 |
| 65 | Automatic QandA Schema for bbPress | 3.4, 3.7 |
| 66 | Advanced Redirections Module | 3.5 |
| 67 | Scheduled redirect activation and deactivation | 3.5 |
| 68 | Organising and filtering redirections | 3.5 |
| 69 | Redirections for parameterised URLs | 3.5 |
| 70 | Export redirects as CSV | 3.5 |
| 71 | Import redirections data via CSV | 3.5 |
| 72 | Sync redirections to .htaccess | 3.5 |
| 73 | Advanced 404 Monitor (referer, user agent, hit grouping) | 3.5 |
| 74 | Export 404 Log | 3.5 |
| 75 | Advanced Post Filtering | 3.9 |
| 76 | Advanced Bulk Edit Options | 3.9 |
| 77 | Advanced Quick Edit Options | 3.9 |
| 78 | Complete Import and Export Options | 3.9, 3.10 |
| 79 | Complete Elementor Integration extras (breadcrumbs widget, accordion to FAQ) | 3.8 |
| 80 | Complete Divi Integration extras (accordion to FAQ) | 3.8 |
| 81 | Import and Export Focus Keywords | 3.9, 3.10 |
| 82 | Import SEO Data via CSV File | 3.9, 3.10 |
| 83 | Detect Orphan Pages | 3.1 |
| 84 | Advanced HowTo Schema Options | 3.4 |
| 85 | Mark Cloaked Links as External Links | 3.1 |
| 86 | Noindex Password Protected Pages | 3.3 |
| 87 | Client Sites Support Per Account (100 Biz / 500 Agency) | 3.11 |
| 88 | Custom Setup Wizard Mode | 3.9 |
| 89 | Google data fetch frequency: daily (vs 3 days free) | 3.6 |
| 90 | Days to preserve Google data: 180 PRO, unlimited Business and above | 3.6 |
| 91 | Email report frequency: 15 or 30 PRO, 7, 15 or 30 Business and above | 3.6 |
| 92 | Advanced content analysis tests (Product Schema, reviews, dedicated product tests) | 3.1, 3.7 |
| 93 | Competitor SEO Analysis | 3.1 |
| 94 | Side by Side SEO Comparison | 3.1 |
| 95 | Search Intent Analysis | 3.1 |
| 96 | AI Link Genius (full suite) | 3.1 |
| 97 | Broken Link Checker | 3.1 |
| 98 | Automated Keyword Linking | 3.1 |
| 99 | Related Posts Block and Shortcode | 3.1, 3.8 |
| 100 | Link Opportunities and internal-link audit outputs | 3.1 |
| 101 | ACF images in sitemap | 3.5, 3.8 |
| 102 | Competitor site SEO audit via MCP | 3.6, 3.8 |
| 103 | 24/7 and dedicated premium support | 3.11 |
| 104 | White-labelled client reporting (Biz) | 3.6, 3.11 |
| 105 | Rank Math Vault (credential sharing), tier ambiguous | 3.9 |
| 106 | Content AI trials bundled (Starter, Creator, Expert) | 3.2, 3.11 |

## 4. Yoast complete feature inventory

Tier column: FREE = in the free plugin; PREMIUM = in Yoast SEO Premium; BUNDLED = a former separate addon now included in Premium (Local SEO, News SEO, Video SEO); ADD-ON = a separate paid product (WooCommerce SEO, AI+, Shopify, Google Docs add-on); THIRD-PARTY = an external service or plugin. Rows marked (code) are corroborated in the on-disk source by `yoast-code.md`, `yoast-code-a.md`, `yoast-code-b.md` or `yoast-code-c.md`.

### 4.1 Content and SEO analysis

| Feature | What it does | Tier |
|---|---|---|
| SEO analysis (real-time traffic-light) | Live red, orange and green checks plus an overall content score in the editor (code) | FREE |
| Focus keyphrase input | Sets the target phrase that drives all SEO checks (code: `_yoast_wpseo_focuskw`) | FREE |
| Cornerstone content marking | Marks the most important pages (code: `_yoast_wpseo_is_cornerstone`) | FREE |
| Keyphrase in introduction | Checks keyphrase words appear in the first paragraph | FREE |
| Keyphrase length | Counts content words in the keyphrase against limits | FREE |
| Keyphrase density | Checks co-occurrence at 0.5 to 3 percent | FREE |
| Keyphrase in meta description | Checks keyphrase words appear in the meta description | FREE |
| Keyphrase in subheadings | Checks keyphrase in H2 and H3 | FREE |
| Competing links / keyphrase in link text | Warns when anchor text uses the keyphrase | FREE |
| Keyphrase in image alt attributes | Keyphrase in alt text (synonym matching is Premium) | FREE |
| Keyphrase in SEO title | Checks the keyphrase is used in the title | FREE |
| Keyphrase in slug | Checks the keyphrase is used in the URL | FREE |
| Previously used / duplicate keyphrase | Warns if the keyphrase was used on another post (cannibalisation) | FREE |
| Text length | Checks body text is long enough | FREE |
| Text length for taxonomy pages | Checks category and tag descriptions are long enough | FREE |
| Outbound links | Checks for followed outbound links | FREE |
| Internal links | Checks for followed internal links | FREE |
| SEO title width | Checks title pixel width is within display limits | FREE |
| Meta description length | Checks description length is within display limits | FREE |
| Function words | Warns when the keyphrase is only low-meaning function words | FREE |
| Image count and alt-tags checks | Enough content images and every image has alt | FREE |
| Single H1 | Checks exactly one H1 | FREE |
| Lists presence | Checks for a list where relevant | FREE |
| Readability analysis (traffic-light) | Per-check readability score with sentence highlighting | FREE |
| Readability: subheading distribution | Flags long text without enough subheadings | FREE |
| Readability: paragraph length | Flags overly long paragraphs | FREE |
| Readability: sentence length | Flags share of sentences over 20 words | FREE |
| Readability: sentence beginnings | Flags three or more consecutive same-word starts | FREE |
| Readability: passive voice | Flags passive sentences above 10 percent | FREE |
| Readability: transition words | Checks sentence flow via transition words | FREE |
| Readability: text presence | Requires minimum text before assessments run | FREE |
| Flesch Reading Ease score and word count | Reading-ease score and word count in the Insights tab | FREE |
| Keyphrase distribution | Checks keyphrase and synonyms are spread evenly, with highlight | PREMIUM (code) |
| Keyphrase synonyms | Register synonyms counted as keyphrase matches | PREMIUM |
| Related keyphrases | Up to 4 additional phrases with looser assessment | PREMIUM |
| Word-form recognition (morphology) | Plurals, tenses and word-order variants in 20+ languages, requires an active subscription | PREMIUM |
| Word complexity | Highlights complex words to replace | PREMIUM (code) |
| Text alignment | Premium-only readability assessment | PREMIUM (code) |
| Stale cornerstone content | Flags cornerstone articles not updated recently | PREMIUM |
| Prominent words Insights | Top word combinations that feed internal linking | PREMIUM |
| Estimated reading time metric | Reading-time metric in the Insights sidebar | PREMIUM |
| Estimated Reading Time block | Front-end reading-time badge | PREMIUM (code) |
| Inclusive Language Analysis | Flags non-inclusive terms with alternatives | PREMIUM |
| Inclusive language column and filter | List-table inclusion for the assessment | PREMIUM (code) |
| Internal linking suggestions | Suggests related posts ranked by overlapping prominent words | PREMIUM (code) |
| Internal-link counts overview | Shows internal links in and out of a post | PREMIUM |
| Internal linking block: Related Links | One-click list of auto-suggested related content | PREMIUM (code) |
| Internal linking block: Subpages | Auto-lists child pages of a parent | PREMIUM (code) |
| Internal linking block: Siblings | Auto-lists sibling pages | PREMIUM (code) |
| Internal linking block: Table of Contents | Auto-builds a TOC from headings | PREMIUM (code) |
| Breadcrumbs block | Adds a breadcrumb trail without code (breadcrumb output is free) | FREE / PREMIUM |
| Ecommerce content analysis | Flags missing SKU, GTIN, ISBN, short description and alt | ADD-ON (WooCommerce SEO) |
| Semrush integration | Related-keyphrase data with volume, trend, difficulty and intent | FREE (limited) / PREMIUM (unlimited) |
| Wincher integration | Tracks keyphrase Google positions with a dashboard and competitor view | FREE (limited) / PREMIUM |
| Yoast AI Generate (SEO and social metadata) | AI titles and descriptions with options and regenerate | PREMIUM |
| Yoast AI Generate (product metadata at scale) | AI titles and descriptions across product catalogs | ADD-ON (WooCommerce SEO) |
| Yoast AI Optimize | One-click AI fixes for keyphrase, density, distribution and length checks | PREMIUM |
| Yoast AI Summarize / Key Takeaways block | Generates an editable bullet summary block | PREMIUM (code) |
| Yoast AI Content Planner | Five site-specific post ideas plus a structured starter draft | PREMIUM |
| AI Brand Insights | Weekly AI mention, visibility, sentiment, citation and competitor tracking across ChatGPT, Claude, Gemini and Perplexity | ADD-ON (AI+) |

### 4.2 Metadata and head control

| Feature | What it does | Tier |
|---|---|---|
| SEO title output (`<title>`) | Templated and per-post editable SEO titles | FREE |
| Meta description output | Per-post or templated meta descriptions | FREE |
| Slug control in the Google preview | Edit the URL slug from the search-appearance preview | FREE |
| Google / search snippet preview | Live simulation of the search result with length feedback | FREE |
| Meta-tag and snippet variables (50+) | Templates such as `%%title%% %%sitename%% %%excerpt%%` | FREE |
| Basic and advanced variables | `title`, `sitename`, `sitedesc`, `excerpt`, `sep`, `pt_single`, `modified`, `focuskw`, `cf_*`, `ct_*` and more | FREE |
| WooCommerce variables | `wc_shortdesc`, `wc_sku`, `wc_brand`, `wc_price`, `ct_product_cat` | ADD-ON (WooCommerce SEO) |
| Default title templates per content type | Defaults for posts, pages, archives, homepage, 404 and search | FREE |
| Content-type defaults (Search Appearance) | Title, meta, robots, schema and social templates per type | FREE |
| Taxonomy advanced settings | Per-taxonomy noindex plus title, meta, social and schema defaults | FREE |
| Per-post Advanced tab | Noindex, nofollow and canonical override per URL | FREE |
| Robots meta site-wide defaults | Default index controls for post types, date and author archives, taxonomies | FREE |
| Canonical URL auto-output | Auto `link rel=canonical`, omitted on noindex and error pages | FREE |
| Canonical override and capability control | Custom canonical per post, restrict who can edit | FREE |
| OpenGraph data | `og:*` and `article:*` tags with title, description and image hierarchy | FREE |
| Per-post Facebook title, description and image | Custom OG values via the Social media appearance box | FREE |
| X / Twitter Cards | `twitter:*` cards with OpenGraph fallback | FREE |
| Per-post X title, description and image | Custom X snippet values | FREE |
| Social previews | Live Facebook, X and LinkedIn preview before publishing | PREMIUM |
| Enhanced Slack sharing | Rich Slack unfurls with author and reading time | FREE |
| Front-end SEO inspector | One-click front-end readout of title, description, robots, schema and scores | PREMIUM |
| OpenGraph for archives | OpenGraph output for author, date, post-type and term archives | PREMIUM (code) |

### 4.3 Schema and structured data

| Feature | What it does | Tier |
|---|---|---|
| Schema graph | Single connected `@graph` JSON-LD linking Organisation, WebSite, WebPage and entities | FREE |
| Enable Schema Framework toggle | Global on and off for the graph | FREE |
| Schema tab (per-post Page and Article type picker) | Override the default type per URL | FREE |
| Schema defaults per post type | Change the default Page or Article type for all posts of a type | FREE |
| Organisation node | Site identity node with logo and sameAs | FREE |
| WebSite and SearchAction | Site node with an internal site-search target | FREE |
| WebPage plus selectable subtypes | Base page node with about 12 selectable subtypes (WebPage, ItemPage, AboutPage, FAQPage, QAPage, ProfilePage, ContactPage, MedicalWebPage, CollectionPage, CheckoutPage, RealEstateListing, SearchResultsPage) | FREE |
| Article plus subtypes | Article, BlogPosting, SocialMediaPosting, NewsArticle, AdvertiserContentArticle, SatiricalArticle, ScholarlyArticle, TechArticle, Report, None | FREE |
| Person and Author | Author and owner identity merged into Organisation when the site represents a person | FREE |
| BreadcrumbList and ListItem | Breadcrumb structured data (requires breadcrumb output) | FREE |
| ImageObject and image metadata | Prominent, featured and social images as graph nodes | FREE |
| FAQPage and Question (FAQ block) | Valid FAQ schema that flips WebPage to FAQPage | FREE |
| HowTo and HowToStep (How-to block) | Steps, images and timing auto-emit HowTo schema | FREE |
| CreativeWork, CommentAction, ReadAction | Generic work and interaction nodes | FREE |
| Site representation | Organisation or Person plus social profiles, X handle and Facebook page | FREE |
| Custom schema via Schema API | Developers add or alter graph pieces with filters and block hooks | FREE |
| Schema integrations framework | Third-party plugins inject into the Yoast graph | FREE |
| Schema partner types | WP Recipe Maker to Recipe, The Events Calendar to Event, Seriously Simple Podcasting to Podcast, WooCommerce to Product, EDD | THIRD-PARTY (+ partner plugin) |
| Schema aggregation endpoint / schemamap | Opt-in single endpoint of whole-site deduplicated schema for AI agents (NLWeb-ready); tier is conflicting | FREE / PREMIUM (conflicting) |
| VideoObject | Video nodes for detected or embedded videos | BUNDLED (Video SEO) |
| LocalBusiness plus PostalAddress, GeoCoordinates, OpeningHoursSpecification, branchOf | Address, hours, geo and phone merged into Organisation or per-location pages | BUNDLED (Local SEO) |
| NewsArticle plus subtypes and copyright | Article re-typed to News with copyright holder | BUNDLED (News SEO) |
| Product, Offer, AggregateOffer, Review, AggregateRating, ProductGroup | Full commerce graph on product pages | ADD-ON (WooCommerce SEO) |
| Organisation trust / E-E-A-T enrichment | publishingPrinciples, ownershipFundingInfo, correctionsPolicy, ethicsPolicy, legalName, foundingDate, vatID, taxID, duns, leiCode, naics, numberOfEmployees and more | PREMIUM (code) |
| User profile schema fields | honourificPrefix, birthDate, gender, award, knowsAbout, jobTitle, worksFor and more | PREMIUM (code) |
| Mastodon profile on Person schema | Adds the Mastodon profile to sameAs | PREMIUM (code) |

### 4.4 Technical SEO

| Feature | What it does | Tier |
|---|---|---|
| XML sitemaps (base index) | Auto-generates a sitemap index, 1000 URLs per sitemap, noindex `X-Robots-Tag`, replaces WP core | FREE |
| Post, page and custom post type sitemaps | Per-type sub-sitemaps with a per-type toggle | FREE |
| Category and tag / custom taxonomy sitemaps | Per-taxonomy sub-sitemaps, excludable via toggle or filter | FREE |
| Author sitemap | Lists author archives (only authors with posts unless include-empties is on) | FREE |
| Image sitemaps (embedded) | `<image:image>` entries within each URL entry | FREE |
| Sitemap XSL stylesheet | Renders the sitemap XML | FREE |
| Sitemap cache and validator | Per-type cache with invalidation on content change | FREE |
| Breadcrumbs (frontend and Schema) | Theme, shortcode and block breadcrumbs with BreadcrumbList | FREE |
| Breadcrumb controls / primary category | Chooses which category appears in the breadcrumb path | FREE |
| Redirect attachment URLs | Auto-redirects media attachment pages to the file | FREE |
| robots.txt file editor | Edits robots.txt from Tools, file editor | FREE |
| .htaccess file editor | Edits .htaccess from the same file editor (server dependent) | FREE |
| Indexables / Optimize SEO data | Builds indexable DB tables for fast meta output and link counts | FREE |
| Site representation settings | Organisation (name, logo, alternate name, social profiles) or Person for the Knowledge Graph | FREE |
| REST API head endpoint | Exposes titles, descriptions, canonical, OpenGraph, robots and Schema graph for headless | FREE |
| RSS feed settings | Prepends and appends links to feed items to combat scrapers | FREE |
| Special pages | Custom title templates for internal search results and 404 error pages | FREE |
| llms.txt and .md companions | Auto-generates a customisable LLM-friendly `llms.txt` map with preview | FREE (code) |
| Crawl optimisation suite | Toggle suite removing WP overhead URLs and metadata to save crawl budget | PREMIUM (page says FREE+PREMIUM, help doc says PREMIUM) |
| Crawl: remove shortlinks | Removes `link rel=shortlink` metadata | PREMIUM (code) |
| Crawl: remove REST API links | Removes `link rel=https://api.w.org/` header links | PREMIUM (code) |
| Crawl: remove RSD and WLW links | Removes Really Simple Discovery and Windows Live Writer links | PREMIUM (code) |
| Crawl: remove oEmbed links | Removes oEmbed discovery links | PREMIUM (code) |
| Crawl: remove generator, Pingback, Powered-by | Removes generator meta, Pingback and Powered-by headers | PREMIUM (code) |
| Crawl: disable feeds | Removes global, comment, author, post-type, category, tag, custom-taxonomy, search, Atom and RDF feeds (10 toggles) | PREMIUM (code) |
| Crawl: remove emoji scripts and unused resources | Strips WP emoji scripts and other unused resources | PREMIUM (code) |
| Crawl: internal site search cleanup | Filters search terms, cleans pretty search URLs, blocks crawl of internal search | PREMIUM (code) |
| Crawl: advanced URL cleanup and UTM handling | 301-strips unknown query params (allowlist, keeps gclid) and manages UTM params | PREMIUM (code) |
| Bot blocker for AI crawlers | One-click robots.txt toggles blocking GPTBot, CCBot, Google-Extended (not on multisite) | PREMIUM (code) |
| IndexNow | Pings participating search engines on add, update and delete | PREMIUM (code) |
| Premium redirect manager (manual) | Admin UI to create, search, sort, filter, undo and disable redirects; Apache and NGINX direct-write | PREMIUM (code) |
| Automatic redirects on move or delete | Auto-creates 301 on slug, taxonomy and CPT move; prompts on delete | PREMIUM (code) |
| REGEX redirects | One rule redirecting many URLs sharing a pattern | PREMIUM (code) |
| Redirect types 301, 302, 307, 410, 451 | Permanent, found, temporary, deleted, legal | PREMIUM (code) |
| Redirect CSV import and export plus .htaccess import | Bulk import and export redirects | PREMIUM (code) |
| Redirect sitemap filter | Removes redirected URLs from the XML sitemap | PREMIUM (code) |
| Redirect WP-CLI commands | `wp yoast redirect` list, create, update, delete, has, follow | PREMIUM (code) |
| 404 handling | No dedicated 404 log or monitor; only the 404 title template and redirect prevention | n/a (absent) |
| Site health screen | No dedicated Yoast site-health screen; closest is the Alert centre plus Site Kit | n/a (absent) |

### 4.5 Site settings and admin UX

| Feature | What it does | Tier |
|---|---|---|
| Site basics / General settings | Website name, alternate name, tagline override, title separator, fallback site image, restrict advanced settings, usage tracking, site policies | FREE |
| First-time configuration wizard | Walks through SEO data optimisation, site representation, social profiles and preferences | FREE |
| Content types and Categories and tags templates | Per-type show-in-search toggle plus title, meta, social and schema templates | FREE |
| Author, Date and Format archive settings | Enable or disable each archive type and set its search and social appearance | FREE |
| Alert centre (notification centre) | General to Alerts, Problems (SEO-blocking issues plus fix links) and Notifications | FREE |
| SEO dashboard | Site-wide SEO and readability scores filterable by type and category, plus Site Kit metrics | FREE |
| Task list | Prioritized SEO tasks with time estimates and deep links | FREE (foundational) / PREMIUM (full) |
| Bulk editor (manual table) | Search, filter and inline-edit titles, meta descriptions, keyphrases and social appearance | FREE (code) |
| Bulk editor with AI drafts and product views | AI-drafts titles and descriptions for selected rows, product and category views | PREMIUM / ADD-ON |
| Admin columns | Post and page list SEO and readability scores, cornerstone filter, orphaned filter, text-link counter | FREE (code) |
| Cornerstone and Text link counter | Mark cornerstone content and count internal in and out links per post | FREE |
| Admin bar menu | Frontend admin-bar quick scores, keyphrase and inspector links | FREE |
| Dashboard widgets | Yoast SEO Posts Overview and Wincher Top Keyphrases widgets | FREE (code) |
| SEO workouts | Guided three-step exercises for cornerstones and unlinked content | PREMIUM |
| Orphaned content finder / filter | Lists posts and pages with no inbound internal links | PREMIUM (code) |
| Stale cornerstone content finder | Flags cornerstone posts not updated in six months | PREMIUM (code) |
| Notification centre and usage tracking | Update notifications and opt-in usage tracking | FREE |
| Settings import and export | Export and import Yoast settings via Tools | FREE |

### 4.6 Integrations

| Feature | What it does | Tier |
|---|---|---|
| Google Search Console site verification | Paste the Google verification tag via Site connections | FREE |
| Google Search Console and Analytics via Site Kit | Shows impressions, clicks, CTR, position, organic sessions and top content and queries in the Yoast dashboard | FREE (requires free Site Kit plugin) |
| Site Kit dashboard widgets | Key metrics, top content and top queries matched to WP entities | FREE (requires Site Kit) |
| Elementor integration | Full Yoast sidebar inside Elementor (scores, titles, meta, social, Schema) | FREE (internal linking, related keyphrases, social previews, word forms require PREMIUM) |
| Gutenberg / Block editor integration | Yoast sidebar and meta box, snippet and social previews, internal linking, Schema blocks | FREE (some blocks and sections PREMIUM) |
| Other page builders | Only Elementor is fully integrated; others are not compatible by default | n/a (limited) |
| Semrush integration | Related keyphrases in the editor sidebar | FREE (limited) / PREMIUM |
| Wincher rank-tracker integration | Keyphrase positions with dashboard and competitor view | FREE (limited) / PREMIUM |
| Algolia site-search integration | Ranks internal search by internal-link weight and removes noindexed posts | PREMIUM (code) |
| Enhanced Slack sharing | Enriches Slack link previews | FREE |
| Zapier automated publishing | Auto-shares published content to Twitter, Facebook, Slack and thousands of apps (deprecated as of 20.7; migrate to Webhooks by Zapier) | PREMIUM (deprecated) |
| Schema integrations framework (Schema API) | Third-party plugins inject into the Yoast graph | FREE |
| Schema partners | WP Recipe Maker, Seriously Simple Podcasting, The Events Calendar, EDD | THIRD-PARTY (+ partner plugin) |
| Advanced Custom Fields integration | Uses ACF fields for meta and templates and analyses custom-field content | FREE (+ free glue plugin) |
| Jetpack integration | Upgrades meta tags and social previews, manages SEO settings jointly | FREE |
| Mastodon verification | Verifies the site link on a Mastodon profile and adds it to sameAs | PREMIUM (code) |
| NLWeb / AI discoverability connector | Connects structured data to the agentic web | FREE |
| Shopify review partners (Loox, Judge.me) | Displays reviews for social proof alongside Yoast SEO for Shopify | THIRD-PARTY |
| Multilingual WPML compatibility | Official compatibility for translating SEO data | FREE (requires WPML) |
| Multilingual Polylang, hreflang and language analysis | Analysis in 25+ languages including RTL (word forms and related keyphrases are Premium) | FREE / PREMIUM |
| Custom fields plugin | Adds Yoast-defined custom fields to the content analysis | PREMIUM (code) |
| WooCommerce HPOS compatibility | Declares compatibility with WooCommerce HPOS | PREMIUM (code) |

### 4.7 Local SEO (bundled addon)

| Feature | What it does | Tier |
|---|---|---|
| Yoast Local SEO | Optimises a business for local search and Google Maps | BUNDLED in Premium, also sold as a separate addon historically |
| Business address and business type schema | Converts address and business type to automatic Schema | BUNDLED |
| Opening hours | Enter hours once, outputs live hours and Schema | BUNDLED |
| Geo coordinates, geo sitemap and KML file | Generates a geo sitemap and KML so Maps finds you | BUNDLED |
| Multiple locations management | Each location gets its own page, address, hours, map via CPTs plus a primary location | BUNDLED |
| Embedded Google Maps and contact and location blocks | Embeds a map and builds a contact page with live hours | BUNDLED (maps key) |
| Store locator / store finder | Lets customers search CSV or WordPress locations for the nearest branch | BUNDLED |
| WooCommerce local pickup integration | Integrates local pickup data with WooCommerce | BUNDLED |

### 4.8 News SEO, Video SEO and other addons

| Feature | What it does | Tier |
|---|---|---|
| Yoast News SEO | Gets breaking news indexed fast for Google News and Top Stories | BUNDLED in Premium, separate addon historically |
| News SEO XML News sitemap | Auto-generates a dynamic News sitemap on publish | BUNDLED |
| News SEO NewsArticle schema plus E-E-A-T signals | Auto-adds NewsArticle structured data | BUNDLED |
| News SEO include and exclude per post plus stock tickers | Per-article News sitemap inclusion and finance story links | BUNDLED |
| Yoast Video SEO | Makes embedded videos findable with rich results and thumbnails | BUNDLED in Premium, separate addon historically |
| Video SEO VideoObject schema | Auto-adds VideoObject schema for detected videos | BUNDLED |
| Video SEO XML Video sitemap | Creates and updates video sitemaps automatically | BUNDLED |
| Video SEO custom thumbnails plus OpenGraph previews | Sets and previews thumbnails and adds OpenGraph tags | BUNDLED |
| Video SEO responsive and async performance, host support | Async JS and responsive playback (YouTube, Vimeo, Wistia, VideoPress) | BUNDLED |
| Yoast WooCommerce SEO | Product and category SEO with product schema, sitemaps, breadcrumbs, canonicals and AI titles | ADD-ON (USD 178.80/yr, includes Premium + Local + News + Video + Docs seat) |
| WooCommerce SEO product schema | Product, Offer and Review structured data | ADD-ON |
| WooCommerce SEO product sitemap, breadcrumbs, canonicals | Excludes cart, checkout and filter pages; cleans breadcrumbs; sets canonicals | ADD-ON |
| WooCommerce SEO product content analysis plus AI metadata | Checks short descriptions, alt and identifiers and AI-generates product metadata | ADD-ON |
| Yoast SEO for Shopify | Real-time SEO and readability, AI metadata, automatic product schema, templates for products, collections and blogs | ADD-ON (USD 19/30 days) |
| Yoast SEO Google Docs add-on | Real-time SEO, readability and inclusivity inside Google Docs | ADD-ON (USD 5/mo per seat, one seat with Premium) |
| Yoast SEO AI+ | Complete visibility package: AI Brand Insights plus WooCommerce SEO plus Premium plus Local, News, Video plus Docs seat | ADD-ON (USD 358.80/yr) |

### 4.9 Importer and migration tools

| Feature | What it does | Tier |
|---|---|---|
| Migrate from other SEO plugins | Imports metadata and settings from AIOSEO, Rank Math, SEO Framework, SmartCrawl, Squirrly, WP Meta SEO, WPSEO | FREE (code) |
| Yoast settings import and export | Export and import Yoast's own settings | FREE |
| Indexables / SEO data optimisation migration | Builds the indexable tables after migration or first config | FREE |
| Redirect CSV import and export for migrations | Bulk-migrate legacy redirects via CSV or .htaccess | PREMIUM (code) |
| Premium extension importer | Divi, Elementor and Gutenberg converters plus media importer | PREMIUM (code) |

### 4.10 Multisite, agency and licensing

| Feature | What it does | Tier |
|---|---|---|
| Licensing model | One subscription equals one site or domain; subfolders and subdomains count separately for verification | PREMIUM |
| Multisite network-activated subfolders | One subscription covers the network | PREMIUM |
| Multisite subfolders not network-activated | One subscription per active site | PREMIUM |
| Multisite subdomains | One subscription per active site | PREMIUM |
| Domain mapping | One subscription per domain | PREMIUM |
| Bulk discounts | 5+ to 75+ sites, 5 percent to 40 percent, auto-applied | PREMIUM |
| Agency / reseller (100+ sites) | Custom quote, multi-year deals, priority support, Provisioner API and Composer tooling | PREMIUM / Agency |
| Free support | Help centre, WordPress.org forums, in-plugin Support FAQ, no direct email or chat | FREE |
| Premium support | 24/7 live chat and email | PREMIUM |
| Refund policy | 30-day money-back guarantee on the initial purchase only | PREMIUM |
| Expiry behaviour | Premium files are yours to keep; updates, downloads, support and all AI and morphology features require an active subscription | PREMIUM |
| Subscription terms | One-year recurring term, auto-renews, cancel in MyYoast | PREMIUM |
| Yoast SEO Academy | All SEO courses (free courses remain free) | PREMIUM |

## 5. RankKernel implemented today

All rows are code-verified from `docs/research/raw/rankkernel-current.md` at main commit 606fc95 (version 0.1.0). Test suite at that commit: 82 PHPUnit files, 992 tests, 3460 assertions, green, no database required.

### 5.1 Module registry and default states

Two registries exist. The canonical id list (`ModuleRegistry::MODULES`, `src/Modules/ModuleRegistry.php:24-38`) has 13 ids, but only 6 are backed by a class and directory. The other 7 are reserved placeholders with no code.

| id | Label | Class | Default state | Verdict |
|---|---|---|---|---|
| metadata | Metadata Engine | `MetadataModule` | ON | DONE |
| sitemaps | XML Sitemaps | `SitemapsModule` | ON | DONE |
| schema | Schema (JSON-LD) | `SchemaModule` | ON | DONE |
| breadcrumbs | Breadcrumbs | `BreadcrumbsModule` | ON | DONE |
| redirects | Redirects | `RedirectsModule` | OFF | DONE |
| 404 | 404 Monitor | `MonitorModule` | OFF | DONE |
| importer | Importer | none | seeded ON but never boots | MISSING / PLANNED (roadmap 1.3.1) |
| instant-indexing | Instant Indexing (IndexNow) | none | not seeded | MISSING / PLANNED (roadmap 1.5.1) |
| robots | Robots.txt and .htaccess | none | not seeded | MISSING / PLANNED (roadmap 1.2 and 1.5.7) |
| image-seo | Image SEO | none | not seeded | MISSING / PLANNED (roadmap 1.3.4) |
| gutenberg | Gutenberg Suite | none | not seeded | MISSING / PLANNED (roadmap 1.3.2) |
| ai | AI Suite (BYO key) | none | not seeded | MISSING / PLANNED (roadmap 1.6.1) |
| headless | Headless | none | not seeded | MISSING / PLANNED (roadmap 1.6.2) |

Default enable map seeded at activation: `[metadata, sitemaps, schema, breadcrumbs, importer]`. Because `importer` has no class, only 4 modules actually boot by default. Disabled modules register zero hooks and perform no table work; the only unavoidable per-request cost is one `get_option` for the enable map.

### 5.2 Metadata engine

| Capability | State | Evidence |
|---|---|---|
| Head production, one pass, `wp_head` priority 1 | DONE | `HeadRenderer.php:80-83,135-191` |
| Meta description (payload, then template, then excerpt or taxonomy description) | DONE | `HeadRenderer.php:155-160,268-308` |
| Meta robots (index, noindex, follow, nofollow, noarchive, noimageindex, nosnippet, max-snippet, max-image-preview, max-video-preview) | DONE | `HeadRenderer.php:162-167,317-371` |
| Canonical (omitted on search and 404) | DONE | `HeadRenderer.php:169-174,380-392` |
| Open Graph set (title, description, url, type, image plus dimensions, site_name, locale) | DONE | `HeadRenderer.php:401-487` |
| Twitter cards (card, title, description, image with OG fallback) | DONE | `HeadRenderer.php:495-569` |
| Webmaster verification (Google, Bing, Yandex, Baidu, Pinterest) | DONE | `HeadRenderer.php:574-590` |
| Context memoisation (one meta read per request) | DONE | `Context.php:73-120` |
| Per-post SEO title, description, robots, canonical, OG and Twitter editing UI | MISSING (payload writable only via REST meta or code; only schema metabox exists) | `SchemaMetabox.php` only |
| Social or SERP preview UI | MISSING | none |
| Token grammar | PARTIAL: flat `%%lowercase%%` only, no parameters | `TagsReplacer.php:99-107` |
| Built-in tokens | title, sitename, sep, excerpt, date, author, category, page, currentdate | `TagsReplacer.php:75-85` |
| Custom tokens via filter `rankkernel/tokens` | DONE | `TagsReplacer.php:93,104` |
| rel=prev and rel=next | Deliberately not emitted (Google deprecated) | `HeadRenderer.php:188` |
| Slack-specific tags | Not emitted | `HeadRenderer.php:190` |

### 5.3 Schema

| Capability | State | Evidence |
|---|---|---|
| Single `@graph` JSON-LD, lazily gated per piece | DONE | `Generator.php:40-161`; `SchemaModule.php:216-283` |
| Registered pieces (29) | Organization, Website, WebPage, Breadcrumb, Person, Article, FAQ, HowTo, Product, Recipe, Event, Service, Video, Book, Course, JobPosting, Software, Music, Movie, ClaimReview, Dataset, PodcastEpisode, Carousel, QAPage, ItemList, LocalBusiness, Review, ImageObject, CustomJson | `SchemaModule.php:290-329` |
| Selectable schema types (26) | Article, BlogPosting, NewsArticle, WebPage, FAQPage, HowTo, Product, Recipe, Event, Service, VideoObject, ImageObject, Book, Course, JobPosting, SoftwareApplication, MusicRecording, LocalBusiness, Review, Movie, ClaimReview, Dataset, PodcastEpisode, Carousel, QAPage, ItemList | `SchemaTypes.php:38-65` |
| Automatic default type | posts to BlogPosting, pages and others to Article | `SchemaTypes.php:286-292` |
| Custom JSON | `CustomJsonPiece` from payload `schema.custom` (depth 5, 200 keys) | `MetaPayload.php:509-549` |
| Schema metabox (type, 65 fields, FAQ questions, HowTo steps, JSON import/export) | DONE | `SchemaMetabox.php:57-235,881-1058` |
| Blank-canvas custom schema builder with conditions and variables | MISSING | none |
| Schema templates and display conditions | MISSING | none |
| Term or user schema metabox | MISSING | none |
| Schema preview endpoint | MISSING | none |
| Speakable, About and Mentions | MISSING | none |
| WooCommerce, EDD, SiteNavigationElement, CollectionPage, ProfilePage and similar types | MISSING | none |

### 5.4 Sitemaps

| Capability | State | Evidence |
|---|---|---|
| Sitemap index plus per public post type (attachments excluded) | DONE | `Provider/PostsProvider.php`; `IndexBuilder.php:177-232` |
| Per public taxonomy set | DONE | `TaxonomiesProvider.php` |
| Authors set | DONE | `AuthorsProvider.php` |
| Images inline from featured images | DONE | `PostsProvider.php:18-22` |
| Routing (`sitemap_index.xml`, numbered sets, XSL), query vars, `pre_get_posts` intercept | DONE | `Router.php:150-330` |
| `/sitemap.xml` 301 to index | DONE | `Router.php:236-244` |
| Cache ON by default, object cache plus transient fallback, validator invalidation | DONE | `SitemapCache.php:60-78,381-406` |
| XSL stylesheet bundled and served | DONE | `XslStylesheet.php:28-56` |
| robots.txt sitemap directive, strips stale core line | DONE | `SitemapsModule.php:198-220` |
| Core WP sitemaps disabled with admin notice | DONE | `SitemapsModule.php:154,161,225-233` |
| Exclusions (posts, terms, noindex, password) and author rules | DONE | `SitemapSettings.php:31-50` |
| HTML sitemap | MISSING | none |
| News, Video, product, Local and KML sitemaps | MISSING | none |
| Custom URL sitemap | MISSING | none |

### 5.5 Breadcrumbs, redirects, 404 monitor

| Capability | State | Evidence |
|---|---|---|
| Trail builder (front, blog, search, 404, all archive types, singular, hierarchical, attachment) | DONE | `TrailBuilder.php:71-1170` |
| Accessible renderer with `aria-current` and CSS-variable separator | DONE | `Renderer.php:40-192` |
| Template tags `rankkernel_breadcrumbs` and `rankkernel_get_breadcrumbs` | DONE | `template-tags.php:22-51` |
| Shortcode `[rankkernel_breadcrumbs]` | DONE | `BreadcrumbsModule.php:157-158` |
| Block `rankkernel/breadcrumbs` (server rendered) | DONE | `BreadcrumbsBlock.php:86-200` |
| Breadcrumb settings (separator, home label, toggles, per-type primary taxonomy) | DONE | `BreadcrumbsSettings.php:31-60` |
| Redirect match modes (exact, prefix, contains, suffix, wildcard, regex) | DONE (default OFF) | `Normalizer.php:31`; `Matcher.php:96-462` |
| Redirect codes 301, 302, 307, 410, 451 | DONE | `Normalizer.php:38,45` |
| Redirect cache-first dispatch, loop and chain detection, regex caps | DONE | `Redirector.php:99-180`; `Validator.php:29-487`; `Matcher.php:35,40` |
| Redirect CSV import and export | DONE | `CsvHandler.php:18-50,105-573` |
| Slug-change watcher auto-301 | DONE | `SlugWatcher.php:31,84-193` |
| Redirect hit counter (coalesced, one UPDATE on shutdown) | DONE | `HitCounter.php:17-90` |
| Scheduled redirect activation or expiration | MISSING | none |
| Redirect categories or organising | MISSING | none |
| 404 logging with deduplication by URI hash | DONE (default OFF) | `Logger.php:34,144-170` |
| 404 pruning by age and by count (never blanket truncate) and flood guard | DONE | `Pruner.php:27-109`; `FloodGuard.php:30-120` |
| 404 exclusions (5 comparators) and advanced fields (referer, user agent) | DONE | `Exclusions.php:30`; `MonitorSettings.php:21-100` |
| 404 admin screen with 1-click create redirect | DONE | `NotFoundPage.php:453-484` |
| 404 log export | MISSING | none |
| Bulk set 410 | MISSING | none |

### 5.6 Admin screens, REST, blocks and extension points

| Item | State | Evidence |
|---|---|---|
| RankKernel settings screen (module toggles for all 13 ids, templates, separator, webmaster codes, social fields, breadcrumb settings) | DONE | `AdminMenu.php:155-181`; `SettingsPage.php:57-277` |
| Sitemap screen (general, post types, taxonomies, authors tabs) | DONE | `SitemapSettingsPage.php:31,80-441` |
| Schema screen (site representation, org, per-type defaults, breadcrumbs, author) | DONE | `SchemaSettingsPage.php:145-259` |
| Redirects screen (CRUD, search, status views, sort, CSV) | DONE | `RedirectsPage.php:36-1430` |
| 404 screen (list, filters, delete, settings, 1-click redirect) | DONE | `NotFoundPage.php:39-806` |
| REST `/rankkernel/v1/settings` (GET, POST, `manage_options`) | DONE | `SettingsController.php:53-88` |
| REST `/rankkernel/v1/modules/{id}` (POST, `manage_options`) | DONE | `ModulesController.php:60-78` |
| REST `GET /seo/{id}` and `/preview/{id}` (headless and preview) | MISSING (blueprint future) | `blueprint.md:540-546` |
| Blocks `rankkernel/faq`, `rankkernel/howto` (Schema) | DONE | `FaqBlock.php:114-119`; `HowtoBlock.php:159-164` |
| Block `rankkernel/breadcrumbs` (Breadcrumbs) | DONE | `BreadcrumbsBlock.php:130-136` |
| TOC block, rich-snippet block, related-posts block | MISSING | none |
| Public actions: `rankkernel/module/force_disabled`, `rankkernel/head/after_tags`, `rankkernel/sitemap/ping`, `rankkernel/migration/failed`, `rankkernel/redirect/reentry` | DONE | see 5.7 |
| Public filters: `rankkernel/tokens`, schema suite (`disabled`, `needs_{id}`, `piece/{id}`, `graph`, `breadcrumb_trail`), sitemap suite (`enable_cache`, `entries_per_page`), breadcrumbs suite, `rankkernel/redirect/allowed_hosts` | DONE | see 5.7 |
| Importer (detect, map, batch, dry-run, rollback) | MISSING / PLANNED (roadmap 1.3.1) | no `src/Modules/Importer/` |
| Instant Indexing (IndexNow) | MISSING / PLANNED (roadmap 1.5.1) | no module |
| robots.txt and .htaccess editors | MISSING / PLANNED (roadmap 1.2 and 1.5.7) | sitemap directive only |
| Image SEO | MISSING / PLANNED (roadmap 1.3.4) | no module |
| Gutenberg Suite (editor sidebar) | MISSING / PLANNED (roadmap 1.3.2) | no module |
| AI Suite (BYO key) | MISSING / PLANNED (roadmap 1.6.1) | no module |
| Headless | MISSING / PLANNED (roadmap 1.6.2) | no module |

### 5.7 Meta keys, option keys, tables and autoload

Meta keys:

| Key | Object | Purpose |
|---|---|---|
| `_rankkernel_meta_data` | post | Single JSON payload (title, description, canonical, robots, og, twitter, focus keywords, schema, flags) |
| `_rankkernel_term_data` | term | Same payload shape for terms |
| `_rankkernel_user_prefs` | user | Per-user prefs (permissive schema) |

Option keys: `rankkernel_settings` (autoload yes), `rankkernel_modules` (yes), `rankkernel_sitemap_settings` (yes), `rankkernel_breadcrumbs_settings` (yes), `rankkernel_db_version` (no), `rankkernel_redirects_settings` (no), `rankkernel_404_settings` (no), sitemap and redirect validator options (no), `rankkernel_404_suppressed`, `rankkernel_conflict_notice`. Only the small always-needed options autoload; heavy or volatile payloads are written with `autoload=false`.

Custom tables (2, both created lazily on module enable, not through the migration ledger):

| Table | Purpose |
|---|---|
| `{prefix}rankkernel_redirects` | Redirect rules; UNIQUE(match_type, source_hash), KEY(is_active) |
| `{prefix}rankkernel_404_log` | 404 log; uri_hash unique, KEY(last_accessed) |

Migrations: versioned ledger in `rankkernel_db_version`, one baseline migration only, table creation bypasses the ledger. Known gaps recorded in planning docs: per-context metadata templates, parameterised tokens, snippet preview, internal linking, content analysis, HTML sitemap, single-location Local SEO settings, WooCommerce, News and Video, header and footer injection, hreflang, head cleanup, site-wide analyzer, settings export and import, 404 export, redirect scheduling, and a true blank-canvas schema builder (the current build has a fixed type plus field set plus custom JSON).

## 6. Master parity matrix

One row per distinct feature, deduplicated across vendors where the feature is the same concept. Competitor cells use yes, no or a short qualifier. The RankKernel column uses the verdict vocabulary, with a brief note for PARTIAL. Evidence cites the raw research section or a RankKernel `file:line`. Grouped by area for navigation; every area table uses the same columns.

### 6.1 Content and on-page analysis

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Focus keyword (primary) | Content analysis | yes | yes | yes | yes | PLANNED (payload field exists, no editor or analysis) | PLANNED | `rankkernel-current.md` 2.14; `feature-gap-research-gate.md` 15 |
| Multiple or secondary focus keywords | Content analysis | yes (up to 5) | yes | no (1) | yes (up to 5) | PLANNED (unlimited planned free) | PLANNED | `feature-matrix.md:79-87` |
| Unlimited focus keyphrases | Content analysis | yes | yes | no | no | PLANNED (blueprint promises unlimited, not built) | PLANNED | `feature-matrix.md:79-87` |
| Content analysis engine | Content analysis | yes | yes | yes | yes | PLANNED (gate Phase 3, content analysis) | PLANNED | `feature-gap-research-gate.md` 15 |
| On-page SEO tests (30+) | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| SEO score | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| Readability analysis | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| Keyphrase density test | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in introduction or first 10 percent | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in SEO title | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in meta description | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in URL or slug | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in subheadings | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase in image alt | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Keyphrase uniqueness or duplicate check | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 11 |
| Title readability tests (power word, number, sentiment) | Content analysis | yes | yes | no | no | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| Content readability tests (TOC, short paragraphs, media) | Content analysis | yes | yes | yes | yes | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| Keyphrase distribution | Content analysis | no | yes | no | yes | MISSING | MISSING | `rankmath-web.md` 3.3; `yoast-web.md` 1.1 |
| Keyphrase synonyms | Content analysis | no | yes | no | yes | MISSING | MISSING | `yoast-web.md` 1.1 |
| Related or multi keyphrase analysis | Content analysis | yes (5) | yes | no | yes (4 related) | PLANNED (multi keyphrase planned free) | PLANNED | `feature-gap-research-gate.md` 12 |
| Word-form recognition / morphology | Content analysis | no | no | no | yes | MISSING | MISSING | `yoast-web.md` 1.1 |
| Word complexity check | Content analysis | no | no | no | yes | MISSING | MISSING | `yoast-web.md` 1.2 |
| Inclusive language analysis | Content analysis | no | no | no | yes | MISSING | MISSING | `yoast-web.md` 1.3 |
| Cornerstone or pillar content marking | Content analysis | yes (pillar) | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` meta keys; `yoast-code-a.md` |
| Stale cornerstone finder | Content analysis | no | no | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| Prominent words | Content analysis | no | yes | no | yes | MISSING | MISSING | `yoast-code-b.md`; `yoast-code-c.md` |
| Estimated reading time metric and block | Content analysis | no | no | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| Product-specific content analysis tests | Content analysis | yes | yes | no | yes (add-on) | MISSING | MISSING | `rankmath-pro-code.md` 17; `yoast-web.md` 1.3 |
| Internal link suggestions | Internal linking | yes | yes | no | yes | MISSING | MISSING | `rankmath-web.md` 1.3; `yoast-code-c.md` |
| Internal link counter or counts | Internal linking | yes | yes | yes | yes | PLANNED (link index is a Phase 3 architecture decision) | PLANNED | `feature-gap-research-gate.md` 12, 23 |
| Orphan page detection | Internal linking | no | yes | no | yes | PLANNED (gate Phase 3, orphan report first) | PLANNED | `feature-gap-research-gate.md` 15 |
| Nofollow internal link detection | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 1.3 |
| Redirected internal link detection | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 1.3 |
| Internal-link HTTP status audit or broken link checker | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 11 |
| Automated keyword linking or keyword maps | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 11 |
| Bulk link update with rollback | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 11 |
| Related posts block and shortcode | Internal linking | no | yes | no | yes (related links block) | MISSING | MISSING | `rankmath-pro-code.md` 11; `yoast-code-c.md` |
| Affiliate link prefix handling | Internal linking | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Competitor SEO analysis | Analysis / competitor | no | yes | no | no | EXTERNAL (RankMath.com SEO Analyzer API) | EXTERNAL | `rankmath-pro-code.md` 9 |
| Side-by-side SEO comparison | Analysis / competitor | no | yes | no | no | EXTERNAL (RankMath.com SEO Analyzer API) | EXTERNAL | `rankmath-web.md` 1.1 |
| Search intent analysis | Analysis / competitor | no | yes | no | no | EXTERNAL (Rank Math Content AI) | EXTERNAL | `rankmath-pro-code.md` 0 |
| Google Trends data | Analysis / keyword data | no | yes | no | no | EXTERNAL (Google Trends via Rank Math) | EXTERNAL | `rankmath-pro-code.md` 0 |
| Semrush keyword data | Analysis / keyword data | no (integration) | no (integration) | yes (limited) | yes | EXTERNAL (Semrush) | EXTERNAL | `yoast-web.md` 1.4 |
| Wincher rank tracking | Analysis / keyword data | no | no (own tracker) | yes (limited) | yes | EXTERNAL (Wincher or Rank Math quota) | EXTERNAL | `yoast-web.md` 1.4 |
| AI title and description generation | AI | no | yes (Content AI) | no | yes | PLANNED (BYO-key AI suite, roadmap 1.6.1) | PLANNED | `ROADMAP.md` |
| AI optimize or fix assessments | AI | no | yes (Content AI) | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| AI summarize / key takeaways | AI | no | yes (Content AI) | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| AI content planner | AI | no | yes (Content AI) | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| AI image alt generation | AI | no | yes (Content AI) | no | yes | PLANNED (part of BYO-key AI suite) | PLANNED | `ROADMAP.md` |
| Generative long-form AI writing | AI | no | yes (Content AI) | no | no (base) | EXTERNAL (paid AI provider subscription) | EXTERNAL | `rankmath-free-code.md` external HTTP |
| AI brand visibility tracking | AI | yes (beta, metered) | yes | no | yes (AI+ add-on) | EXTERNAL (paid AI visibility service) | EXTERNAL | `rankmath-free-code.md` AI Visibility; `yoast-web.md` 1.4 |
| MCP or agent tooling | AI | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5.2 |

### 6.2 Metadata and head control

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Per-post SEO title editing UI | Metadata | yes | yes | yes | yes | MISSING (payload writable only via REST meta; no admin field) | MISSING | `rankkernel-current.md` 2.3 |
| Per-post meta description editing UI | Metadata | yes | yes | yes | yes | MISSING (same) | MISSING | `rankkernel-current.md` 2.3 |
| Per-post robots editing UI | Metadata | yes | yes | yes | yes | MISSING (same) | MISSING | `rankkernel-current.md` 2.3 |
| Per-post canonical override | Metadata | yes | yes | yes | yes | PARTIAL (payload field via REST, no UI) | PARTIAL | `rankkernel-current.md` 2.3, Payload |
| Per-post Open Graph and Twitter editing UI | Metadata | yes | yes | yes | yes | MISSING (REST or code only) | MISSING | `rankkernel-current.md` 2.3 |
| Snippet or SERP preview | Metadata | yes | yes | yes | yes | PLANNED (gate P1, snippet preview with pixel guidance) | PLANNED | `rankmath-web.md` 2.1; `yoast-web.md` 2 |
| Social network previews | Metadata | yes | yes | no | yes | PLANNED (gate P1, social template layer and previews) | PLANNED | `rankmath-web.md` 2.4; `yoast-web.md` 2 |
| Bulk edit titles and descriptions | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 2.1; `yoast-web.md` 5 |
| Global title and description templates | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.10 |
| Per-post-type title or description templates | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `feature-gap-research-gate.md` 2 |
| Per-taxonomy title or description templates | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `feature-gap-research-gate.md` 2 |
| Homepage title, description and robots | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `rankkernel-current.md` 2.10 |
| Author archive title, description and robots | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `rankkernel-current.md` 2.10 |
| Date archive title, description and robots | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `rankmath-web.md` 2.5; `yoast-web.md` 5 |
| Search results title template | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `rankmath-web.md` 2.5; `yoast-web.md` 4 |
| 404 title template | Metadata | yes | yes | yes | yes | PLANNED (gate Phase 2.6, head engine parity) | PLANNED | `rankmath-web.md` 2.5; `yoast-web.md` 4 |
| Token or variable system | Metadata | yes (about 53) | yes | yes (50+) | yes | PARTIAL (flat `%%lowercase%%`, 9 tokens) | PARTIAL | `rankkernel-current.md` 2.3, Appendix |
| Parameterised or formatted tokens | Metadata | yes | yes | yes | yes | MISSING | MISSING | `feature-gap-research-gate.md` 11 |
| Random word variable | Metadata | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Image alt or title variables | Metadata | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Custom variables via code filter | Metadata | yes | yes | yes | yes | DONE (`rankkernel/tokens`) | DONE | `rankkernel-current.md` 2.13 |
| Title separator | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.10 |
| Capitalize titles | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` titles keys |
| Rewrite titles | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` titles keys |
| Strip category base | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` links keys |
| Redirect attachments to parent | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 2.2; `yoast-web.md` 4 |
| Auto canonical output | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Custom canonical per object | Metadata | yes | yes | yes | yes | PARTIAL (payload via REST, no UI) | PARTIAL | `rankkernel-current.md` 2.3 |
| Represent site as Person or Company | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.10, 2.5 |
| Knowledge Graph meta output | Metadata | yes | yes | yes | yes | PARTIAL (Organisation and Person nodes in graph, no separate meta tags) | PARTIAL | `rankkernel-current.md` 2.5 |
| Robots meta index, noindex, follow, nofollow | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Advanced robots directives (noarchive, nosnippet, max-*) | Metadata | yes | yes | yes | yes | DONE (emitted; per-object UI missing) | DONE | `rankkernel-current.md` 2.3 |
| Robots defaults per type, taxonomy, author and date | Metadata | yes | yes | yes | yes | PARTIAL (global and per-payload only) | PARTIAL | `rankkernel-current.md` 2.3, 2.10 |
| Noindex empty taxonomies | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` titles keys |
| Noindex search results | Metadata | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Noindex subpages and paginated single pages | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` titles keys |
| Noindex password-protected pages | Metadata | no | yes | yes | yes | MISSING | MISSING | `rankmath-pro-code.md` PRO list 86 |
| rel=next and rel=prev tags | Metadata | yes | yes | yes | yes | N/A (Google deprecated in 2019, deliberately not emitted) | N/A | `rankkernel-current.md` 2.3 |
| Open Graph output (full set) | Social | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Homepage Open Graph | Social | yes | yes | yes | yes | DONE (homepage context handled) | DONE | `rankkernel-current.md` 2.3 |
| Twitter Card output | Social | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Default Twitter card type | Social | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.3 |
| Twitter username | Social | yes | yes | yes | yes | PARTIAL (social fields stored, emission not confirmed) | PARTIAL | `rankkernel-current.md` 2.10 |
| Additional social profiles | Social | yes | yes | yes | yes | PARTIAL (fields stored, emission not confirmed) | PARTIAL | `rankkernel-current.md` 2.10 |
| Default Open Graph share image | Social | yes | yes | yes | yes | PARTIAL (per-post fallback chain, global default unconfirmed) | PARTIAL | `rankkernel-current.md` 2.4 |
| Social image overlay icons | Social | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 2.4 |
| Watermarked social images | Social | no | yes | no | no | N/A (marketing value only) | N/A | `rankkernel-current.md` 1.3 |
| Slack enhanced sharing | Social | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 2.4; `yoast-web.md` 2 |
| Facebook thumbnail flush on update | Social | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 2.4 |
| Webmaster verification tags | Metadata | yes | yes | yes | yes | DONE (Google, Bing, Yandex, Baidu, Pinterest) | DONE | `rankkernel-current.md` 2.3 |
| Norton Safe Web verification | Metadata | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 2.6 |
| Custom verification or head meta tags | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 2.1 |
| Header and footer code injection | Metadata | no | yes | no | no | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 12 |
| hreflang passthrough | Metadata | yes (passthrough) | yes (passthrough) | yes (via plugin) | yes (via plugin) | PLANNED (gate Phase 5, passthrough plus filters) | PLANNED | `feature-gap-research-gate.md` 12 |
| llms.txt generator | Metadata / crawl | yes | yes (advanced) | yes | yes | PLANNED (gate Phase 2.6, virtual generator) | PLANNED | `feature-gap-research-gate.md` 10 |
| AI crawler control (bot blocker) | Metadata / crawl | no | no | no | yes | PLANNED (gate Phase 2.6, free AI crawler group editor) | PLANNED | `yoast-web.md` 4; `feature-gap-research-gate.md` 10 |
| Custom fields in the meta box | Metadata | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` titles keys |
| Search appearance per type and archive | Metadata | yes | yes | yes | yes | MISSING | MISSING | `yoast-code-c.md` |

### 6.3 Schema and structured data

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| JSON-LD `@graph` | Schema | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema generator metabox | Schema | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 5.3 |
| Default schema type per post type | Schema | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5, 2.10 |
| Multiple schema types stacked per page | Schema | yes | yes | yes | yes | PARTIAL (automatic pieces combine; no user stacking of manual types) | PARTIAL | `rankkernel-current.md` 2.5 |
| Custom schema builder (blank canvas) | Schema | no | yes | no | no | PARTIAL (fixed type plus field set plus custom JSON, no blank canvas) | PARTIAL | `rankkernel-current.md` Appendix |
| Custom JSON-LD input | Schema | no | yes | no (Schema API code) | no | DONE (CustomJson piece) | DONE | `rankkernel-current.md` 2.5 |
| Schema templates (reusable) | Schema | yes | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Schema display conditions | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Import schema from URL, HTML or JSON-LD | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Schema validation via Google | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Schema search in generator | Schema | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 3.1 |
| ACF fields as schema variables | Schema | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 3.1 |
| Schema preview endpoint | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Term or taxonomy schema | Schema | no | yes | yes | yes | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Schema shortcodes | Schema | yes | yes | yes | yes | MISSING | MISSING | `rankmath-free-code.md` shortcodes |
| Schema extension API (filters) | Schema | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.13 |
| Partner schema integrations | Schema | EDD yes | yes | partner plugins | partner plugins | MISSING | MISSING | `yoast-web.md` 3; `rankmath-free-code.md` |
| FAQ Gutenberg block | Schema / block | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.12 |
| HowTo Gutenberg block | Schema / block | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.12 |
| Table of Contents block | Schema / block | yes | yes | no | yes | MISSING | MISSING | `rankmath-free-code.md` blocks; `yoast-code-c.md` |
| Rich-snippet block (embed template by id) | Schema / block | yes | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Local Business schema | Schema | yes | yes | no (add-on) | yes (add-on) | DONE (LocalBusiness piece) | DONE | `rankkernel-current.md` 2.5 |
| 193 LocalBusiness types UI | Schema | yes | yes | no (add-on) | yes (add-on) | MISSING | MISSING | `rankmath-web.md` 6.2 |
| Automatic QandA schema for bbPress | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 5 |
| Video autodetect for schema | Schema | no | yes | no | yes (add-on) | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Generate video schema for old posts | Schema | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Schema type: Article, BlogPosting, NewsArticle | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: WebPage | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: WebSite | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Organization | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Person | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: BreadcrumbList | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: ImageObject | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: FAQPage | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: HowTo | Schema type | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Product | Schema type | yes | yes | no (add-on) | yes (add-on) | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Recipe | Schema type | yes | yes | partner plugin | partner plugin | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Event | Schema type | yes | yes | partner plugin | partner plugin | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Service | Schema type | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: VideoObject | Schema type | yes | yes | no (add-on) | yes (add-on) | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Book | Schema type | yes | yes | no (Schema API) | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Course | Schema type | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: JobPosting | Schema type | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: SoftwareApplication | Schema type | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: MusicRecording | Schema type | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: LocalBusiness | Schema type | yes | yes | no (add-on) | yes (add-on) | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Review | Schema type | yes | yes (legacy free) | no (add-on) | yes (add-on) | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Movie | Schema type | no | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: ClaimReview or FactCheck | Schema type | no | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Dataset | Schema type | no | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: PodcastEpisode | Schema type | no | yes | partner plugin | partner plugin | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Carousel | Schema type | no | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: QAPage | Schema type | no | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: ItemList | Schema type | no | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.5 |
| Schema type: Speakable | Schema type | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 3.3 |
| Schema type: About and Mentions | Schema type | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 3.3 |
| Schema page subtypes (CollectionPage, ProfilePage, ItemPage, AboutPage, ContactPage, MedicalWebPage, CheckoutPage, RealEstateListing, SearchResultsPage) | Schema type | yes (some) | yes | yes (about 12) | yes | MISSING | MISSING | `yoast-code-a.md`; `yoast-code-b.md` |
| Schema type: SiteNavigationElement | Schema type | yes | yes | no | no | PARTIAL (Website SearchAction present, SiteNavigationElement absent) | PARTIAL | `rankkernel-current.md` 2.5 |
| WooCommerce product schema | Schema type | yes | yes | no (add-on) | yes (add-on) | MISSING | MISSING | `rankmath-free-code.md` WooCommerce; `yoast-web.md` 8 |
| EDD product schema | Schema type | yes | yes | no | yes (EDD add-on) | MISSING | MISSING | `rankmath-free-code.md` |
| Schema type: Restaurant | Schema type | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 3.2 |
| Schema type: CollectionPage (standalone) | Schema type | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 3.2 |

### 6.4 Technical SEO, crawl and indexing

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| XML sitemap index | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Per-post-type sitemaps | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Per-taxonomy sitemaps | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Author sitemap | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Image entries in sitemap | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Sitemap XSL stylesheet | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Sitemap caching | Sitemap | yes (file) | yes | yes (off by default) | yes | DONE (cache ON by default) | DONE | `rankkernel-current.md` 2.6 |
| Sitemap exclusion rules | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Sitemap directive in robots.txt | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Core sitemap takeover | Sitemap | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.6 |
| Search engine ping or submission | Sitemap | yes | yes | no (removed in v22) | no (removed in v22) | DONE (robots directive plus cache-warm hook, no real engine ping, matching current competitor behaviour) | DONE | `rankkernel-current.md` 2.6 |
| HTML sitemap | Sitemap | yes | yes | no | no | PLANNED (gate P1) | PLANNED | `feature-gap-research-gate.md` 16 |
| KML or geo sitemap | Sitemap | yes | yes | no (Local add-on) | yes (Local add-on) | PLANNED (Phase 6 single-location Local SEO) | PLANNED | `feature-gap-research-gate.md` 15 |
| Custom sitemap URLs | Sitemap | yes (child function) | yes | no | no | MISSING | MISSING | `rankmath-web.md` 4.1 |
| News sitemap | Sitemap | no | yes | no (News add-on) | yes (News add-on) | PLANNED (Phase 6) | PLANNED | `feature-gap-research-gate.md` 15 |
| Video sitemap | Sitemap | no | yes | no (Video add-on) | yes (Video add-on) | PLANNED (Phase 6) | PLANNED | `feature-gap-research-gate.md` 15 |
| Product sitemap | Sitemap | yes | yes | no (Woo add-on) | yes (Woo add-on) | PLANNED (Phase 6, WooCommerce free parity) | PLANNED | `feature-gap-research-gate.md` 15 |
| Local sitemap | Sitemap | yes | yes | no (Local add-on) | yes (Local add-on) | PLANNED (Phase 6) | PLANNED | `feature-gap-research-gate.md` 15 |
| robots.txt editor | Crawl | yes | yes | yes | yes | PARTIAL (sitemap directive only; editor PLANNED roadmap 1.2) | PARTIAL | `rankkernel-current.md` 2.6; `ROADMAP.md` |
| robots.txt validator or tester | Crawl | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` robots-txt |
| .htaccess editor | Crawl | yes | yes | yes | yes | PLANNED (roadmap 1.5.7; gate recommends omit in v1, decision open) | PLANNED | `ROADMAP.md`; `feature-gap-research-gate.md` 8 |
| Redirect manager | Redirects | yes | yes (advanced) | no | yes | DONE (default OFF) | DONE | `rankkernel-current.md` 2.8 |
| Redirect codes 301, 302, 307, 410, 451 | Redirects | yes | yes | no | yes | DONE | DONE | `rankkernel-current.md` 2.8 |
| Regex redirects | Redirects | yes | yes | no | yes | DONE | DONE | `rankkernel-current.md` 2.8 |
| Redirect match types | Redirects | yes | yes | no | yes (plain and regex) | DONE (exact, prefix, contains, suffix, wildcard, regex) | DONE | `rankkernel-current.md` 2.8 |
| Multiple sources per redirect | Redirects | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 4.3 |
| Auto redirect on slug change | Redirects | yes | yes | no | yes | DONE | DONE | `rankkernel-current.md` 2.8 |
| Redirect hit statistics | Redirects | yes | yes | no | no | DONE | DONE | `rankkernel-current.md` 2.8 |
| Redirect CSV import and export | Redirects | no (server export only) | yes | no | yes | DONE | DONE | `rankkernel-current.md` 2.8 |
| Redirect .htaccess sync | Redirects | no | yes | no | yes | N/A (server coupling and lockout risk, gate recommends omit) | N/A | `feature-gap-research-gate.md` 8 |
| Scheduled redirect activation and expiration | Redirects | no | yes | no | no | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| Redirect organising or categories | Redirects | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 2 |
| Parameterised URL redirects | Redirects | no | yes | no | partial | PARTIAL (preserve_query setting; parameter matching not built) | PARTIAL | `rankkernel-current.md` 2.8 |
| Redirect debugger | Redirects | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` redirections |
| Redirect fallback behaviour | Redirects | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` redirections |
| 404 monitor | 404 | yes (simple) | yes (advanced) | no | no | DONE (default OFF) | DONE | `rankkernel-current.md` 2.9 |
| 404 advanced fields (referer, user agent) | 404 | no | yes | no | no | DONE (advanced_fields setting) | DONE | `rankkernel-current.md` 2.9 |
| 404 log export | 404 | no | yes | no | no | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| 404 grouping by hits | 404 | yes | yes | no | no | DONE (dedupe by URI hash plus hits) | DONE | `rankkernel-current.md` 2.9 |
| 404 bulk set 410 | 404 | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 4.4 |
| 404 to redirect workflow | 404 | yes | yes | no | no | DONE (1-click create redirect) | DONE | `rankkernel-current.md` 2.9 |
| Instant Indexing (IndexNow) | Indexing | yes | yes | no | yes | PLANNED (roadmap 1.5.1) | PLANNED | `ROADMAP.md` |
| Google Indexing API | Indexing | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 4.5 |
| Image auto ALT | Image SEO | yes | yes | no | no | PLANNED (roadmap 1.3.4) | PLANNED | `ROADMAP.md` |
| Image auto title | Image SEO | yes | yes | no | no | PLANNED (roadmap 1.3.4) | PLANNED | `ROADMAP.md` |
| Image ALT and title variable library | Image SEO | yes | yes | no | no | PLANNED (roadmap 1.3.4) | PLANNED | `ROADMAP.md` |
| Image caption autofill | Image SEO | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 10 |
| Image description autofill | Image SEO | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 10 |
| Image casing conversion | Image SEO | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 10 |
| Avatar ALT | Image SEO | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 10 |
| Image find and replace | Image SEO | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 10 |
| Media library SEO filters | Image SEO | yes | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Head cleanup (generator, shortlink, RSD, oEmbed, emojis, pingback, powered-by) | Crawl | partial | yes | no | yes | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| Feed controls | Crawl | yes (RSS) | yes | no | yes | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| Internal search cleanup | Crawl | yes | yes | no | yes | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| Advanced URL cleanup and UTM handling | Crawl | no | yes | no | yes | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| RSS optimization (content before and after feed) | Crawl | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 8; `yoast-web.md` 4 |
| Indexables table for fast meta output | Performance | no | no | yes | yes | N/A (RankKernel uses a single-key meta row by design) | N/A | `rankkernel-current.md` 1.1 |
| Site-wide SEO analyzer | Technical | yes | yes | partial | partial | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| Breadcrumbs output plus BreadcrumbList | Technical | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.7 |
| Breadcrumb primary category or taxonomy control | Technical | yes | yes | yes | yes | PARTIAL (per-post-type primary taxonomy setting, no per-post primary term UI) | PARTIAL | `rankkernel-current.md` 2.7 |
| Freshness or modified-date lock | Technical | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` meta keys |

### 6.5 Analytics and reporting

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Google Search Console integration | Analytics | yes | yes | yes (via Site Kit) | yes (via Site Kit) | PLANNED (Site Kit bridge; analytics deferred in the roadmap) | PLANNED | `ROADMAP.md` |
| Google Analytics 4 integration | Analytics | no | yes | yes (via Site Kit) | yes (via Site Kit) | PLANNED (Site Kit bridge) | PLANNED | `ROADMAP.md` |
| Analytics dashboard | Analytics | no | yes | yes (via Site Kit) | yes (via Site Kit) | PLANNED (Site Kit bridge) | PLANNED | `ROADMAP.md` |
| SEO dashboard with site-wide scores | Analytics | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 8; `yoast-web.md` 5 |
| Traffic source filtering | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| AI search traffic tracker | Analytics | no | yes | no | no | EXTERNAL (paid AI traffic service via Rank Math) | EXTERNAL | `rankmath-pro-code.md` 7 |
| Keyword report and positions | Analytics | no | yes | yes (via Wincher) | yes (via Wincher) | PLANNED (read-only GSC via Site Kit bridge) | PLANNED | `rankmath-web.md` 5 |
| Site analytics table | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Post analytics report | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Winning and losing keywords | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Winning and losing posts | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Advanced content SEO overview | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Ranking keywords per post | Analytics | no | yes | no | no | PLANNED (read-only GSC via Site Kit bridge) | PLANNED | `rankmath-pro-code.md` 7 |
| Keyword and post position history | Analytics | no | yes | no | no | PLANNED (read-only GSC via Site Kit bridge) | PLANNED | `rankmath-web.md` 5 |
| Single post SEO report | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Single post performance badges | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| PageSpeed tracking per URL | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Google AdSense earnings history | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Country-filtered GSC and GA data | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Google Algorithm Updates timeline | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Keyword rank tracker | Analytics | no | yes | yes (via Wincher) | yes (via Wincher) | EXTERNAL (Rank Math quota or Wincher subscription) | EXTERNAL | `rankmath-pro-code.md` 7 |
| Google Index Status (URL Inspection API) | Analytics | yes (limited) | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` Analytics |
| SEO email reports | Analytics | yes (limited) | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5 |
| Email report content and frequency | Analytics | yes (limited) | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| White-labelled email reports | Analytics | no | no (Business) | no | no | N/A (agency reporting, not a free-plugin concern) | N/A | `rankmath-web.md` 10 |
| Client management for client sites | Analytics | no | no (Business) | no | no | N/A (agency multi-client feature) | N/A | `rankmath-web.md` 10 |
| Google data retention and fetch-frequency limits | Analytics | yes | yes | no | no | N/A (paid plan limits, does not apply to a free plugin) | N/A | `rankmath-web.md` 10.2 |
| Analytics dashboard widgets | Analytics | yes | yes | yes (via Site Kit) | yes (via Site Kit) | MISSING | MISSING | `rankmath-web.md` 8; `yoast-code-a.md` |
| Anonymise IP addresses in GA | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Self-hosted Google Analytics JS | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |
| Exclude logged-in users from GA | Analytics | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 7 |

### 6.6 Verticals (WooCommerce, Local, News, Video, Podcast, EDD, Stories, AMP, bbPress, BuddyPress)

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| WooCommerce: remove product base | WooCommerce | yes | yes | no | add-on | PLANNED (Phase 6, WooCommerce free parity) | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: remove product category base | WooCommerce | yes | yes | no | add-on | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: remove parent slugs | WooCommerce | yes | yes | no | add-on | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: remove generator tag | WooCommerce | yes | yes | no | add-on | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: remove shop-archive schema | WooCommerce | yes | yes | no | add-on | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: product brand taxonomy | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-web.md` 6.1 |
| WooCommerce: product schema fields (brand, price, currency, availability) | WooCommerce | yes | yes | no | add-on | PARTIAL (generic Product piece ships, WooCommerce wiring absent) | PARTIAL | `rankkernel-current.md` 2.5 |
| WooCommerce: gallery images in Open Graph and sitemap | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-web.md` 6.1 |
| WooCommerce: exclude hidden products from sitemap | WooCommerce | yes | yes | no | add-on | PLANNED | PLANNED | `feature-gap-research-gate.md` 15 |
| WooCommerce: noindex hidden products | WooCommerce | no | yes | no | add-on | PLANNED (Woo parity) | PLANNED | `feature-gap-research-gate.md` 12 |
| WooCommerce: GTIN and MPN identifiers | WooCommerce | no | yes | no | add-on | MISSING | MISSING | `rankmath-pro-code.md` 17 |
| WooCommerce: variation product schema | WooCommerce | no | yes | no | add-on | MISSING | MISSING | `rankmath-pro-code.md` 17 |
| WooCommerce: product content analysis tests | WooCommerce | no | yes | no | add-on | MISSING | MISSING | `rankmath-pro-code.md` 17 |
| WooCommerce: product variables | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-web.md` 6.1 |
| WooCommerce: short description in analysis | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-web.md` 6.1 |
| WooCommerce: noindex cart, checkout and account pages | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-free-code.md` WooCommerce |
| WooCommerce: product Open Graph price | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-free-code.md` WooCommerce |
| WooCommerce: duplicate-content handling | WooCommerce | yes | yes | no | add-on | MISSING | MISSING | `rankmath-web.md` 6.1 |
| WooCommerce: GTIN migration tool | WooCommerce | no | yes | no | add-on | MISSING | MISSING | `rankmath-pro-code.md` 17 |
| LocalBusiness schema for a single location | Local SEO | yes | yes | no (Local add-on) | yes (Local add-on) | PARTIAL (LocalBusiness piece ships, location settings absent) | PARTIAL | `rankkernel-current.md` 2.5, Part 3 gap 7 |
| Local business settings (name, address, phone, hours, geo, map) | Local SEO | yes | yes | no (Local add-on) | yes (Local add-on) | PLANNED (Phase 6, single location) | PLANNED | `feature-gap-research-gate.md` 15 |
| Local business type (193 subtypes) | Local SEO | yes | yes | no (Local add-on) | yes (Local add-on) | MISSING | MISSING | `rankmath-web.md` 6.2 |
| Local contact-info shortcode | Local SEO | yes | yes | no (Local add-on) | yes (Local add-on) | MISSING | MISSING | `rankmath-web.md` 6.2 |
| Local multiple locations | Local SEO | no | yes | no (Local add-on) | yes (Local add-on) | MISSING (deferred) | MISSING | `ROADMAP.md` |
| Local map, store locator or GPS | Local SEO | yes (map) | yes | no (Local add-on) | yes (Local add-on) | EXTERNAL (Google Maps JavaScript and Embed API key, billing-enabled) | EXTERNAL | `rankmath-pro-code.md` 12 |
| Local Business Gutenberg block | Local SEO | no | yes | no (Local add-on) | yes (Local add-on) | MISSING | MISSING | `rankmath-pro-code.md` 12 |
| News sitemap | News | no | yes | no (News add-on) | yes (News add-on) | PLANNED (Phase 6) | PLANNED | `feature-gap-research-gate.md` 15 |
| NewsArticle schema | News | yes | yes | no (News add-on) | yes (News add-on) | DONE (Article piece supports NewsArticle) | DONE | `rankkernel-current.md` 2.5 |
| Video sitemap | Video | no | yes | no (Video add-on) | yes (Video add-on) | PLANNED (Phase 6) | PLANNED | `feature-gap-research-gate.md` 15 |
| VideoObject schema | Video | yes | yes | no (Video add-on) | yes (Video add-on) | DONE | DONE | `rankkernel-current.md` 2.5 |
| Video autodetect for schema | Video | no | yes | no (Video add-on) | yes (Video add-on) | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Podcast module, schema and RSS feed | Podcast | no | yes | no (partner plugin) | no (partner plugin) | MISSING | MISSING | `rankmath-pro-code.md` 16 |
| Media RSS (MRSS) and Yandex video OpenGraph | Video | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 13 |
| Easy Digital Downloads schema | EDD | yes | yes | no | yes (EDD add-on) | MISSING | MISSING | `rankmath-free-code.md`; `yoast-code-c.md` |
| Complete EDD SEO | EDD | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` PRO list 43 |
| Google Web Stories module | Stories | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` web-stories |
| AMP module | AMP | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md`; `yoast-code-c.md` |
| bbPress post-type SEO (forums, topics, replies) | bbPress | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md`; `yoast-code-c.md` |
| Automatic QandA schema for bbPress | bbPress | no | yes | no | no | PARTIAL (QAPage piece ships, bbPress wiring absent) | PARTIAL | `rankkernel-current.md` 2.5 |
| BuddyPress schema variables | BuddyPress | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` buddypress |

### 6.7 Integrations and ecosystem

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Elementor integration | Integrations | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 7; `yoast-code-c.md` |
| Elementor breadcrumbs widget and accordion to FAQ | Integrations | no | yes | no | yes | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Divi integration and accordion to FAQ | Integrations | yes | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Gutenberg block editor sidebar | Integrations | yes | yes | yes | yes | PARTIAL (three server-rendered blocks exist, no editor sidebar or analysis panel) | PARTIAL | `rankkernel-current.md` 2.12 |
| Classic editor meta box | Integrations | yes | yes | yes | yes | PARTIAL (schema metabox only, no SEO or social fields) | PARTIAL | `rankkernel-current.md` 2.3 |
| Advanced Custom Fields (ACF) integration | Integrations | yes | yes | yes (glue plugin) | yes (glue plugin) | MISSING | MISSING | `rankmath-free-code.md` acf; `yoast-code-c.md` |
| Page builder compatibility (Divi, Bricks, Betheme, Brizy, SiteOrigin and more) | Integrations | yes | yes | Elementor only | Elementor only | MISSING | MISSING | `rankmath-web.md` 7; `yoast-web.md` 6 |
| WPML, Polylang and TranslatePress compatibility | Integrations | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 7; `yoast-web.md` 6 |
| Headless REST head endpoint | Integrations | yes | yes | yes | yes | PLANNED (roadmap 1.6.2) | PLANNED | `ROADMAP.md` |
| WPGraphQL SEO fields | Integrations | no | no | no | no | PLANNED (roadmap 1.6.2) | PLANNED | `ROADMAP.md` |
| Google Site Kit integration | Integrations | no | no | yes | yes | MISSING | MISSING | `yoast-code-c.md` |
| Semrush integration | Integrations | no | no | yes (limited) | yes | EXTERNAL (Semrush) | EXTERNAL | `yoast-code-c.md` |
| Wincher integration | Integrations | no | no | yes (limited) | yes | EXTERNAL (Wincher) | EXTERNAL | `yoast-code-c.md` |
| Algolia site-search integration | Integrations | no | no | no | yes | EXTERNAL (Algolia subscription) | EXTERNAL | `yoast-code-c.md` |
| Zapier automated publishing | Integrations | no | no | no | yes (deprecated) | N/A (deprecated in Yoast 20.7, external accounts) | N/A | `yoast-code.md` Unverified |
| Mastodon verification | Integrations | no | no | no | yes | MISSING | MISSING | `yoast-code-c.md` |
| NLWeb or AI discoverability connector | Integrations | no | no | yes | yes | MISSING | MISSING | `yoast-web.md` 6 |
| Custom schema API (filters) | Integrations | yes | yes | yes | yes | DONE | DONE | `rankkernel-current.md` 2.13 |
| Schema partner integrations (Recipe, Event, Podcast, EDD) | Integrations | EDD yes | yes | partner plugins | partner plugins | MISSING | MISSING | `yoast-code-c.md`; `rankmath-free-code.md` |
| Table of Contents block | Integrations | yes | yes | no | yes | MISSING | MISSING | `rankmath-free-code.md` blocks |
| Related Posts block and shortcode | Integrations | no | yes | no | yes | MISSING | MISSING | `rankmath-pro-code.md` 11 |
| Local Business Gutenberg block | Integrations | no | yes | no | yes | MISSING | MISSING | `rankmath-pro-code.md` 12 |
| MCP or AI assistant tooling | Integrations | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 5.2 |
| WP Rocket cross-sell | Integrations | no | yes | no | no | N/A (third-party plugin cross-sell) | N/A | `rankmath-pro-code.md` 0 |
| Imagify cross-sell | Integrations | no | yes | no | no | N/A (third-party plugin cross-sell) | N/A | `rankmath-pro-code.md` 0 |

### 6.8 Admin UX, tooling and governance

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Setup wizard | Admin UX | yes | yes (custom mode) | yes | yes | PLANNED (roadmap deferred, decide during 4.1) | PLANNED | `ROADMAP.md` |
| Module toggles | Admin UX | yes | yes | no | no | DONE (13 registry ids) | DONE | `rankkernel-current.md` 2.1 |
| Branded dashboard and sidebar layout | Admin UX | yes | yes | yes | yes | PLANNED (roadmap 1.4.1) | PLANNED | `ROADMAP.md` |
| Tabbed settings information architecture | Admin UX | yes | yes | yes | yes | PLANNED (roadmap 1.4.1; v1 single settings page ships) | PLANNED | `ROADMAP.md` |
| Admin columns with SEO score | Admin UX | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 8; `yoast-code.md` |
| Bulk edit of SEO fields | Admin UX | yes | yes (advanced) | yes | yes | MISSING | MISSING | `rankmath-pro-code.md` 0; `yoast-code.md` |
| Quick edit of SEO fields | Admin UX | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Post-list SEO filters (score, noindex, no keyword, orphan, schema) | Admin UX | no | yes | no | partial | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Media library SEO filters | Admin UX | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Term SEO details column | Admin UX | no | yes | yes | yes | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Role manager and capabilities | Admin UX | yes | yes | yes | yes | MISSING (REST uses `manage_options`; no role manager) | MISSING | `rankkernel-current.md` 2.11 |
| Settings export and import | Admin UX | yes | yes | yes | yes | PLANNED (gate Phase 4) | PLANNED | `feature-gap-research-gate.md` 15 |
| Database maintenance tools | Admin UX | yes | yes | no | no | MISSING | MISSING | `rankmath-web.md` 8.1 |
| System status and site health | Admin UX | yes | yes | partial | partial | MISSING | MISSING | `rankmath-web.md` 8 |
| Error log viewer | Admin UX | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` Status |
| Version control and rollback | Admin UX | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` version-control |
| Beta update channel | Admin UX | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` version-control |
| Contextual help and documentation | Admin UX | yes | yes | yes | yes | MISSING | MISSING | `rankmath-web.md` 8 |
| Frontend SEO score badge | Admin UX | yes | yes | no | no | MISSING | MISSING | `rankmath-free-code.md` frontend-seo-score |
| Notification centre and upsell banners | Admin UX | yes | yes | yes | yes | N/A (product rule: zero nags, upsells and notification centre) | N/A | `rankkernel-current.md` 1.2 |
| Onboarding wizard | Admin UX | yes | yes | yes | yes | PLANNED (deferred, decide during 4.1) | PLANNED | `ROADMAP.md` |
| Multisite compatibility | Admin UX | yes | yes | yes | yes | MISSING (v1 targets a single site) | MISSING | `ROADMAP.md` |
| Multisite network settings and purge | Admin UX | yes | yes | yes | yes | N/A (network-level agency feature; deferred in v1) | N/A | `ROADMAP.md` |
| Benchmark or debug dev panel | Admin UX | no | no | no | no | PLANNED (roadmap 1.4.2) | PLANNED | `ROADMAP.md` |

### 6.9 Migration, import and export

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Import from Yoast SEO | Migration | yes | yes | n/a | n/a | PLANNED (roadmap 1.3.1) | PLANNED | `ROADMAP.md` |
| Import from Rank Math | Migration | n/a | n/a | yes | yes | PLANNED (roadmap 1.3.1) | PLANNED | `ROADMAP.md` |
| Import from All in One SEO | Migration | yes | yes | yes | yes | PLANNED (roadmap 1.3.1) | PLANNED | `ROADMAP.md` |
| Import from SEOPress | Migration | yes | yes | no | no | PLANNED (roadmap 1.3.1) | PLANNED | `ROADMAP.md` |
| Import from SEO Framework, SmartCrawl, Squirrly, WP Meta SEO | Migration | no | no | yes | yes | MISSING | MISSING | `yoast-web.md` 9 |
| Import redirects from the Redirection plugin | Migration | yes | yes | no | yes | MISSING | MISSING | `rankmath-web.md` 9; `yoast-code-c.md` |
| Import Yoast Premium redirects | Migration | yes | yes | n/a | n/a | MISSING | MISSING | `rankmath-web.md` 9 |
| Yoast block converter | Migration | yes | yes | n/a | n/a | MISSING | MISSING | `rankmath-free-code.md` Database tools |
| CSV SEO metadata import and export | Migration | no | yes | no | no | MISSING | MISSING | `rankmath-pro-code.md` 0 |
| Focus keyword CSV import and export | Migration | no | yes | no | yes (keyword export) | MISSING | MISSING | `rankmath-pro-code.md` 0; `yoast-code-c.md` |
| Redirect CSV import and export | Migration | no (server export only) | yes | no | yes | DONE | DONE | `rankkernel-current.md` 2.8 |
| 404 log export | Migration | no | yes | no | no | PLANNED (gate Phase 5) | PLANNED | `feature-gap-research-gate.md` 15 |
| GTIN migration tool | Migration | no | yes | n/a | n/a | MISSING | MISSING | `rankmath-pro-code.md` 17 |

### 6.10 Licensing, support and agency

| Feature | Area | Rank Math free | Rank Math PRO | Yoast free | Yoast Premium | RankKernel | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|
| Free forever with no licence checks | Licensing | yes | n/a | yes | n/a | DONE (no licence code path exists) | DONE | `rankkernel-current.md` 1.2 |
| Paid plans and pricing tiers | Licensing | no | yes | no | yes | N/A (free self-hosted plugin, no paid tier by product rule) | N/A | `rankkernel-current.md` 1.1 |
| Licence activation and enrolment | Licensing | no | yes | no | yes | N/A (no licence model) | N/A | `rankkernel-current.md` 1.2 |
| Priority or 24/7 support | Licensing | no | yes | no | yes | N/A (community support for a free plugin) | N/A | `rankmath-web.md` 10.2; `yoast-web.md` 10 |
| Multisite licence rules | Licensing | no | yes | no | yes | N/A (no licence model) | N/A | `yoast-web.md` 10 |
| Bulk discounts and agency reseller | Licensing | no | yes | no | yes | N/A (no paid product) | N/A | `yoast-web.md` 10 |
| Money-back guarantee and refund policy | Licensing | no | yes | no | yes | N/A (no paid product) | N/A | `rankmath-web.md` 10.2; `yoast-web.md` 10 |
| Client site quota per account | Licensing | no | yes | no | yes | N/A (no account or quota model) | N/A | `rankmath-web.md` 10.2 |
| Rank Math Vault credential sharing | Licensing | yes | yes | no | no | N/A (no vendor support channel requiring credentials) | N/A | `rankmath-web.md` 8 |
| Academy or paid training | Licensing | no | no | no | yes | N/A (external training product) | N/A | `yoast-web.md` 10 |
| Google Docs add-on | Licensing | no | no | no | yes | N/A (external product, not a WordPress plugin) | N/A | `yoast-web.md` 8 |
| Shopify app | Licensing | no | no | no | yes | N/A (different platform, not WordPress) | N/A | `yoast-web.md` 8 |

## 7. Gap register

Every row from the master matrix whose verdict is MISSING or PARTIAL, grouped into tiers and ordered by user impact inside each tier. Closely related features are bundled into one entry; each entry names the competitor feature it corresponds to and a one line on the smallest honest implementation. PLANNED, EXTERNAL and N/A rows are excluded here (they are in the matrix, section 8 and section 9).

### Tier 1, required for credible free parity with what both competitors give away free

| What is missing | Corresponds to competitor feature | Smallest honest implementation |
|---|---|---|
| Per-post SEO title, meta description, robots and Open Graph or Twitter editing UI (only the schema metabox exists) | Rank Math and Yoast per-object editors in the block and classic editor | One registered meta payload already exists; render a standard meta box bound to it with title, description, robots and social fields. |
| Per-post canonical override UI | Yoast and Rank Math per-object canonical field | Add a canonical field to the same meta box, wired to the existing payload `canonical`. |
| Per-context metadata templates for post types, taxonomy, homepage, author, date, search and 404 | Both vendors' Titles and Meta per-context template layer | Extend the existing single `title_template` and `description_template` settings to a per-context map resolved by a context key. |
| Snippet and SERP preview with pixel guidance | Rank Math snippet preview and Yoast search appearance preview | Client-side title and description preview component using a rough pixel measure, no server round trip. |
| Social previews and the social template layer, plus default share image, Twitter username and additional profiles emission | Rank Math free social preview; Yoast Premium social previews | Emit the already stored social fields and reuse the metadata box for a simple OG and Twitter preview. |
| Full token grammar, including parameterised tokens and the wider token set | Rank Math about 53 variables and Yoast 50+ variables | Extend `TagsReplacer` to accept `%%token(args)%%` and add the missing built-ins (parent title, term, author id, custom field, page number, product fields). |
| Robots defaults per post type, taxonomy, author and date, plus noindex empty taxonomies, noindex subpages and noindex password-protected | Rank Math and Yoast granular robots defaults | A per-context robots map merged restrictively, plus three global toggles, all driving one `is_indexable()` result. |
| Noindex toggle for password-protected pages | Rank Math PRO and Yoast per-object noindex | One filter on the indexability gate when `post_password` is set. |
| Content analysis engine, readability, focus keyword UI, on-page tests and SEO score | Both vendors' free on-page analysis and readability scoring | A local deterministic analyser in PHP plus editor JS with a small check set and a numeric score, no external calls. |
| Multiple and unlimited focus keyphrases plus multi-keyphrase analysis | Rank Math 5 free, Yoast 1 free and 5 Premium | Store a keyword list in the payload and run the same checks per keyword. |
| Internal link counter, link index and orphan report | Yoast link counter and orphaned filter; Rank Math link counter and orphan detection | One link index table populated on save plus a background pass, then an orphan query for published indexable posts. |
| Internal link suggestions | Rank Math free suggestions; Yoast Premium suggestions | Token-overlap suggestions from the link index, shown in the editor, never auto-inserted. |
| HTML sitemap | Rank Math free HTML sitemap | A shortcode and a virtual endpoint rendering the existing sitemap sets as a linked list. |
| llms.txt generator | Rank Math free module; Yoast free generator | Virtual rewrite with `text/markdown`, headers from settings, sections per post type and taxonomy, no physical file by default. |
| AI crawler group editor for robots.txt | Yoast Premium bot blocker | A free robots.txt group for GPTBot, CCBot and Google-Extended rendered above the wildcard group, with verified tokens. |
| robots.txt editor and validator | Rank Math and Yoast robots.txt editors | Virtual filter editor only, never write a physical file, with a syntax preview and allow-list validation. |
| Custom sitemap URLs | Rank Math custom sitemap function | Add a filter that appends arbitrary URLs to a set. |
| Strip category base, redirect attachments to parent, capitalize and rewrite titles | Rank Math and Yoast general title and link settings | Three small settings hooks in the metadata module. |
| Bulk edit of titles and descriptions | Both vendors' bulk editor | A list-table bulk screen writing to the existing payload field. |
| Breadcrumb primary category or taxonomy per post | Yoast primary category, Rank Math primary taxonomy | Add a per-post primary term selector writing to the existing per-type primary taxonomy setting. |
| Full site-wide dashboard, sidebar and tabbed settings IA | Both vendors' admin shell and dashboard | The Phase 4.1 redesign already planned, zero nags. |
| Setup and onboarding wizard | Both vendors' setup wizards | A short guided flow over existing settings, deferred until the redesign. |

### Tier 2, required to match their paid tiers for free, per the product promise

| What is missing | Corresponds to competitor feature | Smallest honest implementation |
|---|---|---|
| Blank-canvas custom schema builder with conditions, templates, import, preview and property groups | Rank Math PRO Custom Schema Builder, templates and display conditions | Keep the current fixed type plus custom JSON, add a free-form property editor and a template store with include and exclude rules. |
| Schema types Speakable, About and Mentions, Restaurant, standalone CollectionPage, and the Yoast page subtypes (ItemPage, AboutPage, ContactPage, MedicalWebPage, CheckoutPage, RealEstateListing, SearchResultsPage) | Rank Math PRO extra types; Yoast page type catalog | Add piece classes and register the type names, reusing the existing piece interface. |
| SiteNavigationElement schema | Rank Math free SiteNavigationElement | One small piece built from the primary menu. |
| Term and taxonomy schema | Rank Math PRO taxonomy schema; Yoast taxonomy schema tab | Add a term metabox bound to `_rankkernel_term_data` and a CollectionPage piece for archives. |
| Schema shortcodes, rich-snippet block and Table of Contents block | Rank Math and Yoast schema shortcodes, rich snippet block and TOC block | Register the shortcode and block, reusing the existing generator pieces. |
| 193 LocalBusiness subtypes UI | Rank Math free business types | A select list of schema.org LocalBusiness subtypes stored in the location settings. |
| Automatic QandA schema for bbPress and video autodetect for schema | Rank Math PRO bbPress QandA and video autodetect | Reuse the QAPage and VideoObject pieces behind a detection step. |
| Generate video schema for old posts | Rank Math PRO backfill tool | A WP-CLI and admin batch that runs the video parser over existing posts. |
| IndexNow (Instant Indexing) | Rank Math free module; Yoast Premium IndexNow | The planned Phase 5.1 module with key file, submit on publish and a failure log. |
| Google Indexing API support | Rank Math free Google Indexing API | Optional OAuth submission path alongside IndexNow, off by default. |
| Image SEO: auto ALT and title, variable library, captions, descriptions, casing, avatar ALT, find and replace, media library filters | Rank Math free image SEO plus PRO extras | The planned Phase 5.3 module with pattern autofill, batch processing and media filters, frontend attribute filters only. |
| Redirect scheduling, categories, debugger, fallback behaviour, multiple sources and parameterised matching | Rank Math PRO redirections plus free debugger and fallback | Extend the redirect table and matcher with schedule, category, fallback and multi-source; parameter matching follows the matcher. |
| 404 log export and bulk set 410 | Rank Math PRO 404 export and free bulk 410 | A CSV export of the existing log table and a bulk action that inserts 410 rules. |
| WooCommerce free parity: base stripping, product schema fields, gallery images, hidden product noindex and sitemap, OG price, noindex cart, checkout and account, product variables, short description in analysis, product sitemap | Rank Math free WooCommerce module (Yoast charges) | A WooCommerce module that hooks product data into the existing schema and sitemap, plus the permalink and noindex settings. |
| WooCommerce GTIN and MPN, variation schema, product content tests and GTIN migration | Rank Math PRO WooCommerce SEO | Editor fields plus a ProductGroup and per-variation Offer builder, and product-specific analysis checks. |
| Local SEO single location: identity, contact, address, hours, geo, contact shortcode, KML, LocalBusiness block | Rank Math free Local SEO single location | A settings screen and shortcode feeding the existing LocalBusiness piece, plus a KML endpoint. |
| News sitemap, Video sitemap and video autodetect | Rank Math PRO and Yoast bundled addons | Add two sitemap providers to the existing sitemap registry, gated in a News and Video module. |
| Podcast module, schema and RSS feed | Rank Math PRO podcast module | A PodcastEpisode piece plus a dedicated feed, plus Media RSS and Yandex video OpenGraph tags. |
| Easy Digital Downloads schema and EDD SEO | Rank Math free and PRO EDD | An EDD Product piece and download-aware meta fallbacks. |
| EDD, Recipe, Event and Podcast partner schema integrations | Yoast partner integrations | Detection hooks that inject pieces from partner plugin data. |
| Importers for Yoast, Rank Math, All in One SEO and SEOPress | Both vendors' importers | The planned Phase 3.1 importer with dry run, batch and rollback, never deleting competitor data. |
| Settings export and import | Rank Math and Yoast settings export and import | Export the option set as JSON and import it with validation, Phase 4. |
| Head cleanup (generator, shortlink, RSD, WLW, oEmbed, emojis, pingback, powered-by), feed controls, internal search cleanup, advanced URL and UTM cleanup | Yoast Premium crawl optimisation | A crawl controls module of opt-in toggles, each one filter, off by default. |
| Header and footer code injection | Rank Math PRO | Two settings fields printed on the front end, gated to `unfiltered_html` and the right capability. |
| hreflang passthrough | Both vendors' passthrough | A filter that emits hreflang pairs from a multilingual plugin, plus manual pairs. |
| Site-wide local SEO analyzer | Rank Math free SEO analyzer; Yoast partial | Local deterministic site scan with no remote API, reusing the content analysis checks at site scale. |
| Keyphrase synonyms, keyphrase distribution, morphology, word complexity, inclusive language, prominent words, estimated reading time, product-specific tests, affiliate link prefixes, cornerstone marking and stale cornerstone finder | Rank Math PRO and Yoast Premium analysis extensions | Extend the planned local analyser with a synonym and morphology layer, a distribution check and the extra readability checks. |
| AI Optimize, AI Summarize and AI Content Planner | Yoast Premium AI; Rank Math Content AI | Extend the planned BYO-key AI suite beyond titles and alt text, strictly read-and-suggest, never auto-write. |
| Analytics via a free bridge: GSC, GA4 and dashboard widgets | Rank Math analytics; Yoast via Site Kit | Read-only Site Kit bridge, no vendor account and no telemetry. |
| SEO email reports | Rank Math email reports | A scheduled report built from the Site Kit bridge data, off by default. |

### Tier 3, vertical and ecosystem features

| What is missing | Corresponds to competitor feature | Smallest honest implementation |
|---|---|---|
| WooCommerce brand taxonomy, product variables, short-description analysis, duplicate-content handling and GTIN migration | Rank Math free and PRO WooCommerce | A brand taxonomy registration plus variables and a one-off migration tool. |
| Local multiple locations and store locator | Rank Math PRO local and Yoast Local addon | A Locations post type and a radius search, deferred, maps need an external key. |
| EDD complete SEO, Google Web Stories module, AMP module, bbPress post-type SEO and BuddyPress variables | Rank Math free verticals (Yoast charges for most) | Small compatibility modules that emit correct metadata and schema for each platform. |
| Elementor integration, Elementor breadcrumbs widget and accordion to FAQ | Rank Math and Yoast Elementor integration | A sidebar compatibility shim plus a widget and an accordion to FAQ converter. |
| Divi integration and accordion to FAQ | Rank Math Divi integration | A builder panel plus an accordion to FAQ converter. |
| Advanced Custom Fields integration | Rank Math free ACF; Yoast glue plugin | Feed ACF field values into tokens and schema variables, and into analysis. |
| Page builder compatibility (Bricks, Betheme, Brizy, SiteOrigin and more) | Rank Math certified compatibility | Detection and meta output fixes per builder, no bespoke UI. |
| WPML, Polylang and TranslatePress compatibility | Both vendors' multilingual compatibility | Passthrough filters and language-aware sitemap and canonical handling. |
| Google Site Kit integration | Yoast free Site Kit integration | A read-only data adapter for the analytics bridge. |
| Mastodon verification and NLWeb connector | Yoast Premium Mastodon and free NLWeb | A `rel=me` link and a `sameAs` entry; a documented schema aggregation endpoint. |
| Broad Gutenberg editor sidebar and full classic meta box | Both vendors' editor panels | The planned Phase 3.2 editor sidebar over the registered REST meta field. |
| MCP or agent tooling | Rank Math MCP tools | A small read-only tool surface exposing settings and analysis, later. |
| Headless REST head payload and WPGraphQL fields | Rank Math and Yoast headless endpoints | The planned Phase 6.2 `GET /rankkernel/v1/seo/{id}` and WPGraphQL registration. |

### Tier 4, admin polish and tooling

| What is missing | Corresponds to competitor feature | Smallest honest implementation |
|---|---|---|
| Admin columns with SEO score, term SEO column, bulk edit, quick edit and post-list or media SEO filters | Rank Math and Yoast list-table tooling | Reuse the WP list-table API, gated to the relevant screens, and surface the analyzer score. |
| Role manager and fine-grained capabilities | Rank Math Role Manager | Map the existing capabilities to role groups and add a small settings screen. |
| Database maintenance tools, system status, error log viewer, version control and rollback, beta channel | Rank Math Status and Tools | Reuse `MigrationRunner` for a maintenance screen and expose a rollback only once on the wordpress.org repository. |
| Contextual help and documentation | Both vendors' inline help | Link to hosted docs from each screen, no bundled marketing. |
| Frontend SEO score badge | Rank Math free frontend score | A small shortcode or block reading the stored score, off by default. |
| Multisite compatibility | Both vendors' multisite support | Network-aware option reads and per-site tables, deferred, v1 is single site. |
| Analytics dashboard widgets, AdSense, PageSpeed, URL Inspection, email report content, GA IP anonymisation, self-hosted GA JS and logged-in-user exclusion | Rank Math PRO analytics | Deferred, only the read-only Site Kit bridge is planned; the rest needs Google accounts. |
| Freshness or modified-date lock | Rank Math lock modified date | One meta toggle and a save filter. |
| RSS optimization (content before and after feed) | Rank Math and Yoast RSS settings | Two settings printed on the RSS item hooks. |
| Random word, image alt and image title variables | Rank Math PRO variables | Add three tokens to the extended token resolver. |
| Social image overlay icons, Slack enhanced sharing and Facebook thumbnail flush | Rank Math free social extras | Small output and cache-bust hooks, low priority. |
| Norton Safe Web verification and custom verification or head meta tags | Rank Math webmaster extras | One extra setting field and a free-form meta tag field. |
| Custom fields in the meta box and search appearance per type and archive | Rank Math custom fields and Yoast search appearance | Settings that expose chosen custom fields and per-context appearance controls. |
| Importer extras: SEO Framework, SmartCrawl, Squirrly, WP Meta SEO, Redirection plugin imports, Yoast Premium redirects, Yoast block converter, CSV metadata import and focus keyword CSV | Rank Math and Yoast importer breadth | Extend the Phase 3.1 importer with the extra map files and a CSV path. |
| Analytics and rank tracking suite (traffic source filtering, site and post analytics, winning and losing posts and keywords, content SEO overview, single-post reports, ranking keywords per post, position history, rank tracker) | Rank Math PRO analytics | Read-only GSC bridge only if a free path exists, otherwise recorded as EXTERNAL. |
| AI brand visibility and AI optimize, summarize and planner leftovers | Rank Math AI Visibility; Yoast AI+ | Only the BYO-key subset is planned; the rest depends on paid AI services. |

## 8. Cannot be free (EXTERNAL)

Every feature that depends on a paid third-party service, with the service named and a one line explanation of why it cannot be delivered free. This is the explicit and complete list, so nobody later claims something was forgotten.

| Feature | Service | Why it cannot be free |
|---|---|---|
| Competitor SEO analysis | RankMath.com SEO Analyzer API | The remote analyzer runs on Rank Math servers and needs a paid Rank Math account. |
| Side-by-side SEO comparison | RankMath.com SEO Analyzer API | Same remote endpoint, paid account required. |
| Competitor site SEO audit via MCP | RankMath.com SEO Analyzer API | Same remote endpoint, paid account required. |
| Search intent analysis | Rank Math Content AI | Keyword intent is computed by the paid Content AI service. |
| Google Trends data | Google Trends via Rank Math | Rank Math brokers Trends data through its paid service account. |
| Semrush keyword data and Semrush integration | Semrush | Keyword volume, trend, difficulty and intent come from the paid Semrush API. |
| Wincher rank tracking and Wincher integration | Wincher | Free Wincher is capped; tracking keyphrases at scale needs a paid Wincher subscription. |
| Keyword rank tracker | Rank Math tracked-keyword quota, or Wincher | Rank Math sells tracked-keyword quota by plan, and third-party tracking needs a paid Wincher plan. |
| AI search traffic tracker | Rank Math Content AI and analytics AI-referrer service | Requires the paid Content AI service to classify AI traffic. |
| AI brand visibility tracking | Rank Math AI Visibility, or Yoast AI+ | Paid AI visibility service, sold as part of a paid plan. |
| Generative long-form AI writing | Paid AI provider (OpenAI, Anthropic, Google and similar) | Model calls cost money; RankKernel offers only a BYO-key subset for titles and alt text, never a bundled paid service. |
| Local map, store locator or GPS | Google Maps JavaScript and Embed API with a billing-enabled key | The Maps API requires a billing-enabled Google Cloud project key. |
| Algolia site-search integration | Algolia | Requires a paid Algolia account and index. |

Note on free-but-external services, which are not counted as EXTERNAL because they are not paid third-party services and can be offered free with the user's own credentials: Google Search Console and Google Analytics 4 data (planned via a read-only Site Kit bridge), Google PageSpeed Insights, Google AdSense reporting, Google Indexing API, and any user supplied AI provider key. These need a user account or OAuth grant but not a payment to a vendor, so they are tracked as PLANNED or MISSING in the matrix rather than EXTERNAL.

## 9. Not applicable (N/A)

Agency style and product-fit features that do not apply to a free self-hosted plugin, each with the reason.

### 9.1 Commercial and licensing features

These exist only because the competitors sell a paid product or a service contract. RankKernel has no paid tier, no account and no licence server, so the whole category is out of scope by product rule.

| Feature | Reason it does not apply |
|---|---|
| Paid plans and pricing tiers | RankKernel is free forever with no paid tier. |
| Licence activation and enrolment | There is no licence model and no licence code path exists. |
| Multisite licence rules | No licence model to scope. |
| Client site quota per account | No account or quota model. |
| Priority or 24/7 support | Community support is the model for a free plugin. |
| Bulk discounts and agency reseller programme | No paid product to discount or resell. |
| Money-back guarantee and refund policy | No purchase exists. |
| White-labelled email reports | Agency client reporting, not a free-plugin concern. |
| Client management for client sites | Agency multi-client feature. |
| Academy or paid training | External training product, not plugin functionality. |
| Google Docs add-on | External product, not a WordPress plugin. |
| Shopify app | Different platform, not WordPress. |
| Rank Math Vault credential sharing | Ranks as a vendor support channel; there is no vendor support channel requiring site credentials. |
| Google data retention and fetch-frequency plan limits | Paid plan limits on data retention and fetch cadence, meaningless without a plan. |

### 9.2 Product-fit and design decisions

| Feature | Reason it does not apply |
|---|---|
| rel=next and rel=prev tags | Google deprecated them in 2019, so RankKernel deliberately does not emit them. |
| Watermarked social images | Marketing value only, with no SEO benefit; deliberately excluded. |
| Redirect .htaccess sync | Server coupling and availability lockout risk; the gate recommends omitting it. |
| Indexables table for fast meta output | RankKernel uses a single-key meta row by design and has no derived store to build or rebuild. |
| Zapier automated publishing | Deprecated in Yoast 20.7 and dependent on external accounts. |
| WP Rocket cross-sell | Third-party plugin cross-sell, not functionality. |
| Imagify cross-sell | Third-party plugin cross-sell, not functionality. |
| Notification centre and upsell banners | Product rule: zero nags, upsells and notification centre, ever. |
| Multisite network settings and purge | Network-level administration; v1 targets a single site by decision. |

## 10. Recommended build order

Derived from the section 7 tiers and mapped onto the existing roadmap phases. Tier 1 first, then Tier 2, then Tier 3, with Tier 4 polish folded into the admin phase.

1. Phase 2.6, head and crawl controls (Tier 1). Per-context metadata templates, per-post SEO editing UI, canonical override UI, full token grammar with parameters, snippet preview, social layer and previews, robots defaults, noindex rules, virtual robots.txt editor with a free AI crawler group, and the llms.txt virtual generator. All local and free, and the single largest genuine gap.
2. Phase 3, content, media and migration (Tier 1 and Tier 2). Importer (3.1), Gutenberg editor sidebar (3.2), local content analysis and readability, multi-keyphrase, internal linking link index and orphan report, image SEO pattern autofill with bulk and media filters, HTML sitemap.
3. Phase 4, admin UI and design (Tier 1 and Tier 4). Branded dashboard and sidebar, tabbed settings, setup wizard, settings export and import, admin columns, bulk edit, quick edit and list-table filters.
4. Phase 5, technical SEO extras (Tier 2 and Tier 4). IndexNow, head cleanup and crawl toggles, header and footer injection, hreflang passthrough, local site-wide analyzer, 404 log export, redirect scheduling, redirect organising and debugger, robots.txt validator, and the .htaccess decision.
5. Phase 6, commerce, local, news and video (Tier 2 and Tier 3). WooCommerce free parity then the PRO identifiers and variations, single-location Local SEO with settings, shortcode and KML, News sitemap, VideoObject autodetect and video sitemap.
6. Phase 7, AI and headless (Tier 2 and Tier 3). BYO-key AI for titles, descriptions and alt text (never a bundled paid service), read-only reporting, and the headless REST payload with optional WPGraphQL fields.
7. Phase 8, QA and WordPress.org launch. Benchmark protocol, plugin check, readme, assets and submission.
8. Ongoing, the Tier 2 AI optimize, summarize and planner extras, the Tier 3 ecosystem integrations, and the Tier 4 polish items, each only after the phase above it ships.

Items in section 8 are not scheduled because they cannot be free. Items in section 9 are not scheduled by design.

## 11. Conflicting or unverified claims

Everything unverified, disagreeing between sources, or uncertain. Stated plainly so the rest of the document can be trusted.

### 11.1 RankKernel source discrepancies

| Claim | Conflict | Resolution used |
|---|---|---|
| Module count | The registry declares 13 ids but only 6 have classes and directories | Treated the 7 placeholders as not built, and marked their features MISSING or PLANNED. |
| Test count | `ROADMAP.md` snapshot says 322 tests and 1219 assertions; `rankkernel-current.md` reports 992 tests and 3460 assertions at commit 606fc95 | Used the newer code-verified count; `ROADMAP.md` is stale. |
| Schema piece count | The gate doc says 30 pieces; `rankkernel-current.md` says 29 registered pieces (the 30th counts the helper) | Used 29. |
| Roadmap content | The gate doc section 22 says it updated `ROADMAP.md` with Phase 2.6, content analysis, image SEO and internal linking, but the on-disk `ROADMAP.md` contains no Phase 2.6 and no content analysis phase | Used the on-disk `ROADMAP.md` for committed phases, and the gate doc for the proposed phases, with PLANNED applied to both. |
| Per-object editing | `feature-matrix.md` marks per-object title and description COMPLETE, but `rankkernel-current.md` shows no admin field, only REST and programmatic writes | Preferred `rankkernel-current.md`; marked per-object editing MISSING. |
| Redirect cache table | `feature-matrix.md` names a second `wp_rankkernel_redirects_cache` table; `rankkernel-current.md` states there is no second cache table, only object cache with transient fallback, and only two custom tables exist | Preferred `rankkernel-current.md`. |
| Social fields | The settings page stores five social fields, but `rankkernel-current.md` does not confirm they are emitted by the head renderer | Marked those rows PARTIAL rather than DONE. |
| Importer default | The `importer` id is seeded ON at activation but has no class, so it never boots | Noted explicitly; treated as not built. |
| PLANNED versus MISSING policy | The gate doc is marked RESEARCH ONLY and awaiting owner approval, but it writes concrete recommended phases | Applied PLANNED where a feature is named in `ROADMAP.md` or in the gate doc's recommended phases, and MISSING everywhere else. |

### 11.2 Version discrepancies between inputs

| Input | Version stated | Note |
|---|---|---|
| `feature-matrix.md`, `gap-analysis.md` | Yoast free 27.8, Rank Math free 1.0.277.2 | Older than the audited versions. Their structure and findings were re-checked against the newer code passes rather than copied. |
| `yoast-code.md` | Yoast free 28.4, Premium 27.8 | Matches the audited versions. |
| `rankmath-free-code.md` | Rank Math free 1.0.278 | Matches. |
| `rankmath-pro-code.md` | Rank Math PRO 3.0.109 | Matches. |

### 11.3 Rank Math unverified or conflicting claims

| Claim | Uncertainty | Treatment |
|---|---|---|
| Extra PRO schema type count | The comparison table says 6 extra types, the knowledge base lists 9, and the PRO code has 4 PRO-only types (Dataset, FactCheck, Movie, PodcastEpisode) with free shipping a 13-entry dropdown | Recorded all three counts; the matrix lists the 4 code-confirmed PRO types plus the knowledge base extras separately. |
| 840+ schema types supported | Marketing claim, not verifiable against schema.org | Recorded as a PRO claim, not asserted as fact. |
| Google Indexing API support | The Instant Indexing knowledge base covers IndexNow; the sidebar link labelled Google Indexing API points to the Instant Indexing page | Marked as a separate row and flagged ambiguous. |
| Rank Math Vault tier | Knowledge base article exists with no free or PRO indication | Marked FREE with a note, and flagged here. |
| Web Stories and AMP module tiers | Both modules exist in code (free, dependency-gated) but are not itemised in the official comparison table | Marked FREE based on code. |
| MCP module tier | Present on the tools page and knowledge base, not in the comparison table | Assumed free. |
| AI Visibility tier | Knowledge base and MCP page treat it as Content AI linked; no explicit plan gating row | Marked as beta, metered, and EXTERNAL for the paid capability. |
| bbPress and BuddyPress | bbPress is a PRO module in code; BuddyPress is a free module but only a schema-variable group in the knowledge base | Recorded from code. |
| Broken Link Checker and Automated Keyword Linking | Listed as separate pricing bullets but described as parts of AI Link Genius | Treated as AI Link Genius features. |
| Certified compatible product count | The compatibility page is paginated and filter driven; the exact count was not extracted | Recorded as about 40 without a precise number. |
| Alexa site verification | Still listed in the comparison table though the Alexa service is defunct | Recorded as listed, flagged as legacy. |
| Elementor and Divi integration tier | The comparison table marks both free while the breadcrumbs widget and accordion to FAQ are PRO | Split the base integration from the PRO extras. |
| 404 simple versus advanced naming | The comparison table splits simple free and advanced PRO, while the knowledge base presents one module with two modes | Recorded both. |

### 11.4 Yoast unverified or conflicting claims

| Claim | Uncertainty | Treatment |
|---|---|---|
| Social previews free or Premium | One page header lists social previews as in free, while the body and shop page say not available in free | Treated tags as free and visual previews as PREMIUM. |
| Crawl optimisation tier | The feature page header says free plus Premium; the help configuration guide says Premium only | Treated as PREMIUM and flagged. |
| Schema aggregation endpoint tier | The feature and timeline pages list it for free, the Premium features page lists it Premium only | Recorded as conflicting. |
| File editor (.htaccess, robots.txt) tier | Not stated as Premium; inferred free from Tools docs | Marked FREE with medium confidence. |
| Flesch score, word count and reading-time metric | Pages moved these into the Insights tab and describe Insights with Premium | Treated the metric as free and the reading-time block as Premium. |
| Exact snippet variable count | Vendor says 50+; the precise per-version count is not enumerated | Recorded as 50+ without a precise figure. |
| Semrush and Wincher free caps | The free caps are partner-plan boundaries that can change | Recorded as limited without depending on the exact cap. |
| Zapier integration | Deprecated in Yoast 20.7; no Zapier class exists in Premium 27.8 | Recorded as deprecated, not as a live feature. |
| ACF, Jetpack and NLWeb connector details | Listed only on the integrations hub with no dedicated feature page | Low confidence, recorded without detail. |
| JobPosting and Course schema | No native Yoast output found, would need the Schema API | Marked absent with low confidence. |
| Recipe, Event and Podcast schema | Present only through named third-party integrations, not native types | Recorded as partner integrations. |
| Yoast SEO Multilingual as a product | No separate paid Multilingual add-on page found; only WPML compatibility and multilingual analysis | Recorded as compatibility, not a standalone product. |
| Local, News and Video standalone purchase | Feature pages no longer show a standalone price; help footers say Premium includes them | Recorded as bundled in Premium, historically standalone. |
| Dedicated 404 monitor and site health | No Yoast 404 log or site-health screen found; closest are the Alert centre and the 404 title template | Recorded as absent. |

### 11.5 Disagreements between the Yoast code passes

| Disagreement | Detail | Resolution |
|---|---|---|
| News and Video sitemap providers | `yoast-code.md` and `yoast-code-b.md` state no news or video sitemap provider exists in either plugin on disk, while `yoast-web.md` documents the News SEO and Video SEO addons as bundled | The addons were not installed, so code cannot confirm them; the matrix records them as BUNDLED per vendor pages and notes the code absence. |
| Option key completeness | `yoast-code-a.md` is more detailed than `yoast-code.md` (adds `wpseo_upgrade_history`, `wpseo_tracking_last_request`, MyYoast token options, llms.txt options and the transient set) | Preferred `yoast-code-a.md` for option keys. |
| Inclusive language packaging | `yoast-code-b.md` shows the inclusive-language class ships in the free analysis bundle with Premium overriding the load path, while `yoast-code.md` lists it in the inclusive-language (Premium) category | Kept it PREMIUM per the official free-versus-Premium comparison, and noted the packaging detail. |
| Premium-only assessments | `yoast-code-b.md` shows KeyphraseDistribution, WordComplexity, TextAlignment and TextTitle are present in the free bundle catalog and registered by Premium, while `yoast-code.md` lists them premium-only | Kept them PREMIUM and noted that the catalog ships in the free bundle. |
| Premium schema pieces | `yoast-code.md` and `yoast-code-b.md` agree Premium adds no schema-piece classes and only filters properties, while `yoast-web.md` says Premium has significantly more schema types | Recorded Premium as additional properties on existing pieces, not new pieces. |
| Autoload | `yoast-code.md` states neither plugin passes an explicit autoload argument, so options inherit the WordPress default; `yoast-code-a.md` identifies specific explicit-autoload writes (`wpseo_upgrade_history` false, redirect export options true) | Preferred `yoast-code-a.md` where it gives an explicit value. |

### 11.6 Method caveats

| Caveat | Effect |
|---|---|
| Both competitors were installed but never activated | No runtime behaviour was observed; all competitor findings are code-derived, supplemented by official vendor pages for the web-only pass. |
| Line numbers move with plugin versions | Evidence anchors are valid for the audited versions only. |
| The web-only pass could not corroborate every code finding | Rows that appear only in the web pass or only in the code pass are marked in the area inventories, and genuinely conflicting ones are listed above. |
| No performance benchmark was run | This document makes no performance claim; the roadmap's benchmark panel is still pending. |
