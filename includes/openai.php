<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Interacts with OpenAI Assistants API using thread-based conversations.
 *
 * @param array $args Arguments: dataset_url, prompt_usuario, model?, thread_id?
 * @return array
 */
function tanviz_openai_assistant_chat( array $args ): array {
    $dataset_url   = isset( $args['dataset_url'] ) ? (string) $args['dataset_url'] : '';
    $prompt_user   = isset( $args['prompt_usuario'] ) ? (string) $args['prompt_usuario'] : '';
    $model         = isset( $args['model'] ) ? (string) $args['model'] : 'gpt-4o-2024-08-06';
    $thread_id     = isset( $args['thread_id'] ) ? (string) $args['thread_id'] : '';

    $api_key      = trim( get_option( 'tanviz_openai_api_key', '' ) );
    $assistant_id = trim( get_option( 'tanviz_assistant_id', '' ) );
    if ( ! $api_key ) {
        return array( 'ok' => false, 'error' => 'missing_api_key' );
    }
    if ( ! $assistant_id ) {
        return array( 'ok' => false, 'error' => 'missing_assistant_id' );
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
        'OpenAI-Beta'   => 'assistants=v2',
    );

    if ( ! $thread_id ) {
        $resp = wp_remote_post( 'https://api.openai.com/v1/threads', array(
            'headers' => $headers,
            'timeout' => 60,
            'body'    => '{}',
        ) );
        if ( is_wp_error( $resp ) ) {
            tanviz_log_error( 'OpenAI thread create failed: ' . $resp->get_error_message() );
            return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '' );
        }
        $json      = json_decode( wp_remote_retrieve_body( $resp ), true );
        $thread_id = $json['id'] ?? '';
        if ( ! $thread_id ) {
            tanviz_log_error( 'OpenAI thread create: missing id' );
            return array( 'ok' => false, 'error' => 'no_thread_id', 'raw' => wp_remote_retrieve_body( $resp ) );
        }
    }

    if ( $dataset_url && ! $args['thread_id'] ) {
        $content = "Eres un experto en p5.js. Entrega ÚNICAMENTE el archivo final p5.js listo para ejecutar.\n\n" .
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
            "-----END_P5JS-----";
    } else {
        $content = $prompt_user;
    }

    $msg_body = array(
        'role'    => 'user',
        'content' => $content,
    );
    $resp = wp_remote_post( "https://api.openai.com/v1/threads/{$thread_id}/messages", array(
        'headers' => $headers,
        'timeout' => 60,
        'body'    => wp_json_encode( $msg_body ),
    ) );
    if ( is_wp_error( $resp ) ) {
        tanviz_log_error( 'OpenAI message failed: ' . $resp->get_error_message() );
        return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '' );
    }

    $run_body = array( 'assistant_id' => $assistant_id, 'model' => $model );
    $resp = wp_remote_post( "https://api.openai.com/v1/threads/{$thread_id}/runs", array(
        'headers' => $headers,
        'timeout' => 60,
        'body'    => wp_json_encode( $run_body ),
    ) );
    if ( is_wp_error( $resp ) ) {
        tanviz_log_error( 'OpenAI run failed: ' . $resp->get_error_message() );
        return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '' );
    }
    $json   = json_decode( wp_remote_retrieve_body( $resp ), true );
    $run_id = $json['id'] ?? '';
    if ( ! $run_id ) {
        tanviz_log_error( 'OpenAI run: missing id' );
        return array( 'ok' => false, 'error' => 'no_run_id', 'raw' => wp_remote_retrieve_body( $resp ) );
    }

    $status = '';
    for ( $i = 0; $i < 15; $i++ ) {
        $resp = wp_remote_get( "https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", array(
            'headers' => $headers,
            'timeout' => 60,
        ) );
        if ( is_wp_error( $resp ) ) {
            tanviz_log_error( 'OpenAI run status failed: ' . $resp->get_error_message() );
            return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '' );
        }
        $json   = json_decode( wp_remote_retrieve_body( $resp ), true );
        $status = $json['status'] ?? '';
        if ( in_array( $status, array( 'completed', 'failed', 'cancelled', 'expired' ), true ) ) {
            break;
        }
        sleep( 2 );
    }
    if ( 'completed' !== $status ) {
        tanviz_log_error( 'OpenAI run status: ' . $status );
        return array( 'ok' => false, 'error' => 'run_' . $status, 'raw' => '', 'thread_id' => $thread_id );
    }

    $resp = wp_remote_get( "https://api.openai.com/v1/threads/{$thread_id}/messages", array(
        'headers' => $headers,
        'timeout' => 60,
    ) );
    if ( is_wp_error( $resp ) ) {
        tanviz_log_error( 'OpenAI messages failed: ' . $resp->get_error_message() );
        return array( 'ok' => false, 'error' => $resp->get_error_message(), 'raw' => '', 'thread_id' => $thread_id );
    }
    $json = json_decode( wp_remote_retrieve_body( $resp ), true );
    $msgs = array();
    if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
        $ordered = array_reverse( $json['data'] );
        foreach ( $ordered as $m ) {
            $role = $m['role'] ?? '';
            $text = '';
            foreach ( $m['content'] ?? array() as $c ) {
                if ( isset( $c['text']['value'] ) ) {
                    $text .= $c['text']['value'];
                }
            }
            $msgs[] = array( 'role' => $role, 'text' => $text );
        }
    }

    $code = '';
    for ( $i = count( $msgs ) - 1; $i >= 0; $i-- ) {
        if ( $msgs[ $i ]['role'] === 'assistant' ) {
            $block = tanviz_extract_p5_block( $msgs[ $i ]['text'] );
            if ( $block['ok'] ) {
                $code = $block['code'];
                break;
            }
        }
    }
    if ( ! $code ) {
        tanviz_log_error( 'Missing p5.js code block in OpenAI response.' );
        return array( 'ok' => false, 'error' => 'no_block', 'raw' => wp_remote_retrieve_body( $resp ), 'thread_id' => $thread_id, 'messages' => $msgs );
    }

    return array( 'ok' => true, 'code' => $code, 'thread_id' => $thread_id, 'messages' => $msgs );
}
