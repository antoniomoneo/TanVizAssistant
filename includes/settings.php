<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_register_settings() {
    register_setting( 'tanviz_settings', 'tanviz_openai_api_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'tanviz_settings', 'tanviz_model', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4o-2024-08-06' ] );
    register_setting( 'tanviz_settings', 'tanviz_assistant_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'tanviz_settings', 'tanviz_datasets_base', [ 'type'=>'string', 'sanitize_callback'=>'esc_url_raw' ] );
    register_setting( 'tanviz_settings', 'tanviz_logo_url', [ 'type'=>'string', 'sanitize_callback'=>'esc_url_raw', 'default'=> plugins_url('assets/logo.png', dirname(__FILE__,1) . '/../TanViz.php') ] );

    add_settings_section( 'tanviz_main', __( 'TanViz Settings', 'TanViz' ), function(){ echo '<p>'.esc_html__('Configure API and data sources.','TanViz').'</p>'; }, 'tanviz' );

    add_settings_field( 'tanviz_openai_api_key', __( 'OpenAI API Key', 'TanViz' ), function(){
        $v = esc_attr( get_option( 'tanviz_openai_api_key', '' ) );
        echo '<input type="password" name="tanviz_openai_api_key" value="' . $v . '" class="regular-text" autocomplete="off" />';
    }, 'tanviz', 'tanviz_main' );

    add_settings_field( 'tanviz_model', __( 'OpenAI Model', 'TanViz' ), function(){
        $v = esc_attr( get_option( 'tanviz_model', 'gpt-4o-2024-08-06' ) );
        echo '<input type="text" name="tanviz_model" value="' . $v . '" class="regular-text" />';
    }, 'tanviz', 'tanviz_main' );

    add_settings_field( 'tanviz_assistant_id', __( 'OpenAI Assistant ID', 'TanViz' ), function(){
        $v = esc_attr( get_option( 'tanviz_assistant_id', '' ) );
        echo '<input type="text" name="tanviz_assistant_id" value="' . $v . '" class="regular-text code" />';
    }, 'tanviz', 'tanviz_main' );


    add_settings_field( 'tanviz_datasets_base', __( 'GitHub Datasets Base (raw)', 'TanViz' ), function(){
        $v = esc_attr( get_option('tanviz_datasets_base','') );
        echo '<input type="url" name="tanviz_datasets_base" value="'.$v.'" class="regular-text code" placeholder="https://raw.githubusercontent.com/user/repo/branch/path/" />';
    }, 'tanviz', 'tanviz_main' );

    add_settings_field( 'tanviz_logo_url', __( 'Overlay Logo URL', 'TanViz' ), function(){
        $v = esc_attr( get_option('tanviz_logo_url', plugins_url('assets/logo.png', dirname(__FILE__,1) . '/../TanViz.php') ) );
        echo '<input type="url" name="tanviz_logo_url" value="'.$v.'" class="regular-text code" />';
    }, 'tanviz', 'tanviz_main' );
}
add_action('admin_init','tanviz_register_settings');
