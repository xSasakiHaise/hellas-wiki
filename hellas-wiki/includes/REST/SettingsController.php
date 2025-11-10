<?php

namespace HellasWiki\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Settings endpoint for storing GitHub credentials.
 */
class SettingsController extends WP_REST_Controller {
/**
 * Namespace.
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
'/settings',
[
[
'methods'             => 'GET',
'callback'            => [ $this, 'get_settings' ],
'permission_callback' => [ $this, 'permissions_check' ],
],
[
'methods'             => 'POST',
'callback'            => [ $this, 'save_settings' ],
'permission_callback' => [ $this, 'permissions_check' ],
],
]
);
}

/**
 * Register settings for admin UI.
 */
    public static function register_options(): void {
        register_setting( 'hellaswiki_settings', 'hellaswiki_settings' );
        register_setting(
            'hellaswiki_settings',
            'hellaswiki_updater_releases_only',
            [
                'type'              => 'boolean',
                'sanitize_callback' => static function ( $value ) {
                    return $value ? 1 : 0;
                },
                'default'           => 1,
            ]
        );
    }

/**
 * Permission check.
 */
public function permissions_check(): bool {
return current_user_can( 'manage_options' );
}

/**
 * Return settings.
 */
public function get_settings(): WP_REST_Response {
        $settings = get_option( 'hellaswiki_settings', [] );
        $settings['releases_only'] = (bool) get_option( 'hellaswiki_updater_releases_only', 1 );

        return new WP_REST_Response( $settings, 200 );
}

/**
 * Save settings.
 */
public function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $settings = wp_parse_args(
            $request->get_json_params(),
            [
                'github_repo'    => '',
                'github_token'   => '',
                'webhook_secret' => '',
                'enable_poller'  => false,
                'releases_only'  => true,
            ]
        );

        $settings['enable_poller'] = ! empty( $settings['enable_poller'] );
        $settings['releases_only'] = ! empty( $settings['releases_only'] );

        update_option( 'hellaswiki_settings', $settings );
        update_option( 'hellaswiki_updater_releases_only', $settings['releases_only'] ? 1 : 0 );
        UpdateController::clear_cached_check();

        return new WP_REST_Response( $settings, 200 );
    }
}
