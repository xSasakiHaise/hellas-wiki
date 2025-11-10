<?php

namespace HellasWiki\Blocks;

use HellasWiki\Helpers;

/**
 * Registers the infobox shortcode/block.
 */
class Infobox {
/**
 * Register block + shortcode.
 */
public static function register(): void {
add_shortcode( 'hellaswiki_infobox', [ self::class, 'render_shortcode' ] );
}

/**
 * Render shortcode content.
 */
public static function render_shortcode( array $atts = [] ): string {
$atts = shortcode_atts(
[
'post_id' => get_the_ID(),
],
$atts,
'hellaswiki_infobox'
);

$post_id = intval( $atts['post_id'] );
$post    = get_post( $post_id );

if ( ! $post ) {
return '';
}

$meta = static function ( string $key ) use ( $post_id ) {
return get_post_meta( $post_id, $key, true );
};

$image = 'wiki_item' === $post->post_type ? Helpers::resolve_item_image( $post_id ) : Helpers::resolve_species_image( $post_id );

$fields = [
__( 'Primary Type', 'hellas-wiki' )   => $meta( 'primary_type' ),
__( 'Secondary Type', 'hellas-wiki' ) => $meta( 'secondary_type' ),
__( 'Rarity Tier', 'hellas-wiki' )    => $meta( 'rarity_tier' ),
__( 'Dex Number', 'hellas-wiki' )     => $meta( 'dex_number' ),
];

$rows = '';
foreach ( $fields as $label => $value ) {
if ( empty( $value ) ) {
continue;
}

$rows .= '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
}

$html  = '<div class="hellaswiki-infobox">';
$html .= '<aside class="hw-figure"><figure>';
$html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" loading="lazy" decoding="async" />';
$html .= '<figcaption>' . esc_html( $meta( 'primary_type' ) ?: $meta( 'item_category' ) ) . '</figcaption>';
$html .= '</figure></aside>';
$html .= '<table>' . $rows . '</table>';
$html .= '</div>';

return apply_filters( 'hellaswiki_render_infobox', $html, $post_id );
}
}
