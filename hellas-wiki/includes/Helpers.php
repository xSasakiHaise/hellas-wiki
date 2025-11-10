<?php

namespace HellasWiki;

/**
 * Generic helpers for template rendering and parsing.
 */
class Helpers {
/**
 * Hook initialisation.
 */
public static function init(): void {
add_filter( 'single_template', [ self::class, 'load_single_template' ] );
add_filter( 'the_content', [ self::class, 'auto_link_content' ], 20 );
}

/**
 * Load custom template for wiki post types.
 *
 * @param string $template Template path.
 */
public static function load_single_template( string $template ): string {
$post = get_queried_object();

if ( ! $post instanceof \WP_Post ) {
return $template;
}

$type = $post->post_type;

$map = [
'wiki_species'  => 'species.php',
'wiki_form'     => 'form.php',
'wiki_move'     => 'move.php',
'wiki_ability'  => 'ability.php',
'wiki_item'     => 'item.php',
'wiki_location' => 'location.php',
'wiki_guide'    => 'guide.php',
];

if ( isset( $map[ $type ] ) ) {
$custom = HELLAS_WIKI_PATH . 'templates/' . $map[ $type ];

if ( file_exists( $custom ) ) {
return $custom;
}
}

return $template;
}

/**
 * Resolve a species image URL from meta values.
 */
public static function resolve_species_image( int $post_id ): string {
$image = get_post_meta( $post_id, 'image_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

$image = get_post_meta( $post_id, 'sprite_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

/**
 * Allow themes to resolve textures dynamically.
 */
$asset = apply_filters( 'hellaswiki_map_asset_to_url', null, $post_id, 'species' );
if ( $asset ) {
return esc_url_raw( $asset );
}

        return HELLAS_WIKI_URL . 'assets/placeholder-species.svg';
}

/**
 * Resolve an item image URL from meta values.
 */
public static function resolve_item_image( int $post_id ): string {
$image = get_post_meta( $post_id, 'image_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

$icon = get_post_meta( $post_id, 'icon_model_path', true );
if ( $icon ) {
$url = apply_filters( 'hellaswiki_map_asset_to_url', $icon, $post_id, 'item' );
if ( $url ) {
return esc_url_raw( $url );
}
}

        return HELLAS_WIKI_URL . 'assets/placeholder-item.svg';
}

/**
 * Converts associative arrays to HTML badges.
 *
 * @param array<int|string, string> $items Items to render.
 */
public static function render_badges( array $items ): string {
$items = array_filter( array_map( 'trim', $items ) );

if ( empty( $items ) ) {
return '';
}

$html = '<ul class="hw-badges">';

foreach ( $items as $item ) {
$html .= '<li>' . esc_html( $item ) . '</li>';
}

$html .= '</ul>';

return $html;
}

/**
 * Auto link [[Name]] references to wiki entries.
 */
public static function auto_link_content( string $content ): string {
return preg_replace_callback(
'/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/u',
static function ( array $matches ) {
$target = trim( $matches[1] );
$label  = trim( $matches[2] ?? $target );
if ( ! $target ) {
return $matches[0];
}

$post = get_page_by_path( sanitize_title( $target ), OBJECT, TypeRegistry::get_post_type_slugs() );
if ( ! $post ) {
return '<span class="hellaswiki-redlink" data-missing="' . esc_attr( $target ) . '">' . esc_html( $label ) . '</span>';
}

return '<a href="' . esc_url( get_permalink( $post ) ) . '" data-hellaswiki="1" data-slug="' . esc_attr( $post->post_name ) . '" data-post-type="' . esc_attr( $post->post_type ) . '">' . esc_html( $label ) . '</a>';
},
$content
);
}
}
