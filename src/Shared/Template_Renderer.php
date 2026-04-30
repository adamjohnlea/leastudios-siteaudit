<?php
/**
 * Render PHP template partials with extracted scope variables.
 *
 * @package LEAStudios\SiteAudit\Shared
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and renders PHP partials from a base directory, populating their
 * scope from a `(string => mixed)` context array. Two flavours:
 *
 *   - {@see render()} streams the partial's output directly (used by admin
 *     screens that emit during the WP request lifecycle).
 *   - {@see render_to_string()} captures the output via `ob_start()` (used
 *     by email notifiers that need the rendered HTML as a string).
 *
 * Both methods silently no-op if the resolved path doesn't exist; the
 * caller is responsible for any user-facing error handling. This matches
 * the prior per-controller helpers it replaces.
 */
final class Template_Renderer {

	/**
	 * Base directory partials resolve under (always trailing-slashed).
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Set the base directory partials resolve under.
	 *
	 * @param string $base_dir Absolute path under which `$relative_path` is resolved.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/' ) . '/';
	}

	/**
	 * Include a partial directly to output.
	 *
	 * @param string               $relative_path Path under the base directory.
	 * @param array<string, mixed> $context       Variables extracted into the partial's scope.
	 *
	 * @return void
	 */
	public function render( string $relative_path, array $context = [] ): void {
		$file = $this->base_dir . $relative_path;

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- partials use bare names; this is the controlled rendering boundary.
		extract( $context, EXTR_SKIP );
		include $file;
	}

	/**
	 * Render a partial and capture its output as a string.
	 *
	 * @param string               $relative_path Path under the base directory.
	 * @param array<string, mixed> $context       Variables extracted into the partial's scope.
	 *
	 * @return string Captured output (empty string if the file is missing or the buffer collapses).
	 */
	public function render_to_string( string $relative_path, array $context = [] ): string {
		$file = $this->base_dir . $relative_path;

		if ( ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- partials use bare names; this is the controlled rendering boundary.
		extract( $context, EXTR_SKIP );
		include $file;
		$html = ob_get_clean();

		return false === $html ? '' : (string) $html;
	}
}
