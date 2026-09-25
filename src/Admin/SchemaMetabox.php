<?php
/**
 * Schema metabox, builder UI with import and export.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;

/**
 * Classic editor metabox for the per post schema payload.
 *
 * Renders the type selector, manual field overrides, FAQ and HowTo
 * builders, a raw custom JSON box, validation warnings, validator
 * links, and import/export controls. Saves merge the schema subtree
 * only, every other payload key stays byte identical. Revisions are
 * skipped entirely, the parent keeps the canonical copy.
 *
 * Owns capability checks, nonce verification, request handling, saving,
 * validation, and view state preparation. The HTML lives in
 * src/Admin/Views/schema-metabox.php.
 */
final class SchemaMetabox {
	/**
	 * Post meta key holding the payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

	/**
	 * Nonce action and field for the save handler.
	 */
	private const NONCE_ACTION = 'rankkernel_schema_save';
	private const NONCE_FIELD  = 'rankkernel_schema_nonce';

	/**
	 * Export handler action.
	 */
	private const EXPORT_ACTION = 'rankkernel_schema_export';

	/**
	 * Manual field keys, short and generic.
	 *
	 * Every key a schema piece reads lives here, so the builder UI
	 * can set each one. Keys stay plain strings, sanitized on save.
	 *
	 * @var string[]
	 */
	private const FIELD_KEYS = [
		'headline',
		'description',
		'author',
		'price',
		'priceCurrency',
		'sku',
		'availability',
		'ratingValue',
		'reviewCount',
		'bestRating',
		'worstRating',
		'isbn',
		'startDate',
		'endDate',
		'locationName',
		'streetAddress',
		'addressLocality',
		'addressRegion',
		'postalCode',
		'addressCountry',
		'performer',
		'eventStatus',
		'ingredients',
		'instructions',
		'prepTime',
		'cookTime',
		'totalTime',
		'yield',
		'areaServed',
		'thumbnailUrl',
		'uploadDate',
		'duration',
		'contentUrl',
		'appCategory',
		'operatingSystem',
		'artist',
		'album',
		'dateCreated',
		'director',
		'company',
		'jobLocation',
		'salary',
		'datePosted',
		'validThrough',
		'claimReviewed',
		'datePublished',
		'license',
		'distributionUrl',
		'distributionFormat',
		'seriesName',
		'question',
		'answer',
		'answerAuthor',
		'itemName',
		'reviewBody',
		'telephone',
		'priceRange',
		'openingHours',
		'caption',
		'width',
		'height',
		'speakable',
		'about',
		'mentions',
	];

	/**
	 * Fields holding URLs, cleaned with esc_url_raw on save.
	 *
	 * @var string[]
	 */
	private const URL_KEYS = [
		'thumbnailUrl',
		'contentUrl',
		'license',
		'distributionUrl',
	];

	/**
	 * Labels for the manual field keys.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_LABELS = [
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
	 * Status from the last save in this request, for the redirect filter.
	 *
	 * @var string|null
	 */
	private ?string $saveStatus = null;

	/**
	 * Upload probe, true for genuine HTTP uploads.
	 *
	 * @var callable(string): bool
	 */
	private $isUploadedFile;

	/**
	 * Settings store for resolving per post type defaults.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $store;

	/**
	 * Constructor.
	 *
	 * @param callable(string): bool|null $isUploadedFile Upload probe override, test double seam.
	 * @param SettingsStore|null          $store          Settings store override, test double seam.
	 */
	public function __construct( ?callable $isUploadedFile = null, ?SettingsStore $store = null ) {
		$this->isUploadedFile = $isUploadedFile ?? 'is_uploaded_file';
		$this->store          = $store ?? new SettingsStore();
	}

	/**
	 * Register hooks, admin only by wiring.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'addBoxes' ], 10, 2 );
		add_action( 'save_post', [ $this, 'handleSave' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'admin_post_' . self::EXPORT_ACTION, [ $this, 'handleExport' ] );
		add_filter( 'redirect_post_location', [ $this, 'filterRedirect' ] );
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
		// The block editor renders the schema controls through the RankKernel
		// SEO sidebar, so registering the box there would duplicate them.
		if ( ScreenGuard::isBlockEditorScreen() ) {
			return;
		}

		if ( 'attachment' === $postType ) {
			return;
		}

		$types = function_exists( 'get_post_types' ) ? get_post_types( [ 'public' => true ] ) : [];

		if ( ! is_array( $types ) || ! in_array( $postType, array_values( $types ), true ) ) {
			return;
		}

		add_meta_box(
			'rankkernel-schema',
			__( 'RankKernel Schema', 'rankkernel' ),
			[ $this, 'renderBox' ],
			$postType,
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue the builder script on post edit screens only.
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

		$src = function_exists( 'plugins_url' )
			? plugins_url( 'assets/js/schema-metabox.js', (string) RANKKERNEL_FILE )
			: '';

		$version = \RankKernel\Plugin::version();

		wp_register_script( 'rankkernel-schema-metabox', $src, [], $version, true );
		wp_enqueue_script( 'rankkernel-schema-metabox' );
	}

	/**
	 * Render the box for a post.
	 *
	 * @param mixed $post Current post object.
	 */
	public function renderBox( mixed $post ): void {
		$postId = ( is_object( $post ) && isset( $post->ID ) ) ? (int) $post->ID : 0;

		if ( $postId <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only display flag, non-scalar input is discarded and scalars are sanitized on the following statement.
		$rawMsg        = isset( $_GET['rankkernel_schema_msg'] ) ? wp_unslash( $_GET['rankkernel_schema_msg'] ) : '';
		$noticeMessage = is_scalar( $rawMsg ) ? sanitize_key( (string) $rawMsg ) : '';

		$schema   = $this->readSchema( $this->readPayload( $postId ) );
		$rawType  = $schema['type'] ?? '';
		$selected = ( is_string( $rawType ) && in_array( $rawType, SchemaTypes::SUPPORTED, true ) )
			? $rawType
			: '';

		$disabled = ! empty( $schema['disabled'] );

		$postType = function_exists( 'get_post_type' ) ? (string) get_post_type( $postId ) : '';
		$resolved = $this->resolvedDefaultType( $postType );

		$fields = ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) ? $schema['fields'] : [];

		$autoLabel = '' !== $resolved
			/* translators: %s: schema type name, e.g. Blog Posting. */
			? sprintf( __( 'Automatic (%s)', 'rankkernel' ), SchemaTypes::label( $resolved ) )
			: __( 'Automatic', 'rankkernel' );

		$typeOptions = [];

		foreach ( SchemaTypes::SUPPORTED as $schemaType ) {
			$typeOptions[] = [
				'value' => $schemaType,
				'label' => SchemaTypes::label( $schemaType ),
			];
		}

		$fieldRows = [];

		foreach ( self::FIELD_KEYS as $fieldKey ) {
			$fieldRows[] = [
				'id'    => 'rankkernel-schema-field-' . $fieldKey,
				'name'  => 'rankkernel_schema_fields[' . $fieldKey . ']',
				'label' => self::FIELD_LABELS[ $fieldKey ],
				'value' => isset( $fields[ $fieldKey ] ) && is_scalar( $fields[ $fieldKey ] ) ? (string) $fields[ $fieldKey ] : '',
				'types' => implode( ',', self::FIELD_TYPES[ $fieldKey ] ),
				'hide'  => $this->fieldVisible( $fieldKey, $selected ) ? '' : ' style="display:none;"',
			];
		}

		$custom = ( isset( $schema['custom'] ) && is_array( $schema['custom'] ) ) ? $schema['custom'] : [];

		if ( function_exists( 'wp_json_encode' ) ) {
			$customJson = (string) wp_json_encode( $custom, JSON_PRETTY_PRINT );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback keeps unit tests free of WP, used only when wp_json_encode is missing.
			$customJson = (string) json_encode( $custom, JSON_PRETTY_PRINT );
		}

		$validationMessages = $this->validationMessages( $selected, $schema );
		$validationLabel    = '' === $selected ? 'Automatic' : $selected;

		$permalink = function_exists( 'get_permalink' ) ? (string) get_permalink( $postId ) : '';

		$richResultsUrl = 'https://search.google.com/test/rich-results?url=' . rawurlencode( $permalink );
		$validatorUrl   = 'https://validator.schema.org/';

		$exportUrl = function_exists( 'wp_nonce_url' )
			? wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION . '&post=' . $postId ),
				self::EXPORT_ACTION . '_' . $postId
			)
			: '#';

		require __DIR__ . '/Views/schema-metabox.php';
	}

	/**
	 * Resolved default type for a post type: setting first, mapping fallback.
	 *
	 * @param string $postType Post Type.
	 * @return string The result.
	 */
	private function resolvedDefaultType( string $postType ): string {
		if ( '' !== $postType ) {
			$setting = trim( (string) $this->store->get( 'schema_default_' . $postType, '' ) );

			if ( in_array( $setting, SchemaTypes::SUPPORTED, true ) ) {
				return $setting;
			}
		}

		return SchemaTypes::defaultForPostType( $postType );
	}

	/**
	 * Manual field visibility per type: only fields that matter for the
	 * chosen type show, the rest stay hidden until relevant.
	 *
	 * @var array<string, string[]>
	 */
	private const FIELD_TYPES = [
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
	 * Whether a manual field row shows for the selected type.
	 *
	 * @param string $key      Key.
	 * @param string $selected Selected.
	 * @return bool The result.
	 */
	private function fieldVisible( string $key, string $selected ): bool {
		$types = self::FIELD_TYPES[ $key ];

		if ( in_array( '*', $types, true ) ) {
			return true;
		}

		return '' !== $selected && in_array( $selected, $types, true );
	}

	/**
	 * Missing required fields for the selected type.
	 *
	 * Field lists come from the central SchemaTypes registry, so the
	 * admin warning and the piece gating logic can never drift apart.
	 * FAQPage and HowTo validate through their row counters, page and
	 * list types need no manual fields at all.
	 *
	 * @param string               $selected Selected type or empty for automatic.
	 * @param array<string, mixed> $schema   Stored schema subtree.
	 * @return string[] The result.
	 */
	public function validationMessages( string $selected, array $schema ): array {
		if ( '' === $selected ) {
			return [];
		}

		if ( 'FAQPage' === $selected ) {
			return 0 === $this->countQuestions( $schema )
				? [ 'At least one question is required for FAQPage.' ]
				: [];
		}

		if ( 'HowTo' === $selected ) {
			return 0 === $this->countSteps( $schema )
				? [ 'At least one step is required for HowTo.' ]
				: [];
		}

		$fields = ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) ? $schema['fields'] : [];

		$messages = [];

		foreach ( SchemaTypes::requiredFields( $selected ) as $field ) {
			$value = isset( $fields[ $field ] ) ? trim( (string) $fields[ $field ] ) : '';

			if ( '' === $value ) {
				$messages[] = SchemaTypes::requiredMessage( $selected, $field );
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
	private function countQuestions( array $schema ): int {
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
	private function countSteps( array $schema ): int {
		$howto = ( isset( $schema['howto'] ) && is_array( $schema['howto'] ) ) ? $schema['howto'] : [];
		$rows  = ( isset( $howto['steps'] ) && is_array( $howto['steps'] ) ) ? $howto['steps'] : [];

		$count = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = trim( (string) ( $row['title'] ?? '' ) );
			$text  = trim( (string) ( $row['text'] ?? '' ) );

			if ( '' !== $title || '' !== $text ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Save handler on save_post.
	 *
	 * Skips autosaves and revisions, then merges the schema subtree
	 * over the stored payload. Import files take over the whole save.
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

		$import = $this->readImportFile();

		if ( $import['found'] ) {
			if ( ! $import['valid'] ) {
				$this->saveStatus = 'invalid-import';

				return;
			}

			$existing           = $this->readPayload( $postId );
			$existing['schema'] = $import['schema'];
			$this->saveStatus   = 'saved';

			update_post_meta( $postId, self::META_KEY, $existing );

			return;
		}

		$customRaw = isset( $_POST['rankkernel_schema_custom'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here, sanitized or validated on the following statements.
			? (string) wp_unslash( $_POST['rankkernel_schema_custom'] )
			: '';

		$custom = [];
		$status = 'saved';

		if ( '' !== trim( $customRaw ) ) {
			$decoded = json_decode( $customRaw, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				$status = 'invalid-json';
			} else {
				$custom = $decoded;
			}
		}

		$rawSchema = [
			'type'     => $this->postedType(),
			'disabled' => isset( $_POST['rankkernel_schema_disabled'] ),
			'fields'   => $this->postedFields(),
			'faq'      => [ 'questions' => $this->postedQuestions() ],
			'howto'    => $this->postedHowto(),
			'custom'   => $custom,
		];

		$sanitized = MetaPayload::sanitize( [ 'schema' => $rawSchema ] );
		$newSchema = ( isset( $sanitized['schema'] ) && is_array( $sanitized['schema'] ) )
			? $sanitized['schema']
			: [];

		$existing       = $this->readPayload( $postId );
		$existingSchema = ( isset( $existing['schema'] ) && is_array( $existing['schema'] ) )
			? $existing['schema']
			: [];

		// The FAQ and HowTo builders now live in blocks. When their POST
		// keys are absent, the metabox did not render them, so previously
		// stored rows carry over instead of being wiped.
		if ( ! isset( $_POST['rankkernel_schema_faq'] ) && isset( $existingSchema['faq'] ) ) {
			$newSchema['faq'] = $existingSchema['faq'];
		}

		$howtoKeys = [
			'rankkernel_schema_howto_name',
			'rankkernel_schema_howto_steps',
			'rankkernel_schema_howto_totaltime',
			'rankkernel_schema_howto_cost',
		];

		$howtoPosted = false;

		foreach ( $howtoKeys as $howtoKey ) {
			if ( isset( $_POST[ $howtoKey ] ) ) {
				$howtoPosted = true;
				break;
			}
		}

		if ( ! $howtoPosted && isset( $existingSchema['howto'] ) ) {
			$newSchema['howto'] = $existingSchema['howto'];
		}

		// An invalid JSON submission is a typo, not a delete. Keep the
		// previously stored custom schema untouched instead of wiping it.
		if ( 'invalid-json' === $status && isset( $existingSchema['custom'] ) && is_array( $existingSchema['custom'] ) ) {
			$newSchema['custom'] = $existingSchema['custom'];
		}

		$existing['schema'] = $newSchema;
		$this->saveStatus   = $status;

		update_post_meta( $postId, self::META_KEY, $existing );
	}

	/**
	 * Read the posted type, validated against the central list.
	 *
	 * @return string The result.
	 */
	private function postedType(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
		$raw = isset( $_POST['rankkernel_schema_type'] )
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handleSave, unslashed here, validated against an allow list below.
			? trim( (string) wp_unslash( $_POST['rankkernel_schema_type'] ) )
			: '';

		return in_array( $raw, SchemaTypes::SUPPORTED, true ) ? $raw : '';
	}

	/**
	 * Read the posted manual fields, allowlisted keys only.
	 *
	 * @return array<string, string>
	 */
	private function postedFields(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave, array of rows, each scalar unslashed and sanitized in the loop below.
		$raw = $_POST['rankkernel_schema_fields'] ?? [];

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$fields = [];

		foreach ( self::FIELD_KEYS as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) ) {
				continue;
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			$posted = (string) wp_unslash( $raw[ $key ] );

			if ( in_array( $key, self::URL_KEYS, true ) ) {
				$value = function_exists( 'esc_url_raw' ) ? esc_url_raw( $posted ) : trim( $posted );
			} else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
				$value = sanitize_text_field( $posted );
			}

			if ( '' !== $value ) {
				$fields[ $key ] = $value;
			}
		}

		return $fields;
	}

	/**
	 * Read the posted FAQ rows.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function postedQuestions(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave, array of rows, each scalar unslashed and sanitized in the loop below.
		$raw = $_POST['rankkernel_schema_faq'] ?? [];

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$rows = [];

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			$question = isset( $row['question'] )
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
				? sanitize_text_field( (string) wp_unslash( $row['question'] ) )
				: '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			$answer = isset( $row['answer'] )
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
				? sanitize_text_field( (string) wp_unslash( $row['answer'] ) )
				: '';

			$rows[] = [
				'question' => $question,
				'answer'   => $answer,
			];
		}

		return $rows;
	}

	/**
	 * Read the posted HowTo block.
	 *
	 * @return array{name: string, steps: array<int, mixed>, totalTime: string, cost: string}
	 */
	private function postedHowto(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
		$rawName = isset( $_POST['rankkernel_schema_howto_name'] )
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			? sanitize_text_field( (string) wp_unslash( $_POST['rankkernel_schema_howto_name'] ) )
			: '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
		$rawTotal = isset( $_POST['rankkernel_schema_howto_totaltime'] )
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			? sanitize_text_field( (string) wp_unslash( $_POST['rankkernel_schema_howto_totaltime'] ) )
			: '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
		$rawCost = isset( $_POST['rankkernel_schema_howto_cost'] )
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
			? sanitize_text_field( (string) wp_unslash( $_POST['rankkernel_schema_howto_cost'] ) )
			: '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- verified in handleSave, array of rows, each scalar unslashed and sanitized in the loop below.
		$rawSteps = $_POST['rankkernel_schema_howto_steps'] ?? [];
		$steps    = [];

		if ( is_array( $rawSteps ) ) {
			foreach ( $rawSteps as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$steps[] = [
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
					'title' => isset( $row['title'] )
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
						? sanitize_text_field( (string) wp_unslash( $row['title'] ) )
						: '',
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
					'text'  => isset( $row['text'] )
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
						? sanitize_text_field( (string) wp_unslash( $row['text'] ) )
						: '',
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
					'image' => isset( $row['image'] )
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
						? esc_url_raw( (string) wp_unslash( $row['image'] ) )
						: '',
				];
			}
		}

		return [
			'name'      => $rawName,
			'steps'     => $steps,
			'totalTime' => $rawTotal,
			'cost'      => $rawCost,
		];
	}

	/**
	 * Read an uploaded import file, never moved, only parsed.
	 *
	 * @return array{found: bool, valid: bool, schema: array<string, mixed>}
	 */
	private function readImportFile(): array {
		$empty = [
			'found'  => false,
			'valid'  => false,
			'schema' => [],
		];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave before readImportFile runs.
		if ( ! isset( $_FILES['rankkernel_schema_import'] ) || ! is_array( $_FILES['rankkernel_schema_import'] ) ) {
			return $empty;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handleSave before readImportFile runs, upload metadata validated below, file contents never executed.
		$file  = $_FILES['rankkernel_schema_import'];
		$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return $empty;
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

		$tmp = $file['tmp_name'] ?? '';

		$probe = $this->isUploadedFile;

		if ( ! is_string( $tmp ) || '' === $tmp || ! $probe( $tmp ) ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

		$name = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';

		if ( '' === $name ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

		$mimes = [ 'json' => 'application/json' ];
		if ( function_exists( 'wp_check_filetype_and_ext' ) ) {
			$check = wp_check_filetype_and_ext( $tmp, $name, $mimes );
		} elseif ( function_exists( 'wp_check_filetype' ) ) {
			$check = wp_check_filetype( $name, $mimes );
		} else {
			$check = false;
		}

		if ( ! is_array( $check ) || empty( $check['ext'] ) ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads the verified local upload temp path, never a URL, after the upload probe and JSON type check.
		$contents = file_get_contents( $tmp );

		if ( false === $contents ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

		$decoded = json_decode( $contents, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return [
				'found'  => true,
				'valid'  => false,
				'schema' => [],
			];
		}

		$sanitized = MetaPayload::sanitize( [ 'schema' => $decoded ] );
		$schema    = ( isset( $sanitized['schema'] ) && is_array( $sanitized['schema'] ) )
			? $sanitized['schema']
			: [];

		return [
			'found'  => true,
			'valid'  => true,
			'schema' => $schema,
		];
	}

	/**
	 * Append the save status to the post redirect URL.
	 *
	 * @param string $location Redirect URL.
	 * @return string The result.
	 */
	public function filterRedirect( string $location ): string {
		if ( null === $this->saveStatus ) {
			return $location;
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( 'rankkernel_schema_msg', $this->saveStatus, $location );
		}

		$sep = str_contains( $location, '?' ) ? '&' : '?';

		return $location . $sep . 'rankkernel_schema_msg=' . $this->saveStatus;
	}

	/**
	 * Export handler, downloads the stored schema object as JSON.
	 */
	public function handleExport(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified below, unslashed here, cast to scalar on the following statement.
		$rawPostId = isset( $_GET['post'] ) ? wp_unslash( $_GET['post'] ) : 0;
		$postId    = max( 0, (int) ( is_scalar( $rawPostId ) ? $rawPostId : 0 ) );

		if ( $postId <= 0 ) {
			wp_die(
				esc_html__( 'Missing post.', 'rankkernel' ),
				'',
				[ 'response' => 400 ]
			);
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to edit this post.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( self::EXPORT_ACTION . '_' . $postId );
		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$schema = $this->readSchema( $this->readPayload( $postId ) );

		$slug = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_name', $postId ) : '';

		if ( '' === $slug ) {
			$slug = 'post-' . $postId;
		}

		if ( function_exists( 'sanitize_file_name' ) ) {
			$slug = (string) sanitize_file_name( $slug );
		}

		if ( '' === trim( $slug ) ) {
			$slug = 'post-' . $postId;
		}

		if ( function_exists( 'wp_json_encode' ) ) {
			$json = (string) wp_json_encode( $schema );
		} else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback keeps unit tests free of WP, used only when wp_json_encode is missing.
			$json = (string) json_encode( $schema );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $slug ) . '-schema.json"' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body served as application/json, encoded with wp_json_encode above.
		echo $json;

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Read the stored payload, defensive on any shape.
	 *
	 * @param int $postId Current post id.
	 * @return array<string, mixed>
	 */
	private function readPayload( int $postId ): array {
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $postId, self::META_KEY, true ) : [];

		if ( is_array( $raw ) ) {
			return $raw;
		}

		return MetaPayload::decodeMetaValue( $raw );
	}

	/**
	 * Read the schema subtree, legacy lists become empty.
	 *
	 * @param array<string, mixed> $payload Stored payload.
	 * @return array<string, mixed>
	 */
	private function readSchema( array $payload ): array {
		$schema = $payload['schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			return [];
		}

		if ( [] !== $schema && array_is_list( $schema ) ) {
			return [];
		}

		return $schema;
	}
}
