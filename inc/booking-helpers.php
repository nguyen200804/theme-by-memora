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
// START - TRA NGƯỢC: LẤY ID ĐỊA CHỈ TỪ ID PHÒNG CHỤP
//====================================
/**
 * Từ ID một post phòng chụp (post-type: phong-chup-anh),
 * tìm ID post dia-chi cha chứa phòng đó qua ACF field "cac-phong-cua-dia-chi".
 *
 * Kết quả được cache tĩnh trong request để tránh query lặp.
 *
 * @param int $room_id  ID post phòng chụp.
 * @return int  ID post dia-chi, hoặc 0 nếu không tìm thấy.
 */
function memora_get_location_id_from_room( $room_id ) {
    $room_id = intval( $room_id );
    if ( $room_id <= 0 ) {
        return 0;
    }

    // Static cache trong cùng request
    static $cache = array();
    if ( isset( $cache[ $room_id ] ) ) {
        return $cache[ $room_id ];
    }

    // Truy vấn tất cả post dia-chi, kiểm tra xem phòng có nằm trong field không
    $dia_chi_posts = get_posts( array(
        'post_type'      => 'dia-chi',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    if ( empty( $dia_chi_posts ) ) {
        $cache[ $room_id ] = 0;
        return 0;
    }

    $field_candidates = array(
        'cac-phong-cua-dia-chi',
        'cac_phong_cua_dia_chi',
    );

    foreach ( $dia_chi_posts as $dc_id ) {
        foreach ( $field_candidates as $field_key ) {
            $rooms_val = function_exists( 'get_field' )
                ? get_field( $field_key, $dc_id )
                : get_post_meta( $dc_id, $field_key, true );

            if ( empty( $rooms_val ) ) {
                continue;
            }

            // Chuẩn hóa thành mảng ID
            $room_ids_in_dc = array();
            if ( is_array( $rooms_val ) ) {
                foreach ( $rooms_val as $r ) {
                    $room_ids_in_dc[] = is_object( $r ) ? intval( $r->ID ) : intval( $r );
                }
            } elseif ( is_object( $rooms_val ) ) {
                $room_ids_in_dc[] = intval( $rooms_val->ID );
            } elseif ( is_numeric( $rooms_val ) ) {
                $room_ids_in_dc[] = intval( $rooms_val );
            }

            if ( in_array( $room_id, $room_ids_in_dc, true ) ) {
                $cache[ $room_id ] = $dc_id;
                return $dc_id;
            }
        }
    }

    $cache[ $room_id ] = 0;
    return 0;
}
//====================================
// END - TRA NGƯỢC: LẤY ID ĐỊA CHỈ TỪ ID PHÒNG CHỤP
//====================================

//====================================
// START - RESOLVE: XÁC ĐỊNH ID ĐỊA CHỈ TỪ THAM SỐ URL
//====================================
/**
 * Đọc tham số URL (?phong_id=... hoặc ?dia_chi_id=...) và trả về
 * ID post dia-chi tương ứng.
 *
 * Ưu tiên:
 *  1. ?dia_chi_id   → dùng trực tiếp (đã là ID dia-chi)
 *  2. ?phong_id     → tra ngược qua ACF field để tìm dia-chi cha
 *
 * @return int  ID post dia-chi, hoặc 0 nếu không xác định được.
 */
function memora_resolve_booking_location_id() {
    // 1. Có dia_chi_id trực tiếp trên URL → dùng ngay
    if ( ! empty( $_GET['dia_chi_id'] ) && intval( $_GET['dia_chi_id'] ) > 0 ) {
        return intval( $_GET['dia_chi_id'] );
    }

    // 2. Có phong_id → tra ngược sang dia-chi cha
    if ( ! empty( $_GET['phong_id'] ) && intval( $_GET['phong_id'] ) > 0 ) {
        $room_id     = intval( $_GET['phong_id'] );
        $location_id = memora_get_location_id_from_room( $room_id );
        if ( $location_id > 0 ) {
            return $location_id;
        }
    }

    return 0;
}
//====================================
// END - RESOLVE: XÁC ĐỊNH ID ĐỊA CHỈ TỪ THAM SỐ URL
//====================================


//====================================
/**
 * Lấy cấu hình giờ chụp & gói chụp theo địa chỉ (post-type: dia-chi).
 *
 * Ưu tiên lấy từ post dia-chi nếu $location_id hợp lệ.
 * Fallback về ACF Options Page nếu không tìm thấy hoặc field trống.
 *
 * @param int|string $location_id  ID của post dia-chi. Để trống để lấy từ Options.
 * @return array { start_time, end_time, interval, packages }
 */
function memora_get_booking_config( $location_id = 0 ) {
    $start_time  = '';
    $end_time    = '';
    $interval    = 15;
    $packages    = array();
    $location_id = intval( $location_id );

    if ( function_exists( 'get_field' ) ) {
        // --- Lấy từ post dia-chi nếu có ID hợp lệ ---
        if ( $location_id > 0 ) {
            $start_time = get_field( 'thoi_gian_bat_dau_phuc_vu_hanh_chinh', $location_id );
            $end_time   = get_field( 'thoi_gian_ket_thuc_phuc_vu_hanh_chinh', $location_id );
            $interval   = get_field( 'dan_cach_gio_chup_anh', $location_id );
            $packages   = get_field( 'cac_goi_chup_anh', $location_id );
        }

        // --- Fallback về ACF Options nếu field trống ---
        if ( empty( $start_time ) ) {
            $start_time = get_field( 'thoi_gian_bat_dau_phuc_vu_hanh_chinh', 'option' );
        }
        if ( empty( $end_time ) ) {
            $end_time = get_field( 'thoi_gian_ket_thuc_phuc_vu_hanh_chinh', 'option' );
        }
        if ( empty( $interval ) ) {
            $interval = get_field( 'dan_cach_gio_chup_anh', 'option' );
        }
        if ( empty( $packages ) ) {
            $packages = get_field( 'cac_goi_chup_anh', 'option' );
        }
    }

    // Dự phòng giá trị mặc định nếu chưa cấu hình
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
        'start_time'  => $start_time,
        'end_time'    => $end_time,
        'interval'    => $interval,
        'packages'    => $packages,
        'location_id' => $location_id,
    );
}
//====================================
// END - LẤY CẤU HÌNH TỪ ACF (THEO ĐỊA CHỈ)
//====================================





//====================================
// START - TÍNH TOÁN SLOT KHUNG GIỜ TỰ ĐỘNG
//====================================
/**
 * Tạo danh sách slot khung giờ.
 *
 * @param string     $start_time        Giờ bắt đầu (HH:MM). Để trống để lấy từ config.
 * @param string     $end_time          Giờ kết thúc (HH:MM). Để trống để lấy từ config.
 * @param int        $interval_minutes  Khoảng cách giữa các slot (phút).
 * @param int|string $location_id       ID post dia-chi để lấy config theo địa chỉ.
 * @return array Mảng chuỗi khung giờ ('HH:MM').
 */
function memora_generate_time_slots( $start_time = '', $end_time = '', $interval_minutes = 15, $location_id = 0 ) {
    $config = memora_get_booking_config( $location_id );

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
