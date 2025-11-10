<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Handles alternate forms.
 */
class FormType extends AbstractType {
public function get_slug(): string {
return 'wiki_form';
}

public function get_label(): string {
return __( 'Forms', 'hellas-wiki' );
}

protected function register_taxonomies(): void {
register_taxonomy_for_object_type( 'wiki_typing', $this->get_slug() );
register_taxonomy_for_object_type( 'wiki_rarity_tier', $this->get_slug() );
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';
if ( ! $name ) {
return new WP_Error( 'hellaswiki_missing_name', __( 'Form payload missing name.', 'hellas-wiki' ) );
}

$meta = [
'dex_number'      => $payload['dex'] ?? '',
'form_key'        => $payload['form'] ?? $payload['identifier'] ?? '',
'primary_type'    => $payload['types'][0] ?? '',
'secondary_type'  => $payload['types'][1] ?? '',
'base_stats'      => $payload['baseStats'] ?? [],
'abilities'       => $payload['abilities'] ?? [],
'image_url'       => $payload['artwork'] ?? '',
'sprite_url'      => $payload['sprite'] ?? '',
'namespace'       => $payload['namespace'] ?? '',
'rarity_tier'     => $payload['rarity_tier'] ?? '',
];

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => wpautop( sanitize_textarea_field( $payload['description'] ?? '' ) ),
'post_name'    => sanitize_title( $name ),
'meta'         => $meta,
'tax'          => [ 'wiki_typing' => array_filter( $payload['types'] ?? [] ) ],
];
}
}
