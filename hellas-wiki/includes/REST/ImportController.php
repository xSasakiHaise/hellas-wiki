<?php

namespace HellasWiki\REST;

use HellasWiki\Logger;

use HellasWiki\Routing;
use HellasWiki\TypeRegistry;
use HellasWiki\Types\AbstractType;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API for importing wiki data.
 */
class ImportController extends WP_REST_Controller {
/**
 * Namespace.
 *
 * @var string
 */
protected $namespace = 'hellaswiki/v1';

/**
 * Register routes.
 */
public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/import',
            [
                [ 'methods' => 'POST', 'callback' => [ $this, 'handle_import' ], 'permission_callback' => [ $this, 'permissions_check' ] ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/import/parse',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_parse' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );
}

/**
 * Permission check.
 */
public function permissions_check(): bool {
return current_user_can( 'import_wiki_pages' );
}

/**
 * Handle import request.
 */
    public function handle_import( WP_REST_Request $request ) {
        $post_type = sanitize_key( $request['post_type'] ?? '' );
        $payload   = json_decode( (string) $request->get_body(), true );

        if ( ! $post_type ) {
            Logger::error( 'Import attempted without post type.' );
            return new WP_Error( 'hellaswiki_missing_type', __( 'Missing post type parameter.', 'hellas-wiki' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $payload ) ) {
            Logger::error( 'Import payload not array.', [ 'type' => $post_type ] );
            return new WP_Error( 'hellaswiki_invalid_payload', __( 'Invalid JSON payload.', 'hellas-wiki' ), [ 'status' => 400 ] );
        }

$result = $this->import_payload( $payload, $post_type );

if ( is_wp_error( $result ) ) {
return $result;
}

return new WP_REST_Response( [ 'created' => $result ], 201 );
}

/**
 * Import payload helper used by admin UI.
 *
 * @param array<string, mixed> $payload Payload.
 * @param string               $post_type Post type.
 */
public function import_payload( array $payload, string $post_type ) {
/** @var AbstractType|null $type */
$type = TypeRegistry::get( $post_type );

        if ( ! $type ) {
            Logger::error( 'Import failed: unknown type.', [ 'post_type' => $post_type ] );
            return new WP_Error( 'hellaswiki_unknown_type', __( 'Unknown post type.', 'hellas-wiki' ) );
        }

        $normalized = $type->normalize_payload( $payload );

        if ( is_wp_error( $normalized ) ) {
            Logger::error( 'Payload normalization failed.', [ 'post_type' => $post_type, 'error' => $normalized->get_error_code() ] );
            return $normalized;
        }

        $post_id = Routing::upsert_from_payload( $normalized );

        if ( ! $post_id ) {
            Logger::error( 'Import upsert failed.', [ 'post_type' => $post_type ] );
            return new WP_Error( 'hellaswiki_import_failed', __( 'Could not create wiki entry.', 'hellas-wiki' ) );
        }

        do_action( 'hellaswiki_after_import_create', $post_id, $normalized );

        Logger::info( 'Import successful.', [ 'post_id' => $post_id, 'post_type' => $post_type ] );

        return $post_id;
    }

    /**
     * Parse a payload without persisting.
     */
    public function handle_parse( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();
        $raw    = '';

        if ( isset( $params['payload'] ) ) {
            $raw = is_array( $params['payload'] ) ? wp_json_encode( $params['payload'] ) : (string) $params['payload'];
        }

        if ( ! $raw && ! empty( $params['url'] ) ) {
            $url      = esc_url_raw( (string) $params['url'] );
            $response = wp_remote_get( $url );

            if ( is_wp_error( $response ) ) {
                Logger::error( 'Parse test download failed.', [ 'url' => $url, 'error' => $response->get_error_message() ] );
                return new WP_REST_Response( [ 'error' => 'download_failed' ], 400 );
            }

            $raw = (string) wp_remote_retrieve_body( $response );
        }

        if ( ! $raw ) {
            return new WP_REST_Response( [ 'error' => 'missing_payload' ], 400 );
        }

        $payload = json_decode( $raw, true );

        if ( ! is_array( $payload ) ) {
            Logger::error( 'Parse test invalid JSON.' );
            return new WP_REST_Response( [ 'error' => 'invalid_json' ], 400 );
        }

        $detected = self::detect_post_type_from_payload( $payload );

        if ( ! $detected ) {
            return new WP_REST_Response( [ 'error' => 'unknown_type' ], 400 );
        }

        /** @var AbstractType|null $type */
        $type = TypeRegistry::get( $detected );

        if ( ! $type ) {
            return new WP_REST_Response( [ 'error' => 'type_not_registered' ], 400 );
        }

        $normalized = $type->normalize_payload( $payload );

        if ( is_wp_error( $normalized ) ) {
            return new WP_REST_Response(
                [
                    'error'   => $normalized->get_error_code(),
                    'message' => $normalized->get_error_message(),
                ],
                400
            );
        }

        $preview = [
            'post_type'    => $detected,
            'title'        => $normalized['post_title'] ?? '',
            'slug'         => $normalized['post_name'] ?? '',
            'meta'         => $normalized['meta'] ?? [],
            'tax'          => $normalized['tax'] ?? [],
            'description'  => $payload['description'] ?? $payload['flavourText'] ?? '',
        ];

        Logger::info( 'Parse test successful.', [ 'type' => $detected, 'title' => $preview['title'] ] );

        return new WP_REST_Response( $preview, 200 );
    }

    /**
     * Best-effort detection of target CPT from payload shape.
     *
     * @param array<string, mixed> $payload Payload data.
     */
    public static function detect_post_type_from_payload( array $payload ): ?string {
        if ( isset( $payload['baseStats'] ) || isset( $payload['types'] ) ) {
            return 'wiki_species';
        }

        if ( isset( $payload['formName'] ) || isset( $payload['baseForm'] ) ) {
            return 'wiki_form';
        }

        if ( isset( $payload['category'] ) && isset( $payload['type'] ) ) {
            return 'wiki_move';
        }

        if ( isset( $payload['effect'] ) && isset( $payload['name'] ) && isset( $payload['cooldown'] ) ) {
            return 'wiki_ability';
        }

        if ( isset( $payload['item'] ) || isset( $payload['price'] ) ) {
            return 'wiki_item';
        }

        if ( isset( $payload['region'] ) || isset( $payload['biomes'] ) ) {
            return 'wiki_location';
        }

        if ( isset( $payload['steps'] ) || isset( $payload['sections'] ) ) {
            return 'wiki_guide';
        }

        return null;
    }
}
