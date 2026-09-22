<?php
/**
 * Booking AJAX Handlers
 * 
 * Tiếp nhận và xử lý các yêu cầu AJAX:
 * 1. Lấy danh sách khung giờ trống theo ngày
 * 2. Xác thực và lưu thông tin đặt lịch vào database
 * 3. Tra cứu thông tin đặt lịch theo Số điện thoại và Mã code 4 số
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - AJAX LẤY KHUNG GIỜ THEO NGÀY
//====================================
add_action( 'wp_ajax_memora_get_slots', 'memora_ajax_get_slots' );
add_action( 'wp_ajax_nopriv_memora_get_slots', 'memora_ajax_get_slots' );

function memora_ajax_get_slots() {
    $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

    if ( empty( $date ) ) {
        wp_send_json_error( array( 'message' => 'Ngày không hợp lệ' ) );
    }

    $all_slots    = memora_generate_time_slots();
    $booked_slots = memora_get_booked_slots( $date );

    // Kiểm tra giờ quá khứ nếu chọn ngày hôm nay
    $today_d_m_y = current_time( 'd/m/Y' );
    $current_h_i = current_time( 'H:i' );

    $slots_data = array();
    foreach ( $all_slots as $slot ) {
        $is_booked = in_array( $slot, $booked_slots, true );
        $is_past   = false;

        // Nếu ngày được chọn là hôm nay, vô hiệu hóa giờ đã qua
        if ( $date === $today_d_m_y && $slot <= $current_h_i ) {
            $is_past = true;
        }

        $available = ( ! $is_booked && ! $is_past );

        $slots_data[] = array(
            'time'      => $slot,
            'available' => $available,
            'booked'    => $is_booked,
            'past'      => $is_past,
        );
    }

    wp_send_json_success( array(
        'date'  => $date,
        'slots' => $slots_data,
    ) );
}
//====================================
// END - AJAX LẤY KHUNG GIỜ THEO NGÀY
//====================================





//====================================
// START - AJAX TIẾP NHẬN ĐƠN ĐẶT LỊCH
//====================================
add_action( 'wp_ajax_memora_submit_booking', 'memora_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_memora_submit_booking', 'memora_ajax_submit_booking' );

function memora_ajax_submit_booking() {
    // Xác thực Nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'memora_booking_action' ) ) {
        wp_send_json_error( array( 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang!' ) );
    }

    $name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $contact_other = isset( $_POST['contact_other'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_other'] ) ) : '';
    $date          = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
    $time          = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
    $pkg_name      = isset( $_POST['package_name'] ) ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) ) : '';
    $total_price   = isset( $_POST['total_price'] ) ? floatval( $_POST['total_price'] ) : 0;
    $deposit_price = isset( $_POST['deposit_price'] ) ? floatval( $_POST['deposit_price'] ) : 0;

    // Kiểm tra dữ liệu bắt buộc
    if ( empty( $name ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng nhập họ và tên của bạn nhaaa!' ) );
    }
    if ( empty( $phone ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng nhập số điện thoại để liên hệ!' ) );
    }
    if ( empty( $date ) || empty( $time ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng chọn ngày và giờ chụp!' ) );
    }
    if ( empty( $pkg_name ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng chọn gói chụp ảnh!' ) );
    }

    // Tự động tính cọc 50% nếu chưa có
    if ( $deposit_price <= 0 && $total_price > 0 ) {
        $deposit_price = $total_price * 0.5;
    }

    // Concurrency Check: Kiểm tra lại slot giờ tránh xung đột đặt cùng lúc
    $booked_slots = memora_get_booked_slots( $date );
    if ( in_array( $time, $booked_slots, true ) ) {
        wp_send_json_error( array( 'message' => 'Rất tiếc! Khung giờ ' . $time . ' ngày ' . $date . ' vừa có người đặt trước. Bạn vui lòng chọn giờ khác nhé!' ) );
    }

    // Sinh mã code 4 số ngẫu nhiên duy nhất
    $code = memora_generate_unique_booking_code();

    // Lưu vào Custom Post Type 'memora_booking'
    $post_title = sprintf( '#%s - %s - %s %s', $code, $name, $date, $time );
    $post_data  = array(
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_type'   => 'memora_booking',
    );

    $booking_id = wp_insert_post( $post_data );

    if ( is_wp_error( $booking_id ) || ! $booking_id ) {
        wp_send_json_error( array( 'message' => 'Có lỗi xảy ra khi lưu thông tin. Vui lòng thử lại!' ) );
    }

    // Lưu Meta dữ liệu
    update_post_meta( $booking_id, '_booking_code', $code );
    update_post_meta( $booking_id, '_booking_customer_name', $name );
    update_post_meta( $booking_id, '_booking_phone', $phone );
    update_post_meta( $booking_id, '_booking_contact_other', $contact_other );
    update_post_meta( $booking_id, '_booking_date', $date );
    update_post_meta( $booking_id, '_booking_time', $time );
    update_post_meta( $booking_id, '_booking_package_name', $pkg_name );
    update_post_meta( $booking_id, '_booking_total_price', $total_price );
    update_post_meta( $booking_id, '_booking_deposit_price', $deposit_price );
    update_post_meta( $booking_id, '_booking_status', 'deposit_paid' );
    update_post_meta( $booking_id, '_booking_created_at', current_time( 'mysql' ) );

    // Trả về dữ liệu thành công
    wp_send_json_success( array(
        'booking_id'   => $booking_id,
        'booking_code' => $code,
        'date'         => $date,
        'time'         => $time,
        'package_name' => $pkg_name,
        'total_price'  => memora_format_price( $total_price ),
        'deposit_price'=> memora_format_price( $deposit_price ),
    ) );
}
//====================================
// END - AJAX TIẾP NHẬN ĐƠN ĐẶT LỊCH
//====================================





//====================================
// START - AJAX TRA CỨU ĐƠN ĐẶT LỊCH
//====================================
add_action( 'wp_ajax_memora_lookup_booking', 'memora_ajax_lookup_booking' );
add_action( 'wp_ajax_nopriv_memora_lookup_booking', 'memora_ajax_lookup_booking' );

function memora_ajax_lookup_booking() {
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

    if ( empty( $phone ) || empty( $code ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng nhập đầy đủ Số điện thoại và Code chụp để tra cứu nhaaa!' ) );
    }

    // Chuẩn hóa số điện thoại loại bỏ khoảng trắng, dấu chấm
    $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
    $clean_code  = trim( $code );

    $args = array(
        'post_type'      => 'memora_booking',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_booking_code',
                'value'   => $clean_code,
                'compare' => '=',
            ),
            array(
                'key'     => '_booking_status',
                'value'   => 'cancelled',
                'compare' => '!=',
            ),
        ),
    );

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        wp_send_json_error( array( 'message' => 'Không tìm thấy đơn lịch đặt nào với Code ' . esc_html( $clean_code ) . '. Bạn vui lòng kiểm tra lại nhaaa!' ) );
    }

    $found = false;
    $result_data = array();

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id   = get_the_ID();
        $db_phone  = get_post_meta( $post_id, '_booking_phone', true );
        $clean_db_phone = preg_replace( '/[^0-9]/', '', $db_phone );

        // Kiểm tra khớp số điện thoại
        if ( $clean_db_phone === $clean_phone || strpos( $clean_db_phone, $clean_phone ) !== false || strpos( $clean_phone, $clean_db_phone ) !== false ) {
            $found = true;
            $result_data = array(
                'code'         => get_post_meta( $post_id, '_booking_code', true ),
                'name'         => get_post_meta( $post_id, '_booking_customer_name', true ),
                'phone'        => $db_phone,
                'date'         => get_post_meta( $post_id, '_booking_date', true ),
                'time'         => get_post_meta( $post_id, '_booking_time', true ),
                'package_name' => get_post_meta( $post_id, '_booking_package_name', true ),
                'total_price'  => memora_format_price( get_post_meta( $post_id, '_booking_total_price', true ) ),
                'status'       => get_post_meta( $post_id, '_booking_status', true ),
            );
            break;
        }
    }
    wp_reset_postdata();

    if ( ! $found ) {
        wp_send_json_error( array( 'message' => 'Số điện thoại không khớp với mã Code này. Bạn vui lòng kiểm tra lại số điện thoại nhaaa!' ) );
    }

    wp_send_json_success( array(
        'data' => $result_data,
    ) );
}
//====================================
// END - AJAX TRA CỨU ĐƠN ĐẶT LỊCH
//====================================
