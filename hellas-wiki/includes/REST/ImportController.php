<?php

namespace HellasWiki\REST;

use HellasWiki\Routing;
use HellasWiki\TypeRegistry;
use HellasWiki\Types\AbstractType;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API for importing wiki data.
 */
class ImportController extends WP_REST_Controller {
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
'/import',
[
[ 'methods' => 'POST', 'callback' => [ $this, 'handle_import' ], 'permission_callback' => [ $this, 'permissions_check' ] ],
]
);
}

/**
 * Permission check.
 */
public function permissions_check(): bool {
return current_user_can( 'import_wiki_pages' );
}

/**
 * Handle import request.
 */
public function handle_import( WP_REST_Request $request ) {
$post_type = sanitize_key( $request['post_type'] ?? '' );
$payload   = json_decode( (string) $request->get_body(), true );

if ( ! $post_type ) {
return new WP_Error( 'hellaswiki_missing_type', __( 'Missing post type parameter.', 'hellas-wiki' ), [ 'status' => 400 ] );
}

if ( ! is_array( $payload ) ) {
return new WP_Error( 'hellaswiki_invalid_payload', __( 'Invalid JSON payload.', 'hellas-wiki' ), [ 'status' => 400 ] );
}

$result = $this->import_payload( $payload, $post_type );

if ( is_wp_error( $result ) ) {
return $result;
}

return new WP_REST_Response( [ 'created' => $result ], 201 );
}

/**
 * Import payload helper used by admin UI.
 *
 * @param array<string, mixed> $payload Payload.
 * @param string               $post_type Post type.
 */
public function import_payload( array $payload, string $post_type ) {
/** @var AbstractType|null $type */
$type = TypeRegistry::get( $post_type );

if ( ! $type ) {
return new WP_Error( 'hellaswiki_unknown_type', __( 'Unknown post type.', 'hellas-wiki' ) );
}

$normalized = $type->normalize_payload( $payload );

if ( is_wp_error( $normalized ) ) {
return $normalized;
}

$post_id = Routing::upsert_from_payload( $normalized );

if ( ! $post_id ) {
return new WP_Error( 'hellaswiki_import_failed', __( 'Could not create wiki entry.', 'hellas-wiki' ) );
}

do_action( 'hellaswiki_after_import_create', $post_id, $normalized );

return $post_id;
}
}
