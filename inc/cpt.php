<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('init', function(){
    register_post_type('tanviz_visualization',[
        'labels' => [
            'name' => __( 'Visualizations', 'TanViz' ),
            'singular_name' => __( 'Visualization', 'TanViz' )
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title','editor'],
        'menu_icon' => 'dashicons-visibility',
    ]);

    register_post_meta( 'tanviz_visualization', 'tanviz_dataset_url', [
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'single' => true,
        'show_in_rest' => false,
    ] );
});
