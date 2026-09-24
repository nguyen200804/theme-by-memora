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