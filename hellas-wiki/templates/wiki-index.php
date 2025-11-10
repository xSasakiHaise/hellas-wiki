<?php
/** Template Name: Hellas Wiki Index */
get_header();

$latest_types = [
    'wiki_species'  => __( 'Latest Species', 'hellas-wiki' ),
    'wiki_form'     => __( 'Latest Forms', 'hellas-wiki' ),
    'wiki_move'     => __( 'Latest Moves', 'hellas-wiki' ),
    'wiki_ability'  => __( 'Latest Abilities', 'hellas-wiki' ),
    'wiki_item'     => __( 'Latest Items', 'hellas-wiki' ),
    'wiki_guide'    => __( 'Latest Guides', 'hellas-wiki' ),
];
?>
<main class="hellas-wiki hellaswiki-index">
    <section class="hw-hero">
        <h1><?php esc_html_e( 'Hellas Wiki Staging', 'hellas-wiki' ); ?></h1>
        <p><?php esc_html_e( 'Review the latest encyclopaedia entries before they go live.', 'hellas-wiki' ); ?></p>
        <a class="hw-button" href="#type-overview"><?php esc_html_e( 'View Type Overview', 'hellas-wiki' ); ?></a>
    </section>

    <section class="hw-latest-grid">
        <?php foreach ( $latest_types as $post_type => $label ) :
            $entries = get_posts(
                [
                    'post_type'      => $post_type,
                    'posts_per_page' => 4,
                    'post_status'    => 'publish',
                    'orderby'        => 'modified',
                ]
            );

            if ( empty( $entries ) ) {
                continue;
            }
            ?>
            <article class="hw-card">
                <h2><?php echo esc_html( $label ); ?></h2>
                <ul>
                    <?php foreach ( $entries as $entry ) : ?>
                        <li><a href="<?php echo esc_url( get_permalink( $entry ) ); ?>"><?php echo esc_html( get_the_title( $entry ) ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </section>

    <section id="type-overview" class="hw-type-overview">
        <?php echo do_shortcode( '[hellas_wiki_type_overview]' ); ?>
    </section>
</main>
<?php
get_footer();
