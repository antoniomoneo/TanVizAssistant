<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Very simple dataset lister: reads from option 'tanviz_datasets_base' and returns a few common files
 * or allows manual listing via filter tanviz_dataset_candidates.
 * In production you may query GitHub API; here we keep it simple.
 */
function tanviz_list_datasets() {
    $base = trailingslashit( get_option('tanviz_datasets_base','') );
    $candidates = apply_filters('tanviz_dataset_candidates', [
        'data.csv','data.json','sample.csv','sample.json'
    ]);
    $out = [];
    if ( $base ) {
        foreach ( $candidates as $f ) {
            $out[] = [ 'name' => $f, 'url' => $base . $f ];
        }
    }
    return $out;
}

/**
 * Fetch a tiny sample of a CSV/JSON URL to display in Sandbox (first ~20 rows)
 */
function tanviz_fetch_sample( $url ) {
    if ( ! $url ) return [ 'type'=>'none', 'data'=>'' ];
    $resp = wp_remote_get( $url, [ 'timeout'=>10 ] );
    if ( is_wp_error($resp) ) return [ 'type'=>'error', 'data'=>$resp->get_error_message() ];
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    if ( $code !== 200 || ! $body ) return [ 'type'=>'error', 'data'=>'HTTP '.$code ];
    // very naive sniff
    if ( preg_match('/\.csv($|\?)/i', $url) ) {
        $lines = preg_split('/\r?\n/', $body);
        $lines = array_slice($lines, 0, 21);
        return [ 'type'=>'csv', 'data'=>implode("\n", $lines) ];
    }
    // assume json
    $json = json_decode($body, true);
    if ( json_last_error() === JSON_ERROR_NONE ) {
        return [ 'type'=>'json', 'data'=> wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ];
    }
    return [ 'type'=>'text', 'data'=> substr($body,0,1000) ];
}
