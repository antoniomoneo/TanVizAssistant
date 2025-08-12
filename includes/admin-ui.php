<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('admin_menu', function(){
    add_menu_page( 'TanViz', 'TanViz', 'manage_options', 'tanviz', 'tanviz_render_settings', 'dashicons-art', 58 );
    add_submenu_page( 'tanviz', __('Settings','TanViz'), __('Settings','TanViz'), 'manage_options', 'tanviz', 'tanviz_render_settings' );
    add_submenu_page( 'tanviz', __('Sandbox','TanViz'), __('Sandbox','TanViz'), 'manage_options', 'tanviz-sandbox', 'tanviz_render_sandbox' );
    add_submenu_page( 'tanviz', __('Library','TanViz'), __('Library','TanViz'), 'manage_options', 'tanviz-library', 'tanviz_render_library' );
});

function tanviz_admin_assets( $hook ){
    if ( strpos($hook,'tanviz') === false ) return;
    wp_enqueue_style( 'tanviz-admin', TANVIZ_URL.'assets/admin.css', [], TANVIZ_VERSION );
    wp_enqueue_script('tanviz-admin', TANVIZ_URL.'assets/admin.js', ['jquery'], TANVIZ_VERSION, true );
    wp_localize_script('tanviz-admin','TanVizCfg',[
        'rest' => [
            'generate' => esc_url_raw( rest_url('TanViz/v1/generate') ),
            'datasets' => esc_url_raw( rest_url('TanViz/v1/datasets') ),
        ],
        'nonce' => wp_create_nonce('wp_rest'),
        'logo'  => esc_url( get_option('tanviz_logo_url', TANVIZ_URL.'assets/logo.png') ),
    ]);
    // Code editor for the sandbox editor textarea
    $settings = wp_enqueue_code_editor( [ 'type'=>'text/javascript' ] );
    if ( $settings ) {
        wp_add_inline_script( 'code-editor', sprintf( 'window.tanvizCodeEditorSettings = %s;', wp_json_encode( $settings ) ) );
    }
}
add_action('admin_enqueue_scripts','tanviz_admin_assets');

function tanviz_render_settings(){
    echo '<div class="wrap"><h1>TanViz — Settings</h1><form method="post" action="options.php">';
    settings_fields('tanviz_settings');
    do_settings_sections('tanviz');
    submit_button();
    echo '</form></div>';
}

function tanviz_render_sandbox(){
    $datasets = tanviz_list_datasets();
    ?>
    <div class="wrap tanviz-wrap">
      <h1>TanViz — Sandbox</h1>
      <div class="tanviz-grid">
        <section>
          <h2><?php echo esc_html__('Prompt','TanViz'); ?></h2>
          <textarea id="tanviz-prompt" rows="6" class="large-text" placeholder="Describe the generative p5.js visualization you want..."></textarea>
          <h2><?php echo esc_html__('Dataset','TanViz'); ?></h2>
          <select id="tanviz-dataset">
            <option value=""><?php echo esc_html__('-- choose --','TanViz'); ?></option>
            <?php foreach ($datasets as $d): ?>
              <option value="<?php echo esc_attr($d['url']); ?>"><?php echo esc_html($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <p><button class="button button-primary" id="tanviz-generate"><?php echo esc_html__('Generate visualization','TanViz'); ?></button>
             <button class="button" id="tanviz-preview"><?php echo esc_html__('Update preview','TanViz'); ?></button></p>
          <h2><?php echo esc_html__('Title & Slug','TanViz'); ?></h2>
          <input type="text" id="tanviz-title" class="regular-text" placeholder="Title">
          <input type="text" id="tanviz-slug" class="regular-text code" placeholder="slug-for-visualization">
          <p><button class="button" id="tanviz-save"><?php echo esc_html__('Save to Library','TanViz'); ?></button>
             <button class="button" id="tanviz-export"><?php echo esc_html__('Export PNG','TanViz'); ?></button></p>
        </section>
        <section>
          <h2><?php echo esc_html__('Code (p5.js)','TanViz'); ?></h2>
          <textarea id="tanviz-code" rows="18" class="large-text code"></textarea>
          <details><summary>Request/Response</summary><pre id="tanviz-rr"></pre></details>
        </section>
        <section>
          <h2><?php echo esc_html__('Preview','TanViz'); ?></h2>
          <iframe id="tanviz-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
        </section>
      </div>
    </div>
    <?php
}

function tanviz_render_library(){
    $q = new WP_Query([ 'post_type'=>'tanviz_visualization', 'posts_per_page'=>50, 'post_status'=>'any' ]);
    echo '<div class="wrap"><h1>TanViz — Library</h1><table class="widefat"><thead><tr><th>Title</th><th>Slug</th><th>Date</th></tr></thead><tbody>';
    if ( $q->have_posts() ) {
        while( $q->have_posts() ){ $q->the_post();
            echo '<tr><td>'.esc_html(get_the_title()).'</td><td>'.esc_html(get_post_field('post_name')).'</td><td>'.esc_html(get_the_date()).'</td></tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="3">No visualizations yet.</td></tr>';
    }
    echo '</tbody></table></div>';
}
