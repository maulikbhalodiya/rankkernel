<?php
/**
 * Schema metabox, builder UI with import and export.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

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
    private const NONCE_FIELD = 'rankkernel_schema_nonce';

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
        add_action('add_meta_boxes', [ $this, 'addBoxes' ], 10, 2);
        add_action('save_post', [ $this, 'handleSave' ], 10, 2);
        add_action('admin_enqueue_scripts', [ $this, 'enqueueAssets' ]);
        add_action('admin_post_' . self::EXPORT_ACTION, [ $this, 'handleExport' ]);
        add_filter('redirect_post_location', [ $this, 'filterRedirect' ]);
    }

    /**
     * Add the box to every public post type except attachment.
     *
     * Types are read at runtime so later registrations apply.
     *
     * @param string $postType Current post type.
     * @param mixed  $post     Current post object.
     */
    public function addBoxes( string $postType, mixed $post = null ): void {
        if ('attachment' === $postType) {
            return;
        }

        $types = function_exists('get_post_types') ? get_post_types([ 'public' => true ]) : [];

        if (! is_array($types) || ! in_array($postType, array_values($types), true)) {
            return;
        }

        add_meta_box(
            'rankkernel-schema',
            __('RankKernel Schema', 'rankkernel'),
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
        if (! in_array($hookSuffix, [ 'post.php', 'post-new.php' ], true)) {
            return;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();

            if (! is_object($screen) || ! is_string($screen->base) || 'post' !== $screen->base) {
                return;
            }
        }

        $src = function_exists('plugins_url')
            ? plugins_url('assets/js/schema-metabox.js', (string) RANKKERNEL_FILE)
            : '';

        $version = defined('RANKKERNEL_VERSION') ? (string) RANKKERNEL_VERSION : '0.1.0';

        wp_register_script('rankkernel-schema-metabox', $src, [], $version, true);
        wp_enqueue_script('rankkernel-schema-metabox');
    }

    /**
     * Render the box for a post.
     *
     * @param mixed $post Current post object.
     */
    public function renderBox( mixed $post ): void {
        $postId = ( is_object($post) && isset($post->ID) ) ? (int) $post->ID : 0;

        if ($postId <= 0) {
            return;
        }

        $this->renderNotices();

        $schema   = $this->readSchema($this->readPayload($postId));
        $rawType  = $schema['type'] ?? '';
        $selected = ( is_string($rawType) && in_array($rawType, SchemaTypes::SUPPORTED, true) )
            ? $rawType
            : '';

        $disabled = ! empty($schema['disabled']);

        $postType = function_exists('get_post_type') ? (string) get_post_type($postId) : '';
        $resolved = $this->resolvedDefaultType($postType);

        $fields = ( isset($schema['fields']) && is_array($schema['fields']) ) ? $schema['fields'] : [];

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $this->renderDisableRow($disabled);
        $this->renderTypeSelector($selected, $resolved, $postType);
        $this->renderFields($fields, $selected);
        echo '<details><summary>'
            . esc_html__('Advanced: custom JSON, import, export', 'rankkernel')
            . '</summary>';
        $this->renderCustom($schema);
        $this->renderValidation($selected, $schema);
        $this->renderLinks($postId);
        $this->renderImportExport($postId);
        echo '</details>';
    }

    /**
     * Resolved default type for a post type: setting first, mapping fallback.
     */
    private function resolvedDefaultType( string $postType ): string {
        if ('' !== $postType) {
            $setting = trim((string) $this->store->get('schema_default_' . $postType, ''));

            if (in_array($setting, SchemaTypes::SUPPORTED, true)) {
                return $setting;
            }
        }

        return SchemaTypes::defaultForPostType($postType);
    }

    /**
     * Render the per post disable row.
     */
    private function renderDisableRow( bool $disabled ): void {
        echo '<p><label>';
        echo '<input type="checkbox" name="rankkernel_schema_disabled" value="1" '
            . checked($disabled, true, false) . ' /> ';
        echo esc_html__('Disable schema output for this post', 'rankkernel');
        echo '</label><br />';
        echo '<span class="description">';
        echo esc_html__('No structured data prints on this post while checked.', 'rankkernel');
        echo '</span></p>';
    }

    /**
     * Render save and import notices from the redirect query arg.
     */
    private function renderNotices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.
        $msg = isset($_GET['rankkernel_schema_msg']) ? (string) $_GET['rankkernel_schema_msg'] : '';

        if ('saved' === $msg) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Schema saved.', 'rankkernel');
            echo '</p></div>';
        } elseif ('invalid-json' === $msg) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Custom JSON was invalid, it was cleared and nothing else changed.', 'rankkernel');
            echo '</p></div>';
        } elseif ('invalid-import' === $msg) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Import file was invalid, nothing was saved.', 'rankkernel');
            echo '</p></div>';
        }
    }

    /**
     * Render the type selector.
     *
     * @param string $selected Selected type or empty for automatic.
     * @param string $resolved Resolved automatic type shown in the label.
     * @param string $postType Current post type slug.
     */
    private function renderTypeSelector( string $selected, string $resolved, string $postType ): void {
        echo '<h3>' . esc_html__('Schema type', 'rankkernel') . '</h3>';
        echo '<p><label for="rankkernel-schema-type">' . esc_html__('Type', 'rankkernel') . '</label> ';
        echo '<select name="rankkernel_schema_type" id="rankkernel-schema-type">';

        $autoLabel = '' !== $resolved
            // translators: %s: schema type name, e.g. Blog Posting.
            ? sprintf(__('Automatic (%s)', 'rankkernel'), SchemaTypes::label($resolved))
            : __('Automatic', 'rankkernel');
        $auto = '' === $selected ? ' selected="selected"' : '';
        echo '<option value=""' . $auto . '>' . esc_html($autoLabel) . '</option>';

        foreach (SchemaTypes::SUPPORTED as $type) {
            $mark = $type === $selected ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr($type) . '"' . $mark . '>'
                . esc_html(SchemaTypes::label($type)) . '</option>';
        }

        echo '</select></p>';

        if ('' !== $postType) {
            echo '<p class="description">';
            echo esc_html__(
                'Automatic uses the default type set for this post type in RankKernel Schema settings.',
                'rankkernel'
            );
            echo '</p>';
        }
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
     * Render the manual field overrides, collapsed with per type rows.
     *
     * @param array<string, mixed> $fields   Stored field values.
     * @param string               $selected Currently selected type or empty.
     */
    private function renderFields( array $fields, string $selected ): void {
        echo '<details><summary>'
            . esc_html__('Manual field overrides (optional)', 'rankkernel')
            . '</summary>';
        echo '<p class="description">';
        echo esc_html__(
            'Only needed when a value must differ from the post itself. Rows unrelated to the chosen type stay hidden.',
            'rankkernel'
        );
        echo '</p>';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach (self::FIELD_KEYS as $key) {
            $value = isset($fields[ $key ]) && is_scalar($fields[ $key ]) ? (string) $fields[ $key ] : '';
            $id    = 'rankkernel-schema-field-' . $key;
            $label = self::FIELD_LABELS[ $key ];
            $types = implode(',', self::FIELD_TYPES[ $key ]);
            $hide  = $this->fieldVisible($key, $selected) ? '' : ' style="display:none;"';

            echo '<tr data-rankkernel-field-types="' . esc_attr($types) . '"' . $hide . '>';
            echo '<th scope="row"><label for="' . esc_attr($id) . '">'
                . esc_html($label) . '</label></th><td>';
            echo '<input type="text" id="' . esc_attr($id) . '" class="regular-text" name="'
                . esc_attr('rankkernel_schema_fields[' . $key . ']') . '" value="'
                . esc_attr($value) . '" />';
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</details>';
    }

    /**
     * Whether a manual field row shows for the selected type.
     */
    private function fieldVisible( string $key, string $selected ): bool {
        $types = self::FIELD_TYPES[ $key ];

        if (in_array('*', $types, true)) {
            return true;
        }

        return '' !== $selected && in_array($selected, $types, true);
    }

    /**
     * Render the custom JSON box.
     *
     * @param array<string, mixed> $schema Stored schema subtree.
     */
    private function renderCustom( array $schema ): void {
        $custom = ( isset($schema['custom']) && is_array($schema['custom']) ) ? $schema['custom'] : [];

        $json = function_exists('wp_json_encode')
            ? (string) wp_json_encode($custom, JSON_PRETTY_PRINT)
            : (string) json_encode($custom, JSON_PRETTY_PRINT);

        echo '<h3>' . esc_html__('Custom JSON', 'rankkernel') . '</h3>';
        echo '<p><label for="rankkernel-schema-custom">' . esc_html__('Extra schema properties', 'rankkernel')
            . '</label></p>';
        echo '<textarea id="rankkernel-schema-custom" class="large-text code" rows="6" name="'
            . esc_attr('rankkernel_schema_custom') . '">' . esc_textarea($json) . '</textarea>';
        echo '<p class="description">';
        echo esc_html__(
            'Optional, for advanced use. A valid JSON object typed here is added to the schema output as is.',
            'rankkernel'
        );
        echo '</p>';
    }

    /**
     * Render the server computed validation warnings.
     *
     * @param string               $selected Selected type or empty for automatic.
     * @param array<string, mixed> $schema   Stored schema subtree.
     */
    private function renderValidation( string $selected, array $schema ): void {
        $messages = $this->validationMessages($selected, $schema);
        $label    = '' === $selected ? 'Automatic' : $selected;

        echo '<h3>' . esc_html__('Validation', 'rankkernel') . '</h3>';

        if ([] === $messages) {
            echo '<div class="notice notice-success inline"><p>';
            echo esc_html(
                sprintf(
                    /* translators: %s: schema type name */
                    __('All required fields for %s are present.', 'rankkernel'),
                    $label
                )
            );
            echo '</p></div>';

            return;
        }

        echo '<div class="notice notice-warning inline"><ul>';

        foreach ($messages as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
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
     * @return string[]
     */
    public function validationMessages( string $selected, array $schema ): array {
        if ('' === $selected) {
            return [];
        }

        if ('FAQPage' === $selected) {
            return 0 === $this->countQuestions($schema)
                ? [ 'At least one question is required for FAQPage.' ]
                : [];
        }

        if ('HowTo' === $selected) {
            return 0 === $this->countSteps($schema)
                ? [ 'At least one step is required for HowTo.' ]
                : [];
        }

        $fields = ( isset($schema['fields']) && is_array($schema['fields']) ) ? $schema['fields'] : [];

        $messages = [];

        foreach (SchemaTypes::requiredFields($selected) as $field) {
            $value = isset($fields[ $field ]) ? trim((string) $fields[ $field ]) : '';

            if ('' === $value) {
                $messages[] = SchemaTypes::requiredMessage($selected, $field);
            }
        }

        return $messages;
    }

    /**
     * Count valid FAQ questions, rows with a non empty question.
     *
     * @param array<string, mixed> $schema Stored schema subtree.
     */
    private function countQuestions( array $schema ): int {
        $faq = ( isset($schema['faq']) && is_array($schema['faq']) ) ? $schema['faq'] : [];
        $rows = ( isset($faq['questions']) && is_array($faq['questions']) ) ? $faq['questions'] : [];

        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ('' !== trim((string) ( $row['question'] ?? '' ))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count valid HowTo steps, rows with a title or text.
     *
     * @param array<string, mixed> $schema Stored schema subtree.
     */
    private function countSteps( array $schema ): int {
        $howto = ( isset($schema['howto']) && is_array($schema['howto']) ) ? $schema['howto'] : [];
        $rows = ( isset($howto['steps']) && is_array($howto['steps']) ) ? $howto['steps'] : [];

        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ( $row['title'] ?? '' ));
            $text  = trim((string) ( $row['text'] ?? '' ));

            if ('' !== $title || '' !== $text) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Render the external validator links.
     *
     * @param int $postId Current post id.
     */
    private function renderLinks( int $postId ): void {
        $permalink = function_exists('get_permalink') ? (string) get_permalink($postId) : '';

        $richResults = 'https://search.google.com/test/rich-results?url=' . rawurlencode($permalink);
        $validator   = 'https://validator.schema.org/';

        echo '<h3>' . esc_html__('Test this page', 'rankkernel') . '</h3>';
        echo '<p><a href="' . esc_url($richResults) . '" target="_blank" rel="noopener">'
            . esc_html__('Rich Results Test', 'rankkernel') . '</a> | ';
        echo '<a href="' . esc_url($validator) . '" target="_blank" rel="noopener">'
            . esc_html__('Schema Validator', 'rankkernel') . '</a></p>';
    }

    /**
     * Render the import and export controls.
     *
     * @param int $postId Current post id.
     */
    private function renderImportExport( int $postId ): void {
        $exportUrl = function_exists('wp_nonce_url')
            ? wp_nonce_url(
                admin_url('admin-post.php?action=' . self::EXPORT_ACTION . '&post=' . $postId),
                self::EXPORT_ACTION . '_' . $postId
            )
            : '#';

        echo '<h3>' . esc_html__('Import and export', 'rankkernel') . '</h3>';
        echo '<p><a class="button" href="' . esc_url($exportUrl) . '">'
            . esc_html__('Export JSON', 'rankkernel') . '</a></p>';
        echo '<p><label for="rankkernel-schema-import">' . esc_html__('Import JSON', 'rankkernel')
            . '</label> ';
        echo '<input type="file" id="rankkernel-schema-import" name="rankkernel_schema_import" '
            . 'accept=".json,application/json" /></p>';
        echo '<p class="description">';
        echo esc_html__(
            'Upload a file previously exported with the Export JSON button above.',
            'rankkernel'
        );
        echo '</p>';
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
    public function handleSave( int $postId, mixed $post = null ): void {
        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($postId)) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            wp_die(
                esc_html__('Sorry, you are not allowed to edit this post.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        if (! isset($_POST[ self::NONCE_FIELD ])) {
            return;
        }

        $verified = check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);
        if (false === $verified) {
            wp_die(
                esc_html__('Security check failed. Please refresh and try again.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $import = $this->readImportFile();

        if ($import['found']) {
            if (! $import['valid']) {
                $this->saveStatus = 'invalid-import';

                return;
            }

            $existing             = $this->readPayload($postId);
            $existing['schema']   = $import['schema'];
            $this->saveStatus     = 'saved';

            update_post_meta($postId, self::META_KEY, $existing);

            return;
        }

        $customRaw = isset($_POST['rankkernel_schema_custom'])
            ? (string) wp_unslash($_POST['rankkernel_schema_custom'])
            : '';

        $custom = [];
        $status = 'saved';

        if ('' !== trim($customRaw)) {
            $decoded = json_decode($customRaw, true);

            if (JSON_ERROR_NONE !== json_last_error() || ! is_array($decoded)) {
                $status = 'invalid-json';
            } else {
                $custom = $decoded;
            }
        }

        $rawSchema = [
            'type'     => $this->postedType(),
            'disabled' => isset($_POST['rankkernel_schema_disabled']),
            'fields'   => $this->postedFields(),
            'faq'    => [ 'questions' => $this->postedQuestions() ],
            'howto'  => $this->postedHowto(),
            'custom' => $custom,
        ];

        $sanitized = MetaPayload::sanitize([ 'schema' => $rawSchema ]);
        $newSchema = ( isset($sanitized['schema']) && is_array($sanitized['schema']) )
            ? $sanitized['schema']
            : [];

        $existing        = $this->readPayload($postId);
        $existingSchema  = ( isset($existing['schema']) && is_array($existing['schema']) )
            ? $existing['schema']
            : [];

        // The FAQ and HowTo builders now live in blocks. When their POST
        // keys are absent, the metabox did not render them, so previously
        // stored rows carry over instead of being wiped.
        if (! isset($_POST['rankkernel_schema_faq']) && isset($existingSchema['faq'])) {
            $newSchema['faq'] = $existingSchema['faq'];
        }

        $howtoKeys = [
            'rankkernel_schema_howto_name',
            'rankkernel_schema_howto_steps',
            'rankkernel_schema_howto_totaltime',
            'rankkernel_schema_howto_cost',
        ];

        $howtoPosted = false;

        foreach ($howtoKeys as $howtoKey) {
            if (isset($_POST[ $howtoKey ])) {
                $howtoPosted = true;
                break;
            }
        }

        if (! $howtoPosted && isset($existingSchema['howto'])) {
            $newSchema['howto'] = $existingSchema['howto'];
        }

        $existing['schema'] = $newSchema;
        $this->saveStatus   = $status;

        update_post_meta($postId, self::META_KEY, $existing);
    }

    /**
     * Read the posted type, validated against the central list.
     */
    private function postedType(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $raw = isset($_POST['rankkernel_schema_type'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            ? trim((string) wp_unslash($_POST['rankkernel_schema_type']))
            : '';

        return in_array($raw, SchemaTypes::SUPPORTED, true) ? $raw : '';
    }

    /**
     * Read the posted manual fields, allowlisted keys only.
     *
     * @return array<string, string>
     */
    private function postedFields(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $raw = $_POST['rankkernel_schema_fields'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $fields = [];

        foreach (self::FIELD_KEYS as $key) {
            if (! isset($raw[ $key ]) || ! is_scalar($raw[ $key ])) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            $posted = (string) wp_unslash($raw[ $key ]);

            if (in_array($key, self::URL_KEYS, true)) {
                $value = function_exists('esc_url_raw') ? esc_url_raw($posted) : trim($posted);
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
                $value = sanitize_text_field($posted);
            }

            if ('' !== $value) {
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $raw = $_POST['rankkernel_schema_faq'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $rows = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            $question = isset($row['question'])
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
                ? sanitize_text_field((string) wp_unslash($row['question']))
                : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            $answer = isset($row['answer'])
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
                ? sanitize_text_field((string) wp_unslash($row['answer']))
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
        $rawName = isset($_POST['rankkernel_schema_howto_name'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            ? sanitize_text_field((string) wp_unslash($_POST['rankkernel_schema_howto_name']))
            : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $rawTotal = isset($_POST['rankkernel_schema_howto_totaltime'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            ? sanitize_text_field((string) wp_unslash($_POST['rankkernel_schema_howto_totaltime']))
            : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $rawCost = isset($_POST['rankkernel_schema_howto_cost'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
            ? sanitize_text_field((string) wp_unslash($_POST['rankkernel_schema_howto_cost']))
            : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handleSave.
        $rawSteps = $_POST['rankkernel_schema_howto_steps'] ?? [];
        $steps    = [];

        if (is_array($rawSteps)) {
            foreach ($rawSteps as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $steps[] = [
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                    'title' => isset($row['title'])
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                        ? sanitize_text_field((string) wp_unslash($row['title']))
                        : '',
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                    'text'  => isset($row['text'])
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                        ? sanitize_text_field((string) wp_unslash($row['text']))
                        : '',
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                    'image' => isset($row['image'])
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
                        ? esc_url_raw((string) wp_unslash($row['image']))
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

        if (! isset($_FILES['rankkernel_schema_import']) || ! is_array($_FILES['rankkernel_schema_import'])) {
            return $empty;
        }

        $file  = $_FILES['rankkernel_schema_import'];
        $error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );

        if (UPLOAD_ERR_NO_FILE === $error) {
            return $empty;
        }

        if (UPLOAD_ERR_OK !== $error) {
            return [
                'found'  => true,
                'valid'  => false,
                'schema' => [],
            ];
        }

        $tmp = $file['tmp_name'] ?? '';

        $probe = $this->isUploadedFile;

        if (! is_string($tmp) || '' === $tmp || ! $probe($tmp)) {
            return [
                'found'  => true,
                'valid'  => false,
                'schema' => [],
            ];
        }

        if (function_exists('wp_check_filetype') && isset($file['name']) && is_string($file['name'])) {
            $check = wp_check_filetype($file['name'], [ 'json' => 'application/json' ]);

            if (! is_array($check) || empty($check['ext'])) {
                return [
                    'found'  => true,
                    'valid'  => false,
                    'schema' => [],
                ];
            }
        }

        $contents = file_get_contents($tmp);

        if (false === $contents) {
            return [
                'found'  => true,
                'valid'  => false,
                'schema' => [],
            ];
        }

        $decoded = json_decode($contents, true);

        if (JSON_ERROR_NONE !== json_last_error() || ! is_array($decoded)) {
            return [
                'found'  => true,
                'valid'  => false,
                'schema' => [],
            ];
        }

        $sanitized = MetaPayload::sanitize([ 'schema' => $decoded ]);
        $schema    = ( isset($sanitized['schema']) && is_array($sanitized['schema']) )
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
     */
    public function filterRedirect( string $location ): string {
        if (null === $this->saveStatus) {
            return $location;
        }

        if (function_exists('add_query_arg')) {
            return add_query_arg('rankkernel_schema_msg', $this->saveStatus, $location);
        }

        $sep = str_contains($location, '?') ? '&' : '?';

        return $location . $sep . 'rankkernel_schema_msg=' . $this->saveStatus;
    }

    /**
     * Export handler, downloads the stored schema object as JSON.
     */
    public function handleExport(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;

        if ($postId <= 0) {
            wp_die(
                esc_html__('Missing post.', 'rankkernel'),
                '',
                [ 'response' => 400 ]
            );
        }

        if (! current_user_can('edit_post', $postId)) {
            wp_die(
                esc_html__('Sorry, you are not allowed to edit this post.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $verified = check_admin_referer(self::EXPORT_ACTION . '_' . $postId);
        if (false === $verified) {
            wp_die(
                esc_html__('Security check failed. Please refresh and try again.', 'rankkernel'),
                '',
                [ 'response' => 403 ]
            );
        }

        $schema = $this->readSchema($this->readPayload($postId));

        $slug = function_exists('get_post_field') ? (string) get_post_field('post_name', $postId) : '';

        if ('' === $slug) {
            $slug = 'post-' . $postId;
        }

        if (function_exists('sanitize_file_name')) {
            $slug = (string) sanitize_file_name($slug);
        }

        if ('' === trim($slug)) {
            $slug = 'post-' . $postId;
        }

        $json = function_exists('wp_json_encode') ? (string) wp_json_encode($schema) : (string) json_encode($schema);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $slug) . '-schema.json"');

        echo $json;

        if (! defined('RANKKERNEL_TESTING')) {
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
        $raw = function_exists('get_post_meta') ? get_post_meta($postId, self::META_KEY, true) : [];

        if (is_array($raw)) {
            return $raw;
        }

        return MetaPayload::decodeMetaValue($raw);
    }

    /**
     * Read the schema subtree, legacy lists become empty.
     *
     * @param array<string, mixed> $payload Stored payload.
     * @return array<string, mixed>
     */
    private function readSchema( array $payload ): array {
        $schema = $payload['schema'] ?? [];

        if (! is_array($schema)) {
            return [];
        }

        if ([] !== $schema && array_is_list($schema)) {
            return [];
        }

        return $schema;
    }
}
