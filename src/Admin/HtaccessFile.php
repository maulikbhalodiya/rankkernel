<?php
/**
 * Safe .htaccess read, backup and write.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the site .htaccess with a timestamped backup.
 *
 * A bad .htaccess can take the whole site down, so this class backs the file
 * up before every write, only reports success when the write happened, and
 * only supports Apache and LiteSpeed, where .htaccess is actually read.
 */
final class HtaccessFile {
	/**
	 * Absolute path of the site .htaccess, filterable for tests.
	 *
	 * @return string The result.
	 */
	public function path(): string {
		$default = defined( 'ABSPATH' ) ? ABSPATH . '.htaccess' : '';

		$filtered = apply_filters( 'rankkernel/htaccess/path', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_string( $filtered ) || '' === $filtered || $filtered === $default ) {
			return $default;
		}

		return $this->containedPath( $filtered, $default );
	}

	/**
	 * Keep a filtered path only when it resolves inside the site root.
	 *
	 * Rejects null bytes, relative paths and symlinks, then validates that both
	 * the parent directory and the file itself, when it already exists, resolve
	 * inside ABSPATH.
	 *
	 * @param string $path Filtered path.
	 * @param string $fallback Default path.
	 * @return string The result.
	 */
	private function containedPath( string $path, string $fallback ): string {
		// A null byte makes later filesystem calls raise a ValueError, and a
		// relative path would be resolved against the current working directory
		// rather than the site root, so neither can be checked for containment.
		$isAbsolute = '/' === $path[0] || 1 === preg_match( '/^[A-Za-z]:[\\\\\/]/', $path );

		if ( false !== strpos( $path, "\0" ) || ! $isAbsolute ) {
			return $fallback;
		}

		// Rejecting a symlink outright is what closes the dangling symlink case.
		// A link whose target does not exist yet fails both file_exists() and
		// realpath(), so a leaf check gated on either would be skipped while a
		// later write still followed the link to a target outside the root.
		if ( is_link( $path ) ) {
			return $fallback;
		}

		if ( ! function_exists( 'wp_normalize_path' ) ) {
			return $fallback;
		}

		$root = realpath( ABSPATH );
		$dir  = realpath( dirname( $path ) );

		if ( false === $root || false === $dir ) {
			return $fallback;
		}

		$rootNorm = rtrim( wp_normalize_path( $root ), '/' ) . '/';
		$dirNorm  = rtrim( wp_normalize_path( $dir ), '/' ) . '/';

		if ( ! str_starts_with( $dirNorm, $rootNorm ) ) {
			return $fallback;
		}

		if ( file_exists( $path ) ) {
			$realPath = realpath( $path );

			if ( false === $realPath ) {
				return $fallback;
			}

			$fileNorm = wp_normalize_path( $realPath );

			if ( ! str_starts_with( $fileNorm, $rootNorm ) ) {
				return $fallback;
			}

			return $realPath;
		}

		return $path;
	}

	/**
	 * Whether the .htaccess exists.
	 *
	 * @return bool The result.
	 */
	public function exists(): bool {
		$path = $this->path();

		return '' !== $path && file_exists( $path );
	}

	/**
	 * Read the .htaccess content.
	 *
	 * @return string The result.
	 */
	public function read(): string {
		$path = $this->path();

		if ( '' === $path || ! file_exists( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the site .htaccess, the only portable option.

		return ( is_string( $content ) ) ? $content : '';
	}

	/**
	 * Whether the .htaccess is on a server that reads it.
	 *
	 * @return bool The result.
	 */
	public function isSupported(): bool {
		$supported = (bool) ( $GLOBALS['is_apache'] ?? false );

		if ( ! $supported && isset( $_SERVER['SERVER_SOFTWARE'] ) && is_string( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$software  = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) );
			$supported = str_contains( $software, 'apache' ) || str_contains( $software, 'litespeed' );
		}

		/**
		 * Filter whether .htaccess editing is available on this server.
		 *
		 * @param bool $supported Detected support.
		 */
		return (bool) apply_filters( 'rankkernel/htaccess/supported', $supported ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
	}

	/**
	 * Whether the .htaccess can be written.
	 *
	 * @return bool The result.
	 */
	public function isWritable(): bool {
		if (
			( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			|| ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		) {
			return false;
		}

		$path = $this->path();

		if ( '' === $path ) {
			return false;
		}

		return file_exists( $path ) ? is_writable( $path ) : is_writable( dirname( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- checking writability of the site .htaccess before offering an editor.
	}

	/**
	 * How many backups to keep; older ones are pruned on every save.
	 */
	public const BACKUP_KEEP = 5;

	/**
	 * Start marker of the RankKernel managed block.
	 */
	public const MARKER_BEGIN = '# BEGIN RankKernel';

	/**
	 * End marker of the RankKernel managed block.
	 */
	public const MARKER_END = '# END RankKernel';

	/**
	 * The only directives RankKernel can manage in .htaccess.
	 *
	 * The wp.org plugin review asks plugins not to accept arbitrary
	 * server directives: RankKernel therefore never writes admin-typed
	 * text. Every directive string is generated from this fixed map and
	 * a save request may only reference the ids here. Ids double as
	 * request keys, so they must stay lowercase word characters.
	 *
	 * @var array<string, array{signature: string, lines: string[]}>
	 */
	public const DIRECTIVES = [
		'block_directory_browsing' => [
			// Matched at line start when reading the managed block back.
			'signature' => 'Options -Indexes',
			'lines'     => [ 'Options -Indexes' ],
		],
		'block_ai_crawlers'        => [
			'signature' => 'RewriteCond %{HTTP_USER_AGENT} (GPTBot|ClaudeBot',
			'lines'     => [
				'RewriteEngine On',
				'RewriteCond %{HTTP_USER_AGENT} (GPTBot|ClaudeBot|Claude-Web|CCBot|Google-Extended|PerplexityBot|Bytespider|FacebookBot) [NC]',
				'RewriteRule ^ - [F,L]',
			],
		],
	];

	/**
	 * Option holding the last few .htaccess snapshots.
	 *
	 * Private by construction: WordPress options are not web-servable, so
	 * this works on Nginx without relying on a directory deny rule. The
	 * option is non-autoloaded and bounded to BACKUP_KEEP snapshots.
	 */
	public const BACKUP_OPTION = 'rankkernel_htaccess_backups';

	/**
	 * Store one snapshot in the private option and prune the oldest.
	 *
	 * @param string $content Current .htaccess content.
	 * @return string Backup token, empty when storage failed.
	 */
	private function storeBackup( string $content ): string {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return '';
		}

		$stored = get_option( self::BACKUP_OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$time = function_exists( 'time' ) ? time() : 0;

		try {
			$suffix = bin2hex( random_bytes( 8 ) );
		} catch ( \Throwable $throwable ) {
			$suffix = str_replace( '.', '', uniqid( '', true ) );
		}

		$token    = 'db:' . $time . ':' . $suffix;
		$stored[] = [
			'token'   => $token,
			'time'    => $time,
			'content' => $content,
		];

		$stored = array_slice( $stored, -self::BACKUP_KEEP );

		return true === update_option( self::BACKUP_OPTION, $stored, false ) ? $token : '';
	}

	/**
	 * Stored snapshots, newest last. Primarily for admin diagnostics/tests.
	 *
	 * @return array<int, array{token: string, time: int, content: string}> The result.
	 */
	public function backups(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$stored = get_option( self::BACKUP_OPTION, [] );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		$clean = [];

		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['content'] ) || ! is_string( $row['content'] ) ) {
				continue;
			}

			$clean[] = [
				'token'   => isset( $row['token'] ) && is_string( $row['token'] ) ? $row['token'] : '',
				'time'    => isset( $row['time'] ) ? (int) $row['time'] : 0,
				'content' => $row['content'],
			];
		}

		return $clean;
	}

	/**
	 * Title of the managed block written inside the markers.
	 */
	private const BLOCK_NOTE = '# RankKernel managed directives. Edit from wp-admin settings; content outside this block is never changed by RankKernel.';

	/**
	 * Build the managed directives block from whitelisted ids.
	 *
	 * Unknown ids are ignored, so a forged request can never smuggle
	 * text into the file: only strings generated from the fixed
	 * DIRECTIVES map leave this method.
	 *
	 * @param array<int|string, mixed> $selected Requested directive ids.
	 * @return string The block, empty when no directive is selected, which removes the block.
	 */
	public function generateBlock( array $selected ): string {
		// Accept ids as either array keys, plain values, or checkbox
		// arrays of one, so a save request earns a hit in any of the
		// shapes the admin payload can arrive.
		$ids = [];

		foreach ( $selected as $key => $value ) {
			$ids[] = (string) $key;

			if ( is_string( $value ) || is_int( $value ) ) {
				$ids[] = (string) $value;
			}

			if ( is_array( $value ) ) {
				foreach ( array_keys( $value ) as $nested ) {
					$ids[] = (string) $nested;
				}
			}
		}

		$lines = [];

		foreach ( self::DIRECTIVES as $id => $directive ) {
			if ( in_array( $id, $ids, true ) ) {
				foreach ( $directive['lines'] as $line ) {
					$lines[] = $line;
				}
			}
		}

		if ( [] === $lines ) {
			return '';
		}

		return self::MARKER_BEGIN . "\n"
			. self::BLOCK_NOTE . "\n"
			. implode( "\n", $lines ) . "\n"
			. self::MARKER_END . "\n";
	}

	/**
	 * Whether the file currently carries the managed block.
	 *
	 * @return bool The result.
	 */
	public function hasBlock(): bool {
		return str_contains( $this->read(), self::MARKER_BEGIN );
	}

	/**
	 * Which whitelisted directives are currently active in the file.
	 *
	 * Matches each directive's signature line inside the managed block,
	 * so a manually deleted directive is reported as off.
	 *
	 * @return array<int, string> Active directive ids.
	 */
	public function blockSelection(): array {
		$content = $this->read();

		if ( '' === $content || ! str_contains( $content, self::MARKER_BEGIN ) ) {
			return [];
		}

		$lines    = preg_split( '/\R/', $content );
		$selected = [];

		if ( ! is_array( $lines ) ) {
			return [];
		}

		foreach ( self::DIRECTIVES as $id => $directive ) {
			foreach ( $lines as $line ) {
				if ( str_starts_with( ltrim( (string) $line ), $directive['signature'] ) ) {
					$selected[] = $id;

					break;
				}
			}
		}

		return $selected;
	}

	/**
	 * Replace the managed block, then write via the guarded save path.
	 *
	 * Everything outside the marker block is preserved byte for byte.
	 * An empty selection clears the managed block. When the resulting
	 * document would not change, nothing is written and no backup is
	 * taken.
	 *
	 * @param array<int|string, mixed> $selected Requested directive ids.
	 * @return array{saved: bool, reason: string, backup: string} Save result.
	 */
	public function saveBlock( array $selected ): array {
		$content = $this->read();
		$block   = $this->generateBlock( $selected );

		$updated = ( '' === $block ) ? $this->stripBlock( $content ) : $block . "\n" . $this->stripBlock( $content );

		if ( $updated === $content ) {
			return [
				'saved'  => true,
				'reason' => '',
				'backup' => '',
			];
		}

		return $this->save( $updated );
	}

	/**
	 * Remove the managed block without requiring server detection.
	 *
	 * Deactivation can run from WP-CLI, where SERVER_SOFTWARE is absent even
	 * on Apache. Removing our own marked block is safe: only bytes between
	 * the markers are touched, and everything else is preserved. Writes still
	 * require a writable file and a safe backup.
	 *
	 * @return array{saved: bool, reason: string, backup: string} Result.
	 */
	public function removeBlock(): array {
		$content = $this->read();

		if ( '' === $content || ! str_contains( $content, self::MARKER_BEGIN ) ) {
			return [
				'saved'  => true,
				'reason' => '',
				'backup' => '',
			];
		}

		$updated = $this->stripBlock( $content );

		if ( $updated === $content ) {
			return [
				'saved'  => true,
				'reason' => '',
				'backup' => '',
			];
		}

		return $this->save( $updated, false );
	}

	/**
	 * Drop the managed block from a document.
	 *
	 * @param string $content Current content.
	 * @return string Content without the managed block.
	 */
	private function stripBlock( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		$pattern  = '/' . preg_quote( self::MARKER_BEGIN, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*\n?/s';
		$stripped = preg_replace( $pattern, '', $content );

		return ( is_string( $stripped ) ) ? $stripped : $content;
	}

	/**
	 * Back up the current file, then write the new content.
	 *
	 * @param string $content          New .htaccess content.
	 * @param bool   $require_supported Whether server detection must pass.
	 * @return array{saved: bool, reason: string, backup: string} Result, reason code and backup path.
	 */
	public function save( string $content, bool $require_supported = true ): array {
		$path = $this->path();

		if ( '' === $path ) {
			return [
				'saved'  => false,
				'reason' => 'no_path',
				'backup' => '',
			];
		}

		if ( $require_supported && ! $this->isSupported() ) {
			return [
				'saved'  => false,
				'reason' => 'unsupported',
				'backup' => '',
			];
		}

		if ( ! $this->isWritable() ) {
			return [
				'saved'  => false,
				'reason' => 'not_writable',
				'backup' => '',
			];
		}

		$backup = '';

		if ( file_exists( $path ) ) {
			$existing = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the live .htaccess before a private snapshot.

			$backup = $this->storeBackup( is_string( $existing ) ? $existing : '' );

			if ( '' === $backup ) {
				return [
					'saved'  => false,
					'reason' => 'backup_failed',
					'backup' => '',
				];
			}
		}

		$dir = dirname( $path );

		// The temporary file must live on the same filesystem so rename() can
		// replace the live file atomically.
		if ( ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- temp file needs a writable parent before the atomic replace.
			return [
				'saved'  => false,
				'reason' => 'not_writable',
				'backup' => $backup,
			];
		}

		try {
			$suffix = bin2hex( random_bytes( 8 ) );
		} catch ( \Throwable $throwable ) {
			$suffix = str_replace( '.', '', uniqid( '', true ) );
		}

		$tmp   = $path . '.rankkernel-tmp-' . $suffix;
		$bytes = file_put_contents( $tmp, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a same-directory temp file before the atomic rename.

		// A failed or partial temp write never touches the live file.
		if ( false === $bytes || $bytes < strlen( $content ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- removing our own failed temp file.

			return [
				'saved'  => false,
				'reason' => 'write_failed',
				'backup' => $backup,
			];
		}

		// Keep the existing file mode; rename() would otherwise install the
		// temp file's umask-derived permissions.
		if ( file_exists( $path ) ) {
			$mode = fileperms( $path );

			if ( false !== $mode ) {
				@chmod( $tmp, $mode & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged -- preserve the live .htaccess mode on the temp file.
			}
		}

		if ( ! rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace of the live .htaccess on the same filesystem.
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- removing our own temp file after a failed rename.

			return [
				'saved'  => false,
				'reason' => 'write_failed',
				'backup' => $backup,
			];
		}

		return [
			'saved'  => true,
			'reason' => '',
			'backup' => $backup,
		];
	}
}
