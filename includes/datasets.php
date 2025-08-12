<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dataset lister: reads from option 'tanviz_datasets_base' and attempts to fetch
 * available datasets from a GitHub repository. If it cannot fetch dynamically,
 * it falls back to a few common candidate files or a filtered list via
 * tanviz_dataset_candidates.
 */
function tanviz_list_datasets() {
    $base = trailingslashit( get_option('tanviz_datasets_base','') );
    $out  = [];

    if ( $base && preg_match('#https://raw.githubusercontent.com/([^/]+)/([^/]+)/([^/]+)/(.*)#', $base, $m) ) {
        $owner  = $m[1];
        $repo   = $m[2];
        $branch = $m[3];
        $path   = rtrim( $m[4], '/' );
        $api_url = sprintf( 'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            $owner, $repo, $path, $branch );
        $resp = wp_remote_get( $api_url, [ 'timeout' => 10, 'headers'=>['User-Agent'=>'TanViz'] ] );
        if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
            $items = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'file' ) {
                        $name = $item['name'];
                        if ( preg_match( '/\.(csv|json)$/i', $name ) ) {
                            $out[] = [ 'name' => $name, 'url' => $base . $name ];
                        }
                    }
                }
            }
        }
    }

    if ( empty( $out ) && $base ) {
        $candidates = apply_filters( 'tanviz_dataset_candidates', [
            'data.csv','data.json','sample.csv','sample.json'
        ] );
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
