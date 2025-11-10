<?php

namespace HellasWiki\REST;

use HellasWiki\REST\GitHubWebhook;
use HellasWiki\Routing;
use HellasWiki\Types\AbstractType;
use HellasWiki\TypeRegistry;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for interacting with the import queue.
 */
class QueueController extends WP_REST_Controller {
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
'/queue',
[
'methods'             => 'GET',
'callback'            => [ $this, 'get_items' ],
'permission_callback' => [ $this, 'permissions_check' ],
]
);

register_rest_route(
$this->namespace,
'/queue/process',
[
'methods'             => 'POST',
'callback'            => [ $this, 'process_item' ],
'permission_callback' => [ $this, 'permissions_check' ],
]
);

register_rest_route(
$this->namespace,
'/queue/dismiss',
[
'methods'             => 'POST',
'callback'            => [ $this, 'dismiss_item' ],
'permission_callback' => [ $this, 'permissions_check' ],
]
);
}

/**
 * Capability check.
 */
public function permissions_check(): bool {
return current_user_can( 'import_wiki_pages' );
}

/**
 * Return queued items.
 */
public function get_items(): WP_REST_Response {
$queue = get_option( 'hellaswiki_import_queue', [] );

return new WP_REST_Response( array_values( $queue ), 200 );
}

/**
 * Process a queued item.
 */
public function process_item( WP_REST_Request $request ) {
$key    = sanitize_text_field( $request['key'] ?? '' );
$queue  = get_option( 'hellaswiki_import_queue', [] );
$item   = $queue[ $key ] ?? null;
$settings = get_option( 'hellaswiki_settings', [] );
$repo   = $settings['github_repo'] ?? '';
$token  = $settings['github_token'] ?? '';

if ( ! $item ) {
return new WP_Error( 'hellaswiki_missing_item', __( 'Queue item not found.', 'hellas-wiki' ), [ 'status' => 404 ] );
}

$content = '';

if ( ! empty( $repo ) && ! empty( $token ) ) {
$content = $this->download_github_file( $repo, $item['path'], $token );
}

if ( empty( $content ) ) {
return new WP_Error( 'hellaswiki_missing_content', __( 'Could not download file content.', 'hellas-wiki' ) );
}

$payload = json_decode( $content, true );

if ( ! is_array( $payload ) ) {
return new WP_Error( 'hellaswiki_invalid_json', __( 'Invalid JSON data.', 'hellas-wiki' ) );
}

/** @var AbstractType|null $type */
$type = TypeRegistry::get( $item['post_type'] );

if ( ! $type ) {
return new WP_Error( 'hellaswiki_unknown_type', __( 'Unknown content type.', 'hellas-wiki' ) );
}

$normalized = $type->normalize_payload( $payload );

if ( is_wp_error( $normalized ) ) {
return $normalized;
}

$post_id = Routing::upsert_from_payload( $normalized );

unset( $queue[ $key ] );

update_option( 'hellaswiki_import_queue', $queue );
update_option( 'hellaswiki_import_counter', count( $queue ) );

return new WP_REST_Response( [ 'post_id' => $post_id ], 200 );
}

/**
 * Dismiss queue item.
 */
public function dismiss_item( WP_REST_Request $request ) {
$key   = sanitize_text_field( $request['key'] ?? '' );
$queue = get_option( 'hellaswiki_import_queue', [] );

if ( isset( $queue[ $key ] ) ) {
unset( $queue[ $key ] );
}

update_option( 'hellaswiki_import_queue', $queue );
update_option( 'hellaswiki_import_counter', count( $queue ) );

return new WP_REST_Response( [ 'remaining' => count( $queue ) ], 200 );
}

/**
 * Download file from GitHub repo using contents API.
 */
protected function download_github_file( string $repo, string $path, string $token ): string {
list( $owner, $name ) = GitHubWebhook::split_repo( $repo );
$segments = array_map( 'rawurlencode', array_filter( explode( '/', $path ) ) );
$url      = sprintf( 'https://raw.githubusercontent.com/%s/%s/main/%s', rawurlencode( $owner ), rawurlencode( $name ), implode( '/', $segments ) );

$response = wp_remote_get(
$url,
[
'headers' => [
'Authorization' => 'token ' . $token,
'User-Agent'    => 'HellasWiki/1.0',
],
]
);

if ( is_wp_error( $response ) ) {
return '';
}

return (string) wp_remote_retrieve_body( $response );
}
}
