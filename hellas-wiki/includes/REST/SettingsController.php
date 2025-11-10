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
]
);

update_option( 'hellaswiki_settings', $settings );

return new WP_REST_Response( $settings, 200 );
}
}
