<?php

namespace HellasWiki\Types;

use WP_Error;

/**
 * Ability CPT.
 */
class AbilityType extends AbstractType {
public function get_slug(): string {
return 'wiki_ability';
}

public function get_label(): string {
return __( 'Abilities', 'hellas-wiki' );
}

protected function get_meta_fields(): array {
return parent::get_meta_fields() + [
'ability_effect_text' => [],
'ability_effect_class'=> [],
];
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';
if ( ! $name ) {
return new WP_Error( 'hellaswiki_missing_name', __( 'Ability payload missing name.', 'hellas-wiki' ) );
}

$meta = [
'ability_effect_text'  => $payload['effect'] ?? '',
'ability_effect_class' => $payload['class'] ?? ($payload['className'] ?? ''),
'namespace'            => $payload['namespace'] ?? '',
];

$content = wpautop( sanitize_textarea_field( $meta['ability_effect_text'] ) );

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => $content,
'post_name'    => sanitize_title( $name ),
'meta'         => $meta,
];
}
}
