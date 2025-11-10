<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Item CPT.
 */
class ItemType extends AbstractType {
public function get_slug(): string {
return 'wiki_item';
}

public function get_label(): string {
return __( 'Items', 'hellas-wiki' );
}

protected function get_meta_fields(): array {
return parent::get_meta_fields() + [
'item_category'    => [],
'item_effect_text' => [],
'item_affects'     => [ 'type' => 'array' ],
];
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';

if ( ! $name ) {
return new WP_Error( 'hellaswiki_missing_name', __( 'Item payload missing name.', 'hellas-wiki' ) );
}

$meta = [
'item_category'    => $payload['category'] ?? '',
'item_effect_text' => $payload['effect'] ?? '',
'item_affects'     => $payload['affects'] ?? [],
'icon_model_path'  => $payload['iconModelPath'] ?? '',
'namespace'        => $payload['namespace'] ?? '',
'image_url'        => $payload['image'] ?? '',
];

$content  = wpautop( sanitize_textarea_field( $meta['item_effect_text'] ) );
$content .= '<p class="hellaswiki-item-category">' . esc_html__( 'Category:', 'hellas-wiki' ) . ' ' . esc_html( $meta['item_category'] ) . '</p>';

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => $content,
'post_name'    => sanitize_title( $name ),
'meta'         => $meta,
];
}
}
