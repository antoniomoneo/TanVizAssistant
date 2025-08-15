<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_extract_structured( array $api_json ) {
    if ( ! empty( $api_json['output'] ) && is_array( $api_json['output'] ) ) {
        foreach ( $api_json['output'] as $item ) {
            if ( empty( $item['content'] ) || ! is_array( $item['content'] ) ) continue;
            foreach ( $item['content'] as $c ) {
                $type = $c['type'] ?? '';
                if ( ($type === 'json_schema' || $type === 'output_json') && isset( $c['json'] ) ) {
                    return $c['json'];
                }
            }
        }
    }
    if ( ! empty( $api_json['output_text'] ) && is_string( $api_json['output_text'] ) ) {
        $maybe = json_decode( $api_json['output_text'], true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
    }
    $buf = '';
    if ( ! empty( $api_json['output'] ) ) {
        foreach ( $api_json['output'] as $item ) {
            foreach ( ($item['content'] ?? []) as $c ) {
                if ( ($c['type'] ?? '') === 'output_text' ) {
                    if ( isset($c['text']) && is_string($c['text']) ) $buf .= $c['text'];
                    elseif ( isset($c['text']['value']) && is_string($c['text']['value']) ) $buf .= $c['text']['value'];
                }
            }
        }
    }
    $buf = trim($buf);
    if ( $buf !== '' ) {
        $maybe = json_decode( $buf, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
        if ( preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $buf, $m) ) {
            $maybe = json_decode( trim($m[1]), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
        }
    }
    return null;
}

function tanviz_is_valid_p5( $code ) {
    if ( ! is_string($code) || strlen($code) < 20 ) return false;
    $global_ok = preg_match('/\bfunction\s+setup\s*\(/i', $code) || preg_match('/\bsetup\s*=\s*function\s*\(/i', $code);
    $inst_ok   = preg_match('/new\s+p5\s*\(\s*function\s*\(\s*p\s*\)/i', $code) ||
                 preg_match('/new\s+p5\s*\(\s*\(\s*p\s*\)\s*=>/i', $code) ||
                 preg_match('/\bp\.\s*setup\s*=\s*function\s*\(/i', $code);
    return ( $global_ok || $inst_ok );
}

function tanviz_normalize_p5_code( $code ) {
    $code = preg_replace('/^```(?:p5|javascript|js)?\s*|\s*```$/m', '', $code);
    $code = preg_replace('#<\s*/?\s*script[^>]*>#i', '', $code);
    // Strip multiline and single-line JS comments
    $code = preg_replace('~/\*.*?\*/~s', '', $code);
    $code = preg_replace('~(?<!:)//.*$~m', '', $code);
    return trim($code);
}

function tanviz_build_user_content( $dataset_url, $user_prompt, $sample_rows = 20 ) {
    $file = TANVIZ_PATH . "prompts/generate_visualization.json";
    if ( ! file_exists( $file ) ) return "";
    $json = file_get_contents( $file );
    if ( $json === false ) return "";
    $json = str_replace( [ "{{PROMPT_USUARIO}}", "{{DATASET_URL}}" ], [ $user_prompt, $dataset_url ], $json );
    $data = json_decode( $json, true );
    if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['sections'] ) ) return "";

    $sections = $data['sections'];
    $parts    = [];

    if ( ! empty( $sections['role'] ) ) {
        $parts[] = trim( $sections['role'] );
    }
    if ( ! empty( $sections['objective'] ) && is_array( $sections['objective'] ) ) {
        $parts[] = "OBJETIVO:\n- " . implode( "\n- ", array_map( 'trim', $sections['objective'] ) );
    }
    if ( ! empty( $sections['inputs'] ) && is_array( $sections['inputs'] ) ) {
        $inputs = [];
        foreach ( $sections['inputs'] as $k => $v ) {
            $inputs[] = strtoupper( $k ) . ': ' . $v;
        }
        $parts[] = "ENTRADAS:\n" . implode( "\n", $inputs );
    }
    if ( ! empty( $sections['rules'] ) && is_array( $sections['rules'] ) ) {
        $parts[] = "REGLAS:\n- " . implode( "\n- ", array_map( 'trim', $sections['rules'] ) );
    }

    $lessons_file = TANVIZ_PATH . "LESSONS.md";
    if ( file_exists( $lessons_file ) ) {
        $lessons = file_get_contents( $lessons_file );
        if ( $lessons !== false ) {
            $lessons = trim( preg_replace('/^#.*\n/', '', $lessons) );
            if ( $lessons !== '' ) {
                $parts[] = "LECCIONES:\n" . $lessons;
            }
        }
    }

    return implode( "\n\n", $parts );
}
