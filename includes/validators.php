<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Extracts p5.js code block from text between markers.
 *
 * @param string $text Raw text from model.
 * @return array
 */
function tanviz_extract_p5_block( string $text ): array {
    if ( ! preg_match( '/-----BEGIN_P5JS-----\s*([\s\S]*?)\s*-----END_P5JS-----/u', $text, $m ) ) {
        return array( 'ok' => false, 'error' => 'no_block' );
    }
    return array( 'ok' => true, 'codigo' => trim( $m[1] ) );
}

/**
 * Validates p5.js code using regex checks.
 *
 * @param string $code Code to validate.
 * @return array
 */
function tanviz_validate_p5_code( string $code ): array {
    $checks = array(
        'hasPreload'     => (bool) preg_match( '/function\s+preload\s*\(/', $code ),
        'hasSetup'       => (bool) preg_match( '/function\s+setup\s*\(/', $code ),
        'hasDraw'        => (bool) preg_match( '/function\s+draw\s*\(/', $code ),
        'noBannedAPIs'   => ! preg_match( '/(eval\s*\(|XMLHttpRequest|fetch\s*\(|import\s*\(|<style>)/i', $code ),
        'noFallbackData' => ! preg_match( '/(sampleData|dummy|placeholder\s*data)/i', $code ),
    );
    $errors = array();
    foreach ( $checks as $k => $v ) {
        if ( ! $v ) {
            $errors[] = $k;
        }
    }
    return array(
        'ok'     => empty( $errors ),
        'checks' => $checks,
        'errors' => $errors,
    );
}
