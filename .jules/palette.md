# Palette's Journal - UX & Accessibility Learnings

## 2025-05-18 - Screen Reader Indicators for External Links Opening in New Tab

**Learning:** External links using `target="_blank"` without a screen-reader text indicator violate WCAG 2.1 AA (3.2.5 / 4.1.2) because screen-reader users are not informed that activating the link will open a new browser window or tab.
**Action:** Always append `<span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span>` inside `target="_blank"` anchor tags in RankKernel WP Admin views.
