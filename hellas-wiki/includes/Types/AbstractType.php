<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Base class for wiki custom post types.
 */
abstract class AbstractType {
/**
 * Post type slug.
 */
abstract public function get_slug(): string;

/**
 * Label for admin UI.
 */
abstract public function get_label(): string;

/**
 * Register CPT + meta.
 */
public function register(): void {
register_post_type(
$this->get_slug(),
[
'label'        => $this->get_label(),
'public'       => true,
'show_in_rest' => true,
'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
'has_archive'  => true,
'rewrite'      => [ 'slug' => str_replace( 'wiki_', '', $this->get_slug() ) ],
'show_in_menu' => 'hellas-wiki',
'capability_type' => 'wiki_page',
'map_meta_cap' => true,
'capabilities' => [
'edit_post'          => 'edit_wiki_pages',
'read_post'          => 'edit_wiki_pages',
'delete_post'        => 'edit_wiki_pages',
'edit_posts'         => 'edit_wiki_pages',
'edit_others_posts'  => 'edit_wiki_pages',
'publish_posts'      => 'publish_wiki_pages',
'read_private_posts'  => 'edit_wiki_pages',
'delete_posts'        => 'edit_wiki_pages',
'edit_published_posts'=> 'edit_wiki_pages',
'delete_published_posts'=> 'edit_wiki_pages',
],
]
);

$this->register_taxonomies();
$this->register_meta();
}

/**
 * Taxonomies.
 */
protected function register_taxonomies(): void {}

/**
 * Register meta fields.
 */
protected function register_meta(): void {
$fields = $this->get_meta_fields();

foreach ( $fields as $field => $args ) {
register_post_meta( $this->get_slug(), $field, wp_parse_args( $args, [
'show_in_rest' => true,
'single'       => true,
'type'         => 'string',
] ) );
}
}

/**
 * Meta field definitions.
 *
 * @return array<string, array<string, mixed>>
 */
protected function get_meta_fields(): array {
return [
'dex_number'         => [],
'form_key'           => [],
'primary_type'       => [],
'secondary_type'     => [],
'base_stats'         => [ 'type' => 'array' ],
'abilities'          => [ 'type' => 'array' ],
'evolution'          => [ 'type' => 'array' ],
'spawn_info'         => [ 'type' => 'array' ],
'drops'              => [ 'type' => 'array' ],
'source_tags'        => [ 'type' => 'array' ],
'namespace'          => [],
'related_items'      => [ 'type' => 'array' ],
'related_species'    => [ 'type' => 'array' ],
'image_url'          => [],
'sprite_url'         => [],
'icon_model_path'    => [],
'rarity_tier'        => [],
];
}

/**
 * Prefill metadata for wizard.
 */
public function prefill_meta( int $post_id, string $identifier ): void {
if ( $identifier ) {
update_post_meta( $post_id, 'dex_number', $identifier );
}
}

/**
 * Normalise payload before creation.
 *
 * @param array<string, mixed> $payload Payload.
 *
 * @return array<string, mixed>|WP_Error
 */
abstract public function normalize_payload( array $payload );

/**
 * Create or update post using payload.
 *
 * @param array<string, mixed> $payload Payload.
 */
public function upsert_from_payload( array $payload ): int {
$post_id = $this->find_existing_post( $payload );

$args = [
'post_type'   => $this->get_slug(),
'post_status' => 'publish',
'post_title'  => $payload['post_title'] ?? '',
'post_name'   => $payload['post_name'] ?? sanitize_title( $payload['post_title'] ?? '' ),
'post_content'=> $payload['post_content'] ?? '',
];

if ( $post_id ) {
$args['ID'] = $post_id;
$post_id    = wp_update_post( $args, true );
} else {
$post_id = wp_insert_post( $args, true );
}

if ( is_wp_error( $post_id ) ) {
return 0;
}

foreach ( $payload['meta'] ?? [] as $key => $value ) {
update_post_meta( $post_id, $key, $value );
}

if ( ! empty( $payload['tax'] ) ) {
foreach ( $payload['tax'] as $taxonomy => $terms ) {
wp_set_object_terms( $post_id, $terms, $taxonomy );
}
}

return (int) $post_id;
}

/**
 * Attempt to find existing post.
 *
 * @param array<string, mixed> $payload Payload.
 */
protected function find_existing_post( array $payload ): int {
if ( ! empty( $payload['meta']['dex_number'] ) ) {
$post = get_posts(
[
'post_type'      => $this->get_slug(),
'posts_per_page' => 1,
'meta_key'       => 'dex_number',
'meta_value'     => $payload['meta']['dex_number'],
]
);

if ( $post ) {
return (int) $post[0]->ID;
}
}

return 0;
}

/**
 * Export data for REST.
 */
public function export( int $post_id ): array {
return [
'post' => get_post( $post_id ),
'meta' => get_post_meta( $post_id ),
];
}
}
