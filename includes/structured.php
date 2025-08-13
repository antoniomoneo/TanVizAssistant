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
    return trim($code);
}

function tanviz_build_user_content( $dataset_url, $user_prompt, $sample_rows = 20 ) {
    $lines = [];
    $lines[] = 'Goal: Generate a generative p5.js sketch from a dataset + description.';
    if ( $dataset_url ) {
        $lines[] = 'Dataset (raw): ' . $dataset_url;
        $lines[] = 'Use loadTable/loadJSON; if fetch fails, create const sample with ~' . intval($sample_rows) . ' rows.';
    }
    $lines[] = 'Output rules:';
    $lines[] = "- Return ONLY JSON according to schema. 'codigo' must be p5 (global or instance). No <script>.";
    $lines[] = '- Ensure animation or stochastic behavior (generative). Keep draw efficient.';
    $lines[] = '- Overlay title and logo are handled externally; do not create related constants or logic.';
    $lines[] = '';
    $lines[] = 'User description:';
    $lines[] = $user_prompt ?: '(empty)';
    return implode("\n", $lines);
}
