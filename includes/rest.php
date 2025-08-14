<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once TANVIZ_PATH . 'includes/schema.php';
require_once TANVIZ_PATH . 'includes/structured.php';
require_once TANVIZ_PATH . 'includes/datasets.php';

add_action('rest_api_init', function(){
    register_rest_route('TanViz/v1','/generate',[
        'methods'  => 'POST',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_generate',
        'args'     => [
            'prompt' => ['required'=>true],
            'dataset_url' => ['required'=>false],
        ],
    ]);

    register_rest_route('TanViz/v1','/datasets',[
        'methods'  => 'GET',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => function(){
            $list = tanviz_list_datasets();
            return new WP_REST_Response([ 'ok'=>true, 'datasets'=>$list ],200);
        },
    ]);

    register_rest_route('TanViz/v1','/sample',[
        'methods'  => 'GET',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => function( WP_REST_Request $req ){
            $url = esc_url_raw( (string) $req->get_param('url') );
            $sample = tanviz_fetch_sample( $url );
            return new WP_REST_Response( $sample, 200 );
        },
        'args' => [ 'url' => ['required'=>true] ],
    ]);

    register_rest_route('TanViz/v1','/save',[
        'methods'  => 'POST',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_save',
        'args'     => [
            'title' => ['required'=>true],
            'slug'  => ['required'=>true],
            'code'  => ['required'=>true],
            'dataset_url' => ['required'=>false],
        ],
    ]);
});

function tanviz_rest_generate( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_api_key', '' ) );
    if ( ! $api_key ) {
        return new WP_REST_Response( [ 'error' => 'Missing API key' ], 400 );
    }

    $model       = get_option( 'tanviz_model', 'gpt-4o-mini' );
    $prompt      = sanitize_textarea_field( (string) $req->get_param( 'prompt' ) );
    $dataset_url = esc_url_raw( (string) $req->get_param( 'dataset_url' ) );

    // Obtain the JSON Schema definition for the p5.js response
    $schema = \TanViz\Schema::responseSchema();
    $body   = [
        'model' => $model,
        'input' => tanviz_build_user_content( $dataset_url, $prompt, 20 ),
        'text'  => [
            'format' => [
                'name'        => 'json_schema',
                'json_schema' => $schema,
            ],
        ],
    ];

    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 60,
        'body'    => wp_json_encode( $body ),
    ];

    $resp = wp_remote_post( 'https://api.openai.com/v1/responses', $args );
    if ( is_wp_error( $resp ) ) {
        return new WP_REST_Response( [ 'error' => $resp->get_error_message() ], 500 );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $json = json_decode( $raw, true );
    if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
        return new WP_REST_Response( [ 'error' => 'API error', 'raw' => $raw ], 500 );
    }

    $structured = tanviz_extract_structured( $json );
    if ( ! $structured || empty( $structured['codigo'] ) ) {
        return new WP_REST_Response( [ 'error' => 'No structured output', 'raw' => $json ], 502 );
    }

    $code_p5 = tanviz_normalize_p5_code( $structured['codigo'] );

    $response = [
        'ok'     => true,
        'codigo' => $code_p5,
    ];
    if ( isset( $structured['titulo'] ) ) {
        $response['titulo'] = $structured['titulo'];
    }
    if ( isset( $structured['descripcion'] ) ) {
        $response['descripcion'] = $structured['descripcion'];
    }
    if ( isset( $structured['tags'] ) ) {
        $response['tags'] = $structured['tags'];
    }

    return new WP_REST_Response( $response, 200 );
}

function tanviz_rest_save( WP_REST_Request $req ) {
    $title = sanitize_text_field( (string) $req->get_param('title') );
    $slug  = sanitize_title( (string) $req->get_param('slug') );
    $code  = tanviz_normalize_p5_code( (string) $req->get_param('code') );
    $dataset = esc_url_raw( (string) $req->get_param('dataset_url') );

    if ( ! $title || ! $slug || ! $code ) {
        return new WP_REST_Response( [ 'error' => __( 'Missing fields', 'TanViz' ) ], 400 );
    }

    $existing = get_page_by_path( $slug, OBJECT, 'tanviz_visualization' );
    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $code,
        'post_type'    => 'tanviz_visualization',
        'post_status'  => 'publish',
    ];
    if ( $existing ) {
        $postarr['ID'] = $existing->ID;
        $post_id = wp_update_post( $postarr, true );
    } else {
        $post_id = wp_insert_post( $postarr, true );
    }

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
    }

    if ( $dataset ) {
        update_post_meta( $post_id, 'tanviz_dataset_url', $dataset );
    }

    return new WP_REST_Response( [ 'ok' => true, 'id' => $post_id ], 200 );
}
