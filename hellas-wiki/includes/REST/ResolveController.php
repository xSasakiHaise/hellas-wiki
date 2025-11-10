<?php

namespace HellasWiki\REST;

use HellasWiki\TypeRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Endpoint used by tooltips for quick summaries.
 */
class ResolveController extends WP_REST_Controller {
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
'/resolve',
[
'methods'             => 'GET',
'callback'            => [ $this, 'handle_resolve' ],
'permission_callback' => '__return_true',
]
);
}

/**
 * Resolve handler.
 */
public function handle_resolve( WP_REST_Request $request ): WP_REST_Response {
$slug  = sanitize_title( $request['slug'] ?? '' );
$type  = sanitize_key( $request['post_type'] ?? '' );

if ( empty( $slug ) ) {
return new WP_REST_Response( [], 200 );
}

$args = [
'name'        => $slug,
'post_status' => 'publish',
'posts_per_page' => 1,
];

if ( $type ) {
$args['post_type'] = $type;
} else {
$args['post_type'] = TypeRegistry::get_post_type_slugs();
}

$posts = get_posts( $args );

if ( empty( $posts ) ) {
return new WP_REST_Response( [], 200 );
}

$post_id = $posts[0]->ID;

$data = [
'id'    => $post_id,
'title' => get_the_title( $post_id ),
'link'  => get_permalink( $post_id ),
'fields' => [
'primary_type'  => get_post_meta( $post_id, 'primary_type', true ),
'rarity_tier'   => get_post_meta( $post_id, 'rarity_tier', true ),
'move_type'     => get_post_meta( $post_id, 'move_type', true ),
'move_power'    => get_post_meta( $post_id, 'move_power', true ),
'ability_effect'=> get_post_meta( $post_id, 'ability_effect_text', true ),
],
];

return new WP_REST_Response( $data, 200 );
}
}
