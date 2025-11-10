<?php
/** @var WP_Post $post */
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

<?php echo do_shortcode( '[hellaswiki_stats]' ); ?>

<section class="hellaswiki-meta">
<h2><?php esc_html_e( 'Typing', 'hellas-wiki' ); ?></h2>
<?php
$types = array_filter( [ get_post_meta( get_the_ID(), 'primary_type', true ), get_post_meta( get_the_ID(), 'secondary_type', true ) ] );
echo \HellasWiki\Helpers::render_badges( $types );
?>
</section>

<section class="hellaswiki-meta">
<h2><?php esc_html_e( 'Abilities', 'hellas-wiki' ); ?></h2>
<?php
$abilities = (array) get_post_meta( get_the_ID(), 'abilities', true );
echo \HellasWiki\Helpers::render_badges( $abilities );
?>
</section>

<?php
echo \HellasWiki\Helpers::render_contextual_notes(
    get_the_ID(),
    'species',
    [ 'species_notes_html' ],
    __( 'Species Notes', 'hellas-wiki' )
);
?>
</div>
</article>
</main>
<?php
get_footer();
