<?php

namespace HellasWiki\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

class UpdateController {
    public const OWNER      = 'xSasakiHaise';
    public const REPO       = 'hellas-wiki';
    public const MAIN_FILE  = 'hellas-wiki/hellas-wiki.php';
    public const TRANSIENT  = 'hellaswiki_update_check';
    public const BRANCH     = 'main';

    /**
     * Register REST routes.
     */
    public static function register(): void {
        add_action(
            'rest_api_init',
            static function (): void {
                register_rest_route(
                    'hellaswiki/v1',
                    '/update/check',
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'permission_callback' => static function (): bool {
                            return current_user_can( 'update_plugins' );
                        },
                        'callback'            => [ __CLASS__, 'check' ],
                    ]
                );

                register_rest_route(
                    'hellaswiki/v1',
                    '/update/run',
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'permission_callback' => static function ( WP_REST_Request $request ): bool {
                            if ( ! current_user_can( 'update_plugins' ) ) {
                                return false;
                            }

                            $nonce = $request->get_param( '_wpnonce' );

                            if ( ! $nonce ) {
                                $nonce = $request->get_header( 'X-WP-Nonce' );
                            }

                            if ( ! $nonce ) {
                                return false;
                            }

                            // Populate $_REQUEST for compatibility with check_ajax_referer().
                            if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
                                $_REQUEST['_wpnonce'] = $nonce;
                            }

                            return (bool) check_ajax_referer( 'hellaswiki_update', '_wpnonce', false );
                        },
                        'callback'            => [ __CLASS__, 'run' ],
                    ]
                );
            }
        );
    }

    /**
     * Determine the GitHub repository details.
     */
    protected static function repository_details(): array {
        $settings = get_option( 'hellaswiki_settings', [] );
        $default  = [
            'owner'  => self::OWNER,
            'repo'   => self::REPO,
            'branch' => self::BRANCH,
        ];

        $candidate = isset( $settings['github_repo'] ) ? trim( (string) $settings['github_repo'] ) : '';

        if ( '' === $candidate ) {
            return $default;
        }

        $candidate = strtolower( $candidate );

        if ( 2 !== substr_count( $candidate . '/', '/' ) ) {
            return $default;
        }

        [ $owner, $repo ] = array_map( 'trim', explode( '/', $candidate, 2 ) );

        if ( '' === $owner || '' === $repo ) {
            return $default;
        }

        $allow_list = apply_filters( 'hellaswiki_updater_allowed_repos', [ strtolower( self::OWNER . '/' . self::REPO ) ] );

        if ( is_array( $allow_list ) && ! empty( $allow_list ) ) {
            $allow_list = array_map( 'strtolower', $allow_list );

            if ( ! in_array( $owner . '/' . $repo, $allow_list, true ) ) {
                return $default;
            }
        } elseif ( is_array( $allow_list ) && empty( $allow_list ) ) {
            return $default;
        }

        $branch = apply_filters( 'hellaswiki_updater_branch', self::BRANCH, $owner, $repo );

        return [
            'owner'  => $owner,
            'repo'   => $repo,
            'branch' => $branch ?: self::BRANCH,
        ];
    }

    /**
     * Generate cache key per repository.
     */
    protected static function cache_key( string $owner, string $repo ): string {
        return self::TRANSIENT . '_' . md5( $owner . '/' . $repo );
    }

    /**
     * Whether release-only mode is enabled.
     */
    protected static function releases_only(): bool {
        $option = get_option( 'hellaswiki_updater_releases_only', '1' );

        if ( is_bool( $option ) ) {
            return $option;
        }

        return '0' !== (string) $option;
    }

    /**
     * Retrieve the current plugin version.
     */
    protected static function current_version(): string {
        if ( ! function_exists( '\\get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::MAIN_FILE, false, false );

        return $data['Version'] ?? '0.0.0';
    }

    /**
     * Headers for GitHub API requests.
     */
    protected static function github_headers(): array {
        $settings = get_option( 'hellaswiki_settings', [] );
        $token    = isset( $settings['github_token'] ) ? trim( (string) $settings['github_token'] ) : '';

        if ( ! $token ) {
            $token = trim( (string) get_option( 'hellaswiki_github_token', '' ) );
        }

        $headers = [
            'User-Agent' => 'HellasWiki-Updater',
            'Accept'     => 'application/vnd.github+json',
        ];

        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Fetch the latest GitHub release.
     */
    protected static function fetch_latest_release( string $owner, string $repo ): ?array {
        $url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
        $resp = wp_remote_get(
            $url,
            [
                'timeout' => 12,
                'headers' => self::github_headers(),
            ]
        );

        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            return null;
        }

        $zip = isset( $body['zipball_url'] ) ? (string) $body['zipball_url'] : '';

        if ( '' === $zip ) {
            $zip = sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( $body['tag_name'] ) );
        }

        return [
            'version' => ltrim( (string) $body['tag_name'], 'v' ),
            'zip_url' => $zip,
            'source'  => 'release',
        ];
    }

    /**
     * Fallback download details for the default branch.
     */
    protected static function fallback_branch_zip( string $owner, string $repo, string $branch ): array {
        $branch = $branch ?: self::BRANCH;

        return [
            'version' => 'branch-' . gmdate( 'Ymd.His' ),
            'zip_url' => sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s',
                rawurlencode( $owner ),
                rawurlencode( $repo ),
                rawurlencode( $branch )
            ),
            'source'  => 'branch',
        ];
    }

    /**
     * Perform the update check.
     */
    protected static function build_check_result( string $owner, string $repo, string $branch, bool $force = false ): array {
        $cache_key = self::cache_key( $owner, $repo );

        if ( $force ) {
            delete_transient( $cache_key );
        }

        $cached = get_transient( $cache_key );

        if ( $cached && ! $force ) {
            return $cached;
        }

        $current = self::current_version();
        $latest  = self::fetch_latest_release( $owner, $repo );
        $reason  = '';

        if ( ! $latest ) {
            if ( self::releases_only() ) {
                $latest = [
                    'version' => $current,
                    'zip_url' => null,
                    'source'  => 'release',
                ];
                $reason = 'no_releases';
            } else {
                $latest = self::fallback_branch_zip( $owner, $repo, $branch );
            }
        }

        $has_update = false;

        if ( 'branch' === $latest['source'] ) {
            $has_update = true;
        } else {
            $has_update = version_compare( $latest['version'], $current, '>' );
        }

        $result = [
            'current'       => $current,
            'latest'        => $latest['version'],
            'has_update'    => (bool) $has_update,
            'source'        => $latest['source'],
            'zip_url'       => $latest['zip_url'],
            'repository'    => $owner . '/' . $repo,
            'releases_only' => self::releases_only(),
        ];

        if ( $reason ) {
            $result['reason'] = $reason;
            $result['has_update'] = false;
        }

        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

        return $result;
    }

    /**
     * Clear cached update data.
     */
    public static function clear_cached_check(): void {
        $details = self::repository_details();
        delete_transient( self::cache_key( $details['owner'], $details['repo'] ) );
    }

    /**
     * REST callback for GET /update/check.
     */
    public static function check( WP_REST_Request $request ) {
        $details = self::repository_details();
        $force   = (bool) $request->get_param( 'force' );

        return self::build_check_result( $details['owner'], $details['repo'], $details['branch'], $force );
    }

    /**
     * Run the plugin update.
     */
    public static function run( WP_REST_Request $request ) {
        $details = self::repository_details();

        $check = self::build_check_result( $details['owner'], $details['repo'], $details['branch'], true );

        if ( empty( $check['zip_url'] ) ) {
            return new WP_Error( 'no_package', __( 'No update package available.', 'hellas-wiki' ), [ 'status' => 400 ] );
        }

        if ( empty( $check['has_update'] ) ) {
            return new WP_Error( 'no_update', __( 'Already up to date.', 'hellas-wiki' ), [ 'status' => 400 ] );
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $previous_version = self::current_version();
        $skin             = new \Automatic_Upgrader_Skin();
        $upgrader         = new \Plugin_Upgrader( $skin );

        $result = $upgrader->install(
            $check['zip_url'],
            [
                'overwrite_package' => true,
                'clear_working'     => true,
                'abort_if_destination_exists' => false,
            ]
        );

        if ( is_wp_error( $result ) || ! $result ) {
            $message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Installation failed.', 'hellas-wiki' );

            return new WP_Error( 'update_failed', $message, [ 'status' => 500 ] );
        }

        $plugins = get_plugins();
        $target  = self::MAIN_FILE;

        if ( ! isset( $plugins[ $target ] ) ) {
            foreach ( $plugins as $file => $data ) {
                if ( substr( $file, -strlen( '/hellas-wiki.php' ) ) === '/hellas-wiki.php' ) {
                    $target = $file;
                    break;
                }
            }
        }

        $activation = activate_plugin( $target, '', false, true );

        if ( is_wp_error( $activation ) ) {
            return new WP_Error( 'activation_failed', $activation->get_error_message(), [ 'status' => 500 ] );
        }

        delete_transient( self::cache_key( $details['owner'], $details['repo'] ) );

        $new_version = self::current_version();

        return [
            'updated'     => true,
            'previous'    => $previous_version,
            'new'         => $new_version,
            'activated'   => $target,
            'repository'  => $check['repository'] ?? ( $details['owner'] . '/' . $details['repo'] ),
            'source'      => $check['source'] ?? 'release',
        ];
    }
}
