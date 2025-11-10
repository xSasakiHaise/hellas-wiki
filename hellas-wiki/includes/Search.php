<?php

namespace HellasWiki;

/**
 * Handles search enhancements.
 */
class Search {
/**
 * Register hooks.
 */
public static function init(): void {
add_filter( 'pre_get_posts', [ self::class, 'include_wiki_post_types' ] );
}

/**
 * Include wiki types in search queries.
 */
public static function include_wiki_post_types( \WP_Query $query ): void {
if ( is_admin() || ! $query->is_main_query() ) {
return;
}

if ( $query->is_search ) {
$types = TypeRegistry::get_post_type_slugs();
$query->set( 'post_type', $types );
}
}
}
