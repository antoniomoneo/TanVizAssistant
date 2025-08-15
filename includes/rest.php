<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once TANVIZ_PATH . 'includes/validators.php';
require_once TANVIZ_PATH . 'includes/openai.php';
require_once TANVIZ_PATH . 'includes/structured.php';
require_once TANVIZ_PATH . 'includes/datasets.php';

add_action('rest_api_init', function(){
    register_rest_route('TanViz/v1','/generate',[
        'methods'  => 'POST',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_generate',
        'args'     => [
            'prompt'      => array( 'required' => true ),
            'dataset_url' => array( 'required' => true ),
            'feedback'    => array( 'required' => false ),
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
            'code'     => ['required'=>true],
            'feedback' => ['required'=>true],
        ],
    ]);

    register_rest_route('TanViz/v1','/ask',[
        'methods'  => 'POST',
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'callback' => 'tanviz_rest_ask',
        'args'     => [
            'code' => ['required'=>true],
        ],
    ]);
});

function tanviz_rest_generate( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_openai_api_key', '' ) );
    if ( ! $api_key ) {
        tanviz_log_error( 'Generate: Missing API key' );
        return new WP_REST_Response( array( 'error' => 'Missing API key' ), 400 );
    }

    $model       = get_option( 'tanviz_model', 'gpt-4o-2024-08-06' );
    $prompt      = sanitize_textarea_field( (string) $req->get_param( 'prompt' ) );
    $dataset_url = esc_url_raw( (string) $req->get_param( 'dataset_url' ) );

    if ( $dataset_url && ( ! wp_http_validate_url( $dataset_url ) || 'https' !== wp_parse_url( $dataset_url, PHP_URL_SCHEME ) ) ) {
        tanviz_log_error( 'Generate: Invalid dataset URL ' . $dataset_url );
        return new WP_Error( 'tanviz_invalid_dataset', __( 'URL del dataset inválida', 'TanViz' ), array( 'status' => 400 ) );
    }

    $fb_in = $req->get_param( 'feedback' );
    $feedback = array();
    if ( is_array( $fb_in ) ) {
        foreach ( array( 'root_causes', 'blocking_errors', 'policy_violations', 'improvements' ) as $k ) {
            if ( ! empty( $fb_in[ $k ] ) ) {
                $feedback[ $k ] = array_map( 'sanitize_text_field', (array) $fb_in[ $k ] );
            }
        }
    }

    $args = array(
        'dataset_url'    => $dataset_url,
        'prompt_usuario' => $prompt,
        'feedback'       => $feedback,
        'model'          => $model,
    );

    $resp = tanviz_openai_generate_code_only( $args );
    if ( ! $resp['ok'] ) {
        tanviz_log_error( 'Generate: OpenAI error ' . $resp['error'] );
        if ( 'no_block' === $resp['error'] ) {
            return new WP_Error( 'tanviz_no_code', __( 'No se encontró el bloque de código p5.js en la respuesta.', 'TanViz' ) );
        }
        return new WP_Error( 'tanviz_openai_error', $resp['error'], array( 'raw' => $resp['raw'] ) );
    }

    $codigo = $resp['codigo'];
    $val    = tanviz_validate_p5_code( $codigo );
    if ( ! $val['ok'] ) {
        $err_txt = implode( ', ', $val['errors'] );
        tanviz_log_error( 'Generate: Validation errors ' . $err_txt );
        $prompt_fix = "Corrige el código p5.js basándote en los errores detectados. Debes reemplazar ÚNICAMENTE lo imprescindible y devolver el archivo COMPLETO listo para ejecutar.\n\nOBJETIVO\n- Entregar SOLO el código final p5.js entre marcadores.\n\nCONTEXTO\nERROR EN VALIDACIÓN:\n{$err_txt}\n\nCÓDIGO ACTUAL:\n{$codigo}\n\nREGLAS DE CORRECCIÓN (OBLIGATORIAS)\n1) Sustitución mínima: conserva intención original.\n2) Estructura p5.js: preload(), setup(), draw() y helpers usados.\n3) Mantén dataset/URLs/placeholders existentes.\n4) Prohibido eval/import/fetch/XHR y datos de ejemplo.\n\nResponde entre:\n-----BEGIN_P5JS-----\n...CÓDIGO CORREGIDO...\n-----END_P5JS-----";

        $retry = tanviz_openai_generate_code_only( array(
            'dataset_url'    => $dataset_url,
            'prompt_usuario' => $prompt_fix,
            'model'          => $model,
        ) );
        if ( ! $retry['ok'] ) {
            tanviz_log_error( 'Generate: OpenAI retry error ' . $retry['error'] );
            if ( 'no_block' === $retry['error'] ) {
                return new WP_Error( 'tanviz_no_code', __( 'No se encontró el bloque de código p5.js en la respuesta.', 'TanViz' ) );
            }
            return new WP_Error( 'tanviz_openai_error', $retry['error'], array( 'raw' => $retry['raw'] ) );
        }
        $codigo = $retry['codigo'];
        $val    = tanviz_validate_p5_code( $codigo );
        if ( ! $val['ok'] ) {
            tanviz_log_error( 'Generate: Validation failed after retry ' . implode( ', ', $val['errors'] ) );
            return new WP_Error( 'tanviz_validation_failed', __( 'Errores de validación', 'TanViz' ), array( 'checks' => $val['checks'], 'errors' => $val['errors'] ) );
        }
    }

    return new WP_REST_Response( array( 'success' => true, 'code' => $codigo ), 200 );
}

function tanviz_rest_ask( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_openai_api_key', '' ) );
    if ( ! $api_key ) {
        tanviz_log_error( 'Ask: Missing API key' );
        return new WP_REST_Response( array( 'error' => 'Missing API key' ), 400 );
    }

    $model = get_option( 'tanviz_model', 'gpt-4o-2024-08-06' );
    $code  = tanviz_normalize_p5_code( (string) $req->get_param( 'code' ) );

    $input = <<<PROMPT
Evalúa el siguiente código p5.js y describe de forma concisa los problemas o mejoras necesarias.

CÓDIGO:
{$code}
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
        tanviz_log_error( 'Ask: HTTP error ' . $resp->get_error_message() );
        return new WP_REST_Response( [ 'error' => $resp->get_error_message() ], 500 );
    }

    $code_status = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $json = json_decode( $raw, true );
    if ( $code_status < 200 || $code_status >= 300 || ! is_array( $json ) ) {
        tanviz_log_error( 'Ask: API error ' . $code_status );
        return new WP_REST_Response( [ 'error' => 'API error', 'raw' => $raw ], 500 );
    }

    $out = '';
    if ( ! empty( $json['output_text'] ) ) {
        $out = $json['output_text'];
    } elseif ( ! empty( $json['output'][0]['content'][0]['text'] ) ) {
        $out = $json['output'][0]['content'][0]['text'];
    }
    if ( ! $out ) {
        tanviz_log_error( 'Ask: No output from OpenAI' );
        return new WP_REST_Response( [ 'error' => 'No output', 'raw' => $json ], 502 );
    }

    return new WP_REST_Response( [ 'ok' => true, 'feedback' => $out ], 200 );
}

function tanviz_rest_fix( WP_REST_Request $req ) {
    $api_key = trim( get_option( 'tanviz_openai_api_key', '' ) );
    if ( ! $api_key ) {
        tanviz_log_error( 'Fix: Missing API key' );
        return new WP_REST_Response( array( 'error' => 'Missing API key' ), 400 );
    }

    $model = get_option( 'tanviz_model', 'gpt-4o-2024-08-06' );
    $code  = tanviz_normalize_p5_code( (string) $req->get_param( 'code' ) );
    $feedback = sanitize_textarea_field( (string) $req->get_param( 'feedback' ) );

    $input = <<<PROMPT
Corrige el código de visualización p5.js basándote en la retroalimentación proporcionada. Debes reemplazar únicamente las partes defectuosas y devolver el archivo completo ya corregido y listo para ejecutar.

OBJETIVO
- Entregar SOLO el código final de p5.js (archivo completo), funcional y sin errores, con las correcciones aplicadas.

CONTEXTO
RETROALIMENTACIÓN:
{$feedback}

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
- [ ] ¿Se resuelven los puntos indicados en la retroalimentación?
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
        tanviz_log_error( 'Fix: HTTP error ' . $resp->get_error_message() );
        return new WP_REST_Response( [ 'error' => $resp->get_error_message() ], 500 );
    }

    $code_status = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $json = json_decode( $raw, true );
    if ( $code_status < 200 || $code_status >= 300 || ! is_array( $json ) ) {
        tanviz_log_error( 'Fix: API error ' . $code_status );
        return new WP_REST_Response( [ 'error' => 'API error', 'raw' => $raw ], 500 );
    }

    $out = '';
    if ( ! empty( $json['output_text'] ) ) {
        $out = $json['output_text'];
    } elseif ( ! empty( $json['output'][0]['content'][0]['text'] ) ) {
        $out = $json['output'][0]['content'][0]['text'];
    }
    if ( ! $out ) {
        tanviz_log_error( 'Fix: No output from OpenAI' );
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
        tanviz_log_error( 'Save: Missing fields' );
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
        tanviz_log_error( 'Save: ' . $post_id->get_error_message() );
        return new WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
    }

    if ( $dataset ) {
        update_post_meta( $post_id, 'tanviz_dataset_url', $dataset );
    }

    return new WP_REST_Response( [ 'ok' => true, 'id' => $post_id ], 200 );
}
