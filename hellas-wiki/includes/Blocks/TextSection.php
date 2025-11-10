<?php

namespace HellasWiki\Blocks;

use WP_Block;

/**
 * Server-rendered text section block.
 */
class TextSection {
    public const BLOCK_NAME = 'hellas-wiki/text-section';

    /**
     * Register block assets.
     */
    public static function register(): void {
        add_action(
            'init',
            static function (): void {
                wp_register_script(
                    'hellaswiki-text-section',
                    HELLAS_WIKI_URL . 'assets/text-section.js',
                    [ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor' ],
                    HELLAS_WIKI_VERSION,
                    true
                );

                register_block_type(
                    self::BLOCK_NAME,
                    [
                        'render_callback' => [ self::class, 'render' ],
                        'editor_script'  => 'hellaswiki-text-section',
                        'attributes'     => [
                            'title'   => [ 'type' => 'string', 'default' => '' ],
                            'content' => [ 'type' => 'string', 'default' => '' ],
                            'context' => [ 'type' => 'string', 'default' => 'generic' ],
                        ],
                        'supports'       => [
                            'align' => false,
                        ],
                    ]
                );
            }
        );
    }

    /**
     * Render block HTML.
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @param string               $content    Inner content (unused).
     */
    public static function render( array $attributes, string $content = '', WP_Block $block = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $title   = isset( $attributes['title'] ) ? wp_strip_all_tags( $attributes['title'] ) : '';
        $body    = isset( $attributes['content'] ) ? wp_kses_post( $attributes['content'] ) : '';
        $context = isset( $attributes['context'] ) ? sanitize_key( $attributes['context'] ) : 'generic';

        if ( '' === trim( $body ) ) {
            return '';
        }

        $classes = [ 'hellaswiki-text-section', 'context-' . sanitize_html_class( $context ?: 'generic' ) ];

        $html  = '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">';
        if ( $title ) {
            $html .= '<h3 class="hellaswiki-text-section__title">' . esc_html( $title ) . '</h3>';
        }
        $html .= '<div class="hellaswiki-text-section__body">' . wpautop( $body ) . '</div>';
        $html .= '</section>';

        return $html;
    }
}
