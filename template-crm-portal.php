<?php
/**
 * Template Name: CRM Quản Lý Đặt Lịch (No Header, No Footer)
 *
 * Giao diện Portal toàn màn hình chuyên biệt cho trang /quan-li-dat-lich/
 * Loại bỏ hoàn toàn Header, Footer, Menu điều hướng và các tiện ích ngoài.
 *
 * @package Memora
 * @subpackage BookingCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Phân quyền: Chỉ cho phép administrator và editor truy cập
if ( ! is_user_logged_in() || ( function_exists( 'memora_crm_user_has_access' ) && ! memora_crm_user_has_access() ) ) {
    $login_url = wp_login_url( home_url( '/quan-li-dat-lich/' ) );
    wp_redirect( $login_url, 301 );
    exit;
}

// 2. Nạp assets cho CRM Frontend Portal
if ( function_exists( 'memora_crm_enqueue_assets' ) ) {
    memora_crm_enqueue_assets( true );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Quản Lý Đặt Lịch - <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
    <style id="memora-crm-portal-canvas-css">
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #f8fafc !important;
            overflow-x: hidden;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
        header, footer,
        #site-header, #site-footer,
        .site-header, .site-footer,
        .elementor-location-header, .elementor-location-footer,
        [data-elementor-type="header"], [data-elementor-type="footer"],
        .memora-contact-btn-wrap, #memora_contact_button,
        .memora-menu-header-wrap, #memora_menu_header {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            overflow: hidden !important;
        }
        .memora-crm-portal-wrapper {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        .memora-crm-portal-wrapper .memora-crm-app,
        .memora-crm-portal-wrapper .memora-crm-container {
            width: 100% !important;
            max-width: 100% !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            flex: 1;
            box-sizing: border-box !important;
        }
    </style>
</head>
<body <?php body_class( 'memora-crm-portal-page elementor-template-canvas' ); ?>>
<?php wp_body_open(); ?>

<div class="memora-crm-portal-wrapper">
    <?php
    if ( function_exists( 'memora_crm_render_view' ) ) {
        memora_crm_render_view();
    } else {
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
    }
    ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
