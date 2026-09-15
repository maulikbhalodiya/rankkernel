<?php
/**
 * Canonical breadcrumb trail builder.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

use RankKernel\Modules\Metadata\Context;

/**
 * Builds the single canonical breadcrumb trail for every context.
 *
 * The only place breadcrumb hierarchy and context logic lives. It returns
 * Item objects built from already loaded WordPress objects through cache
 * backed APIs, so a normal singular or archive view needs zero extra
 * queries. Rendering and schema mapping happen elsewhere.
 *
 * Dispatch mirrors core conditional order for predictability: front page
 * and blog index, then search, then 404, then term and other archives,
 * then singular. Separator handling belongs to the renderer, which is a
 * later phase and reads the separator setting itself.
 */
final class TrailBuilder {
	/**
	 * Constructor.
	 *
	 * @param Context             $ctx      Request context.
	 * @param BreadcrumbsSettings $settings Breadcrumb settings.
	 */
	/**
	 * Constructor.
	 *
	 * @param Context              $ctx       Request context.
	 * @param BreadcrumbsSettings  $settings  Breadcrumb settings.
	 * @param array<string, mixed> $overrides Optional visibility overrides: show_home, show_current.
	 */
	public function __construct(
		private readonly Context $ctx,
		private readonly BreadcrumbsSettings $settings,
		private readonly array $overrides = []
	) {
	}

	/**
	 * Whether the home crumb is shown, caller override first.
	 *
	 * Visibility is decided here, before pagination is appended, so a
	 * hidden home or current item never removes a Page N crumb.
	 */
	private function showHome(): bool {
		return array_key_exists( 'show_home', $this->overrides )
			? (bool) $this->overrides['show_home']
			: (bool) $this->settings->get( 'show_home', true );
	}

	/**
	 * Whether the current crumb is shown, caller override first.
	 */
	private function showCurrent(): bool {
		return array_key_exists( 'show_current', $this->overrides )
			? (bool) $this->overrides['show_current']
			: (bool) $this->settings->get( 'show_current', true );
	}

	/**
	 * Build the canonical trail for the current request.
	 *
	 * @return Item[] Ordered trail, empty for invalid contexts.
	 */
	public function build(): array {
		if ( $this->isFrontPage() ) {
			return $this->buildFrontPage();
		}

		if ( $this->isBlogIndex() ) {
			return $this->buildBlogIndex();
		}

		$type = $this->ctx->queriedType();

		if ( 'search' === $type || $this->isSearch() ) {
			return $this->buildSearch();
		}

		if ( '404' === $type || $this->is404() ) {
			return $this->build404();
		}

		if ( 'term' === $type ) {
			return $this->buildTermArchive();
		}

		if ( 'archive' === $type ) {
			return $this->buildArchive();
		}

		if ( 'post' === $type ) {
			return $this->buildSingular();
		}

		return [];
	}

	/**
	 * Build the front page trail.
	 *
	 * Posts mode shows no trail (a paged Page N only). A static front
	 * page shows home only unless hide_on_front_page suppresses it, with
	 * a paged Page N appended in both modes.
	 *
	 * @return Item[] Ordered trail.
	 */
	private function buildFrontPage(): array {
		$paged = max( $this->queryVar( 'paged' ), $this->queryVar( 'page' ) );

		if ( $this->isPostsFrontPage() ) {
			if ( $paged < 2 ) {
				return [];
			}

			return [ $this->pageItem( $paged ) ];
		}

		if ( (bool) $this->settings->get( 'hide_on_front_page', true ) && $paged < 2 ) {
			return [];
		}

		$items = [];

		$this->addHome( $items );

		if ( $paged > 1 ) {
			$items[] = $this->pageItem( $paged );
		}

		return $this->collapse( $items );
	}

	/**
	 * Build the blog posts index trail: home plus the posts page title.
	 *
	 * The label comes from the configured posts page, never a hardcoded
	 * Blog string.
	 *
	 * @return Item[] Ordered trail.
	 */
	private function buildBlogIndex(): array {
		$items = [];

		$this->addHome( $items );

		$pageId = $this->blogPageId();

		$title = '';

		if ( $pageId > 0 && function_exists( 'get_the_title' ) ) {
			$title = (string) get_the_title( $pageId );
		}

		if ( '' === trim( $title ) ) {
			$title = $this->ctx->title();
		}

		$url = '';

		if ( $pageId > 0 && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $pageId );
			$url  = is_string( $link ) ? $link : '';
		}

		if ( '' === $url ) {
			$url = $this->ctx->permalink();
		}

		$items[] = new Item( $this->resolveLabel( $title ), $url );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build the search trail: home plus a linked search crumb.
	 *
	 * The search URL carries no pagination; a paged search appends a
	 * visible only Page N crumb instead.
	 *
	 * @return Item[] Ordered trail.
	 */
	private function buildSearch(): array {
		$items = [];

		$this->addHome( $items );

		$query = function_exists( 'get_search_query' ) ? (string) get_search_query() : '';
		$query = trim( $query );

		if ( '' !== $query ) {
			// translators: %s: search query.
			$label = sprintf( __( 'Search results for "%s"', 'rankkernel' ), $query );
		} else {
			$label = __( 'Search results', 'rankkernel' );
		}

		$url = '';

		if ( function_exists( 'get_search_link' ) ) {
			$url = (string) get_search_link();
		}

		$items[] = new Item( $label, $url );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build the 404 trail: home plus an unlinked label.
	 *
	 * @return Item[] Ordered trail.
	 */
	private function build404(): array {
		$items = [];

		$this->addHome( $items );

		$items[] = new Item( __( 'Page not found', 'rankkernel' ), '' );

		$items = $this->applyShowCurrent( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build a taxonomy term archive trail.
	 *
	 * Home, optional blog page crumb for post taxonomies, taxonomy name
	 * crumb for custom taxonomies, term ancestors root first when
	 * enabled, then the term itself.
	 *
	 * @return Item[] Ordered trail, empty when the term is unreadable.
	 */
	private function buildTermArchive(): array {
		$vars = function_exists( 'get_queried_object' ) ? $this->vars( get_queried_object() ) : [];

		$termId   = isset( $vars['term_id'] ) ? (int) $vars['term_id'] : 0;
		$taxonomy = isset( $vars['taxonomy'] ) ? (string) $vars['taxonomy'] : '';

		if ( $termId <= 0 || '' === $taxonomy ) {
			return [];
		}

		$items = [];

		$this->addHome( $items );

		if ( (bool) $this->settings->get( 'show_blog_page', true ) && $this->taxonomyForPost( $taxonomy ) ) {
			$blog = $this->blogPageItem();

			if ( null !== $blog ) {
				$items[] = $blog;
			}
		}

		if ( ! in_array( $taxonomy, [ 'category', 'post_tag' ], true ) ) {
			$items[] = new Item( $this->taxonomyLabel( $taxonomy ), '' );
		}

		foreach ( $this->termAncestors( $termId, $taxonomy ) as $ancestor ) {
			$items[] = $ancestor;
		}

		$name = isset( $vars['name'] ) ? (string) $vars['name'] : '';
		$link = function_exists( 'get_term_link' ) ? get_term_link( $termId, $taxonomy ) : '';
		$url  = is_string( $link ) ? $link : '';

		$items[] = new Item( $this->resolveLabel( $name ), $this->currentUrl( $url ) );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build other archives: post type archive, author, or date.
	 *
	 * @return Item[] Ordered trail, empty when the archive is unreadable.
	 */
	private function buildArchive(): array {
		if ( $this->isPostTypeArchive() ) {
			return $this->buildPostTypeArchive();
		}

		if ( $this->isAuthor() ) {
			return $this->buildAuthorArchive();
		}

		if ( $this->isDate() ) {
			return $this->buildDateArchive();
		}

		return [];
	}

	/**
	 * Build a post type archive trail: home plus the archive label.
	 *
	 * The label comes from the post type object, never a guess.
	 *
	 * @return Item[] Ordered trail, empty when the type is unreadable.
	 */
	private function buildPostTypeArchive(): array {
		$postType = $this->archivePostType();

		if ( '' === $postType ) {
			return [];
		}

		$items = [];

		$this->addHome( $items );

		$archive = $this->cptArchiveItem( $postType );

		if ( null === $archive ) {
			return [];
		}

		$items[] = $archive;

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build an author archive trail: home plus the author name.
	 *
	 * @return Item[] Ordered trail.
	 */
	private function buildAuthorArchive(): array {
		$authorId = $this->ctx->queriedId();
		$name     = '';

		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		if ( is_object( $obj ) && isset( $obj->display_name ) ) {
			$name = (string) $obj->display_name;
		}

		if ( '' === trim( $name ) ) {
			if ( is_object( $obj ) && isset( $obj->ID ) && is_numeric( $obj->ID ) ) {
				$authorId = (int) $obj->ID;
			}

			if ( $authorId > 0 && function_exists( 'get_the_author_meta' ) ) {
				$name = (string) get_the_author_meta( 'display_name', $authorId );
			}
		}

		$url = '';

		if ( $authorId > 0 && function_exists( 'get_author_posts_url' ) ) {
			$url = (string) get_author_posts_url( $authorId );
		}

		if ( '' === $url ) {
			$url = $this->ctx->permalink();
		}

		$items = [];

		$this->addHome( $items );

		$items[] = new Item( $this->resolveLabel( $name ), $this->currentUrl( $url ) );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build a date archive trail: home plus the year to month to day chain.
	 *
	 * Ancestor segments link to their archive; the deepest present
	 * segment is the current item.
	 *
	 * @return Item[] Ordered trail, empty without a year.
	 */
	private function buildDateArchive(): array {
		$year  = $this->queryVar( 'year' );
		$month = $this->queryVar( 'monthnum' );
		$day   = $this->queryVar( 'day' );

		if ( $year <= 0 ) {
			return [];
		}

		$items = [];

		$this->addHome( $items );

		$yearUrl = '';

		if ( function_exists( 'get_year_link' ) ) {
			$yearUrl = (string) get_year_link( $year );
		}

		$items[] = new Item( (string) $year, $yearUrl );

		if ( $month > 0 ) {
			$monthUrl = '';

			if ( function_exists( 'get_month_link' ) ) {
				$monthUrl = (string) get_month_link( $year, $month );
			}

			$items[] = new Item( $this->monthLabel( $year, $month ), $monthUrl );
		}

		if ( $month > 0 && $day > 0 ) {
			$dayUrl = '';

			if ( function_exists( 'get_day_link' ) ) {
				$dayUrl = (string) get_day_link( $year, $month, $day );
			}

			$items[] = new Item( $this->dayLabel( $year, $month, $day ), $this->currentUrl( $dayUrl ) );
		}

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendArchivePagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build a singular trail, dispatching on post type shape.
	 *
	 * @return Item[] Ordered trail, empty when the post is unreadable.
	 */
	private function buildSingular(): array {
		$post = $this->queriedPost();

		if ( null === $post ) {
			return [];
		}

		if ( 'attachment' === $post['type'] ) {
			return $this->buildAttachment( $post );
		}

		if ( 'page' === $post['type'] ) {
			return $this->buildHierarchical( $post, false );
		}

		if ( $this->isHierarchicalPostType( $post['type'] ) ) {
			return $this->buildHierarchical( $post, true );
		}

		return $this->buildStandardSingular( $post );
	}

	/**
	 * Build a post or non hierarchical CPT trail.
	 *
	 * Home, optional blog page crumb for posts or optional CPT archive
	 * crumb for other types, one taxonomy branch, then the title.
	 *
	 * @param array{id: int, type: string, title: string, parent: int} $post Post shape.
	 * @return Item[] Ordered trail.
	 */
	private function buildStandardSingular( array $post ): array {
		$items = [];

		$this->addHome( $items );

		if ( 'post' === $post['type'] ) {
			$blog = $this->blogPageItem();

			if ( null !== $blog ) {
				$items[] = $blog;
			}
		} else {
			$archive = $this->cptArchiveItem( $post['type'] );

			if ( null !== $archive ) {
				$items[] = $archive;
			}
		}

		foreach ( $this->termBranch( $post['type'], $post['id'] ) as $branch ) {
			$items[] = $branch;
		}

		$items[] = new Item( $this->resolveLabel( $this->postTitle( $post ) ), $this->currentUrl( $this->postPermalink( $post['id'] ) ) );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendSingularPagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build a page or hierarchical CPT trail.
	 *
	 * Home, optional CPT archive crumb, ancestor chain root first, then
	 * the title.
	 *
	 * @param array{id: int, type: string, title: string, parent: int} $post        Post shape.
	 * @param bool                                                     $withArchive Whether a CPT archive crumb may appear.
	 * @return Item[] Ordered trail.
	 */
	private function buildHierarchical( array $post, bool $withArchive ): array {
		$items = [];

		$this->addHome( $items );

		if ( $withArchive ) {
			$archive = $this->cptArchiveItem( $post['type'] );

			if ( null !== $archive ) {
				$items[] = $archive;
			}
		}

		foreach ( $this->postAncestors( $post['id'] ) as $ancestor ) {
			$items[] = $ancestor;
		}

		$items[] = new Item( $this->resolveLabel( $this->postTitle( $post ) ), $this->currentUrl( $this->postPermalink( $post['id'] ) ) );

		$items = $this->applyShowCurrent( $items );
		$items = $this->appendSingularPagination( $items );

		return $this->collapse( $items );
	}

	/**
	 * Build an attachment trail through its parent.
	 *
	 * Parentless attachments fall back to home plus the title.
	 *
	 * @param array{id: int, type: string, title: string, parent: int} $post Attachment shape.
	 * @return Item[] Ordered trail.
	 */
	private function buildAttachment( array $post ): array {
		$items = [];

		$this->addHome( $items );

		$parentId = $post['parent'];
		$parent   = [];

		if ( $parentId > 0 && function_exists( 'get_post' ) ) {
			$parent = $this->vars( get_post( $parentId ) );
		}

		if ( [] !== $parent ) {
			foreach ( $this->postAncestors( $parentId ) as $ancestor ) {
				$items[] = $ancestor;
			}

			$parentTitle = isset( $parent['post_title'] ) ? (string) $parent['post_title'] : '';
			$parentUrl   = $this->postPermalink( $parentId );

			$items[] = new Item( $this->plainLabel( $parentTitle ), $parentUrl );
		}

		$items[] = new Item( $this->resolveLabel( $this->postTitle( $post ) ), $this->currentUrl( $this->postPermalink( $post['id'] ) ) );

		$items = $this->applyShowCurrent( $items );

		return $this->collapse( $items );
	}

	/**
	 * Object properties as a string keyed map.
	 *
	 * WordPress objects arrive as WP_Post, WP_Term, and similar shapes or
	 * as plain doubles in tests. Reading through a map keeps every access
	 * defensive without typed property assumptions.
	 *
	 * @param mixed $obj Candidate object.
	 * @return array<string, mixed> Property map, empty when not an object.
	 */
	private function vars( mixed $obj ): array {
		if ( ! is_object( $obj ) ) {
			return [];
		}

		return get_object_vars( $obj );
	}

	/**
	 * Read the queried post shape from the loaded object.
	 *
	 * @return array{id: int, type: string, title: string, parent: int}|null Post shape or null.
	 */
	private function queriedPost(): ?array {
		$vars = function_exists( 'get_queried_object' ) ? $this->vars( get_queried_object() ) : [];
		$type = isset( $vars['post_type'] ) ? (string) $vars['post_type'] : '';

		if ( '' === $type ) {
			return null;
		}

		$id = $this->ctx->queriedId();

		if ( $id <= 0 && isset( $vars['ID'] ) && is_numeric( $vars['ID'] ) ) {
			$id = (int) $vars['ID'];
		}

		if ( $id <= 0 ) {
			return null;
		}

		$title  = isset( $vars['post_title'] ) ? (string) $vars['post_title'] : '';
		$parent = isset( $vars['post_parent'] ) ? (int) $vars['post_parent'] : 0;

		if ( 0 === $parent && function_exists( 'wp_get_post_parent_id' ) ) {
			$parent = (int) wp_get_post_parent_id( $id );
		}

		return [
			'id'     => $id,
			'type'   => $type,
			'title'  => $title,
			'parent' => $parent,
		];
	}

	/**
	 * Native title for a post shape, falling back to get_the_title.
	 *
	 * @param array{id: int, type: string, title: string, parent: int} $post Post shape.
	 * @return string The result.
	 */
	private function postTitle( array $post ): string {
		if ( '' !== trim( $post['title'] ) ) {
			return $post['title'];
		}

		if ( function_exists( 'get_the_title' ) ) {
			$title = get_the_title( $post['id'] );

			if ( is_string( $title ) ) {
				return $title;
			}
		}

		return '';
	}

	/**
	 * Permalink for a post id.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	private function postPermalink( int $postId ): string {
		if ( $postId > 0 && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $postId );

			if ( is_string( $link ) ) {
				return $link;
			}
		}

		return '';
	}

	/**
	 * Linked ancestor chain root first for a post id.
	 *
	 * Missing ancestors are skipped so the trail continues from home.
	 *
	 * @param int $postId Post id.
	 * @return Item[] Ordered ancestors.
	 */
	private function postAncestors( int $postId ): array {
		if ( ! function_exists( 'get_post_ancestors' ) || ! function_exists( 'get_post' ) ) {
			return [];
		}

		$ancestors = get_post_ancestors( $postId );

		if ( ! is_array( $ancestors ) ) {
			return [];
		}

		$items = [];

		foreach ( array_reverse( array_map( 'intval', $ancestors ) ) as $ancestorId ) {
			if ( $ancestorId <= 0 ) {
				continue;
			}

			$ancestor = function_exists( 'get_post' ) ? $this->vars( get_post( $ancestorId ) ) : [];

			if ( [] === $ancestor ) {
				continue;
			}

			$title = isset( $ancestor['post_title'] ) ? (string) $ancestor['post_title'] : '';

			if ( '' === trim( $title ) && function_exists( 'get_the_title' ) ) {
				$title = (string) get_the_title( $ancestorId );
			}

			$items[] = new Item( $this->plainLabel( $title ), $this->postPermalink( $ancestorId ) );
		}

		return $items;
	}

	/**
	 * One taxonomy term branch: ancestors root first, then the term.
	 *
	 * Uses the primary taxonomy mapping when it names a usable taxonomy
	 * with terms, else the first public taxonomy with terms.
	 *
	 * @param string $postType Post type.
	 * @param int    $postId   Post id.
	 * @return Item[] Ordered branch, empty without terms.
	 */
	private function termBranch( string $postType, int $postId ): array {
		$taxonomy = $this->primaryTaxonomyFor( $postType, $postId );

		if ( '' === $taxonomy ) {
			return [];
		}

		$terms = $this->postTerms( $postId, $taxonomy );

		if ( [] === $terms ) {
			return [];
		}

		$term   = $terms[0];
		$termId = isset( $term['term_id'] ) ? (int) $term['term_id'] : 0;

		if ( $termId <= 0 ) {
			return [];
		}

		$items = $this->termAncestors( $termId, $taxonomy );

		$name = isset( $term['name'] ) ? (string) $term['name'] : '';
		$link = function_exists( 'get_term_link' ) ? get_term_link( $termId, $taxonomy ) : '';

		$items[] = new Item( $this->plainLabel( $name ), is_string( $link ) ? $link : '' );

		return $items;
	}

	/**
	 * Primary taxonomy for a post: mapped value or safe fallback.
	 *
	 * The mapped primary_taxonomy_{post_type} value passes through the
	 * rankkernel/breadcrumbs/post_type_settings filter inside
	 * BreadcrumbsSettings::postTypeSettings, so the returned taxonomy is
	 * already validated against the public taxonomies registered for the
	 * post type. It wins when it names a usable taxonomy with terms on
	 * this post. Otherwise the first public taxonomy with terms supplies
	 * the single term branch.
	 *
	 * @param string $postType Post type.
	 * @param int    $postId   Post id.
	 * @return string Taxonomy slug or empty string.
	 */
	private function primaryTaxonomyFor( string $postType, int $postId ): string {
		$config = $this->settings->postTypeSettings( $postType );
		$mapped = (string) $config['primary_taxonomy'];

		if ( '' !== $mapped && in_array( $mapped, $this->publicTaxonomies( $postType ), true ) && [] !== $this->postTerms( $postId, $mapped ) ) {
			return $mapped;
		}

		foreach ( $this->publicTaxonomies( $postType ) as $taxonomy ) {
			if ( $taxonomy === $mapped ) {
				continue;
			}

			if ( [] !== $this->postTerms( $postId, $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Public taxonomy slugs registered for a post type.
	 *
	 * @param string $postType Post type.
	 * @return string[] Taxonomy slugs.
	 */
	private function publicTaxonomies( string $postType ): array {
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return [];
		}

		$taxes = get_object_taxonomies( $postType, 'objects' );

		if ( ! is_array( $taxes ) ) {
			return [];
		}

		$out = [];

		foreach ( $taxes as $key => $tax ) {
			$vars = $this->vars( $tax );

			if ( [] === $vars ) {
				continue;
			}

			$name   = isset( $vars['name'] ) ? (string) $vars['name'] : '';
			$public = isset( $vars['public'] ) ? (bool) $vars['public'] : false;

			if ( '' === $name && is_string( $key ) ) {
				$name = $key;
			}

			if ( '' !== $name && $public ) {
				$out[] = $name;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Terms attached to a post in one taxonomy, lowest id first.
	 *
	 * @param int    $postId   Post id.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int, array<string, mixed>> Term property maps.
	 */
	private function postTerms( int $postId, string $taxonomy ): array {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return [];
		}

		$terms = get_the_terms( $postId, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$out = [];

		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) ) {
				continue;
			}

			$vars = $this->vars( $term );

			if ( ! isset( $vars['term_id'] ) ) {
				continue;
			}

			$out[] = $vars;
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return (int) $a['term_id'] <=> (int) $b['term_id'];
			}
		);

		return $out;
	}

	/**
	 * Linked term ancestors root first for a term.
	 *
	 * Missing ancestors are skipped so the trail continues from home.
	 * Honors the show_ancestors setting and hierarchical taxonomies only.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy slug.
	 * @return Item[] Ordered ancestors.
	 */
	private function termAncestors( int $termId, string $taxonomy ): array {
		if ( ! (bool) $this->settings->get( 'show_ancestors', true ) ) {
			return [];
		}

		if ( function_exists( 'is_taxonomy_hierarchical' ) && ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return [];
		}

		if ( ! function_exists( 'get_ancestors' ) || ! function_exists( 'get_term' ) ) {
			return [];
		}

		$ancestors = get_ancestors( $termId, $taxonomy, 'taxonomy' );

		if ( ! is_array( $ancestors ) ) {
			return [];
		}

		$items = [];

		foreach ( array_reverse( array_map( 'intval', $ancestors ) ) as $ancestorId ) {
			if ( $ancestorId <= 0 ) {
				continue;
			}

			$ancestor = function_exists( 'get_term' ) ? $this->vars( get_term( $ancestorId, $taxonomy ) ) : [];

			if ( [] === $ancestor ) {
				continue;
			}

			$name = isset( $ancestor['name'] ) ? (string) $ancestor['name'] : '';
			$link = function_exists( 'get_term_link' ) ? get_term_link( $ancestorId, $taxonomy ) : '';

			$items[] = new Item( $this->plainLabel( $name ), is_string( $link ) ? $link : '' );
		}

		return $items;
	}

	/**
	 * Whether a taxonomy belongs to posts (blog page crumb applies).
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool The result.
	 */
	private function taxonomyForPost( string $taxonomy ): bool {
		if ( ! function_exists( 'get_taxonomy' ) ) {
			return false;
		}

		$vars = $this->vars( get_taxonomy( $taxonomy ) );

		if ( ! isset( $vars['object_type'] ) || ! is_array( $vars['object_type'] ) ) {
			return false;
		}

		return in_array( 'post', $vars['object_type'], true );
	}

	/**
	 * Display label for a taxonomy (singular name preferred).
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return string The result.
	 */
	private function taxonomyLabel( string $taxonomy ): string {
		if ( function_exists( 'get_taxonomy' ) ) {
			$vars   = $this->vars( get_taxonomy( $taxonomy ) );
			$labels = isset( $vars['labels'] ) ? $this->vars( $vars['labels'] ) : [];

			$singular = isset( $labels['singular_name'] ) ? trim( (string) $labels['singular_name'] ) : '';

			if ( '' !== $singular ) {
				return $singular;
			}

			$plural = isset( $labels['name'] ) ? trim( (string) $labels['name'] ) : '';

			if ( '' !== $plural ) {
				return $plural;
			}

			$label = isset( $vars['label'] ) ? trim( (string) $vars['label'] ) : '';

			if ( '' !== $label ) {
				return $label;
			}
		}

		return $taxonomy;
	}

	/**
	 * CPT archive crumb from the post type object, or null without one.
	 *
	 * @param string $postType Post type.
	 * @return Item|null Archive crumb or null.
	 */
	private function cptArchiveItem( string $postType ): ?Item {
		if ( ! function_exists( 'get_post_type_object' ) ) {
			return null;
		}

		$vars = $this->vars( get_post_type_object( $postType ) );

		if ( empty( $vars['has_archive'] ) ) {
			return null;
		}

		$labels = isset( $vars['labels'] ) ? $this->vars( $vars['labels'] ) : [];
		$label  = isset( $labels['name'] ) ? (string) $labels['name'] : '';

		if ( '' === trim( $label ) ) {
			$label = isset( $vars['label'] ) ? (string) $vars['label'] : '';
		}

		if ( '' === trim( $label ) ) {
			$label = $postType;
		}

		$url = '';

		if ( function_exists( 'get_post_type_archive_link' ) ) {
			$link = get_post_type_archive_link( $postType );
			$url  = is_string( $link ) ? $link : '';
		}

		return new Item( $label, $url );
	}

	/**
	 * Blog page crumb from the configured posts page, or null.
	 *
	 * @return Item|null Blog crumb or null.
	 */
	private function blogPageItem(): ?Item {
		if ( ! (bool) $this->settings->get( 'show_blog_page', true ) ) {
			return null;
		}

		$pageId = $this->blogPageId();

		if ( $pageId <= 0 ) {
			return null;
		}

		$title = function_exists( 'get_the_title' ) ? get_the_title( $pageId ) : '';

		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			return null;
		}

		return new Item( $title, $this->postPermalink( $pageId ) );
	}

	/**
	 * Configured posts page id, or zero without one.
	 *
	 * @return int The result.
	 */
	private function blogPageId(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}

		return (int) get_option( 'page_for_posts', 0 );
	}

	/**
	 * Post type for a post type archive request.
	 *
	 * @return string Post type or empty string.
	 */
	private function archivePostType(): string {
		$fromVar = function_exists( 'get_query_var' ) ? get_query_var( 'post_type' ) : '';

		if ( is_string( $fromVar ) && '' !== $fromVar ) {
			return $fromVar;
		}

		$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		if ( is_object( $obj ) && isset( $obj->name ) && is_string( $obj->name ) && '' !== $obj->name ) {
			return $obj->name;
		}

		return '';
	}

	/**
	 * Resolve a current item label.
	 *
	 * Priority: flags.breadcrumb_title from the Context meta first, then
	 * the native object title, then a translatable untitled fallback.
	 *
	 * @param string $fallback Native object title.
	 * @return string The result.
	 */
	private function resolveLabel( string $fallback ): string {
		$meta  = $this->ctx->meta();
		$flags = ( isset( $meta['flags'] ) && is_array( $meta['flags'] ) ) ? $meta['flags'] : [];

		$override = isset( $flags['breadcrumb_title'] ) ? trim( (string) $flags['breadcrumb_title'] ) : '';

		if ( '' !== $override ) {
			return $override;
		}

		return $this->plainLabel( $fallback );
	}

	/**
	 * Plain label with the untitled fallback.
	 *
	 * @param string $title Raw title.
	 * @return string The result.
	 */
	private function plainLabel( string $title ): string {
		$title = trim( $title );

		if ( '' !== $title ) {
			return $title;
		}

		return __( '(no title)', 'rankkernel' );
	}

	/**
	 * Current item URL: canonical first, then context permalink.
	 *
	 * @param string $fallback Fallback URL.
	 * @return string The result.
	 */
	private function currentUrl( string $fallback = '' ): string {
		$meta = $this->ctx->meta();

		$canonical = isset( $meta['canonical'] ) ? trim( (string) $meta['canonical'] ) : '';

		if ( '' !== $canonical ) {
			return $canonical;
		}

		$permalink = $this->ctx->permalink();

		if ( '' !== $permalink ) {
			return $permalink;
		}

		return $fallback;
	}

	/**
	 * Month archive label.
	 *
	 * @param int $year  Year.
	 * @param int $month Month number.
	 * @return string The result.
	 */
	private function monthLabel( int $year, int $month ): string {
		if ( function_exists( 'date_i18n' ) ) {
			$label = date_i18n( 'F Y', (int) mktime( 0, 0, 0, $month, 1, $year ) );

			if ( is_string( $label ) && '' !== $label ) {
				return $label;
			}
		}

		return sprintf( '%04d-%02d', $year, $month );
	}

	/**
	 * Day archive label.
	 *
	 * @param int $year  Year.
	 * @param int $month Month number.
	 * @param int $day   Day number.
	 * @return string The result.
	 */
	private function dayLabel( int $year, int $month, int $day ): string {
		if ( function_exists( 'date_i18n' ) ) {
			$label = date_i18n( 'F j, Y', (int) mktime( 0, 0, 0, $month, $day, $year ) );

			if ( is_string( $label ) && '' !== $label ) {
				return $label;
			}
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Visible only Page N crumb, always excluded from schema.
	 *
	 * @param int $pageNumber Page number.
	 * @return Item The result.
	 */
	private function pageItem( int $pageNumber ): Item {
		$number = function_exists( 'number_format_i18n' ) ? (string) number_format_i18n( $pageNumber ) : (string) $pageNumber;

		// translators: %s: page number.
		return new Item( sprintf( __( 'Page %s', 'rankkernel' ), $number ), '', false, true );
	}

	/**
	 * Visible only comments page crumb, always excluded from schema.
	 *
	 * @param int $pageNumber Comments page number.
	 * @return Item The result.
	 */
	private function commentsPageItem( int $pageNumber ): Item {
		$number = function_exists( 'number_format_i18n' ) ? (string) number_format_i18n( $pageNumber ) : (string) $pageNumber;

		// translators: %s: comments page number.
		return new Item( sprintf( __( 'Comments Page %s', 'rankkernel' ), $number ), '', false, true );
	}

	/**
	 * Append a paged Page N crumb for archives, search, author, and date.
	 *
	 * @param Item[] $items Trail so far.
	 * @return Item[] Trail with pagination.
	 */
	private function appendArchivePagination( array $items ): array {
		$paged = $this->queryVar( 'paged' );

		if ( $paged > 1 ) {
			$items[] = $this->pageItem( $paged );
		}

		return $items;
	}

	/**
	 * Append paged crumbs for singular content and comment pages.
	 *
	 * @param Item[] $items Trail so far.
	 * @return Item[] Trail with pagination.
	 */
	private function appendSingularPagination( array $items ): array {
		$page = $this->queryVar( 'page' );

		if ( $page > 1 ) {
			$items[] = $this->pageItem( $page );
		}

		$cpage = $this->queryVar( 'cpage' );

		if ( $cpage > 1 ) {
			$items[] = $this->commentsPageItem( $cpage );
		}

		return $items;
	}

	/**
	 * Drop the current item when show_current is off.
	 *
	 * Runs before pagination is appended, so a Page N crumb stays
	 * visible even when the current item is hidden.
	 *
	 * @param Item[] $items Trail so far.
	 * @return Item[] Trail without the current item when hidden.
	 */
	private function applyShowCurrent( array $items ): array {
		if ( $this->showCurrent() ) {
			return $items;
		}

		if ( [] === $items ) {
			return $items;
		}

		array_pop( $items );

		return $items;
	}

	/**
	 * Collapse duplicate consecutive items by label and URL.
	 *
	 * @param Item[] $items Raw trail.
	 * @return Item[] Deduplicated trail.
	 */
	private function collapse( array $items ): array {
		$out = [];

		foreach ( $items as $item ) {
			$prev = end( $out );

			if ( $prev instanceof Item && $prev->label() === $item->label() && $prev->url() === $item->url() ) {
				continue;
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Append the home crumb when show_home is on.
	 *
	 * @param Item[] $items Trail under construction.
	 */
	private function addHome( array &$items ): void {
		if ( $this->showHome() ) {
			$items[] = $this->homeItem();
		}
	}

	/**
	 * Home crumb from the home_label setting.
	 *
	 * @return Item The result.
	 */
	private function homeItem(): Item {
		$label = (string) $this->settings->get( 'home_label', 'Home' );

		if ( '' === trim( $label ) ) {
			$label = 'Home';
		}

		$url = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';

		return new Item( $label, $url );
	}

	/**
	 * Integer query variable, zero when unavailable.
	 *
	 * @param string $key Variable name.
	 * @return int The result.
	 */
	private function queryVar( string $key ): int {
		if ( ! function_exists( 'get_query_var' ) ) {
			return 0;
		}

		return (int) get_query_var( $key, 0 );
	}

	/**
	 * Whether the request is the site front page.
	 *
	 * @return bool The result.
	 */
	private function isFrontPage(): bool {
		return function_exists( 'is_front_page' ) && (bool) is_front_page();
	}

	/**
	 * Whether the request is the blog posts index (not the front page).
	 *
	 * @return bool The result.
	 */
	private function isBlogIndex(): bool {
		return function_exists( 'is_home' ) && (bool) is_home() && ! $this->isFrontPage();
	}

	/**
	 * Whether the front page shows posts (not a static page).
	 *
	 * @return bool The result.
	 */
	private function isPostsFrontPage(): bool {
		if ( function_exists( 'is_home' ) && (bool) is_home() ) {
			return true;
		}

		if ( function_exists( 'get_option' ) ) {
			return 'posts' === (string) get_option( 'show_on_front', 'posts' );
		}

		return true;
	}

	/**
	 * Whether the request is a search.
	 *
	 * @return bool The result.
	 */
	private function isSearch(): bool {
		return function_exists( 'is_search' ) && (bool) is_search();
	}

	/**
	 * Whether the request is a 404.
	 *
	 * @return bool The result.
	 */
	private function is404(): bool {
		return function_exists( 'is_404' ) && (bool) is_404();
	}

	/**
	 * Whether the request is a post type archive.
	 *
	 * @return bool The result.
	 */
	private function isPostTypeArchive(): bool {
		return function_exists( 'is_post_type_archive' ) && (bool) is_post_type_archive();
	}

	/**
	 * Whether the request is an author archive.
	 *
	 * @return bool The result.
	 */
	private function isAuthor(): bool {
		return function_exists( 'is_author' ) && (bool) is_author();
	}

	/**
	 * Whether the request is a date archive.
	 *
	 * @return bool The result.
	 */
	private function isDate(): bool {
		return function_exists( 'is_date' ) && (bool) is_date();
	}

	/**
	 * Whether a post type is hierarchical.
	 *
	 * @param string $postType Post type.
	 * @return bool The result.
	 */
	private function isHierarchicalPostType( string $postType ): bool {
		return function_exists( 'is_post_type_hierarchical' ) && (bool) is_post_type_hierarchical( $postType );
	}
}
