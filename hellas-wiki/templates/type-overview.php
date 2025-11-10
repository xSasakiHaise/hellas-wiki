<?php
/** Template Name: Hellas Wiki Type Overview */
get_header();
$types = get_terms(
[
'taxonomy'   => 'wiki_typing',
'hide_empty' => false,
]
);
$type_names = wp_list_pluck( $types, 'name' );
?>
<main class="hellas-wiki hellaswiki-type-overview">
<section class="hw-type-matrix">
<h1><?php esc_html_e( 'Type Effectiveness Overview', 'hellas-wiki' ); ?></h1>
<table>
<thead>
<tr>
<th><?php esc_html_e( 'Attacker', 'hellas-wiki' ); ?></th>
<?php foreach ( $type_names as $type ) : ?>
<th><?php echo esc_html( $type ); ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ( $type_names as $attacking ) : ?>
<tr>
<th><?php echo esc_html( $attacking ); ?></th>
<?php foreach ( $type_names as $defending ) : ?>
<td data-attack="<?php echo esc_attr( $attacking ); ?>" data-defense="<?php echo esc_attr( $defending ); ?>">&times;1</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

<section class="hw-type-cards">
<h2><?php esc_html_e( 'Type Index', 'hellas-wiki' ); ?></h2>
<div class="hw-grid">
<?php foreach ( $types as $type ) :
$count = $type->count;
$link  = get_term_link( $type );
?>
<article class="hw-card">
<h3><a href="<?php echo esc_url( add_query_arg( 'type', $type->slug, $link ) ); ?>"><?php echo esc_html( $type->name ); ?></a></h3>
<p><?php printf( esc_html__( '%d entries', 'hellas-wiki' ), intval( $count ) ); ?></p>
</article>
<?php endforeach; ?>
</div>
</section>
</main>
<?php
get_footer();
