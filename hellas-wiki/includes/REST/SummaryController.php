<?php

namespace HellasWiki\REST;

use HellasWiki\TypeRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Provides condensed summaries for tooltips.
 */
class SummaryController extends WP_REST_Controller {
    /**
     * Namespace prefix.
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
            '/summary',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_summary' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Summary handler.
     */
    public function handle_summary( WP_REST_Request $request ): WP_REST_Response {
        $type_param = sanitize_key( $request['type'] ?? '' );
        $post_id    = absint( $request['id'] ?? 0 );
        $slug       = sanitize_title( $request['slug'] ?? '' );

        $post_type = $this->map_type_to_post_type( $type_param );
        $post      = null;

        if ( $post_id ) {
            $candidate = get_post( $post_id );
            if ( $candidate && ( ! $post_type || $candidate->post_type === $post_type ) ) {
                $post = $candidate;
            }
        }

        if ( ! $post && $slug ) {
            $args = [
                'name'           => $slug,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'post_type'      => $post_type ?: TypeRegistry::get_post_type_slugs(),
            ];
            $posts = get_posts( $args );
            if ( $posts ) {
                $post = $posts[0];
            }
        }

        if ( ! $post ) {
            return new WP_REST_Response( [], 200 );
        }

        $fields = $this->collect_fields_for_post( $post->ID, $post->post_type );

        $data = [
            'id'     => $post->ID,
            'title'  => get_the_title( $post ),
            'link'   => get_permalink( $post ),
            'type'   => $post->post_type,
            'fields' => array_filter( $fields ),
        ];

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Map short type to registered post type.
     */
    protected function map_type_to_post_type( string $type ): string {
        $map = [
            'species' => 'wiki_species',
            'form'    => 'wiki_form',
            'move'    => 'wiki_move',
            'ability' => 'wiki_ability',
            'item'    => 'wiki_item',
            'location'=> 'wiki_location',
            'guide'   => 'wiki_guide',
        ];

        if ( isset( $map[ $type ] ) ) {
            return $map[ $type ];
        }

        if ( post_type_exists( $type ) ) {
            return $type;
        }

        return '';
    }

    /**
     * Gather tooltip fields for a post type.
     *
     * @return array<string, string>
     */
    protected function collect_fields_for_post( int $post_id, string $post_type ): array {
        switch ( $post_type ) {
            case 'wiki_move':
                return [
                    'Type'     => get_post_meta( $post_id, 'move_type', true ),
                    'Category' => get_post_meta( $post_id, 'move_category', true ),
                    'Power'    => get_post_meta( $post_id, 'move_power', true ),
                    'Accuracy' => get_post_meta( $post_id, 'move_accuracy', true ),
                ];
            case 'wiki_ability':
                return [
                    'Class'  => get_post_meta( $post_id, 'ability_effect_class', true ),
                    'Effect' => get_post_meta( $post_id, 'ability_effect_text', true ),
                ];
            case 'wiki_item':
                return [
                    'Category' => get_post_meta( $post_id, 'item_category', true ),
                    'Effect'   => get_post_meta( $post_id, 'item_effect_text', true ),
                ];
            case 'wiki_species':
                return [
                    'Primary Type'   => get_post_meta( $post_id, 'primary_type', true ),
                    'Secondary Type' => get_post_meta( $post_id, 'secondary_type', true ),
                    'Rarity'         => get_post_meta( $post_id, 'rarity_tier', true ),
                ];
            case 'wiki_form':
                return [
                    'Base Species' => get_post_meta( $post_id, 'base_species', true ),
                    'Form Type'    => get_post_meta( $post_id, 'form_type', true ),
                ];
            case 'wiki_location':
                return [
                    'Region' => get_post_meta( $post_id, 'location_region', true ),
                ];
            case 'wiki_guide':
                return [
                    'Updated' => get_post_modified_time( 'Y-m-d', false, $post_id ),
                ];
            default:
                return [];
        }
    }
}
