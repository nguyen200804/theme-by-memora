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

// Force desktop layout on all devices by setting viewport width to 1200px on small screens, and standard viewport on large screens
function tocfl_force_desktop_viewport( $html ) {
    if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return $html;
    }
    if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) ) {
        return $html;
    }

    // Remove all existing viewport meta tags to avoid duplicates or overrides
    $html = preg_replace( '/<meta\s+name=["\']viewport["\'][^>]*>/i', '', $html );
    
    $target_width = 1200;
    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        $path = strtok( $_SERVER['REQUEST_URI'], '?' );
        if ( preg_match( '#^/paper_download/?$#', $path ) ) {
            $target_width = 980;
        }
    }
    
    // Insert the dynamic desktop viewport script right after <head>
    $dynamic_viewport_script = '
<script type="text/javascript">
(function() {
    var screenWidth = window.screen.width;
    var content = (screenWidth < ' . $target_width . ') ? "width=' . $target_width . '" : "width=device-width, initial-scale=1.0";
    document.write(\'<meta name="viewport" content="\' + content + \'">\');
})();
</script>
';
    if ( stripos( $html, '<head>' ) !== false ) {
        $html = str_ireplace( '<head>', '<head>' . $dynamic_viewport_script, $html );
    } elseif ( stripos( $html, '<head' ) !== false ) {
        $html = preg_replace( '/(<head[^>]*>)/i', '$1' . $dynamic_viewport_script, $html, 1 );
    }
    return $html;
}

add_action( 'template_redirect', function() {
    if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }
    if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) ) {
        return;
    }
    ob_start( 'tocfl_force_desktop_viewport' );
} );