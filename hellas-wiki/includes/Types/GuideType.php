<?php

namespace HellasWiki\Types;

/**
 * Guide CPT for walkthrough content.
 */
class GuideType extends AbstractType {
public function get_slug(): string {
return 'wiki_guide';
}

public function get_label(): string {
return __( 'Guides', 'hellas-wiki' );
}

public function normalize_payload( array $payload ) {
$name = $payload['name'] ?? '';

return [
'post_type'    => $this->get_slug(),
'post_title'   => $name,
'post_content' => wp_kses_post( $payload['content'] ?? '' ),
'post_name'    => sanitize_title( $name ),
'meta'         => $payload['meta'] ?? [],
];
}
}
