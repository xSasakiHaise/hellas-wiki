<?php

namespace HellasWiki\REST;

use HellasWiki\TypeRegistry;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exports wiki posts as JSON payloads.
 */
class ExportController extends WP_REST_Controller {
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
'/export/(?P<id>\d+)',
[
'methods'             => 'GET',
'callback'            => [ $this, 'handle_export' ],
'permission_callback' => [ $this, 'permissions_check' ],
]
);
}

/**
 * Ensure the user can export.
 */
public function permissions_check(): bool {
return current_user_can( 'edit_wiki_pages' );
}

/**
 * Export handler.
 */
public function handle_export( WP_REST_Request $request ) {
$post_id = intval( $request['id'] );
$post    = get_post( $post_id );

if ( ! $post ) {
return new WP_Error( 'hellaswiki_not_found', __( 'Post not found.', 'hellas-wiki' ), [ 'status' => 404 ] );
}

$type = TypeRegistry::get( $post->post_type );

if ( ! $type ) {
return new WP_Error( 'hellaswiki_unknown_type', __( 'Unsupported type.', 'hellas-wiki' ), [ 'status' => 400 ] );
}

$data = $type->export( $post_id );

return new WP_REST_Response( $data, 200 );
}
}
