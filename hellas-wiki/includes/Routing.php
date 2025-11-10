<?php

namespace HellasWiki;

use HellasWiki\REST\GitHubWebhook;
use HellasWiki\Types\AbstractType;

/**
 * Handles cron operations and URL routing helpers.
 */
class Routing {
/**
 * Hook registrations.
 */
public static function init(): void {
add_action( 'init', [ self::class, 'register_type_overview_route' ] );
}

/**
 * Register rewrite for type overview.
 */
public static function register_type_overview_route(): void {
add_rewrite_rule( '^hellas-wiki/type-overview/?$', 'index.php?hellaswiki=type-overview', 'top' );
add_rewrite_tag( '%hellaswiki%', '([^&]+)' );
add_filter(
'query_vars',
static function ( array $vars ): array {
$vars[] = 'hellaswiki';
return $vars;
}
);

add_filter(
'template_include',
static function ( string $template ) {
if ( 'type-overview' === get_query_var( 'hellaswiki' ) ) {
return HELLAS_WIKI_PATH . 'templates/type-overview.php';
}

return $template;
}
);
}

/**
 * Triggered by cron to poll GitHub.
 */
    public static function run_github_poller( bool $force = false ): void {
        if ( ! $force && ! current_user_can( 'import_wiki_pages' ) && ! wp_doing_cron() ) {
            return;
        }

        $settings = get_option( 'hellaswiki_settings', [] );
        $token    = $settings['github_token'] ?? '';
        $repo     = $settings['github_repo'] ?? '';

        if ( empty( $settings['enable_poller'] ) && ! $force ) {
            Logger::info( 'Poller skipped: disabled in settings.' );
            Health::record_poll( 'disabled' );
            return;
        }

        if ( empty( $token ) || empty( $repo ) ) {
            Logger::error( 'Poller skipped: missing credentials.', [ 'repo' => $repo ? 'configured' : 'empty' ] );
            Health::record_poll( 'missing_credentials' );
            return;
        }

        Logger::info( 'Poller starting.', [ 'repo' => $repo ] );
        $count = GitHubWebhook::fetch_repository_changes( $repo, $token );
        $queue = get_option( 'hellaswiki_import_queue', [] );
        $size  = is_array( $queue ) ? count( $queue ) : 0;

        Logger::info(
            'Poller complete.',
            [
                'fetched'   => $count,
                'queueSize' => $size,
            ]
        );

        Health::record_poll( sprintf( 'queued:%d', $size ) );
    }

/**
 * Create a wiki post from parsed data.
 *
 * @param array<string, mixed> $payload Parsed payload.
 */
public static function upsert_from_payload( array $payload ): int {
$post_type = $payload['post_type'] ?? '';

if ( ! $post_type || ! post_type_exists( $post_type ) ) {
return 0;
}

/** @var AbstractType|null $type */
$type = TypeRegistry::get( $post_type );
if ( ! $type ) {
return 0;
}

return $type->upsert_from_payload( $payload );
}
}
