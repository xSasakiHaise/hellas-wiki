<?php
/*
Plugin Name: HellasWiki Staging Boot
*/
add_action('init', function () {
    if (!get_option('hellaswiki_staging_page')) {
        $id = wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => 'Hellas Wiki (Staging)',
            'post_name'   => 'wiki-staging',
            'post_status' => 'publish',
        ]);

        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_wp_page_template', 'wiki-index.php');
            update_option('hellaswiki_staging_page', $id, false);
            flush_rewrite_rules(false);
        }
    }
});

add_action('template_redirect', function () {
    $staging_on = get_option('hellaswiki_staging_mode', '1') === '1';
    if (!$staging_on) {
        return;
    }

    $protected = [
        '/wiki',
        '/wiki/',
        '/wiki-staging',
        '/wiki-staging/',
        '/wiki/species',
        '/wiki/forms',
        '/wiki/moves',
        '/wiki/abilities',
        '/wiki/items',
    ];

    $req          = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $is_protected = false;

    foreach ($protected as $path) {
        if (stripos((string) $req, $path) === 0) {
            $is_protected = true;
            break;
        }
    }

    if (!$is_protected) {
        return;
    }

    if (current_user_can('edit_wiki_pages') || current_user_can('edit_posts')) {
        add_action('wp_head', static function () {
            echo "<meta name='robots' content='noindex,nofollow'>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        });

        return;
    }

    $token = isset($_GET['preview_key']) ? (string) $_GET['preview_key'] : '';
    $valid = $token && hash_equals((string) get_option('hellaswiki_preview_key', ''), $token);

    if ($valid) {
        add_action('wp_head', static function () {
            echo "<meta name='robots' content='noindex,nofollow'>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        });

        return;
    }

    wp_redirect('https://hellasregion.miraheze.org/wiki/Main_Page', 302);
    exit;
});
