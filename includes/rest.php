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

    register_rest_route('TanViz/v1','/fix',[
        'methods'  => 'POST',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_fix',
        'args'     => [
            'code'  => ['required'=>true],
            'error' => ['required'=>true],
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

    $schema = tanviz_p5_json_schema();
    $body   = [
        'model' => $model,
        'input' => tanviz_build_user_content( $dataset_url, $prompt, 20 ),
        'text'  => [
            'format' => [
                'type'   => 'json_schema',
                'name'   => 'tanviz_p5_visualizacion',
                'schema' => $schema,
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

function tanviz_rest_fix( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_api_key', '' ) );
    if ( ! $api_key ) {
        return new WP_REST_Response( [ 'error' => 'Missing API key' ], 400 );
    }

    $model = get_option( 'tanviz_model', 'gpt-4o-mini' );
    $code  = tanviz_normalize_p5_code( (string) $req->get_param( 'code' ) );
    $error = sanitize_textarea_field( (string) $req->get_param( 'error' ) );

    $input = <<<PROMPT
Corrige el código de visualización p5.js basándote en el error capturado en la consola. Debes reemplazar únicamente las partes defectuosas y devolver el archivo completo ya corregido y listo para ejecutar.

OBJETIVO
- Entregar SOLO el código final de p5.js (archivo completo), funcional y sin errores, con las correcciones aplicadas.

CONTEXTO
ERROR EN CONSOLA:
{$error}

CÓDIGO ACTUAL:
{$code}

REGLAS DE CORRECCIÓN (OBLIGATORIAS)
1) Sustitución mínima: modifica SOLO lo imprescindible para resolver el/los errores y mantener la intención original.
2) Estructura p5.js obligatoria: incluir (según corresponda) preload(), setup(), draw() y cualquier función auxiliar usada.
3) Mantener placeholders/datos:
   - No inventar datos ni usar muestras ficticias.
   - Respetar y reutilizar exactamente los placeholders/variables/URLs existentes (p.ej. {{DATASET_URL}}, {{col.year}}, {{col.value}}).
4) Sin dependencias externas nuevas: no añadir librerías ni fetchs/cargas fuera de los mecanismos ya presentes/placeholders.
5) Robustez:
   - Manejo básico de errores (p.ej., verificar existencia de columnas/valores antes de acceder).
   - Evitar patrones frágiles (eval, import dinámicos, XHR no previsto).
6) Estilo de salida:
   - SALIDA EXCLUSIVAMENTE EN FORMATO DE CÓDIGO.
   - No incluir texto, explicaciones, comentarios, logs, prints ni anotaciones (ni dentro ni fuera del bloque).
   - No incluir banners, encabezados, ni “```explicaciones```”. SOLO el código JS final.
7) Compatibilidad:
   - Mantener el API esperado por el entorno (nombres de funciones/variables públicas).
   - No cambiar nombres de placeholders ni la interfaz esperada por el sistema.
8) Performance: evitar bucles/operaciones innecesarias en draw().

VALIDACIONES AUTOMÁTICAS (ANTES DE DEVOLVER EL CÓDIGO)
- [ ] ¿Se resuelve el/los errores indicados en {$error}?
- [ ] ¿El archivo es completo, ejecutable en p5.js y sin errores de sintaxis?
- [ ] ¿Se han conservado placeholders/variables/URLs originales?
- [ ] ¿No hay comentarios, impresiones de consola ni texto adicional?
- [ ] ¿Se mantiene preload/setup/draw (si aplica) y la intención original?

FORMATO DE RESPUESTA (ESTRICTO)
Devuelve ÚNICAMENTE el código final del archivo p5.js, sin ningún texto antes o después.
PROMPT;

    $body = [
        'model' => $model,
        'input' => $input,
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

    $code_status = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $json = json_decode( $raw, true );
    if ( $code_status < 200 || $code_status >= 300 || ! is_array( $json ) ) {
        return new WP_REST_Response( [ 'error' => 'API error', 'raw' => $raw ], 500 );
    }

    $out = '';
    if ( ! empty( $json['output_text'] ) ) {
        $out = $json['output_text'];
    } elseif ( ! empty( $json['output'][0]['content'][0]['text'] ) ) {
        $out = $json['output'][0]['content'][0]['text'];
    }
    if ( ! $out ) {
        return new WP_REST_Response( [ 'error' => 'No output', 'raw' => $json ], 502 );
    }

    $code_fixed = tanviz_normalize_p5_code( $out );

    return new WP_REST_Response( [ 'ok' => true, 'codigo' => $code_fixed ], 200 );
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
