<?php

namespace HellasWiki;

use HellasWiki\Types\AbstractType;

/**
 * Registry for wiki types.
 */
class TypeRegistry {
/**
 * Registered types.
 *
 * @var array<string, AbstractType>
 */
protected static array $types = [];

/**
 * Register hooks.
 */
public static function init(): void {
add_action( 'init', [ self::class, 'register_post_types' ] );
}

/**
 * Set the registry.
 *
 * @param array<string, AbstractType> $types Types.
 */
public static function register_types( array $types ): void {
self::$types = $types;
}

/**
 * Register CPTs + taxonomies.
 */
public static function register_post_types(): void {
foreach ( self::$types as $type ) {
$type->register();
}
}

/**
 * Get type by slug.
 */
public static function get( string $slug ): ?AbstractType {
return self::$types[ $slug ] ?? null;
}

/**
 * Retrieve post type slugs.
 *
 * @return string[]
 */
public static function get_post_type_slugs(): array {
return array_keys( self::$types );
}
}
