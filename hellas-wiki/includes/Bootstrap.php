<?php

namespace HellasWiki;

use HellasWiki\Admin\DashboardWidget;
use HellasWiki\Admin\ImportPage;
use HellasWiki\Admin\Menu;
use HellasWiki\Admin\WizardPage;
use HellasWiki\CLI\Command as CLICommand;
use HellasWiki\REST\ExportController;
use HellasWiki\REST\GitHubWebhook;
use HellasWiki\REST\HealthController;
use HellasWiki\REST\ImportController;
use HellasWiki\REST\PollController;
use HellasWiki\REST\QueueController;
use HellasWiki\REST\ResolveController;
use HellasWiki\REST\SettingsController;
use HellasWiki\REST\UpdateController;
use HellasWiki\REST\SummaryController;
use HellasWiki\Types\AbstractType;
use HellasWiki\Types\AbilityType;
use HellasWiki\Types\FormType;
use HellasWiki\Types\GuideType;
use HellasWiki\Types\ItemType;
use HellasWiki\Types\LocationType;
use HellasWiki\Types\MoveType;
use HellasWiki\Types\SpeciesType;

/**
 * Bootstraps the plugin.
 */
class Bootstrap {
/**
 * Registered type instances.
 *
 * @var array<string, AbstractType>
 */
protected static array $types = [];

/**
 * Prevent duplicate cron hook registration.
 *
 * @var bool
 */
protected static bool $events_registered = false;

/**
 * Register the plugin autoloader.
 */
protected static function register_autoloader(): void {
spl_autoload_register(
static function ( string $class ): void {
if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
return;
}

$relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
$file     = HELLAS_WIKI_PATH . 'includes/' . $relative . '.php';

if ( file_exists( $file ) ) {
require_once $file;
}
}
);
}

/**
 * Initialise plugin services.
 */
public static function init(): void {
self::register_autoloader();

Helpers::init();
Capabilities::init();
        TypeRegistry::init();

        self::register_types();
        self::register_assets();

        UpdateController::register();

        Menu::init();
        WizardPage::init();
        ImportPage::init();
        DashboardWidget::init();

        CLICommand::register();

Search::init();
Routing::init();

add_action( 'rest_api_init', [ self::class, 'register_rest_controllers' ] );
add_action( 'init', [ self::class, 'register_blocks' ] );
add_action( 'init', [ self::class, 'schedule_events' ] );
add_action( 'admin_init', [ REST\SettingsController::class, 'register_options' ] );
}

/**
 * Run on plugin activation.
 */
public static function activate(): void {
self::register_autoloader();
self::register_types();
self::schedule_events();

foreach ( self::$types as $type ) {
$type->register();
}

flush_rewrite_rules();

if ( ! wp_next_scheduled( 'hellaswiki/github_poller' ) ) {
wp_schedule_event( time() + MINUTE_IN_SECONDS, 'ten_minutes', 'hellaswiki/github_poller' );
}
}

/**
 * Run on plugin deactivation.
 */
public static function deactivate(): void {
wp_clear_scheduled_hook( 'hellaswiki/github_poller' );
flush_rewrite_rules();
}

/**
 * Register the custom cron interval.
 */
public static function schedule_events(): void {
if ( self::$events_registered ) {
return;
}

self::$events_registered = true;

add_filter(
'cron_schedules',
static function ( array $schedules ): array {
if ( isset( $schedules['ten_minutes'] ) ) {
return $schedules;
}

$schedules['ten_minutes'] = [
'interval' => 10 * MINUTE_IN_SECONDS,
'display'  => __( 'Every Ten Minutes', 'hellas-wiki' ),
];

return $schedules;
}
);

add_action( 'hellaswiki/github_poller', [ Routing::class, 'run_github_poller' ] );
}

/**
 * Register Type classes.
 */
protected static function register_types(): void {
if ( ! empty( self::$types ) ) {
return;
}

self::$types = [
'wiki_species'  => new SpeciesType(),
'wiki_form'     => new FormType(),
'wiki_move'     => new MoveType(),
'wiki_ability'  => new AbilityType(),
'wiki_item'     => new ItemType(),
'wiki_location' => new LocationType(),
'wiki_guide'    => new GuideType(),
];

TypeRegistry::register_types( self::$types );
}

/**
 * Register styles and scripts.
 */
protected static function register_assets(): void {
add_action(
'admin_enqueue_scripts',
static function (): void {
wp_enqueue_style( 'hellaswiki-admin', HELLAS_WIKI_URL . 'assets/admin.css', [], HELLAS_WIKI_VERSION );
            wp_enqueue_script( 'hellaswiki-admin', HELLAS_WIKI_URL . 'assets/admin.js', [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ], HELLAS_WIKI_VERSION, true );
            wp_localize_script(
                'hellaswiki-admin',
                'hellasWikiAdmin',
                [
                    'rest'      => [
                        'root'  => esc_url_raw( rest_url( 'hellaswiki/v1/' ) ),
                        'nonce' => wp_create_nonce( 'wp_rest' ),
                    ],
                    'update'    => [
                        'nonce' => wp_create_nonce( 'hellaswiki_update' ),
                    ],
                    'placeholders' => [
                        'species' => HELLAS_WIKI_URL . 'assets/placeholder-species.svg',
                'item'    => HELLAS_WIKI_URL . 'assets/placeholder-item.svg',
],
]
);
}
);

add_action(
'wp_enqueue_scripts',
static function (): void {
wp_enqueue_style( 'hellaswiki-frontend', HELLAS_WIKI_URL . 'assets/frontend.css', [], HELLAS_WIKI_VERSION );
wp_enqueue_script( 'hellaswiki-tooltip', HELLAS_WIKI_URL . 'assets/tooltip.js', [ 'wp-api-fetch' ], HELLAS_WIKI_VERSION, true );
            wp_localize_script(
                'hellaswiki-tooltip',
                'hellasWikiTooltip',
                [
                    'endpoint' => esc_url_raw( rest_url( 'hellaswiki/v1/summary' ) ),
                ]
            );
}
);
}

/**
 * Register REST controllers.
 */
public static function register_rest_controllers(): void {
        $controllers = [
            new ImportController(),
            new ExportController(),
            new GitHubWebhook(),
            new QueueController(),
            new ResolveController(),
            new SettingsController(),
            new HealthController(),
            new PollController(),
            new SummaryController(),
        ];

foreach ( $controllers as $controller ) {
$controller->register_routes();
}
}

/**
 * Register Gutenberg blocks.
 */
public static function register_blocks(): void {
        Blocks\Infobox::register();
        Blocks\StatTable::register();
        Blocks\TextSection::register();
}
}
