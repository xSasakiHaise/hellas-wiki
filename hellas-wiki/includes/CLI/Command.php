<?php

namespace HellasWiki\CLI;

use HellasWiki\Health;
use HellasWiki\REST\ImportController;
use HellasWiki\Routing;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI entry points for Hellas Wiki utilities.
 */
class Command extends WP_CLI_Command {
    /**
     * Register commands when WP-CLI is present.
     */
    public static function register(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'hellaswiki', self::class );
        }
    }

    /**
     * Display a health summary.
     *
     * ## EXAMPLES
     *
     *     wp hellaswiki health
     */
    public function health(): void {
        $status   = Health::get_status();
        $settings = get_option( 'hellaswiki_settings', [] );
        $queue    = get_option( 'hellaswiki_import_queue', [] );
        $next     = wp_next_scheduled( 'hellaswiki/github_poller' );

        $rows = [
            [ 'Key' => 'Repository', 'Value' => $settings['github_repo'] ?? 'xSasakiHaise/hellasforms' ],
            [ 'Key' => 'PAT Configured', 'Value' => empty( $settings['github_token'] ) && empty( get_option( 'hellaswiki_github_token', '' ) ) ? 'No' : 'Yes' ],
            [ 'Key' => 'Poller Enabled', 'Value' => ! empty( $settings['enable_poller'] ) ? 'Yes' : 'No' ],
            [ 'Key' => 'Last Poll', 'Value' => $status['last_poll_at'] ?: '—' ],
            [ 'Key' => 'Last Poll Result', 'Value' => $status['last_poll_result'] ?: '—' ],
            [ 'Key' => 'Last Webhook', 'Value' => $status['last_webhook_at'] ?: '—' ],
            [ 'Key' => 'Last Webhook Status', 'Value' => $status['last_webhook_status'] ?: '—' ],
            [ 'Key' => 'Queue Count', 'Value' => is_array( $queue ) ? count( $queue ) : 0 ],
            [ 'Key' => 'Next Cron', 'Value' => $next ? wp_date( DATE_ATOM, $next ) : '—' ],
        ];

        WP_CLI\Utils\format_items( 'table', $rows, [ 'Key', 'Value' ] );
    }

    /**
     * Run the GitHub poller immediately.
     */
    public function poll(): void {
        Routing::run_github_poller( true );
        WP_CLI::success( 'Poller completed. Check the queue for results.' );
    }

    /**
     * Inspect or clear the import queue.
     *
     * ## OPTIONS
     *
     * <action>
     * : Either `list` or `clear`.
     */
    public function queue( array $args ): void {
        $action = $args[0] ?? 'list';
        $queue  = get_option( 'hellaswiki_import_queue', [] );

        if ( 'clear' === $action ) {
            update_option( 'hellaswiki_import_queue', [] );
            update_option( 'hellaswiki_import_counter', 0 );
            WP_CLI::success( 'Queue cleared.' );
            return;
        }

        if ( empty( $queue ) ) {
            WP_CLI::log( 'Queue empty.' );
            return;
        }

        $rows = [];
        foreach ( $queue as $key => $item ) {
            $rows[] = [
                'Key'       => $key,
                'Path'      => $item['path'] ?? '—',
                'Post Type' => $item['post_type'] ?? '—',
                'Queued'    => isset( $item['timestamp'] ) ? wp_date( 'Y-m-d H:i', (int) $item['timestamp'] ) : '—',
            ];
        }

        WP_CLI\Utils\format_items( 'table', $rows, [ 'Key', 'Path', 'Post Type', 'Queued' ] );
    }

    /**
     * Import a JSON payload from a remote URL.
     *
     * ## OPTIONS
     *
     * --url=<url>
     * : Raw GitHub URL to fetch.
     *
     * [--type=<type>]
     * : Optional post type to force (e.g. wiki_species).
     */
    public function import( array $args, array $assoc_args ): void {
        $url = isset( $assoc_args['url'] ) ? esc_url_raw( (string) $assoc_args['url'] ) : '';

        if ( ! $url ) {
            WP_CLI::error( 'Please provide a --url parameter.' );
        }

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            WP_CLI::error( $response->get_error_message() );
        }

        $payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $payload ) ) {
            WP_CLI::error( 'Invalid JSON payload received.' );
        }

        $post_type = isset( $assoc_args['type'] ) ? sanitize_key( $assoc_args['type'] ) : '';

        if ( ! $post_type ) {
            $post_type = ImportController::detect_post_type_from_payload( $payload );
        }

        if ( ! $post_type ) {
            WP_CLI::error( 'Unable to determine post type. Provide --type to override.' );
        }

        $controller = new ImportController();
        $result     = $controller->import_payload( $payload, $post_type );

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }

        WP_CLI::success( sprintf( 'Imported %s (#%d).', $post_type, (int) $result ) );
    }

    /**
     * Create or update the main staging wiki page with the latest template.
     */
    public function create_main_page(): void {
        $page_id = (int) get_option( 'hellaswiki_staging_page', 0 );

        if ( $page_id && get_post( $page_id ) ) {
            wp_update_post(
                [
                    'ID'           => $page_id,
                    'post_title'   => 'Hellas Wiki (Staging)',
                    'post_content' => '[hellas_wiki_type_overview]',
                ]
            );
        } else {
            $page_id = wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => 'Hellas Wiki (Staging)',
                    'post_name'    => 'wiki-staging',
                    'post_content' => '[hellas_wiki_type_overview]',
                ]
            );
            update_option( 'hellaswiki_staging_page', $page_id, false );
        }

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', 'wiki-index.php' );
            WP_CLI::success( 'Staging wiki index ensured.' );
        } else {
            WP_CLI::error( 'Failed to create staging page.' );
        }
    }

    /**
     * Flush rewrite rules for wiki routes.
     */
    public function flush_rewrites(): void {
        flush_rewrite_rules( false );
        WP_CLI::success( 'Rewrite rules flushed.' );
    }
}
