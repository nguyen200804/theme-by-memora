<?php
/**
 * Booking Post Type & Admin Management
 * 
 * Đăng ký Custom Post Type 'memora_booking' để lưu trữ các lịch chụp ảnh,
 * cấu hình cột hiển thị trong danh sách Admin và Meta Box chi tiết đơn.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - ĐĂNG KÝ POST TYPE MEMORA BOOKING
//====================================
add_action( 'init', 'memora_register_booking_post_type' );
function memora_register_booking_post_type() {
    $labels = array(
        'name'               => __( 'Lịch Chụp Memora', 'memora' ),
        'singular_name'      => __( 'Đơn Đặt Lịch', 'memora' ),
        'menu_name'          => __( 'Lịch Chụp Ảnh', 'memora' ),
        'all_items'          => __( 'Tất cả Đơn Đặt', 'memora' ),
        'add_new'            => __( 'Thêm Đơn Mới', 'memora' ),
        'add_new_item'       => __( 'Thêm Đơn Đặt Lịch Mới', 'memora' ),
        'edit_item'          => __( 'Chỉnh sửa Đơn Đặt Lịch', 'memora' ),
        'new_item'           => __( 'Đơn Đặt Lịch Mới', 'memora' ),
        'view_item'          => __( 'Xem Đơn Đặt Lịch', 'memora' ),
        'search_items'       => __( 'Tìm kiếm Đơn Đặt Lịch', 'memora' ),
        'not_found'          => __( 'Không có đơn đặt lịch nào', 'memora' ),
        'not_found_in_trash' => __( 'Không có đơn đặt lịch nào trong thùng rác', 'memora' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 28,
        'menu_icon'          => 'dashicons-camera',
        'supports'           => array( 'title' ),
    );

    register_post_type( 'memora_booking', $args );
}
//====================================
// END - ĐĂNG KÝ POST TYPE MEMORA BOOKING
//====================================





//====================================
// START - CẤU HÌNH CỘT DANH SÁCH QUẢN TRỊ ADMIN
//====================================
add_filter( 'manage_memora_booking_posts_columns', 'memora_booking_columns' );
function memora_booking_columns( $columns ) {
    $new_columns = array(
        'cb'             => $columns['cb'],
        'booking_code'   => __( 'Mã Code', 'memora' ),
        'customer'       => __( 'Khách Hàng', 'memora' ),
        'schedule'       => __( 'Lịch Chụp', 'memora' ),
        'package'        => __( 'Gói Chụp', 'memora' ),
        'payment'        => __( 'Thanh Toán (50%)', 'memora' ),
        'booking_status' => __( 'Trạng Thái', 'memora' ),
        'date'           => __( 'Ngày Đặt', 'memora' ),
    );
    return $new_columns;
}

add_action( 'manage_memora_booking_posts_custom_column', 'memora_booking_column_content', 10, 2 );
function memora_booking_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'booking_code':
            $code = get_post_meta( $post_id, '_booking_code', true );
            if ( $code ) {
                echo '<span style="display:inline-block; padding:4px 10px; background:#733e1c; color:#fff; font-weight:700; border-radius:12px; font-size:14px; letter-spacing:1px;">#' . esc_html( $code ) . '</span>';
            } else {
                echo '—';
            }
            break;

        case 'customer':
            $name  = get_post_meta( $post_id, '_booking_customer_name', true );
            $phone = get_post_meta( $post_id, '_booking_phone', true );
            $ig    = get_post_meta( $post_id, '_booking_contact_other', true );

            echo '<strong>' . esc_html( $name ? $name : 'Khách vãng lai' ) . '</strong><br>';
            if ( $phone ) {
                echo '<span style="color:#555;">📞 ' . esc_html( $phone ) . '</span><br>';
            }
            if ( $ig ) {
                echo '<span style="color:#777; font-size:12px;">📷 IG: ' . esc_html( $ig ) . '</span>';
            }
            break;

        case 'schedule':
            $date = get_post_meta( $post_id, '_booking_date', true );
            $time = get_post_meta( $post_id, '_booking_time', true );
            echo '<span style="display:inline-block; padding:3px 8px; background:#cbe3f3; color:#1e4a6d; border-radius:6px; font-weight:600; font-size:12px; margin-bottom:2px;">' . esc_html( $date ) . '</span><br>';
            echo '<span style="display:inline-block; padding:3px 8px; background:#e8f2f9; color:#333; border-radius:6px; font-weight:600; font-size:12px;">⏰ ' . esc_html( $time ) . '</span>';
            break;

        case 'package':
            $pkg  = get_post_meta( $post_id, '_booking_package_name', true );
            $room = get_post_meta( $post_id, '_booking_room_name', true );
            echo '<strong style="color:#733e1c;">' . esc_html( $pkg ? $pkg : '—' ) . '</strong>';
            if ( $room ) {
                echo '<br><span style="color:#2b6cb0; font-size:12px; font-weight:600;">📷 ' . esc_html( $room ) . '</span>';
            }
            break;

        case 'payment':
            $total   = get_post_meta( $post_id, '_booking_total_price', true );
            $deposit = get_post_meta( $post_id, '_booking_deposit_price', true );
            echo 'Tổng: <strong>' . esc_html( memora_format_price( $total ) ) . '</strong><br>';
            echo '<span style="color:#d9534f; font-weight:600; font-size:12px;">Cọc 50%: ' . esc_html( memora_format_price( $deposit ) ) . '</span>';
            break;

        case 'booking_status':
            $status = get_post_meta( $post_id, '_booking_status', true );
            if ( ! $status ) {
                $status = 'pending';
            }

            $badges = array(
                'pending'      => array( 'label' => 'Chờ xác nhận', 'bg' => '#f0ad4e', 'color' => '#fff' ),
                'deposit_paid' => array( 'label' => 'Đã cọc 50%', 'bg' => '#5cb85c', 'color' => '#fff' ),
                'completed'    => array( 'label' => 'Hoàn tất', 'bg' => '#337ab7', 'color' => '#fff' ),
                'cancelled'    => array( 'label' => 'Đã hủy', 'bg' => '#d9534f', 'color' => '#fff' ),
            );

            $badge = isset( $badges[ $status ] ) ? $badges[ $status ] : array( 'label' => $status, 'bg' => '#888', 'color' => '#fff' );
            echo '<span style="display:inline-block; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600; background:' . esc_attr( $badge['bg'] ) . '; color:' . esc_attr( $badge['color'] ) . ';">' . esc_html( $badge['label'] ) . '</span>';
            break;
    }
}
//====================================
// END - CẤU HÌNH CỘT DANH SÁCH QUẢN TRỊ ADMIN
//====================================





//====================================
// START - META BOX CHI TIẾT ĐƠN ĐẶT LỊCH
//====================================
add_action( 'add_meta_boxes', 'memora_add_booking_meta_box' );
function memora_add_booking_meta_box() {
    add_meta_box(
        'memora_booking_details_meta',
        __( 'Thông Tin Đặt Lịch Chụp Ảnh Memora', 'memora' ),
        'memora_render_booking_meta_box',
        'memora_booking',
        'normal',
        'high'
    );
}

function memora_render_booking_meta_box( $post ) {
    wp_nonce_field( 'memora_save_booking_meta', 'memora_booking_nonce' );

    $code        = get_post_meta( $post->ID, '_booking_code', true );
    $name        = get_post_meta( $post->ID, '_booking_customer_name', true );
    $phone       = get_post_meta( $post->ID, '_booking_phone', true );
    $ig          = get_post_meta( $post->ID, '_booking_contact_other', true );
    $date        = get_post_meta( $post->ID, '_booking_date', true );
    $time        = get_post_meta( $post->ID, '_booking_time', true );
    $pkg         = get_post_meta( $post->ID, '_booking_package_name', true );
    $room        = get_post_meta( $post->ID, '_booking_room_name', true );
    $total       = get_post_meta( $post->ID, '_booking_total_price', true );
    $deposit     = get_post_meta( $post->ID, '_booking_deposit_price', true );
    $status      = get_post_meta( $post->ID, '_booking_status', true ) ?: 'pending';
    $admin_note  = get_post_meta( $post->ID, '_booking_admin_note', true );
    ?>
    <style>
        .memora-admin-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; }
        .memora-meta-field { margin-bottom: 12px; }
        .memora-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; color: #333; }
        .memora-meta-field input, .memora-meta-field select, .memora-meta-field textarea { width: 100%; max-width: 100%; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; }
        .memora-code-highlight { font-size: 20px; font-weight: bold; color: #733e1c; background: #fdf5f0; padding: 6px 12px; border-radius: 6px; display: inline-block; border: 1px solid #dfb0bf; }
    </style>

    <div class="memora-admin-meta-wrap">
        <div style="margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
            <label style="font-weight: 600; font-size: 14px; margin-right: 10px;">MÃ CODE TRA CỨU:</label>
            <span class="memora-code-highlight">#<?php echo esc_html( $code ? $code : 'Chưa có' ); ?></span>
            <input type="hidden" name="booking_code" value="<?php echo esc_attr( $code ); ?>" />
        </div>

        <div class="memora-admin-meta-grid">
            <div class="memora-meta-col">
                <h4 style="margin: 0 0 10px 0; color: #733e1c; font-size: 15px; border-bottom: 2px solid #733e1c; padding-bottom: 4px;">👤 Thông tin khách hàng</h4>
                <div class="memora-meta-field">
                    <label for="booking_customer_name">Tên khách hàng:</label>
                    <input type="text" id="booking_customer_name" name="booking_customer_name" value="<?php echo esc_attr( $name ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_phone">Số điện thoại:</label>
                    <input type="text" id="booking_phone" name="booking_phone" value="<?php echo esc_attr( $phone ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_contact_other">Instagram / Liên hệ khác:</label>
                    <input type="text" id="booking_contact_other" name="booking_contact_other" value="<?php echo esc_attr( $ig ); ?>" />
                </div>
            </div>

            <div class="memora-meta-col">
                <h4 style="margin: 0 0 10px 0; color: #733e1c; font-size: 15px; border-bottom: 2px solid #733e1c; padding-bottom: 4px;">📅 Lịch chụp & Gói dịch vụ</h4>
                <div class="memora-meta-field">
                    <label for="booking_date">Ngày chụp (DD/MM/YYYY):</label>
                    <input type="text" id="booking_date" name="booking_date" value="<?php echo esc_attr( $date ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_time">Giờ chụp:</label>
                    <input type="text" id="booking_time" name="booking_time" value="<?php echo esc_attr( $time ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_package_name">Tên gói chụp:</label>
                    <input type="text" id="booking_package_name" name="booking_package_name" value="<?php echo esc_attr( $pkg ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_room_name">Phòng chụp:</label>
                    <input type="text" id="booking_room_name" name="booking_room_name" value="<?php echo esc_attr( $room ); ?>" />
                </div>
            </div>
        </div>

        <div class="memora-admin-meta-grid" style="margin-top: 15px;">
            <div class="memora-meta-col">
                <h4 style="margin: 0 0 10px 0; color: #733e1c; font-size: 15px; border-bottom: 2px solid #733e1c; padding-bottom: 4px;">💳 Thanh toán</h4>
                <div class="memora-meta-field">
                    <label for="booking_total_price">Tổng tiền gói (VNĐ):</label>
                    <input type="number" id="booking_total_price" name="booking_total_price" value="<?php echo esc_attr( $total ); ?>" />
                </div>
                <div class="memora-meta-field">
                    <label for="booking_deposit_price">Tiền đặt cọc 50% (VNĐ):</label>
                    <input type="number" id="booking_deposit_price" name="booking_deposit_price" value="<?php echo esc_attr( $deposit ); ?>" />
                </div>
            </div>

            <div class="memora-meta-col">
                <h4 style="margin: 0 0 10px 0; color: #733e1c; font-size: 15px; border-bottom: 2px solid #733e1c; padding-bottom: 4px;">⚙️ Trạng thái & Ghi chú nội bộ</h4>
                <div class="memora-meta-field">
                    <label for="booking_status">Trạng thái đơn:</label>
                    <select id="booking_status" name="booking_status">
                        <option value="pending" <?php selected( $status, 'pending' ); ?>>Chờ xác nhận</option>
                        <option value="deposit_paid" <?php selected( $status, 'deposit_paid' ); ?>>Đã cọc 50%</option>
                        <option value="completed" <?php selected( $status, 'completed' ); ?>>Hoàn tất chụp</option>
                        <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="memora-meta-field">
                    <label for="booking_admin_note">Ghi chú quản trị:</label>
                    <textarea id="booking_admin_note" name="booking_admin_note" rows="3"><?php echo esc_textarea( $admin_note ); ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'save_post_memora_booking', 'memora_save_booking_meta' );
function memora_save_booking_meta( $post_id ) {
    if ( ! isset( $_POST['memora_booking_nonce'] ) || ! wp_verify_nonce( $_POST['memora_booking_nonce'], 'memora_save_booking_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = array(
        'booking_code'          => '_booking_code',
        'booking_customer_name' => '_booking_customer_name',
        'booking_phone'         => '_booking_phone',
        'booking_contact_other' => '_booking_contact_other',
        'booking_date'          => '_booking_date',
        'booking_time'          => '_booking_time',
        'booking_package_name'  => '_booking_package_name',
        'booking_room_name'     => '_booking_room_name',
        'booking_total_price'   => '_booking_total_price',
        'booking_deposit_price' => '_booking_deposit_price',
        'booking_status'        => '_booking_status',
        'booking_admin_note'    => '_booking_admin_note',
    );

    foreach ( $fields as $input_key => $meta_key ) {
        if ( isset( $_POST[ $input_key ] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $input_key ] ) );
            update_post_meta( $post_id, $meta_key, $value );
        }
    }
}
//====================================
// END - META BOX CHI TIẾT ĐƠN ĐẶT LỊCH
//====================================
