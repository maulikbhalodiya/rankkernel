<?php
/**
 * Sitemap index and entry builder.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

use RankKernel\Modules\Sitemaps\Provider\AuthorsProvider;
use RankKernel\Modules\Sitemaps\Provider\PostsProvider;
use RankKernel\Modules\Sitemaps\Provider\TaxonomiesProvider;

/**
 * Builds sitemap index and per set entry XML.
 */
class IndexBuilder {
    /**
     * Constructor.
     *
     * @param PostsProvider|null      $posts      Posts provider.
     * @param TaxonomiesProvider|null $taxonomies Taxonomies provider.
     * @param AuthorsProvider|null    $authors    Authors provider.
     */
    public function __construct(
        private readonly ?PostsProvider $posts = null,
        private readonly ?TaxonomiesProvider $taxonomies = null,
        private readonly ?AuthorsProvider $authors = null
    ) {
    }

    /**
     * Get posts provider, lazy create.
     */
    private function postsProvider(): PostsProvider {
        if (null !== $this->posts) {
            return $this->posts;
        }

        return new PostsProvider();
    }

    /**
     * Get taxonomies provider, lazy create.
     */
    private function taxonomiesProvider(): TaxonomiesProvider {
        if (null !== $this->taxonomies) {
            return $this->taxonomies;
        }

        return new TaxonomiesProvider();
    }

    /**
     * Get authors provider, lazy create.
     */
    private function authorsProvider(): AuthorsProvider {
        if (null !== $this->authors) {
            return $this->authors;
        }

        return new AuthorsProvider();
    }

    /**
     * Get entries per page, filtered.
     */
    public function getPerPage(): int {
        /**
         * Filter entries per page for sitemaps.
         *
         * @param int $perPage Default 1000.
         */
        $perPage = (int) apply_filters('rankkernel/sitemap/entries_per_page', 1000);

        if ($perPage < 1) {
            $perPage = 1;
        }

        if ($perPage > 50000) {
            $perPage = 50000;
        }

        return $perPage;
    }

    /**
     * Get all populated sets with page counts.
     *
     * @return array<string, int> Map of set => page count.
     */
    public function getSetsWithPageCounts(): array {
        $perPage = $this->getPerPage();
        $out     = [];

        $postsSets = $this->postsProvider()->getSets();
        foreach ($postsSets as $set) {
            $count = $this->postsProvider()->getCount($set);
            if (0 === $count) {
                continue;
            }

            $pages = (int) ceil($count / $perPage);
            if (0 === $pages) {
                continue;
            }

            $out[ $set ] = $pages;
        }

        $taxSets = $this->taxonomiesProvider()->getSets();
        foreach ($taxSets as $set) {
            $count = $this->taxonomiesProvider()->getCount($set);
            if (0 === $count) {
                continue;
            }

            $pages = (int) ceil($count / $perPage);
            if (0 === $pages) {
                continue;
            }

            $out[ $set ] = $pages;
        }

        $authorSets = $this->authorsProvider()->getSets();
        foreach ($authorSets as $set) {
            $count = $this->authorsProvider()->getCount($set);
            if (0 === $count) {
                continue;
            }

            $pages = (int) ceil($count / $perPage);
            if (0 === $pages) {
                continue;
            }

            $out[ $set ] = $pages;
        }

        return $out;
    }

    /**
     * Build sitemap index XML.
     *
     * @return string XML.
     */
    public function buildIndexXml(): string {
        $sets = $this->getSetsWithPageCounts();

        $xslHref = esc_url(home_url('/sitemap.xsl'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $hrefEsc = htmlspecialchars($xslHref, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . $hrefEsc . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sets as $set => $pages) {
            for ($i = 1; $i <= $pages; $i++) {
                $suffix = $i > 1 ? (string) $i : '';
                $loc    = home_url('/' . $set . '-sitemap' . $suffix . '.xml');
                $loc    = esc_url($loc);
                $date   = (string) mysql2date(DATE_W3C, current_time('mysql', true), false);
                $locEsc = htmlspecialchars($loc, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $dateEsc = htmlspecialchars($date, ENT_QUOTES | ENT_XML1, 'UTF-8');

                $xml .= '  <sitemap>' . "\n";
                $xml .= '    <loc>' . $locEsc . '</loc>' . "\n";
                $xml .= '    <lastmod>' . $dateEsc . '</lastmod>' . "\n";
                $xml .= '  </sitemap>' . "\n";
            }
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Build entries XML for a set and page.
     *
     * @param string $set  Set name.
     * @param int    $page Page number, 1 based.
     * @return string XML.
     */
    /**
     * Whether a sitemap set exists at all.
     *
     * Used to 404 unknown set names instead of rendering an empty urlset.
     *
     * @param string $set Set name (post type slug, taxonomy name, authors).
     */
    public function hasSet(string $set): bool {
        return array_key_exists($set, $this->getSetsWithPageCounts());
    }

    /**
     * Page count for a set, or zero when the set is unknown.
     *
     * @param string $set Set name.
     */
    public function getSetPageCount(string $set): int {
        $sets = $this->getSetsWithPageCounts();

        return (int) ( $sets[ $set ] ?? 0 );
    }

    public function buildEntriesXml(string $set, int $page): string {
        $page    = max(1, $page);
        $perPage = $this->getPerPage();

        $entries = $this->getEntriesForSet($set, $page, $perPage);

        $xslHref = esc_url(home_url('/sitemap.xsl'));

        $hrefEsc2 = htmlspecialchars($xslHref, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . $hrefEsc2 . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($entries as $entry) {
            $loc = (string) $entry['loc'];
            if ('' === $loc) {
                continue;
            }

            $loc = esc_url($loc);

            $lastmod = (string) $entry['lastmod'];
            $image   = $entry['image'];

            $locEsc = htmlspecialchars($loc, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $locEsc . '</loc>' . "\n";

            if ('' !== $lastmod) {
                $lastEsc = htmlspecialchars($lastmod, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xml .= '    <lastmod>' . $lastEsc . '</lastmod>' . "\n";
            }

            if (is_string($image) && '' !== $image) {
                $image = esc_url($image);
                $imgEsc = htmlspecialchars($image, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . $imgEsc . '</image:loc>' . "\n";
                $xml .= '    </image:image>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get entries for a set.
     *
     * @param string $set     Set name.
     * @param int    $page    Page number.
     * @param int    $perPage Per page.
     * @return array<int, array{loc: string, lastmod: string, image: string|null}>
     */
    private function getEntriesForSet(string $set, int $page, int $perPage): array {
        // Check posts provider first.
        $postsSets = $this->postsProvider()->getSets();
        if (in_array($set, $postsSets, true)) {
            return $this->postsProvider()->getEntries($set, $page, $perPage);
        }

        // Taxonomies.
        $taxSets = $this->taxonomiesProvider()->getSets();
        if (in_array($set, $taxSets, true)) {
            return $this->taxonomiesProvider()->getEntries($set, $page, $perPage);
        }

        // Authors.
        $authorSets = $this->authorsProvider()->getSets();
        if (in_array($set, $authorSets, true)) {
            return $this->authorsProvider()->getEntries($set, $page, $perPage);
        }

        return [];
    }
}
