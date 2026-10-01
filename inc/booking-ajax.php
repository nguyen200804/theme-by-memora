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

    // Lấy ID địa chỉ để generate đúng slot theo dia-chi
    $location_id = 0;
    if ( ! empty( $_POST['dia_chi_id'] ) && intval( $_POST['dia_chi_id'] ) > 0 ) {
        // dia_chi_id truyền thẳng → dùng ngay
        $location_id = intval( $_POST['dia_chi_id'] );
    } elseif ( ! empty( $_POST['phong_id'] ) && intval( $_POST['phong_id'] ) > 0 ) {
        // phong_id → tra ngược sang dia-chi cha
        $location_id = memora_get_location_id_from_room( intval( $_POST['phong_id'] ) );
    }

    $all_slots    = memora_generate_time_slots( '', '', 15, $location_id );
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

    $name          = isset( $_POST['name'] )          ? sanitize_text_field( wp_unslash( $_POST['name'] ) )          : '';
    $phone         = isset( $_POST['phone'] )         ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )         : '';
    $contact_other = isset( $_POST['contact_other'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_other'] ) ) : '';
    $date          = isset( $_POST['date'] )          ? sanitize_text_field( wp_unslash( $_POST['date'] ) )          : '';
    $time          = isset( $_POST['time'] )          ? sanitize_text_field( wp_unslash( $_POST['time'] ) )          : '';
    $pkg_name      = isset( $_POST['package_name'] )  ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) )  : '';
    $total_price   = isset( $_POST['total_price'] )   ? floatval( $_POST['total_price'] )   : 0;
    $deposit_price = isset( $_POST['deposit_price'] ) ? floatval( $_POST['deposit_price'] ) : 0;

    // Phòng chụp và địa chỉ
    $phong_id    = isset( $_POST['room_id'] )    ? intval( $_POST['room_id'] )                                          : 0;
    $phong_name  = isset( $_POST['room_name'] )  ? sanitize_text_field( wp_unslash( $_POST['room_name'] ) )            : '';
    $dia_chi_id  = isset( $_POST['dia_chi_id'] ) ? intval( $_POST['dia_chi_id'] )                                      : 0;

    // Nếu không có dia_chi_id nhưng có phong_id → tra ngược
    if ( $dia_chi_id <= 0 && $phong_id > 0 && function_exists( 'memora_get_location_id_from_room' ) ) {
        $dia_chi_id = memora_get_location_id_from_room( $phong_id );
    }

    // Tự điền tên phòng nếu JS không truyền
    if ( empty( $phong_name ) && $phong_id > 0 ) {
        $phong_name = get_the_title( $phong_id );
    }

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

    // Tự động tính thanh toán 100% nếu chưa có
    if ( $deposit_price <= 0 && $total_price > 0 ) {
        $deposit_price = $total_price * 1.0;
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

    // Lưu Meta dữ liệu cơ bản
    update_post_meta( $booking_id, '_booking_code',            $code );
    update_post_meta( $booking_id, '_booking_customer_name',   $name );
    update_post_meta( $booking_id, '_booking_phone',           $phone );
    update_post_meta( $booking_id, '_booking_contact_other',   $contact_other );
    update_post_meta( $booking_id, '_booking_date',            $date );
    update_post_meta( $booking_id, '_booking_time',            $time );
    update_post_meta( $booking_id, '_booking_package_name',    $pkg_name );
    update_post_meta( $booking_id, '_booking_total_price',     $total_price );
    update_post_meta( $booking_id, '_booking_deposit_price',   $deposit_price );
    update_post_meta( $booking_id, '_booking_status',          'deposit_paid' );
    update_post_meta( $booking_id, '_booking_created_at',      current_time( 'mysql' ) );

    // Lưu Phòng chụp
    if ( $phong_id > 0 ) {
        update_post_meta( $booking_id, '_booking_phong_id',   $phong_id );
        update_post_meta( $booking_id, '_booking_phong_name', $phong_name );
        // Tương thích ngược: field cũ _booking_room_name
        update_post_meta( $booking_id, '_booking_room_name',  $phong_name );
    }

    // Lưu Địa chỉ
    if ( $dia_chi_id > 0 ) {
        $dia_chi_name = get_the_title( $dia_chi_id );
        update_post_meta( $booking_id, '_booking_dia_chi_id',   $dia_chi_id );
        update_post_meta( $booking_id, '_booking_dia_chi_name', $dia_chi_name );
    }

    // --- Lưu vào WC Session + Thêm vào WC Cart → redirect sang WC Checkout native ---
    $wc_order_url  = '';
    $thankyou_html = '';

    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->session ) {
        // Đảm bảo có session
        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        // Lưu thông tin booking vào WC session
        WC()->session->set( 'memora_booking_pending_id',   $booking_id );
        WC()->session->set( 'memora_booking_pending_code', $code );
        WC()->session->set( 'memora_booking_pending_data', array(
            'name'          => $name,
            'phone'         => $phone,
            'ig'            => $contact_other,
            'date'          => $date,
            'time'          => $time,
            'pkg_name'      => $pkg_name,
            'total_price'   => $total_price,
            'deposit_price' => $deposit_price,
        ) );

        // Xóa cart cũ và thêm sản phẩm ảo đặt lịch
        if ( function_exists( 'memora_get_or_create_booking_product' ) ) {
            $product_id = memora_get_or_create_booking_product();
            if ( $product_id ) {
                WC()->cart->empty_cart();
                WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'memora_booking_id' => $booking_id ) );
            }
        }

        $wc_order_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
    }

    // Fallback: hiển thị Thank You HTML nếu không có WooCommerce
    if ( empty( $wc_order_url ) && function_exists( 'memora_render_thankyou_html' ) ) {
        $thankyou_html = memora_render_thankyou_html( $code );
    }

    // Trả về dữ liệu thành công
    wp_send_json_success( array(
        'booking_id'    => $booking_id,
        'booking_code'  => $code,
        'date'          => $date,
        'time'          => $time,
        'package_name'  => $pkg_name,
        'total_price'   => memora_format_price( $total_price ),
        'deposit_price' => memora_format_price( $deposit_price ),
        'wc_order_url'  => $wc_order_url,   // ← URL trang thanh toán WC
        'html'          => $thankyou_html,
    ) );
}
//====================================
// END - AJAX TIẾP NHẬN ĐƠN ĐẶT LỊCH
//====================================





//====================================
// START - TẠO WOOCOMMERCE ORDER CHO BOOKING
//====================================
/**
 * Tạo WC Order (phương thức BACS) từ dữ liệu booking.
 * Không cần sản phẩm WC — dùng WC_Order_Item_Fee để ghi số tiền động.
 *
 * @param int   $booking_id  ID của memora_booking post.
 * @param array $data        name, phone, pkg_name, total_price, date, time, code.
 * @return array             { order_id, order_key, order_url } hoặc mảng rỗng nếu lỗi.
 */
function memora_create_wc_order_for_booking( $booking_id, $data ) {
    if ( ! class_exists( 'WooCommerce' ) ) return array();

    $order = wc_create_order( array(
        'status'      => 'pending',
        'customer_id' => 0,
    ) );

    if ( is_wp_error( $order ) ) return array();

    // --- Thêm fee item (không cần sản phẩm WC) ---
    $item = new WC_Order_Item_Fee();
    $item->set_name(
        sprintf( 'Đặt lịch chụp ảnh — %s | %s %s', $data['pkg_name'], $data['date'], $data['time'] )
    );
    $item->set_amount( $data['total_price'] );
    $item->set_total( $data['total_price'] );
    $item->set_tax_status( 'none' );
    $item->set_taxes( array() );
    $order->add_item( $item );

    // --- Thông tin khách hàng ---
    $order->set_billing_first_name( $data['name'] );
    $order->set_billing_phone( $data['phone'] );
    // Placeholder email để WC không báo lỗi (không gửi email thật)
    $safe_email = preg_replace( '/[^a-z0-9]/i', '', strtolower( $data['phone'] ) ) . '@memora.booking';
    $order->set_billing_email( $safe_email );

    // --- Phương thức thanh toán: BACS ---
    $order->set_payment_method( 'bacs' );
    $order->set_payment_method_title( 'Scan VietQR — Chuyển khoản' );
    $order->set_status( 'on-hold' ); // on-hold = chờ thanh toán

    // --- Ghi chú nội bộ liên kết với booking ---
    $order->update_meta_data( '_memora_booking_id',   $booking_id );
    $order->update_meta_data( '_memora_booking_code', $data['code'] );
    $order->add_order_note(
        sprintf(
            'Đặt lịch tự động | Code: #%s | Ngày: %s %s | Gói: %s | Phone: %s',
            $data['code'], $data['date'], $data['time'], $data['pkg_name'], $data['phone']
        )
    );

    // --- Tính tổng và lưu ---
    $order->calculate_totals( false );
    $order->save();

    return array(
        'order_id'  => $order->get_id(),
        'order_key' => $order->get_order_key(),
        'order_url' => $order->get_checkout_order_received_url(),
    );
}
//====================================
// END - TẠO WOOCOMMERCE ORDER CHO BOOKING
//====================================




//====================================
// START - AJAX CHUẨN BỊ SESSION CHO WC CHECKOUT (Flow mới)
// Gọi từ nút "Thanh toán" ở bước xác nhận [choose_time]
// Lưu booking data → WC session, add cart, return WC checkout URL
//====================================
add_action( 'wp_ajax_memora_prepare_checkout_session',        'memora_ajax_prepare_checkout_session' );
add_action( 'wp_ajax_nopriv_memora_prepare_checkout_session', 'memora_ajax_prepare_checkout_session' );

function memora_ajax_prepare_checkout_session() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'memora_booking_action' ) ) {
        wp_send_json_error( array( 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang!' ) );
    }

    $date          = isset( $_POST['date'] )         ? sanitize_text_field( wp_unslash( $_POST['date'] ) )         : '';
    $time          = isset( $_POST['time'] )         ? sanitize_text_field( wp_unslash( $_POST['time'] ) )         : '';
    $pkg_name      = isset( $_POST['package_name'] ) ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) ) : '';
    $total_price   = isset( $_POST['total_price'] )  ? floatval( $_POST['total_price'] )  : 0;
    $deposit_price = isset( $_POST['deposit_price'] )? floatval( $_POST['deposit_price'] ): 0;
    $room_id       = isset( $_POST['room_id'] )      ? intval( $_POST['room_id'] )        : 0;
    $room_name     = isset( $_POST['room_name'] )    ? sanitize_text_field( wp_unslash( $_POST['room_name'] ) )    : '';
    $dia_chi_id    = isset( $_POST['dia_chi_id'] )   ? intval( $_POST['dia_chi_id'] )     : 0;

    // Validation
    if ( empty( $date ) || empty( $time ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng chọn ngày và giờ chụp!' ) );
    }
    if ( empty( $pkg_name ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng chọn gói chụp ảnh!' ) );
    }

    if ( $deposit_price <= 0 && $total_price > 0 ) {
        $deposit_price = $total_price;
    }

    // Tra ngược thông tin phòng / địa chỉ nếu thiếu
    if ( $room_id > 0 && empty( $room_name ) ) {
        $room_name = get_the_title( $room_id );
    }
    if ( $dia_chi_id <= 0 && $room_id > 0 && function_exists( 'memora_get_location_id_from_room' ) ) {
        $dia_chi_id = memora_get_location_id_from_room( $room_id );
    }
    $dia_chi_name = $dia_chi_id > 0 ? get_the_title( $dia_chi_id ) : '';

    // Concurrency check: kiểm tra slot còn trống không
    $booked_slots = memora_get_booked_slots( $date );
    if ( in_array( $time, $booked_slots, true ) ) {
        wp_send_json_error( array( 'message' => 'Rất tiếc! Khung giờ ' . $time . ' ngày ' . $date . ' vừa có người đặt. Vui lòng chọn giờ khác!' ) );
    }

    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
        wp_send_json_error( array( 'message' => 'WooCommerce chưa sẵn sàng. Vui lòng thử lại!' ) );
    }

    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    // Lưu booking data vào WC session (KHÔNG tạo booking post ở đây)
    WC()->session->set( 'memora_booking_pending_data', array(
        'date'         => $date,
        'time'         => $time,
        'pkg_name'     => $pkg_name,
        'total_price'  => $total_price,
        'deposit_price'=> $deposit_price,
        'room_id'      => $room_id,
        'room_name'    => $room_name,
        'dia_chi_id'   => $dia_chi_id,
        'dia_chi_name' => $dia_chi_name,
    ) );
    // Xóa booking_id cũ để flow mới tạo booking từ đầu
    WC()->session->__unset( 'memora_booking_pending_id' );
    WC()->session->__unset( 'memora_booking_pending_code' );

    // Thêm sản phẩm ảo vào WC cart
    if ( function_exists( 'memora_get_or_create_booking_product' ) ) {
        $product_id = memora_get_or_create_booking_product();
        if ( $product_id ) {
            WC()->cart->empty_cart();
            WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'memora_booking_pending' => true ) );
        }
    }

    wp_send_json_success( array(
        'checkout_url' => wc_get_checkout_url(),
    ) );
}
//====================================
// END - AJAX CHUẨN BỊ SESSION CHO WC CHECKOUT
//====================================




//====================================
// START - AJAX TRA CỨU ĐƠN ĐẶT LỊCH
//====================================
add_action( 'wp_ajax_memora_lookup_booking', 'memora_ajax_lookup_booking' );
add_action( 'wp_ajax_nopriv_memora_lookup_booking', 'memora_ajax_lookup_booking' );

function memora_ajax_lookup_booking() {
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $code  = isset( $_POST['code'] )  ? sanitize_text_field( wp_unslash( $_POST['code'] ) )  : '';

    $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
    $clean_code  = trim( $code );

    // Phải có ít nhất 1 trong 2 trường
    if ( empty( $clean_phone ) && empty( $clean_code ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng nhập Số điện thoại hoặc Code chụp để tra cứu nhaaa!' ) );
    }

    $result_data = array();

    // -------------------------------------------------------
    // CASE 1: Có code (tìm theo code, optionally verify phone)
    // -------------------------------------------------------
    if ( ! empty( $clean_code ) ) {
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
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id      = get_the_ID();
            $db_phone     = get_post_meta( $post_id, '_booking_phone', true );
            $clean_db_phone = preg_replace( '/[^0-9]/', '', $db_phone );

            // Nếu có nhập phone → verify khớp; nếu chỉ nhập code → bỏ qua verify
            $phone_ok = empty( $clean_phone )
                || $clean_db_phone === $clean_phone
                || strpos( $clean_db_phone, $clean_phone ) !== false
                || strpos( $clean_phone, $clean_db_phone ) !== false;

            if ( $phone_ok ) {
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

        wp_send_json_success( array( 'data' => $result_data ) );
    }

    // -------------------------------------------------------
    // CASE 2: Chỉ có phone → tìm theo phone, lấy lịch gần nhất
    // -------------------------------------------------------
    $all_bookings_args = array(
        'post_type'      => 'memora_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => array(
            array(
                'key'     => '_booking_status',
                'value'   => 'cancelled',
                'compare' => '!=',
            ),
        ),
    );

    $all_query = new WP_Query( $all_bookings_args );

    if ( ! $all_query->have_posts() ) {
        wp_send_json_error( array( 'message' => 'Không tìm thấy đơn lịch đặt nào. Bạn vui lòng kiểm tra lại nhaaa!' ) );
    }

    $found = false;
    while ( $all_query->have_posts() ) {
        $all_query->the_post();
        $post_id        = get_the_ID();
        $db_phone       = get_post_meta( $post_id, '_booking_phone', true );
        $clean_db_phone = preg_replace( '/[^0-9]/', '', $db_phone );

        if ( $clean_db_phone === $clean_phone
            || strpos( $clean_db_phone, $clean_phone ) !== false
            || strpos( $clean_phone, $clean_db_phone ) !== false ) {
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
            break; // orderby=DESC nên cái đầu tiên match là gần nhất
        }
    }
    wp_reset_postdata();

    if ( ! $found ) {
        wp_send_json_error( array( 'message' => 'Không tìm thấy đơn lịch đặt nào với số điện thoại này. Bạn vui lòng kiểm tra lại nhaaa!' ) );
    }

    wp_send_json_success( array( 'data' => $result_data ) );
}
//====================================
// END - AJAX TRA CỨU ĐƠN ĐẶT LỊCH
//====================================
