<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Species custom post type.
 */
class SpeciesType extends AbstractType {
public function get_slug(): string {
return 'wiki_species';
}

public function get_label(): string {
return __( 'Species', 'hellas-wiki' );
}

protected function register_taxonomies(): void {
if ( ! taxonomy_exists( 'wiki_typing' ) ) {
register_taxonomy(
'wiki_typing',
$this->get_slug(),
[
'label'        => __( 'Typing', 'hellas-wiki' ),
'hierarchical' => false,
'show_in_rest' => true,
]
);
}

if ( ! taxonomy_exists( 'wiki_generation' ) ) {
register_taxonomy(
'wiki_generation',
$this->get_slug(),
[
'label'        => __( 'Generation', 'hellas-wiki' ),
'hierarchical' => true,
'show_in_rest' => true,
]
);
}

if ( ! taxonomy_exists( 'wiki_rarity_tier' ) ) {
register_taxonomy(
'wiki_rarity_tier',
$this->get_slug(),
[
'label'        => __( 'Rarity Tier', 'hellas-wiki' ),
'hierarchical' => false,
'show_in_rest' => true,
]
);
}
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? $payload['species'] ?? '';

if ( ! $name ) {
return new WP_Error( 'hellaswiki_missing_name', __( 'Species payload missing name.', 'hellas-wiki' ) );
}

$types = $payload['types'] ?? [];
if ( ! is_array( $types ) ) {
$types = [];
}

$rarity = $payload['spawnInformation']['rarity'] ?? null;
$tier   = $this->determine_rarity_tier( $rarity );

$meta = [
'dex_number'      => $payload['dex'] ?? $payload['id'] ?? '',
'primary_type'    => $types[0] ?? '',
'secondary_type'  => $types[1] ?? '',
'base_stats'      => $payload['baseStats'] ?? [],
'abilities'       => $payload['abilities'] ?? [],
'evolution'       => $payload['evolutions'] ?? [],
'spawn_info'      => $payload['spawnInformation'] ?? [],
'drops'           => $payload['drops'] ?? [],
'namespace'       => $payload['namespace'] ?? '',
'image_url'       => $payload['artwork'] ?? '',
'sprite_url'      => $payload['sprite'] ?? '',
'rarity_tier'     => $tier,
];

$tax = [
'wiki_typing'      => array_filter( $types ),
'wiki_rarity_tier' => $tier ? [ $tier ] : [],
];

$content  = '';
$flavour  = $payload['flavourText'] ?? $payload['description'] ?? '';
if ( $flavour ) {
$content .= wpautop( sanitize_textarea_field( $flavour ) );
}

if ( ! empty( $payload['abilities'] ) ) {
$content .= "\n\n<h2>" . esc_html__( 'Abilities', 'hellas-wiki' ) . '</h2>';
$content .= '<ul class="hellaswiki-ability-list">';
foreach ( $payload['abilities'] as $ability ) {
if ( is_string( $ability ) ) {
$content .= '<li>[[' . esc_html( $ability ) . ']]</li>';
} elseif ( is_array( $ability ) && isset( $ability['ability'] ) ) {
$content .= '<li>[[' . esc_html( $ability['ability'] ) . ']]</li>';
}
}
$content .= '</ul>';
}

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => $content,
'post_name'    => sanitize_title( $name ),
'meta'         => $meta,
'tax'          => $tax,
];
}

/**
 * Determine rarity tier label.
 */
protected function determine_rarity_tier( $rarity ): string {
$rarity = floatval( $rarity );

if ( $rarity >= 60 ) {
return 'Common';
}

if ( $rarity >= 40 ) {
return 'Uncommon';
}

if ( $rarity >= 20 ) {
return 'Rare';
}

if ( $rarity >= 10 ) {
return 'Epic';
}

if ( $rarity >= 5 ) {
return 'Legendary';
}

return $rarity > 0 ? 'Mythic' : '';
}
}
