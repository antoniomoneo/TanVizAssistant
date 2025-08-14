<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once TANVIZ_PATH . 'includes/structured.php';
require_once TANVIZ_PATH . 'includes/datasets.php';

// Attempt to load the OpenAI PHP SDK if it's bundled with the plugin.
if ( ! class_exists( '\\OpenAI\\Client' ) ) {
    $autoload = TANVIZ_PATH . 'vendor/autoload.php';
    if ( is_readable( $autoload ) ) {
        require_once $autoload;
    }
}

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

    register_rest_route('TanViz/v1','/test',[
        'methods'  => 'GET',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_test',
    ]);
});

function tanviz_rest_generate( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_api_key', '' ) );
    if ( ! $api_key ) {
        wp_send_json_error( [ 'message' => 'Missing API key' ], 400 );
    }

    $model       = get_option( 'tanviz_model', 'gpt-4o-2024-08-06' );
    $prompt_raw  = sanitize_textarea_field( (string) $req->get_param( 'prompt' ) );
    $dataset_url = esc_url_raw( (string) $req->get_param( 'dataset_url' ) );

    $prompt = tanviz_build_user_content( $dataset_url, $prompt_raw, 20 );

    $schema_data = include TANVIZ_PATH . 'includes/schema.php';
    if ( is_string( $schema_data ) ) {
        $schema = json_decode( $schema_data, true );
    } else {
        $schema = $schema_data;
    }

    if ( ! class_exists( '\OpenAI\Client' ) ) {
        wp_send_json_error( [ 'message' => 'OpenAI PHP SDK not installed' ], 500 );
    }

    $client = new OpenAI\Client( $api_key );

    try {
        $response = $client->responses->create([
            'model' => $model,
            'input' => $prompt,
            'text'  => [
                'format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name'   => 'TanVizResponse',
                        'schema' => $schema,
                        'strict' => true,
                    ],
                ],
            ],
            // 'max_output_tokens' => 2048,
        ]);

        $out = $response->output[0]->content[0]->text ?? null;
        if ( ! $out ) {
            throw new \RuntimeException( 'No text output from Responses API' );
        }
        $data = json_decode( $out, true, 512, JSON_THROW_ON_ERROR );

        $code_p5 = tanviz_normalize_p5_code( $data['codigo'] ?? '' );
        if ( ! $code_p5 ) {
            throw new \RuntimeException( 'No codigo in response' );
        }

        $result = [
            'ok'     => true,
            'codigo' => $code_p5,
        ];
        if ( isset( $data['titulo'] ) ) {
            $result['titulo'] = $data['titulo'];
        }
        if ( isset( $data['descripcion'] ) ) {
            $result['descripcion'] = $data['descripcion'];
        }
        if ( isset( $data['tags'] ) ) {
            $result['tags'] = $data['tags'];
        }

        wp_send_json_success( $result );
    } catch ( \OpenAI\Exceptions\ErrorException $e ) {
        wp_send_json_error( $e->getPayload(), 500 );
    } catch ( \Throwable $e ) {
        wp_send_json_error([
            'message' => $e->getMessage(),
            'trace'   => defined( 'TANVIZ_DEBUG' ) && TANVIZ_DEBUG ? $e->getTraceAsString() : null,
        ], 500 );
    }
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

function tanviz_rest_test() {
    $schema_data = include TANVIZ_PATH . 'includes/schema.php';
    if ( is_string( $schema_data ) ) {
        $schema = json_decode( $schema_data, true );
    } else {
        $schema = $schema_data;
    }

    wp_send_json_success([
        'ok'     => true,
        'schema' => $schema,
    ]);
}
