<?php

namespace HellasWiki;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Centralised health tracking helpers.
 */
class Health {
    protected const OPTION = 'hellaswiki_health';

    /**
     * Retrieve health snapshot with defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_status(): array {
        $defaults = [
            'last_poll_at'       => null,
            'last_poll_result'   => null,
            'last_webhook_at'    => null,
            'last_webhook_status'=> null,
            'webhook_history'    => [],
            'token_scope'        => null,
            'routes_ok'          => true,
        ];

        $value = get_option( self::OPTION, [] );

        return wp_parse_args( is_array( $value ) ? $value : [], $defaults );
    }

    /**
     * Persist snapshot.
     *
     * @param array<string, mixed> $data Data to merge.
     */
    protected static function update( array $data ): void {
        $current = self::get_status();
        update_option( self::OPTION, array_merge( $current, $data ) );
    }

    /**
     * Record poll results.
     *
     * @param string $result Short summary.
     */
    public static function record_poll( string $result ): void {
        self::update(
            [
                'last_poll_at'     => self::now(),
                'last_poll_result' => $result,
            ]
        );
    }

    /**
     * Record webhook delivery outcome.
     *
     * @param int    $status  HTTP status code.
     * @param string $summary Short note.
     */
    public static function record_webhook( int $status, string $summary = '' ): void {
        $snapshot = self::get_status();
        $history  = $snapshot['webhook_history'];
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        array_unshift(
            $history,
            [
                'status'    => $status,
                'summary'   => $summary,
                'timestamp' => self::now(),
            ]
        );

        $history = array_slice( $history, 0, 3 );

        self::update(
            [
                'last_webhook_at'     => self::now(),
                'last_webhook_status' => $status,
                'webhook_history'     => $history,
            ]
        );
    }

    /**
     * Persist detected token scope.
     */
    public static function set_token_scope( ?string $scope ): void {
        self::update(
            [
                'token_scope' => $scope,
            ]
        );
    }

    /**
     * Store routing failure.
     */
    public static function set_routes_ok( bool $ok ): void {
        self::update( [ 'routes_ok' => $ok ] );
    }

    /**
     * Helper to format now timestamp.
     */
    protected static function now(): string {
        $time = new DateTimeImmutable( 'now', new DateTimeZone( wp_timezone_string() ) );
        return $time->format( DateTimeImmutable::ATOM );
    }
}
