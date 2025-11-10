<?php
get_header();
?>
<main class="hellas-wiki">
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<header class="entry-header">
<h1 class="entry-title"><?php the_title(); ?></h1>
</header>

<?php echo do_shortcode( '[hellaswiki_infobox]' ); ?>

<div class="entry-content">
<?php the_content(); ?>

<section class="hellaswiki-meta">
<h2><?php esc_html_e( 'Affected Species', 'hellas-wiki' ); ?></h2>
<?php
$affects = (array) get_post_meta( get_the_ID(), 'item_affects', true );
echo \HellasWiki\Helpers::render_badges( $affects );
?>
</section>

<?php
echo \HellasWiki\Helpers::render_contextual_notes(
    get_the_ID(),
    'item',
    [ 'item_explanation_html' ],
    __( 'Item Notes', 'hellas-wiki' )
);
?>
</div>
</article>
</main>
<?php
get_footer();
