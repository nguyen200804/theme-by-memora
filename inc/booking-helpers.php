<?php
/**
 * Booking Helpers
 * 
 * Các hàm tiện ích hỗ trợ hệ thống đặt lịch chụp ảnh Memora:
 * - Lấy cấu hình thời gian & gói chụp từ ACF Options
 * - Tạo danh sách slot khung giờ tự động
 * - Lấy danh sách khung giờ đã được đặt trước trong ngày
 * - Sinh mã Code ngẫu nhiên 4 chữ số không trùng lặp
 * - Định dạng tiền tệ
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - ĐĂNG KÝ OPTIONS PAGE CẤU HÌNH THỜI GIAN
//====================================
add_action( 'acf/init', 'memora_register_booking_options_page' );
function memora_register_booking_options_page() {
    if ( function_exists( 'acf_add_options_page' ) ) {
        if ( ! function_exists( 'acf_get_options_page' ) || ! acf_get_options_page( 'cau-hinh-thoi-gian-chup-anh' ) ) {
            acf_add_options_page( array(
                'page_title' => __( 'Cấu hình thời gian chụp ảnh', 'memora' ),
                'menu_title' => __( 'Cấu hình Lịch Chụp', 'memora' ),
                'menu_slug'  => 'cau-hinh-thoi-gian-chup-anh',
                'capability' => 'manage_options',
                'icon_url'   => 'dashicons-clock',
                'position'   => 29,
                'redirect'   => false,
            ) );
        }
    }
}
//====================================
// END - ĐĂNG KÝ OPTIONS PAGE CẤU HÌNH THỜI GIAN
//====================================





//====================================
// START - LẤY CẤU HÌNH TỪ ACF OPTIONS
//====================================
function memora_get_booking_config() {
    $start_time = '';
    $end_time   = '';
    $interval   = 15;
    $packages   = array();

    if ( function_exists( 'get_field' ) ) {
        $start_time = get_field( 'thoi_gian_bat_dau_phuc_vu_hanh_chinh', 'option' );
        $end_time   = get_field( 'thoi_gian_ket_thuc_phuc_vu_hanh_chinh', 'option' );
        $interval   = get_field( 'dan_cach_gio_chup_anh', 'option' );
        $packages   = get_field( 'cac_goi_chup_anh', 'option' );
    }

    // Dự phòng giá trị mặc định nếu chưa cấu hình trong Admin
    if ( empty( $start_time ) ) {
        $start_time = '10:00';
    }
    if ( empty( $end_time ) ) {
        $end_time = '22:00';
    }
    $interval = ! empty( $interval ) ? intval( $interval ) : 15;
    if ( $interval <= 0 ) {
        $interval = 15;
    }

    // Chuẩn hóa định dạng packages
    if ( empty( $packages ) || ! is_array( $packages ) ) {
        $packages = array(
            array(
                'ten_goi_chup' => '5p',
                'gia_goi_chup' => 200000,
            ),
            array(
                'ten_goi_chup' => '10p',
                'gia_goi_chup' => 350000,
            ),
        );
    }

    return array(
        'start_time' => $start_time,
        'end_time'   => $end_time,
        'interval'   => $interval,
        'packages'   => $packages,
    );
}
//====================================
// END - LẤY CẤU HÌNH TỪ ACF OPTIONS
//====================================





//====================================
// START - TÍNH TOÁN SLOT KHUNG GIỜ TỰ ĐỘNG
//====================================
function memora_generate_time_slots( $start_time = '', $end_time = '', $interval_minutes = 15 ) {
    $config = memora_get_booking_config();

    if ( empty( $start_time ) ) {
        $start_time = $config['start_time'];
    }
    if ( empty( $end_time ) ) {
        $end_time = $config['end_time'];
    }
    if ( empty( $interval_minutes ) || $interval_minutes <= 0 ) {
        $interval_minutes = $config['interval'];
    }

    // Chuyển đổi dạng chuỗi sang giờ:phút
    $start_parts = explode( ':', trim( $start_time ) );
    $end_parts   = explode( ':', trim( $end_time ) );

    $start_h = isset( $start_parts[0] ) ? intval( $start_parts[0] ) : 10;
    $start_m = isset( $start_parts[1] ) ? intval( $start_parts[1] ) : 0;
    $end_h   = isset( $end_parts[0] ) ? intval( $end_parts[0] ) : 22;
    $end_m   = isset( $end_parts[1] ) ? intval( $end_parts[1] ) : 0;

    $start_total = ( $start_h * 60 ) + $start_m;
    $end_total   = ( $end_h * 60 ) + $end_m;

    $slots = array();
    for ( $current = $start_total; $current <= $end_total; $current += $interval_minutes ) {
        $slot_h = floor( $current / 60 );
        $slot_m = $current % 60;
        $slots[] = sprintf( '%02d:%02d', $slot_h, $slot_m );
    }

    return $slots;
}
//====================================
// END - TÍNH TOÁN SLOT KHUNG GIỜ TỰ ĐỘNG
//====================================





//====================================
// START - LẤY DANH SÁCH GIỜ ĐÃ ĐƯỢC ĐẶT TRONG NGÀY
//====================================
function memora_get_booked_slots( $date ) {
    if ( empty( $date ) ) {
        return array();
    }

    $args = array(
        'post_type'      => 'memora_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_booking_date',
                'value'   => sanitize_text_field( $date ),
                'compare' => '=',
            ),
            array(
                'key'     => '_booking_status',
                'value'   => 'cancelled',
                'compare' => '!=',
            ),
        ),
        'fields'         => 'ids',
    );

    $booking_ids = get_posts( $args );
    $booked_slots = array();

    if ( ! empty( $booking_ids ) ) {
        foreach ( $booking_ids as $id ) {
            $time = get_post_meta( $id, '_booking_time', true );
            if ( ! empty( $time ) ) {
                $booked_slots[] = trim( $time );
            }
        }
    }

    return array_unique( $booked_slots );
}
//====================================
// END - LẤY DANH SÁCH GIỜ ĐÃ ĐƯỢC ĐẶT TRONG NGÀY
//====================================





//====================================
// START - SINH MÃ CODE NGẪU NHIÊN 4 CHỮ SỐ
//====================================
function memora_generate_unique_booking_code() {
    $max_attempts = 100;
    $attempt = 0;

    do {
        $attempt++;
        // Sinh ngẫu nhiên từ 1000 đến 9999
        $code = strval( wp_rand( 1000, 9999 ) );

        $existing = get_posts( array(
            'post_type'      => 'memora_booking',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_booking_code',
                    'value'   => $code,
                    'compare' => '=',
                ),
            ),
            'fields'         => 'ids',
        ) );

        if ( empty( $existing ) ) {
            return $code;
        }
    } while ( $attempt < $max_attempts );

    // Nếu sau 100 lần vẫn trùng thì dùng timestamp rút gọn 4 số
    return substr( strval( time() ), -4 );
}
//====================================
// END - SINH MÃ CODE NGẪU NHIÊN 4 CHỮ SỐ
//====================================





//====================================
// START - ĐỊNH DẠNG TIỀN TỆ
//====================================
function memora_format_price( $amount ) {
    $numeric = floatval( $amount );
    return number_format( $numeric, 0, ',', '.' ) . 'vnd';
}
//====================================
// END - ĐỊNH DẠNG TIỀN TỆ
//====================================
