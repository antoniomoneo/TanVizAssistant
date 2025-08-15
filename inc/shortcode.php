<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('TanViz', function( $atts ){
    $atts = shortcode_atts([ 'slug'=>'' ], $atts, 'TanViz' );
    if ( ! $atts['slug'] ) return '';
    $post = get_page_by_path( sanitize_title($atts['slug']), OBJECT, 'tanviz_visualization' );
    if ( ! $post ) return '';
    $code = $post->post_content;
    $code = wp_kses_post( $code );
    wp_enqueue_script('p5', 'https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js', [], '1.9.0', true);
    wp_add_inline_script('p5', "try{ new Function(" . wp_json_encode( $code ) . ")(); }catch(e){ console.error('TanViz error', e); }");
    ob_start(); ?>
    <div class="tanviz-embed" data-slug="<?php echo esc_attr($atts['slug']); ?>"></div>
    <?php
    return ob_get_clean();
});

function tanviz_embed_rewrite(){
    add_rewrite_rule('tanviz/embed/([^/]+)/?','index.php?tanviz_embed=$matches[1]','top');
}
add_action('init','tanviz_embed_rewrite');

add_filter('query_vars', function($vars){ $vars[]='tanviz_embed'; return $vars; });

function tanviz_handle_embed(){
    $slug = get_query_var('tanviz_embed');
    if ( ! $slug ) return;
    $post = get_page_by_path( sanitize_title($slug), OBJECT, 'tanviz_visualization' );
    if ( ! $post ) { status_header(404); exit; }
    $code  = tanviz_normalize_p5_code( $post->post_content );
    $title = esc_html( get_the_title( $post ) );
    $logo  = esc_url( get_option('tanviz_logo_url', TANVIZ_URL.'assets/logo.png') );
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset='utf-8'><title>{$title}</title><style>html,body{margin:0;height:100%}#wrap{position:relative;height:100%}#ovl{position:absolute;top:8px;left:8px;display:flex;align-items:center;gap:.5rem;font:14px/1.2 system-ui}#ovl img{height:24px}</style><script src='https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js'></script></head><body><div id='wrap'><div id='ovl'><img src='{$logo}'/><div>{$title}</div></div></div><script>{$code}</script></body></html>";
    exit;
}
add_action('template_redirect','tanviz_handle_embed');

register_activation_hook( TANVIZ_PATH . 'TanViz.php', function(){
    tanviz_embed_rewrite();
    flush_rewrite_rules();
});
register_deactivation_hook( TANVIZ_PATH . 'TanViz.php', 'flush_rewrite_rules' );
