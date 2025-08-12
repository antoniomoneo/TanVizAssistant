<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_enqueue_p5(){
    wp_enqueue_script('p5', 'https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js', [], '1.9.0', true);
}
add_action('wp_enqueue_scripts','tanviz_enqueue_p5');

add_shortcode('TanViz', function( $atts ){
    $atts = shortcode_atts([ 'slug'=>'' ], $atts, 'TanViz' );
    if ( ! $atts['slug'] ) return '';
    $post = get_page_by_path( sanitize_title($atts['slug']), OBJECT, 'tanviz_visualization' );
    if ( ! $post ) return '';
    $code = $post->post_content;
    $code = wp_kses_post( $code );
    ob_start(); ?>
    <div class="tanviz-embed" data-slug="<?php echo esc_attr($atts['slug']); ?>"></div>
    <script>(function(){
      function run(){
        var code = <?php echo wp_json_encode($code); ?>;
        try{ new Function(code)(); }catch(e){ console.error('TanViz error', e); }
      }
      if (window.p5) run(); else {
        var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js';
        s.onload=run; document.head.appendChild(s);
      }
    })();</script>
    <?php
    return ob_get_clean();
});
