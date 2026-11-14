# RankKernel — False-Positive / Accepted-Warning Justifications

Exact ZIP: `rankkernel-0.1.0.zip` (SHA-256 `6f6cac88...`). Plugin Check 2.1.0 on WordPress 7.1.4 and 6.5.14: **0 errors / 223 warnings**.

## Summary

- PrefixAllGlobals warnings: 203
- DirectDB warnings: 20
- Unclassified warnings: 0

A prior pass reported 204 PrefixAllGlobals warnings; the 204th was a transient-name warning on an unprefixed `$sites` variable. It was renamed to `$rankkernel_sites`, removing that warning and leaving the 203 included-view locals listed below.

## PrefixAllGlobals — included-view scope

Every warning in this group is a variable inside an admin view included from a method (for example `SettingsPage::render()` or `NotFoundPage::render()`). PHPCS analyzes the included file as if it were global scope. The variables are method-local at runtime, are not registered as globals, and do not collide with another plugin. No global variable, option, transient or public identifier uses an unprefixed name.

| File | Line | Variable/message | Justification |
|---|---:|---|---|
| `src/Admin/Views/schema-metabox.php` | 55 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$auto". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-metabox.php` | 57 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$typeOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-metabox.php` | 58 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$mark". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-metabox.php` | 69 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$fieldRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-metabox.php` | 89 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$validationMessage". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 79 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkUsagePercent". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 80 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSlotsAvailable". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 82 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkFormatDate". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 92 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkFormatDateTime". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 110 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityPill". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 111 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 112 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkFillModifier". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 114 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityPill". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 115 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 116 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkFillModifier". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 118 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityPill". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 119 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCapacityLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 120 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkFillModifier". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 128 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkChainLinks". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 162 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkChainIndex". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 162 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkChainLink". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 387 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkExclRowIndex". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 387 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$exclusionRowItem". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 388 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkExclRuleNumber". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 390 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkExclRemoveLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 397 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$comparatorOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 397 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$comparatorLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 422 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$comparatorOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 422 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$comparatorLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 658 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$sortColumn". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 660 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortIsAscending". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 663 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortState". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 664 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortName". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 666 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortState". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 667 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortName". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 669 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortState". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 670 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSortName". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 688 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$listRowItem". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 690 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowUri". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 691 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowHits". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 692 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowCreated". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 693 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowSeen". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 694 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowReferer". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 695 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowAgent". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 697 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowCreatedLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 698 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowSeenLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 700 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowCreatedFull". | Included-view local; not a PHP global. |
| `src/Admin/Views/not-found.php` | 701 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRowSeenFull". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 56 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_total_modules". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 57 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_active_modules". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 58 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_available_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 59 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 60 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_names". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 62 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_card". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 67 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_available_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 70 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 71 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_names". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 75 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_available_count". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 76 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_count". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 79 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_health_ids". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 80 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_health_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 82 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_card". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 84 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_health_cards". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 88 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_sitemaps_card". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 89 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_sitemaps_on". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 96 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_module_icons". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 112 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_label". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 118 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_attention_body". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 125 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_home_url". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 249 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$card". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 267 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$settingsAriaLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 279 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$toggleAriaLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 370 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_health_id". | Included-view local; not a PHP global. |
| `src/Admin/Views/dashboard.php` | 372 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_health_card". | Included-view local; not a PHP global. |
| `src/Admin/Views/settings.php` | 143 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$section". | Included-view local; not a PHP global. |
| `src/Admin/Views/instant-indexing.php` | 402 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$statusTab". | Included-view local; not a PHP global. |
| `src/Admin/Views/instant-indexing.php` | 432 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$pageRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/instant-indexing.php` | 446 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$retryable". | Included-view local; not a PHP global. |
| `src/Admin/Views/instant-indexing.php` | 466 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkRetryAria". | Included-view local; not a PHP global. |
| `src/Admin/Views/instant-indexing.php` | 486 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$pageEntry". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 98 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCodeBadgeMap". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 106 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkMatchBadgeMap". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 327 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$matchRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 384 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$codeRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 451 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$matchHintRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 459 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$codeHintRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 665 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$importErrorLine". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects.php` | 672 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$importWarningLine". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-settings.php` | 58 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkPluginVersion". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-settings.php` | 415 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$row". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-settings.php` | 423 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSlug". | Included-view local; not a PHP global. |
| `src/Admin/Views/schema-settings.php` | 439 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaType". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 44 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkListCodeBadgeMap". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 52 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkListMatchBadgeMap". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 65 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$statusView". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 106 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$matchRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 120 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$codeRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 210 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkHeaderColMap". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 219 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$columnHeader". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 235 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$ruleRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 259 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCodeVal". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 260 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCodeClass". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 268 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkMatchVal". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 269 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkMatchClass". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 286 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkToggleAria". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 289 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkToggleAria". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 293 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkEditAria". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 296 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkDeleteAria". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 324 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$perPageOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/redirects-list.php` | 340 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$pageEntry". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/webmaster.php` | 62 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$webmaster". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/webmaster.php` | 63 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_desc_id". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/llms.php` | 72 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$consistencyWarning". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/llms.php` | 161 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$llmsError". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/llms.php` | 170 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$llmsWarning". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 102 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$choice". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 134 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$toggle". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 135 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_toggle_hint_id". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 144 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$toggle". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 145 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_toggle_hint_id". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 160 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$taxonomyRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/breadcrumbs.php` | 186 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$taxonomyOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/robots.php` | 71 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$consistencyWarning". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/robots.php` | 82 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$robotGroup". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/robots.php` | 87 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$robotCrawler". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/robots.php` | 131 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$robotError". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/robots.php` | 140 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$robotWarning". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/htaccess.php` | 64 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$htaccess_directives_visual". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/social.php` | 78 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkSocialNetworks". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/social.php` | 87 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkNetworkId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/social.php` | 87 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkNetworkLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/social.php` | 88 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkProfileValue". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 62 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$templateTokens". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 75 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$templateToken". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 77 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$titleTokenLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 98 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$templateToken". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 100 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$descTokenLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/sections/general.php` | 133 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$titleSeparatorChoice". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 70 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$maxSnippet". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 71 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$maxVideoPreview". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 72 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$maxImagePreview". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 73 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$ogType". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 74 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$twitterCard". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 75 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$twitterTitle". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 76 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$twitterDescription". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 77 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$ogTitle". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 78 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$ogDescription". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 79 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$canonicalValue". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 83 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$socialTitle". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 84 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$socialDescription". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 85 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$socialImage". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 86 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$socialCard". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 87 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$socialCardLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 90 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaStatusText". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 92 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaStatusText". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 98 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaStatusText". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 132 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$serpMark". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 137 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$serpMark". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 172 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$tokenRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 175 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkTitleTokenLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 201 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$tokenRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 204 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkDescTokenLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 258 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$previewOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 289 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaTypeOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 303 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaFieldRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 323 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$schemaValidationMessage". | Included-view local; not a PHP global. |
| `src/Admin/Views/metadata-box.php` | 372 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$cardOption". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 59 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkGeneralRowNotes". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 133 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$tabItem". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 193 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$generalRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 204 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkGeneralTitleId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 205 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkGeneralHintId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 225 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkGeneralInputHintId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 250 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkPostTypeCount". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 251 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkPostTypeCountLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 269 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$postTypeRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 278 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkPostTypeTitleId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 279 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkPostTypeLabelId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 315 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkTaxonomyCount". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 316 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkTaxonomyCountLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 334 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$taxonomyRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 341 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkTaxonomyTitleId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 342 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkTaxonomyLabelId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 386 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$authorsRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 395 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkAuthorsTitleId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 396 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkAuthorsHintId". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 431 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$roleRow". | Included-view local; not a PHP global. |
| `src/Admin/Views/sitemap-settings.php` | 439 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkAuthorsExcludeHintId". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 22 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkNotice". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 23 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkErrors". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 24 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkValues". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 25 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkCats". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 26 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkVersion". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 27 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkDiag". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 35 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_value". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 45 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_error". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 55 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_error_attrs". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 73 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_describedby". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 87 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rk_error_block". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 168 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkValue". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 168 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 289 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkDiagLabel". | Included-view local; not a PHP global. |
| `src/Admin/Views/support.php` | 289 | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: "$rkDiagValue". | Included-view local; not a PHP global. |

## DirectDB — prepared/fixed SQL

Each warning points at a `$wpdb` call whose query text is constant or built from fixed plugin-owned table identifiers. Request values are bound with `$wpdb->prepare()` placeholders; uninstall DROP names are regex-gated.

| File | Line | Code | Justification |
|---|---:|---|---|
| `src/Modules/Monitor/LogTable.php` | 196 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $sql used in $wpdb-&gt;query() $sql assigned unsafely at line 173. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/PostsProvider.php` | 184 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 181. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/PostsProvider.php` | 230 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 227. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/PostsProvider.php` | 275 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 272. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/PostsProvider.php` | 675 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 672. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/AuthorsProvider.php` | 175 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 173. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/AuthorsProvider.php` | 225 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 223. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/AuthorsProvider.php` | 254 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 251. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/AuthorsProvider.php` | 400 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 397. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/AuthorsProvider.php` | 450 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 447. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 202 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 199. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 259 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 256. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 293 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_var() $args assigned unsafely at line 290. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 355 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 352. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 489 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 486. — table name is fixed/validated; values are prepared. |
| `src/Modules/Sitemaps/Provider/TaxonomiesProvider.php` | 658 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $args used in $wpdb-&gt;get_results() $args assigned unsafely at line 655. — table name is fixed/validated; values are prepared. |
| `src/Modules/InstantIndexing/LogTable.php` | 183 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $sql used in $wpdb-&gt;query() $sql assigned unsafely at line 162. — table name is fixed/validated; values are prepared. |
| `src/Modules/Redirects/HitCounter.php` | 85 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $sql used in $wpdb-&gt;query() $sql assigned unsafely at line 82. — table name is fixed/validated; values are prepared. |
| `src/Modules/Redirects/RedirectTable.php` | 224 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $sql used in $wpdb-&gt;query() $sql assigned unsafely at line 200. — table name is fixed/validated; values are prepared. |
| `uninstall.php` | 58 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $rankkernel_table used in $wpdb-&gt;query() — table name is fixed/validated; values are prepared. |

## Targeted scanner annotations

Ten targeted annotations remain. They are not blanket suppressions:

- `MetadataBox` metadata maps: `map_deep()` with `sanitize_text_field()` for every string leaf; the scanner cannot follow the callback.
- `SupportPage` upload array: `SupportDelivery::normalizeScreenshots()`/`validateScreenshot()` validate upload error, actual size, MIME, temp path and private storage.
- `InstantIndexingPage` whole `$_GET` array: each leaf is sanitized by `map_deep()` and then normalized by `LogQuery::fromInput()`.
- `KeyFileServer` whole `$_GET`: candidate is read only for an exact `hash_equals()` key match; nothing is stored or echoed raw.

These are the only remaining line-level annotations; the full warning set is listed above rather than hidden with a ruleset exclusion.
