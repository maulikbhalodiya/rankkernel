<?php
/**
 * Per post and per page SEO metadata editor.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Metadata\TagsReplacer;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Plugin;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Classic editor metabox plus the PHP side of the Gutenberg sidebar.
 *
 * Owns capability checks, nonce verification, request handling, saving,
 * reset handling, token resolution, and view state preparation. The HTML
 * lives in src/Admin/Views/metadata-box.php.
 *
 * The stored payload is the override. An empty stored title or description
 * means the template applies, so the editor can always show the inherited
 * template string next to the field value.
 *
 * The Classic save path merges the submitted fields over the existing
 * payload and passes the result through MetaPayload::sanitize(). It never
 * writes defaults over unrelated fields, so the schema, flags, and
 * focus_keywords subtrees survive untouched. Gutenberg saves through the
 * registered REST meta field instead, so a save without the metabox fields
 * present returns early and never clobbers the REST write.
 *
 * This is the final baseline surface. Later content analysis, AI, and score
 * features plug into it, so every capability is a small, named method.
 */
final class MetadataBox {
	/**
	 * Post meta key holding the payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

	/**
	 * Nonce action and field for the save handler.
	 */
	private const NONCE_ACTION = 'rankkernel_meta_save';
	private const NONCE_FIELD  = 'rankkernel_meta_nonce';

	/**
	 * Sentinel field proving the metabox posted its own fields.
	 */
	private const FIELDS_FIELD = 'rankkernel_meta_fields';

	/**
	 * Asset handles and the single localized object name.
	 */
	private const EDITOR_SCRIPT   = 'rankkernel-metadata-editor';
	private const EDITOR_STYLE    = 'rankkernel-metadata-editor';
	private const CLASSIC_STYLE   = 'rankkernel-metadata-classic';
	private const SIDEBAR_SCRIPT  = 'rankkernel-metadata-sidebar';
	private const ANALYSIS_SCRIPT = 'rankkernel-analysis-editor';
	private const LOCALIZE_NAME   = 'rankkernelMetaEditor';

	/**
	 * Pure analysis engine parts, registered in load order.
	 *
	 * @var array<string, array{0: string, 1: string[]}>
	 */
	private const ENGINE_SCRIPTS = [
		'rankkernel-analysis-text-stats'      => [ 'assets/js/analysis/text-stats.js', [] ],
		'rankkernel-analysis-accents'         => [ 'assets/js/analysis/accents.js', [] ],
		'rankkernel-analysis-format'          => [ 'assets/js/analysis/analysis-format.js', [] ],
		'rankkernel-analysis-keyword-matcher' => [ 'assets/js/analysis/keyword-matcher.js', [ 'rankkernel-analysis-text-stats', 'rankkernel-analysis-accents' ] ],
		'rankkernel-analysis-analyzer'        => [ 'assets/js/analysis/analyzer.js', [ 'rankkernel-analysis-keyword-matcher', 'rankkernel-analysis-format' ] ],
		'rankkernel-analysis-editor-bridge'   => [ 'assets/js/analysis/editor-bridge.js', [] ],
	];

	/**
	 * Field length budgets shown in the editor.
	 */
	private const TITLE_LIMIT       = 60;
	private const DESCRIPTION_LIMIT = 160;

	/**
	 * Supported Twitter card values.
	 *
	 * @var string[]
	 */
	private const CARD_VALUES = [ 'summary_large_image', 'summary' ];

	/**
	 * Supported max-image-preview values.
	 *
	 * @var string[]
	 */
	private const PREVIEW_VALUES = [ 'none', 'standard', 'large' ];

	/**
	 * Manual schema field keys with labels for the Schema tab.
	 *
	 * Mirrored from SchemaMetabox, which owns the schema save path. The
	 * Classic SEO box renders the same rows so both surfaces post the
	 * same names; keep this map in sync with that class.
	 *
	 * @var array<string, string>
	 */
	private const SCHEMA_FIELD_LABELS = [
		'headline'           => 'Headline',
		'description'        => 'Description',
		'author'             => 'Author',
		'price'              => 'Price',
		'priceCurrency'      => 'Price currency',
		'sku'                => 'SKU',
		'availability'       => 'Availability',
		'ratingValue'        => 'Rating value',
		'reviewCount'        => 'Review count',
		'bestRating'         => 'Best rating',
		'worstRating'        => 'Worst rating',
		'isbn'               => 'ISBN',
		'startDate'          => 'Start date',
		'endDate'            => 'End date',
		'locationName'       => 'Location name',
		'streetAddress'      => 'Street address',
		'addressLocality'    => 'City',
		'addressRegion'      => 'Region',
		'postalCode'         => 'Postal code',
		'addressCountry'     => 'Country',
		'performer'          => 'Performer',
		'eventStatus'        => 'Event status',
		'ingredients'        => 'Ingredients',
		'instructions'       => 'Instructions',
		'prepTime'           => 'Prep time',
		'cookTime'           => 'Cook time',
		'totalTime'          => 'Total time',
		'yield'              => 'Yield',
		'areaServed'         => 'Area served',
		'thumbnailUrl'       => 'Thumbnail URL',
		'uploadDate'         => 'Upload date',
		'duration'           => 'Duration',
		'contentUrl'         => 'Content URL',
		'appCategory'        => 'App category',
		'operatingSystem'    => 'Operating system',
		'artist'             => 'Artist',
		'album'              => 'Album',
		'dateCreated'        => 'Date created',
		'director'           => 'Director',
		'company'            => 'Company',
		'jobLocation'        => 'Job location',
		'salary'             => 'Salary',
		'datePosted'         => 'Date posted',
		'validThrough'       => 'Valid through',
		'claimReviewed'      => 'Claim reviewed',
		'datePublished'      => 'Date published',
		'license'            => 'License URL',
		'distributionUrl'    => 'File URL',
		'distributionFormat' => 'File format',
		'seriesName'         => 'Series name',
		'question'           => 'Question',
		'answer'             => 'Answer',
		'answerAuthor'       => 'Answer author',
		'itemName'           => 'Reviewed item',
		'reviewBody'         => 'Review text',
		'telephone'          => 'Phone',
		'priceRange'         => 'Price range',
		'openingHours'       => 'Opening hours',
		'caption'            => 'Caption',
		'width'              => 'Width',
		'height'             => 'Height',
		'speakable'          => 'Speakable selectors',
		'about'              => 'About',
		'mentions'           => 'Mentions',
	];

	/**
	 * Manual schema field visibility per type, mirrored from SchemaMetabox.
	 *
	 * Every key a schema piece reads lives here, so the Schema tab can
	 * set each one. Rows unrelated to the chosen type stay hidden.
	 *
	 * @var array<string, string[]>
	 */
	private const SCHEMA_FIELD_TYPES = [
		'headline'           => [ '*' ],
		'description'        => [ '*' ],
		'author'             => [ '*' ],
		'price'              => [ 'Product', 'Event', 'Service', 'SoftwareApplication' ],
		'priceCurrency'      => [ 'Product', 'Event', 'Service', 'JobPosting', 'SoftwareApplication' ],
		'sku'                => [ 'Product' ],
		'availability'       => [ 'Product' ],
		'ratingValue'        => [ 'Product', 'SoftwareApplication', 'Movie', 'ClaimReview', 'Review' ],
		'reviewCount'        => [ 'Product', 'SoftwareApplication', 'Movie' ],
		'bestRating'         => [ 'ClaimReview', 'Review' ],
		'worstRating'        => [ 'ClaimReview', 'Review' ],
		'isbn'               => [ 'Book' ],
		'startDate'          => [ 'Event' ],
		'endDate'            => [ 'Event' ],
		'locationName'       => [ 'Event', 'JobPosting' ],
		'streetAddress'      => [ 'Event', 'LocalBusiness', 'JobPosting' ],
		'addressLocality'    => [ 'Event', 'LocalBusiness', 'JobPosting' ],
		'addressRegion'      => [ 'Event', 'LocalBusiness', 'JobPosting' ],
		'postalCode'         => [ 'Event', 'LocalBusiness', 'JobPosting' ],
		'addressCountry'     => [ 'Event', 'LocalBusiness', 'JobPosting' ],
		'performer'          => [ 'Event' ],
		'eventStatus'        => [ 'Event' ],
		'ingredients'        => [ 'Recipe' ],
		'instructions'       => [ 'Recipe' ],
		'prepTime'           => [ 'Recipe' ],
		'cookTime'           => [ 'Recipe' ],
		'totalTime'          => [ 'Recipe' ],
		'yield'              => [ 'Recipe' ],
		'areaServed'         => [ 'Service' ],
		'thumbnailUrl'       => [ 'VideoObject' ],
		'uploadDate'         => [ 'VideoObject' ],
		'duration'           => [ 'VideoObject', 'PodcastEpisode' ],
		'contentUrl'         => [ 'VideoObject', 'PodcastEpisode', 'ImageObject' ],
		'appCategory'        => [ 'SoftwareApplication' ],
		'operatingSystem'    => [ 'SoftwareApplication' ],
		'artist'             => [ 'MusicRecording' ],
		'album'              => [ 'MusicRecording' ],
		'dateCreated'        => [ 'Movie' ],
		'director'           => [ 'Movie' ],
		'company'            => [ 'JobPosting' ],
		'jobLocation'        => [ 'JobPosting' ],
		'salary'             => [ 'JobPosting' ],
		'datePosted'         => [ 'JobPosting' ],
		'validThrough'       => [ 'JobPosting' ],
		'claimReviewed'      => [ 'ClaimReview' ],
		'datePublished'      => [ 'ClaimReview', 'PodcastEpisode', 'Review' ],
		'license'            => [ 'Dataset' ],
		'distributionUrl'    => [ 'Dataset' ],
		'distributionFormat' => [ 'Dataset' ],
		'seriesName'         => [ 'PodcastEpisode' ],
		'question'           => [ 'QAPage' ],
		'answer'             => [ 'QAPage' ],
		'answerAuthor'       => [ 'QAPage' ],
		'itemName'           => [ 'Review' ],
		'reviewBody'         => [ 'Review' ],
		'telephone'          => [ 'LocalBusiness' ],
		'priceRange'         => [ 'LocalBusiness' ],
		'openingHours'       => [ 'LocalBusiness' ],
		'caption'            => [ 'ImageObject' ],
		'width'              => [ 'ImageObject' ],
		'height'             => [ 'ImageObject' ],
		'speakable'          => [ 'WebPage' ],
		'about'              => [ 'WebPage' ],
		'mentions'           => [ 'WebPage' ],
	];

	/**
	 * Tokens the backend can resolve through TagsReplacer, with labels.
	 *
	 * The keys are the raw token names without percent signs and the values
	 * are the human labels. This list is the single source for the tokens
	 * map, the tokenLabels map, and the quick insert control, so the editor
	 * can never advertise a token the backend cannot resolve. It must hold
	 * exactly TagsReplacer::SUPPORTED_TOKENS; a test locks that parity.
	 *
	 * @var array<string, string>
	 */
	private const TOKEN_LABELS = [
		'title'       => 'Title',
		'sitename'    => 'Site name',
		'sep'         => 'Separator',
		'excerpt'     => 'Excerpt',
		'date'        => 'Date',
		'author'      => 'Author',
		'category'    => 'Category',
		'page'        => 'Page',
		'currentdate' => 'Current date',
	];

	/**
	 * Settings store for template and separator resolution.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $store;

	/**
	 * Tags replacer sharing token resolution with the frontend renderer.
	 *
	 * @var TagsReplacer
	 */
	private TagsReplacer $replacer;

	/**
	 * Context factory, test double seam.
	 *
	 * @var callable(int): Context|null
	 */
	private $contextFactory;

	/**
	 * Synthetic query factory, test double seam.
	 *
	 * @var callable(): WP_Query|null
	 */
	private $queryFactory;

	/**
	 * Module enable map, used to keep a disabled module's transport out of the
	 * localized contract. Null means the caller supplied none, and the contract
	 * is built as it was before the map existed.
	 *
	 * @var ModuleEnableMap|null
	 */
	private ?ModuleEnableMap $enableMap;

	/**
	 * Constructor.
	 *
	 * @param SettingsStore|null          $store          Settings store override, test double seam.
	 * @param TagsReplacer|null           $replacer       Replacer override, test double seam.
	 * @param callable(int): Context|null $contextFactory Context factory override, test double seam.
	 * @param callable(): WP_Query|null   $queryFactory   Query factory override, test double seam.
	 * @param ModuleEnableMap|null        $enableMap      Enable map, gates module owned transport.
	 */
	public function __construct( ?SettingsStore $store = null, ?TagsReplacer $replacer = null, ?callable $contextFactory = null, ?callable $queryFactory = null, ?ModuleEnableMap $enableMap = null ) {
		$this->store          = $store ?? new SettingsStore();
		$this->replacer       = $replacer ?? new TagsReplacer();
		$this->contextFactory = $contextFactory;
		$this->queryFactory   = $queryFactory;
		$this->enableMap      = $enableMap;
	}

	/**
	 * Register hooks, admin only by wiring.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'addBoxes' ], 10, 2 );
		add_action( 'save_post', [ $this, 'handleSave' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ] );
	}

	/**
	 * Add the box to every public post type except attachment.
	 *
	 * Types are read at runtime so later registrations apply.
	 *
	 * @param string $postType Current post type.
	 * @param mixed  $post     Current post object.
	 */
	public function addBoxes( string $postType, mixed $post = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- callback signature required by the stubbed WordPress function under test.
		// The block editor already renders the same controls through the
		// sidebar, so registering the box there would duplicate every field.
		if ( ScreenGuard::isBlockEditorScreen() ) {
			return;
		}

		if ( ! $this->isSupportedType( $postType ) ) {
			return;
		}

		add_meta_box(
			'rankkernel-meta',
			__( 'RankKernel SEO', 'rankkernel' ),
			[ $this, 'renderBox' ],
			$postType,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue the editor script and style on post edit screens only.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( ! in_array( $hookSuffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( ! is_object( $screen ) || ! is_string( $screen->base ) || 'post' !== $screen->base ) {
				return;
			}
		}

		if ( ! $this->isSupportedType( $this->currentPostType() ) ) {
			return;
		}

		$pluginFile = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';
		$scriptSrc  = function_exists( 'plugins_url' ) ? plugins_url( 'assets/js/metadata-editor.js', $pluginFile ) : '';
		$styleSrc   = function_exists( 'plugins_url' ) ? plugins_url( 'assets/css/metadata-editor.css', $pluginFile ) : '';
		$classicSrc = function_exists( 'plugins_url' ) ? plugins_url( 'assets/css/metadata-classic.css', $pluginFile ) : '';
		$version    = Plugin::version();

		wp_register_style( self::EDITOR_STYLE, $styleSrc, [], $version );
		wp_enqueue_style( self::EDITOR_STYLE );

		wp_register_style( self::CLASSIC_STYLE, $classicSrc, [], $version );
		wp_enqueue_style( self::CLASSIC_STYLE );

		wp_register_script( self::EDITOR_SCRIPT, $scriptSrc, [], $version, true );

		if ( function_exists( 'wp_localize_script' ) ) {
			wp_localize_script( self::EDITOR_SCRIPT, self::LOCALIZE_NAME, $this->localizedState( $this->currentPostId() ) );
		}

		wp_enqueue_script( self::EDITOR_SCRIPT );

		if ( $this->analysisEnabled() ) {
			$engine      = $this->enqueueAnalysisEngine();
			$analysisSrc = function_exists( 'plugins_url' ) ? plugins_url( 'assets/js/analysis-editor.js', $pluginFile ) : '';

			wp_register_script( self::ANALYSIS_SCRIPT, $analysisSrc, array_merge( [ self::EDITOR_SCRIPT, 'wp-i18n' ], $engine ), $version, true );
			wp_enqueue_script( self::ANALYSIS_SCRIPT );
		}
	}

	/**
	 * Enqueue the Gutenberg sidebar script on supported edit screens.
	 *
	 * Localizes the same rankkernelMetaEditor object the classic script uses.
	 */
	public function enqueueEditorAssets(): void {
		if ( ! $this->isSupportedType( $this->currentPostType() ) ) {
			return;
		}

		$pluginFile = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';
		$scriptSrc  = function_exists( 'plugins_url' ) ? plugins_url( 'assets/js/metadata-sidebar.js', $pluginFile ) : '';
		$version    = Plugin::version();

		$engine = $this->analysisEnabled() ? $this->enqueueAnalysisEngine() : [];
		$deps   = array_merge( [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ], $engine );

		wp_register_script( self::SIDEBAR_SCRIPT, $scriptSrc, $deps, $version, true );

		if ( function_exists( 'wp_localize_script' ) ) {
			wp_localize_script( self::SIDEBAR_SCRIPT, self::LOCALIZE_NAME, $this->localizedState( $this->currentPostId() ) );
		}

		wp_enqueue_script( self::SIDEBAR_SCRIPT );
	}

	/**
	 * Render the box for a post.
	 *
	 * Prepares every variable the view consumes and requires it. Malformed
	 * stored data fails safe to defaults so editor rendering never breaks.
	 *
	 * @param mixed $post Current post object.
	 */
	public function renderBox( mixed $post ): void {
		$postId = ( is_object( $post ) && isset( $post->ID ) ) ? (int) $post->ID : 0;

		if ( $postId <= 0 ) {
			return;
		}

		$meta    = $this->safePayload( $postId );
		$context = $this->contextFor( $postId );

		$titleTemplate       = (string) $this->store->get( 'title_template', '%%title%% %%sep%% %%sitename%%' );
		$descriptionTemplate = (string) $this->store->get( 'description_template', '' );

		$resolvedTitleTemplate = $this->resolveTemplate( $context, $titleTemplate, 'title_template' );

		$resolvedDescriptionTemplate = '';
		if ( '' !== trim( $descriptionTemplate ) ) {
			$resolvedDescriptionTemplate = $this->resolveTemplate( $context, $descriptionTemplate, 'description' );
		}

		if ( '' === trim( $resolvedDescriptionTemplate ) && $context->isSingular() ) {
			$resolvedDescriptionTemplate = $context->excerpt();
		}

		$titleOverride       = isset( $meta['title'] ) ? trim( (string) $meta['title'] ) : '';
		$descriptionOverride = isset( $meta['description'] ) ? trim( (string) $meta['description'] ) : '';

		$effectiveTitle       = $this->effectiveTitle( $context, $meta );
		$effectiveDescription = $this->effectiveDescription( $context, $meta );

		$titleInherited       = '' === $titleOverride;
		$descriptionInherited = '' === $descriptionOverride;

		$tokenRows        = $this->tokenRows( $context );
		$titleLimit       = self::TITLE_LIMIT;
		$descriptionLimit = self::DESCRIPTION_LIMIT;

		$canonical = isset( $meta['canonical'] ) ? (string) $meta['canonical'] : '';

		$robots  = $this->subtree( $meta, 'robots' );
		$og      = $this->subtree( $meta, 'og' );
		$twitter = $this->subtree( $meta, 'twitter' );

		$ogImageUrl      = isset( $og['image'] ) ? (string) $og['image'] : '';
		$ogImageId       = isset( $og['image_id'] ) ? (int) $og['image_id'] : 0;
		$twitterImageUrl = isset( $twitter['image'] ) ? (string) $twitter['image'] : '';
		$twitterImageId  = isset( $twitter['image_id'] ) ? (int) $twitter['image_id'] : 0;
		$defaultOgImage  = $this->defaultOgImage( $postId );

		$cardOptions = [
			[
				'value' => 'summary_large_image',
				'label' => __( 'Summary with large image', 'rankkernel' ),
			],
			[
				'value' => 'summary',
				'label' => __( 'Summary', 'rankkernel' ),
			],
		];

		$maxImagePreviewOptions = [
			[
				'value' => '',
				'label' => __( 'Default', 'rankkernel' ),
			],
			[
				'value' => 'none',
				'label' => __( 'None', 'rankkernel' ),
			],
			[
				'value' => 'standard',
				'label' => __( 'Standard', 'rankkernel' ),
			],
			[
				'value' => 'large',
				'label' => __( 'Large', 'rankkernel' ),
			],
		];

		$previewUrl      = function_exists( 'get_permalink' ) ? (string) get_permalink( $postId ) : '';
		$previewSiteName = $context->siteName();

		$robotsIndex     = ! empty( $robots['index'] );
		$robotsFollow    = ! empty( $robots['follow'] );
		$canonicalCustom = '' !== trim( $canonical );

		$schemaPostType = function_exists( 'get_post_type' ) ? (string) get_post_type( $postId ) : '';
		$schema         = $this->readSchemaSubtree( $meta );
		$schemaDisabled = ! empty( $schema['disabled'] );

		$rawSchemaType  = $schema['type'] ?? '';
		$schemaSelected = ( is_string( $rawSchemaType ) && in_array( $rawSchemaType, SchemaTypes::SUPPORTED, true ) )
			? $rawSchemaType
			: '';

		$schemaResolved = $this->resolvedSchemaDefault( $schemaPostType );

		$schemaAutoLabel = '' !== $schemaResolved
			/* translators: %s: schema type name, e.g. Blog Posting. */
			? sprintf( __( 'Automatic (%s)', 'rankkernel' ), SchemaTypes::label( $schemaResolved ) )
			: __( 'Automatic', 'rankkernel' );

		$schemaTypeOptions = [];

		foreach ( SchemaTypes::SUPPORTED as $supportedType ) {
			$schemaTypeOptions[] = [
				'value' => $supportedType,
				'label' => SchemaTypes::label( $supportedType ),
			];
		}

		$schemaFieldRows = $this->schemaFieldRows( $schema, $schemaSelected );
		$schemaCustom    = ( isset( $schema['custom'] ) && is_array( $schema['custom'] ) ) ? $schema['custom'] : [];

		if ( function_exists( 'wp_json_encode' ) ) {
			$schemaCustomJson = (string) wp_json_encode( $schemaCustom, JSON_PRETTY_PRINT );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback keeps unit tests free of WP, used only when wp_json_encode is missing.
			$schemaCustomJson = (string) json_encode( $schemaCustom, JSON_PRETTY_PRINT );
		}

		$schemaValidationMessages = $this->schemaValidationMessages( $schemaSelected, $schema );
		$schemaValidationLabel    = '' === $schemaSelected ? 'Automatic' : $schemaSelected;

		$schemaRichResultsUrl = 'https://search.google.com/test/rich-results?url=' . rawurlencode( $previewUrl );
		$schemaValidatorUrl   = 'https://validator.schema.org/';

		$schemaExportUrl = ( function_exists( 'wp_nonce_url' ) && function_exists( 'admin_url' ) )
			? wp_nonce_url(
				admin_url( 'admin-post.php?action=rankkernel_schema_export&post=' . $postId ),
				'rankkernel_schema_export_' . $postId
			)
			: '#';

		$analysisEnabled = $this->analysisEnabled();
		$focusKeywords   = [];

		if ( isset( $meta['focus_keywords'] ) && is_array( $meta['focus_keywords'] ) ) {
			foreach ( $meta['focus_keywords'] as $focusKeyword ) {
				$focusKeywords[] = (string) $focusKeyword;
			}
		}

		require __DIR__ . '/Views/metadata-box.php';
	}

	/**
	 * Save handler on save_post.
	 *
	 * Skips autosaves and revisions, returns early when the metabox fields
	 * are absent, then checks capability and nonce before merging the
	 * submitted fields over the existing payload. save_post fires for REST
	 * writes, cron, WP-CLI and importers, so the fields guard runs first to
	 * keep an unrelated save from reaching wp_die().
	 *
	 * @param int   $postId Current post id.
	 * @param mixed $post   Current post object.
	 */
	public function handleSave( int $postId, mixed $post = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- callback signature required by the stubbed WordPress function under test.
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $postId ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
			return;
		}

		// Gutenberg saves through the REST meta field, so its request has no
		// metabox fields. Returning here keeps the Classic path from wiping
		// the REST write with a defaults shaped payload, and keeps a save
		// without edit_post capability from reaching wp_die().
		if ( ! $this->hasPostedFields() ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to edit this post.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$verified = check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );
		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$existing = $this->readPayload( $postId );
		$merged   = $this->mergePosted( $existing );

		update_post_meta( $postId, self::META_KEY, $merged );
	}

	/**
	 * Build the localized rankkernelMetaEditor object.
	 *
	 * The templates entry holds the effective preview value: the stored
	 * override when set, otherwise the template rendered through the same
	 * Context and TagsReplacer the frontend uses. Every token exposed here
	 * is one TagsReplacer can resolve.
	 *
	 * @param int $postId Current post id.
	 * @return array<string, mixed> The localized contract.
	 */
	public function localizedState( int $postId ): array {
		$titleTemplate       = (string) $this->store->get( 'title_template', '%%title%% %%sep%% %%sitename%%' );
		$descriptionTemplate = (string) $this->store->get( 'description_template', '' );

		$tokens      = $this->emptyTokens();
		$tokenLabels = $this->tokenLabels();

		$titleEffective       = $this->resolveTemplate( null, $titleTemplate, 'title_template' );
		$descriptionEffective = '' !== trim( $descriptionTemplate ) ? $descriptionTemplate : '';

		$siteName       = '';
		$defaultOgImage = '';

		if ( $postId > 0 ) {
			$context = $this->contextFor( $postId );
			$meta    = $this->safePayload( $postId );

			$tokens               = $this->resolvedTokens( $context );
			$titleEffective       = $this->effectiveTitle( $context, $meta );
			$descriptionEffective = $this->effectiveDescription( $context, $meta );

			$siteName       = $context->siteName();
			$defaultOgImage = $this->defaultOgImage( $postId );
		}

		$permalink = ( $postId > 0 && function_exists( 'get_permalink' ) ) ? (string) get_permalink( $postId ) : '';
		$siteUrl   = function_exists( 'site_url' ) ? (string) site_url() : '';
		$homeUrl   = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';

		$featuredAlt = $this->featuredAlt( $postId );

		$state = [
			'postId'      => $postId,
			'permalink'   => $permalink,
			'siteUrl'     => $siteUrl,
			'siteName'    => $siteName,
			'homeUrl'     => $homeUrl,
			'featuredAlt' => $featuredAlt,
			'templates'   => [
				'title'       => $titleEffective,
				'description' => $descriptionEffective,
			],
			'tokens'      => $tokens,
			'tokenLabels' => $tokenLabels,
			'limits'      => [
				'title'       => self::TITLE_LIMIT,
				'description' => self::DESCRIPTION_LIMIT,
			],
			'defaults'    => [
				'ogImage' => $defaultOgImage,
			],
			'strings'     => $this->strings(),
			'restPath'    => $this->restPath( $postId ),
		];

		// The path is the module enabled signal the local engine checks, so the
		// block editor panel only mounts when the analysis module is on. The
		// route itself is retained deliberately for REST and headless consumers,
		// and the editors no longer post a draft to it.
		if ( $this->analysisEnabled() ) {
			$state['analysis'] = [
				'path'  => function_exists( 'rest_url' ) ? (string) rest_url( 'rankkernel/v1/analysis' ) : '',
				'nonce' => function_exists( 'wp_create_nonce' ) ? (string) wp_create_nonce( 'wp_rest' ) : '',
			];
		}

		return $state;
	}

	/**
	 * Whether the analysis module is enabled for this request.
	 *
	 * Mirrors the gate localizedState() uses for the analysis transport, so
	 * the panel, its transport and its asset can only ever appear together.
	 * A null enable map means the caller supplied none, which keeps the
	 * contract built as it was before the map existed.
	 *
	 * @return bool The result.
	 */
	private function analysisEnabled(): bool {
		return null === $this->enableMap || $this->enableMap->isEnabled( 'analysis' );
	}

	/**
	 * Register and enqueue the pure engine scripts, in load order.
	 *
	 * @return string[] The registered handles, in load order.
	 */
	private function enqueueAnalysisEngine(): array {
		$pluginFile = defined( 'RANKKERNEL_FILE' ) ? (string) RANKKERNEL_FILE : '';
		$version    = Plugin::version();
		$handles    = [];

		foreach ( self::ENGINE_SCRIPTS as $handle => $parts ) {
			$source = function_exists( 'plugins_url' ) ? plugins_url( $parts[0], $pluginFile ) : '';
			wp_register_script( $handle, $source, $parts[1], $version, true );
			wp_enqueue_script( $handle );
			$handles[] = $handle;
		}

		return $handles;
	}

	/**
	 * Resolve the effective title for a post, override first.
	 *
	 * @param int $postId Current post id.
	 * @return string The result.
	 */
	public function effectiveTitleFor( int $postId ): string {
		return $this->effectiveTitle( $this->contextFor( $postId ), $this->safePayload( $postId ) );
	}

	/**
	 * Resolve the effective description for a post, override first.
	 *
	 * @param int $postId Current post id.
	 * @return string The result.
	 */
	public function effectiveDescriptionFor( int $postId ): string {
		return $this->effectiveDescription( $this->contextFor( $postId ), $this->safePayload( $postId ) );
	}

	/**
	 * Whether a post type supports the metadata editor.
	 *
	 * @param string $postType Current post type.
	 * @return bool The result.
	 */
	public function isSupportedType( string $postType ): bool {
		if ( '' === $postType || 'attachment' === $postType ) {
			return false;
		}

		$types = function_exists( 'get_post_types' ) ? get_post_types( [ 'public' => true ] ) : [];

		return is_array( $types ) && in_array( $postType, array_values( $types ), true );
	}

	/**
	 * Resolve the title template, override aware.
	 *
	 * @param Context              $context  Request context.
	 * @param array<string, mixed> $meta     Sanitized payload.
	 * @return string The result.
	 */
	private function effectiveTitle( Context $context, array $meta ): string {
		$override = isset( $meta['title'] ) ? trim( (string) $meta['title'] ) : '';

		if ( '' !== $override ) {
			// Treat the stored override as literal unless it carries a token,
			// matching the frontend title resolution path.
			if ( 1 === preg_match( '/%%[a-z_]+%%/', $override ) ) {
				$resolved = $this->replacer->replace( $context, $override, 'meta_title' );

				if ( '' !== trim( $resolved ) ) {
					return $resolved;
				}
			} else {
				return $override;
			}
		}

		$template = (string) $this->store->get( 'title_template', '%%title%% %%sep%% %%sitename%%' );
		$resolved = $this->resolveTemplate( $context, $template, 'title_template' );

		if ( '' !== trim( $resolved ) ) {
			return $resolved;
		}

		return $context->title();
	}

	/**
	 * Resolve the description, override then template then excerpt.
	 *
	 * @param Context              $context Request context.
	 * @param array<string, mixed> $meta    Sanitized payload.
	 * @return string The result.
	 */
	private function effectiveDescription( Context $context, array $meta ): string {
		$override = isset( $meta['description'] ) ? trim( (string) $meta['description'] ) : '';

		if ( '' !== $override ) {
			return $override;
		}

		$template = (string) $this->store->get( 'description_template', '' );

		if ( '' !== trim( $template ) ) {
			$resolved = $this->resolveTemplate( $context, $template, 'description' );

			if ( '' !== trim( $resolved ) ) {
				return trim( $resolved );
			}
		}

		if ( $context->isSingular() ) {
			$excerpt = $context->excerpt();

			if ( '' !== $excerpt ) {
				return $excerpt;
			}
		}

		return '';
	}

	/**
	 * Replace tokens in a template through TagsReplacer.
	 *
	 * @param Context|null $context  Request context, null renders the raw template.
	 * @param string       $template Template body.
	 * @param string       $field    Field key for memoization.
	 * @return string The result.
	 */
	private function resolveTemplate( ?Context $context, string $template, string $field ): string {
		if ( '' === trim( $template ) ) {
			return '';
		}

		if ( null === $context ) {
			return $template;
		}

		return $this->replacer->replace( $context, $template, $field );
	}

	/**
	 * Resolve every supported token through TagsReplacer.
	 *
	 * @param Context $context Request context.
	 * @return array<string, string> Token name (no percents) to value.
	 */
	private function resolvedTokens( Context $context ): array {
		$out = [];

		foreach ( array_keys( self::TOKEN_LABELS ) as $name ) {
			$out[ $name ] = $this->replacer->replace( $context, '%%' . $name . '%%', 'token_' . $name );
		}

		return $out;
	}

	/**
	 * Empty token map with every supported key present.
	 *
	 * @return array<string, string> The result.
	 */
	private function emptyTokens(): array {
		$out = [];

		foreach ( array_keys( self::TOKEN_LABELS ) as $name ) {
			$out[ $name ] = '';
		}

		return $out;
	}

	/**
	 * Token labels keyed by the token with percent signs.
	 *
	 * @return array<string, string> The result.
	 */
	private function tokenLabels(): array {
		$out = [];

		foreach ( self::TOKEN_LABELS as $name => $label ) {
			$out[ '%%' . $name . '%%' ] = $label;
		}

		return $out;
	}

	/**
	 * View rows for the token quick insert control.
	 *
	 * @param Context $context Request context.
	 * @return array<int, array{token: string, label: string, value: string}> The result.
	 */
	private function tokenRows( Context $context ): array {
		$rows = [];

		foreach ( self::TOKEN_LABELS as $name => $label ) {
			$rows[] = [
				'token' => '%%' . $name . '%%',
				'label' => $label,
				'value' => $this->replacer->replace( $context, '%%' . $name . '%%', 'token_' . $name ),
			];
		}

		return $rows;
	}

	/**
	 * Build a request context representing the edited post.
	 *
	 * @param int $postId Current post id.
	 * @return Context The result.
	 */
	private function contextFor( int $postId ): Context {
		if ( null !== $this->contextFactory ) {
			$factory = $this->contextFactory;

			return $factory( $postId );
		}

		return $this->buildContext( $postId );
	}

	/**
	 * Build the default context around a synthetic singular query.
	 *
	 * The admin edit screen has no frontend query, so the edited post is
	 * mapped onto a singular query. That keeps %%title%%, %%date%%,
	 * %%author%%, %%category%%, and the permalink resolving to the post
	 * the editor is open on, through the same Context the frontend uses.
	 *
	 * @param int $postId Current post id.
	 * @return Context The result.
	 */
	private function buildContext( int $postId ): Context {
		$query = null !== $this->queryFactory ? ( $this->queryFactory )() : new WP_Query();

		if ( ! $query instanceof WP_Query ) {
			$query = new WP_Query();
		}

		$query->queried_object_id = $postId;
		$query->is_singular       = true;

		// WP_Query::get_queried_object_id() rebuilds the id through
		// get_queried_object() and resets it to null when the queried object
		// is unset, so seeding the id alone is lost on the first read. Seed
		// the post object too so %%title%% and every other id backed token
		// resolve against the edited post.
		if ( function_exists( 'get_post' ) ) {
			$post = get_post( $postId );

			if ( is_object( $post ) ) {
				$query->post           = $post;
				$query->queried_object = $post;
			}
		}

		return new Context( $query, $this->store, $this->replacer );
	}

	/**
	 * Resolve the default Open Graph image for a post.
	 *
	 * Uses the featured image so the Social tab can show what will be
	 * inherited when no override is set.
	 *
	 * @param int $postId Current post id.
	 * @return string The result.
	 */
	private function defaultOgImage( int $postId ): string {
		if ( ! function_exists( 'get_post_thumbnail_id' ) || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$thumbId = (int) get_post_thumbnail_id( $postId );

		if ( $thumbId <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $thumbId, 'large' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Alt text of the featured image, when one is set.
	 *
	 * Mirrors AnalysisController::featuredAlt so the browser and the REST
	 * route see the same value for keyword_in_image_alt and image_alt_quality.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	private function featuredAlt( int $postId ): string {
		if ( $postId <= 0 || ! function_exists( 'get_post_thumbnail_id' ) || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$thumbnail = (int) get_post_thumbnail_id( $postId );

		if ( $thumbnail <= 0 ) {
			return '';
		}

		return (string) get_post_meta( $thumbnail, '_wp_attachment_image_alt', true );
	}

	/**
	 * Build the REST path for the edited post endpoint.
	 *
	 * @param int $postId Current post id.
	 * @return string The result.
	 */
	private function restPath( int $postId ): string {
		if ( ! function_exists( 'rest_url' ) ) {
			return '';
		}

		$restBase = 'posts';

		if ( $postId > 0 && function_exists( 'get_post_type' ) && function_exists( 'get_post_type_object' ) ) {
			$postType = get_post_type( $postId );

			if ( is_string( $postType ) && '' !== $postType ) {
				$object = get_post_type_object( $postType );

				if ( is_object( $object ) ) {
					if ( is_string( $object->rest_base ) && '' !== $object->rest_base ) {
						$restBase = $object->rest_base;
					} elseif ( '' !== $object->name ) {
						$restBase = $object->name . 's';
					}
				}
			}
		}

		return (string) rest_url( 'wp/v2/' . $restBase . '/' . $postId );
	}

	/**
	 * UI strings the editor scripts consume.
	 *
	 * @return array<string, string> The result.
	 */
	private function strings(): array {
		return [
			'customOverride' => __( 'Custom override active', 'rankkernel' ),
			'inherited'      => __( 'Inherited from the template', 'rankkernel' ),
			'resetLabel'     => __( 'Reset to template', 'rankkernel' ),
			'templateLabel'  => __( 'Template', 'rankkernel' ),
			'effectiveLabel' => __( 'Effective value', 'rankkernel' ),
			'tokenInsert'    => __( 'Insert token', 'rankkernel' ),
			'selectImage'    => __( 'Select image', 'rankkernel' ),
			'removeImage'    => __( 'Remove image', 'rankkernel' ),
			'previewDesktop' => __( 'Desktop', 'rankkernel' ),
			'previewMobile'  => __( 'Mobile', 'rankkernel' ),
			'resetConfirm'   => __( 'This clears the override and restores the template value.', 'rankkernel' ),
		];
	}

	/**
	 * Whether the metabox fields posted with this request.
	 *
	 * @return bool The result.
	 */
	private function hasPostedFields(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence-only guard that runs before nonce verification, reads no posted value.
		if ( isset( $_POST[ self::FIELDS_FIELD ] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence-only guard that runs before nonce verification, reads no posted value.
		return isset( $_POST['rankkernel_meta_title'] ) || isset( $_POST['rankkernel_meta_description'] );
	}

	/**
	 * Merge the submitted fields over the existing payload.
	 *
	 * Unrelated subtrees not present in this request survive because the
	 * merge starts from the stored payload and only overwrites submitted
	 * keys.
	 *
	 * @param array<string, mixed> $existing Stored payload.
	 * @return array<string, mixed> Sanitized payload.
	 */
	private function mergePosted( array $existing ): array {
		$merged = $existing;

		$textFields = [ 'title', 'description' ];
		foreach ( $textFields as $name ) {
			$key = 'rankkernel_meta_' . $name;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave before this runs.
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handleSave before this runs, unslashed here, sanitized by MetaPayload::sanitize below.
			$merged[ $name ] = (string) wp_unslash( $_POST[ $key ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave before this runs.
		if ( isset( $_POST['rankkernel_meta_focus_keywords'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handleSave before this runs, unslashed here, split and sanitized by MetaPayload::sanitize below.
			$postedKeywords = (string) wp_unslash( $_POST['rankkernel_meta_focus_keywords'] );

			$keywords = [];
			foreach ( explode( ',', $postedKeywords ) as $keyword ) {
				$keyword = trim( $keyword );

				if ( '' !== $keyword ) {
					$keywords[] = $keyword;
				}
			}

			$merged['focus_keywords'] = $keywords;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave before this runs.
		if ( isset( $_POST['rankkernel_meta_canonical'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handleSave before this runs, unslashed here, sanitized by MetaPayload::sanitize below.
			$merged['canonical'] = (string) wp_unslash( $_POST['rankkernel_meta_canonical'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave before this runs, mapped and sanitized in the helpers below.
		$postedRobots = $_POST['rankkernel_meta_robots'] ?? null;
		if ( is_array( $postedRobots ) ) {
			$merged['robots'] = $this->postedRobots( $postedRobots );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave before this runs, mapped and sanitized in the helper below.
		$postedOg = $_POST['rankkernel_meta_og'] ?? null;
		if ( is_array( $postedOg ) ) {
			$existingOg   = ( isset( $existing['og'] ) && is_array( $existing['og'] ) ) ? $existing['og'] : [];
			$merged['og'] = array_merge( $existingOg, $this->postedOg( $postedOg ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave before this runs, mapped and sanitized in the helper below.
		$postedTwitter = $_POST['rankkernel_meta_twitter'] ?? null;
		if ( is_array( $postedTwitter ) ) {
			$existingTwitter   = ( isset( $existing['twitter'] ) && is_array( $existing['twitter'] ) ) ? $existing['twitter'] : [];
			$merged['twitter'] = array_merge( $existingTwitter, $this->postedTwitter( $postedTwitter ) );
		}

		// Per field reset runs last so it wins over whatever the input posted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave before this runs, scalar membership checked below.
		$reset = $_POST['rankkernel_meta_reset'] ?? null;
		if ( is_array( $reset ) ) {
			foreach ( $textFields as $name ) {
				if ( ! empty( $reset[ $name ] ) ) {
					$merged[ $name ] = '';
				}
			}
		}

		return MetaPayload::sanitize( $merged );
	}

	/**
	 * Normalize the posted robots directives.
	 *
	 * The stored index flag is the inverse of the noindex checkbox, matching
	 * the payload contract.
	 *
	 * @param array<int|string, mixed> $raw Posted robots map.
	 * @return array<string, mixed> The result.
	 */
	private function postedRobots( array $raw ): array {
		return [
			'index'             => empty( $raw['noindex'] ),
			'follow'            => empty( $raw['nofollow'] ),
			'noarchive'         => ! empty( $raw['noarchive'] ),
			'noimageindex'      => ! empty( $raw['noimageindex'] ),
			'nosnippet'         => ! empty( $raw['nosnippet'] ),
			'max_snippet'       => $this->intOrNull( $raw['max_snippet'] ?? null ),
			'max_image_preview' => $this->previewOrNull( $raw['max_image_preview'] ?? null ),
			'max_video_preview' => $this->intOrNull( $raw['max_video_preview'] ?? null ),
		];
	}

	/**
	 * Normalize the posted Open Graph subtree.
	 *
	 * @param array<int|string, mixed> $raw Posted og map.
	 * @return array<string, mixed> The result.
	 */
	private function postedOg( array $raw ): array {
		return [
			'title'       => $this->scalarString( $raw['title'] ?? null ),
			'description' => $this->scalarString( $raw['description'] ?? null ),
			'image'       => $this->scalarUrl( $raw['image'] ?? null ),
			'image_id'    => $this->scalarInt( $raw['image_id'] ?? null ),
			'type'        => $this->scalarString( $raw['type'] ?? null ),
		];
	}

	/**
	 * Normalize the posted Twitter subtree.
	 *
	 * @param array<int|string, mixed> $raw Posted twitter map.
	 * @return array<string, mixed> The result.
	 */
	private function postedTwitter( array $raw ): array {
		return [
			'card'        => $this->cardValue( $raw['card'] ?? null ),
			'title'       => $this->scalarString( $raw['title'] ?? null ),
			'description' => $this->scalarString( $raw['description'] ?? null ),
			'image'       => $this->scalarUrl( $raw['image'] ?? null ),
			'image_id'    => $this->scalarInt( $raw['image_id'] ?? null ),
		];
	}

	/**
	 * Cast a posted scalar to a plain string.
	 *
	 * @param mixed $value Raw value.
	 * @return string The result.
	 */
	private function scalarString( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return (string) wp_unslash( $value );
	}

	/**
	 * Cast a posted scalar to a URL string.
	 *
	 * @param mixed $value Raw value.
	 * @return string The result.
	 */
	private function scalarUrl( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$url = (string) wp_unslash( $value );

		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : trim( $url );
	}

	/**
	 * Cast a posted scalar to a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int The result.
	 */
	private function scalarInt( mixed $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		return max( 0, (int) $value );
	}

	/**
	 * Cast a posted scalar to a nullable integer budget.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null The result.
	 */
	private function intOrNull( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Validate a posted max-image-preview value.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null The result.
	 */
	private function previewOrNull( mixed $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$clean = (string) $value;

		return in_array( $clean, self::PREVIEW_VALUES, true ) ? $clean : null;
	}

	/**
	 * Validate a posted Twitter card value.
	 *
	 * @param mixed $value Raw value.
	 * @return string The result.
	 */
	private function cardValue( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return 'summary_large_image';
		}

		$clean = (string) $value;

		return in_array( $clean, self::CARD_VALUES, true ) ? $clean : 'summary_large_image';
	}

	/**
	 * Read a payload subtree, defensively.
	 *
	 * @param array<string, mixed> $meta Sanitized payload.
	 * @param string               $key  Subtree key.
	 * @return array<string, mixed> The result.
	 */
	private function subtree( array $meta, string $key ): array {
		$value = $meta[ $key ] ?? [];

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Read the schema subtree, legacy lists become empty.
	 *
	 * Mirrors SchemaMetabox so the Schema tab renders the same state the
	 * schema save path persists.
	 *
	 * @param array<string, mixed> $meta Sanitized payload.
	 * @return array<string, mixed> The result.
	 */
	private function readSchemaSubtree( array $meta ): array {
		$schema = $meta['schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			return [];
		}

		if ( [] !== $schema && array_is_list( $schema ) ) {
			return [];
		}

		return $schema;
	}

	/**
	 * Resolved default schema type for a post type.
	 *
	 * Setting first, post type mapping fallback, mirroring SchemaMetabox.
	 *
	 * @param string $postType Post type slug.
	 * @return string The result.
	 */
	private function resolvedSchemaDefault( string $postType ): string {
		if ( '' !== $postType ) {
			$setting = trim( (string) $this->store->get( 'schema_default_' . $postType, '' ) );

			if ( in_array( $setting, SchemaTypes::SUPPORTED, true ) ) {
				return $setting;
			}
		}

		return SchemaTypes::defaultForPostType( $postType );
	}

	/**
	 * Manual field override rows for the Schema tab.
	 *
	 * Names match the schema save path exactly so persistence is
	 * untouched; ids are prefixed for this box so the standalone schema
	 * box never shares an id with these rows.
	 *
	 * @param array<string, mixed> $schema   Stored schema subtree.
	 * @param string               $selected Selected type, empty for automatic.
	 * @return array<int, array{id: string, name: string, label: string, value: string, types: string, hidden: bool}> The result.
	 */
	private function schemaFieldRows( array $schema, string $selected ): array {
		$fields = ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) ? $schema['fields'] : [];
		$rows   = [];

		foreach ( self::SCHEMA_FIELD_LABELS as $fieldKey => $fieldLabel ) {
			$rows[] = [
				'id'     => 'rankkernel-meta-schema-field-' . $fieldKey,
				'name'   => 'rankkernel_schema_fields[' . $fieldKey . ']',
				'label'  => $fieldLabel,
				'value'  => isset( $fields[ $fieldKey ] ) && is_scalar( $fields[ $fieldKey ] ) ? (string) $fields[ $fieldKey ] : '',
				'types'  => implode( ',', self::SCHEMA_FIELD_TYPES[ $fieldKey ] ),
				'hidden' => ! $this->schemaFieldVisible( $fieldKey, $selected ),
			];
		}

		return $rows;
	}

	/**
	 * Whether a manual schema field row shows for the selected type.
	 *
	 * @param string $fieldKey Key.
	 * @param string $selected Selected type, empty for automatic.
	 * @return bool The result.
	 */
	private function schemaFieldVisible( string $fieldKey, string $selected ): bool {
		$allowed = self::SCHEMA_FIELD_TYPES[ $fieldKey ];

		if ( in_array( '*', $allowed, true ) ) {
			return true;
		}

		return '' !== $selected && in_array( $selected, $allowed, true );
	}

	/**
	 * Missing required schema fields for the selected type.
	 *
	 * Mirrors SchemaMetabox::validationMessages through the central
	 * SchemaTypes registry, so the tab warning and the piece gating
	 * logic can never drift apart.
	 *
	 * @param string               $selected Selected type, empty for automatic.
	 * @param array<string, mixed> $schema   Stored schema subtree.
	 * @return string[] The result.
	 */
	private function schemaValidationMessages( string $selected, array $schema ): array {
		if ( '' === $selected ) {
			return [];
		}

		if ( 'FAQPage' === $selected ) {
			return 0 === $this->countSchemaQuestions( $schema )
				? [ 'At least one question is required for FAQPage.' ]
				: [];
		}

		if ( 'HowTo' === $selected ) {
			return 0 === $this->countSchemaSteps( $schema )
				? [ 'At least one step is required for HowTo.' ]
				: [];
		}

		$fields = ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) ? $schema['fields'] : [];

		$messages = [];

		foreach ( SchemaTypes::requiredFields( $selected ) as $requiredField ) {
			$value = isset( $fields[ $requiredField ] ) ? trim( (string) $fields[ $requiredField ] ) : '';

			if ( '' === $value ) {
				$messages[] = SchemaTypes::requiredMessage( $selected, $requiredField );
			}
		}

		return $messages;
	}

	/**
	 * Count valid FAQ questions, rows with a non empty question.
	 *
	 * @param array<string, mixed> $schema Stored schema subtree.
	 * @return int The result.
	 */
	private function countSchemaQuestions( array $schema ): int {
		$faq  = ( isset( $schema['faq'] ) && is_array( $schema['faq'] ) ) ? $schema['faq'] : [];
		$rows = ( isset( $faq['questions'] ) && is_array( $faq['questions'] ) ) ? $faq['questions'] : [];

		$count = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( '' !== trim( (string) ( $row['question'] ?? '' ) ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count valid HowTo steps, rows with a title or text.
	 *
	 * @param array<string, mixed> $schema Stored schema subtree.
	 * @return int The result.
	 */
	private function countSchemaSteps( array $schema ): int {
		$howto = ( isset( $schema['howto'] ) && is_array( $schema['howto'] ) ) ? $schema['howto'] : [];
		$rows  = ( isset( $howto['steps'] ) && is_array( $howto['steps'] ) ) ? $howto['steps'] : [];

		$count = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rowTitle = trim( (string) ( $row['title'] ?? '' ) );
			$rowText  = trim( (string) ( $row['text'] ?? '' ) );

			if ( '' !== $rowTitle || '' !== $rowText ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Read the stored payload, defensive on any shape.
	 *
	 * @param int $postId Current post id.
	 * @return array<string, mixed> The result.
	 */
	private function readPayload( int $postId ): array {
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $postId, self::META_KEY, true ) : [];

		if ( is_array( $raw ) ) {
			return $raw;
		}

		return MetaPayload::decodeMetaValue( $raw );
	}

	/**
	 * Read the stored payload and sanitize it, failing safe on malformed data.
	 *
	 * @param int $postId Current post id.
	 * @return array<string, mixed> The result.
	 */
	private function safePayload( int $postId ): array {
		try {
			return MetaPayload::sanitize( $this->readPayload( $postId ) );
		} catch ( \Throwable $e ) {
			unset( $e );

			return MetaPayload::defaults();
		}
	}

	/**
	 * Current edited post type, screen first.
	 *
	 * @return string The result.
	 */
	private function currentPostType(): string {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( is_object( $screen ) && '' !== $screen->post_type ) {
				return $screen->post_type;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only screen context, unslashed and cast to a non-negative integer or sanitized key below.
		$rawPost = isset( $_GET['post'] ) ? wp_unslash( $_GET['post'] ) : null;

		if ( is_scalar( $rawPost ) && (int) $rawPost > 0 && function_exists( 'get_post_type' ) ) {
			$postType = get_post_type( (int) $rawPost );

			if ( is_string( $postType ) && '' !== $postType ) {
				return $postType;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only screen context, unslashed and sanitized with sanitize_key below.
		$rawType = isset( $_GET['post_type'] ) ? wp_unslash( $_GET['post_type'] ) : null;

		if ( is_scalar( $rawType ) && '' !== (string) $rawType ) {
			return sanitize_key( (string) $rawType );
		}

		return 'post';
	}

	/**
	 * Current edited post id.
	 *
	 * @return int The result.
	 */
	private function currentPostId(): int {
		if ( function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();

			if ( is_int( $id ) && $id > 0 ) {
				return $id;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only screen context, unslashed and cast to a non-negative integer below.
		$rawPost = isset( $_GET['post'] ) ? wp_unslash( $_GET['post'] ) : null;

		if ( is_scalar( $rawPost ) ) {
			return max( 0, (int) $rawPost );
		}

		return 0;
	}
}
