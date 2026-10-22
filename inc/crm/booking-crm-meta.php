<?php
/**
 * Memora Booking CRM - Metadata & Trạng Thái Nghiệp Vụ
 *
 * Định nghĩa cấu trúc dữ liệu, danh mục trạng thái nghiệp vụ và các hàm tiện ích
 * lưu trữ / truy xuất metadata phục vụ CRM Điều phối Đặt lịch.
 *
 * @package Memora
 * @subpackage BookingCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. ĐỊNH NGHĨA TOÀN BỘ CÁC DANH MỤC TRẠNG THÁI NGHIỆP VỤ (STATUS DICTIONARY)
 *
 * Bao gồm mã trạng thái, nhãn hiển thị, màu sắc badge tương ứng với giao diện thực tế.
 *
 * @param string $group Tên nhóm trạng thái: 'payment', 'shooting', 'invoice', 'print' hoặc rỗng để lấy tất cả.
 * @return array
 */
function memora_crm_get_status_config( $group = '' ) {
    $configs = array(
        // Trạng thái Thanh toán
        'payment' => array(
            'chua_thanh_toan' => array(
                'label'        => 'Chưa TT',
                'bg_color'     => '#f5f5f5',
                'text_color'   => '#616161',
                'border_color' => '#e0e0e0',
            ),
            'da_coc' => array(
                'label'        => 'Đã cọc',
                'bg_color'     => '#fff8e1',
                'text_color'   => '#f57f17',
                'border_color' => '#ffe082',
            ),
            'da_ck' => array(
                'label'        => 'Đã ck',
                'bg_color'     => '#e8f5e9',
                'text_color'   => '#2e7d32',
                'border_color' => '#a5d6a7',
            ),
            'tien_mat' => array(
                'label'        => 'Tiền mặt',
                'bg_color'     => '#e8f5e9',
                'text_color'   => '#1b5e20',
                'border_color' => '#81c784',
            ),
        ),

        // Trạng thái Chụp ảnh tại phòng
        'shooting' => array(
            'cho_chup' => array(
                'label'        => 'Chờ chụp',
                'bg_color'     => '#f5f5f5',
                'text_color'   => '#616161',
                'border_color' => '#e0e0e0',
            ),
            'dang_chup' => array(
                'label'        => 'Đang chụp',
                'bg_color'     => '#fff3e0',
                'text_color'   => '#e65100',
                'border_color' => '#ffcc80',
            ),
            'da_chup' => array(
                'label'        => 'Đã chụp',
                'bg_color'     => '#e8f5e9',
                'text_color'   => '#2e7d32',
                'border_color' => '#a5d6a7',
            ),
            'huy' => array(
                'label'        => 'Hủy',
                'bg_color'     => '#ffebee',
                'text_color'   => '#c62828',
                'border_color' => '#ef9a9a',
            ),
        ),

        // Tình trạng Xuất hóa đơn
        'invoice' => array(
            'chua_in' => array(
                'label'        => 'Chưa in',
                'bg_color'     => '#f5f5f5',
                'text_color'   => '#616161',
                'border_color' => '#e0e0e0',
            ),
            'da_in' => array(
                'label'        => 'Đã in...',
                'bg_color'     => '#e8f5e9',
                'text_color'   => '#2e7d32',
                'border_color' => '#a5d6a7',
            ),
        ),

        // Tình trạng In ấn ảnh
        'print' => array(
            'chua_in' => array(
                'label'        => 'Chưa in',
                'bg_color'     => '#f5f5f5',
                'text_color'   => '#616161',
                'border_color' => '#e0e0e0',
            ),
            'dang_in' => array(
                'label'        => 'Đang in',
                'bg_color'     => '#fff3e0',
                'text_color'   => '#e65100',
                'border_color' => '#ffcc80',
            ),
            'da_in' => array(
                'label'        => 'Đã in',
                'bg_color'     => '#e8f5e9',
                'text_color'   => '#2e7d32',
                'border_color' => '#a5d6a7',
            ),
            'da_tra' => array(
                'label'        => 'Đã trả',
                'bg_color'     => '#ede7f6',
                'text_color'   => '#512da8',
                'border_color' => '#b39ddb',
            ),
        ),
    );

    if ( ! empty( $group ) && isset( $configs[ $group ] ) ) {
        return $configs[ $group ];
    }

    return $configs;
}

/**
 * 2. TRÍCH XUẤT TOÀN BỘ THÔNG TIN CRM TỪ ĐƠN HÀNG WOOCOMMERCE
 *
 * @param int|WC_Order $order_or_id
 * @return array|false
 */
function memora_crm_get_order_data( $order_or_id ) {
    $order = is_numeric( $order_or_id ) ? wc_get_order( $order_or_id ) : $order_or_id;
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    $order_id = $order->get_id();

    // Mã đặt lịch & thời gian
    $code = $order->get_meta( '_memora_booking_code' ) ?: $order->get_meta( '_booking_code' );
    $date = $order->get_meta( '_booking_date' ) ?: $order->get_meta( '_memora_booking_date' );
    $time = $order->get_meta( '_booking_time' ) ?: $order->get_meta( '_memora_booking_time' );

    // Khách hàng
    $name  = $order->get_meta( '_booking_customer_name' );
    if ( empty( $name ) ) {
        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    }
    $phone = $order->get_meta( '_booking_phone' ) ?: $order->get_billing_phone();
    $ig    = $order->get_meta( '_booking_contact_other' ) ?: (string) $order->get_meta( '_memora_ig' );

    // Gói chụp & phòng & chi nhánh
    $pkg_name = $order->get_meta( '_booking_package_name' );
    $room_id  = intval( $order->get_meta( '_booking_phong_id' ) );
    $room_name = $order->get_meta( '_booking_phong_name' ) ?: $order->get_meta( '_booking_room_name' );
    $branch_id = intval( $order->get_meta( '_booking_dia_chi_id' ) );
    $branch_name = $order->get_meta( '_booking_dia_chi_name' );

    // Tiền cọc & tổng tiền
    $deposit = floatval( $order->get_meta( '_booking_deposit_price' ) );
    $total   = floatval( $order->get_meta( '_booking_total_price' ) ?: $order->get_total() );

    // --- Các trạng thái CRM chuyên biệt (kèm fallback thông minh nếu đơn chưa lưu) ---
    // 1. Thanh toán
    $payment_status = $order->get_meta( '_booking_payment_status' );
    if ( empty( $payment_status ) ) {
        $wc_status = $order->get_status();
        if ( in_array( $wc_status, array( 'processing', 'completed' ), true ) ) {
            $payment_status = 'da_ck';
        } elseif ( $deposit > 0 ) {
            $payment_status = 'da_coc';
        } else {
            $payment_status = 'chua_thanh_toan';
        }
    }

    // 2. Tình trạng chụp
    $shooting_status = $order->get_meta( '_booking_shooting_status' );
    if ( empty( $shooting_status ) ) {
        $wc_status = $order->get_status();
        if ( 'cancelled' === $wc_status ) {
            $shooting_status = 'huy';
        } elseif ( 'completed' === $wc_status ) {
            $shooting_status = 'da_chup';
        } else {
            $shooting_status = 'cho_chup';
        }
    }

    // 3. Tình trạng hóa đơn
    $invoice_status = $order->get_meta( '_booking_invoice_status' ) ?: 'chua_in';

    // 4. Tình trạng in ảnh
    $print_status = $order->get_meta( '_booking_print_status' );
    if ( empty( $print_status ) ) {
        $print_status = ( 'completed' === $order->get_status() ) ? 'da_in' : 'chua_in';
    }

    // 5. Background / Ghi chú
    $background_note = $order->get_meta( '_booking_background_note' );
    if ( '' === (string) $background_note && ! $order->meta_exists( '_booking_background_note' ) && $deposit > 0 ) {
        // Mặc định hiển thị cọc nếu chưa nhập phông nền cụ thể
        $background_note = 'cọc ' . ( $deposit >= 1000 ? ( $deposit / 1000 ) . 'k' : $deposit );
    }

    return array(
        'order_id'         => $order_id,
        'code'             => $code ?: (string) $order_id,
        'date'             => $date,
        'time'             => $time,
        'name'             => $name,
        'phone'            => $phone,
        'contact_other'    => $ig,
        'package_name'     => $pkg_name,
        'room_id'          => $room_id,
        'room_name'        => $room_name,
        'dia_chi_id'       => $branch_id,
        'dia_chi_name'     => $branch_name,
        'deposit_price'    => $deposit,
        'total_price'      => $total,
        'wc_status'        => $order->get_status(),
        'wc_status_name'   => wc_get_order_status_name( $order->get_status() ),
        'payment_status'   => $payment_status,
        'shooting_status'  => $shooting_status,
        'invoice_status'   => $invoice_status,
        'print_status'     => $print_status,
        'background_note'  => $background_note,
        'edit_order_url'   => admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
    );
}

/**
 * 3. HÀM CẬP NHẬT TRƯỜNG CRM AN TOÀN KÈM GHI CHÚ ĐƠN HÀNG (AUDIT LOG)
 *
 * @param int    $order_id
 * @param string $field_name
 * @param string $value
 * @return bool|WP_Error
 */
function memora_crm_update_order_field( $order_id, $field_name, $value ) {
    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        return new WP_Error( 'invalid_order', 'Đơn hàng không tồn tại' );
    }

    $valid_fields = array(
        'payment_status'  => '_booking_payment_status',
        'shooting_status' => '_booking_shooting_status',
        'invoice_status'  => '_booking_invoice_status',
        'print_status'    => '_booking_print_status',
        'background_note' => '_booking_background_note',
        'customer_name'   => '_booking_customer_name',
        'phone'           => '_booking_phone',
        'package_name'    => '_booking_package_name',
        'room_id'         => '_booking_phong_id',
        'time'            => '_booking_time',
        'date'            => '_booking_date',
    );

    if ( ! isset( $valid_fields[ $field_name ] ) ) {
        return new WP_Error( 'invalid_field', 'Trường dữ liệu không hợp lệ' );
    }

    $meta_key  = $valid_fields[ $field_name ];
    $old_value = (string) $order->get_meta( $meta_key );
    if ( 'customer_name' === $field_name && '' === $old_value ) {
        $old_value = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    } elseif ( 'phone' === $field_name && '' === $old_value ) {
        $old_value = (string) $order->get_billing_phone();
    }
    $new_value = sanitize_text_field( wp_unslash( $value ) );

    // Nếu không thay đổi và meta đã tồn tại thì bỏ qua
    if ( $old_value === $new_value && $order->meta_exists( $meta_key ) ) {
        return true;
    }

    // Cập nhật meta key chính
    $order->update_meta_data( $meta_key, $new_value );

    // Đồng bộ ngược các trường tương đương nếu cần
    if ( 'time' === $field_name ) {
        $order->update_meta_data( '_memora_booking_time', $new_value );
    } elseif ( 'date' === $field_name ) {
        $order->update_meta_data( '_memora_booking_date', $new_value );
    } elseif ( 'customer_name' === $field_name ) {
        $order->set_billing_first_name( $new_value );
        $order->set_billing_last_name( '' );
    } elseif ( 'phone' === $field_name ) {
        $order->set_billing_phone( $new_value );
    } elseif ( 'room_id' === $field_name ) {
        $room_post = get_post( intval( $new_value ) );
        if ( $room_post ) {
            $order->update_meta_data( '_booking_phong_name', $room_post->post_title );
            $order->update_meta_data( '_booking_room_name', $room_post->post_title );
        }
    }

    // Ghi Order Note để kiểm soát lịch sử thao tác
    $current_user = wp_get_current_user();
    $actor_name   = $current_user->exists() ? $current_user->display_name : 'Hệ thống CRM';
    $order->add_order_note( sprintf(
        '[CRM Điều Phối] %s đã cập nhật [%s] từ "%s" thành "%s".',
        $actor_name,
        $field_name,
        $old_value ?: '(trống)',
        $new_value
    ) );

    $order->save();
    return true;
}

/**
 * 3b. CẬP NHẬT ĐỒNG THỜI CÁC TRƯỜNG THÔNG TIN ĐẶT LỊCH (TÊN, GÓI CHỤP, BACKGROUND, SĐT)
 *
 * @param int   $order_id
 * @param array $data Mảng gồm 'customer_name', 'phone', 'package_name', 'background_note'
 * @return bool|WP_Error
 */
function memora_crm_update_booking_info( $order_id, $data ) {
    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        return new WP_Error( 'invalid_order', 'Đơn hàng không tồn tại' );
    }

    $changes = array();

    if ( isset( $data['customer_name'] ) ) {
        $name = sanitize_text_field( wp_unslash( $data['customer_name'] ) );
        $old_name = (string) ( $order->get_meta( '_booking_customer_name' ) ?: $order->get_billing_first_name() );
        if ( $name !== $old_name ) {
            $order->update_meta_data( '_booking_customer_name', $name );
            $order->set_billing_first_name( $name );
            $order->set_billing_last_name( '' );
            $changes[] = sprintf( 'Tên: "%s" -> "%s"', $old_name ?: '(trống)', $name );
        }
    }

    if ( isset( $data['phone'] ) ) {
        $phone = sanitize_text_field( wp_unslash( $data['phone'] ) );
        $old_phone = (string) ( $order->get_meta( '_booking_phone' ) ?: $order->get_billing_phone() );
        if ( $phone !== $old_phone ) {
            $order->update_meta_data( '_booking_phone', $phone );
            $order->set_billing_phone( $phone );
            $changes[] = sprintf( 'SĐT: "%s" -> "%s"', $old_phone ?: '(trống)', $phone );
        }
    }

    if ( isset( $data['package_name'] ) ) {
        $pkg = sanitize_text_field( wp_unslash( $data['package_name'] ) );
        $old_pkg = (string) $order->get_meta( '_booking_package_name' );
        if ( $pkg !== $old_pkg ) {
            $order->update_meta_data( '_booking_package_name', $pkg );
            $changes[] = sprintf( 'Gói: "%s" -> "%s"', $old_pkg ?: '(trống)', $pkg );
        }
    }

    if ( isset( $data['background_note'] ) ) {
        $bg = sanitize_text_field( wp_unslash( $data['background_note'] ) );
        $old_bg = (string) $order->get_meta( '_booking_background_note' );
        if ( $bg !== $old_bg || ! $order->meta_exists( '_booking_background_note' ) ) {
            $order->update_meta_data( '_booking_background_note', $bg );
            if ( $bg !== $old_bg ) {
                $changes[] = sprintf( 'Background: "%s" -> "%s"', $old_bg ?: '(trống)', $bg );
            }
        }
    }

    if ( ! empty( $changes ) ) {
        $current_user = wp_get_current_user();
        $actor_name   = $current_user->exists() ? $current_user->display_name : 'Hệ thống CRM';
        $order->add_order_note( sprintf(
            '[CRM Điều Phối] %s cập nhật thông tin: %s.',
            $actor_name,
            implode( ', ', $changes )
        ) );
    }
    $order->save();

    return true;
}

/**
 * 4. TỰ ĐỘNG KHỞI TẠO CÁC GIÁ TRỊ CRM MẶC ĐỊNH KHI CÓ ĐƠN ĐẶT LỊCH MỚI
 */
add_action( 'woocommerce_checkout_order_created', 'memora_crm_init_order_meta_defaults', 20 );
function memora_crm_init_order_meta_defaults( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // Chỉ áp dụng cho đơn đặt lịch (có booking date hoặc pending session)
    $has_booking = $order->get_meta( '_booking_date' ) || $order->get_meta( '_memora_booking_date' );
    if ( ! $has_booking && function_exists( 'WC' ) && WC()->session ) {
        $has_booking = (bool) WC()->session->get( 'memora_booking_pending_data' );
    }

    if ( ! $has_booking ) {
        return;
    }

    // Mặc định trạng thái thanh toán ban đầu
    if ( ! $order->get_meta( '_booking_payment_status' ) ) {
        $deposit = floatval( $order->get_meta( '_booking_deposit_price' ) );
        $status  = ( $deposit > 0 ) ? 'da_ck' : 'chua_thanh_toan';
        $order->update_meta_data( '_booking_payment_status', $status );
    }

    // Mặc định tình trạng chụp
    if ( ! $order->get_meta( '_booking_shooting_status' ) ) {
        $order->update_meta_data( '_booking_shooting_status', 'cho_chup' );
    }

    // Mặc định tình trạng hóa đơn
    if ( ! $order->get_meta( '_booking_invoice_status' ) ) {
        $order->update_meta_data( '_booking_invoice_status', 'chua_in' );
    }

    // Mặc định tình trạng in ảnh
    if ( ! $order->get_meta( '_booking_print_status' ) ) {
        $order->update_meta_data( '_booking_print_status', 'chua_in' );
    }

    // Mặc định ghi chú background / cọc
    if ( ! $order->get_meta( '_booking_background_note' ) ) {
        $deposit = floatval( $order->get_meta( '_booking_deposit_price' ) );
        if ( $deposit > 0 ) {
            $formatted_deposit = ( $deposit >= 1000 ) ? ( $deposit / 1000 ) . 'k' : $deposit;
            $order->update_meta_data( '_booking_background_note', 'cọc ' . $formatted_deposit );
        }
    }

    $order->save();
}
