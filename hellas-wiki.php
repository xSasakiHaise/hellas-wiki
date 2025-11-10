<?php
/**
 * Plugin Name: Hellas Wiki
 * Plugin URI: https://github.com/HellasRegion/wiki
 * Description: Complete in-game encyclopedia for the Hellas Region project.
 * Version: 1.2.0
 * Author: Hellas Forge Team
 * Text Domain: hellas-wiki
 * Requires PHP: 7.4
 * Requires at least: 6.6
 *
 * @package HellasWiki
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HELLAS_WIKI_PLUGIN_FILE' ) ) {
    define( 'HELLAS_WIKI_PLUGIN_FILE', __FILE__ );
}

$core_file = __DIR__ . '/hellas-wiki/hellas-wiki.php';

if ( ! file_exists( $core_file ) ) {
    // Translators: %s: relative path to the plugin bootstrap file.
    $message = sprintf( __( 'Hellas Wiki could not be loaded because %s is missing.', 'hellas-wiki' ), 'hellas-wiki/hellas-wiki.php' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message already escaped by WordPress.
    error_log( $message );
    return;
}

require_once $core_file;
