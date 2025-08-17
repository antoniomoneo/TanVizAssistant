<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_logs_base_dir(): string {
    $upload = wp_upload_dir();
    $base = trailingslashit( $upload['basedir'] ) . 'tangibleviz/logs/';
    foreach ( [ '', 'errors', 'runs', 'lessons' ] as $sub ) {
        $dir = $base . $sub;
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
    }
    return $base;
}

function tanviz_log_write( string $channel, array $payload ): void {
    $base = tanviz_logs_base_dir();
    $file = $base . $channel . '/' . current_time( 'Y-m-d' ) . '.jsonl';
    $meta = [
        'ts'       => current_time( 'c' ),
        'site_url' => home_url(),
        'user_id'  => get_current_user_id(),
        'ip'       => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
        'ua'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
    ];
    $line = wp_json_encode( array_merge( $meta, $payload ), JSON_UNESCAPED_SLASHES );
    file_put_contents( $file, $line . "\n", FILE_APPEND );
}

function tanviz_log_error( $data ): void {
    if ( is_string( $data ) ) {
        $data = [ 'message' => $data ];
    }
    tanviz_log_write( 'errors', $data );
    $msg = isset( $data['message'] ) ? $data['message'] : wp_json_encode( $data );
    error_log( '[TanViz] ' . $msg );
}

function tanviz_log_run( array $data ): void {
    tanviz_log_write( 'runs', $data );
}

function tanviz_lessons_update( array $items ): void {
    $base = tanviz_logs_base_dir() . 'lessons/';
    $md    = $base . 'lessons.md';
    $index = $base . 'lessons.index.json';
    $idx   = file_exists( $index ) ? json_decode( file_get_contents( $index ), true ) : [];
    if ( ! is_array( $idx ) ) { $idx = []; }
    $lines = [];
    foreach ( $items as $txt ) {
        $txt = trim( (string) $txt );
        if ( $txt === '' ) { continue; }
        $hash = substr( sha1( $txt ), 0, 12 );
        if ( isset( $idx[ $hash ] ) ) { continue; }
        $idx[ $hash ] = true;
        $lines[] = "- {$txt}";
    }
    if ( ! empty( $lines ) ) {
        if ( ! file_exists( $md ) ) {
            file_put_contents( $md, "# Lecciones aprendidas (TanViz)\n\n" );
        }
        file_put_contents( $md, implode( "\n", $lines ) . "\n", FILE_APPEND );
        file_put_contents( $index, wp_json_encode( $idx, JSON_PRETTY_PRINT ) );
    }
}

if ( ! wp_next_scheduled( 'tanviz_logs_cleanup_daily' ) ) {
    wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tanviz_logs_cleanup_daily' );
}

add_action( 'tanviz_logs_cleanup_daily', function(){
    $base = tanviz_logs_base_dir();
    foreach ( [ 'errors', 'runs' ] as $ch ) {
        $dir = $base . $ch;
        if ( ! is_dir( $dir ) ) { continue; }
        foreach ( glob( trailingslashit( $dir ) . '*.jsonl' ) as $file ) {
            if ( filemtime( $file ) < time() - 30 * DAY_IN_SECONDS ) {
                @unlink( $file );
            }
        }
    }
} );

add_action( 'init', function(){
    tanviz_lessons_update([
        "Evita placeholders: usa los nombres reales de las columnas.",
        "Cálculo de Rango: verifica datos vacíos o mal formateados antes de min/max.",
        "Visualización: beginShape()/endShape() + puntos para resaltar observaciones.",
        "Manejo de Errores: captura fallos al cargar CSV (URL incorrecta / 404).",
        "Reactividad: redibuja en windowResized() manteniendo proporciones.",
        "Estilo Visual: añade ejes y título para dar contexto.",
        "Optimización: noLoop() para gráficos estáticos.",
    ]);
});

