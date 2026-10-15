<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'hello-elementor','hello-elementor-theme-style','hello-elementor-header-footer' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION
add_filter('use_block_editor_for_post', '__return_false', 10);

// Nạp file cấu hình Custom Field Group (Marquee, etc.)
if ( file_exists( get_stylesheet_directory() . '/custom-field-group.php' ) ) {
    require_once get_stylesheet_directory() . '/custom-field-group.php';
}

// Nạp file cấu hình Menu Header Drilldown Shortcode [menu__header]
if ( file_exists( get_stylesheet_directory() . '/menu-header.php' ) ) {
    require_once get_stylesheet_directory() . '/menu-header.php';
}

// Nạp file cấu hình Hệ thống Đặt lịch chụp ảnh Memora Booking
if ( file_exists( get_stylesheet_directory() . '/inc/booking-system.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/booking-system.php';
}

// Nạp Shortcode [gallery_swiper] – Slider ảnh với pagination ngôi sao
if ( file_exists( get_stylesheet_directory() . '/inc/gallery-swiper-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/gallery-swiper-shortcode.php';
}

// Nạp Shortcode [dia_chi_noi_bat] – Giới thiệu địa chỉ nổi bật
if ( file_exists( get_stylesheet_directory() . '/inc/dia-chi-noi-bat-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/dia-chi-noi-bat-shortcode.php';
}

// Nạp Shortcode [danh_sach_phong] – Danh sách phòng chụp ảnh của địa chỉ
if ( file_exists( get_stylesheet_directory() . '/inc/danh-sach-phong-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/danh-sach-phong-shortcode.php';
}

// Nạp Shortcode [thong_tin_phong_swiper] – Slider ảnh phòng chụp kèm mô tả WYSIWYG
if ( file_exists( get_stylesheet_directory() . '/inc/thong-tin-phong-swiper-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/thong-tin-phong-swiper-shortcode.php';
}

// Nạp tính năng Nút liên hệ nổi ở góc dưới bên phải [nut_lien_he]
if ( file_exists( get_stylesheet_directory() . '/inc/nut-lien-he.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/nut-lien-he.php';
}

// Nạp tích hợp WooCommerce Checkout native + Booking (pre-fill, cart, link order)
if ( file_exists( get_stylesheet_directory() . '/inc/wc-checkout-booking.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/wc-checkout-booking.php';
}

/**
 * Nạp font chữ local của theme (assets/css/fonts.css → assets/fonts/).
 * Version theo filemtime để tự xóa cache trình duyệt khi file CSS thay đổi.
 */
add_action( 'wp_enqueue_scripts', 'memora_enqueue_local_fonts', 5 );
add_action( 'elementor/preview/enqueue_styles', 'memora_enqueue_local_fonts' );
function memora_enqueue_local_fonts() {
    $rel  = '/assets/css/fonts.css';
    $path = get_stylesheet_directory() . $rel;
    if ( ! file_exists( $path ) ) return;

    wp_enqueue_style(
        'memora-local-fonts',
        get_stylesheet_directory_uri() . $rel,
        array(),
        filemtime( $path )
    );
}


/**
 * Lấy danh sách font face của tất cả Elementor Custom Fonts.
 * Đọc meta 'elementor_font_files' (Elementor Pro lưu mỗi weight/style là 1 phần tử
 * dạng [ 'font_weight' => '400', 'font_style' => 'normal', 'woff2' => [ 'url' => ... ], ... ]).
 *
 * @return array [ family => [ [ 'weight' => '400', 'style' => 'normal', 'src' => "url(..) format(..),..." ], ... ] ]
 */
function memora_get_custom_font_faces() {
    static $cache = null;
    if ( null !== $cache ) return $cache;
    $cache = [];

    if ( ! post_type_exists( 'elementor_font' ) ) return $cache;

    $font_posts = get_posts( [
        'post_type'      => 'elementor_font',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ] );

    // Thứ tự ưu tiên định dạng: trình duyệt lấy src hợp lệ đầu tiên
    $format_map = [
        'woff2' => 'woff2',
        'woff'  => 'woff',
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
        'svg'   => 'svg',
    ];

    foreach ( $font_posts as $font_post ) {
        $files = get_post_meta( $font_post->ID, 'elementor_font_files', true );
        if ( empty( $files ) || ! is_array( $files ) ) continue;

        $family = $font_post->post_title;
        foreach ( $files as $face ) {
            if ( ! is_array( $face ) ) continue;

            $sources = [];
            foreach ( $format_map as $type => $fmt ) {
                $url = '';
                if ( ! empty( $face[ $type ]['url'] ) ) {
                    $url = $face[ $type ]['url'];
                } elseif ( ! empty( $face[ $type ]['id'] ) ) {
                    $url = wp_get_attachment_url( (int) $face[ $type ]['id'] );
                }
                if ( $url ) {
                    $sources[] = "url('" . esc_url( $url ) . "') format('{$fmt}')";
                }
            }
            if ( empty( $sources ) ) continue;

            $weight = ! empty( $face['font_weight'] ) ? $face['font_weight'] : 'normal';
            $style  = ! empty( $face['font_style'] ) ? $face['font_style'] : 'normal';

            $cache[ $family ][] = [
                'weight' => preg_replace( '/[^a-z0-9 ]/i', '', $weight ),
                'style'  => preg_replace( '/[^a-z]/i', '', $style ),
                'src'    => implode( ',', $sources ),
            ];
        }
    }

    return $cache;
}

/**
 * In @font-face cho 1 family – MỖI weight/style một rule riêng
 * (không gộp nhiều file vào chung 1 src, vì trình duyệt chỉ dùng file hợp lệ đầu tiên).
 */
function memora_build_font_face_css( $family, $faces, $extra_descriptors = '' ) {
    $css = '';
    foreach ( $faces as $face ) {
        $css .= "@font-face{font-family:'" . esc_attr( $family ) . "';font-weight:{$face['weight']};font-style:{$face['style']};src:{$face['src']};{$extra_descriptors}}\n";
    }
    return $css;
}

/**
 * Force-load tất cả Elementor Custom Fonts (@font-face) vào wp_head.
 * Elementor chỉ load font khi widget Elementor trên trang dùng font đó.
 * Hook này đảm bảo font luôn available cho PHP shortcodes.
 */
add_action( 'wp_head', 'memora_force_elementor_custom_fonts', 1 );
function memora_force_elementor_custom_fonts() {
    // Chỉ chạy ở frontend
    if ( is_admin() ) return;

    $css = '';
    foreach ( memora_get_custom_font_faces() as $family => $faces ) {
        $css .= memora_build_font_face_css( $family, $faces );
    }

    if ( $css ) {
        echo "\n<style id=\"memora-elementor-custom-fonts\">\n" . $css . "</style>\n";
    }
}

/**
 * Override font metrics cho font 'Cocomat Pro' để cân bằng vùng bôi đen (selection box) và căn giữa dọc tự nhiên.
 * Trị tận gốc lỗi file font gốc có Ascent quá cao và Descent quá thấp làm chữ bị dính sát đáy vùng bôi đen.
 *
 * Lưu ý: Safari KHÔNG hỗ trợ ascent-override/descent-override/line-gap-override.
 * Vì vậy các file Cocomat Pro đã được vá sẵn metrics (asc 85% / desc 20% / gap 0) ngay trong font,
 * block này chỉ còn là lớp bảo vệ cho Chrome/Firefox nếu ai đó upload lại file font gốc chưa vá.
 * Mỗi weight dùng đúng file của weight đó (theo cấu hình Elementor Custom Fonts).
 */
add_action( 'wp_head', 'memora_override_cocomat_metrics', 9999 );
function memora_override_cocomat_metrics() {
    if ( is_admin() ) return;

    $faces = memora_get_custom_font_faces();
    if ( empty( $faces['Cocomat Pro'] ) ) return;

    $css = memora_build_font_face_css(
        'Cocomat Pro',
        $faces['Cocomat Pro'],
        'ascent-override:85%;descent-override:20%;line-gap-override:0%;'
    );

    echo "\n<style id=\"memora-cocomat-metrics-fix\">\n" . $css . "</style>\n";
}


// Force desktop layout on all devices by setting viewport width to 1200px on small screens, and standard viewport on large screens
function tocfl_force_desktop_viewport($html) {
    // Remove all existing viewport meta tags to avoid duplicates or overrides
    $html = preg_replace('/<meta\s+name=["\']viewport["\'][^>]*>/i', '', $html);
    
    $target_width = 600;
    if ( isset($_SERVER['REQUEST_URI']) ) {
        $path = strtok($_SERVER['REQUEST_URI'], '?');
        if ( preg_match( '#^/paper_download/?$#', $path ) ) {
            $target_width = 600;
        }
    }
    
    // Insert the dynamic desktop viewport script right after <head>
    $dynamic_viewport_script = '
<script type="text/javascript">
(function() {
    var targetWidth = ' . $target_width . ';
    function getViewportContent() {
        var screenW = window.screen.width;
        var innerW = window.innerWidth;
        // visualViewport cho phép tính đúng kích thước thực tế kể cả khi đang scale hoặc co kéo trên DevTools Responsive
        var actualWidth = (window.visualViewport && window.visualViewport.width && window.visualViewport.scale) 
            ? (window.visualViewport.width * window.visualViewport.scale)
            : ((innerW && innerW < screenW) ? innerW : screenW);

        return (actualWidth < targetWidth) ? "width=" + targetWidth : "width=device-width, initial-scale=1.0";
    }

    var content = getViewportContent();
    document.write(\'<meta name="viewport" id="memora-viewport" content="\' + content + \'">\');

    function updateViewport() {
        var meta = document.getElementById("memora-viewport") || document.querySelector(\'meta[name="viewport"]\');
        if (!meta) return;
        var newContent = getViewportContent();
        if (meta.getAttribute("content") !== newContent) {
            meta.setAttribute("content", newContent);
        }
    }

    window.addEventListener("resize", updateViewport);
    window.addEventListener("orientationchange", updateViewport);
})();
</script>
';
    if (stripos($html, '<head>') !== false) {
        $html = str_ireplace('<head>', '<head>' . $dynamic_viewport_script, $html);
    } elseif (stripos($html, '<head') !== false) {
        $html = preg_replace('/(<head[^>]*>)/i', '$1' . $dynamic_viewport_script, $html);
    }
    return $html;
}

function tocfl_start_buffer() {
    ob_start('tocfl_force_desktop_viewport');
}
add_action('template_redirect', 'tocfl_start_buffer', 1);




/**
 * Fix taxonomy 'dia-chi' (tạo bởi SCF) hiển thị trong Elementor Archive conditions.
 * Elementor dùng get_taxonomies(['show_in_nav_menus' => true]) để build danh sách.
 * Hook này chạy sau SCF (priority 999) để đảm bảo các args cần thiết đúng.
 */
add_action( 'init', 'memora_fix_dia_chi_taxonomy_for_elementor', 999 );
function memora_fix_dia_chi_taxonomy_for_elementor() {
    global $wp_taxonomies;

    if ( ! isset( $wp_taxonomies['dia-chi'] ) ) {
        return;
    }

    // Đảm bảo taxonomy xuất hiện trong Elementor Archive conditions
    $wp_taxonomies['dia-chi']->public            = true;
    $wp_taxonomies['dia-chi']->publicly_queryable = true;
    $wp_taxonomies['dia-chi']->show_in_nav_menus = true;
    $wp_taxonomies['dia-chi']->show_ui           = true;
}