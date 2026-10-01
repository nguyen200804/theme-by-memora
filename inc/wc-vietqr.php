<?php
/**
 * WooCommerce VietQR Integration (Universal)
 * Hook vao woocommerce_thankyou (moi payment method), doc bank info tu WC gateway settings,
 * map ten ngan hang -> BIN tu dong.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =====================================================================
// 1. HOOK - fire cho MOI payment method
// =====================================================================
add_action( 'woocommerce_thankyou', 'memora_wc_vietqr_on_thankyou', 5 );
function memora_wc_vietqr_on_thankyou( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    memora_render_wc_vietqr( $order );
}

add_action( 'woocommerce_view_order', 'memora_wc_vietqr_view_order', 20 );
function memora_wc_vietqr_view_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) return;
    memora_render_wc_vietqr( $order );
}

// =====================================================================
// 2. BANG MAP TEN/MA NGAN HANG -> BIN VIETQR
// =====================================================================
function memora_get_bank_bin_map() {
    return array(
        '970418' => '970418', 'bidv' => '970418',
        '970436' => '970436', 'vietcombank' => '970436', 'vcb' => '970436',
        '970415' => '970415', 'vietinbank' => '970415', 'ctg' => '970415',
        '970405' => '970405', 'agribank' => '970405', 'agr' => '970405',
        '970422' => '970422', 'mb' => '970422', 'mbbank' => '970422',
        '970407' => '970407', 'techcombank' => '970407', 'tcb' => '970407',
        '970432' => '970432', 'vpbank' => '970432',
        '970416' => '970416', 'acb' => '970416',
        '970423' => '970423', 'tpbank' => '970423',
        '970403' => '970403', 'sacombank' => '970403', 'stb' => '970403',
        '970448' => '970448', 'ocb' => '970448',
        '970426' => '970426', 'msb' => '970426',
        '970441' => '970441', 'vib' => '970441',
        '970443' => '970443', 'shb' => '970443',
        '970437' => '970437', 'hdbank' => '970437',
        '970449' => '970449', 'lpbank' => '970449', 'lienvietpost' => '970449',
        '970440' => '970440', 'seabank' => '970440',
        '970425' => '970425', 'abbank' => '970425',
        '970428' => '970428', 'namabank' => '970428',
        '970452' => '970452', 'kienlongbank' => '970452',
        '970409' => '970409', 'bacabank' => '970409',
        '970430' => '970430', 'pgbank' => '970430',
        '970427' => '970427', 'vietabank' => '970427',
        '970408' => '970408', 'gpbank' => '970408',
        '970438' => '970438', 'bvbank' => '970438',
        '970429' => '970429', 'scb' => '970429',
        '970406' => '970406', 'dongabank' => '970406',
        '970414' => '970414', 'oceanbank' => '970414',
        '970444' => '970444', 'cbbank' => '970444',
        '970412' => '970412', 'pvcombank' => '970412',
        '970433' => '970433', 'vietbank' => '970433',
    );
}

function memora_bank_name_to_bin( $input ) {
    if ( empty( $input ) ) return '';
    $input = trim( $input );
    if ( preg_match( '/^\d{6}$/', $input ) ) return $input;
    $map = memora_get_bank_bin_map();
    $key = strtolower( preg_replace( '/\s+/', '', $input ) );
    if ( isset( $map[ $key ] ) ) return $map[ $key ];
    foreach ( $map as $k => $bin ) {
        if ( strlen( $k ) >= 3 && strpos( $key, $k ) !== false ) return $bin;
    }
    return '';
}

// =====================================================================
// 3. DOC THONG TIN NGAN HANG TU MOI NGUON
// =====================================================================
function memora_get_vietqr_bank_info( $order ) {
    $info = array( 'bank_id' => '', 'account_no' => '', 'account_name' => '', 'bank_name' => '', 'template' => 'compact2' );

    // Nguon 1: ACF Options
    if ( function_exists( 'get_field' ) ) {
        $info['bank_id']      = (string) get_field( 'vietqr_bank_id',      'option' );
        $info['account_no']   = (string) get_field( 'vietqr_account_no',   'option' );
        $info['account_name'] = (string) get_field( 'vietqr_account_name', 'option' );
        $info['bank_name']    = (string) get_field( 'vietqr_bank_name',    'option' );
        $tmpl = (string) get_field( 'vietqr_template', 'option' );
        if ( $tmpl ) $info['template'] = $tmpl;
    }
    if ( ! empty( $info['bank_id'] ) && ! empty( $info['account_no'] ) ) return $info;

    // Nguon 2: WC gateway settings cua don hang
    $pm = $order->get_payment_method();
    $gs = get_option( 'woocommerce_' . $pm . '_settings', array() );

    $candidates = array(
        'account_no'   => array( 'account_number', 'account_no', 'stk' ),
        'account_name' => array( 'account_name', 'ten_tai_khoan' ),
        'bank_name'    => array( 'bank_name', 'bank', 'bank_id', 'bank_code', 'bin', 'bank_bin' ),
    );
    foreach ( $candidates as $key => $fields ) {
        if ( ! empty( $info[ $key ] ) ) continue;
        foreach ( $fields as $f ) {
            if ( ! empty( $gs[ $f ] ) ) { $info[ $key ] = (string) $gs[ $f ]; break; }
        }
    }
    if ( empty( $info['bank_id'] ) && ! empty( $info['bank_name'] ) ) {
        $info['bank_id'] = memora_bank_name_to_bin( $info['bank_name'] );
    }

    // Nguon 3: WC BACS accounts (legacy)
    if ( empty( $info['account_no'] ) ) {
        $bacs = get_option( 'woocommerce_bacs_accounts', array() );
        if ( ! empty( $bacs[0] ) ) {
            $a = $bacs[0];
            if ( empty( $info['account_no'] ) )   $info['account_no']   = (string) ( $a['account_number'] ?? '' );
            if ( empty( $info['account_name'] ) )  $info['account_name'] = (string) ( $a['account_name']   ?? '' );
            if ( empty( $info['bank_name'] ) )     $info['bank_name']    = (string) ( $a['bank_name']      ?? '' );
            if ( empty( $info['bank_id'] ) ) {
                $bc = $a['sort_code'] ?? $a['bank_name'] ?? '';
                $info['bank_id'] = memora_bank_name_to_bin( $bc ) ?: $bc;
            }
        }
    }
    return $info;
}

// =====================================================================
// 4. RENDER QR
// =====================================================================
function memora_render_wc_vietqr( $order ) {
    $info = memora_get_vietqr_bank_info( $order );
    $bank_id = $info['bank_id']; $account_no = $info['account_no'];
    $account_name = $info['account_name']; $bank_name = $info['bank_name'];
    $template = $info['template'] ?: 'compact2';

    if ( empty( $account_no ) ) {
        if ( current_user_can( 'manage_options' ) )
            echo '<p style="background:#fff3cd;padding:10px;border-radius:6px;">Admin: Chua co thong tin tai khoan ngan hang. <a href="' . admin_url('admin.php?page=cau-hinh-thoi-gian-chup-anh') . '">Cau hinh ngay</a></p>';
        return;
    }

    $has_qr = ! empty( $bank_id );
    $amount = (int) round( $order->get_total() );
    $order_number = $order->get_order_number();
    $booking_code = (string) $order->get_meta( '_memora_booking_code' );
    $add_info = $booking_code ? ( 'MEMORA ' . $booking_code ) : ( 'MEMORA DH' . $order_number );

    $qr_url = '';
    if ( $has_qr ) {
        $qr_params = http_build_query( array( 'amount' => $amount > 0 ? $amount : '', 'addInfo' => $add_info, 'accountName' => $account_name ) );
        $qr_url = 'https://img.vietqr.io/image/' . rawurlencode($bank_id) . '-' . rawurlencode($account_no) . '-' . rawurlencode($template) . '.png?' . $qr_params;
    }
    ?>
    <section class="memora-wc-vietqr">
        <h3 class="memora-wc-vietqr__title"><?php echo $has_qr ? '&#x1F4B3; Quet ma QR de thanh toan' : '&#x1F3E6; Thong tin chuyen khoan'; ?></h3>
        <div class="memora-wc-vietqr__body">
            <?php if ( $has_qr ) : ?>
            <div class="memora-wc-vietqr__qr">
                <img src="<?php echo esc_url( $qr_url ); ?>" alt="QR #<?php echo esc_attr($order_number); ?>" loading="lazy" />
            </div>
            <?php endif; ?>
            <div class="memora-wc-vietqr__info">
                <?php if ( $bank_name ) : ?><div class="memora-wc-vietqr__row"><span class="memora-wc-vietqr__label">Ngan hang</span><span class="memora-wc-vietqr__value"><?php echo esc_html($bank_name); ?></span></div><?php endif; ?>
                <div class="memora-wc-vietqr__row"><span class="memora-wc-vietqr__label">So TK</span><span class="memora-wc-vietqr__value"><span class="memora-wc-vietqr__copy" data-copy="<?php echo esc_attr($account_no); ?>"><?php echo esc_html($account_no); ?> <button class="memora-wc-copy-btn" type="button">&#x1F4CB;</button></span></span></div>
                <?php if ( $account_name ) : ?><div class="memora-wc-vietqr__row"><span class="memora-wc-vietqr__label">Chu TK</span><span class="memora-wc-vietqr__value"><?php echo esc_html($account_name); ?></span></div><?php endif; ?>
                <?php if ( $amount > 0 ) : ?><div class="memora-wc-vietqr__row memora-wc-vietqr__row--amount"><span class="memora-wc-vietqr__label">So tien</span><span class="memora-wc-vietqr__value"><?php echo esc_html(number_format($amount,0,',','.')); ?>d</span></div><?php endif; ?>
                <div class="memora-wc-vietqr__row memora-wc-vietqr__row--content"><span class="memora-wc-vietqr__label">Noi dung CK</span><span class="memora-wc-vietqr__value"><span class="memora-wc-vietqr__copy" data-copy="<?php echo esc_attr($add_info); ?>"><?php echo esc_html($add_info); ?> <button class="memora-wc-copy-btn" type="button">&#x1F4CB;</button></span></span></div>
            </div>
        </div>
        <p class="memora-wc-vietqr__note">&#x23F1; Sau khi chuyen khoan, don hang xac nhan trong vong <strong>15 phut</strong>.</p>
    </section>
    <style>
    .memora-wc-vietqr{background:#eef6fc;border:1px solid #b8d9f0;border-radius:12px;padding:24px 28px;margin:24px 0}
    .memora-wc-vietqr__title{font-size:18px;font-weight:700;color:#733e1c;margin:0 0 20px;text-align:center}
    .memora-wc-vietqr__body{display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap}
    .memora-wc-vietqr__qr img{width:200px;height:auto;border-radius:8px;display:block;box-shadow:0 2px 12px rgba(0,0,0,.08)}
    .memora-wc-vietqr__info{flex:1 1 200px;display:flex;flex-direction:column;gap:10px}
    .memora-wc-vietqr__row{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#fff;border-radius:6px;border:1px solid #cce4f5}
    .memora-wc-vietqr__row--amount{background:#fff4ed;border-color:#f0c8a8}
    .memora-wc-vietqr__row--content{background:#fdf4f0;border-color:#e8c4b0}
    .memora-wc-vietqr__label{font-size:13px;font-weight:600;color:#8a7a72;min-width:90px;flex-shrink:0}
    .memora-wc-vietqr__value{font-size:14px;font-weight:700;color:#733e1c}
    .memora-wc-vietqr__row--amount .memora-wc-vietqr__value{font-size:16px;color:#b03a00}
    .memora-wc-vietqr__copy{display:inline-flex;align-items:center;gap:6px}
    .memora-wc-copy-btn{background:none;border:none;cursor:pointer;font-size:14px;opacity:.7;transition:opacity .2s;line-height:1;padding:0 2px}
    .memora-wc-copy-btn:hover{opacity:1}
    .memora-wc-vietqr__note{margin:16px 0 0;font-size:13px;color:#7a8e99;text-align:center;font-style:italic}
    .memora-wc-vietqr__note strong{color:#733e1c}
    @media(max-width:480px){.memora-wc-vietqr__body{flex-direction:column;align-items:center}.memora-wc-vietqr__label{min-width:70px}}
    </style>
    <script>
    (function(){document.querySelectorAll('.memora-wc-copy-btn').forEach(function(b){b.addEventListener('click',function(){var e=b.closest('.memora-wc-vietqr__copy'),t=e?e.dataset.copy:'';if(!t)return;navigator.clipboard.writeText(t).then(function(){b.textContent='ok';setTimeout(function(){b.textContent='\u{1F4CB}';},1500)});})});})();
    </script>
    <?php
}

// =====================================================================
// 5. EMAIL
// =====================================================================
add_action( 'woocommerce_email_before_order_table', 'memora_wc_vietqr_in_email', 10, 4 );
function memora_wc_vietqr_in_email( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $plain_text ) return;
    if ( ! in_array( $email->id, array( 'customer_on_hold_order', 'new_order' ), true ) ) return;
    $info = memora_get_vietqr_bank_info( $order );
    if ( empty( $info['account_no'] ) ) return;
    $amount = (int) round( $order->get_total() );
    $order_number = $order->get_order_number();
    $booking_code = (string) $order->get_meta( '_memora_booking_code' );
    $add_info = $booking_code ? ( 'MEMORA ' . $booking_code ) : ( 'MEMORA DH' . $order_number );
    $qr_url = '';
    if ( ! empty( $info['bank_id'] ) ) {
        $qp = http_build_query( array( 'amount' => $amount > 0 ? $amount : '', 'addInfo' => $add_info, 'accountName' => $info['account_name'] ) );
        $qr_url = 'https://img.vietqr.io/image/' . rawurlencode($info['bank_id']) . '-' . rawurlencode($info['account_no']) . '-' . rawurlencode($info['template'] ?: 'compact2') . '.png?' . $qp;
    }
    echo '<div style="margin:20px 0;padding:20px;background:#eef6fc;border:1px solid #b8d9f0;border-radius:8px;">';
    echo '<h3 style="color:#733e1c;font-size:16px;margin:0 0 16px;text-align:center;">Thong tin thanh toan</h3>';
    if ( $qr_url ) echo '<img src="' . esc_url($qr_url) . '" alt="QR" style="width:180px;height:auto;display:block;margin:0 auto 16px;" />';
    echo '<table style="width:100%;border-collapse:collapse;">';
    if ( $info['bank_name'] ) echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;width:100px;">Ngan hang</td><td style="font-weight:700;color:#733e1c;">' . esc_html($info['bank_name']) . '</td></tr>';
    echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">So TK</td><td style="font-weight:700;color:#733e1c;">' . esc_html($info['account_no']) . '</td></tr>';
    if ( $info['account_name'] ) echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Chu TK</td><td style="font-weight:700;color:#733e1c;">' . esc_html($info['account_name']) . '</td></tr>';
    if ( $amount > 0 ) echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">So tien</td><td style="font-weight:700;color:#b03a00;">' . esc_html(number_format($amount,0,',','.')) . 'd</td></tr>';
    echo '<tr><td style="padding:6px 0;color:#8a7a72;font-weight:600;">Noi dung CK</td><td style="font-weight:700;color:#733e1c;background:#fdf4f0;padding:4px 8px;border-radius:4px;">' . esc_html($add_info) . '</td></tr>';
    echo '</table>';
    echo '<p style="margin:12px 0 0;font-size:12px;color:#7a8e99;text-align:center;">Don hang xac nhan trong 15 phut.</p>';
    echo '</div>';
}
