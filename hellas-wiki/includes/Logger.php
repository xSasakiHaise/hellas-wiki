<?php

namespace HellasWiki;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Simple logger utility for Hellas Wiki.
 */
class Logger {
    /**
     * Log an info message.
     *
     * @param string               $message Message to log.
     * @param array<string, mixed> $context Extra context.
     */
    public static function info( string $message, array $context = [] ): void {
        self::log( 'INFO', $message, $context );
    }

    /**
     * Log an error message.
     *
     * @param string               $message Message to log.
     * @param array<string, mixed> $context Extra context.
     */
    public static function error( string $message, array $context = [] ): void {
        self::log( 'ERROR', $message, $context );
    }

    /**
     * Internal log writer.
     *
     * @param string               $level   Log level label.
     * @param string               $message Log message.
     * @param array<string, mixed> $context Context data.
     */
    protected static function log( string $level, string $message, array $context = [] ): void {
        if ( empty( $message ) ) {
            return;
        }

        $time   = new DateTimeImmutable( 'now', new DateTimeZone( wp_timezone_string() ) );
        $record = [
            'timestamp' => $time->format( DateTimeImmutable::ATOM ),
            'level'     => $level,
            'message'   => $message,
        ];

        if ( ! empty( $context ) ) {
            $record['context'] = self::sanitize_context( $context );
        }

        $line = wp_json_encode( $record, JSON_UNESCAPED_SLASHES ) ?: '';

        if ( ! $line ) {
            return;
        }

        self::write_to_file( $line );

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[HellasWiki] ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Sanitize context values to avoid storing secrets.
     *
     * @param array<string, mixed> $context Raw context.
     *
     * @return array<string, mixed>
     */
    protected static function sanitize_context( array $context ): array {
        $sanitized = [];

        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) ) {
                $sanitized[ $key ] = self::mask_if_sensitive( (string) $value );
                continue;
            }

            if ( is_array( $value ) ) {
                $sanitized[ $key ] = self::sanitize_context( $value );
            }
        }

        return $sanitized;
    }

    /**
     * Mask secrets in string context.
     */
    protected static function mask_if_sensitive( string $value ): string {
        if ( '' === $value ) {
            return $value;
        }

        if ( preg_match( '/(token|secret|signature|authorization)/i', $value ) ) {
            return substr( $value, 0, 6 ) . '…';
        }

        return $value;
    }

    /**
     * Append log line to uploads directory file.
     */
    protected static function write_to_file( string $line ): void {
        $uploads = wp_get_upload_dir();

        if ( empty( $uploads['basedir'] ) ) {
            return;
        }

        $dir = trailingslashit( $uploads['basedir'] );

        if ( ! wp_mkdir_p( $dir ) ) {
            return;
        }

        $file = $dir . 'hellaswiki.log';
        $fh   = @fopen( $file, 'ab' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( ! $fh ) {
            return;
        }

        fwrite( $fh, $line . PHP_EOL );
        fclose( $fh );
    }
}
