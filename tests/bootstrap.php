<?php
/**
 * Test bootstrap, Brain Monkey (no WP DB).
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Composer autoloader (after composer install).
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Brain Monkey setup.
\Brain\Monkey\setUp();

// Polyfills for WordPress functions used in src/ that Brain Monkey doesn't auto-stub.
// These are only needed if not already stubbed via Brain\Monkey\Functions\when.

// Ensure plugin helper stubs exist.
if (! function_exists('plugin_basename')) {
    function plugin_basename( string $file ): string {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path( string $file ): string {
        return rtrim(dirname($file), '/\\') . '/';
    }
}

if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url( string $file ): string {
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

// Minimal WP stubs for unit tests (when phpunit-polyfills not yet loaded).
if (! class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        /** @var mixed */
        private $data;
        /** @param mixed $data */
        public function __construct( string $code = '', string $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }
        public function get_error_code(): string { return $this->code; 
        }
        public function get_error_message(): string { return $this->message; 
        }
        /** @return mixed */
        public function get_error_data( string $code = '' ) { return $this->data; 
        }
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        /** @var array<string,mixed> */
        private array $params = [];
        /** @var array<string,mixed> */
        private array $json = [];
        /** @param string $key */
        public function get_param( string $key ): mixed { return $this->params[ $key ] ?? null; 
        }
        /** @return mixed */
        public function get_params(): mixed { return $this->params; 
        }
        /** @return mixed */
        public function get_json_params(): mixed { return $this->json; 
        }
        public function set_param( string $key, mixed $value ): void { $this->params[ $key ] = $value; 
        }
        /** @param array<string,mixed> $data */
        public function set_json_params( array $data ): void { $this->json = $data; 
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        /** @var mixed */
        private $data;
        private int $status;
        /** @param mixed $data */
        public function __construct( $data = null, int $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }
        /** @return mixed */
        public function get_data(): mixed { return $this->data; 
        }
        public function get_status(): int { return $this->status; 
        }
    }
}

if (! class_exists('WP_Query')) {
    class WP_Query {
        /** @var array<string,mixed> */
        public array $query_vars = [];
        public int $max_num_pages = 0;
        public function is_singular( $post = '' ): bool { return false; 
        }
        public function is_search(): bool { return false; 
        }
        public function is_404(): bool { return false; 
        }
        public function is_feed(): bool { return false; 
        }
        public function is_category( $c = '' ): bool { return false; 
        }
        public function is_tag( $c = '' ): bool { return false; 
        }
        public function is_tax( $a = '', $b = '' ): bool { return false; 
        }
        public function is_home(): bool { return false; 
        }
        public function is_front_page(): bool { return false; 
        }
        public function is_archive(): bool { return false; 
        }
        public function is_author( $a = '' ): bool { return false; 
        }
        public function is_date(): bool { return false; 
        }
        public function is_post_type_archive( $c = '' ): bool { return false; 
        }
        public function is_preview(): bool { return false; 
        }
        public function get_queried_object_id(): int { return 0; 
        }
        public function get( string $key, mixed $default = '' ): mixed { return $default; 
        }
        public function get_queried_object(): mixed { return null; 
        }
    }
}
