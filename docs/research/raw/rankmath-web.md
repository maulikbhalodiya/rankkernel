# Rank Math SEO — Exhaustive Feature Inventory (FREE vs PRO)

Web-source-only inventory of every Rank Math SEO feature and sub-feature that could be confirmed from official Rank Math domains. Compiled as a completeness checklist for building a feature matrix.

- **Scope:** Rank Math SEO plugin (FREE), Rank Math PRO (PRO / Business / Agency), the Content AI add-on, AI Link Genius, MCP tools, and first-party integrations.
- **Date compiled:** 2026-09-16.
- **Method:** Official pages + individual Knowledge Base (KB) articles. No plugin source code was read.
- **Tier legend:** `FREE` = available in the free plugin; `PRO` = gated to paid Rank Math PRO/Business/Agency plans; `FREE(ltd)` = partly available in free, full in PRO; `Biz`/`Agency` = plan-specific; `Add-on` = separate purchase (Content AI/feature uses) or bundle (WP Rocket).
- **Confidence legend:** `H` = stated in the official Free-vs-PRO comparison table or a dedicated official KB page; `M` = stated on an official marketing/KB page but not itemized in the official comparison; `L` = seen on an official page but ambiguous/indirect.
- **Source key:**
  - `FvP` = https://rankmath.com/free-vs-pro/
  - `PRC` = https://rankmath.com/pricing/
  - `SS` = https://rankmath.com/wordpress/plugin/seo-suite/
  - `U50` = https://rankmath.com/blog/unique-rank-math-features/
  - `CAI` = https://rankmath.com/content-ai/
  - `ALG` = https://rankmath.com/ai-link-genius/
  - `COMP` = https://rankmath.com/compatibility/
  - `KB:<slug>` = https://rankmath.com/kb/<slug>/

> Important reading notes on the official comparison table (`FvP`):
> 1. The FREE column of the official table uses ✓ / ✕ icons; several rows use a "!" notice meaning *limited in free* (noted below as FREE(ltd)).
> 2. The table has two blocks: Block A = 77 "premium" rows (most PRO-gated), Block B = 123 "core" rows (most FREE).
> 3. Some counts conflict between the comparison table and the Schema KB (e.g. "18 pre-defined Schema Types / 6 extra" vs the KB listing 18 free + 9 PRO types). Both are recorded; see the Uncertain section.

---

## 1. Content & On-Page Analysis

### 1.1 Focus keyword & content optimization

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Focus Keyword (primary) | Set the primary keyword to optimize a post/page/CPT against. | FREE | H | `FvP`, `KB:focus-keyword` |
| Multiple / secondary focus keywords | Add secondary keywords; tests run per-keyword. | FREE | H | `KB:how-to-add-multiple-keywords` |
| Optimize unlimited keywords | No cap on keywords per post. | FREE | H | `FvP` |
| Content analysis engine | Analyzes content against keyword placement, length and density. | FREE | H | `FvP` |
| SEO Analysis Tool (40 factors) | Site/content audit across ~40 SEO factors. | FREE | H | `FvP`, `SS` |
| 30+ detailed SEO tests | Itemized on-page tests per post. | FREE | H | `FvP`, `KB:score-100-in-tests` |
| SEO Analysis Score | Numeric optimization score. | FREE | H | `FvP` |
| SEO warnings / failed tests | Distinguishes warnings from failed tests. | FREE | H | `FvP` |
| Use Product Schema test | On-page test prompting Product schema. | PRO | H | `KB:score-100-in-tests` |
| Allow customers to leave reviews test | Content test for Product review schema. | PRO | H | `KB:score-100-in-tests` |
| Competitor SEO Analysis | Analyze a competitor's on-page SEO. | PRO | H | `FvP` |
| Side-by-Side SEO Comparison | Compare your page vs competitor side by side. | PRO | H | `FvP` |
| Search Intent Analysis | Determines search intent of the focus keyword. | PRO | H | `FvP`, `U50` |
| Google Trends integration | In-dashboard Trends data for focus keywords. | PRO | H | `FvP`, `U50` |

### 1.2 On-page test groups (from official test KB)

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Basic SEO tests | KW in SEO title, meta description, URL, beginning of content, in content, overall content length. | FREE | H | `KB:score-100-in-tests` |
| Additional SEO tests | KW in subheadings, KW in image ALT, keyword density, URL length, external links, followed external link, internal links, KW uniqueness, Content AI usage. | FREE | H | `KB:score-100-in-tests` |
| Title Readability tests | KW at start of title, sentiment, power word, number in title. | FREE | H | `KB:score-100-in-tests` |
| Content Readability tests | Table of contents, short paragraphs, media usage. | FREE | H | `KB:score-100-in-tests` |
| Pillar content selection | Mark a post as pillar content for internal linking context. | FREE | H | `FvP`, `KB:score-100-in-tests` |

### 1.3 Internal linking & link intelligence

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Internal Linking Suggestions | Editor suggestions for internal links while writing. | FREE | H | `FvP` |
| Link Suggestions / Link Suggestion Titles | Per-post-type toggle for link suggestions and their titles. | FREE | H | `KB:titles-and-meta` |
| AI Link Genius module | AI internal-linking suite (suggestions, audits, auto-linking). | PRO | H | `PRC`, `ALG` |
| Centralized Links Dashboard | Single dashboard for internal-link health. | PRO | H | `ALG`, `KB:how-to-do-link-audit` |
| AI Link Suggestions in editor | Context-aware anchor-text/link suggestions. | PRO | H | `ALG`, `KB:how-to-use-ai-link-genius` |
| Auto-Link Keyword Variations | Keyword-to-URL maps with AI-suggested variations, auto-linked on publish. | PRO | H | `PRC`, `ALG` |
| Bulk Link Update tool | Update multiple links at once, with rollback. | PRO | H | `ALG`, `KB:how-to-use-ai-link-genius` |
| AI Recommended Related Posts | AI-curated related posts. | PRO | H | `ALG` |
| Related Posts Block & Shortcode | Frontend related-posts block/shortcode with styling options. | PRO | M | `KB:customize-related-posts` |
| Orphan Pages detection | Detect posts with no incoming internal links. | PRO | H | `FvP`, `ALG` |
| Nofollow Link Detection | Find nofollow internal links. | PRO | H | `ALG`, `KB:how-to-do-link-audit` |
| Redirected-link detection | Find redirected internal links. | PRO | H | `KB:how-to-do-link-audit` |
| Internal-link HTTP status audit | Audit the HTTP status of internal links. | PRO | H | `KB:how-to-do-link-audit` |
| Link coverage / incoming-link analysis | Per-post incoming-link report and filters. | PRO | M | `KB:how-to-do-link-audit` |
| Broken Link Checker | Automatically detects broken internal/external links. | PRO | H | `PRC` |
| Automated Keyword Linking | Keyword-to-URL maps auto-linked across new posts. | PRO | H | `PRC` |
| Max links per post / case sensitive / excluded post types/IDs/terms | AI Link Genius configuration controls. | PRO | H | `KB:how-to-use-ai-link-genius` |
| Exclude post types / IDs / terms from linking | Exclude content from AI Link Genius. | PRO | H | `KB:how-to-use-ai-link-genius` |

### 1.4 Content AI (AI assistant) — module FREE, usage metered

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Content AI module | AI content assistant panel inside the editor. | FREE(ltd) | H | `CAI`, `KB:content-ai-setup` |
| Content AI Research | Latest-info research/recommendations for content. | FREE(ltd) | H | `KB:content-ai-plans-and-features` |
| Content AI Writing | AI content generation inside WordPress. | FREE(ltd) | H | `KB:content-ai-plans-and-features` |
| Content AI Images (alt text) | AI-generated image alt text. | FREE(ltd) | H | `KB:content-ai-plans-and-features` |
| Free users: 10 uses per available feature | Free-tier Content AI allowance. | FREE(ltd) | H | `KB:content-ai-plans-and-features` |
| 15-day Content AI trial (Starter) | Trial bundled with PRO plan. | PRO | H | `PRC`, `KB:content-ai-plans-and-features` |
| 15-day Content AI trial (Creator) | Trial bundled with Business plan. | Biz | H | `PRC` |
| 15-day Content AI trial (Expert) | Trial bundled with Agency plan. | Agency | H | `PRC` |
| Content AI plans: Starter / Creator / Expert | Annual plans with monthly feature uses that do not roll over. | Add-on | H | `KB:content-ai-plans-and-features` |
| Feature usage/refresh tracking | Check Content AI usage and refresh date. | FREE | H | `KB:check-content-ai-usage` |
| Content AI credits → feature-based migration | Migration from credit model to feature-based monthly limits. | FREE | H | `KB:content-ai-credits-migration` |

**Content AI tool inventory** (each is a named tool; usage metered per plan; `H` from KB `content-ai-*` slugs):

| Tool | What it does | Tier | Source |
|---|---|---|---|
| Command Center | Command palette for Content AI actions. | FREE(ltd) | `KB:content-ai-command-center` |
| Command tool | Prompt-driven command execution. | FREE(ltd) | `KB:content-ai-command-tool` |
| AI Blog Post Wizard | Guided long-form post generation. | FREE(ltd) | `KB:content-ai-blog-post-wizard-tool` |
| Write long-form content with 1-click | One-click long-form generation. | Add-on | `KB:content-ai-plans-and-features` |
| Blog Post Idea / Outline / Introduction / Conclusion | Blog-building AI tools. | FREE(ltd) | `KB:content-ai-blog-post-idea-tool` etc. |
| Topic Research | AI topic research. | FREE(ltd) | `KB:content-ai-topic-research-tool` |
| Keyword Research | AI keyword research. | Add-on | `KB:content-ai-plans-and-features` |
| Semantic Keyword Variations | Secondary keyword variations. | Add-on | `KB:content-ai-plans-and-features` |
| SEO Title / SEO Description / SEO Meta AI tools | AI meta generation. | FREE(ltd) | `KB:content-ai-seo-title-tool`, `KB:content-ai-seo-description-tool`, `KB:content-ai-seo-meta-tool` |
| Fix SEO Tests | AI auto-fix for failed on-page tests. | Add-on | `KB:fix-seo-tests-with-content-ai` |
| Bulk Edit SEO Meta | Bulk AI meta generation. | Add-on | `KB:content-ai-plans-and-features` |
| Generate alt text with AI | Bulk/individual image alt generation. | Add-on | `KB:generate-alt-text-with-content-ai` |
| Open Graph AI tool | AI social/OG copy. | FREE(ltd) | `KB:content-ai-open-graph-tool` |
| Sentence Expander | Expand sentences. | FREE(ltd) | `KB:content-ai-sentence-expander-tool` |
| Paragraph Rewriter / Paragraph Writing | Rewrite/write paragraphs. | FREE(ltd) | `KB:content-ai-paragraph-rewriter-tool` |
| Text Summarizer | Summarize text. | FREE(ltd) | `KB:content-ai-text-summarizer-tool` |
| Spin tool | Reword content. | FREE(ltd) | `KB:content-ai-spin-tool` |
| Fix Grammar | Grammar correction. | FREE(ltd) | `KB:content-ai-fix-grammar-tool` |
| Freeform Writing | Open-ended writing assistant. | FREE(ltd) | `KB:content-ai-freeform-writing-tool` |
| RankBot AI Chatbot | In-dashboard AI chatbot. | FREE(ltd) | `KB:content-ai-plans-and-features` |
| Product Description / Product Review / Product Pros & Cons / Product Features | Ecommerce content tools. | FREE(ltd) | `KB:content-ai-product-description-tool` etc. |
| Job Description tool | HR/hiring content. | FREE(ltd) | `KB:content-ai-job-description-tool` |
| Recipe tool | Recipe content. | FREE(ltd) | `KB:content-ai-recipe-tool` |
| Testimonial tool | Testimonial content. | FREE(ltd) | `KB:content-ai-testimonial-tool` |
| Personal Bio / Company Bio | Bio generation. | FREE(ltd) | `KB:content-ai-personal-bio-tool`, `KB:content-ai-company-bio-tool` |
| Customer Persona / Content Plan / Content Calendar | Strategy & planning tools. | FREE(ltd) | `KB:create-customer-persona-with-content-ai` etc. |
| Email tool / Email Reply / Email Subject Lines / Outreach Email / Promo Email / Newsletter | Email content tools. | FREE(ltd) | `KB:content-ai-email-tool` etc. |
| Facebook Post / Facebook Comment Reply | Social tools. | FREE(ltd) | `KB:content-ai-facebook-post-tool` etc. |
| Tweet / Tweet Reply / Instagram Caption | Social tools. | FREE(ltd) | `KB:content-ai-tweet-tool` etc. |
| YouTube Video Script / YouTube Video Description | Video content tools. | FREE(ltd) | `KB:content-ai-youtube-video-script-tool` etc. |
| LinkedIn Bio | LinkedIn bio generation. | FREE(ltd) | `KB:write-linkedin-bio-with-content-ai` |
| Podcast Episode Outline | Podcast outline generation. | FREE(ltd) | `KB:content-ai-podcast-episode-outline-tool` |
| AIDA / PAS / BAB frameworks | Copywriting framework tools. | FREE(ltd) | `KB:content-ai-aida-tool`, `KB:content-ai-pas-tool`, `KB:content-ai-bab-tool` |
| Hero tool / Frequently Asked Questions tool / Analogy tool | Landing-page helpers. | FREE(ltd) | `KB:content-ai-hero-tool` etc. |
| Content AI History | Access previously generated content. | FREE(ltd) | `KB:content-ai-history` |
| Content AI global settings | Configure Content AI site-wide. | FREE | `KB:configure-content-ai-global-settings` |
| Content AI in Block Editor / Classic Editor / Elementor / Divi | Editor integrations. | FREE(ltd) | `KB:using-content-ai-in-block-editor` etc. |

---

## 2. Metadata & Head Control

### 2.1 Snippet & meta editing

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Control the SEO title | Per-post SEO title editing. | FREE | H | `FvP` |
| Control the meta description | Per-post meta description editing. | FREE | H | `FvP` |
| Snippet preview / Post Preview on Google | Live SERP snippet preview. | FREE | H | `FvP` |
| Social previews | Preview Facebook/Twitter card appearance. | FREE | H | `FvP` |
| Bulk edit titles & descriptions | Edit title/description across many posts. | FREE | H | `FvP` |
| Auto add additional meta data | Auto-injects supplementary meta tags. | FREE | H | `FvP` |
| Custom Fields (per post type) | Choose custom fields exposed in the meta box. | FREE | H | `KB:titles-and-meta` |
| Add SEO Controls (user roles) | Show Rank Math controls to selected roles. | FREE | H | `KB:titles-and-meta` |
| Bulk Editing (per post type) | Enable bulk SEO editing per post type. | FREE | H | `KB:titles-and-meta` |
| Remove Snippet Data (taxonomies) | Remove snippet data from archive templates. | FREE | L | `KB:titles-and-meta` |
| Custom HTML/verification meta tags | Add custom HTML head meta tags. | FREE | M | `KB:verify-site-custom-html-meta-tags` |

### 2.2 Templates, variables & global meta

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| SEO title & description templates | Dynamic title/description templates per post type/taxonomy. | FREE | H | `KB:titles-and-meta` |
| Variables (title/description/schema) | ~53 dynamic variables (see list below). | FREE | H | `KB:variables-in-seo-title-description` |
| Variable: Random Word | Random-word variable. | PRO | H | `KB:variables-in-seo-title-description` |
| Variable: Image Alt | Current image alt as a variable. | PRO | H | `KB:variables-in-seo-title-description` |
| Variable: Image Title | Current image title as a variable. | PRO | H | `KB:variables-in-seo-title-description` |
| Custom variables (code) | Register custom variables via code. | FREE | H | `KB:variables-in-seo-title-description` |
| Separator character | Global title separator. | FREE | H | `FvP` |
| Capitalize titles | Auto-capitalize titles. | FREE | H | `FvP` |
| Rewrite titles | Rewrite titles globally. | FREE | H | `KB:titles-and-meta` |
| Modify global meta | Edit global meta output. | FREE | H | `FvP` |
| Strip category base | Remove `/category/` from URLs. | FREE | H | `FvP` |
| Redirect attachments | Redirect attachment URLs to parent. | FREE | H | `KB:general-settings` |
| Auto canonical URLs | Automatic canonical tags. | FREE | H | `FvP` |
| Custom canonical URL | Override canonical per post. | FREE | H | `FvP` |
| Knowledge Graph meta | Output Knowledge Graph meta tags. | FREE | H | `SS` |
| Represent site as a Person | Site identity as a person. | FREE | H | `FvP` |
| Represent site as a Company | Site identity as a company/organization. | FREE | H | `FvP` |

**Variables supported** (`H`, `KB:variables-in-seo-title-description`) — FREE unless marked: Separator Character, Search Query, Counter, File Name, Site Title, Site Description, Current Date/Day/Month/Year/Time, Current Time (advanced), Organization Name, Organization Logo, Organization URL, Post Title, Post Title of Parent Page, Post Excerpt, Post URL, Post Thumbnail, Date Published, Date Modified, Post Category/Categories (+advanced), Post Tag/Tags (+advanced), Current Term, Term Description, Custom Term (advanced), Custom Term Description, Author ID, Post Author, Author Description, Post ID, Focus Keyword, Focus Keywords, Custom Field (advanced), Page, Page Number, Max Page, Post Type Name (singular/plural), Group Name, Group Description, Product's Price, Product's SKU, Product's Short Description, Product's Brand. **PRO:** Random Word, Image Alt, Image Title.

### 2.3 Robots / indexation controls

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Control ROBOTS meta | Per-post index/noindex/follow/nofollow. | FREE | H | `FvP` |
| Advanced Robots Meta | noarchive, nosnippet, noimageindex, notranslate, max-snippet, max-video-preview, max-image-preview, etc. | FREE | H | `KB:titles-and-meta` |
| Robots Meta (global / per post type / taxonomy / author / date) | Granular robots defaults everywhere. | FREE | H | `KB:titles-and-meta` |
| Noindex empty category & tag archives | Auto-noindex empty archives. | FREE | H | `KB:titles-and-meta` |
| Noindex search results | Noindex internal search pages. | FREE | H | `KB:titles-and-meta` |
| Noindex subpages | Noindex subpages. | FREE | H | `KB:titles-and-meta` |
| Noindex paginated single pages | Noindex paginated single pages. | FREE | H | `KB:titles-and-meta` |
| Noindex password-protected pages | Noindex password-protected content. | PRO | H | `FvP` |
| Robots meta vs X-Robots | X-Robots tag support guidance. | FREE | H | `KB:robots-meta-tag-vs-x-robots` |
| Noindex paginated/archive/search pages | Prevent paginated/archive/search URLs from indexing. | FREE | M | `U50` |
| rel=next / rel=prev tags | Pagination relationship tags. | FREE | M | `U50` |

### 2.4 Social meta

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Social Media Optimization | OpenGraph + Twitter meta output. | FREE | H | `FvP` |
| Auto Facebook Open Graph | Automatic OG tags per post. | FREE | H | `FvP` |
| FB Open Graph for Homepage | Homepage OG tags. | FREE | H | `FvP` |
| Facebook Authorship | Facebook authorship markup. | FREE | H | `FvP` |
| Facebook Admin / App / Secret settings | FB app credentials for insights/cache. | FREE | H | `KB:titles-and-meta` |
| Automatic Twitter Meta Cards | Auto Twitter card tags. | FREE | H | `FvP` |
| Twitter Card for Homepage | Homepage Twitter card. | FREE | H | `FvP` |
| Default Twitter Card Type | Choose card type globally. | FREE | H | `FvP` |
| Twitter Username | Site Twitter handle. | FREE | H | `KB:titles-and-meta` |
| Additional Profiles | List additional social profiles. | FREE | H | `KB:titles-and-meta` |
| Default OpenGraph Thumbnail | Fallback share image. | FREE | H | `FvP` |
| Default Share Image | Global default share image. | FREE | H | `FvP` |
| Add Overlay Icons On Social Images | Play/GIF overlay icons on share images. | FREE | H | `FvP` |
| Watermarked social images | Watermark images shared on social. | PRO | H | `FvP`, `U50` |
| Default Thumbnail Watermark | Auto-watermark the default thumbnail. | PRO | H | `KB:titles-and-meta` |
| Slack Enhanced Sharing | Slack unfurl enhancement. | FREE | H | `KB:titles-and-meta` |
| Automatic flushing of Facebook thumbnails | Clears FB image cache on update. | FREE | M | `U50` |

### 2.5 Archives, authors, misc pages

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Homepage title/description/robots/social | Homepage Title & Meta settings. | FREE | H | `KB:titles-and-meta` |
| Author archive title/description/robots + author base | Control author archives and author base. | FREE | H | `KB:titles-and-meta` |
| Date archive title/description/robots | Control date archives. | FREE | H | `KB:titles-and-meta` |
| 404 title | Custom 404 title. | FREE | H | `KB:titles-and-meta` |
| Search results title | Custom search results title. | FREE | H | `KB:titles-and-meta` |
| Optimize archive pages | Archive SEO controls. | FREE | H | `FvP` |
| Optimize author archive pages | Author archive SEO controls. | FREE | H | `FvP` |
| Category / Tag / Product Category / Product Tag archive meta | Archive titles, descriptions, robots per taxonomy. | FREE | H | `KB:titles-and-meta` |
| Forum / Topic / Reply post-type & archive meta | bbPress post-type SEO controls. | FREE | H | `KB:titles-and-meta` |
| Downloads (EDD) post-type & archive meta | EDD SEO controls. | FREE | H | `KB:titles-and-meta` |
| RM Locations post-type & taxonomy meta | Local-locations post type SEO (all sub-options). | PRO | H | `KB:titles-and-meta` |

### 2.6 Webmaster verification

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Search Engine Verification Tools | Meta verification hub. | FREE | H | `FvP` |
| Google / Bing / Baidu / Yandex / Alexa / Pinterest / Norton Safe Web site verification | Individual verification methods. | FREE | H | `FvP` |

---

## 3. Schema & Structured Data

### 3.1 Schema generator controls

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Schema Generator (most advanced) | Structured-data generator in editor. | FREE | H | `FvP`, `KB:rich-snippets` |
| 18 Pre-defined Schema Types | Built-in schema types (free). | FREE | H | `FvP`, `KB:rich-snippets` |
| 6 Extra Schema Types | Extra schema types added by PRO. | PRO | H | `FvP` |
| 840+ Schema Types Supported | Access to the full schema.org type set in the builder. | PRO | H | `FvP` |
| Default Schema per post type | Set default schema type per post type. | FREE | H | `KB:rich-snippets` |
| Add multiple Schema types per page | Stack multiple schema graphs on one page. | PRO | H | `FvP`, `KB:multiple-schema-types` |
| Custom Schema Builder | Build custom JSON-LD with properties/property groups/hierarchies. | PRO | H | `FvP`, `KB:schema-generator` |
| Add custom Schema using JSON-LD/HTML | Paste custom JSON-LD/HTML schema. | PRO | H | `FvP` |
| Schema Templates | Reusable schema templates with a library. | PRO | H | `FvP`, `KB:schema-templates` |
| Schema display conditions | Show/hide schema by singular/archives/entire site + inclusion/exclusion rules. | PRO | H | `FvP`, `KB:schema-templates` |
| Automate schema implementation | Automate schema across the site via templates/conditions. | PRO | H | `FvP` |
| Code validation (validate Schema with Google) | Validate JSON-LD via Google Rich Results test from dashboard. | PRO | H | `FvP`, `U50` |
| Import Schema from URL | Import schema from any webpage URL. | PRO | H | `FvP`, `U50` |
| Import Schema from HTML source | Import schema from HTML. | PRO | H | `U50`, `KB:import-schema-from-html` |
| Import Schema from JSON-LD markup | Import raw JSON-LD. | PRO | H | `U50`, `KB:import-schema-markup` |
| Advanced Schema Editor | Property groups, hierarchies, duplicate/delete. | PRO | H | `KB:rich-snippets` |
| Schema search in generator | Search schema types quickly. | FREE | H | `KB:rich-snippets` |
| ACF fields in Schema Generator | Use ACF values as schema variables. | FREE | M | `KB:how-to-use-acf-fields-in-schema-generator` |
| Automate FAQ schema with ACF repeater fields | Generate FAQ schema from ACF repeater. | FREE | M | `KB:automate-faq-schema-with-acf-repeater-fields` |

### 3.2 Schema types — FREE (per official KB list)

`H`, `KB:rich-snippets`. Each row: Name — what it does — Tier — Source.

| Schema type | Purpose | Tier |
|---|---|---|
| None | Disable schema | FREE |
| Article / Blog Posting / News Article | Editorial content markup | FREE |
| Book | Book markup | FREE |
| CollectionPage | Collection page markup | FREE |
| Course | Course markup | FREE |
| Event | Event markup (15+ event types) | FREE |
| FAQ Schema | FAQ rich results | FREE |
| HowTo Schema | HowTo rich results | FREE |
| Job Posting | Job listing markup | FREE |
| Music | Music markup | FREE |
| Person / Person or Organization | Entity markup | FREE |
| Product | Product markup (name, SKU, inventory, price) | FREE |
| ProfilePage | Profile page markup | FREE |
| Recipe | Recipe markup | FREE |
| Restaurant | Restaurant markup | FREE |
| Service | Service markup | FREE |
| Software Application | App/software markup | FREE |
| Video | Video schema | FREE |
| WebPage | Web page markup | FREE |
| WebSite | Website markup | FREE |
| Breadcrumb Schema | Breadcrumb list markup | FREE |
| Easy Digital Downloads Schema | EDD product schema | FREE |
| Local SEO Schema | LocalBusiness schema | FREE |
| Sitelinks Search Box Schema | Sitelinks searchbox | FREE |
| WooCommerce Schema | WooCommerce product schema | FREE |
| SiteNavigationElement Schema | Site navigation markup | FREE |

### 3.3 Schema types — PRO (per official KB list)

`H`, `KB:rich-snippets`.

| Schema type | Purpose | Tier |
|---|---|---|
| Dataset | Dataset markup | PRO |
| FactCheck (Claim Review) | Fact-check markup | PRO |
| Movie | Movie markup | PRO |
| Podcast Episode | Podcast episode markup | PRO |
| About and Mentions | About/Mentions entities for links | PRO |
| ItemList Schema | ItemList markup | PRO |
| Carousel Schema | Carousel rich result | PRO |
| Q&A Page Schema | Q&A page markup | PRO |
| Speakable Schema | Voice-assistant speakable markup | PRO |

### 3.4 Schema blocks & local schema

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| FAQ Schema Block (Gutenberg) | Add schema-ready FAQ accordion block. | FREE | H | `SS`, `KB:faq-schema-block` |
| HowTo Schema Block (Gutenberg) | Add schema-ready HowTo block. | FREE | H | `SS`, `KB:howto-schema` |
| Local Business Schema | LocalBusiness schema with 193 business types. | FREE | H | `SS`, `FvP` |
| Multiple schema options / advanced HowTo options | Extended schema fields. | PRO | H | `FvP` |
| Automatic Q&A Schema for bbPress | Auto Q&A schema for bbPress. | PRO | H | `FvP` |
| Automatic Video Detection for Video Schema | Auto-detect videos for schema. | PRO | H | `FvP` |
| Automatic Video Data Fill | Auto-fill video schema data. | PRO | H | `FvP` |
| Generate Video Schema for old posts | DB tool to backfill video schema. | PRO | H | `FvP`, `U50` |
| Schema markup validator | Validate schema (see also Section 4/8). | PRO | H | `KB:schema-markup-validator` |
| Schema selection guide | Guidance on choosing schema. | FREE | H | `KB:schema-selection-guide` |

---

## 4. Technical SEO

### 4.1 Sitemaps

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Powerful XML Sitemap | Search-engine-compatible XML sitemaps, auto-updated. | FREE | H | `FvP`, `KB:configure-sitemaps` |
| Sitemap configuration | Include/exclude post types, taxonomies, individual posts. | FREE | H | `KB:configure-sitemaps` |
| Per-post-type sitemap index | Separate sitemaps for Posts, Pages, Categories, Tags, Products, Forums, Topics, Replies, Downloads, RM Locations. | FREE | H | `KB:configure-sitemaps` |
| Include images in sitemap | Add featured/content images to sitemaps. | FREE | H | `KB:configure-sitemaps` |
| HTML Sitemap | Human-readable HTML sitemap (shortcode/page, sort, dates). | FREE | H | `KB:html-sitemap` |
| KML Sitemap | Geo sitemap for local business locations. | FREE | H | `KB:kml-sitemap` |
| Custom Sitemap | Add custom URLs via child theme function. | FREE | H | `KB:custom-sitemaps` |
| Google News SEO Sitemap | News sitemap for Google News. | PRO | H | `FvP`, `KB:news-sitemap` |
| Google News publication name / News post type / exclude post terms | News sitemap config. | PRO | H | `KB:news-sitemap` |
| Googlebot-News index (per post) | Mark posts for Googlebot-News. | PRO | H | `KB:news-sitemap` |
| Google Video SEO Sitemap | Video sitemap. | PRO | H | `FvP`, `KB:video-sitemap` |
| Video sitemap config (post types, YouTube API key, custom fields) | Video sitemap settings. | PRO | H | `KB:video-sitemap` |
| Include ACF images in sitemap | Add ACF field images to sitemaps. | PRO | H | `KB:advanced-custom-fields` |
| Sitemap submission to Google/Bing | Submit sitemaps to search engines. | FREE | H | `KB:submit-sitemap-to-google` |
| Sitemaps ping | Ping search engines on update. | FREE | M | `KB:configure-sitemaps` |

### 4.2 robots.txt / llms.txt / .htaccess

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| robots.txt Editor | Edit robots.txt from the dashboard. | FREE | H | `FvP`, `KB:how-to-edit-robots-txt-with-rank-math` |
| robots.txt rules + validator | Default rules and syntax help + tester. | FREE | H | `KB:how-to-edit-robots-txt-with-rank-math` |
| Advanced llms.txt Generator | Generate an llms.txt file for AI assistants. | FREE | H | `FvP`, `KB:llms-txt` |
| llms.txt config (post types, taxonomies, limit, additional content, preview) | Configure llms.txt output. | FREE | H | `KB:llms-txt` |
| .htaccess Editor | Edit .htaccess from dashboard. | FREE | H | `FvP` |
| Add sitemaps to robots.txt | Include sitemap directive in robots.txt. | FREE | H | `KB:add-sitemaps-to-robots-txt` |

### 4.3 Redirections

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Advanced Redirection Manager | Manage redirects in WordPress. | FREE | H | `FvP`, `KB:setting-up-redirections` |
| Redirection types 301 / 302 / 307 / 410 / 451 | HTTP redirect/status types. | FREE | H | `FvP` |
| Support for Regex | Regex redirect matching. | FREE | H | `FvP` |
| Match types: Exact / Contains / Start With / End With / Regex / Ignore Case | Source-matching options. | FREE | H | `KB:setting-up-redirections` |
| Multiple sources per redirect | Combine source URLs. | FREE | H | `KB:setting-up-redirections` |
| Destination URL + maintenance code | Redirect destination and maintenance codes. | FREE | H | `KB:setting-up-redirections` |
| Debug Redirections | Built-in redirect debugger. | FREE | H | `FvP`, `KB:setting-up-redirections` |
| Redirect Attachments to Parent | Redirect attachment URLs to parent post. | FREE | H | `FvP` |
| Redirect Orphan Attachments | Redirect orphan attachments. | FREE | H | `KB:general-settings` |
| Smart & Automatic Post Redirects | Auto-redirect when a post URL changes. | FREE | H | `FvP` |
| Redirection statistics | Redirect hit stats. | FREE | H | `KB:setting-up-redirections` |
| Bulk actions in redirects | Bulk activate/deactivate/delete. | FREE | H | `KB:setting-up-redirections` |
| Backing up redirects | Backup redirect rules. | FREE | H | `KB:setting-up-redirections` |
| Advanced Redirections Module | Extra PRO redirection capabilities. | PRO | H | `FvP` |
| Scheduled Activation / Deactivation | Schedule redirect start/end. | PRO | H | `KB:setting-up-redirections` |
| Organizing / filtering redirections | Organize and filter redirect sets. | PRO | H | `KB:setting-up-redirections` |
| Redirections for parameterized URLs | Redirect URLs with parameters. | PRO | H | `KB:setting-up-redirections` |
| Export redirects as CSV | Export redirects. | PRO | H | `KB:setting-up-redirections` |
| Import redirects from CSV | Bulk CSV import. | PRO | H | `KB:import-redirects` |
| Sync redirections to .htaccess | Write redirects to .htaccess. | PRO | H | `FvP` |

### 4.4 404 monitoring

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Simple 404 Monitor | Logs URI + access time + hit counts. | FREE | H | `FvP`, `KB:advanced-404-monitor` |
| Advanced 404 Monitor | Adds referer, user-agent (OS/browser/version), grouping by hits. | PRO | H | `FvP`, `KB:advanced-404-monitor` |
| Export 404 Log | Export the 404 log. | PRO | H | `FvP` |
| 404 → redirect workflow | Turn 404s into redirects. | FREE | M | `KB:setting-up-redirections` |
| Bulk set 410 status | Set 410 in bulk. | FREE | M | `KB:set-410-status-in-bulk` |

### 4.5 Instant indexing (IndexNow / Google Indexing API)

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Instant Indexing module (IndexNow) | Submit URLs to IndexNow-participating engines. | FREE | H | `KB:instant-indexing` |
| Automatic URL submission | Auto-submit new/updated URLs. | FREE | H | `KB:how-to-use-indexnow` |
| Manual URL submission (single + batch) | Submit individual or batched URLs. | FREE | H | `KB:how-to-use-indexnow` |
| Instant Indexing bulk action | Submit pages from the posts list. | FREE | H | `KB:how-to-use-indexnow`, `U50` |
| API key management | Change/verify IndexNow API key. | FREE | H | `KB:how-to-use-indexnow` |
| Submission history | View submission history. | FREE | H | `KB:how-to-use-indexnow` |
| Google Indexing API support | Support for Google's Indexing API. | FREE | M | `SS` (Google Indexing API page) |
| Submit-now from Index Status | Trigger Instant Indexing from Analytics Index Status. | PRO | H | `KB:analytics` |

### 4.6 Image SEO

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Automated Image SEO | Dynamically add missing ALT/title attributes. | FREE | H | `FvP`, `KB:image-seo` |
| Add Missing ALT Attributes + ALT format | Generate ALT text with variables. | FREE | H | `KB:image-seo` |
| Add Missing Title Attributes + format | Generate title attributes. | FREE | H | `KB:image-seo` |
| Image SEO variable library (39+ variables) | Variables for alt/title/caption/description. | FREE | H | `KB:image-seo` |
| Advanced Automated Image SEO Options | Extended image SEO controls. | PRO | H | `FvP` |
| Add Missing Image Caption + caption format | Auto captions. | PRO | H | `KB:image-seo` |
| Add Missing Image Description + format | Auto descriptions. | PRO | H | `KB:image-seo` |
| Change Title / ALT / Description / Caption casing | Case conversion per field. | PRO | H | `KB:image-seo` |
| Add ALT attributes for avatars | Auto alt for avatars. | PRO | H | `KB:image-seo` |
| Replacements (find & replace words) | Replace words in image fields. | PRO | H | `KB:general-settings` |
| Find & Replace Image alt/title/caption text | Bulk find/replace image metadata. | PRO | H | `FvP` |
| Automate Image Captions | Automate caption generation. | PRO | H | `FvP` |
| Advanced Filtering for Images | Media library filters (missing alt, missing/default title, missing caption). | FREE | M | `U50` |

---

## 5. Analytics & Reporting

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Google Search Console Integration | Pull GSC data into WordPress. | FREE | H | `FvP`, `KB:analytics` |
| Install Google Analytics code | Insert GA tracking code. | FREE | H | `FvP` |
| Advanced Google Analytics 4 Integration | GA4 data in the WordPress dashboard. | PRO | H | `FvP`, `SS` |
| Analytics Dashboard | Unified analytics dashboard with timeframe selection. | PRO | H | `FvP`, `KB:analytics` |
| Traffic source selection | Filter by traffic source (incl. AI traffic). | PRO | H | `KB:analytics` |
| AI Search Traffic Tracker | Track traffic from ChatGPT/Perplexity etc. | PRO | H | `FvP` |
| Overall optimization chart | Site-wide optimization chart. | PRO | M | `KB:analytics` |
| SEO Performance Overview Report | High-level SEO performance report. | PRO | H | `FvP` |
| Keyword Report Overview | Keywords report with clicks/impressions/CTR/position. | PRO | H | `KB:analytics` |
| Keyword Positions | Position data per keyword. | PRO | H | `KB:analytics` |
| Site Analytics | Sortable site analytics. | PRO | H | `KB:analytics` |
| Post Analytics | Per-post analytics report. | PRO | H | `FvP`, `KB:analytics` |
| Track Top 5 Winning/Losing Keywords | Best/worst keywords (last 30 days). | PRO | H | `FvP`, `U50` |
| Track Top 5 Winning/Losing Posts | Best/worst posts by search traffic. | PRO | H | `FvP`, `U50` |
| Advanced Content SEO Overview | Content SEO overview dashboard. | PRO | H | `FvP` |
| Check Ranking Keywords for Each Post | Per-post ranking keyword list. | PRO | H | `FvP` |
| Position History for Keywords & Posts | Historical ranking positions. | PRO | H | `FvP`, `U50` |
| Single Post SEO Reports | Per-post SEO performance report (keywords, speed, traffic, history). | PRO | H | `SS`, `FvP` |
| Single Post Performance Badges | Badges on top-performing posts. | PRO | H | `FvP`, `SS` |
| Track PageSpeed for Each Post & Page | PageSpeed + load time per URL in dashboard. | PRO | H | `FvP`, `SS` |
| Google AdSense Earning History | AdSense earnings in dashboard. | PRO | H | `FvP`, `KB:analytics` |
| Import GSC & GA data for a particular country | Country-filtered GSC/GA data. | PRO | H | `FvP`, `KB:analytics` |
| Google Algorithm Updates timeline | Google update markers in Analytics graphs. | PRO | H | `FvP`, `KB:google-algorithm-updates` |
| Keyword Rank Tracker | Track keyword rankings from GSC/GA source. | PRO | H | `FvP`, `U50` |
| Tracked keywords | 500 (PRO) / 10,000 (Business) / 50,000 (Agency). | PRO | H | `PRC` |
| Client Management | Manage tracked-keyword quotas across client sites. | Biz | H | `PRC`, `FvP` |
| Track Google Index Status (URL Inspection API) | Google index status in dashboard. | FREE(ltd) | H | `FvP`, `SS` |
| Index Status: Top statuses / Presence on Google | Advanced index-status aggregates. | PRO | H | `KB:analytics`, `FvP` |
| Index Status of individual posts | Per-URL index status + last crawl. | PRO | M | `KB:analytics` |
| SEO Performance Email Reports | Periodic SEO reports via email. | FREE(ltd) | H | `FvP`, `KB:seo-email-reporting` |
| Email report sections (search traffic, impressions, keywords, avg position, position summary, winning/losing posts & keywords) | Report content. | FREE/PRO | H | `KB:seo-email-reporting` |
| White Labelled Email Reports | Branded client reports (heading, logo, colors, custom CSS). | Biz | H | `FvP`, `KB:seo-email-reporting` |
| Include Only Tracked Keywords in report | Restrict report to tracked keywords. | PRO | H | `KB:seo-email-reporting` |
| Hide Email Reporting Options | Hide email report settings from roles. | FREE | M | `KB:seo-email-reporting` |
| Anonymize IP addresses | GA IP anonymization. | PRO | H | `FvP` |
| Self-Hosted Google Analytics JS File | Serve the GA JS locally. | PRO | H | `FvP` |
| Exclude logged-in users from GA tracking | Do not track logged-in users. | PRO | H | `FvP` |
| Google Data Fetch Frequency | 3 days (Free) / daily (PRO+). | FREE/PRO | H | `FvP` |
| Days to Preserve Google Data | 90 days (Free) / 180 (PRO) / no limit (Business+). | FREE/PRO | H | `FvP` |
| Email Report Frequency in Days | 30 (Free) / 15 or 30 (PRO) / 7,15,30 (Business+). | FREE/PRO | H | `FvP` |

### 5.1 AI Visibility (AI search monitoring)

`H`, `KB:track-ai-visibility` and Content AI KB. Treated as Content AI-linked functionality.

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| AI Visibility module | Monitor brand presence across AI platforms (ChatGPT etc.). | Add-on | M | `KB:track-ai-visibility` |
| Add a brand/product to track | Register entities to monitor. | Add-on | H | `KB:track-ai-visibility` |
| Overview tab | AI visibility overview. | Add-on | H | `KB:track-ai-visibility` |
| Queries tab | Tracked AI queries. | Add-on | H | `KB:track-ai-visibility` |
| Competitors tab | Competitor AI benchmarks. | Add-on | H | `KB:track-ai-visibility` |
| Raw Data/Transcripts tab | Raw AI answer transcripts. | Add-on | H | `KB:track-ai-visibility` |
| Track Brand Mentions (single, average across brands, competing brands) | Mention tracking. | Add-on | H | `KB:track-brand-mentions` |
| Track Brand Sentiment (brand, query, competitor) | Sentiment tracking in AI answers. | Add-on | H | `KB:track-brand-sentiment` |
| AI Brand Visibility Report | Generate brand visibility report. | Add-on | H | `KB:ai-brand-visibility-report` |

### 5.2 MCP tools (AI assistant access)

`H`, `KB:mcp-tools`. Module/feature access via MCP.

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Rank Math MCP module | Expose Rank Math abilities to AI assistants. | FREE | M | `KB:mcp-tools` |
| Get Settings | Retrieve Rank Math settings. | FREE | H | `KB:mcp-tools` |
| Get System Status / health report | Retrieve site health + Rank Math status. | FREE | H | `KB:mcp-tools` |
| Retrieve robots.txt / llms.txt | Read robots/llms files. | FREE | H | `KB:mcp-tools` |
| Update site identity / SEO defaults / homepage SEO / link settings | Configure site via MCP. | FREE | H | `KB:mcp-tools` |
| Enable/disable Rank Math modules | Toggle modules via MCP. | FREE | H | `KB:mcp-tools` |
| Update XML sitemap settings / auto-update prefs / post-type SEO defaults / breadcrumb settings | Configure via MCP. | FREE | H | `KB:mcp-tools` |
| Site-wide SEO audit / fix failed tests | Run and fix audits via MCP. | FREE | H | `KB:mcp-tools` |
| Competitor site SEO audit | Audit competitor sites via MCP. | PRO | H | `KB:mcp-tools` |
| Full SEO analysis on a post / retrieve schema / SEO metadata / scores / links | Per-post analysis via MCP. | FREE | H | `KB:mcp-tools` |
| Retrieve redirections / check link status | Inspect redirects & links via MCP. | FREE | H | `KB:mcp-tools` |
| Retrieve top-performing keywords (GSC) | Search Console keywords via MCP. | FREE | H | `KB:mcp-tools` |
| AI Visibility report / tracked queries / add brand | AI visibility via MCP (Content AI). | Add-on | H | `KB:mcp-tools` |
| MCP setup with ChatGPT / Claude Desktop / GitHub Copilot | Client connection guides. | FREE | H | `KB:connect-rank-math-mcp-with-chatgpt` etc. |
| MCP Tools public page | Public MCP toolkit page. | FREE | H | `SS` (Tools → MCP Tools) |

---

## 6. Verticals (WooCommerce / Local / News / Video / Podcast / EDD / Stories / AMP / bbPress)

### 6.1 WooCommerce SEO

WooCommerce rows below are `H` from `KB:woocommerce-seo-feature-comparison` (Free vs PRO table).

| Feature | What it does | Tier | Source |
|---|---|---|---|
| Remove Product Base | Drop `/product/` from product URLs. | FREE | `KB:woocommerce-seo-feature-comparison` |
| Remove Product Category Base | Drop product-category base. | FREE | same |
| Remove Parent Slugs | Remove parent slugs from product URLs. | FREE | same |
| Remove Generator Tag | Remove the WooCommerce generator tag. | FREE | same |
| Remove Schema Markup on Shop Archive | Remove shop-archive schema. | FREE | same |
| Brand Category | Product brand taxonomy. | FREE | same |
| Add Product Brand / Price / Currency / Availability | Product schema fields. | FREE | same |
| Product Gallery images in og:image tags | Gallery images in OG tags. | FREE | same |
| Include Product gallery images in the Sitemap | Gallery images in sitemap. | FREE | same |
| Exclude Hidden Product from the Sitemap | Exclude hidden products from sitemap. | FREE | same |
| New WooCommerce Product-specific variables | Product variables for meta/schema. | FREE | same |
| Include Product's Short description in content analysis | Use short description in analysis. | FREE | same |
| Option to add Custom Brand from Settings | Configure custom brands in settings. | PRO | same |
| Option to set GTIN/MPN/etc. (even for variations) | Global identifiers incl. variations. | PRO | same |
| Option to show Global Identifier on the frontend | Display GTIN on product page. | PRO | same |
| Option to Noindex the Hidden products | Noindex hidden products. | PRO | same |
| Include GTIN value in Product Schema | GTIN in product schema. | PRO | same |
| Improved Variation Product Schema (Offer per variation) | One Offer entity per variation. | PRO | same |
| Dedicated Content Analysis Tests for Product pages | Product-specific content tests. | PRO | same |
| WooCommerce SEO PRO (module bundle) | Full WooCommerce SEO feature set. | PRO | `FvP` |
| WooCommerce duplicate-content handling | Prevents duplicate content in store. | FREE | `KB:woocommerce-duplicate-content-issues` |
| GTIN migration tool for WooCommerce | DB tool for GTIN migration. | PRO | `KB:rank-math-status-and-tools` |

### 6.2 Local SEO

Local SEO settings rows `H` from `KB:local-seo`.

| Feature | What it does | Tier | Source |
|---|---|---|---|
| Local SEO module / Knowledge Graph | Local business + entity setup. | FREE | `KB:local-seo`, `FvP` |
| Person or Company / Website name / alternate name / description / logo / URL | Business identity fields. | FREE | `KB:local-seo` |
| Email / Phone number / Price range / Additional info | Contact info fields. | FREE | `KB:local-seo` |
| Address / Address format / GeoCoordinates / Google Maps API key | Address + coordinates. | FREE | `KB:local-seo` |
| Business type (193 Local Business Types) | Choose local business type. | FREE | `KB:local-seo`, `FvP` |
| Opening hours / Opening hours format | Business hours. | FREE | `KB:local-seo` |
| About Page / Contact Page | Local pages config. | FREE | `KB:local-seo` |
| Contact Info Shortcode | Output contact info with schema anywhere. | FREE | `KB:local-seo`, `FvP` |
| Use Multiple Locations | Support multiple business locations. | PRO | `KB:local-seo`, `FvP` |
| Hide Opening Hours / Closed label / Open 24-7 label / Open 24h label | Store-hours display options. | PRO | `KB:local-seo` |
| Measurement system / Map style / Maximum number of locations / Primary country / Show route label / Location detection | Store-locator display options. | PRO | `KB:local-seo` |
| All Locations Are Part of the Same Organization / Enhanced Search | Organization grouping + search. | PRO | `KB:local-seo` |
| Locations Post Type Base / Category Base / Post Type names | Locations CPT configuration. | PRO | `KB:local-seo` |
| Advanced Local SEO Blocks | Local business Gutenberg blocks. | PRO | `FvP` |
| Local Business Block (type, address, hours, map, store locator, locations, max locations) | Frontend location block. | PRO | `KB:local-business-block` |
| Multiple Location Schema via block/shortcode | Multiple-location schema output. | PRO | `U50` |
| LocalBusiness schema in Elementor / Divi | Builder integration for local schema. | PRO | `KB:add-localbusiness-schema-in-elementor`, `KB:add-localbusiness-schema-in-divi` |
| Multiple areaServed cities | Add multiple served cities. | FREE | `KB:add-multiple-areaserved-cities-to-localbusiness-schema` |
| KML location sitemap | Geo sitemap for locations. | FREE | `KB:kml-sitemap` |

### 6.3 News / Video / Podcast

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Google News SEO Sitemap | News sitemap. | PRO | H | `FvP`, `KB:news-sitemap` |
| News publication name / news post type / exclude terms / Googlebot-News | News config. | PRO | H | `KB:news-sitemap` |
| Google Video SEO Sitemap | Video sitemap. | PRO | H | `FvP`, `KB:video-sitemap` |
| Video sitemap config (hide sitemap, post types, YouTube API key, custom fields) | Video sitemap settings. | PRO | H | `KB:video-sitemap` |
| Video Schema + auto-detection + auto data fill | Video structured data. | FREE/PRO | H | `FvP`, `KB:video-schema` |
| Podcast Module | Podcast schema + podcast RSS feed + frontend display. | PRO | H | `FvP`, `KB:podcast` |
| Podcast settings (name, description, owner, category, image, tracking prefix, explicit, copyright) | Podcast feed config. | PRO | H | `KB:podcast` |
| Podcast Episode Schema (name, description, author, duration, URL, image, audio file, season/episode numbers) | Episode markup. | PRO | H | `KB:podcast` |
| Podcast RSS Feed | Podcast feed output. | PRO | H | `KB:podcast` |

### 6.4 EDD / Web Stories / AMP / bbPress / BuddyPress

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Easy Digital Downloads schema | EDD product schema. | FREE | H | `KB:rich-snippets`, `KB:edd-product-schema` |
| Complete EDD SEO | Full EDD SEO feature set. | PRO | H | `FvP` |
| Compatible with EDD | EDD compatibility. | FREE | H | `FvP` |
| Google Web Stories module | SEO metadata for Web Stories. | FREE | H | `KB:google-web-stories` |
| Web Stories metadata configuration | Per-story meta titles/descriptions. | FREE | H | `KB:google-web-stories` |
| AMP module | Output correct metadata/schema for AMP. | FREE | H | `KB:using-amp-with-rankmath` |
| AMP plugin support (AMP for WordPress, AMP for WP, weeblrAMP, AMP for WooCommerce, WP AMP) | Supported AMP plugins. | FREE | H | `KB:using-amp-with-rankmath` |
| bbPress post-type SEO (Forums/Topics/Replies) | SEO controls for bbPress content. | FREE | H | `KB:titles-and-meta` |
| Automatic Q&A Schema for bbPress | Auto Q&A schema. | PRO | H | `FvP` |
| BuddyPress variables | BuddyPress-specific schema variables. | FREE | M | `KB:rich-snippets` |

---

## 7. Integrations

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Complete Elementor Integration | Elementor SEO panel + integration. | FREE | H | `FvP` |
| Enable Rank Math on Elementor templates | SEO for Elementor templates. | FREE | H | `KB:enable-rank-math-on-elementor-templates` |
| Dedicated Elementor Breadcrumbs Widget | Elementor breadcrumbs widget. | PRO | H | `FvP` |
| Elementor Accordion Widget → FAQ Schema | Convert Elementor accordion to FAQ schema. | PRO | H | `FvP`, `KB:faq-schema-elementor` |
| Complete Divi Integration | Divi SEO panel + integration. | FREE | H | `FvP` |
| Divi Accordion Widget → FAQ Schema | Convert Divi accordion to FAQ schema. | PRO | H | `FvP` |
| Block Editor (Gutenberg) integration | Meta box + blocks in Gutenberg. | FREE | H | `KB:using-content-ai-in-block-editor` |
| Classic Editor integration | Meta box in classic editor. | FREE | H | `KB:using-content-ai-in-classic-editor` |
| Advanced Custom Fields (ACF) module | Use ACF in SEO meta/schema. | FREE | H | `KB:advanced-custom-fields` |
| ACF for focus keywords | Use ACF to set focus keywords. | FREE | M | `KB:acf-for-focus-keywords` |
| ACF images in sitemap | ACF images in sitemap. | PRO | H | `KB:advanced-custom-fields` |
| Google Search Console | GSC connection. | FREE | H | `FvP` |
| Google Analytics 4 | GA4 connection. | PRO | H | `FvP` |
| Google AdSense | AdSense earnings integration. | PRO | H | `FvP` |
| Google Trends | Trends integration. | PRO | H | `FvP` |
| Google Indexing API | Indexing API support. | FREE | M | `SS` |
| MCP / AI assistants (ChatGPT, Claude Desktop, GitHub Copilot) | AI assistant integration. | FREE | H | `KB:mcp-tools` |
| WP Rocket integration | Install/use WP Rocket via Rank Math. | PRO | M | `KB:how-to-install-wp-rocket` |
| Imagify install via Rank Math PRO | Image optimization integration. | PRO | M | `KB:install-imagify-plugin` |
| Rank Math + WP Rocket bundle | Bundled WP Rocket licence. | Add-on | H | `PRC` |
| WPML / TranslatePress compatibility | Multilingual plugin compatibility. | FREE | H | `COMP` |
| Crocoblock / JetEngine compatibility | Dynamic content compatibility. | FREE | H | `COMP` |
| Bricks/other page builders & themes (Divi, Betheme, Brizy, SiteOrigin, Themify, Bricks?, etc.) | Certified-compatible products list. | FREE | H | `COMP` |
| WooCommerce | Store integration. | FREE | H | `COMP` |
| GeoDirectory, LearnPress, Modern Events Calendar, Ultimate Blocks, Stackable, FooGallery, Admin Columns, Joli TOC, Media Cleaner, Shoptimizer (etc.) | Certified compatible plugins/themes (~40 listed). | FREE | H | `COMP` |
| Headless CMS support (REST API endpoints) | Expose SEO metadata via REST for headless. | FREE | H | `KB:headless-cms-support` |
| Table of Contents Block | Rank Math TOC Gutenberg block. | FREE | H | `KB:table-of-contents-block` |
| Related Posts Block (Gutenberg) | Related posts block. | PRO | M | `KB:customize-related-posts` |
| Related Posts Shortcode | Related posts shortcode. | PRO | M | `KB:customize-related-posts` |
| Local Business Info Block | Local business Gutenberg block. | PRO | H | `SS`, `KB:local-business-block` |
| FAQ Schema Block | FAQ block with schema. | FREE | H | `KB:faq-schema-block` |
| HowTo Schema Block | HowTo block with schema. | FREE | H | `SS` |
| Breadcrumbs function | Theme-callable breadcrumbs. | FREE | H | `KB:breadcrumbs` |
| Breadcrumbs customization (separator, homepage, prefix, archive/search/404 labels, categories, blog page) | Breadcrumb display options. | FREE | H | `KB:breadcrumbs`, `KB:general-settings` |

---

## 8. Admin UX, Tools & Governance

| Feature | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| Simple Setup Wizard | Guided initial configuration. | FREE | H | `FvP`, `KB:how-to-setup` |
| Custom Setup Wizard Mode | Advanced/custom wizard path. | PRO | H | `FvP` |
| Optimal settings pre-selected | Sensible defaults. | FREE | H | `FvP` |
| Clean, simple user interface | Admin UI. | FREE | H | `FvP` |
| Compatibility check | Detects conflicting plugins/settings. | FREE | H | `FvP`, `KB:how-to-setup` |
| Module-based system (enable/disable modules) | Toggle modules on/off. | FREE | H | `FvP`, `KB:managing-modules` |
| SEO Score shown to visitors | Display frontend SEO score. | FREE | H | `KB:general-settings` |
| SEO Score post types / template / position | Frontend score-block config. | FREE | H | `KB:general-settings` |
| Dashboard widgets / rank math column | SEO score column in post lists. | FREE | M | `KB:titles-and-meta` |
| Advanced Bulk Edit Options | Bulk noindex/index, nofollow/follow, remove canonical, add/remove redirect, set/remove schema. | PRO | H | `FvP`, `U50` |
| Bulk actions: AI write SEO title/description, AI alt text, determine search intent, Instant Indexing submit | Bulk action list. | FREE/PRO | H | `U50` |
| Advanced Quick Edit Options | Quick-edit SEO title, description, primary category, focus keyword, canonical, robots. | PRO | H | `FvP`, `SS` |
| Advanced Post Filtering | Filter posts by SEO score, no focus keyword, noindexed, custom canonical/title/description, redirected, orphan, schema type. | PRO | H | `FvP`, `U50` |
| Import/Export Settings | Export/import general, titles/meta, sitemap, role manager, redirections settings; backups. | FREE | H | `FvP`, `KB:import-export-settings` |
| Import SEO Data via CSV file | CSV import of SEO metadata. | PRO | H | `FvP` |
| Import/Export Focus Keywords | CSV focus-keyword import/export. | PRO | H | `FvP`, `KB:export-keywords` |
| Complete Import/Export Options | Full export/import suite. | PRO | H | `FvP` |
| Role Manager | Control Rank Math capabilities per user role; reset settings. | FREE | H | `FvP`, `KB:role-manager` |
| Version Control (native rollback) | Roll back Rank Math to a previous version. | FREE | H | `FvP`, `KB:version-control` |
| Beta updates toggle | Opt into beta versions. | FREE | H | `KB:version-control` |
| Auto-update preferences | Enable/disable automatic updates. | FREE | H | `KB:version-control` |
| Database Tools (see list below) | Maintenance utilities. | FREE | H | `KB:rank-math-status-and-tools` |
| System Status: System Info | Environment/plugin info report. | FREE | H | `KB:rank-math-status-and-tools` |
| System Status: Error Log | Error log viewer. | FREE | H | `KB:rank-math-status-and-tools` |
| Contextual Help | In-context documentation. | FREE | H | `FvP` |
| Detailed Documentation | KB documentation. | FREE | H | `FvP` |
| Multisite Compatible | WordPress multisite support. | FREE | H | `FvP` |
| RSS Optimization | RSS feed optimization. | FREE | H | `FvP` |
| Add Content before/after RSS Feed | Inject content into RSS feed. | FREE | H | `FvP` |
| Notify on plugin update available | Email/notice on updates. | FREE | H | `FvP` |
| PHP-FIG coding standards | Code quality claim. | FREE | H | `FvP` |
| Rank Math Vault (share credentials) | Securely share site credentials with support. | FREE | M | `KB:how-to-use-vault` |

### 8.1 Database Tools inventory

`H`, `KB:rank-math-status-and-tools`.

| DB Tool | What it does | Tier |
|---|---|---|
| Flush SEO Analysis Data | Clear SEO analysis cache. | FREE |
| Remove Rank Math Transients | Delete transients. | FREE |
| Purge Analytics Cache | Clear analytics cache. | FREE |
| Rebuild Index for Analytics | Rebuild analytics index. | FREE |
| Clear 404 Monitor Log | Clear 404 log. | FREE |
| Re-create Missing Database Tables | Rebuild DB tables. | FREE |
| Generate Video Schema for Old Posts/Pages | Backfill video schema. | PRO |
| Fix Analytics Table Collations | Repair collations. | FREE |
| Yoast Block Converter | Convert Yoast blocks to Rank Math. | FREE |
| Delete Internal Links Data | Clear internal-link data. | FREE |
| Delete Redirection Rules | Remove redirect rules. | FREE |
| Delete Old Schema Data | Clear legacy schema. | FREE |
| Update SEO Scores | Recompute scores. | FREE |
| Cancel Content AI Bulk Editing Process | Stop a bulk AI job. | FREE |
| GTIN Migration Tool for WooCommerce | Migrate GTIN data. | PRO |

---

## 9. Migration & Importer Tools

| Feature / Importer | What it does | Tier | Conf | Source |
|---|---|---|---|---|
| 1-Click Import from Yoast SEO | Import Yoast settings/meta. | FREE | H | `FvP` |
| 1-Click Import from All in One SEO (AIO SEO) | Import AIOSEO settings/meta. | FREE | H | `FvP` |
| 1-Click Import from SEOPress | Import SEOPress settings/meta. | FREE | H | `FvP` |
| Import AIO Schema Rich Snippets | Import AIOSEO schema. | FREE | H | `FvP` |
| Import from Redirection plugin | Import redirect rules from the Redirection plugin. | FREE | H | `FvP`, `KB:import-redirection-plugin-data` |
| Import from Yoast SEO Premium redirects | Import Yoast Premium redirects. | FREE | H | `KB:import-redirects` |
| Plugin Importers section | Central importer UI. | FREE | H | `KB:import-export-settings` |
| Bulk import redirects (Redirections Manager) | Bulk redirect import. | FREE | H | `KB:import-redirects` |
| Import Redirections Data via CSV File | CSV redirect import. | PRO | H | `FvP` |
| Import SEO Data via CSV File | CSV SEO metadata import. | PRO | H | `FvP` |
| Import/Export Focus Keywords | CSV keyword import/export. | PRO | H | `FvP` |
| Export 404 Log | CSV 404 export. | PRO | H | `FvP` |
| Export Redirections CSV | CSV redirect export. | PRO | H | `KB:setting-up-redirections` |
| Complete Import/Export Options | Full settings import/export. | PRO | H | `FvP` |
| Import Schema PRO data | Import schema from external/Custom sources. | PRO | H | `KB:import-schema-pro-data` |
| Import All-in-One Schema data | Import AIOSEO schema. | FREE | H | `KB:import-all-in-one-schema-data` |
| Yoast Block Converter (DB tool) | Convert Yoast blocks in content. | FREE | H | `KB:rank-math-status-and-tools` |
| Convert Yoast/AIOSEO TOC blocks | Convert existing TOC blocks. | FREE | M | `KB:table-of-contents-block` |

---

## 10. Support, Licensing & Plan Limits

`H`, `PRC` and `FvP` unless noted.

### 10.1 Plans & pricing (displayed 2026-09-16)

| Plan | Price (per month, billed annually, ex VAT) | Renewal | Personal websites | Client sites | Tracked keywords | Content AI trial |
|---|---|---|---|---|---|---|
| Rank Math PRO | €7.99 | €8.99/mo | Unlimited personal | Excluded | 500 | Starter (15 days) |
| Rank Math Business | €24.99 | €27.99/mo | — | 100 | 10,000 | Creator (15 days) |
| Rank Math Agency | €54.99 | €64.99/mo | — | 500 | 50,000 | Expert (15 days) |
| Rank Math + WP Rocket | €11.99 | €13.99/mo | Unlimited personal | Excluded | 500 | Starter |

### 10.2 Plan-gated limits & support

| Item | Free | PRO | Business | Agency | Conf | Source |
|---|---|---|---|---|---|---|
| Google data fetch frequency | 3 days | Daily | Daily | Daily | H | `FvP` |
| Days to preserve Google data | 90 days | 180 days | No limit | No limit | H | `FvP` |
| Email report frequency (days) | 30 | 15 or 30 | 7, 15 or 30 | 7, 15 or 30 | H | `FvP` |
| White-labelled email reports | No | No | Yes | Yes | H | `FvP` |
| Client Management | No | No | Yes | Yes | H | `FvP` |
| Support | Community/standard | 24/7 | 24/7 Priority | 24/7 Priority | H | `PRC` |
| Dedicated Premium Support | No | Yes | Yes | Yes | H | `FvP` |
| 30-day money-back guarantee | — | Yes | Yes | Yes | H | `PRC` |
| Exclusive Facebook Club | — | Yes | Yes | Yes | M | `FvP` |
| One-click automatic updates | — | Yes | Yes | Yes | M | `FvP` |
| Fastest SEO plugin / enterprise-level features | — | Yes | Yes | Yes | M | `FvP` |
| Non-profit discount | Available (per KB) | — | — | — | M | `KB:nonprofit-discount` |
| Upgrades / plan changes | — | Yes | Yes | Yes | H | `KB:upgrade-plan` |
| Cancel PRO trial | — | Yes | Yes | Yes | H | `KB:cancel-rank-math-pro-trial` |
| No lifetime deal | — | — | — | — | H | `KB:lifetime-deal-explained` |
| 24x7x365 support | — | Yes | Yes | Yes | H | `FvP` |

### 10.3 Content AI plan feature-use allocation

`H`, `KB:content-ai-plans-and-features`. Annual billing; monthly uses do not roll over.

| Content AI tool group | Starter | Creator | Expert |
|---|---|---|---|
| Fix SEO Tests | 500 | 1,000 | Unlimited |
| Keyword Research | 10 | 30 | 100 |
| AI Powered Image Alt Text | 50 | 100 | 500 |
| SEO Meta AI Tool | 100 | 500 | Unlimited |
| Write Inside WordPress | 100 | 500 | Unlimited |
| Bulk Edit SEO Meta | 100 | 500 | Unlimited |
| Create Long Form Content (1-click) | 15 | 60 | Unlimited |
| Link Suggestions | 100 | 500 | 1,000 |
| Link Opportunities | 50 | 200 | 1,000 |
| Related Posts | 50 | 200 | 1,000 |
| Semantic Keyword Variations | 100 | 500 | Unlimited |
| Open Graph AI Tool | 100 | 500 | Unlimited |
| Sentence Expander | 100 | 500 | Unlimited |
| Topic Research | 100 | 500 | Unlimited |
| RankBot AI Chatbot | 100 | 500 | Unlimited |
| Product Description | 100 | 500 | Unlimited |
| Paragraph Rewriter | 100 | 500 | Unlimited |
| Blog Post Idea | 100 | 500 | Unlimited |
| Blog Post Outline | 100 | 500 | Unlimited |
| Text Summarizer | 100 | 500 | Unlimited |
| SEO Description | 100 | 500 | Unlimited |

---

## PRO-only feature list

Consolidated list of every PRO/Business-gated item identified above (duplicates across areas removed). Items marked (Biz) are Business/Agency-specific.

1. Advanced Google Analytics 4 Integration
2. Keyword Rank Tracker (500 / 10,000 / 50,000 tracked keywords)
3. Site Analytics dashboard
4. Traffic source filtering
5. AI Search Traffic Tracker
6. SEO Performance Email Reports (full; free is limited)
7. White Labelled Email Reports (Biz)
8. Client Management (Biz)
9. Track Top 5 Winning/Losing Keywords
10. Track Top 5 Winning/Losing Posts
11. Advanced Content SEO Overview
12. Check Ranking Keywords for Each Post
13. Position History for Keywords & Posts
14. Single Post SEO Reports
15. Single Post Performance Badges
16. Track PageSpeed for Each Post & Page
17. Google AdSense Earning History
18. Import GSC & GA data for a particular country
19. Google Algorithm Updates timeline
20. Index Status: Top statuses & Presence on Google aggregates
21. Anonymize IP addresses
22. Self-Hosted Google Analytics JS File
23. Exclude logged-in users from GA tracking
24. Full Google Index Status report (free = limited)
25. Import Schema From Any Website (URL / HTML / JSON-LD)
26. Speakable Schema
27. Google Trends Integration
28. Google News SEO Sitemap (+ publication name, news post type, exclude terms, Googlebot-News)
29. Google Video SEO Sitemap (+ video sitemap config)
30. Advanced Image SEO Module (captions, descriptions, casing, avatars, replacements)
31. Find & Replace Image alt/title/caption text
32. Automate Image Captions
33. Watermarked social media images
34. Default Thumbnail Watermark
35. Local SEO PRO with Multi Locations
36. Advanced Local SEO Blocks
37. Multiple Location Schema via block/shortcode
38. LocalBusiness schema in Elementor/Divi
39. Local SEO settings: hide hours, closed/24-7 labels, measurement system, map style, max locations, primary country, show route, location detection, same-organization, enhanced search, Locations CPT bases/names
40. RM Locations post type & taxonomy Titles & Meta (all options)
41. WooCommerce SEO PRO: custom brand from settings, GTIN/MPN incl. variations, show global identifier, noindex hidden products, GTIN in product schema, variation Offer entities, dedicated product content-analysis tests
42. GTIN Migration Tool for WooCommerce (DB tool)
43. Complete EDD SEO
44. Podcast Module (+ settings, Podcast Episode schema, Podcast RSS feed)
45. 6 Extra Schema Types
46. Auto-detect Video for Video Schema
47. Automatic Video Data Fill for Video Schema
48. Generate Video Schema for Old Posts/Pages (DB tool)
49. 840+ Schema Types Supported
50. Add Custom Schema Using JSON-LD/HTML
51. Custom Schema Builder
52. Schema Templates
53. Schema display conditions / Automate Schema Implementation
54. Add Unlimited Multiple Schemas
55. Validate Schema With Google / Code Validation
56. Advanced Schema Editor (property groups, hierarchies)
57. Dataset Schema
58. Fact Check Schema
59. Podcast Schema
60. Carousel Schema
61. Mentions & About Schema
62. ItemList Schema
63. Q&A Page Schema
64. Movie Schema
65. Automatic Q&A Schema for bbPress
66. Advanced Redirections Module
67. Scheduled redirect Activation/Deactivation
68. Organizing & filtering redirections
69. Redirections for parameterized URLs
70. Export redirects as CSV
71. Import redirections data via CSV
72. Sync Redirections to .htaccess
73. Advanced 404 Monitor (referer, user-agent, hit grouping)
74. Export 404 Log
75. Advanced Post Filtering
76. Advanced Bulk Edit Options
77. Advanced Quick Edit Options
78. Complete Import/Export Options
79. Complete Elementor Integration (extra: dedicated breadcrumbs widget, accordion→FAQ schema)
80. Complete Divi Integration (extra: accordion→FAQ schema)
81. Import/Export Focus Keywords
82. Import SEO Data via CSV File
83. Detect Orphan Pages
84. Advanced HowTo Schema Options
85. Mark Cloaked Links as External Links
86. Noindex Password Protected Pages
87. Client Sites Support Per Account (100 Biz / 500 Agency)
88. Custom Setup Wizard Mode
89. Google Data Fetch Frequency: daily (vs 3 days free)
90. Days to Preserve Google Data: 180 days PRO / unlimited Business+ (vs 90 free)
91. Email Report Frequency in Days: 15/30 PRO, 7/15/30 Business+ (vs 30 free)
92. Advanced Content Analysis tests: Use Product Schema, Allow reviews, dedicated product tests
93. Competitor SEO Analysis
94. Side by Side SEO Comparison
95. Search Intent Analysis
96. AI Link Genius (full suite)
97. Broken Link Checker
98. Automated Keyword Linking
99. Related Posts Block & Shortcode
100. Link Opportunities / internal-link audit outputs
101. ACF images in sitemap
102. Competitor site SEO audit via MCP
103. 24/7 (Priority) support & dedicated premium support (Biz/Agency priority)
104. White-labelled client reporting (Biz)
105. Rank Math Vault (credential sharing) — listed in KB, tier ambiguous (`L`)
106. Content AI trials bundled: Starter (PRO) / Creator (Biz) / Expert (Agency)

---

## Uncertain or unverifiable

Items seen on official sources but not fully confirmable, with the source they were seen on.

| Item | Why uncertain | Source seen |
|---|---|---|
| Exact count of extra PRO schema types | `FvP` says "6 Extra Schema Types"; `KB:rich-snippets` lists 9 PRO types (Dataset, FactCheck, Movie, Podcast Episode, About & Mentions, ItemList, Carousel, Q&A Page, Speakable). | `FvP`, `KB:rich-snippets` |
| "840+ Schema Types Supported" | Marketing claim; not verifiable against schema.org count. | `FvP` |
| BuddyPress support | Only appears as a schema-variables group ("5.7 BuddyPress"); no dedicated KB page found in the official KB sitemap. | `KB:rich-snippets` |
| bbPress module | "Automatic Q&A Schema for bbPress" is in `FvP`, and Forums/Topics/Replies appear in Titles & Meta, but no dedicated bbPress KB page was found in the official KB sitemap. | `FvP`, `KB:titles-and-meta` |
| Web Stories module tier | KB describes enabling the "Google Web Stories" module; no FREE/PRO row in the official comparison table. | `KB:google-web-stories` |
| AMP module tier | KB describes module; not itemized in the official comparison table. | `KB:using-amp-with-rankmath` |
| MCP module tier | MCP appears on the official tools page + KB; not itemized in the comparison table. Assumed free module. | `KB:mcp-tools` |
| AI Visibility tier | KB (4 articles) and MCP page treat it as Content AI-linked; no explicit plan gating on an official comparison row. | `KB:track-ai-visibility`, `KB:mcp-tools` |
| "Google Indexing API" (Google) vs "IndexNow" | Instant Indexing KB covers IndexNow; the sidebar link "Google Indexing API" points to the Instant Indexing plugin page. Google's Indexing API is primarily for JobPosting/BroadcastEvent. Ambiguous. | `SS`, `KB:instant-indexing` |
| Rank Math Vault tier | KB exists; no FREE/PRO indication. | `KB:how-to-use-vault` |
| "Remove Snippet Data" (taxonomies) | Present in Titles & Meta taxonomy settings; no tier indication. | `KB:titles-and-meta` |
| "Legacy/legacy" `404 Monitor` simple vs advanced naming | `FvP` labels simple FREE / advanced PRO, but the KB presents both as modes of one module; PRO gating is from `FvP` only. | `FvP`, `KB:advanced-404-monitor` |
| Number of certified compatible products | `COMP` page paginated (2 pages) and filter-driven; exact count not extracted. | `COMP` |
| "Complete Elementor Integration" / "Complete Divi Integration" free vs PRO | `FvP` marks both as available in FREE, yet sub-features (breadcrumbs widget, accordion→FAQ) are PRO. | `FvP` |
| "Broken Link Checker" / "Automated Keyword Linking" as distinct products vs AI Link Genius features | Listed as separate pricing-card bullets but described as part of AI Link Genius. | `PRC`, `ALG` |
| Additional integrations beyond those on `COMP` (e.g. Zapier, Webhooks) | No official Zapier/webhook integration page found. | — |
| Newsy/Google News "Genres and Stock Ticker" | KB notes removal of these properties; treated as guidance, not a feature. | `KB:news-sitemap` |
| Legacy AMP/compatibility entries (Alexa Site Verification) | Alexa verification still listed in `FvP` even though the Alexa service is defunct. | `FvP` |

---

## Source index

- https://rankmath.com/free-vs-pro/
- https://rankmath.com/pricing/
- https://rankmath.com/wordpress/plugin/seo-suite/
- https://rankmath.com/blog/unique-rank-math-features/
- https://rankmath.com/content-ai/
- https://rankmath.com/ai-link-genius/
- https://rankmath.com/compatibility/
- https://rankmath.com/kb/ (Knowledge Base index + KB sitemap: ht_kb-sitemap1/2/3.xml)
- Individual KB articles under https://rankmath.com/kb/<slug>/ (slugs cited inline above)
