<?php
/**
 * Simple PSR-4–style autoloader for all classes that start with
 * AWP_… or Wawp_…  ➜  class-awp-log-manager.php, class-wawp-connector.php …
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'AWP_' ) && 0 !== strpos( $class, 'Wawp_' ) ) {
			return;
		}

		$filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$path     = AWP_PLUGIN_DIR . 'includes/' . $filename;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
