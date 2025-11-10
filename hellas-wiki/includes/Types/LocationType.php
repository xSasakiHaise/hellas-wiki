<?php

namespace HellasWiki\Types;

/**
 * Manual location entries.
 */
class LocationType extends AbstractType {
public function get_slug(): string {
return 'wiki_location';
}

public function get_label(): string {
return __( 'Locations', 'hellas-wiki' );
}

protected function get_meta_fields(): array {
return parent::get_meta_fields() + [
'location_region' => [],
'location_level' => [],
];
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => wpautop( sanitize_textarea_field( $payload['description'] ?? '' ) ),
'post_name'    => sanitize_title( $name ),
'meta'         => $payload['meta'] ?? [],
];
}
}
