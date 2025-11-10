<?php

namespace HellasWiki\REST;

use HellasWiki\Logger;
use HellasWiki\Routing;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for manual poller utilities.
 */
class PollController extends WP_REST_Controller {
    /**
     * Namespace prefix.
     *
     * @var string
     */
    protected $namespace = 'hellaswiki/v1';

    /**
     * Register routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/poll',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_poll' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/flush-rewrites',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_flush_rewrites' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );
    }

    /**
     * Capability check.
     */
    public function permissions_check(): bool {
        return current_user_can( 'edit_wiki_pages' );
    }

    /**
     * Run the poller immediately.
     */
    public function handle_poll( WP_REST_Request $request ): WP_REST_Response {
        Logger::info( 'Manual poll trigger via REST.' );

        Routing::run_github_poller( true );

        $queue = get_option( 'hellaswiki_import_queue', [] );
        $count = is_array( $queue ) ? count( $queue ) : 0;

        return new WP_REST_Response(
            [
                'queued' => $count,
            ],
            200
        );
    }

    /**
     * Flush rewrite rules.
     */
    public function handle_flush_rewrites( WP_REST_Request $request ): WP_REST_Response {
        Logger::info( 'Manual rewrite flush requested.' );
        flush_rewrite_rules( false );

        return new WP_REST_Response( [ 'flushed' => true ], 200 );
    }
}
