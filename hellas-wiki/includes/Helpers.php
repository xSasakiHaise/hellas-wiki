<?php

namespace HellasWiki;

use HellasWiki\Blocks\TextSection;
use HellasWiki\TypeRegistry;

/**
 * Generic helpers for template rendering and parsing.
 */
class Helpers {
    /**
     * Cached type chart payload.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $type_chart = null;

    /**
     * Hook initialisation.
     */
    public static function init(): void {
        add_filter( 'single_template', [ self::class, 'load_single_template' ] );
        add_filter( 'the_content', [ self::class, 'auto_link_content' ], 20 );
        add_shortcode( 'hellas_wiki_type_overview', [ self::class, 'render_type_overview_shortcode' ] );
        add_action( 'admin_notices', [ self::class, 'maybe_show_type_overview_notice' ] );
        add_action( 'admin_post_hellaswiki_create_type_overview', [ self::class, 'handle_create_type_overview' ] );
    }

/**
 * Load custom template for wiki post types.
 *
 * @param string $template Template path.
 */
public static function load_single_template( string $template ): string {
$post = get_queried_object();

if ( ! $post instanceof \WP_Post ) {
return $template;
}

$type = $post->post_type;

$map = [
'wiki_species'  => 'species.php',
'wiki_form'     => 'form.php',
'wiki_move'     => 'move.php',
'wiki_ability'  => 'ability.php',
'wiki_item'     => 'item.php',
'wiki_location' => 'location.php',
'wiki_guide'    => 'guide.php',
];

if ( isset( $map[ $type ] ) ) {
$custom = HELLAS_WIKI_PATH . 'templates/' . $map[ $type ];

if ( file_exists( $custom ) ) {
return $custom;
}
}

return $template;
}

/**
 * Resolve a species image URL from meta values.
 */
public static function resolve_species_image( int $post_id ): string {
$image = get_post_meta( $post_id, 'image_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

$image = get_post_meta( $post_id, 'sprite_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

/**
 * Allow themes to resolve textures dynamically.
 */
$asset = apply_filters( 'hellaswiki_map_asset_to_url', null, $post_id, 'species' );
if ( $asset ) {
return esc_url_raw( $asset );
}

        return HELLAS_WIKI_URL . 'assets/placeholder-species.svg';
}

/**
 * Resolve an item image URL from meta values.
 */
public static function resolve_item_image( int $post_id ): string {
$image = get_post_meta( $post_id, 'image_url', true );
if ( $image ) {
return esc_url_raw( $image );
}

$icon = get_post_meta( $post_id, 'icon_model_path', true );
if ( $icon ) {
$url = apply_filters( 'hellaswiki_map_asset_to_url', $icon, $post_id, 'item' );
if ( $url ) {
return esc_url_raw( $url );
}
}

        return HELLAS_WIKI_URL . 'assets/placeholder-item.svg';
}

/**
 * Converts associative arrays to HTML badges.
 *
 * @param array<int|string, string> $items Items to render.
 */
    public static function render_badges( array $items ): string {
        $items = array_filter( array_map( 'trim', $items ) );

        if ( empty( $items ) ) {
            return '';
        }

        $html = '<ul class="hw-badges">';

        foreach ( $items as $item ) {
            $html .= '<li>' . esc_html( $item ) . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render the type overview shortcode.
     */
    public static function render_type_overview_shortcode(): string {
        $data       = self::get_type_chart();
        $type_names = $data['types'];
        $matrix     = $data['chart'];

        $terms = get_terms(
            [
                'taxonomy'   => 'wiki_typing',
                'hide_empty' => false,
            ]
        );

        if ( empty( $type_names ) && ! empty( $terms ) ) {
            $type_names = wp_list_pluck( $terms, 'name' );
        }

        ob_start();
        ?>
        <div class="hellaswiki-type-overview-shortcode">
            <div class="hw-type-matrix">
                <table>
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Attacker', 'hellas-wiki' ); ?></th>
                            <?php foreach ( $type_names as $type ) : ?>
                                <th><?php echo esc_html( $type ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $type_names as $attacking ) : ?>
                            <tr>
                                <th><?php echo esc_html( $attacking ); ?></th>
                                <?php foreach ( $type_names as $defending ) :
                                    $value = $matrix[ $attacking ][ $defending ] ?? 1;
                                ?>
                                    <td data-attack="<?php echo esc_attr( $attacking ); ?>" data-defense="<?php echo esc_attr( $defending ); ?>">
                                        &times;<?php echo esc_html( $value ); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( ! empty( $terms ) ) : ?>
                <div class="hw-type-cards">
                    <div class="hw-grid">
                        <?php foreach ( $terms as $term ) : ?>
                            <article class="hw-card">
                                <h3><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h3>
                                <p><?php printf( esc_html__( '%d entries', 'hellas-wiki' ), intval( $term->count ) ); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Retrieve type chart data from JSON asset.
     *
     * @return array{types: string[], chart: array<string, array<string, float|int>>}
     */
    public static function get_type_chart(): array {
        if ( null !== self::$type_chart ) {
            return self::$type_chart;
        }

        $file = HELLAS_WIKI_PATH . 'assets/type_chart.json';

        if ( file_exists( $file ) ) {
            $contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $decoded  = json_decode( $contents ?: '', true );

            if ( is_array( $decoded ) ) {
                $types = [];

                if ( ! empty( $decoded['types'] ) && is_array( $decoded['types'] ) ) {
                    $types = array_map( 'sanitize_text_field', $decoded['types'] );
                }

                $chart = [];

                if ( ! empty( $decoded['chart'] ) && is_array( $decoded['chart'] ) ) {
                    foreach ( $decoded['chart'] as $attacking => $rows ) {
                        if ( ! is_array( $rows ) ) {
                            continue;
                        }
                        foreach ( $rows as $defending => $value ) {
                            $chart[ $attacking ][ $defending ] = floatval( $value );
                        }
                    }
                }

                self::$type_chart = [
                    'types' => $types,
                    'chart' => $chart,
                ];

                return self::$type_chart;
            }
        }

        self::$type_chart = [
            'types' => [],
            'chart' => [],
        ];

        return self::$type_chart;
    }

    /**
     * Maybe show admin notice if no type overview page is present.
     */
    public static function maybe_show_type_overview_notice(): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        if ( isset( $_GET['hellaswiki_type_notice'] ) && 'dismissed' === $_GET['hellaswiki_type_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            update_option( 'hellaswiki_type_notice_dismissed', 1 );
        }

        if ( get_option( 'hellaswiki_type_notice_dismissed' ) ) {
            return;
        }

        global $wpdb;
        $pattern = '%[hellas_wiki_type_overview%';
        $query   = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft') AND post_type IN ('page','wiki_species','wiki_form','wiki_move','wiki_ability','wiki_item','wiki_location','wiki_guide') AND post_content LIKE %s LIMIT 1", $pattern );
        $found   = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( $found ) {
            return;
        }

        $action_url = wp_nonce_url( admin_url( 'admin-post.php?action=hellaswiki_create_type_overview' ), 'hellaswiki_create_type_overview' );
        $dismiss    = esc_url( add_query_arg( 'hellaswiki_type_notice', 'dismissed' ) );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e( 'No Type Overview page detected. Create one to surface type effectiveness data.', 'hellas-wiki' ); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Create Type Overview', 'hellas-wiki' ); ?></a>
                <a class="button" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'hellas-wiki' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle creation of type overview page or template injection.
     */
    public static function handle_create_type_overview(): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'hellas-wiki' ) );
        }

        check_admin_referer( 'hellaswiki_create_type_overview' );

        $page_id = (int) get_option( 'hellaswiki_staging_page', 0 );

        if ( $page_id && get_post( $page_id ) ) {
            $content = get_post_field( 'post_content', $page_id );
            if ( false === strpos( $content, '[hellas_wiki_type_overview]' ) ) {
                $content .= "\n\n[hellas_wiki_type_overview]";
                wp_update_post(
                    [
                        'ID'           => $page_id,
                        'post_content' => $content,
                    ]
                );
            }
            update_post_meta( $page_id, '_wp_page_template', 'wiki-index.php' );
        } else {
            $page_id = wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => __( 'Type Overview', 'hellas-wiki' ),
                    'post_name'    => 'types',
                    'post_content' => '[hellas_wiki_type_overview]',
                ]
            );

            if ( ! is_wp_error( $page_id ) ) {
                update_post_meta( $page_id, '_wp_page_template', 'type-overview.php' );
            }
        }

        update_option( 'hellaswiki_type_notice_dismissed', 1 );

        wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
        exit;
    }

/**
 * Auto link [[Name]] references to wiki entries.
 */
    public static function auto_link_content( string $content ): string {
        return preg_replace_callback(
            '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/u',
            static function ( array $matches ) {
                $target = trim( $matches[1] );
                $label  = trim( $matches[2] ?? $target );
                if ( ! $target ) {
                    return $matches[0];
                }

                $post = get_page_by_path( sanitize_title( $target ), OBJECT, TypeRegistry::get_post_type_slugs() );
                if ( ! $post ) {
                    $attributes = 'class="hellaswiki-redlink" data-missing="' . esc_attr( $target ) . '"';
                    if ( current_user_can( 'edit_wiki_pages' ) ) {
                        $attributes .= ' data-wizard-type="wiki_species"';
                        $attributes .= ' data-wizard-label="' . esc_attr( $target ) . '"';
                        $wizard_url = admin_url( 'admin.php?page=hellas-wiki&hellaswiki_name=' . rawurlencode( $target ) );
                        return '<a ' . $attributes . ' href="' . esc_url( $wizard_url ) . '">' . esc_html( $label ) . '</a>';
                    }

                    return '<span ' . $attributes . '>' . esc_html( $label ) . '</span>';
                }

                return '<a href="' . esc_url( get_permalink( $post ) ) . '" data-hellaswiki="1" data-id="' . esc_attr( $post->ID ) . '" data-slug="' . esc_attr( $post->post_name ) . '" data-post-type="' . esc_attr( $post->post_type ) . '">' . esc_html( $label ) . '</a>';
            },
            $content
        );
    }

    /**
     * Determine if a text section block exists for contexts.
     *
     * @param string[] $contexts Context identifiers.
     */
    public static function has_text_section_context( int $post_id, array $contexts ): bool {
        $blocks = parse_blocks( get_post_field( 'post_content', $post_id ) );
        return self::scan_blocks_for_context( $blocks, array_map( 'sanitize_key', $contexts ) );
    }

    /**
     * Render fallback content if no block covers the context.
     *
     * @param string[] $meta_keys Meta keys to inspect for HTML fallback.
     */
    public static function render_contextual_notes( int $post_id, string $context, array $meta_keys, string $title ): string {
        if ( self::has_text_section_context( $post_id, [ $context ] ) ) {
            return '';
        }

        foreach ( $meta_keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );

            if ( empty( $value ) ) {
                continue;
            }

            $classes = [ 'hellaswiki-text-section', 'context-' . sanitize_html_class( $context ) ];
            $html    = '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">';
            if ( $title ) {
                $html .= '<h3 class="hellaswiki-text-section__title">' . esc_html( $title ) . '</h3>';
            }
            $html .= '<div class="hellaswiki-text-section__body">' . wp_kses_post( $value ) . '</div>';
            $html .= '</section>';

            return $html;
        }

        return '';
    }

    /**
     * Recursively scan parsed blocks for matching context.
     *
     * @param array<int, array<string, mixed>> $blocks Parsed blocks.
     * @param string[]                          $contexts Contexts to match.
     */
    protected static function scan_blocks_for_context( array $blocks, array $contexts ): bool {
        if ( empty( $blocks ) ) {
            return false;
        }

        foreach ( $blocks as $block ) {
            $name = $block['blockName'] ?? '';

            if ( ! $name ) {
                if ( ! empty( $block['innerBlocks'] ) && self::scan_blocks_for_context( $block['innerBlocks'], $contexts ) ) {
                    return true;
                }
                continue;
            }

            $context = self::resolve_block_context( $name, $block['attrs'] ?? [] );

            if ( $context && in_array( $context, $contexts, true ) ) {
                return true;
            }

            if ( ! empty( $block['innerBlocks'] ) && self::scan_blocks_for_context( $block['innerBlocks'], $contexts ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve block context from block name + attributes.
     */
    protected static function resolve_block_context( string $block_name, array $attrs ): string {
        if ( TextSection::BLOCK_NAME === $block_name ) {
            return sanitize_key( $attrs['context'] ?? 'generic' );
        }

        $aliases = TextSection::get_alias_definitions();

        if ( isset( $aliases[ $block_name ] ) ) {
            return sanitize_key( $aliases[ $block_name ]['context'] );
        }

        return '';
    }
}
