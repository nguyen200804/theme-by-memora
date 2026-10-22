<?php
/**
 * Memora Booking CRM - API & Backend Processing Engine
 *
 * Xử lý toàn bộ các yêu cầu AJAX phục vụ bàn làm việc CRM:
 * 1. Lấy dữ liệu ma trận lịch chụp đa phòng theo ngày & cơ sở (memora_crm_get_schedule)
 * 2. Cập nhật nhanh trực tiếp từng trường (memora_crm_update_field)
 * 3. Tạo nhanh đặt lịch tại quầy (memora_crm_quick_book)
 * 4. Chuyển phòng / đổi giờ chụp (memora_crm_move_booking)
 * 5. Hủy lịch đặt (memora_crm_cancel_booking)
 *
 * @package Memora
 * @subpackage BookingCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Kiểm tra quyền hạn và nonce cho các tác vụ CRM
 */
function memora_crm_verify_request() {
    // 1. Kiểm tra quyền hạn
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Vui lòng đăng nhập để thao tác CRM.' ), 401 );
    }

    $can_access = ( function_exists( 'memora_crm_user_has_access' ) && memora_crm_user_has_access() )
               || current_user_can( 'manage_woocommerce' ) 
               || current_user_can( 'edit_shop_orders' ) 
               || current_user_can( 'manage_options' );

    // Filter cho phép custom role nhân viên nếu có
    $can_access = apply_filters( 'memora_crm_user_can_access', $can_access, get_current_user_id() );

    if ( ! $can_access ) {
        wp_send_json_error( array( 'message' => 'Bạn không có quyền truy cập vào CRM này.' ), 403 );
    }

    // 2. Xác thực Nonce
    $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'memora_crm_action' ) ) {
        wp_send_json_error( array( 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang!' ), 403 );
    }
}

// =====================================================================
// 1. API: LẤY MA TRẬN LỊCH CHỤP THEO NGÀY & CHI NHÁNH
// =====================================================================
add_action( 'wp_ajax_memora_crm_get_schedule', 'memora_crm_ajax_get_schedule' );
function memora_crm_ajax_get_schedule() {
    memora_crm_verify_request();

    // Chuẩn hóa ngày yêu cầu
    $raw_date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
    if ( empty( $raw_date ) ) {
        $raw_date = current_time( 'Y-m-d' );
    }

    // Hỗ trợ cả 2 định dạng ngày Y-m-d và d/m/Y
    $date_obj = false;
    if ( strpos( $raw_date, '/' ) !== false ) {
        $date_obj = DateTime::createFromFormat( 'd/m/Y', $raw_date );
    } else {
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $raw_date );
    }

    if ( ! $date_obj ) {
        $date_obj = new DateTime( current_time( 'mysql' ) );
    }

    $date_ymd = $date_obj->format( 'Y-m-d' );
    $date_dmy = $date_obj->format( 'd/m/Y' );

    // Lấy chi nhánh được chọn
    $dia_chi_id = isset( $_POST['dia_chi_id'] ) ? intval( $_POST['dia_chi_id'] ) : 0;

    // Lấy danh sách tất cả các chi nhánh đang hoạt động để làm filter
    $locations_list = array();
    $terms = get_terms( array(
        'taxonomy'   => 'dia-chi',
        'hide_empty' => false,
    ) );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $term ) {
            $locations_list[] = array(
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
    }

    // Nếu chưa chọn chi nhánh cụ thể, lấy chi nhánh đầu tiên nếu có
    if ( $dia_chi_id <= 0 && ! empty( $locations_list ) ) {
        $dia_chi_id = $locations_list[0]['id'];
    }

    // Lấy danh sách phòng chụp của chi nhánh này
    $rooms = array();
    $room_args = array(
        'post_type'      => 'phong-chup-anh',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    );

    if ( $dia_chi_id > 0 ) {
        $room_args['tax_query'] = array(
            array(
                'taxonomy' => 'dia-chi',
                'field'    => 'term_id',
                'terms'    => $dia_chi_id,
            ),
        );
    }

    $room_posts = get_posts( $room_args );

    // Nếu không query được theo taxonomy hoặc chưa gán term, fallback lấy toàn bộ phòng
    if ( empty( $room_posts ) && $dia_chi_id > 0 ) {
        unset( $room_args['tax_query'] );
        $room_posts = get_posts( $room_args );
    }

    if ( ! empty( $room_posts ) ) {
        foreach ( $room_posts as $rp ) {
            $rooms[] = array(
                'id'    => $rp->ID,
                'name'  => $rp->post_title,
                'slug'  => $rp->post_name,
            );
        }
    }

    // Nếu hệ thống hoàn toàn chưa tạo bài viết phòng chụp nào, tự động tạo phòng mẫu Room 1 & Room 2
    if ( empty( $rooms ) ) {
        $rooms = array(
            array( 'id' => 1, 'name' => 'Room 1', 'slug' => 'room-1' ),
            array( 'id' => 2, 'name' => 'Room 2', 'slug' => 'room-2' ),
        );
    }

    // Cấu hình khung thời gian (Start, End, Interval)
    $config_start = '09:00';
    $config_end   = '22:00';
    $config_interval = 5; // Mặc định 5 phút theo biểu mẫu thực tế của studio

    if ( function_exists( 'memora_get_booking_config' ) ) {
        $cfg = memora_get_booking_config( $dia_chi_id );
        if ( ! empty( $cfg['start_time'] ) ) $config_start = $cfg['start_time'];
        if ( ! empty( $cfg['end_time'] ) )   $config_end   = $cfg['end_time'];
    }

    // Cho phép override interval từ request (5, 10, 15 phút)
    if ( ! empty( $_POST['interval'] ) && in_array( intval( $_POST['interval'] ), array( 5, 10, 15, 20, 30 ), true ) ) {
        $config_interval = intval( $_POST['interval'] );
    }

    // Tạo danh sách các slot thời gian trong ngày
    $time_slots = array();
    $start_dt = DateTime::createFromFormat( 'H:i', $config_start ) ?: DateTime::createFromFormat( 'H:i', '09:00' );
    $end_dt   = DateTime::createFromFormat( 'H:i', $config_end )   ?: DateTime::createFromFormat( 'H:i', '22:00' );

    $curr_dt = clone $start_dt;
    while ( $curr_dt <= $end_dt ) {
        $time_slots[] = $curr_dt->format( 'H:i' );
        $curr_dt->modify( "+{$config_interval} minutes" );
    }

    // Query các đơn đặt lịch trong ngày
    $query_dates = array_unique( array( $date_ymd, $date_dmy ) );
    $orders = wc_get_orders( array(
        'limit'        => -1,
        'status'       => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled' ),
        'meta_query'   => array(
            'relation' => 'OR',
            array(
                'key'     => '_booking_date',
                'value'   => $query_dates,
                'compare' => 'IN',
            ),
            array(
                'key'     => '_memora_booking_date',
                'value'   => $query_dates,
                'compare' => 'IN',
            ),
        ),
    ) );

    // Ánh xạ ma trận [room_id][time_slot]
    $matrix = array();
    foreach ( $rooms as $r ) {
        $matrix[ (string) $r['id'] ] = array();
    }

    // Thống kê nhanh trong ngày
    $stats = array(
        'total_bookings' => 0,
        'completed_shoot'=> 0,
        'completed_print'=> 0,
        'cancelled'      => 0,
        'revenue_total'  => 0,
    );

    if ( ! empty( $orders ) ) {
        foreach ( $orders as $order ) {
            $data = memora_crm_get_order_data( $order );
            if ( ! $data ) continue;

            $booking_time = trim( $data['time'] );
            if ( empty( $booking_time ) ) continue;

            // Lọc theo chi nhánh: nếu đơn hàng thuộc chi nhánh khác thì bỏ qua
            $order_dia_chi = intval( $data['dia_chi_id'] );
            if ( $order_dia_chi <= 0 && ! empty( $data['room_id'] ) && function_exists( 'memora_get_location_id_from_room' ) ) {
                $order_dia_chi = memora_get_location_id_from_room( $data['room_id'] );
            }
            if ( $dia_chi_id > 0 && $order_dia_chi > 0 && $order_dia_chi !== $dia_chi_id ) {
                continue;
            }

            // Xác định room_id
            $order_room_id = $data['room_id'];
            if ( $order_room_id <= 0 || ! isset( $matrix[ (string) $order_room_id ] ) ) {
                // Thử khớp theo tên phòng (VD: "Room 1")
                $matched_id = 0;
                foreach ( $rooms as $r ) {
                    if ( ! empty( $data['room_name'] ) && stripos( $r['name'], $data['room_name'] ) !== false ) {
                        $matched_id = $r['id'];
                        break;
                    }
                }
                if ( $matched_id > 0 ) {
                    $order_room_id = $matched_id;
                } else {
                    // Mặc định gán vào phòng đầu tiên
                    $order_room_id = $rooms[0]['id'];
                }
            }

            // Đưa slot vào mốc thời gian nếu chưa có trong list
            if ( ! in_array( $booking_time, $time_slots, true ) ) {
                $time_slots[] = $booking_time;
                sort( $time_slots );
            }

            $matrix[ (string) $order_room_id ][ $booking_time ] = $data;

            // Thống kê
            $stats['total_bookings']++;
            if ( 'da_chup' === $data['shooting_status'] ) $stats['completed_shoot']++;
            if ( 'da_in'   === $data['print_status'] || 'da_tra' === $data['print_status'] ) $stats['completed_print']++;
            if ( 'huy'     === $data['shooting_status'] || 'cancelled' === $data['wc_status'] ) $stats['cancelled']++;
            $stats['revenue_total'] += $data['total_price'];
        }
    }

    // Danh mục trạng thái để frontend hiển thị dropdown & màu sắc
    $status_configs = memora_crm_get_status_config();

    wp_send_json_success( array(
        'date'            => $date_ymd,
        'date_formatted'  => $date_dmy,
        'dia_chi_id'      => $dia_chi_id,
        'locations'       => $locations_list,
        'rooms'           => $rooms,
        'interval'        => $config_interval,
        'time_slots'      => $time_slots,
        'matrix'          => $matrix,
        'stats'           => $stats,
        'status_configs'  => $status_configs,
    ) );
}

// =====================================================================
// 2. API: CẬP NHẬT TRỰC TIẾP TỪNG TRƯỜNG (INLINE FAST UPDATE)
// =====================================================================
add_action( 'wp_ajax_memora_crm_update_field', 'memora_crm_ajax_update_field' );
function memora_crm_ajax_update_field() {
    memora_crm_verify_request();

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $field    = isset( $_POST['field'] )    ? sanitize_key( $_POST['field'] ) : '';
    $value    = isset( $_POST['value'] )    ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

    if ( $order_id <= 0 || empty( $field ) ) {
        wp_send_json_error( array( 'message' => 'Tham số không hợp lệ.' ) );
    }

    $res = memora_crm_update_order_field( $order_id, $field, $value );
    if ( is_wp_error( $res ) ) {
        wp_send_json_error( array( 'message' => $res->get_error_message() ) );
    }

    // Lấy lại data mới nhất sau khi cập nhật
    $updated_data = memora_crm_get_order_data( $order_id );

    wp_send_json_success( array(
        'message'      => 'Cập nhật thành công',
        'order_id'     => $order_id,
        'field'        => $field,
        'value'        => $value,
        'updated_data' => $updated_data,
    ) );
}

// =====================================================================
// 2b. API: CẬP NHẬT ĐỒNG THỜI THÔNG TIN ĐƠN ĐẶT LỊCH (MODAL SAVE)
// =====================================================================
add_action( 'wp_ajax_memora_crm_update_booking_info', 'memora_crm_ajax_update_booking_info' );
function memora_crm_ajax_update_booking_info() {
    memora_crm_verify_request();

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    if ( $order_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'Mã đơn hàng không hợp lệ.' ) );
    }

    $data = array(
        'customer_name'   => isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '',
        'phone'           => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
        'package_name'    => isset( $_POST['package_name'] ) ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) ) : '',
        'background_note' => isset( $_POST['background_note'] ) ? sanitize_text_field( wp_unslash( $_POST['background_note'] ) ) : '',
    );
    if ( isset( $_POST['deposit_price'] ) ) {
        $raw_dep = sanitize_text_field( wp_unslash( $_POST['deposit_price'] ) );
        $data['deposit_price'] = function_exists( 'memora_crm_parse_money_value' )
            ? memora_crm_parse_money_value( $raw_dep )
            : floatval( preg_replace( '/[^0-9]/', '', $raw_dep ) );
    }

    if ( function_exists( 'memora_crm_update_booking_info' ) ) {
        $res = memora_crm_update_booking_info( $order_id, $data );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( array( 'message' => $res->get_error_message() ) );
        }
    }

    $updated_data = memora_crm_get_order_data( $order_id );

    wp_send_json_success( array(
        'message'      => 'Đã cập nhật thông tin đơn đặt lịch thành công!',
        'updated_data' => $updated_data,
    ) );
}

// =====================================================================
// 3. API: TẠO NHANH ĐẶT LỊCH TẠI QUẦY (QUICK WALK-IN BOOKING)
// =====================================================================
add_action( 'wp_ajax_memora_crm_quick_book', 'memora_crm_ajax_quick_book' );
function memora_crm_ajax_quick_book() {
    memora_crm_verify_request();

    $date         = isset( $_POST['date'] )          ? sanitize_text_field( wp_unslash( $_POST['date'] ) )          : '';
    $time         = isset( $_POST['time'] )          ? sanitize_text_field( wp_unslash( $_POST['time'] ) )          : '';
    $room_id      = isset( $_POST['room_id'] )       ? intval( $_POST['room_id'] )                                  : 0;
    $dia_chi_id   = isset( $_POST['dia_chi_id'] )    ? intval( $_POST['dia_chi_id'] )                               : 0;
    $customer_name= isset( $_POST['name'] )          ? sanitize_text_field( wp_unslash( $_POST['name'] ) )          : '';
    $phone        = isset( $_POST['phone'] )         ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )         : '';
    $package_name = isset( $_POST['package_name'] )  ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) )  : '5';
    $bg_note      = isset( $_POST['background_note'] ) ? sanitize_text_field( wp_unslash( $_POST['background_note'] ) ) : '';
    $payment_stat = isset( $_POST['payment_status'] )? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : 'da_ck';
    $raw_total    = isset( $_POST['total_price'] )   ? sanitize_text_field( wp_unslash( $_POST['total_price'] ) )   : '0';
    $raw_deposit  = isset( $_POST['deposit_price'] ) ? sanitize_text_field( wp_unslash( $_POST['deposit_price'] ) ) : '0';
    $total_price  = function_exists( 'memora_crm_parse_money_value' ) ? memora_crm_parse_money_value( $raw_total )   : floatval( preg_replace( '/[^0-9]/', '', $raw_total ) );
    $deposit_price= function_exists( 'memora_crm_parse_money_value' ) ? memora_crm_parse_money_value( $raw_deposit ) : floatval( preg_replace( '/[^0-9]/', '', $raw_deposit ) );

    // Nếu không nhập ô Tiền cọc nhưng có ghi "cọc 90k" / "150k" trong Background / Ghi chú thì tự trích xuất
    if ( $deposit_price <= 0 && ! empty( $bg_note ) && preg_match( '/(\d+(?:[\.,]\d+)?)\s*k\b/iu', $bg_note ) && function_exists( 'memora_crm_parse_money_value' ) ) {
        $deposit_price = memora_crm_parse_money_value( $bg_note );
    }

    // Nếu đặt nhanh tại quầy chỉ nhập Tiền cọc thì gán Tổng tiền = Tiền cọc để WooCommerce tính đúng Total
    if ( $total_price <= 0 && $deposit_price > 0 ) {
        $total_price = $deposit_price;
    }
    $pay_amount = $total_price > 0 ? $total_price : $deposit_price;

    if ( empty( $date ) || empty( $time ) || empty( $customer_name ) ) {
        wp_send_json_error( array( 'message' => 'Vui lòng nhập đầy đủ Ngày, Giờ và Tên khách hàng.' ) );
    }

    // Kiểm tra trùng lịch trước khi tạo đơn tại quầy
    if ( function_exists( 'memora_get_booked_slots' ) ) {
        $booked_slots = memora_get_booked_slots( $date, $room_id, $dia_chi_id );
        if ( in_array( $time, $booked_slots, true ) ) {
            wp_send_json_error( array( 'message' => 'Khung giờ ' . $time . ' của phòng này đã có lịch đặt. Vui lòng chọn giờ hoặc phòng khác!' ) );
        }
    }

    // Tên phòng & tên chi nhánh
    $room_name   = '';
    $room_post   = get_post( $room_id );
    if ( $room_post ) {
        $room_name = $room_post->post_title;
    } else {
        $room_name = 'Room ' . ( $room_id > 0 ? $room_id : '1' );
    }

    $dia_chi_name = '';
    if ( $dia_chi_id > 0 ) {
        $term = get_term( $dia_chi_id, 'dia-chi' );
        if ( $term && ! is_wp_error( $term ) ) {
            $dia_chi_name = $term->name;
        }
    }

    // Sinh mã code 4 số
    $code = function_exists( 'memora_generate_unique_booking_code' )
        ? memora_generate_unique_booking_code()
        : strtoupper( substr( md5( uniqid() ), 0, 4 ) );

    // Tạo đơn hàng WooCommerce mới
    $order = wc_create_order();
    if ( is_wp_error( $order ) || ! $order ) {
        wp_send_json_error( array( 'message' => 'Không thể khởi tạo đơn hàng WooCommerce.' ) );
    }

    // Gán thông tin khách hàng
    $order->set_billing_first_name( $customer_name );
    $order->set_billing_last_name( '' );
    if ( ! empty( $phone ) ) {
        $order->set_billing_phone( $phone );
    }

    // Thêm line item sản phẩm đặt lịch vào đơn hàng
    $item_title = sprintf( 'Đặt lịch tại quầy — %s | %s %s', $package_name, $date, $time );
    $product_id = function_exists( 'memora_get_or_create_booking_product' ) ? memora_get_or_create_booking_product() : 0;
    $product    = ( $product_id > 0 && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : false;

    if ( $product ) {
        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( 1 );
        $item->set_name( $item_title );
        $item->set_subtotal( $pay_amount );
        $item->set_total( $pay_amount );
        $item->update_meta_data( 'Mã đặt lịch', '#' . $code );
        $item->update_meta_data( 'Ngày chụp', $date );
        $item->update_meta_data( 'Giờ chụp', $time );
        $item->update_meta_data( 'Gói chụp', $package_name );
        if ( ! empty( $room_name ) ) {
            $item->update_meta_data( 'Phòng chụp', $room_name );
        }
        if ( ! empty( $dia_chi_name ) ) {
            $item->update_meta_data( 'Chi nhánh', $dia_chi_name );
        }
        $order->add_item( $item );
    } else {
        $item = new WC_Order_Item_Fee();
        $item->set_name( $item_title );
        $item->set_amount( $pay_amount );
        $item->set_total( $pay_amount );
        $item->set_tax_status( 'none' );
        $item->set_taxes( array() );
        $order->add_item( $item );
    }

    // Lưu toàn bộ metadata
    $order->update_meta_data( '_memora_booking_code',   $code );
    $order->update_meta_data( '_booking_code',          $code );
    $order->update_meta_data( '_booking_customer_name', $customer_name );
    $order->update_meta_data( '_booking_phone',         $phone );
    $order->update_meta_data( '_booking_date',          $date );
    $order->update_meta_data( '_memora_booking_date',   $date );
    $order->update_meta_data( '_booking_time',          $time );
    $order->update_meta_data( '_memora_booking_time',   $time );
    $order->update_meta_data( '_booking_package_name',  $package_name );
    $order->update_meta_data( '_booking_phong_id',      $room_id );
    $order->update_meta_data( '_booking_phong_name',    $room_name );
    $order->update_meta_data( '_booking_room_name',     $room_name );
    $order->update_meta_data( '_booking_dia_chi_id',    $dia_chi_id );
    $order->update_meta_data( '_booking_dia_chi_name',  $dia_chi_name );
    $order->update_meta_data( '_booking_total_price',   $total_price );
    $order->update_meta_data( '_booking_deposit_price', $deposit_price );
    $order->update_meta_data( '_booking_payment_status', $payment_stat );
    $order->update_meta_data( '_booking_shooting_status', 'cho_chup' );
    $order->update_meta_data( '_booking_invoice_status',  'chua_in' );
    $order->update_meta_data( '_booking_print_status',    'chua_in' );
    $order->update_meta_data( '_booking_background_note', $bg_note );
    $order->update_meta_data( '_booking_source',          'crm_walk_in' );
    $order->update_meta_data( '_booking_created_at',      current_time( 'mysql' ) );

    // Trạng thái đơn hàng WooCommerce tương ứng
    $wc_status = ( in_array( $payment_stat, array( 'da_ck', 'tien_mat' ), true ) ) ? 'processing' : 'on-hold';
    $order->set_status( $wc_status );

    // Ghi chú Audit Log
    $current_user = wp_get_current_user();
    $actor_name   = $current_user->exists() ? $current_user->display_name : 'Lễ tân';
    $order->add_order_note( sprintf(
        '[CRM] Tạo đặt lịch nhanh tại quầy bởi %s | Mã: #%s | %s %s | Phòng: %s | Gói: %s | Khách: %s (%s)',
        $actor_name, $code, $date, $time, $room_name, $package_name, $customer_name, $phone
    ) );

    // Tính toán tổng tiền cho WooCommerce Order để hiển thị đúng cột Total trong danh sách Orders
    $order->calculate_totals( false );
    $order->set_total( $pay_amount );
    $order->save();

    $new_booking_data = memora_crm_get_order_data( $order );

    wp_send_json_success( array(
        'message'      => 'Tạo đặt lịch nhanh thành công!',
        'booking_data' => $new_booking_data,
    ) );
}

// =====================================================================
// 4. API: CHUYỂN PHÒNG / ĐỔI GIỜ CHỤP (MOVE / RESCHEDULE)
// =====================================================================
add_action( 'wp_ajax_memora_crm_move_booking', 'memora_crm_ajax_move_booking' );
function memora_crm_ajax_move_booking() {
    memora_crm_verify_request();

    $order_id    = isset( $_POST['order_id'] )    ? intval( $_POST['order_id'] ) : 0;
    $new_room_id = isset( $_POST['room_id'] )     ? intval( $_POST['room_id'] )  : 0;
    $new_time    = isset( $_POST['time'] )        ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
    $new_date    = isset( $_POST['date'] )        ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

    if ( $order_id <= 0 || empty( $new_time ) ) {
        wp_send_json_error( array( 'message' => 'Dữ liệu chuyển đổi không hợp lệ.' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        wp_send_json_error( array( 'message' => 'Đơn hàng không tồn tại.' ) );
    }

    $old_room = $order->get_meta( '_booking_room_name' ) ?: 'Phòng cũ';
    $old_time = $order->get_meta( '_booking_time' );
    $old_date = $order->get_meta( '_booking_date' );

    // Kiểm tra xem slot mới có bị trùng lịch với đơn khác không
    if ( function_exists( 'memora_get_booked_slots' ) ) {
        $target_date = ! empty( $new_date ) ? $new_date : $old_date;
        $target_room = $new_room_id > 0 ? $new_room_id : intval( $order->get_meta( '_booking_phong_id' ) );
        $target_loc  = intval( $order->get_meta( '_booking_dia_chi_id' ) );
        if ( $target_room > 0 && function_exists( 'memora_get_location_id_from_room' ) ) {
            $loc = memora_get_location_id_from_room( $target_room );
            if ( $loc > 0 ) $target_loc = $loc;
        }
        $target_booked = memora_get_booked_slots( $target_date, $target_room, $target_loc, $order_id );
        if ( in_array( $new_time, $target_booked, true ) ) {
            wp_send_json_error( array( 'message' => 'Khung giờ ' . $new_time . ' của phòng này đã có lịch hẹn. Không thể chuyển vào giờ này!' ) );
        }
    }

    // Cập nhật giờ
    $order->update_meta_data( '_booking_time', $new_time );
    $order->update_meta_data( '_memora_booking_time', $new_time );

    // Cập nhật ngày nếu có
    if ( ! empty( $new_date ) ) {
        $order->update_meta_data( '_booking_date', $new_date );
        $order->update_meta_data( '_memora_booking_date', $new_date );
    }

    // Cập nhật phòng nếu có
    $room_name = '';
    if ( $new_room_id > 0 ) {
        $room_post = get_post( $new_room_id );
        $room_name = $room_post ? $room_post->post_title : ( 'Room ' . $new_room_id );
        $order->update_meta_data( '_booking_phong_id', $new_room_id );
        $order->update_meta_data( '_booking_phong_name', $room_name );
        $order->update_meta_data( '_booking_room_name', $room_name );
    }

    $actor = wp_get_current_user()->display_name ?: 'Nhân viên';
    $order->add_order_note( sprintf(
        '[CRM Điều Phối] %s đã đổi lịch từ [%s %s - %s] sang [%s %s - %s].',
        $actor,
        $old_date, $old_time, $old_room,
        ( $new_date ?: $old_date ), $new_time, ( $room_name ?: $old_room )
    ) );

    $order->save();

    wp_send_json_success( array(
        'message'      => 'Đã chuyển lịch hẹn thành công.',
        'updated_data' => memora_crm_get_order_data( $order ),
    ) );
}

// =====================================================================
// 5. API: HỦY ĐƠN ĐẶT LỊCH (CANCEL BOOKING)
// =====================================================================
add_action( 'wp_ajax_memora_crm_cancel_booking', 'memora_crm_ajax_cancel_booking' );
function memora_crm_ajax_cancel_booking() {
    memora_crm_verify_request();

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $reason   = isset( $_POST['reason'] )   ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : 'Hủy tại quầy CRM';

    if ( $order_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'Mã đơn không hợp lệ.' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        wp_send_json_error( array( 'message' => 'Đơn hàng không tồn tại.' ) );
    }

    $order->update_meta_data( '_booking_shooting_status', 'huy' );
    $order->set_status( 'cancelled', 'Hủy qua CRM: ' . $reason );
    $order->save();

    wp_send_json_success( array(
        'message'      => 'Đã hủy lịch hẹn.',
        'updated_data' => memora_crm_get_order_data( $order ),
    ) );
}
