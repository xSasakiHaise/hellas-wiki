<?php

namespace HellasWiki\Blocks;

/**
 * Renders stat tables for Pokemon species/forms.
 */
class StatTable {
/**
 * Register the shortcode.
 */
public static function register(): void {
add_shortcode( 'hellaswiki_stats', [ self::class, 'render_shortcode' ] );
}

/**
 * Render stats shortcode.
 */
public static function render_shortcode( array $atts = [] ): string {
$atts = shortcode_atts(
[
'post_id' => get_the_ID(),
],
$atts,
'hellaswiki_stats'
);

$post_id = intval( $atts['post_id'] );
$stats   = get_post_meta( $post_id, 'base_stats', true );

if ( empty( $stats ) || ! is_array( $stats ) ) {
return '';
}

$html  = '<table class="hellaswiki-stats">';
$html .= '<thead><tr><th>' . esc_html__( 'Stat', 'hellas-wiki' ) . '</th><th>' . esc_html__( 'Value', 'hellas-wiki' ) . '</th></tr></thead>';
$html .= '<tbody>';
foreach ( $stats as $stat => $value ) {
$html .= '<tr><td>' . esc_html( ucfirst( (string) $stat ) ) . '</td><td>' . esc_html( (string) $value ) . '</td></tr>';
}
$html .= '</tbody></table>';

return $html;
}
}
