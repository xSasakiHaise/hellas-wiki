<?php

namespace HellasWiki\Admin;

/**
 * Dashboard widget displaying wiki stats.
 */
class DashboardWidget {
/**
 * Hook setup.
 */
public static function init(): void {
add_action( 'wp_dashboard_setup', [ self::class, 'register_widget' ] );
}

/**
 * Register widget.
 */
public static function register_widget(): void {
wp_add_dashboard_widget( 'hellaswiki_recent', __( 'Hellas Wiki Activity', 'hellas-wiki' ), [ self::class, 'render_widget' ] );
}

/**
 * Render widget contents.
 */
public static function render_widget(): void {
$queue = get_option( 'hellaswiki_import_queue', [] );
$recent = get_posts(
[
'post_type'      => [ 'wiki_species', 'wiki_form', 'wiki_move', 'wiki_item', 'wiki_ability' ],
'posts_per_page' => 5,
'post_status'    => [ 'publish', 'draft' ],
]
);

?>
<p><?php esc_html_e( 'Queue items waiting for review:', 'hellas-wiki' ); ?> <strong><?php echo number_format_i18n( count( $queue ) ); ?></strong></p>
<ul>
<?php foreach ( $recent as $post ) : ?>
<li>
<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
<span class="hellaswiki-status">(<?php echo esc_html( get_post_status( $post ) ); ?>)</span>
</li>
<?php endforeach; ?>
</ul>
<?php
}
}
