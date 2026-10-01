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
// DEBUG SHORTCODE TẠM: [memora_debug_booking]
// Đặt lên trang /dat-lich/ để chẩn đoán ACF, xóa sau khi debug xong
//====================================
add_shortcode( 'memora_debug_booking', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<!-- debug: not admin -->';
    }

    $location_id = function_exists( 'memora_resolve_booking_location_id' )
        ? memora_resolve_booking_location_id()
        : 0;

    ob_start();
    echo '<div style="background:#1e1e2e;color:#cdd6f4;font-family:monospace;font-size:13px;padding:20px;border-radius:8px;margin:20px 0;white-space:pre-wrap;">';
    echo "<strong style='color:#89b4fa;'>🔍 MEMORA BOOKING DEBUG</strong>\n\n";

    // 1. GET params
    echo "<strong style='color:#a6e3a1;'>1. $_GET params:</strong>\n";
    echo '   phong_id   = ' . ( $_GET['phong_id'] ?? '(không có)' ) . "\n";
    echo '   phong      = ' . ( $_GET['phong'] ?? '(không có)' ) . "\n";
    echo '   dia_chi_id = ' . ( $_GET['dia_chi_id'] ?? '(không có)' ) . "\n\n";

    // 2. Resolved location_id
    echo "<strong style='color:#a6e3a1;'>2. memora_resolve_booking_location_id():</strong>\n";
    echo '   location_id = ' . $location_id . "\n\n";

    // 3. Taxonomy term info của location_id
    if ( $location_id > 0 ) {
        $term = get_term( $location_id, 'dia-chi' );
        $title = ( $term && ! is_wp_error( $term ) ) ? $term->name : get_the_title( $location_id );
        echo "<strong style='color:#a6e3a1;'>3. Location info (ID=" . $location_id . "):</strong>\n";
        echo '   term_name = ' . ( ( $term && ! is_wp_error( $term ) ) ? $term->name : '(không phải term dia-chi)' ) . "\n";
        echo '   title     = ' . ( $title ?: '(trống)' ) . "\n\n";

        // 4. Raw ACF values
        if ( function_exists( 'get_field' ) ) {
            echo "<strong style='color:#a6e3a1;'>4. Raw get_field() từ post " . $location_id . ":</strong>\n";
            $fields = [
                'thoi_gian_bat_dau_phuc_vu_hanh_chinh',
                'thoi_gian_ket_thuc_phuc_vu_hanh_chinh',
                'dan_cach_gio_chup_anh',
                'cac_goi_chup_anh',
            ];
            foreach ( $fields as $f ) {
                $val = get_field( $f, $location_id );
                echo '   ' . $f . ' = ';
                if ( is_array( $val ) ) {
                    echo '[array, ' . count( $val ) . ' phần tử]';
                } elseif ( $val === null ) {
                    echo '<span style="color:#f38ba8;">NULL</span>';
                } elseif ( $val === '' || $val === false ) {
                    echo '<span style="color:#f38ba8;">EMPTY</span>';
                } else {
                    echo htmlspecialchars( print_r( $val, true ) );
                }
                echo "\n";
            }

            // 5. Raw post_meta
            echo "\n<strong style='color:#a6e3a1;'>5. Raw get_post_meta() từ post " . $location_id . ":</strong>\n";
            foreach ( $fields as $f ) {
                $meta = get_post_meta( $location_id, $f, true );
                echo '   ' . $f . ' = ';
                echo ( $meta !== '' && $meta !== false ) ? htmlspecialchars( print_r( $meta, true ) ) : '<span style="color:#f38ba8;">EMPTY</span>';
                echo "\n";
            }
        } else {
            echo "<span style='color:#f38ba8;'>get_field() không tồn tại (ACF chưa active?)</span>\n";
        }
    } else {
        echo "<strong style='color:#f38ba8;'>⚠️ location_id = 0 → Không xác định được địa chỉ từ URL!</strong>\n";
        echo "   Kiểm tra lại URL có chứa ?dia_chi_id= hoặc ?phong_id= không.\n";
    }

    // 6. Config cuối cùng
    echo "\n<strong style='color:#a6e3a1;'>6. memora_get_booking_config(" . $location_id . "):</strong>\n";
    if ( function_exists( 'memora_get_booking_config' ) ) {
        $cfg = memora_get_booking_config( $location_id );
        echo '   start_time = ' . ( $cfg['start_time'] ?: '<span style="color:#f38ba8;">EMPTY → dùng default 10:00</span>' ) . "\n";
        echo '   end_time   = ' . ( $cfg['end_time']   ?: '<span style="color:#f38ba8;">EMPTY → dùng default 22:00</span>' ) . "\n";
        echo '   interval   = ' . $cfg['interval'] . " phút\n";
        echo '   packages   = ' . ( ! empty( $cfg['packages'] ) ? count( $cfg['packages'] ) . ' gói' : '<span style="color:#f38ba8;">EMPTY → dùng default</span>' ) . "\n";
    }

    echo '</div>';
    return ob_get_clean();
} );
//====================================
// END - DEBUG SHORTCODE
//====================================



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
// START - TRA NGƯỢC: LẤY TERM ID ĐỊA CHỈ TỪ ID PHÒNG CHỤP
//====================================
/**
 * Từ ID một post phòng chụp (post-type: phong-chup-anh),
 * lấy term_id của taxonomy 'dia-chi' gắn với phòng đó.
 *
 * Kết quả được cache tĩnh trong request để tránh query lặp.
 *
 * @param int $room_id  ID post phòng chụp.
 * @return int  term_id của taxonomy 'dia-chi', hoặc 0 nếu không tìm thấy.
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

    // Lấy terms taxonomy 'dia-chi' gắn với post phong-chup-anh
    $terms = wp_get_object_terms( $room_id, 'dia-chi', array( 'fields' => 'ids' ) );

    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        $term_id           = (int) $terms[0];
        $cache[ $room_id ] = $term_id;
        return $term_id;
    }

    $cache[ $room_id ] = 0;
    return 0;
}
//====================================
// END - TRA NGƯỢC: LẤY TERM ID ĐỊA CHỈ TỪ ID PHÒNG CHỤP
//====================================

//====================================
// START - LẤY THÔNG TIN CHI NHÁNH TỪ TAXONOMY 'dia-chi'
//====================================
/**
 * Lấy term_id và tên chi nhánh từ taxonomy 'dia-chi'.
 *
 * @param int $dia_chi_id  term_id của taxonomy 'dia-chi'.
 * @param int $room_id     ID post phòng chụp ảnh (nếu cần tra ngược).
 * @return array           array( 'id' => int, 'name' => string )
 */
function memora_get_dia_chi_info( $dia_chi_id = 0, $room_id = 0 ) {
    $dia_chi_id = intval( $dia_chi_id );
    $room_id    = intval( $room_id );

    // 1. Nếu có dia_chi_id, kiểm tra trực tiếp với taxonomy 'dia-chi'
    if ( $dia_chi_id > 0 ) {
        $term = get_term( $dia_chi_id, 'dia-chi' );
        if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
            return array(
                'id'   => (int) $term->term_id,
                'name' => $term->name,
            );
        }

        // Thử get_term không truyền taxonomy
        $term = get_term( $dia_chi_id );
        if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) && ( $term->taxonomy === 'dia-chi' || $term->taxonomy === 'dia_chi' ) ) {
            return array(
                'id'   => (int) $term->term_id,
                'name' => $term->name,
            );
        }
    }

    // 2. Tra ngược từ phòng chụp (phong-chup-anh) qua taxonomy 'dia-chi'
    if ( $room_id > 0 ) {
        $terms = wp_get_object_terms( $room_id, 'dia-chi' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            return array(
                'id'   => (int) $terms[0]->term_id,
                'name' => $terms[0]->name,
            );
        }
    }

    return array(
        'id'   => $dia_chi_id,
        'name' => '',
    );
}

/**
 * Lấy tên chi nhánh từ taxonomy 'dia-chi'.
 *
 * @param int $dia_chi_id  term_id của taxonomy 'dia-chi'.
 * @param int $room_id     ID post phòng chụp.
 * @return string          Tên chi nhánh hoặc chuỗi rỗng.
 */
function memora_get_dia_chi_name( $dia_chi_id = 0, $room_id = 0 ) {
    $info = memora_get_dia_chi_info( $dia_chi_id, $room_id );
    return $info['name'];
}
//====================================
// END - LẤY THÔNG TIN CHI NHÁNH TỪ TAXONOMY 'dia-chi'
//====================================

//====================================
// START - RESOLVE: XÁC ĐỊNH TERM ID ĐỊA CHỂ TỪ THAM SỐ URL
//====================================
/**
 * Đọc tham số URL (?phong_id=... hoặc ?dia_chi_id=...) và trả về
 * term_id của taxonomy 'dia-chi' tương ứng.
 *
 * Ưu tiên:
 *  1. ?dia_chi_id   → term_id trực tiếp
 *  2. ?phong_id     → lấy taxonomy term gắn với phòng
 *
 * @return int  term_id taxonomy 'dia-chi', hoặc 0 nếu không xác định được.
 */
function memora_resolve_booking_location_id() {
    // 1. Có dia_chi_id trực tiếp trên URL → là term_id, dùng ngay
    if ( ! empty( $_GET['dia_chi_id'] ) && intval( $_GET['dia_chi_id'] ) > 0 ) {
        return intval( $_GET['dia_chi_id'] );
    }

    // 2. Có phong_id → lấy term 'dia-chi' gắn với phòng
    if ( ! empty( $_GET['phong_id'] ) && intval( $_GET['phong_id'] ) > 0 ) {
        $term_id = memora_get_location_id_from_room( intval( $_GET['phong_id'] ) );
        if ( $term_id > 0 ) {
            return $term_id;
        }
    }

    return 0;
}
//====================================
// END - RESOLVE: XÁC ĐỊNH TERM ID ĐỊA CHỂ TỪ THAM SỐ URL
//====================================
/**
 * Lấy cấu hình giờ chụp & gói chụp theo địa chỉ (taxonomy: dia-chi).
 *
 * $location_id là term_id của taxonomy 'dia-chi'.
 * Dùng cú pháp 'term_{id}' để đọc ACF field từ taxonomy term.
 * Fallback về ACF Options Page nếu không tìm thấy hoặc field trống.
 *
 * @param int $location_id  term_id của taxonomy 'dia-chi'. 0 để dùng Options.
 * @return array { start_time, end_time, interval, packages }
 */
function memora_get_booking_config( $location_id = 0 ) {
    $start_time  = '';
    $end_time    = '';
    $interval    = 15;
    $packages    = array();
    $location_id = intval( $location_id );

    if ( function_exists( 'get_field' ) ) {
        // Dùng cú pháp 'term_{id}' — ACF đọc field từ taxonomy term
        $acf_source = $location_id > 0 ? 'term_' . $location_id : null;

        if ( $acf_source ) {
            $start_time = get_field( 'thoi_gian_bat_dau_phuc_vu_hanh_chinh', $acf_source );
            $end_time   = get_field( 'thoi_gian_ket_thuc_phuc_vu_hanh_chinh', $acf_source );
            $interval   = get_field( 'dan_cach_gio_chup_anh', $acf_source );
            $packages   = get_field( 'cac_goi_chup_anh', $acf_source );

            // Fallback get_term_meta nếu get_field trống
            if ( empty( $start_time ) ) {
                $start_time = get_term_meta( $location_id, 'thoi_gian_bat_dau_phuc_vu_hanh_chinh', true );
            }
            if ( empty( $end_time ) ) {
                $end_time = get_term_meta( $location_id, 'thoi_gian_ket_thuc_phuc_vu_hanh_chinh', true );
            }
            if ( empty( $interval ) ) {
                $interval = get_term_meta( $location_id, 'dan_cach_gio_chup_anh', true );
            }
            if ( empty( $packages ) ) {
                $packages = get_term_meta( $location_id, 'cac_goi_chup_anh', true );
                if ( $packages ) $packages = maybe_unserialize( $packages );
            }
        }

        // --- Fallback về ACF Options nếu field term trống ---
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

    $booked_slots = array();
    $sanitized_date = sanitize_text_field( $date );

    // 1. Lấy danh sách giờ đã đặt từ WooCommerce Orders
    if ( function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders( array(
            'limit'        => -1,
            'status'       => array( 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' ),
            'meta_query'   => array(
                'relation' => 'OR',
                array(
                    'key'     => '_booking_date',
                    'value'   => $sanitized_date,
                    'compare' => '=',
                ),
                array(
                    'key'     => '_memora_booking_date',
                    'value'   => $sanitized_date,
                    'compare' => '=',
                ),
            ),
        ) );

        if ( ! empty( $orders ) ) {
            foreach ( $orders as $order ) {
                $time = $order->get_meta( '_booking_time' ) ?: $order->get_meta( '_memora_booking_time' );
                if ( ! empty( $time ) ) {
                    $booked_slots[] = trim( $time );
                }
            }
        }
    }

    // 2. Tương thích ngược: Lấy từ CPT memora_booking cũ (nếu có bài viết cũ)
    $args = array(
        'post_type'      => 'memora_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_booking_date',
                'value'   => $sanitized_date,
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
        $is_taken = false;

        // 1. Kiểm tra trên WooCommerce Orders
        if ( function_exists( 'wc_get_orders' ) ) {
            $existing_orders = wc_get_orders( array(
                'limit'      => 1,
                'return'     => 'ids',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_booking_code',
                        'value'   => $code,
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_memora_booking_code',
                        'value'   => $code,
                        'compare' => '=',
                    ),
                ),
            ) );
            if ( ! empty( $existing_orders ) ) {
                $is_taken = true;
            }
        }

        // 2. Kiểm tra trên memora_booking cũ (nếu có)
        if ( ! $is_taken ) {
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
            if ( ! empty( $existing ) ) {
                $is_taken = true;
            }
        }

        if ( ! $is_taken ) {
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
