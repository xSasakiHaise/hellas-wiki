<?php

namespace HellasWiki\Admin;

/**
 * Registers admin menu for the wiki.
 */
class Menu {
/**
 * Hook setup.
 */
public static function init(): void {
add_action( 'admin_menu', [ self::class, 'register_menu' ] );
}

/**
 * Register the menu pages.
 */
public static function register_menu(): void {
$capability = 'edit_wiki_pages';

add_menu_page(
__( 'Hellas Wiki', 'hellas-wiki' ),
__( 'Hellas Wiki', 'hellas-wiki' ),
$capability,
'hellas-wiki',
[ WizardPage::class, 'render_page' ],
'dashicons-book-alt',
3
);

add_submenu_page( 'hellas-wiki', __( 'Create Wiki Page', 'hellas-wiki' ), __( 'Create Wiki Page', 'hellas-wiki' ), $capability, 'hellas-wiki', [ WizardPage::class, 'render_page' ] );
add_submenu_page( 'hellas-wiki', __( 'Import JSON', 'hellas-wiki' ), __( 'Import JSON', 'hellas-wiki' ), 'import_wiki_pages', 'hellas-wiki-import', [ ImportPage::class, 'render_page' ] );
add_submenu_page( 'hellas-wiki', __( 'Import Queue', 'hellas-wiki' ), __( 'Import Queue', 'hellas-wiki' ), 'import_wiki_pages', 'hellas-wiki-queue', [ WizardPage::class, 'render_queue_page' ] );
add_submenu_page( 'hellas-wiki', __( 'Settings', 'hellas-wiki' ), __( 'Settings', 'hellas-wiki' ), 'manage_options', 'hellas-wiki-settings', [ WizardPage::class, 'render_settings_page' ] );
}
}
