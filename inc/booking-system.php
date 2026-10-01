<?php
/**
 * Memora Booking System Main Entry
 * 
 * Bộ xử lý trung tâm cho toàn bộ tính năng Đặt lịch chụp ảnh:
 * - Tự động nạp các modules chức năng con
 * - Enqueue stylesheet và scripts kèm Nonce bảo mật AJAX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - NẠP CÁC TẬP TIN CON CỦA HỆ THỐNG ĐẶT LỊCH
//====================================
$booking_inc_dir = trailingslashit( get_stylesheet_directory() ) . 'inc/';

// 1. Nạp hàm tiện ích, cấu hình ACF & thuật toán slots
if ( file_exists( $booking_inc_dir . 'booking-helpers.php' ) ) {
    require_once $booking_inc_dir . 'booking-helpers.php';
}

// 2. Nạp Custom Post Type quản trị
if ( file_exists( $booking_inc_dir . 'booking-post-type.php' ) ) {
    require_once $booking_inc_dir . 'booking-post-type.php';
}

// 3. Nạp xử lý AJAX
if ( file_exists( $booking_inc_dir . 'booking-ajax.php' ) ) {
    require_once $booking_inc_dir . 'booking-ajax.php';
}

// 4. Nạp các Shortcodes
if ( file_exists( $booking_inc_dir . 'booking-shortcodes.php' ) ) {
    require_once $booking_inc_dir . 'booking-shortcodes.php';
}
//====================================
// END - NẠP CÁC TẬP TIN CON CỦA HỆ THỐNG ĐẶT LỊCH
//====================================





//====================================
// START - ENQUEUE SCRIPTS VÀ STYLES CHO BOOKING
//====================================
add_action( 'wp_enqueue_scripts', 'memora_booking_enqueue_assets' );
function memora_booking_enqueue_assets() {
    $theme_dir = trailingslashit( get_stylesheet_directory() );
    $theme_uri = trailingslashit( get_stylesheet_directory_uri() );

    $css_version = file_exists( $theme_dir . 'assets/css/booking.css' ) ? filemtime( $theme_dir . 'assets/css/booking.css' ) : '1.0.0';
    $js_version  = file_exists( $theme_dir . 'assets/js/booking.js' ) ? filemtime( $theme_dir . 'assets/js/booking.js' ) : '1.0.0';

    // Enqueue Stylesheet
    wp_enqueue_style(
        'memora-booking-style',
        $theme_uri . 'assets/css/booking.css',
        array(),
        $css_version
    );

    // Enqueue Script
    wp_enqueue_script(
        'memora-booking-script',
        $theme_uri . 'assets/js/booking.js',
        array( 'jquery' ),
        $js_version,
        true
    );

    // Phát hiện dia-chi để truyền sang JS (lưu sessionStorage)
    $current_dia_chi_id = 0;
    if ( function_exists( 'get_queried_object_id' ) ) {
        $qid       = get_queried_object_id();
        $qid_type  = $qid > 0 ? get_post_type( $qid ) : '';

        if ( $qid_type === 'dia-chi' ) {
            // Đang xem trang dia-chi → dùng trực tiếp
            $current_dia_chi_id = $qid;
        } elseif ( $qid > 0 && function_exists( 'memora_get_location_id_from_room' ) ) {
            // Đang xem trang phòng (phong-chup-anh) → tra ngược sang dia-chi cha
            $found = memora_get_location_id_from_room( $qid );
            if ( $found > 0 ) {
                $current_dia_chi_id = $found;
            }
        }
    }

    // Localize Script truyền URL AJAX và Nonce
    wp_localize_script(
        'memora-booking-script',
        'memora_booking_vars',
        array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'memora_booking_action' ),
            'dia_chi_id' => $current_dia_chi_id, // ID trang dia-chi đang xem (0 nếu không phải)
        )
    );
}
//====================================
// END - ENQUEUE SCRIPTS VÀ STYLES CHO BOOKING
//====================================
