<?php
/**
 * Plugin Name: Hellas Wiki
 * Plugin URI: https://github.com/HellasRegion/wiki
 * Description: Complete in-game encyclopedia for the Hellas Region project.
 * Version: 1.1.0
 * Author: Hellas Forge Team
 * Text Domain: hellas-wiki
 * Requires PHP: 7.4
 * Requires at least: 6.6
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
define( 'HELLAS_WIKI_VERSION', '1.1.0' );
}

require_once HELLAS_WIKI_PATH . 'includes/Bootstrap.php';

register_activation_hook( __FILE__, [ '\\HellasWiki\\Bootstrap', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\HellasWiki\\Bootstrap', 'deactivate' ] );

add_action(
'plugins_loaded',
static function () {
HellasWiki\Bootstrap::init();
}
);
