<?php

namespace HellasWiki\REST;

use HellasWiki\Health;
use HellasWiki\TypeRegistry;
use WP_REST_Controller;
use WP_REST_Response;

/**
 * Provides health diagnostics.
 */
class HealthController extends WP_REST_Controller {
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
            '/health',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_health' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );
    }

    /**
     * Ensure caller can view diagnostics.
     */
    public function permissions_check(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Produce health payload.
     */
    public function get_health(): WP_REST_Response {
        $settings  = get_option( 'hellaswiki_settings', [] );
        $snapshot  = Health::get_status();
        $queue     = get_option( 'hellaswiki_import_queue', [] );
        $queue_cnt = is_array( $queue ) ? count( $queue ) : 0;

        $data = [
            'repo'                => $settings['github_repo'] ?? 'xSasakiHaise/hellasforms',
            'poller_enabled'      => ! empty( $settings['enable_poller'] ),
            'last_poll_at'        => $snapshot['last_poll_at'],
            'last_poll_result'    => $snapshot['last_poll_result'],
            'last_webhook_status' => $snapshot['last_webhook_status'],
            'last_webhook_at'     => $snapshot['last_webhook_at'],
            'webhook_history'     => $snapshot['webhook_history'],
            'queue_count'         => $queue_cnt,
            'cpts'                => TypeRegistry::get_post_type_slugs(),
            'routes_ok'           => (bool) $snapshot['routes_ok'],
            'token_scope'         => $snapshot['token_scope'],
            'cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
        ];

        return new WP_REST_Response( $data, 200 );
    }
}
