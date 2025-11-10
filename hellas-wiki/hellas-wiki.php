<?php
/**
 * Core bootstrap for the Hellas Wiki plugin.
 *
 * @package HellasWiki
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HELLAS_WIKI_PATH' ) ) {
    define( 'HELLAS_WIKI_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'HELLAS_WIKI_URL' ) ) {
    define( 'HELLAS_WIKI_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'HELLAS_WIKI_VERSION' ) ) {
    define( 'HELLAS_WIKI_VERSION', '1.2.0' );
}

require_once HELLAS_WIKI_PATH . 'includes/Bootstrap.php';

$plugin_file = defined( 'HELLAS_WIKI_PLUGIN_FILE' ) ? HELLAS_WIKI_PLUGIN_FILE : __FILE__;

register_activation_hook( $plugin_file, [ '\\HellasWiki\\Bootstrap', 'activate' ] );
register_deactivation_hook( $plugin_file, [ '\\HellasWiki\\Bootstrap', 'deactivate' ] );

add_action(
    'plugins_loaded',
    static function () {
        HellasWiki\Bootstrap::init();
    }
);
