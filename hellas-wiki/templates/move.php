<?php
get_header();
?>
<main class="hellas-wiki">
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<header class="entry-header">
<h1 class="entry-title"><?php the_title(); ?></h1>
</header>

<div class="hellaswiki-move-summary">
<ul class="hw-badges">
<li><?php echo esc_html( get_post_meta( get_the_ID(), 'move_type', true ) ); ?></li>
<li><?php echo esc_html( get_post_meta( get_the_ID(), 'move_category', true ) ); ?></li>
<li><?php printf( esc_html__( 'Power %s', 'hellas-wiki' ), esc_html( get_post_meta( get_the_ID(), 'move_power', true ) ) ); ?></li>
<li><?php printf( esc_html__( 'Accuracy %s', 'hellas-wiki' ), esc_html( get_post_meta( get_the_ID(), 'move_accuracy', true ) ) ); ?></li>
</ul>
</div>

<div class="entry-content">
<?php the_content(); ?>
</div>

<?php
echo \HellasWiki\Helpers::render_contextual_notes(
    get_the_ID(),
    'move',
    [ 'move_explanation_html' ],
    __( 'Move Notes', 'hellas-wiki' )
);
?>
</article>
</main>
<?php
get_footer();
