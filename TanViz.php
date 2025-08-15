<?php
/**
 * Plugin Name: TanViz
 * Description: Admin-only generator of generative p5.js visualizations using OpenAI Responses API (JSON Schema). Includes Settings, Sandbox, and Library.
 * Version: 0.1.0
 * Author: Antonio Moneo + ChatGPT
 * Text Domain: TanViz
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'TANVIZ_VERSION', '0.1.0' );
define( 'TANVIZ_SLUG', 'TanViz' );
define( 'TANVIZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'TANVIZ_URL',  plugin_dir_url( __FILE__ ) );

// Load components
add_action( 'plugins_loaded', function () {
    $files = [
        'includes/settings.php',
        'includes/validators.php',
        'includes/openai.php',
        'includes/structured.php',
        'includes/datasets.php',
        'includes/rest.php',
        'includes/admin-ui.php',
        'inc/cpt.php',
        'inc/shortcode.php',
    ];
    foreach ( $files as $rel ) {
        $file = TANVIZ_PATH . $rel;
        if ( file_exists( $file ) ) { require_once $file; }
    }
    // Textdomain
    load_plugin_textdomain( 'TanViz', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
});
