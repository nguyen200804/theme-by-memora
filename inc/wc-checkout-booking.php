<?php
/**
 * WooCommerce Checkout + Booking Integration
 *
 * Lien ket giua he thong dat lich tuy chinh va WC native checkout:
 *   1. Tao / lay san pham ao "Dat Lich Chup Anh"
 *   2. Override gia cart item theo gia goi chup
 *   3. Pre-fill billing fields tu booking session
 *   4. Hien tom tat dat lich phia tren WC checkout form
 *   5. Khi WC order duoc tao -> lien ket voi memora_booking + luu IG
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =====================================================================
// 1. LAY / TAO SAN PHAM AO "DAT LICH CHUP ANH"
// =====================================================================
function memora_get_or_create_booking_product() {
    static $cached_id = null;
    if ( $cached_id ) return $cached_id;

    $posts = get_posts( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'meta_key'       => '_memora_booking_product',
        'meta_value'     => '1',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $posts ) ) {
        $cached_id = $posts[0];
        return $cached_id;
    }

    if ( ! class_exists( 'WC_Product_Simple' ) ) return 0;

    $product = new WC_Product_Simple();
    $product->set_name( 'Dat Lich Chup Anh - Memora' );
    $product->set_status( 'publish' );
    $product->set_virtual( true );
    $product->set_catalog_visibility( 'hidden' );
    $product->set_sold_individually( true );
    $product->set_price( 0 );
    $product->set_regular_price( 0 );
    $product->update_meta_data( '_memora_booking_product', '1' );
    $cached_id = $product->save();
    return $cached_id;
}

// =====================================================================
// 2. KHOI PHUC CART ITEM DATA TU SESSION
// =====================================================================
add_filter( 'woocommerce_get_cart_item_from_session', 'memora_restore_booking_cart_item', 10, 2 );
function memora_restore_booking_cart_item( $item, $values ) {
    if ( ! empty( $values['memora_booking_id'] ) ) {
        $item['memora_booking_id'] = (int) $values['memora_booking_id'];
    }
    if ( ! empty( $values['memora_booking_pending'] ) ) {
        $item['memora_booking_pending'] = true;
    }
    return $item;
}

// =====================================================================
// 3. OVERRIDE GIA CART ITEM = GIA GOI CHUP
// =====================================================================
add_action( 'woocommerce_before_calculate_totals', 'memora_override_cart_booking_price', 10 );
function memora_override_cart_booking_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    $pending = WC()->session->get( 'memora_booking_pending_data' );
    if ( ! $pending ) return;

    $price = ! empty( $pending['deposit_price'] ) ? floatval( $pending['deposit_price'] ) : floatval( $pending['total_price'] );
    if ( $price <= 0 ) return;

    foreach ( $cart->get_cart() as $item ) {
        // Ho tro ca 2 flow: old (memora_booking_id) + new (memora_booking_pending)
        if ( ! empty( $item['memora_booking_id'] ) || ! empty( $item['memora_booking_pending'] ) ) {
            $item['data']->set_price( $price );
        }
    }
}

// Pre-fill chi ap dung cho flow cu (booking-ajax.php submit_booking)
// Flow moi: user dien name/phone tai WC checkout form
add_filter( 'woocommerce_checkout_get_value', 'memora_prefill_billing_from_session', 10, 2 );
function memora_prefill_billing_from_session( $value, $input ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return $value;

    $pending    = WC()->session->get( 'memora_booking_pending_data' );
    $booking_id = WC()->session->get( 'memora_booking_pending_id' );

    // Chi pre-fill neu la flow cu (booking_id da co trong session va co name)
    if ( ! $pending || empty( $booking_id ) ) return $value;

    if ( 'billing_first_name' === $input && empty( $value ) ) return $pending['name'] ?? $value;
    if ( 'billing_last_name'  === $input && empty( $value ) ) return '';
    if ( 'billing_phone'      === $input && empty( $value ) ) return $pending['phone'] ?? $value;

    return $value;
}

// =====================================================================
// 4b. THEM FIELD IG / LIEN HE KHAC VAO WC CHECKOUT FORM
// =====================================================================
add_action( 'woocommerce_after_checkout_billing_form', 'memora_add_ig_to_checkout_form' );
function memora_add_ig_to_checkout_form( $checkout ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;
    if ( ! WC()->session->get( 'memora_booking_pending_data' ) ) return;

    woocommerce_form_field( 'memora_ig', array(
        'type'        => 'text',
        'class'       => array( 'form-row-wide' ),
        'label'       => 'Phuong thuc lien he khac (Instagram, Zalo...)',
        'placeholder' => 'VD: @memora.film',
        'required'    => false,
    ), $checkout->get_value( 'memora_ig' ) );
}

// Luu IG vao order meta ngay khi WC checkout xu ly POST
add_action( 'woocommerce_checkout_order_created', 'memora_save_ig_from_checkout_post', 5 );
function memora_save_ig_from_checkout_post( $order ) {
    if ( ! empty( $_POST['memora_ig'] ) ) {
        $ig = sanitize_text_field( wp_unslash( $_POST['memora_ig'] ) );
        $order->update_meta_data( '_memora_ig', $ig );
        $order->save();
    }
}

// =====================================================================
// 5. HIEN TOM TAT DAT LICH TREN WC CHECKOUT
// =====================================================================
add_action( 'woocommerce_before_checkout_form', 'memora_show_booking_summary_on_checkout', 5 );
function memora_show_booking_summary_on_checkout() {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    $pending = WC()->session->get( 'memora_booking_pending_data' );
    $code    = WC()->session->get( 'memora_booking_pending_code' );
    if ( ! $pending ) return;

    $price = ! empty( $pending['deposit_price'] ) ? floatval( $pending['deposit_price'] ) : floatval( $pending['total_price'] );
    $price_fmt = number_format( $price, 0, ',', '.' ) . 'ddd';
    if ( function_exists( 'memora_format_price' ) ) {
        $price_fmt = memora_format_price( $price );
    }
    ?>
    <div class="memora-wc-booking-summary">
        <h3 class="memora-wc-booking-summary__title">Thong tin dat lich cua ban</h3>
        <div class="memora-wc-booking-summary__grid">
            <?php if ( $code ) : ?>
            <div class="memora-wc-booking-summary__item">
                <span class="lbl">Ma dat lich</span>
                <strong>#<?php echo esc_html( $code ); ?></strong>
            </div>
            <?php endif; ?>
            <div class="memora-wc-booking-summary__item">
                <span class="lbl">Ngay gio chup</span>
                <strong><?php echo esc_html( ( $pending['date'] ?? '' ) . '  ' . ( $pending['time'] ?? '' ) ); ?></strong>
            </div>
            <div class="memora-wc-booking-summary__item">
                <span class="lbl">Goi chup</span>
                <strong><?php echo esc_html( $pending['pkg_name'] ?? '' ); ?></strong>
            </div>
            <div class="memora-wc-booking-summary__item memora-wc-booking-summary__item--price">
                <span class="lbl">Can thanh toan</span>
                <strong><?php echo esc_html( $price_fmt ); ?></strong>
            </div>
        </div>
    </div>
    <style>
    .memora-wc-booking-summary{background:#eef6fc;border:1px solid #b8d9f0;border-radius:10px;padding:18px 22px;margin-bottom:28px}
    .memora-wc-booking-summary__title{font-size:16px;font-weight:700;color:#733e1c;margin:0 0 14px}
    .memora-wc-booking-summary__grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
    .memora-wc-booking-summary__item{background:#fff;border-radius:6px;padding:8px 12px;border:1px solid #dceef8;display:flex;flex-direction:column}
    .memora-wc-booking-summary__item--price{background:#fff4ed;border-color:#f0c8a8}
    .memora-wc-booking-summary__item .lbl{font-size:11px;font-weight:600;color:#8a7a72;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
    .memora-wc-booking-summary__item strong{font-size:14px;color:#333}
    .memora-wc-booking-summary__item--price strong{color:#b03a00;font-size:16px}
    @media(max-width:600px){.memora-wc-booking-summary__grid{grid-template-columns:1fr}}
    </style>
    <?php
}

add_action( 'woocommerce_checkout_order_created', 'memora_link_wc_order_to_booking', 10 );
function memora_link_wc_order_to_booking( $order ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    $booking_id   = (int) WC()->session->get( 'memora_booking_pending_id' );
    $booking_code = WC()->session->get( 'memora_booking_pending_code' );
    $pending      = WC()->session->get( 'memora_booking_pending_data' );
    $order_id     = $order->get_id();

    // =========================================================================
    // FLOW MOI: khong co booking_id trong session -> tao memora_booking moi
    // (user di tu [choose_time] -> WC checkout truc tiep)
    // =========================================================================
    if ( ! $booking_id && $pending ) {
        $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $phone = $order->get_billing_phone();
        $ig    = (string) $order->get_meta( '_memora_ig' );

        $code = function_exists( 'memora_generate_unique_booking_code' )
            ? memora_generate_unique_booking_code()
            : strtoupper( substr( md5( uniqid() ), 0, 4 ) );

        $post_title = sprintf( '#%s - %s - %s %s', $code, $name, $pending['date'] ?? '', $pending['time'] ?? '' );
        $booking_id = wp_insert_post( array(
            'post_title'  => $post_title,
            'post_status' => 'publish',
            'post_type'   => 'memora_booking',
        ) );

        if ( ! $booking_id || is_wp_error( $booking_id ) ) return;

        $deposit = ! empty( $pending['deposit_price'] ) ? floatval( $pending['deposit_price'] ) : floatval( $pending['total_price'] );

        update_post_meta( $booking_id, '_booking_code',          $code );
        update_post_meta( $booking_id, '_booking_customer_name', $name );
        update_post_meta( $booking_id, '_booking_phone',         $phone );
        update_post_meta( $booking_id, '_booking_contact_other', $ig );
        update_post_meta( $booking_id, '_booking_date',          $pending['date']      ?? '' );
        update_post_meta( $booking_id, '_booking_time',          $pending['time']      ?? '' );
        update_post_meta( $booking_id, '_booking_package_name',  $pending['pkg_name']  ?? '' );
        update_post_meta( $booking_id, '_booking_total_price',   $pending['total_price']  ?? 0 );
        update_post_meta( $booking_id, '_booking_deposit_price', $deposit );
        update_post_meta( $booking_id, '_booking_status',        'pending' );
        update_post_meta( $booking_id, '_booking_created_at',    current_time( 'mysql' ) );
        update_post_meta( $booking_id, '_wc_order_id',           $order_id );

        if ( ! empty( $pending['room_id'] ) ) {
            update_post_meta( $booking_id, '_booking_phong_id',   $pending['room_id'] );
            update_post_meta( $booking_id, '_booking_phong_name', $pending['room_name'] ?? '' );
            update_post_meta( $booking_id, '_booking_room_name',  $pending['room_name'] ?? '' );
        }
        if ( ! empty( $pending['dia_chi_id'] ) ) {
            update_post_meta( $booking_id, '_booking_dia_chi_id',   $pending['dia_chi_id'] );
            update_post_meta( $booking_id, '_booking_dia_chi_name', $pending['dia_chi_name'] ?? '' );
        }

        $booking_code = $code;
        $order->add_order_note( sprintf(
            'Dat lich tu dong (WC Checkout) | Code: #%s | %s %s | Goi: %s | Phone: %s',
            $code, $pending['date'] ?? '', $pending['time'] ?? '', $pending['pkg_name'] ?? '', $phone
        ) );
    }

    // =========================================================================
    // FLOW CU: da co booking_id -> chi lien ket
    // =========================================================================
    if ( $booking_id && $booking_code ) {
        update_post_meta( $booking_id, '_wc_order_id',    $order_id );
        update_post_meta( $booking_id, '_booking_status', 'pending' );
    }

    // Luu vao WC order meta
    $order->update_meta_data( '_memora_booking_id',   $booking_id );
    $order->update_meta_data( '_memora_booking_code', $booking_code );
    $order->save();

    // Xoa session
    WC()->session->__unset( 'memora_booking_pending_id' );
    WC()->session->__unset( 'memora_booking_pending_code' );
    WC()->session->__unset( 'memora_booking_pending_data' );
}

// =====================================================================
// 7. HIEN IG TRONG WC ORDER ADMIN (de admin biet cach lien he)
// =====================================================================
add_action( 'woocommerce_admin_order_data_after_billing_address', 'memora_show_ig_in_order_admin', 10 );
function memora_show_ig_in_order_admin( $order ) {
    $ig         = $order->get_meta( '_memora_ig' );
    $booking_id = $order->get_meta( '_memora_booking_id' );
    $code       = $order->get_meta( '_memora_booking_code' );

    if ( $ig ) {
        echo '<p><strong>Lien he khac (IG):</strong> ' . esc_html( $ig ) . '</p>';
    }
    if ( $code ) {
        $edit_url = $booking_id ? get_edit_post_link( $booking_id ) : '';
        echo '<p><strong>Ma dat lich:</strong> ';
        if ( $edit_url ) echo '<a href="' . esc_url( $edit_url ) . '">';
        echo '#' . esc_html( $code );
        if ( $edit_url ) echo '</a>';
        echo '</p>';
    }
}

// =====================================================================
// 8. DANG KY ACTION HOOK CHO woocommerce_checkout_payment
// =====================================================================
add_action( 'woocommerce_checkout_payment', 'memora_run_woocommerce_checkout_payment', 10 );
function memora_run_woocommerce_checkout_payment() {
    if ( function_exists( 'woocommerce_checkout_payment' ) ) {
        woocommerce_checkout_payment();
    }
}

