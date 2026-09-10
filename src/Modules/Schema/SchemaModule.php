<?php
/**
 * Schema module, registration and boot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\TagsReplacer;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;
use RankKernel\Modules\Schema\Pieces\ArticlePiece;
use RankKernel\Modules\Schema\Pieces\BookPiece;
use RankKernel\Modules\Schema\Pieces\BreadcrumbPiece;
use RankKernel\Modules\Schema\Pieces\CarouselPiece;
use RankKernel\Modules\Schema\Pieces\ClaimReviewPiece;
use RankKernel\Modules\Schema\Pieces\CoursePiece;
use RankKernel\Modules\Schema\Pieces\DatasetPiece;
use RankKernel\Modules\Schema\Pieces\EventPiece;
use RankKernel\Modules\Schema\Pieces\FaqPiece;
use RankKernel\Modules\Schema\Pieces\HowtoPiece;
use RankKernel\Modules\Schema\Pieces\ItemListPiece;
use RankKernel\Modules\Schema\Pieces\JobPostingPiece;
use RankKernel\Modules\Schema\Pieces\MoviePiece;
use RankKernel\Modules\Schema\Pieces\MusicPiece;
use RankKernel\Modules\Schema\Pieces\OrganizationPiece;
use RankKernel\Modules\Schema\Pieces\PersonPiece;
use RankKernel\Modules\Schema\Pieces\PodcastEpisodePiece;
use RankKernel\Modules\Schema\Pieces\ProductPiece;
use RankKernel\Modules\Schema\Pieces\QaPagePiece;
use RankKernel\Modules\Schema\Pieces\RecipePiece;
use RankKernel\Modules\Schema\Pieces\ServicePiece;
use RankKernel\Modules\Schema\Pieces\SoftwarePiece;
use RankKernel\Modules\Schema\Pieces\VideoPiece;
use RankKernel\Modules\Schema\Pieces\WebpagePiece;
use RankKernel\Modules\Schema\Pieces\WebsitePiece;
use RankKernel\Modules\Schema\blocks\FaqBlock;
use RankKernel\Modules\Schema\blocks\HowtoBlock;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Schema (JSON-LD) module, renders the @graph after head tags.
 */
final class SchemaModule implements ModuleInterface {
    /**
     * Cached enabled check (delegates to shared map if injected).
     */
    private ?bool $enabledCache = null;

    /**
     * Settings store.
     */
    private SettingsStore $settings;

    /**
     * Shared enable-map holder (single get_option per request).
     */
    private ?ModuleEnableMap $enableMap;

    /**
     * Generator, built lazily with the default pieces.
     */
    private ?Generator $generator;

    /**
     * Cached context (built once per request).
     */
    private ?Context $context = null;

    /**
     * Optional injected context (tests).
     */
    private ?Context $injectedContext = null;

    /**
     * Optional injected query (tests).
     */
    private ?WP_Query $injectedQuery = null;

    /**
     * Constructor.
     *
     * @param SettingsStore|null   $settings  Optional settings store.
     * @param ModuleEnableMap|null $enableMap Optional shared enable map.
     * @param Generator|null       $generator Optional generator (tests).
     * @param Context|null         $context   Optional pre-built context (tests).
     * @param WP_Query|null        $query     Optional query (tests).
     */
    public function __construct(
        ?SettingsStore $settings = null,
        ?ModuleEnableMap $enableMap = null,
        ?Generator $generator = null,
        ?Context $context = null,
        ?WP_Query $query = null
    ) {
        $this->settings        = $settings ?? new SettingsStore();
        $this->enableMap       = $enableMap;
        $this->generator       = $generator;
        $this->injectedContext = $context;
        $this->injectedQuery   = $query;
    }

    /**
     * Get module id.
     */
    public function getId(): string {
        return 'schema';
    }

    /**
     * Get human-readable name.
     */
    public function getName(): string {
        return __('Schema', 'rankkernel');
    }

    /**
     * Module priority, after metadata.
     */
    public function getPriority(): int {
        return 30;
    }

    /**
     * Dependencies, needs the metadata Context.
     *
     * @return string[]
     */
    public function dependsOn(): array {
        return [ 'metadata' ];
    }

    /**
     * Whether the module is enabled (delegates to shared map if injected).
     */
    public function isEnabled(): bool {
        if (null !== $this->enabledCache) {
            return $this->enabledCache;
        }

        if (null !== $this->enableMap) {
            $this->enabledCache = $this->enableMap->isEnabled('schema');

            return $this->enabledCache;
        }

        $map = get_option('rankkernel_modules', []);

        if (! is_array($map)) {
            $map = [];
        }

        // Support both associative map and indexed list (activation seed is list).
        if (array_key_exists('schema', $map)) {
            $this->enabledCache = (bool) $map['schema'];
        } else {
            $this->enabledCache = in_array('schema', $map, true);
        }

        return $this->enabledCache;
    }

    /**
     * Wire services (no hooks yet).
     */
    public function register(): void {
    }

    /**
     * Boot hooks (only if enabled, caller enforces).
     *
     * Registers the after tags render hook plus the FAQ block. Boot
     * itself fires on init, so the block registration inside already
     * happens on init and stays behind the enable map gate.
     */
    public function boot(): void {
        add_action('rankkernel/head/after_tags', [ $this, 'render' ], 10);
        ( new FaqBlock() )->register();
        ( new HowtoBlock() )->register();
    }

    /**
     * Render the @graph as one JSON-LD script tag.
     *
     * Receives the HeadRenderer Context through the after_tags slot, so the
     * request keeps its single Context build and never builds two.
     *
     * @param mixed $ctx Context passed by the after_tags action.
     */
    public function render( mixed $ctx = null ): void {
        $context = $ctx instanceof Context ? $ctx : $this->getContext();
        $data    = $this->getGenerator()->generate($context);
        $graph   = $data['@graph'] ?? [];

        if (! is_array($graph) || [] === $graph) {
            return;
        }

        $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD already encoded via wp_json_encode.
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * Get the generator, built lazily with the default pieces.
     */
    public function getGenerator(): Generator {
        if (null !== $this->generator) {
            return $this->generator;
        }

        $generator = new Generator();
        $generator->register(new OrganizationPiece($this->settings));
        $generator->register(new WebsitePiece($this->settings));
        $generator->register(new WebpagePiece($this->settings));
        $generator->register(new BreadcrumbPiece($this->settings));
        $generator->register(new PersonPiece($this->settings));
        $generator->register(new ArticlePiece($this->settings));
        $generator->register(new FaqPiece());
        $generator->register(new HowtoPiece());
        $generator->register(new ProductPiece());
        $generator->register(new RecipePiece());
        $generator->register(new EventPiece());
        $generator->register(new ServicePiece());
        $generator->register(new VideoPiece());
        $generator->register(new BookPiece());
        $generator->register(new CoursePiece());
        $generator->register(new JobPostingPiece());
        $generator->register(new SoftwarePiece());
        $generator->register(new MusicPiece());
        $generator->register(new MoviePiece());
        $generator->register(new ClaimReviewPiece());
        $generator->register(new DatasetPiece());
        $generator->register(new PodcastEpisodePiece());
        $generator->register(new CarouselPiece());
        $generator->register(new QaPagePiece());
        $generator->register(new ItemListPiece());

        $this->generator = $generator;

        return $generator;
    }

    /**
     * Get or build context (once per request, reused on every render call).
     */
    private function getContext(): Context {
        if (null !== $this->injectedContext) {
            return $this->injectedContext;
        }

        if (null !== $this->context) {
            return $this->context;
        }

        $query = $this->injectedQuery;

        if (null === $query) {
            global $wp_query;

            if (isset($wp_query) && $wp_query instanceof WP_Query) {
                $query = $wp_query;
            } else {
                $query = new WP_Query();
            }
        }

        $this->context = new Context($query, $this->settings, new TagsReplacer());

        return $this->context;
    }
}
