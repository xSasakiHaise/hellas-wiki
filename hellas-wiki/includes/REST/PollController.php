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
                'permission_callback' => [ $this, 'can_poll' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/flush-rewrites',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_flush_rewrites' ],
                'permission_callback' => [ $this, 'can_flush' ],
            ]
        );
    }

    /**
     * Capability check.
     */
    public function permissions_check(): bool {
        _deprecated_function( __METHOD__, '1.2.0', __CLASS__ . '::can_poll' );

        return $this->can_poll();
    }

    /**
     * Capability check for polling.
     */
    public function can_poll(): bool {
        return current_user_can( 'edit_wiki_pages' );
    }

    /**
     * Capability check for flushing rewrite rules.
     */
    public function can_flush(): bool {
        return current_user_can( 'manage_options' );
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
