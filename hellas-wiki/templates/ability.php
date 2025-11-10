<?php
get_header();
?>
<main class="hellas-wiki">
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<header class="entry-header">
<h1 class="entry-title"><?php the_title(); ?></h1>
</header>

<div class="entry-content">
<?php the_content(); ?>
</div>

<?php
echo \HellasWiki\Helpers::render_contextual_notes(
    get_the_ID(),
    'ability',
    [ 'ability_explanation_html' ],
    __( 'Ability Notes', 'hellas-wiki' )
);
?>
</article>
</main>
<?php
get_footer();
