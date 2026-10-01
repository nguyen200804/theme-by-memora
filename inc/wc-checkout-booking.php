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

    foreach ( $cart->get_cart() as $item ) {
        if ( ! empty( $item['memora_booking_id'] ) ) {
            $item['data']->set_price( $price );
        }
    }
}

// =====================================================================
// 4. PRE-FILL BILLING FIELDS TU SESSION
// =====================================================================
add_filter( 'woocommerce_checkout_get_value', 'memora_prefill_billing_from_session', 10, 2 );
function memora_prefill_billing_from_session( $value, $input ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return $value;

    $pending = WC()->session->get( 'memora_booking_pending_data' );
    if ( ! $pending ) return $value;

    if ( 'billing_first_name' === $input && empty( $value ) ) return $pending['name'] ?? $value;
    if ( 'billing_last_name'  === $input && empty( $value ) ) return '';
    if ( 'billing_phone'      === $input && empty( $value ) ) return $pending['phone'] ?? $value;

    return $value;
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

// =====================================================================
// 6. KHI WC ORDER DUOC TAO -> LIEN KET VOI MEMORA_BOOKING + LUU IG
// =====================================================================
add_action( 'woocommerce_checkout_order_created', 'memora_link_wc_order_to_booking', 10 );
function memora_link_wc_order_to_booking( $order ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    $booking_id   = (int) WC()->session->get( 'memora_booking_pending_id' );
    $booking_code = WC()->session->get( 'memora_booking_pending_code' );
    $pending      = WC()->session->get( 'memora_booking_pending_data' );

    if ( ! $booking_id || ! $booking_code ) return;

    $order_id = $order->get_id();

    // Lien ket booking <-> WC order
    update_post_meta( $booking_id, '_wc_order_id',    $order_id );
    update_post_meta( $booking_id, '_booking_status', 'pending' );

    // Luu vao WC order meta
    $order->update_meta_data( '_memora_booking_id',   $booking_id );
    $order->update_meta_data( '_memora_booking_code', $booking_code );

    // Luu IG / phuong thuc lien he khac
    if ( ! empty( $pending['ig'] ) ) {
        $order->update_meta_data( '_memora_ig', $pending['ig'] );
    }
    $order->save();

    // Xoa session sau khi luu xong
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
