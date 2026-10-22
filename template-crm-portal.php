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

        /* Triệt tiêu triệt để button override từ Elementor/Theme */
        .memora-crm-portal-wrapper button,
        .memora-crm-app button {
            box-shadow: none;
            letter-spacing: normal;
            text-transform: none;
        }

        /* Nút Primary: + Đặt lịch nhanh (Nền nâu đỏ Memora, chữ trắng, không viền hồng) */
        .memora-crm-portal-wrapper button.memora-crm-btn-primary,
        .memora-crm-app button.memora-crm-btn-primary,
        button#memora-crm-btn-quick-book {
            background-color: #733e1c !important;
            background: #733e1c !important;
            color: #ffffff !important;
            border: 1px solid #733e1c !important;
            border-radius: 8px !important;
            padding: 0 16px !important;
            height: 38px !important;
            min-height: 38px !important;
            line-height: 36px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            box-shadow: 0 1px 2px rgba(115, 62, 28, 0.2) !important;
            white-space: nowrap !important;
            text-transform: none !important;
            outline: none !important;
        }
        .memora-crm-portal-wrapper button.memora-crm-btn-primary:hover,
        .memora-crm-app button.memora-crm-btn-primary:hover,
        button#memora-crm-btn-quick-book:hover {
            background-color: #5d3115 !important;
            background: #5d3115 !important;
            border-color: #5d3115 !important;
            color: #ffffff !important;
        }

        /* Nút Secondary: Chuông, Xuất Excel, Làm mới, In (Nền trắng, viền xám, chữ xám đậm) */
        .memora-crm-portal-wrapper button.memora-crm-btn-secondary,
        .memora-crm-portal-wrapper button.memora-crm-btn-refresh,
        .memora-crm-app button.memora-crm-btn-secondary,
        .memora-crm-app button.memora-crm-btn-refresh,
        button#memora-crm-btn-sound,
        button#memora-crm-btn-export,
        button#memora-crm-btn-refresh,
        button#memora-crm-btn-print {
            background-color: #ffffff !important;
            background: #ffffff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 0 14px !important;
            height: 38px !important;
            min-height: 38px !important;
            line-height: 36px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            box-shadow: none !important;
            white-space: nowrap !important;
            text-transform: none !important;
            outline: none !important;
        }
        .memora-crm-portal-wrapper button.memora-crm-btn-secondary:hover,
        .memora-crm-portal-wrapper button.memora-crm-btn-refresh:hover,
        .memora-crm-app button.memora-crm-btn-secondary:hover,
        .memora-crm-app button.memora-crm-btn-refresh:hover,
        button#memora-crm-btn-sound:hover,
        button#memora-crm-btn-export:hover,
        button#memora-crm-btn-refresh:hover,
        button#memora-crm-btn-print:hover {
            background-color: #f1f5f9 !important;
            background: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        /* Nút Hôm nay (Nền trắng, viền xám, chữ xám) */
        .memora-crm-portal-wrapper button.memora-crm-btn-today,
        .memora-crm-app button.memora-crm-btn-today,
        button#memora-crm-btn-today {
            background-color: #ffffff !important;
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 0 16px !important;
            height: 38px !important;
            min-height: 38px !important;
            line-height: 36px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #334155 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: none !important;
            outline: none !important;
            white-space: nowrap !important;
            text-transform: none !important;
        }
        .memora-crm-portal-wrapper button.memora-crm-btn-today:hover,
        .memora-crm-app button.memora-crm-btn-today:hover,
        button#memora-crm-btn-today:hover {
            background-color: #f8fafc !important;
            background: #f8fafc !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        /* Bộ điều khiển ngày */
        .memora-crm-portal-wrapper .memora-crm-date-ctrl,
        .memora-crm-app .memora-crm-date-ctrl {
            display: inline-flex !important;
            align-items: center !important;
            background-color: #f1f5f9 !important;
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            height: 38px !important;
            padding: 3px !important;
            box-sizing: border-box !important;
            gap: 4px !important;
            overflow: hidden !important;
        }

        .memora-crm-portal-wrapper button.memora-crm-btn-icon,
        .memora-crm-app button.memora-crm-btn-icon {
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 26px !important;
            height: 30px !important;
            min-height: auto !important;
            line-height: 1 !important;
            cursor: pointer !important;
            font-size: 14px !important;
            color: #475569 !important;
            box-shadow: none !important;
            outline: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .memora-crm-portal-wrapper button.memora-crm-btn-icon:hover,
        .memora-crm-app button.memora-crm-btn-icon:hover {
            background-color: #e2e8f0 !important;
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border: none !important;
        }

        .memora-crm-portal-wrapper input.memora-crm-date-input,
        .memora-crm-app input.memora-crm-date-input {
            background-color: #ffffff !important;
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            margin: 0 !important;
            height: 30px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            outline: none !important;
            cursor: pointer !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
            text-align: center !important;
            width: auto !important;
            max-width: 155px !important;
        }

        /* 4 Bộ lọc nằm ngang */
        .memora-crm-portal-wrapper .memora-crm-toolbar__filters,
        .memora-crm-app .memora-crm-toolbar__filters {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 8px !important;
            flex-wrap: wrap !important;
            margin-left: auto !important;
        }

        .memora-crm-portal-wrapper select.memora-crm-select,
        .memora-crm-app select.memora-crm-select {
            width: auto !important;
            min-width: 120px !important;
            max-width: 210px !important;
            height: 38px !important;
            line-height: 1.4 !important;
            padding: 6px 28px 6px 12px !important;
            margin: 0 !important;
            display: inline-block !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            color: #334155 !important;
            background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E") no-repeat right 8px center / 16px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            outline: none !important;
            cursor: pointer !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            box-sizing: border-box !important;
            flex: 0 0 auto !important;
        }

        .memora-crm-portal-wrapper input.memora-crm-search-input,
        .memora-crm-app input.memora-crm-search-input {
            width: 170px !important;
            min-width: 130px !important;
            max-width: 210px !important;
            height: 38px !important;
            line-height: 1.4 !important;
            padding: 6px 12px !important;
            margin: 0 !important;
            display: inline-block !important;
            font-size: 13px !important;
            font-weight: 400 !important;
            color: #1e293b !important;
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            outline: none !important;
            box-sizing: border-box !important;
            flex: 0 0 auto !important;
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
