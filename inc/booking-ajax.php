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

    // --- Tạo đơn hàng WooCommerce lưu trực tiếp thông tin đặt lịch ---
    $wc_order_url  = '';
    $thankyou_html = '';
    $order_id      = 0;

    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'bacs';

        $order_result = memora_create_wc_order_for_booking( array(
            'name'           => $name,
            'phone'          => $phone,
            'ig'             => $contact_other,
            'date'           => $date,
            'time'           => $time,
            'pkg_name'       => $pkg_name,
            'total_price'    => $total_price,
            'deposit_price'  => $deposit_price,
            'room_id'        => $phong_id,
            'room_name'      => $phong_name,
            'dia_chi_id'     => $dia_chi_id,
            'dia_chi_name'   => ! empty( $dia_chi_id ) ? get_the_title( $dia_chi_id ) : '',
            'code'           => $code,
            'payment_method' => $payment_method,
        ) );

        if ( ! empty( $order_result['order_id'] ) ) {
            $order_id = $order_result['order_id'];
        }
        if ( ! empty( $order_result['order_url'] ) ) {
            $wc_order_url = $order_result['order_url'];
        }
    }

    // Fallback: hiển thị Thank You HTML nếu không có WooCommerce hoặc không tạo được đơn
    if ( empty( $wc_order_url ) && function_exists( 'memora_render_thankyou_html' ) ) {
        $thankyou_html = memora_render_thankyou_html( $code, $order_id );
    }

    // Trả về dữ liệu thành công
    wp_send_json_success( array(
        'booking_id'    => $order_id,
        'booking_code'  => $code,
        'date'          => $date,
        'time'          => $time,
        'package_name'  => $pkg_name,
        'total_price'   => memora_format_price( $total_price ),
        'deposit_price' => memora_format_price( $deposit_price ),
        'wc_order_url'  => $wc_order_url,
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
 * Tạo WC Order trực tiếp từ dữ liệu booking.
 *
 * @param array $data Thông tin booking: name, phone, ig, date, time, pkg_name, total_price, deposit_price, room_id, dia_chi_id, code, payment_method.
 * @return array      { order_id, order_key, order_url } hoặc mảng rỗng nếu lỗi.
 */
function memora_create_wc_order_for_booking( $data ) {
    if ( ! class_exists( 'WooCommerce' ) ) return array();

    $order = wc_create_order( array(
        'status'      => 'pending',
        'customer_id' => 0,
    ) );

    if ( is_wp_error( $order ) ) return array();

    $pay_amount = ! empty( $data['deposit_price'] ) ? floatval( $data['deposit_price'] ) : ( ! empty( $data['total_price'] ) ? floatval( $data['total_price'] ) : 0 );

    $item_title = sprintf( 'Đặt lịch chụp ảnh — %s | %s %s', $data['pkg_name'], $data['date'], $data['time'] );
    if ( ! empty( $data['room_name'] ) ) {
        $item_title .= ' | Phòng: ' . $data['room_name'];
    }
    if ( ! empty( $data['dia_chi_name'] ) ) {
        $item_title .= ' | ' . $data['dia_chi_name'];
    }

    // --- Thêm sản phẩm đặt lịch vào đơn hàng ---
    $product_id = function_exists( 'memora_get_or_create_booking_product' ) ? memora_get_or_create_booking_product() : 0;
    if ( $product_id && function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_quantity( 1 );
            $item->set_name( $item_title );
            $item->set_subtotal( $pay_amount );
            $item->set_total( $pay_amount );
            $item->update_meta_data( 'Mã đặt lịch', '#' . $data['code'] );
            $item->update_meta_data( 'Ngày chụp', $data['date'] );
            $item->update_meta_data( 'Giờ chụp', $data['time'] );
            $item->update_meta_data( 'Gói chụp', $data['pkg_name'] );
            if ( ! empty( $data['room_name'] ) ) {
                $item->update_meta_data( 'Phòng chụp', $data['room_name'] );
            }
            if ( ! empty( $data['dia_chi_name'] ) ) {
                $item->update_meta_data( 'Chi nhánh', $data['dia_chi_name'] );
            }
            $order->add_item( $item );
        }
    } else {
        $item = new WC_Order_Item_Fee();
        $item->set_name( $item_title );
        $item->set_amount( $pay_amount );
        $item->set_total( $pay_amount );
        $item->set_tax_status( 'none' );
        $item->set_taxes( array() );
        $order->add_item( $item );
    }

    // --- Thông tin khách hàng ---
    $order->set_billing_first_name( $data['name'] );
    $order->set_billing_phone( $data['phone'] );
    // Placeholder email để WC không báo lỗi
    $safe_email = preg_replace( '/[^a-z0-9]/i', '', strtolower( $data['phone'] ) ) . '@memora.booking';
    $order->set_billing_email( $safe_email );

    // --- Phương thức thanh toán: BACS / VietQR ---
    $payment_method = ! empty( $data['payment_method'] ) ? $data['payment_method'] : 'bacs';
    $gateways       = ( function_exists( 'WC' ) && WC()->payment_gateways ) ? WC()->payment_gateways()->payment_gateways() : array();
    $chosen_gateway = isset( $gateways[ $payment_method ] ) ? $gateways[ $payment_method ] : null;
    $payment_title  = $chosen_gateway ? $chosen_gateway->get_title() : 'Scan VietQR — Chuyển khoản';

    $order->set_payment_method( $payment_method );
    $order->set_payment_method_title( $payment_title );

    // --- Lưu toàn bộ dữ liệu đặt lịch vào WC Order meta ---
    $order->update_meta_data( '_memora_booking_code',   $data['code'] );
    $order->update_meta_data( '_booking_code',          $data['code'] );
    $order->update_meta_data( '_booking_customer_name', $data['name'] );
    $order->update_meta_data( '_booking_phone',         $data['phone'] );
    $order->update_meta_data( '_booking_date',          $data['date'] );
    $order->update_meta_data( '_memora_booking_date',   $data['date'] );
    $order->update_meta_data( '_booking_time',          $data['time'] );
    $order->update_meta_data( '_memora_booking_time',   $data['time'] );
    $order->update_meta_data( '_booking_package_name',  $data['pkg_name'] );
    $order->update_meta_data( '_booking_total_price',   $data['total_price'] );
    $order->update_meta_data( '_booking_deposit_price', $pay_amount );
    $order->update_meta_data( '_booking_created_at',    current_time( 'mysql' ) );

    if ( ! empty( $data['ig'] ) ) {
        $order->update_meta_data( '_memora_ig',             $data['ig'] );
        $order->update_meta_data( '_booking_contact_other', $data['ig'] );
    }
    if ( ! empty( $data['room_id'] ) ) {
        $order->update_meta_data( '_booking_phong_id',   $data['room_id'] );
        $order->update_meta_data( '_booking_phong_name', $data['room_name'] ?? '' );
        $order->update_meta_data( '_booking_room_name',  $data['room_name'] ?? '' );
    }
    if ( ! empty( $data['dia_chi_id'] ) ) {
        $order->update_meta_data( '_booking_dia_chi_id',   $data['dia_chi_id'] );
        $order->update_meta_data( '_booking_dia_chi_name', $data['dia_chi_name'] ?? '' );
    }

    $order->add_order_note(
        sprintf(
            'Đặt lịch tự động | Code: #%s | Ngày: %s %s | Gói: %s | SĐT: %s | Liên hệ: %s',
            $data['code'], $data['date'], $data['time'], $data['pkg_name'], $data['phone'], $data['ig'] ?? ''
        )
    );

    // --- Tính tổng và lưu trạng thái on-hold (Chờ chuyển khoản VietQR) ---
    $order->calculate_totals( false );
    $order->set_status( 'on-hold', 'Đơn đặt lịch mới chờ chuyển khoản VietQR.' );
    $order->save();

    // Dọn dẹp giỏ hàng sau khi tạo đơn thành công
    if ( function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->empty_cart();
    }
    if ( function_exists( 'WC' ) && WC()->session ) {
        WC()->session->__unset( 'memora_booking_pending_id' );
        WC()->session->__unset( 'memora_booking_pending_code' );
        WC()->session->__unset( 'memora_booking_pending_data' );
    }

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

    // Trang Checkout: Lấy trực tiếp từ cấu hình WooCommerce (Settings > Advanced > Checkout page)
    $redirect_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
    if ( empty( $redirect_url ) && ! empty( $_POST['checkout_url'] ) && strpos( $_POST['checkout_url'], '/thanh-toan/' ) === false ) {
        $redirect_url = esc_url_raw( wp_unslash( $_POST['checkout_url'] ) );
    }
    if ( empty( $redirect_url ) ) {
        $redirect_url = home_url( '/checkout/' );
    }

    wp_send_json_success( array(
        'checkout_url' => $redirect_url,
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

    // Helper dịch trạng thái đơn hàng sang tiếng Việt thân thiện
    $get_status_label = function( $wc_status ) {
        switch ( $wc_status ) {
            case 'pending':
            case 'on-hold':
                return 'Chờ thanh toán';
            case 'processing':
                return 'Đã thanh toán / Chờ chụp';
            case 'completed':
                return 'Đã hoàn thành';
            case 'cancelled':
                return 'Đã hủy';
            default:
                return function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $wc_status ) : $wc_status;
        }
    };

    // -------------------------------------------------------
    // CASE 1: Có code (tìm theo code, verify phone nếu có)
    // -------------------------------------------------------
    if ( ! empty( $clean_code ) ) {
        // 1.1 Tìm trong WooCommerce Orders
        if ( function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array(
                'limit'      => 1,
                'status'     => array( 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' ),
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_booking_code',
                        'value'   => $clean_code,
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_memora_booking_code',
                        'value'   => $clean_code,
                        'compare' => '=',
                    ),
                ),
            ) );

            if ( ! empty( $orders ) ) {
                $order    = $orders[0];
                $db_phone = $order->get_billing_phone() ?: $order->get_meta( '_booking_phone' );
                $clean_db = preg_replace( '/[^0-9]/', '', $db_phone );

                $phone_ok = empty( $clean_phone )
                    || $clean_db === $clean_phone
                    || strpos( $clean_db, $clean_phone ) !== false
                    || strpos( $clean_phone, $clean_db ) !== false;

                if ( ! $phone_ok ) {
                    wp_send_json_error( array( 'message' => 'Số điện thoại không khớp với mã Code này. Bạn vui lòng kiểm tra lại số điện thoại nhaaa!' ) );
                }

                $total = $order->get_meta( '_booking_total_price' ) ?: $order->get_total();
                $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                if ( empty( $name ) ) {
                    $name = $order->get_meta( '_booking_customer_name' );
                }

                $result_data = array(
                    'code'         => $clean_code,
                    'name'         => $name,
                    'phone'        => $db_phone,
                    'date'         => $order->get_meta( '_booking_date' ) ?: $order->get_meta( '_memora_booking_date' ),
                    'time'         => $order->get_meta( '_booking_time' ) ?: $order->get_meta( '_memora_booking_time' ),
                    'package_name' => $order->get_meta( '_booking_package_name' ) ?: 'Gói chụp Memora',
                    'total_price'  => function_exists( 'memora_format_price' ) ? memora_format_price( $total ) : number_format( floatval( $total ), 0, ',', '.' ) . 'vnd',
                    'status'       => $get_status_label( $order->get_status() ),
                );

                wp_send_json_success( array( 'data' => $result_data ) );
            }
        }

        // 1.2 Fallback: Tìm trong CPT memora_booking cũ
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
            $post_id        = get_the_ID();
            $db_phone       = get_post_meta( $post_id, '_booking_phone', true );
            $clean_db_phone = preg_replace( '/[^0-9]/', '', $db_phone );

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
                    'total_price'  => function_exists( 'memora_format_price' ) ? memora_format_price( get_post_meta( $post_id, '_booking_total_price', true ) ) : '',
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
    // 2.1 Tìm trong WooCommerce Orders
    if ( function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders( array(
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => array( 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' ),
        ) );

        if ( ! empty( $orders ) ) {
            foreach ( $orders as $order ) {
                $db_phone = $order->get_billing_phone() ?: $order->get_meta( '_booking_phone' );
                $clean_db = preg_replace( '/[^0-9]/', '', $db_phone );

                if ( ! empty( $clean_db ) && ( $clean_db === $clean_phone || strpos( $clean_db, $clean_phone ) !== false || strpos( $clean_phone, $clean_db ) !== false ) ) {
                    $code = $order->get_meta( '_memora_booking_code' ) ?: $order->get_meta( '_booking_code' );
                    if ( empty( $code ) ) continue;

                    $total = $order->get_meta( '_booking_total_price' ) ?: $order->get_total();
                    $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    if ( empty( $name ) ) {
                        $name = $order->get_meta( '_booking_customer_name' );
                    }

                    $result_data = array(
                        'code'         => $code,
                        'name'         => $name,
                        'phone'        => $db_phone,
                        'date'         => $order->get_meta( '_booking_date' ) ?: $order->get_meta( '_memora_booking_date' ),
                        'time'         => $order->get_meta( '_booking_time' ) ?: $order->get_meta( '_memora_booking_time' ),
                        'package_name' => $order->get_meta( '_booking_package_name' ) ?: 'Gói chụp Memora',
                        'total_price'  => function_exists( 'memora_format_price' ) ? memora_format_price( $total ) : number_format( floatval( $total ), 0, ',', '.' ) . 'vnd',
                        'status'       => $get_status_label( $order->get_status() ),
                    );

                    wp_send_json_success( array( 'data' => $result_data ) );
                }
            }
        }
    }

    // 2.2 Fallback: Tìm trong CPT memora_booking cũ
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
                'total_price'  => function_exists( 'memora_format_price' ) ? memora_format_price( get_post_meta( $post_id, '_booking_total_price', true ) ) : '',
                'status'       => get_post_meta( $post_id, '_booking_status', true ),
            );
            break;
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
