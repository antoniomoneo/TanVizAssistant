<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('init', function(){
    register_post_type('tanviz_visualization',[
        'labels' => [
            'name' => 'Visualizations',
            'singular_name' => 'Visualization'
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title','editor'],
        'menu_icon' => 'dashicons-visibility',
    ]);
});
