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
</div>

<?php
echo \HellasWiki\Helpers::render_contextual_notes(
    get_the_ID(),
    'species',
    [ 'species_notes_html' ],
    __( 'Form Notes', 'hellas-wiki' )
);
?>
</article>
</main>
<?php
get_footer();
