<?php

namespace HellasWiki;

/**
 * Handles custom capabilities for wiki management.
 */
class Capabilities {
/**
 * Map of roles => capabilities.
 *
 * @var array<string, array<string>>
 */
protected static array $role_map = [
'administrator' => [ 'edit_wiki_pages', 'publish_wiki_pages', 'import_wiki_pages' ],
'editor'        => [ 'edit_wiki_pages', 'publish_wiki_pages' ],
];

/**
 * Hook initialisation.
 */
public static function init(): void {
add_action( 'init', [ self::class, 'register_caps' ], 5 );
add_filter( 'user_has_cap', [ self::class, 'grant_meta_caps' ], 10, 3 );
}

/**
 * Register role capabilities on activation.
 */
public static function register_caps(): void {
foreach ( self::$role_map as $role_slug => $caps ) {
$role = get_role( $role_slug );
if ( ! $role ) {
continue;
}

foreach ( $caps as $cap ) {
$role->add_cap( $cap );
}
}
}

/**
 * Provide meta capabilities for REST operations.
 *
 * @param array<string, bool> $allcaps All capabilities.
 * @param string[]            $caps    Required caps.
 * @param array               $args    Context.
 */
public static function grant_meta_caps( array $allcaps, array $caps, array $args ): array {
if ( in_array( 'manage_hellas_wiki', $caps, true ) ) {
if ( ! empty( $allcaps['edit_wiki_pages'] ) ) {
$allcaps['manage_hellas_wiki'] = true;
}
}

return $allcaps;
}
}
