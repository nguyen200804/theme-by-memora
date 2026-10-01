<?php
/**
 * WooCommerce BACS + VietQR Integration
 *
 * Tự động hiển thị mã QR VietQR trên trang xác nhận đơn hàng
 * sau khi khách chọn thanh toán "Direct Bank Transfer (Scan VietQR)".
 *
 * Đọc thông tin ngân hàng từ:
 *   1. ACF Options Page (vietqr_bank_id, vietqr_account_no, ...)
 *   2. Fallback: WooCommerce BACS accounts settings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =====================================================================
// 1. HIỂN THỊ VIETQR TRÊN TRANG XÁC NHẬN ĐƠN HÀNG (BACS)
// =====================================================================
add_action( 'woocommerce_thankyou_bacs', 'memora_wc_vietqr_on_thankyou', 5 );
function memora_wc_vietqr_on_thankyou( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    memora_render_wc_vietqr( $order );
}

// Cũng hiển thị trên trang My Account > View Order
add_action( 'woocommerce_view_order', 'memora_wc_vietqr_view_order', 20 );
function memora_wc_vietqr_view_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    if ( $order->get_payment_method() !== 'bacs' ) return;
    if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) return;

    memora_render_wc_vietqr( $order );
}

// =====================================================================
// 2. HÀM RENDER QR VIETQR CHÍNH
// =====================================================================
function memora_render_wc_vietqr( $order ) {
    // --- Lấy thông tin ngân hàng từ ACF Options ---
    $bank_id      = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_id',      'option' ) : '';
    $account_no   = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_no',   'option' ) : '';
    $account_name = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_name', 'option' ) : '';
    $bank_name    = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_name',    'option' ) : '';
    $template     = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_template',     'option' ) : 'compact2';
    if ( empty( $template ) ) $template = 'compact2';

    // --- Fallback: lấy từ WooCommerce BACS accounts nếu ACF chưa cấu hình ---
    if ( empty( $bank_id ) || empty( $account_no ) ) {
        $bacs_accounts = get_option( 'woocommerce_bacs_accounts', array() );
        if ( ! empty( $bacs_accounts[0] ) ) {
            $acc          = $bacs_accounts[0];
            $account_no   = ! empty( $acc['account_number'] ) ? $acc['account_number'] : '';
            $account_name = ! empty( $acc['account_name'] )   ? $acc['account_name']   : '';
            $bank_name    = ! empty( $acc['bank_name'] )      ? $acc['bank_name']       : '';
            // sort_code dùng để lưu BIN nếu không có ACF
            $bank_id      = ! empty( $acc['sort_code'] )      ? $acc['sort_code']       : '';
        }
    }

    if ( empty( $bank_id ) || empty( $account_no ) ) return;

    // --- Thông tin đơn hàng ---
    $amount       = (int) round( $order->get_total() );
    $order_number = $order->get_order_number();
    $add_info     = 'MEMORA DH' . $order_number;

    // --- Build VietQR image URL ---
    $qr_params = http_build_query( array(
        'amount'      => $amount > 0 ? $amount : '',
        'addInfo'     => $add_info,
        'accountName' => $account_name,
    ) );
    $qr_url = 'https://img.vietqr.io/image/'
        . rawurlencode( $bank_id ) . '-'
        . rawurlencode( $account_no ) . '-'
        . rawurlencode( $template ) . '.png?'
        . $qr_params;

    ?>
    <section class="memora-wc-vietqr woocommerce-order-vietqr">
        <h3 class="memora-wc-vietqr__title">
            💳 Quét mã QR để thanh toán
        </h3>

        <div class="memora-wc-vietqr__body">

            <!-- QR CODE -->
            <div class="memora-wc-vietqr__qr">
                <img
                    src="<?php echo esc_url( $qr_url ); ?>"
                    alt="QR VietQR - Thanh toán đơn hàng #<?php echo esc_attr( $order_number ); ?>"
                    loading="lazy"
                />
            </div>

            <!-- THÔNG TIN CHUYỂN KHOẢN -->
            <div class="memora-wc-vietqr__info">

                <?php if ( ! empty( $bank_name ) ) : ?>
                <div class="memora-wc-vietqr__row">
                    <span class="memora-wc-vietqr__label">Ngân hàng</span>
                    <span class="memora-wc-vietqr__value"><?php echo esc_html( $bank_name ); ?></span>
                </div>
                <?php endif; ?>

                <div class="memora-wc-vietqr__row">
                    <span class="memora-wc-vietqr__label">Số TK</span>
                    <span class="memora-wc-vietqr__value">
                        <span class="memora-wc-vietqr__copy" data-copy="<?php echo esc_attr( $account_no ); ?>">
                            <?php echo esc_html( $account_no ); ?>
                            <button class="memora-wc-copy-btn" type="button">📋</button>
                        </span>
                    </span>
                </div>

                <?php if ( ! empty( $account_name ) ) : ?>
                <div class="memora-wc-vietqr__row">
                    <span class="memora-wc-vietqr__label">Chủ TK</span>
                    <span class="memora-wc-vietqr__value"><?php echo esc_html( $account_name ); ?></span>
                </div>
                <?php endif; ?>

                <?php if ( $amount > 0 ) : ?>
                <div class="memora-wc-vietqr__row memora-wc-vietqr__row--amount">
                    <span class="memora-wc-vietqr__label">Số tiền</span>
                    <span class="memora-wc-vietqr__value"><?php echo esc_html( number_format( $amount, 0, ',', '.' ) ); ?>đ</span>
                </div>
                <?php endif; ?>

                <div class="memora-wc-vietqr__row memora-wc-vietqr__row--content">
                    <span class="memora-wc-vietqr__label">Nội dung CK</span>
                    <span class="memora-wc-vietqr__value">
                        <span class="memora-wc-vietqr__copy" data-copy="<?php echo esc_attr( $add_info ); ?>">
                            <?php echo esc_html( $add_info ); ?>
                            <button class="memora-wc-copy-btn" type="button">📋</button>
                        </span>
                    </span>
                </div>

            </div>
        </div>

        <p class="memora-wc-vietqr__note">
            ⏱ Sau khi chuyển khoản, đơn hàng sẽ được xác nhận trong vòng <strong>15 phút</strong>.
            Giữ lại ảnh chụp màn hình chuyển khoản để đối chiếu khi cần nhé!
        </p>
    </section>

    <style>
    .memora-wc-vietqr {
        background: #eef6fc;
        border: 1px solid #b8d9f0;
        border-radius: 12px;
        padding: 24px 28px;
        margin: 24px 0;
    }
    .memora-wc-vietqr__title {
        font-size: 18px;
        font-weight: 700;
        color: #733e1c;
        margin: 0 0 20px;
        text-align: center;
    }
    .memora-wc-vietqr__body {
        display: flex;
        align-items: flex-start;
        gap: 24px;
        flex-wrap: wrap;
    }
    .memora-wc-vietqr__qr { flex: 0 0 auto; }
    .memora-wc-vietqr__qr img {
        width: 200px;
        height: auto;
        border-radius: 8px;
        display: block;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    .memora-wc-vietqr__info {
        flex: 1 1 200px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        justify-content: center;
    }
    .memora-wc-vietqr__row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: #fff;
        border-radius: 6px;
        border: 1px solid #cce4f5;
    }
    .memora-wc-vietqr__row--amount {
        background: #fff4ed;
        border-color: #f0c8a8;
    }
    .memora-wc-vietqr__row--content {
        background: #fdf4f0;
        border-color: #e8c4b0;
    }
    .memora-wc-vietqr__label {
        font-size: 13px;
        font-weight: 600;
        color: #8a7a72;
        min-width: 90px;
        flex-shrink: 0;
    }
    .memora-wc-vietqr__value {
        font-size: 14px;
        font-weight: 700;
        color: #733e1c;
    }
    .memora-wc-vietqr__row--amount .memora-wc-vietqr__value {
        font-size: 16px;
        color: #b03a00;
    }
    .memora-wc-vietqr__copy {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .memora-wc-copy-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        padding: 0 2px;
        opacity: 0.7;
        transition: opacity 0.2s;
        line-height: 1;
    }
    .memora-wc-copy-btn:hover { opacity: 1; }
    .memora-wc-vietqr__note {
        margin: 16px 0 0;
        font-size: 13px;
        color: #7a8e99;
        text-align: center;
        font-style: italic;
    }
    .memora-wc-vietqr__note strong { color: #733e1c; }
    @media (max-width: 480px) {
        .memora-wc-vietqr__body { flex-direction: column; align-items: center; }
        .memora-wc-vietqr__label { min-width: 70px; }
    }
    </style>

    <script>
    (function () {
        document.querySelectorAll('.memora-wc-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var el = btn.closest('.memora-wc-vietqr__copy');
                var text = el ? el.dataset.copy : '';
                if (!text) return;
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = '✅';
                    setTimeout(function () { btn.textContent = '📋'; }, 1500);
                });
            });
        });
    })();
    </script>
    <?php
}

// =====================================================================
// 3. THÊM HƯỚNG DẪN VietQR VÀO EMAIL XÁC NHẬN ĐƠN HÀNG
// =====================================================================
add_action( 'woocommerce_email_before_order_table', 'memora_wc_vietqr_in_email', 10, 4 );
function memora_wc_vietqr_in_email( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $plain_text ) return;
    if ( $order->get_payment_method() !== 'bacs' ) return;
    if ( ! in_array( $email->id, array( 'customer_on_hold_order', 'new_order' ), true ) ) return;

    $bank_id      = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_id',      'option' ) : '';
    $account_no   = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_no',   'option' ) : '';
    $account_name = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_name', 'option' ) : '';
    $bank_name    = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_name',    'option' ) : '';
    $template     = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_template',     'option' ) : 'compact2';
    if ( empty( $template ) ) $template = 'compact2';

    if ( empty( $bank_id ) || empty( $account_no ) ) return;

    $amount       = (int) round( $order->get_total() );
    $order_number = $order->get_order_number();
    $add_info     = 'MEMORA DH' . $order_number;

    $qr_params = http_build_query( array(
        'amount'      => $amount > 0 ? $amount : '',
        'addInfo'     => $add_info,
        'accountName' => $account_name,
    ) );
    $qr_url = 'https://img.vietqr.io/image/'
        . rawurlencode( $bank_id ) . '-'
        . rawurlencode( $account_no ) . '-'
        . rawurlencode( $template ) . '.png?'
        . $qr_params;

    echo '<div style="margin:20px 0;padding:20px;background:#eef6fc;border:1px solid #b8d9f0;border-radius:8px;">';
    echo '<h3 style="color:#733e1c;font-size:16px;margin:0 0 16px;">💳 Quét mã QR để thanh toán</h3>';
    echo '<img src="' . esc_url( $qr_url ) . '" alt="QR VietQR" style="width:180px;height:auto;display:block;margin:0 auto 16px;" />';
    echo '<table style="width:100%;border-collapse:collapse;">';
    if ( $bank_name )    echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;width:120px;">Ngân hàng</td><td style="font-weight:700;color:#733e1c;">' . esc_html( $bank_name ) . '</td></tr>';
    echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Số TK</td><td style="font-weight:700;color:#733e1c;">' . esc_html( $account_no ) . '</td></tr>';
    if ( $account_name ) echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Chủ TK</td><td style="font-weight:700;color:#733e1c;">' . esc_html( $account_name ) . '</td></tr>';
    if ( $amount > 0 )   echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Số tiền</td><td style="font-weight:700;color:#b03a00;font-size:16px;">' . esc_html( number_format( $amount, 0, ',', '.' ) ) . 'đ</td></tr>';
    echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Nội dung CK</td><td style="font-weight:700;color:#733e1c;background:#fdf4f0;padding:4px 8px;border-radius:4px;">' . esc_html( $add_info ) . '</td></tr>';
    echo '</table>';
    echo '<p style="margin:12px 0 0;font-size:12px;color:#7a8e99;font-style:italic;">⏱ Đơn hàng sẽ được xác nhận trong vòng 15 phút sau khi chuyển khoản.</p>';
    echo '</div>';
}
