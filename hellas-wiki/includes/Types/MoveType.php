<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Custom move entries.
 */
class MoveType extends AbstractType {
public function get_slug(): string {
return 'wiki_move';
}

public function get_label(): string {
return __( 'Moves', 'hellas-wiki' );
}

protected function get_meta_fields(): array {
return parent::get_meta_fields() + [
'move_category'      => [],
'move_type'          => [],
'move_power'         => [],
'move_accuracy'      => [],
'move_pp'            => [],
'move_effect_text'   => [],
'move_recipients'    => [ 'type' => 'array' ],
];
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';
if ( ! $name ) {
return new WP_Error( 'hellaswiki_missing_name', __( 'Move payload missing name.', 'hellas-wiki' ) );
}

$meta = [
'move_category'    => $payload['category'] ?? '',
'move_type'        => $payload['type'] ?? '',
'move_power'       => $payload['power'] ?? '',
'move_accuracy'    => $payload['accuracy'] ?? '',
'move_pp'          => $payload['pp'] ?? '',
'move_effect_text' => $payload['effect'] ?? '',
'move_recipients'  => $payload['recipients'] ?? [],
'namespace'        => $payload['namespace'] ?? '',
];

$content  = wpautop( sanitize_textarea_field( $payload['effect'] ?? '' ) );
$content .= '<p class="hellaswiki-move-meta">' . esc_html__( 'Type:', 'hellas-wiki' ) . ' ' . esc_html( $meta['move_type'] ) . '</p>';

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => $content,
'post_name'    => sanitize_title( $name ),
'meta'         => $meta,
];
}
}
