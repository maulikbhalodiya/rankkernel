# RankKernel Feature Parity Matrix

Side-by-side comparison of every competitor capability catalogued in the parity audit, with RankKernel's position and the free-value strategy for each row.

**Audited versions.** Rank Math free `1.0.278` and PRO `3.0.109`. Yoast SEO free `28.4` and Premium `27.8`. RankKernel `0.1.0` at main `606fc95`. Both competitors were installed but not activated, so competitor capabilities are code derived.

## How to read this table

| Column | Meaning |
|---|---|
| Rank Math Free | What the free Rank Math plugin does today, including its limits. |
| Yoast Free | What the free Yoast plugin does today, including its limits. |
| Rank Math PRO | What the paid Rank Math tier adds. |
| Yoast Premium | What the paid Yoast tier adds. Separate paid addons are named as such. |
| RankKernel Status | `DONE`, `PARTIAL`, `PLANNED`, `MISSING`, `EXTERNAL` (needs a paid third-party service, cannot be free) or `N/A` (does not apply to a free plugin). |
| Notes & RankKernel Edge | Where we already give away free what competitors charge for, where we win on limits, and where a free-parity gap remains. |

## Totals

| Verdict | Count |
|---|---|
| DONE | 78 |
| PARTIAL | 19 |
| PLANNED | 82 |
| MISSING | 163 |
| EXTERNAL | 14 |
| N/A | 23 |
| **Total** | **379** |

Rows marked `EXTERNAL` are listed in full in section 8 of `feature-parity-master.md`, and rows marked `N/A` in section 9, so nothing in this table is left unexplained.


## 6.1 Content and on-page analysis

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Focus keyword (primary) | yes | yes | yes | yes | PLANNED | **RankKernel Edge:** Unlimited focus keywords planned in the free tier, where Rank Math caps free at 5 and Yoast free at 1. |
| Multiple or secondary focus keywords | yes (up to 5) | no (1) | yes | yes (up to 5) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Unlimited focus keyphrases | yes | no | yes | no | PLANNED | - |
| Content analysis engine | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| On-page SEO tests (30+) | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| SEO score | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Readability analysis | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase density test | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in introduction or first 10 percent | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in SEO title | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in meta description | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in URL or slug | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in subheadings | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase in image alt | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase uniqueness or duplicate check | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Title readability tests (power word, number, sentiment) | yes | no | yes | no | PLANNED | - |
| Content readability tests (TOC, short paragraphs, media) | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Keyphrase distribution | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Keyphrase synonyms | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Related or multi keyphrase analysis | yes (5) | no | yes | yes (4 related) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Word-form recognition / morphology | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Word complexity check | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Inclusive language analysis | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Cornerstone or pillar content marking | yes (pillar) | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Stale cornerstone finder | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Prominent words | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Estimated reading time metric and block | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Product-specific content analysis tests | yes | no | yes | yes (add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Internal link suggestions | yes | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Internal link counter or counts | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Orphan page detection | no | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| Nofollow internal link detection | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Redirected internal link detection | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Internal-link HTTP status audit or broken link checker | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Automated keyword linking or keyword maps | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Bulk link update with rollback | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Related posts block and shortcode | no | no | yes | yes (related links block) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Affiliate link prefix handling | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Competitor SEO analysis | no | no | yes | no | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Side-by-side SEO comparison | no | no | yes | no | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Search intent analysis | no | no | yes | no | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Google Trends data | no | no | yes | no | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Semrush keyword data | no (integration) | yes (limited) | no (integration) | yes | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Wincher rank tracking | no | yes (limited) | no (own tracker) | yes | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| AI title and description generation | no | no | yes (Content AI) | yes | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| AI optimize or fix assessments | no | no | yes (Content AI) | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| AI summarize / key takeaways | no | no | yes (Content AI) | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| AI content planner | no | no | yes (Content AI) | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| AI image alt generation | no | no | yes (Content AI) | yes | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| Generative long-form AI writing | no | no | yes (Content AI) | no (base) | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| AI brand visibility tracking | yes (beta, metered) | no | yes | yes (AI+ add-on) | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| MCP or agent tooling | yes | no | yes | no | MISSING | **Gap:** not built. |

## 6.2 Metadata and head control

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Per-post SEO title editing UI | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free, and built on issue #27 as a Classic Editor meta box plus a Gutenberg sidebar. Unit and contract tested; awaiting manual browser verification before it is called DONE. |
| Per-post meta description editing UI | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free, same surface as the title field, with template fallback and a per field reset. Built on issue #27, awaiting manual browser verification. |
| Per-post robots editing UI | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free. Issue #27 adds the directives UI and routes output through the single core wp_robots tag with most restrictive wins. Awaiting manual browser verification. |
| Per-post canonical override | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free. Issue #27 adds the override field and unhooks core rel_canonical so exactly one canonical is emitted. Awaiting manual browser verification. |
| Per-post Open Graph and Twitter editing UI | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free, including media library pick and remove for both images and a card type selector. Built on issue #27, awaiting manual browser verification. |
| Snippet or SERP preview | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** free. Issue #27 adds a live preview with desktop and mobile frames and pixel and character budgets. Awaiting manual browser verification. |
| Social network previews | yes | no | yes | yes | PARTIAL | **RankKernel Edge:** free, where Yoast paywalls it. Issue #27 adds a live social unfurl card that falls back to the General values. Awaiting manual browser verification. |
| Bulk edit titles and descriptions | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Global title and description templates | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Per-post-type title or description templates | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Per-taxonomy title or description templates | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Homepage title, description and robots | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Author archive title, description and robots | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Date archive title, description and robots | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Search results title template | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| 404 title template | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Token or variable system | yes (about 53) | yes (50+) | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Parameterised or formatted tokens | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Random word variable | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Image alt or title variables | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Custom variables via code filter | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Title separator | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Capitalize titles | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Rewrite titles | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Strip category base | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Redirect attachments to parent | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Auto canonical output | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Custom canonical per object | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Represent site as Person or Company | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Knowledge Graph meta output | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Robots meta index, noindex, follow, nofollow | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Advanced robots directives (noarchive, nosnippet, max-*) | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Robots defaults per type, taxonomy, author and date | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Noindex empty taxonomies | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Noindex search results | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Noindex subpages and paginated single pages | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Noindex password-protected pages | no | yes | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| rel=next and rel=prev tags | yes | yes | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Open Graph output (full set) | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Homepage Open Graph | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Twitter Card output | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Default Twitter card type | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Twitter username | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Additional social profiles | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Default Open Graph share image | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Social image overlay icons | yes | no | yes | no | MISSING | **Gap:** not built. |
| Watermarked social images | no | no | yes | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Slack enhanced sharing | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Facebook thumbnail flush on update | yes | no | yes | no | MISSING | **Gap:** not built. |
| Webmaster verification tags | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Norton Safe Web verification | yes | no | yes | no | MISSING | **Gap:** not built. |
| Custom verification or head meta tags | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Header and footer code injection | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| hreflang passthrough | yes (passthrough) | yes (via plugin) | yes (passthrough) | yes (via plugin) | PLANNED | At parity with both competitors, no cost. |
| llms.txt generator | yes | yes | yes (advanced) | yes | PLANNED | At parity with both competitors, no cost. |
| AI crawler control (bot blocker) | no | no | no | yes | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Custom fields in the meta box | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Search appearance per type and archive | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |

## 6.3 Schema and structured data

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| JSON-LD `@graph` | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema generator metabox | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Default schema type per post type | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Multiple schema types stacked per page | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Custom schema builder (blank canvas) | no | no | yes | no | PARTIAL | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Custom JSON-LD input | no | no (Schema API code) | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema templates (reusable) | yes | no | yes | no | MISSING | **Gap:** not built. |
| Schema display conditions | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Import schema from URL, HTML or JSON-LD | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema validation via Google | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema search in generator | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| ACF fields as schema variables | yes | no | yes | no | MISSING | **Gap:** not built. |
| Schema preview endpoint | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Term or taxonomy schema | no | yes | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema shortcodes | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Schema extension API (filters) | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Partner schema integrations | EDD yes | partner plugins | yes | partner plugins | MISSING | **Gap:** not built. |
| FAQ Gutenberg block | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| HowTo Gutenberg block | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Table of Contents block | yes | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Rich-snippet block (embed template by id) | yes | no | yes | no | MISSING | **Gap:** not built. |
| Local Business schema | yes | no (add-on) | yes | yes (add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| 193 LocalBusiness types UI | yes | no (add-on) | yes | yes (add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Automatic QandA schema for bbPress | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Video autodetect for schema | no | no | yes | yes (add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Generate video schema for old posts | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema type: Article, BlogPosting, NewsArticle | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: WebPage | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: WebSite | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: Organization | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: Person | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: BreadcrumbList | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: ImageObject | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: FAQPage | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: HowTo | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema type: Product | yes | no (add-on) | yes | yes (add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Schema type: Recipe | yes | partner plugin | yes | partner plugin | DONE | - |
| Schema type: Event | yes | partner plugin | yes | partner plugin | DONE | - |
| Schema type: Service | yes | no | yes | no | DONE | - |
| Schema type: VideoObject | yes | no (add-on) | yes | yes (add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Schema type: Book | yes | no (Schema API) | yes | no | DONE | - |
| Schema type: Course | yes | no | yes | no | DONE | - |
| Schema type: JobPosting | yes | no | yes | no | DONE | - |
| Schema type: SoftwareApplication | yes | no | yes | no | DONE | - |
| Schema type: MusicRecording | yes | no | yes | no | DONE | - |
| Schema type: LocalBusiness | yes | no (add-on) | yes | yes (add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Schema type: Review | yes | no (add-on) | yes (legacy free) | yes (add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Schema type: Movie | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: ClaimReview or FactCheck | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: Dataset | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: PodcastEpisode | no | partner plugin | yes | partner plugin | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: Carousel | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: QAPage | no | yes | yes | yes | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: ItemList | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Schema type: Speakable | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema type: About and Mentions | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema page subtypes (CollectionPage, ProfilePage, ItemPage, AboutPage, ContactPage, MedicalWebPage, CheckoutPage, RealEstateListing, SearchResultsPage) | yes (some) | yes (about 12) | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Schema type: SiteNavigationElement | yes | no | yes | no | PARTIAL | - |
| WooCommerce product schema | yes | no (add-on) | yes | yes (add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| EDD product schema | yes | no | yes | yes (EDD add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Schema type: Restaurant | yes | no | yes | no | MISSING | **Gap:** not built. |
| Schema type: CollectionPage (standalone) | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |

## 6.4 Technical SEO, crawl and indexing

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| XML sitemap index | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Per-post-type sitemaps | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Per-taxonomy sitemaps | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Author sitemap | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Image entries in sitemap | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Sitemap XSL stylesheet | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Sitemap caching | yes (file) | yes (off by default) | yes | yes | DONE | At parity with both competitors, no cost. |
| Sitemap exclusion rules | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Sitemap directive in robots.txt | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Core sitemap takeover | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Search engine ping or submission | yes | no (removed in v22) | yes | no (removed in v22) | DONE | - |
| HTML sitemap | yes | no | yes | no | PLANNED | - |
| KML or geo sitemap | yes | no (Local add-on) | yes | yes (Local add-on) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Custom sitemap URLs | yes (child function) | no | yes | no | MISSING | **Gap:** not built. |
| News sitemap | no | no (News add-on) | yes | yes (News add-on) | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| Video sitemap | no | no (Video add-on) | yes | yes (Video add-on) | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| Product sitemap | yes | no (Woo add-on) | yes | yes (Woo add-on) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Local sitemap | yes | no (Local add-on) | yes | yes (Local add-on) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| robots.txt editor | yes | yes | yes | yes | PARTIAL | **RankKernel Edge:** Planned virtual editor written through the WordPress filter, with a built-in AI crawler group for GPTBot and CCBot, no file writes and no lockout risk. |
| robots.txt validator or tester | yes | no | yes | no | MISSING | **Gap:** not built. |
| .htaccess editor | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Redirect manager | yes | no | yes (advanced) | yes | DONE | **RankKernel Edge:** 6 match modes, 5 status codes and CSV import and export included free, where Yoast charges for redirects entirely. |
| Redirect codes 301, 302, 307, 410, 451 | yes | no | yes | yes | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Regex redirects | yes | no | yes | yes | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Redirect match types | yes | no | yes | yes (plain and regex) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Multiple sources per redirect | yes | no | yes | no | MISSING | **Gap:** not built. |
| Auto redirect on slug change | yes | no | yes | yes | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Redirect hit statistics | yes | no | yes | no | DONE | - |
| Redirect CSV import and export | no (server export only) | no | yes | yes | DONE | **RankKernel Edge:** Free, where both competitors paywall it. |
| Redirect .htaccess sync | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Scheduled redirect activation and expiration | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Redirect organising or categories | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Parameterised URL redirects | no | no | yes | partial | PARTIAL | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Redirect debugger | yes | no | yes | no | MISSING | **Gap:** not built. |
| Redirect fallback behaviour | yes | no | yes | no | MISSING | **Gap:** not built. |
| 404 monitor | yes (simple) | no | yes (advanced) | no | DONE | - |
| 404 advanced fields (referer, user agent) | no | no | yes | no | DONE | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| 404 log export | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| 404 grouping by hits | yes | no | yes | no | DONE | - |
| 404 bulk set 410 | yes | no | yes | no | MISSING | **Gap:** not built. |
| 404 to redirect workflow | yes | no | yes | no | DONE | - |
| Instant Indexing (IndexNow) | yes | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Google Indexing API | yes | no | yes | no | MISSING | **Gap:** not built. |
| Image auto ALT | yes | no | yes | no | PLANNED | - |
| Image auto title | yes | no | yes | no | PLANNED | - |
| Image ALT and title variable library | yes | no | yes | no | PLANNED | - |
| Image caption autofill | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Image description autofill | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Image casing conversion | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Avatar ALT | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Image find and replace | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Media library SEO filters | yes | no | yes | no | MISSING | **Gap:** not built. |
| Head cleanup (generator, shortlink, RSD, oEmbed, emojis, pingback, powered-by) | partial | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Feed controls | yes (RSS) | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Internal search cleanup | yes | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Advanced URL cleanup and UTM handling | no | no | yes | yes | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| RSS optimization (content before and after feed) | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Indexables table for fast meta output | no | yes | no | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Site-wide SEO analyzer | yes | partial | yes | partial | PLANNED | - |
| Breadcrumbs output plus BreadcrumbList | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Breadcrumb primary category or taxonomy control | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Freshness or modified-date lock | yes | no | yes | no | MISSING | **Gap:** not built. |

## 6.5 Analytics and reporting

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Google Search Console integration | yes | yes (via Site Kit) | yes | yes (via Site Kit) | PLANNED | At parity with both competitors, no cost. |
| Google Analytics 4 integration | no | yes (via Site Kit) | yes | yes (via Site Kit) | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Analytics dashboard | no | yes (via Site Kit) | yes | yes (via Site Kit) | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| SEO dashboard with site-wide scores | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Traffic source filtering | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| AI search traffic tracker | no | no | yes | no | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Keyword report and positions | no | yes (via Wincher) | yes | yes (via Wincher) | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Site analytics table | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Post analytics report | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Winning and losing keywords | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Winning and losing posts | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Advanced content SEO overview | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Ranking keywords per post | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Keyword and post position history | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| Single post SEO report | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Single post performance badges | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| PageSpeed tracking per URL | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Google AdSense earnings history | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Country-filtered GSC and GA data | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Google Algorithm Updates timeline | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Keyword rank tracker | no | yes (via Wincher) | yes | yes (via Wincher) | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Google Index Status (URL Inspection API) | yes (limited) | no | yes | no | MISSING | **Gap:** not built. |
| SEO email reports | yes (limited) | no | yes | no | MISSING | **Gap:** not built. |
| Email report content and frequency | yes (limited) | no | yes | no | MISSING | **Gap:** not built. |
| White-labelled email reports | no | no | no (Business) | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Client management for client sites | no | no | no (Business) | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Google data retention and fetch-frequency limits | yes | no | yes | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Analytics dashboard widgets | yes | yes (via Site Kit) | yes | yes (via Site Kit) | MISSING | **Gap:** free in both competitors today. |
| Anonymise IP addresses in GA | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Self-hosted Google Analytics JS | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Exclude logged-in users from GA | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |

## 6.6 Verticals (WooCommerce, Local, News, Video, Podcast, EDD, Stories, AMP, bbPress, BuddyPress)

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| WooCommerce: remove product base | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: remove product category base | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: remove parent slugs | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: remove generator tag | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: remove shop-archive schema | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: product brand taxonomy | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: product schema fields (brand, price, currency, availability) | yes | no | yes | add-on | PARTIAL | - |
| WooCommerce: gallery images in Open Graph and sitemap | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: exclude hidden products from sitemap | yes | no | yes | add-on | PLANNED | - |
| WooCommerce: noindex hidden products | no | no | yes | add-on | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| WooCommerce: GTIN and MPN identifiers | no | no | yes | add-on | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| WooCommerce: variation product schema | no | no | yes | add-on | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| WooCommerce: product content analysis tests | no | no | yes | add-on | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| WooCommerce: product variables | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: short description in analysis | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: noindex cart, checkout and account pages | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: product Open Graph price | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: duplicate-content handling | yes | no | yes | add-on | MISSING | **Gap:** not built. |
| WooCommerce: GTIN migration tool | no | no | yes | add-on | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| LocalBusiness schema for a single location | yes | no (Local add-on) | yes | yes (Local add-on) | PARTIAL | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Local business settings (name, address, phone, hours, geo, map) | yes | no (Local add-on) | yes | yes (Local add-on) | PLANNED | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Local business type (193 subtypes) | yes | no (Local add-on) | yes | yes (Local add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Local contact-info shortcode | yes | no (Local add-on) | yes | yes (Local add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Local multiple locations | no | no (Local add-on) | yes | yes (Local add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Local map, store locator or GPS | yes (map) | no (Local add-on) | yes | yes (Local add-on) | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Local Business Gutenberg block | no | no (Local add-on) | yes | yes (Local add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| News sitemap | no | no (News add-on) | yes | yes (News add-on) | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| NewsArticle schema | yes | no (News add-on) | yes | yes (News add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Video sitemap | no | no (Video add-on) | yes | yes (Video add-on) | PLANNED | **RankKernel Edge:** Free, where both competitors paywall it. |
| VideoObject schema | yes | no (Video add-on) | yes | yes (Video add-on) | DONE | **RankKernel Edge:** Free, where Yoast paywalls it. |
| Video autodetect for schema | no | no (Video add-on) | yes | yes (Video add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Podcast module, schema and RSS feed | no | no (partner plugin) | yes | no (partner plugin) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Media RSS (MRSS) and Yandex video OpenGraph | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Easy Digital Downloads schema | yes | no | yes | yes (EDD add-on) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Complete EDD SEO | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Google Web Stories module | yes | no | yes | no | MISSING | **Gap:** not built. |
| AMP module | yes | no | yes | no | MISSING | **Gap:** not built. |
| bbPress post-type SEO (forums, topics, replies) | yes | no | yes | no | MISSING | **Gap:** not built. |
| Automatic QandA schema for bbPress | no | no | yes | no | PARTIAL | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| BuddyPress schema variables | yes | no | yes | no | MISSING | **Gap:** not built. |

## 6.7 Integrations and ecosystem

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Elementor integration | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Elementor breadcrumbs widget and accordion to FAQ | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Divi integration and accordion to FAQ | yes | no | yes | no | MISSING | **Gap:** not built. |
| Gutenberg block editor sidebar | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Classic editor meta box | yes | yes | yes | yes | PARTIAL | At parity with both competitors, no cost. |
| Advanced Custom Fields (ACF) integration | yes | yes (glue plugin) | yes | yes (glue plugin) | MISSING | **Gap:** free in both competitors today. |
| Page builder compatibility (Divi, Bricks, Betheme, Brizy, SiteOrigin and more) | yes | Elementor only | yes | Elementor only | MISSING | **Gap:** not built. |
| WPML, Polylang and TranslatePress compatibility | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Headless REST head endpoint | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| WPGraphQL SEO fields | no | no | no | no | PLANNED | - |
| Google Site Kit integration | no | yes | no | yes | MISSING | **Gap:** not built. |
| Semrush integration | no | yes (limited) | no | yes | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Wincher integration | no | yes (limited) | no | yes | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Algolia site-search integration | no | no | no | yes | EXTERNAL | Cannot be free: depends on a paid third-party service (see section 8 of the analysis). |
| Zapier automated publishing | no | no | no | yes (deprecated) | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Mastodon verification | no | no | no | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| NLWeb or AI discoverability connector | no | yes | no | yes | MISSING | **Gap:** not built. |
| Custom schema API (filters) | yes | yes | yes | yes | DONE | At parity with both competitors, no cost. |
| Schema partner integrations (Recipe, Event, Podcast, EDD) | EDD yes | partner plugins | yes | partner plugins | MISSING | **Gap:** not built. |
| Table of Contents block | yes | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Related Posts block and shortcode | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Local Business Gutenberg block | no | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| MCP or AI assistant tooling | yes | no | yes | no | MISSING | **Gap:** not built. |
| WP Rocket cross-sell | no | no | yes | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Imagify cross-sell | no | no | yes | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |

## 6.8 Admin UX, tooling and governance

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Setup wizard | yes | yes | yes (custom mode) | yes | PLANNED | At parity with both competitors, no cost. |
| Module toggles | yes | no | yes | no | DONE | - |
| Branded dashboard and sidebar layout | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Tabbed settings information architecture | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Admin columns with SEO score | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Bulk edit of SEO fields | yes | yes | yes (advanced) | yes | MISSING | **Gap:** free in both competitors today. |
| Quick edit of SEO fields | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Post-list SEO filters (score, noindex, no keyword, orphan, schema) | no | no | yes | partial | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Media library SEO filters | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Term SEO details column | no | yes | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Role manager and capabilities | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Settings export and import | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Database maintenance tools | yes | no | yes | no | MISSING | **Gap:** not built. |
| System status and site health | yes | partial | yes | partial | MISSING | **Gap:** not built. |
| Error log viewer | yes | no | yes | no | MISSING | **Gap:** not built. |
| Version control and rollback | yes | no | yes | no | MISSING | **Gap:** not built. |
| Beta update channel | yes | no | yes | no | MISSING | **Gap:** not built. |
| Contextual help and documentation | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Frontend SEO score badge | yes | no | yes | no | MISSING | **Gap:** not built. |
| Notification centre and upsell banners | yes | yes | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Onboarding wizard | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Multisite compatibility | yes | yes | yes | yes | MISSING | **Gap:** free in both competitors today. |
| Multisite network settings and purge | yes | yes | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Benchmark or debug dev panel | no | no | no | no | PLANNED | - |

## 6.9 Migration, import and export

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Import from Yoast SEO | yes | n/a | yes | n/a | PLANNED | - |
| Import from Rank Math | n/a | yes | n/a | yes | PLANNED | - |
| Import from All in One SEO | yes | yes | yes | yes | PLANNED | At parity with both competitors, no cost. |
| Import from SEOPress | yes | no | yes | no | PLANNED | - |
| Import from SEO Framework, SmartCrawl, Squirrly, WP Meta SEO | no | yes | no | yes | MISSING | **Gap:** not built. |
| Import redirects from the Redirection plugin | yes | no | yes | yes | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Import Yoast Premium redirects | yes | n/a | yes | n/a | MISSING | **Gap:** not built. |
| Yoast block converter | yes | n/a | yes | n/a | MISSING | **Gap:** not built. |
| CSV SEO metadata import and export | no | no | yes | no | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Focus keyword CSV import and export | no | no | yes | yes (keyword export) | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |
| Redirect CSV import and export | no (server export only) | no | yes | yes | DONE | **RankKernel Edge:** Free, where both competitors paywall it. |
| 404 log export | no | no | yes | no | PLANNED | **RankKernel Edge:** Free, where Rank Math paywalls it. |
| GTIN migration tool | no | n/a | yes | n/a | MISSING | **Gap:** paid in at least one competitor, so this is a free-parity win when built. |

## 6.10 Licensing, support and agency

| Feature / Functionality | Rank Math Free | Yoast Free | Rank Math PRO | Yoast Premium | RankKernel Status | Notes & RankKernel Edge |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Free forever with no licence checks | yes | yes | n/a | n/a | DONE | At parity with both competitors, no cost. |
| Paid plans and pricing tiers | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Licence activation and enrolment | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Priority or 24/7 support | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Multisite licence rules | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Bulk discounts and agency reseller | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Money-back guarantee and refund policy | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Client site quota per account | no | no | yes | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Rank Math Vault credential sharing | yes | no | yes | no | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Academy or paid training | no | no | no | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Google Docs add-on | no | no | no | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |
| Shopify app | no | no | no | yes | N/A | Not applicable to a free self-hosted plugin (see section 9 of the analysis). |

## Evidence base

Every row is derived from the audited source documents in `docs/research/raw/`: `rankmath-web.md`, `rankmath-free-code.md`, `rankmath-pro-code.md`, `yoast-web.md`, `yoast-code.md`, `yoast-code-a.md`, `yoast-code-b.md`, `yoast-code-c.md` and `rankkernel-current.md`. The full narrative analysis, the gap register and the cannot-be-free list live in `feature-parity-master.md`.

