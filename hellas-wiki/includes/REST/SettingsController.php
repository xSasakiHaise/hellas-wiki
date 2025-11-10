<?php

namespace HellasWiki\REST;

use HellasWiki\Health;
use HellasWiki\Logger;

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
        $settings = wp_parse_args(
            get_option( 'hellaswiki_settings', [] ),
            [
                'github_repo'    => 'xSasakiHaise/hellasforms',
                'enable_poller'  => true,
                'github_token'   => '',
                'webhook_secret' => '',
            ]
        );
        $settings['releases_only'] = (bool) get_option( 'hellaswiki_updater_releases_only', 1 );

        Logger::info(
            'Settings fetched.',
            [
                'repo'          => $settings['github_repo'],
                'enable_poller' => ! empty( $settings['enable_poller'] ),
            ]
        );

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

        $this->detect_token_scope( $settings['github_token'] );

        Logger::info( 'Settings saved.', [ 'repo' => $settings['github_repo'], 'enable_poller' => $settings['enable_poller'] ] );

        return new WP_REST_Response( $settings, 200 );
    }

    /**
     * Detect GitHub token scopes for health display.
     */
    protected function detect_token_scope( string $token ): void {
        if ( empty( $token ) ) {
            Health::set_token_scope( null );
            return;
        }

        $response = wp_remote_head(
            'https://api.github.com/rate_limit',
            [
                'headers' => [
                    'Authorization' => 'token ' . $token,
                    'User-Agent'    => 'HellasWiki/1.0',
                    'Accept'        => 'application/vnd.github+json',
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'Failed to detect token scope.', [ 'error' => $response->get_error_message() ] );
            Health::set_token_scope( null );
            return;
        }

        $scopes = wp_remote_retrieve_header( $response, 'x-oauth-scopes' );
        $scope  = is_string( $scopes ) ? trim( $scopes ) : null;

        Health::set_token_scope( $scope ?: null );
    }
}
