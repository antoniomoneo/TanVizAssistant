<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Log error messages to a file under the plugin logs directory.
 *
 * @param string $message Error message to log.
 */
function tanviz_log_error( string $message ): void {
    $dir = TANVIZ_PATH . 'logs';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    $file = trailingslashit( $dir ) . 'errors.log';
    $timestamp = date( 'Y-m-d H:i:s' );
    $entry = sprintf( "[%s] %s\n", $timestamp, $message );
    file_put_contents( $file, $entry, FILE_APPEND );
}
