<?php

namespace HellasWiki\REST;

use HellasWiki\Health;
use HellasWiki\Logger;
use HellasWiki\Routing;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles GitHub webhook events and background polling.
 */
class GitHubWebhook extends WP_REST_Controller {
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
            '/github/webhook',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/webhook/test',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_test_webhook' ],
                'permission_callback' => static function (): bool {
                    return current_user_can( 'manage_options' );
                },
            ]
        );
}

/**
 * Handle webhook payload.
 */
public function handle_webhook( WP_REST_Request $request ) {
        if ( ! $this->verify_signature( $request ) ) {
            Logger::error( 'Webhook signature verification failed.', [ 'ip' => $request->get_header( 'x-forwarded-for' ) ] );
            Health::record_webhook( 403, 'invalid_signature' );
            return new WP_Error( 'hellaswiki_invalid_signature', __( 'Invalid webhook signature.', 'hellas-wiki' ), [ 'status' => 403 ] );
        }

        $payload = json_decode( (string) $request->get_body(), true );

        if ( ! is_array( $payload ) ) {
            Logger::error( 'Webhook payload invalid JSON.' );
            Health::record_webhook( 400, 'invalid_json' );
            return new WP_Error( 'hellaswiki_invalid_payload', __( 'Invalid webhook payload.', 'hellas-wiki' ), [ 'status' => 400 ] );
        }

        $paths = [];
        foreach ( $payload['commits'] ?? [] as $commit ) {
            foreach ( [ 'added', 'modified' ] as $key ) {
                foreach ( $commit[ $key ] ?? [] as $path ) {
                    $paths[] = $path;
                }
            }
        }

        return $this->queue_paths( $paths, 'webhook' );
    }


/**
 * Verify webhook signature.
 */
    protected function verify_signature( WP_REST_Request $request ): bool {
        $settings = get_option( 'hellaswiki_settings', [] );
        $secret   = $settings['webhook_secret'] ?? '';

        if ( empty( $secret ) ) {
            return true; // Allow if not configured.
        }

        $header = $request->get_header( 'x-hub-signature-256' );

        if ( ! $header ) {
            Logger::error( 'Webhook missing signature header.' );
            return false;
        }

        $hash = 'sha256=' . hash_hmac( 'sha256', (string) $request->get_body(), $secret );

        $valid = hash_equals( $hash, $header );

        if ( $valid ) {
            Logger::info( 'Webhook signature verified.' );
        }

        return $valid;
    }

    /**
     * Handle manual webhook test without signature.
     */
    public function handle_test_webhook( WP_REST_Request $request ): WP_REST_Response {
        $payload = json_decode( (string) $request->get_body(), true );

        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_json' ], 400 );
        }

        $paths = array_map( 'sanitize_text_field', $payload['paths'] ?? [] );

        if ( empty( $paths ) && isset( $payload['path'] ) ) {
            $paths[] = sanitize_text_field( (string) $payload['path'] );
        }

        if ( empty( $paths ) ) {
            return new WP_REST_Response( [ 'error' => 'missing_paths' ], 400 );
        }

        Logger::info( 'Webhook test invoked.', [ 'count' => count( $paths ) ] );

        return $this->queue_paths( $paths, 'test' );
    }

    /**
     * Queue detected file paths.
     *
     * @param array<int, string> $paths  File paths.
     * @param string             $source Source label.
     */
    protected function queue_paths( array $paths, string $source ): WP_REST_Response {
        $queue = get_option( 'hellaswiki_import_queue', [] );

        foreach ( $paths as $path ) {
            $detected = $this->detect_type_from_path( $path );
            if ( ! $detected ) {
                continue;
            }

            $queue[ md5( $path ) ] = [
                'path'      => $path,
                'post_type' => $detected,
                'timestamp' => time(),
            ];
        }

        $queued = count( $queue );

        update_option( 'hellaswiki_import_queue', $queue );
        update_option( 'hellaswiki_import_counter', $queued );

        Logger::info(
            'Paths queued.',
            [
                'paths'  => $paths,
                'count'  => $queued,
                'source' => $source,
            ]
        );

        if ( 'webhook' === $source ) {
            Health::record_webhook( 200, sprintf( 'queued:%d', $queued ) );
        }

        return new WP_REST_Response( [ 'queued' => $queued ], 200 );
    }

/**
 * Detect CPT based on GitHub file path.
 */
protected function detect_type_from_path( string $path ): ?string {
$map = [
'src/main/resources/data/pixelmon/species/'     => 'wiki_species',
'src/main/resources/data/hellasforms/moves/'     => 'wiki_move',
'src/main/resources/data/hellasforms/abilities/' => 'wiki_ability',
'src/main/resources/data/hellasforms/items/'     => 'wiki_item',
];

foreach ( $map as $prefix => $type ) {
if ( 0 === strpos( $path, $prefix ) ) {
return $type;
}
}

return null;
}

/**
 * Fetch repository changes for poller.
 */
    public static function fetch_repository_changes( string $repo, string $token ): int {
        $api_base = self::repo_api_base( $repo );
        $response = wp_remote_get(
            $api_base . '/commits?per_page=5',
            [
                'headers' => [
                    'Authorization' => 'token ' . $token,
                    'Accept'        => 'application/vnd.github+json',
                    'User-Agent'    => 'HellasWiki/1.0',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'Poller GitHub request failed.', [ 'error' => $response->get_error_message() ] );
            return 0;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) ) {
            Logger::error( 'Poller GitHub response malformed.' );
            return 0;
        }

        $queue = get_option( 'hellaswiki_import_queue', [] );
        $count = 0;

        foreach ( $data as $commit ) {
$sha = $commit['sha'] ?? '';
if ( empty( $sha ) ) {
continue;
}

$files = self::fetch_commit_files( $repo, $sha, $token );

foreach ( $files as $file ) {
$path     = $file['filename'] ?? '';
$detected = ( new self() )->detect_type_from_path( $path );

if ( ! $detected ) {
continue;
}

                $queue[ md5( $path ) ] = [
                    'path'      => $path,
                    'post_type' => $detected,
                    'sha'       => $sha,
                    'timestamp' => time(),
                ];
                $count++;
            }
        }

        update_option( 'hellaswiki_import_queue', $queue );
        update_option( 'hellaswiki_import_counter', count( $queue ) );

        return $count;
    }

/**
 * Fetch commit file list.
 *
 * @return array<int, array<string, mixed>>
 */
protected static function fetch_commit_files( string $repo, string $sha, string $token ): array {
$api_base = self::repo_api_base( $repo );
$response = wp_remote_get(
$api_base . '/commits/' . rawurlencode( $sha ),
[
'headers' => [
'Authorization' => 'token ' . $token,
'User-Agent'    => 'HellasWiki/1.0',
],
]
);

if ( is_wp_error( $response ) ) {
return [];
}

$data = json_decode( wp_remote_retrieve_body( $response ), true );

return $data['files'] ?? [];
}

/**
 * Download file from GitHub repo using raw endpoint.
 */
protected function download_github_file( string $repo, string $path, string $token ): string {
list( $owner, $name ) = self::split_repo( $repo );
$segments = array_map( 'rawurlencode', array_filter( explode( '/', $path ) ) );
$url      = sprintf( 'https://raw.githubusercontent.com/%s/%s/main/%s', rawurlencode( $owner ), rawurlencode( $name ), implode( '/', $segments ) );

$response = wp_remote_get(
$url,
[
'headers' => [
'Authorization' => 'token ' . $token,
'User-Agent'    => 'HellasWiki/1.0',
],
]
);

if ( is_wp_error( $response ) ) {
return '';
}

return (string) wp_remote_retrieve_body( $response );
}

/**
 * Helper: build API base URL.
 */
protected static function repo_api_base( string $repo ): string {
list( $owner, $name ) = self::split_repo( $repo );
return sprintf( 'https://api.github.com/repos/%s/%s', rawurlencode( $owner ), rawurlencode( $name ) );
}

/**
 * Split `owner/repo`.
 *
 * @return array{string,string}
 */
public static function split_repo( string $repo ): array {
$parts = explode( '/', $repo, 2 );
return [ $parts[0] ?? '', $parts[1] ?? '' ];
}
}
