<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Calls OpenAI Responses API and extracts p5.js code block.
 *
 * @param array $args Arguments: dataset_url, prompt_usuario, feedback?, model?
 * @return array
 */
function tanviz_openai_generate_code_only( array $args ): array {
    $dataset_url   = isset( $args['dataset_url'] ) ? (string) $args['dataset_url'] : '';
    $prompt_user   = isset( $args['prompt_usuario'] ) ? (string) $args['prompt_usuario'] : '';
    $feedback      = isset( $args['feedback'] ) && is_array( $args['feedback'] ) ? $args['feedback'] : array();
    $model         = isset( $args['model'] ) ? (string) $args['model'] : 'gpt-4o-2024-08-06';

    $fb = array(
        'root_causes'       => empty( $feedback['root_causes'] ) ? 'Ninguno' : sanitize_textarea_field( implode( '; ', (array) $feedback['root_causes'] ) ),
        'blocking_errors'   => empty( $feedback['blocking_errors'] ) ? 'Ninguno' : sanitize_textarea_field( implode( '; ', (array) $feedback['blocking_errors'] ) ),
        'policy_violations' => empty( $feedback['policy_violations'] ) ? 'Ninguno' : sanitize_textarea_field( implode( '; ', (array) $feedback['policy_violations'] ) ),
        'improvements'      => empty( $feedback['improvements'] ) ? 'Ninguno' : sanitize_textarea_field( implode( '; ', (array) $feedback['improvements'] ) ),
    );

    $prompt = "Eres un experto en p5.js. Entrega ÚNICAMENTE el archivo final p5.js listo para ejecutar.\n\n" .
        "ENTRADAS\n" .
        "- PROMPT DEL USUARIO: {$prompt_user}\n" .
        "- DATASET_URL: {$dataset_url}\n" .
        "- Placeholders disponibles: {{col.year}}, {{col.value}} (no hardcodear cabeceras/URLs)\n\n" .
        "CONTRATO OBLIGATORIO\n" .
        "1) Estructura: define preload(), setup(), draw(), y windowResized() si procede. Declara helpers antes de usarlos.\n" .
        "2) Carga de datos: usa SOLO funciones de p5.js (loadTable/loadJSON) con {$dataset_url}.\n" .
        "3) Placeholders: usa {{col.*}} (p. ej., {{col.year}}, {{col.value}}). Prohibido datos de ejemplo/muestras.\n" .
        "4) Rangos dinámicos: calcula yearMin/yearMax y min/max de valores.\n" .
        "5) Prohibido: eval(), import(), fetch(), XHR, CSS externo.\n" .
        "6) Respuesta: devuelve EXCLUSIVAMENTE el código entre:\n" .
        "-----BEGIN_P5JS-----\n" .
        "...AQUÍ VA EL CÓDIGO...\n" .
        "-----END_P5JS-----\n\n" .
        "DEFECTOS DETECTADOS EN LA EVALUACIÓN ANTERIOR (CORREGIR OBLIGATORIO)\n" .
        "- Causas raíz: {$fb['root_causes']}\n" .
        "- Errores bloqueantes: {$fb['blocking_errors']}\n" .
        "- Violaciones de contrato: {$fb['policy_violations']}\n" .
        "- Mejoras solicitadas: {$fb['improvements']}\n";

    $body = array(
        'model' => $model,
        'temperature' => 0.2,
        'max_output_tokens' => 3000,
        'input' => array(
            array( 'role' => 'system', 'content' => 'Eres un experto en p5.js. Entrega ÚNICAMENTE el archivo final p5.js entre marcadores.' ),
            array( 'role' => 'user', 'content' => $prompt ),
        ),
    );

    $args_http = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . trim( get_option( 'tanviz_openai_api_key', '' ) ),
            'Content-Type'  => 'application/json',
        ),
        'timeout' => 60,
        'body'    => wp_json_encode( $body ),
    );

    $resp = wp_remote_post( 'https://api.openai.com/v1/responses', $args_http );
    if ( is_wp_error( $resp ) ) {
        tanviz_log_error( 'OpenAI request failed: ' . $resp->get_error_message() );
        return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '' );
    }

    $raw  = wp_remote_retrieve_body( $resp );
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        tanviz_log_error( 'OpenAI HTTP error ' . $code . ': ' . $raw );
        return array( 'ok' => false, 'error' => 'http_' . $code, 'raw' => $raw );
    }

    $json = json_decode( $raw, true );
    $text = '';
    if ( is_array( $json ) ) {
        if ( ! empty( $json['output'] ) && is_array( $json['output'] ) ) {
            foreach ( $json['output'] as $item ) {
                foreach ( ( $item['content'] ?? array() ) as $c ) {
                    if ( isset( $c['text'] ) && is_string( $c['text'] ) ) {
                        $text .= $c['text'];
                    }
                }
            }
        }
        if ( $text === '' && isset( $json['output_text'] ) ) {
            $text = (string) $json['output_text'];
        }
    } else {
        $text = $raw;
    }

    $block = tanviz_extract_p5_block( $text );
    if ( ! $block['ok'] ) {
        tanviz_log_error( 'Missing p5.js code block in OpenAI response.' );
        return array( 'ok' => false, 'error' => 'no_block', 'raw' => $text );
    }

    return array( 'ok' => true, 'codigo' => $block['codigo'] );
}
