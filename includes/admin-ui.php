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
            'sample'   => esc_url_raw( rest_url('TanViz/v1/sample') ),
            'save'     => esc_url_raw( rest_url('TanViz/v1/save') ),
            'fix'      => esc_url_raw( rest_url('TanViz/v1/fix') ),
        ],
        'nonce' => wp_create_nonce('wp_rest'),
        'logo'  => esc_url( get_option('tanviz_logo_url', TANVIZ_URL.'assets/logo.png') ),
        'embed' => esc_url( home_url('tanviz/embed/') ),
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
          <textarea id="tanviz-prompt" rows="6" class="large-text" placeholder="Describe the generative p5.js visualization you want...">crea el código de una visualización generativa impactante sobre el dataset adjunto</textarea>
          <h2><?php echo esc_html__('Dataset','TanViz'); ?></h2>
          <select id="tanviz-dataset">
            <option value=""><?php echo esc_html__('-- choose --','TanViz'); ?></option>
            <?php foreach ($datasets as $d): ?>
              <option value="<?php echo esc_attr($d['url']); ?>"><?php echo esc_html($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <h2><?php echo esc_html__('Dataset sample','TanViz'); ?></h2>
          <pre id="tanviz-sample"></pre>
          <p><button class="button button-primary" id="tanviz-generate"><?php echo esc_html__('Generate visualization','TanViz'); ?></button></p>
        </section>
        <section>
          <h2><?php echo esc_html__('Code (p5.js)','TanViz'); ?></h2>
          <textarea id="tanviz-code" rows="18" class="large-text code"></textarea>
          <p><button class="button" id="tanviz-copy-code"><?php echo esc_html__('Copy code','TanViz'); ?></button>
             <button class="button" id="tanviz-fix"><?php echo esc_html__('Fix','TanViz'); ?></button></p>
          <details id="tanviz-rr-wrap"><summary><?php echo esc_html__( 'Request/Response', 'TanViz' ); ?></summary><pre id="tanviz-rr"></pre><p><button class="button" id="tanviz-copy-rr"><?php echo esc_html__('Copy','TanViz'); ?></button></p></details>
        </section>
        <section>
          <h2><?php echo esc_html__('Preview','TanViz'); ?></h2>
          <iframe id="tanviz-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
          <h2><?php echo esc_html__('Console','TanViz'); ?></h2>
          <pre id="tanviz-console"></pre><p><button class="button" id="tanviz-copy-console"><?php echo esc_html__('Copy','TanViz'); ?></button></p>
        </section>
      </div>
      <section class="tanviz-meta-box">
        <h2><?php echo esc_html__('Title & Slug','TanViz'); ?></h2>
        <input type="text" id="tanviz-title" class="regular-text" placeholder="Title">
        <input type="text" id="tanviz-slug" class="regular-text code" placeholder="slug-for-visualization">
        <p><button class="button" id="tanviz-save"><?php echo esc_html__('Save to Library','TanViz'); ?></button>
           <button class="button" id="tanviz-export"><?php echo esc_html__('Export PNG','TanViz'); ?></button>
           <button class="button" id="tanviz-export-gif"><?php echo esc_html__('Export GIF','TanViz'); ?></button>
           <button class="button" id="tanviz-copy-iframe"><?php echo esc_html__('Copy iframe','TanViz'); ?></button></p>
      </section>
    </div>
    <?php
}

function tanviz_render_library(){
    $q = new WP_Query([ 'post_type'=>'tanviz_visualization', 'posts_per_page'=>50, 'post_status'=>'any' ]);
    echo '<div class="wrap"><h1>TanViz — Library</h1><table class="widefat"><thead><tr><th>'.esc_html__('Title','TanViz').'</th><th>'.esc_html__('Slug','TanViz').'</th><th>'.esc_html__('Date','TanViz').'</th><th>'.esc_html__('Actions','TanViz').'</th></tr></thead><tbody>';
    if ( $q->have_posts() ) {
        while( $q->have_posts() ){ $q->the_post();
            $slug = get_post_field('post_name');
            $iframe = sprintf('<iframe src="%s" loading="lazy"></iframe>', esc_url( home_url('tanviz/embed/'.$slug) ));
            $shortcode = sprintf('[TanViz slug="%s"]', esc_attr($slug));
            $del = wp_nonce_url( admin_url('post.php?action=delete&post='.get_the_ID()), 'delete-post_'.get_the_ID() );
            echo '<tr><td>'.esc_html(get_the_title()).'</td><td>'.esc_html($slug).'</td><td>'.esc_html(get_the_date()).'</td><td>';
            echo '<a href="'.esc_url(get_edit_post_link()).'">'.esc_html__('Edit','TanViz').'</a> | ';
            echo '<a href="#" class="tanviz-copy-shortcode" data-shortcode="'.esc_attr($shortcode).'">'.esc_html__('Copy shortcode','TanViz').'</a> | ';
            echo '<a href="#" class="tanviz-copy-iframe" data-iframe="'.esc_attr($iframe).'">'.esc_html__('Copy iframe','TanViz').'</a> | ';
            echo '<a href="'.esc_url($del).'" onclick="return confirm(\''.esc_js(__('Delete?','TanViz')).'\');">'.esc_html__('Delete','TanViz').'</a>';
            echo '</td></tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="4">'.esc_html__('No visualizations yet.','TanViz').'</td></tr>';
    }
    echo '</tbody></table></div>';
}
