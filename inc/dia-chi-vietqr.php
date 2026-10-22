<?php
/**
 * Tích hợp Plugin VietQR (Casso Team) theo từng Chi nhánh (taxonomy: dia-chi)
 *
 * Cơ chế hoạt động:
 * 1. Tích hợp trực tiếp với plugin "VietQR" (WC_Gateway_VietQR - section=vietqr).
 * 2. Cho phép cấu hình Ngân hàng (Bank name), Chủ TK (Account name), Số TK (Account number)
 *    và Tiền tố nội dung (Prefix) riêng cho từng term của taxonomy 'dia-chi' ở cả 2 nơi:
 *    - Ngay trong trang cài đặt WooCommerce -> Payments -> VietQR (section=vietqr)
 *    - Trong trang quản trị Phòng chụp ảnh -> Địa chỉ (edit-tags.php?taxonomy=dia-chi)
 * 3. Khi khách đặt lịch và thanh toán đơn hàng thuộc chi nhánh nào, tự động truyền thông tin
 *    ngân hàng của chi nhánh đó vào plugin VietQR thông qua:
 *    - Filter 'woocommerce_vietqr_account_fields'
 *    - Filter 'option_woocommerce_vietqr_accounts'
 *    - Cập nhật trực tiếp thuộc tính $gateway->account_details & $gateway->prefix của WC_Gateway_VietQR
 *      trước khi render trang Thank You, View Order và Email.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//====================================
// 1. LẤY DANH SÁCH NGÂN HÀNG TỪ PLUGIN VIETQR
//====================================
/**
 * Lấy danh sách ngân hàng chuẩn từ plugin VietQR (bao gồm mã BIN 6 số, tên ngân hàng, logo)
 */
function memora_get_vietqr_plugin_banks() {
    static $cached_banks = null;
    if ( $cached_banks !== null ) {
        return $cached_banks;
    }

    // 1. VietQR v4.x+: gọi WC_Gateway_VietQR::get_banks()
    if ( class_exists( 'WC_Gateway_VietQR' ) && method_exists( 'WC_Gateway_VietQR', 'get_banks' ) ) {
        $res = WC_Gateway_VietQR::get_banks();
        if ( ! empty( $res['data'] ) && is_array( $res['data'] ) ) {
            $cached_banks = $res['data'];
            return $cached_banks;
        }
    }

    // 2. VietQR v3.x: đọc từ cache trong option 'woocommerce_vietqr_settings'
    $vq_settings = get_option( 'woocommerce_vietqr_settings', array() );
    if ( is_array( $vq_settings ) && ! empty( $vq_settings['vietqr_banks']['data'] ) && is_array( $vq_settings['vietqr_banks']['data'] ) ) {
        $cached_banks = $vq_settings['vietqr_banks']['data'];
        return $cached_banks;
    }

    // 3. Fallback danh sách 37 ngân hàng chuẩn Napas / VietQR (khớp cấu trúc API VietQR)
    $cached_banks = array(
        array( 'bin' => '970436', 'code' => 'VCB',   'short_name' => 'Vietcombank',  'name' => 'Ngân hàng TMCP Ngoại thương Việt Nam',          'logo' => 'https://api.vietqr.io/img/VCB.png' ),
        array( 'bin' => '970415', 'code' => 'ICB',   'short_name' => 'VietinBank',   'name' => 'Ngân hàng TMCP Công thương Việt Nam',           'logo' => 'https://api.vietqr.io/img/ICB.png' ),
        array( 'bin' => '970418', 'code' => 'BIDV',  'short_name' => 'BIDV',         'name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam',  'logo' => 'https://api.vietqr.io/img/BIDV.png' ),
        array( 'bin' => '970405', 'code' => 'VBA',   'short_name' => 'Agribank',     'name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn', 'logo' => 'https://api.vietqr.io/img/VBA.png' ),
        array( 'bin' => '970407', 'code' => 'TCB',   'short_name' => 'Techcombank',  'name' => 'Ngân hàng TMCP Kỹ thương Việt Nam',             'logo' => 'https://api.vietqr.io/img/TCB.png' ),
        array( 'bin' => '970422', 'code' => 'MB',    'short_name' => 'MBBank',       'name' => 'Ngân hàng TMCP Quân đội',                       'logo' => 'https://api.vietqr.io/img/MB.png' ),
        array( 'bin' => '970416', 'code' => 'ACB',   'short_name' => 'ACB',          'name' => 'Ngân hàng TMCP Á Châu',                         'logo' => 'https://api.vietqr.io/img/ACB.png' ),
        array( 'bin' => '970432', 'code' => 'VPB',   'short_name' => 'VPBank',       'name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng',           'logo' => 'https://api.vietqr.io/img/VPB.png' ),
        array( 'bin' => '970423', 'code' => 'TPB',   'short_name' => 'TPBank',       'name' => 'Ngân hàng TMCP Tiên Phong',                     'logo' => 'https://api.vietqr.io/img/TPB.png' ),
        array( 'bin' => '970403', 'code' => 'STB',   'short_name' => 'Sacombank',    'name' => 'Ngân hàng TMCP Sài Gòn Thương Tín',             'logo' => 'https://api.vietqr.io/img/STB.png' ),
        array( 'bin' => '970437', 'code' => 'HDB',   'short_name' => 'HDBank',       'name' => 'Ngân hàng TMCP Phát triển TP. Hồ Chí Minh',     'logo' => 'https://api.vietqr.io/img/HDB.png' ),
        array( 'bin' => '970441', 'code' => 'VIB',   'short_name' => 'VIB',          'name' => 'Ngân hàng TMCP Quốc tế Việt Nam',               'logo' => 'https://api.vietqr.io/img/VIB.png' ),
        array( 'bin' => '970443', 'code' => 'SHB',   'short_name' => 'SHB',          'name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội',               'logo' => 'https://api.vietqr.io/img/SHB.png' ),
        array( 'bin' => '970431', 'code' => 'EIB',   'short_name' => 'Eximbank',     'name' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam',        'logo' => 'https://api.vietqr.io/img/EIB.png' ),
        array( 'bin' => '970426', 'code' => 'MSB',   'short_name' => 'MSB',          'name' => 'Ngân hàng TMCP Hàng Hải Việt Nam',              'logo' => 'https://api.vietqr.io/img/MSB.png' ),
        array( 'bin' => '970448', 'code' => 'OCB',   'short_name' => 'OCB',          'name' => 'Ngân hàng TMCP Phương Đông',                    'logo' => 'https://api.vietqr.io/img/OCB.png' ),
        array( 'bin' => '970449', 'code' => 'LPB',   'short_name' => 'LPBank',       'name' => 'Ngân hàng TMCP Lộc Phát Việt Nam',              'logo' => 'https://api.vietqr.io/img/LPB.png' ),
        array( 'bin' => '970440', 'code' => 'SEAB',  'short_name' => 'SeABank',      'name' => 'Ngân hàng TMCP Đông Nam Á',                     'logo' => 'https://api.vietqr.io/img/SEAB.png' ),
        array( 'bin' => '970428', 'code' => 'NAB',   'short_name' => 'NamABank',     'name' => 'Ngân hàng TMCP Nam Á',                          'logo' => 'https://api.vietqr.io/img/NAB.png' ),
        array( 'bin' => '970409', 'code' => 'BAB',   'short_name' => 'BacABank',     'name' => 'Ngân hàng TMCP Bắc Á',                          'logo' => 'https://api.vietqr.io/img/BAB.png' ),
        array( 'bin' => '970412', 'code' => 'PVCB',  'short_name' => 'PVcomBank',    'name' => 'Ngân hàng TMCP Đại Chúng Việt Nam',             'logo' => 'https://api.vietqr.io/img/PVCB.png' ),
        array( 'bin' => '970425', 'code' => 'ABB',   'short_name' => 'ABBank',       'name' => 'Ngân hàng TMCP An Bình',                        'logo' => 'https://api.vietqr.io/img/ABB.png' ),
        array( 'bin' => '970427', 'code' => 'VAB',   'short_name' => 'VietABank',    'name' => 'Ngân hàng TMCP Việt Á',                         'logo' => 'https://api.vietqr.io/img/VAB.png' ),
        array( 'bin' => '546034', 'code' => 'CAKE',  'short_name' => 'CAKE',         'name' => 'TMCP Việt Nam Thịnh Vượng - Ngân hàng số CAKE', 'logo' => 'https://api.vietqr.io/img/CAKE.png' ),
        array( 'bin' => '963388', 'code' => 'TIMO',  'short_name' => 'Timo',         'name' => 'Ngân hàng số Timo by Ban Viet Bank',            'logo' => 'https://api.vietqr.io/img/TIMO.png' ),
    );

    return $cached_banks;
}

/**
 * Tìm thông tin ngân hàng theo BIN (VD: 970418) hoặc mã code (VD: BIDV, MB, VCB)
 */
function memora_find_vietqr_bank_by_id( $bank_id_or_code ) {
    $bank_id_or_code = trim( (string) $bank_id_or_code );
    if ( $bank_id_or_code === '' ) {
        return null;
    }

    $banks = memora_get_vietqr_plugin_banks();
    $upper = strtoupper( $bank_id_or_code );

    foreach ( $banks as $b ) {
        $bin   = isset( $b['bin'] ) ? (string) $b['bin'] : '';
        $code  = isset( $b['code'] ) ? strtoupper( (string) $b['code'] ) : '';
        $short = isset( $b['short_name'] ) ? strtoupper( (string) $b['short_name'] ) : ( isset( $b['shortName'] ) ? strtoupper( (string) $b['shortName'] ) : '' );

        if ( $bin === $bank_id_or_code || $code === $upper || $short === $upper ) {
            return $b;
        }
    }

    return null;
}

/**
 * Làm sạch chuỗi Prefix theo đúng quy tắc của plugin VietQR (không dấu cách, tối đa 15 ký tự chữ/số)
 */
function memora_clean_vietqr_prefix( $prefix ) {
    $prefix = str_replace( ' ', '', trim( (string) $prefix ) );
    if ( strlen( $prefix ) > 15 ) {
        $prefix = substr( $prefix, 0, 15 );
    }
    return preg_replace( '/[^A-Za-z0-9\-]/', '', $prefix );
}


//====================================
// 2. ĐỌC & LƯU CẤU HÌNH VIETQR CỦA TỪNG TERM 'dia-chi'
//====================================
/**
 * Lấy cấu hình tài khoản VietQR của 1 term dia-chi
 *
 * @param int $term_id Term ID của taxonomy dia-chi
 * @return array|null  Trả về mảng { bank_id (BIN), bank_name, account_name, account_number, prefix, dia_chi_id, dia_chi_name } hoặc null nếu chưa cấu hình
 */
function memora_get_branch_vietqr_account( $term_id ) {
    $term_id = intval( $term_id );
    if ( $term_id <= 0 ) {
        return null;
    }

    $bank_id        = trim( (string) get_term_meta( $term_id, '_memora_vietqr_bank_id', true ) );
    $bank_name      = trim( (string) get_term_meta( $term_id, '_memora_vietqr_bank_name', true ) );
    $account_name   = trim( (string) get_term_meta( $term_id, '_memora_vietqr_account_name', true ) );
    $account_number = trim( (string) get_term_meta( $term_id, '_memora_vietqr_account_no', true ) );
    $prefix         = trim( (string) get_term_meta( $term_id, '_memora_vietqr_prefix', true ) );

    if ( empty( $bank_id ) || empty( $account_number ) ) {
        return null;
    }

    // Đảm bảo bank_id luôn là mã BIN 6 số mà plugin VietQR yêu cầu (VD: '970418')
    $matched_bank = memora_find_vietqr_bank_by_id( $bank_id );
    if ( $matched_bank ) {
        $bank_id = (string) $matched_bank['bin'];
        if ( empty( $bank_name ) || $bank_name === $matched_bank['code'] || $bank_name === ( $matched_bank['short_name'] ?? '' ) ) {
            $bank_name = $matched_bank['name'];
        }
    }

    // Nếu chi nhánh không điền prefix riêng, lấy prefix mặc định trong cài đặt VietQR
    if ( empty( $prefix ) ) {
        $vq_settings = get_option( 'woocommerce_vietqr_settings', array() );
        $prefix      = ! empty( $vq_settings['prefix'] ) ? $vq_settings['prefix'] : 'MEMORA';
    }

    $term = get_term( $term_id, 'dia-chi' );

    return array(
        'dia_chi_id'     => $term_id,
        'dia_chi_name'   => ( $term && ! is_wp_error( $term ) ) ? $term->name : '',
        'bank_id'        => $bank_id,        // Mã BIN 6 số (VD: 970418)
        'bank_name'      => $bank_name,      // Tên đầy đủ ngân hàng (VD: Ngân hàng TMCP Đầu tư và Phát triển Việt Nam)
        'bank_short'     => $matched_bank ? ( $matched_bank['short_name'] ?? $matched_bank['shortName'] ?? $matched_bank['code'] ?? '' ) : $bank_id,
        'account_name'   => $account_name,
        'account_number' => $account_number,
        'prefix'         => memora_clean_vietqr_prefix( $prefix ),
    );
}

/**
 * Lưu cấu hình tài khoản VietQR cho 1 term dia-chi
 *
 * @param int    $term_id        Term ID của dia-chi
 * @param string $raw_bank_val   Giá trị từ select: "{bin}:{bank_name}" hoặc "{bin}"
 * @param string $account_name   Tên chủ tài khoản
 * @param string $account_number Số tài khoản
 * @param string $prefix         Prefix nội dung chuyển khoản
 */
function memora_save_branch_vietqr_account( $term_id, $raw_bank_val, $account_name, $account_number, $prefix = '' ) {
    $term_id = intval( $term_id );
    if ( $term_id <= 0 ) {
        return;
    }

    $raw_bank_val   = trim( sanitize_text_field( $raw_bank_val ) );
    $account_name   = strtoupper( trim( sanitize_text_field( $account_name ) ) );
    $account_number = trim( sanitize_text_field( $account_number ) );
    $prefix         = memora_clean_vietqr_prefix( sanitize_text_field( $prefix ) );

    $bank_id   = '';
    $bank_name = '';

    if ( ! empty( $raw_bank_val ) ) {
        if ( strpos( $raw_bank_val, ':' ) !== false ) {
            $parts     = explode( ':', $raw_bank_val, 2 );
            $bank_id   = trim( $parts[0] );
            $bank_name = trim( $parts[1] );
        } else {
            $matched = memora_find_vietqr_bank_by_id( $raw_bank_val );
            if ( $matched ) {
                $bank_id   = (string) $matched['bin'];
                $bank_name = (string) $matched['name'];
            } else {
                $bank_id   = $raw_bank_val;
                $bank_name = $raw_bank_val;
            }
        }
    }

    update_term_meta( $term_id, '_memora_vietqr_bank_id',      $bank_id );
    update_term_meta( $term_id, '_memora_vietqr_bank_name',    $bank_name );
    update_term_meta( $term_id, '_memora_vietqr_account_name', $account_name );
    update_term_meta( $term_id, '_memora_vietqr_account_no',   $account_number );
    update_term_meta( $term_id, '_memora_vietqr_prefix',       $prefix );
}


//====================================
// 3. CẤU HÌNH NGAY TRONG TRANG SETTINGS CỦA PLUGIN VIETQR
//    (wp-admin/admin.php?page=wc-settings&tab=checkout&section=vietqr)
//====================================
add_filter( 'woocommerce_settings_api_form_fields_vietqr', 'memora_add_branch_fields_to_vietqr_settings', 20, 1 );
function memora_add_branch_fields_to_vietqr_settings( $form_fields ) {
    $form_fields['memora_dia_chi_accounts'] = array(
        'type' => 'memora_dia_chi_accounts',
    );
    return $form_fields;
}

add_filter( 'woocommerce_generate_memora_dia_chi_accounts_html', 'memora_render_branch_accounts_in_vietqr_settings', 10, 4 );
function memora_render_branch_accounts_in_vietqr_settings( $html, $key, $data, $gateway ) {
    $terms = get_terms( array(
        'taxonomy'   => 'dia-chi',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    $banks          = memora_get_vietqr_plugin_banks();
    $default_prefix = method_exists( $gateway, 'get_option' ) ? $gateway->get_option( 'prefix', 'MEMORA' ) : 'MEMORA';

    ob_start();
    ?>
    <tr valign="top">
        <th scope="row" class="titledesc" style="padding-top:24px;">
            <?php esc_html_e( 'Tài khoản theo Chi nhánh (Địa chỉ):', 'memora' ); ?>
            <p class="description" style="font-weight:400;margin-top:6px;">
                Cấu hình ngân hàng VietQR riêng cho từng term trong <code>taxonomy=dia-chi</code>.<br>
                Nếu để trống Số tài khoản ở chi nhánh nào, chi nhánh đó sẽ tự động dùng tài khoản mặc định phía trên.
            </p>
        </th>
        <td class="forminp" style="padding-top:20px;">
            <div style="display:flex;flex-direction:column;gap:16px;max-width:850px;">
                <?php foreach ( $terms as $term ) :
                    $tid            = $term->term_id;
                    $saved_bank_id  = (string) get_term_meta( $tid, '_memora_vietqr_bank_id', true );
                    $saved_bank_nm  = (string) get_term_meta( $tid, '_memora_vietqr_bank_name', true );
                    $saved_acc_name = (string) get_term_meta( $tid, '_memora_vietqr_account_name', true );
                    $saved_acc_no   = (string) get_term_meta( $tid, '_memora_vietqr_account_no', true );
                    $saved_prefix   = (string) get_term_meta( $tid, '_memora_vietqr_prefix', true );

                    // Tương thích nếu trước đó lưu mã code ngắn (MB, VCB, BIDV...)
                    if ( ! empty( $saved_bank_id ) && ! is_numeric( $saved_bank_id ) ) {
                        $m = memora_find_vietqr_bank_by_id( $saved_bank_id );
                        if ( $m ) {
                            $saved_bank_id = (string) $m['bin'];
                            $saved_bank_nm = (string) $m['name'];
                        }
                    }
                    $is_configured = ( ! empty( $saved_bank_id ) && ! empty( $saved_acc_no ) );
                ?>
                <div style="background:#ffffff;border:1px solid <?php echo $is_configured ? '#86efac' : '#cbd5e1'; ?>;border-left:4px solid <?php echo $is_configured ? '#16a34a' : '#94a3b8'; ?>;border-radius:8px;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">
                        <strong style="font-size:14px;color:#0f172a;">
                            📍 Chi nhánh: <?php echo esc_html( $term->name ); ?>
                            <span style="color:#64748b;font-weight:400;font-size:12px;">(ID: <?php echo intval( $tid ); ?>)</span>
                        </strong>
                        <?php if ( $is_configured ) : ?>
                            <span style="background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;">✓ Đang dùng TK riêng</span>
                        <?php else : ?>
                            <span style="background:#f1f5f9;color:#64748b;font-size:11px;font-weight:600;padding:2px 8px;border-radius:12px;">Dùng TK mặc định</span>
                        <?php endif; ?>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="grid-column:1 / -1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px;">Bank name (Ngân hàng):</label>
                            <select class="memora-branch-vietqr-select" name="memora_branch_vietqr_bank[<?php echo intval( $tid ); ?>]" style="width:100%;max-width:100%;">
                                <option value="">— Chọn ngân hàng cho chi nhánh <?php echo esc_attr( $term->name ); ?> —</option>
                                <?php foreach ( $banks as $bank ) :
                                    $bin         = (string) ( $bank['bin'] ?? '' );
                                    $b_name      = (string) ( $bank['name'] ?? '' );
                                    $b_short     = (string) ( $bank['short_name'] ?? $bank['shortName'] ?? $bank['code'] ?? '' );
                                    $b_logo      = (string) ( $bank['logo'] ?? '' );
                                    $opt_val     = $bin . ':' . $b_name;
                                    $opt_text    = $b_short . ' - ' . $b_name;
                                    $is_selected = ( $saved_bank_id !== '' && $saved_bank_id === $bin );
                                ?>
                                    <option value="<?php echo esc_attr( $opt_val ); ?>" title="<?php echo esc_url( $b_logo ); ?>" <?php selected( $is_selected, true ); ?>>
                                        <?php echo esc_html( $opt_text ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px;">Account name (Chủ tài khoản):</label>
                            <input type="text" name="memora_branch_vietqr_acc_name[<?php echo intval( $tid ); ?>]" value="<?php echo esc_attr( $saved_acc_name ); ?>" placeholder="VD: NGUYEN VAN A" style="width:100%;text-transform:uppercase;" />
                        </div>

                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px;">Account number (Số tài khoản):</label>
                            <input type="text" name="memora_branch_vietqr_acc_no[<?php echo intval( $tid ); ?>]" value="<?php echo esc_attr( $saved_acc_no ); ?>" placeholder="Nhập số tài khoản..." style="width:100%;" />
                        </div>

                        <div style="grid-column:1 / -1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px;">Prefix (Tiền tố nội dung chuyển khoản):</label>
                            <input type="text" name="memora_branch_vietqr_prefix[<?php echo intval( $tid ); ?>]" value="<?php echo esc_attr( $saved_prefix ); ?>" placeholder="Mặc định: <?php echo esc_attr( $default_prefix ); ?>" style="width:220px;" />
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
            jQuery(function($) {
                var s2Opts = { width: '100%' };
                if (typeof formatOptions === 'function') {
                    s2Opts.templateResult = formatOptions;
                }
                if ($.fn.select2) {
                    $('.memora-branch-vietqr-select').select2(s2Opts);
                } else if ($.fn.selectWoo) {
                    $('.memora-branch-vietqr-select').selectWoo(s2Opts);
                }
            });
            </script>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Lưu cấu hình ngân hàng của các chi nhánh khi bấm "Save changes" trong WooCommerce > Settings > Payments > VietQR
 */
add_action( 'woocommerce_update_options_payment_gateways_vietqr', 'memora_save_branch_accounts_from_vietqr_settings', 20 );
function memora_save_branch_accounts_from_vietqr_settings() {
    if ( ! isset( $_POST['memora_branch_vietqr_acc_no'] ) || ! is_array( $_POST['memora_branch_vietqr_acc_no'] ) ) {
        return;
    }

    $banks     = isset( $_POST['memora_branch_vietqr_bank'] )     ? wp_unslash( $_POST['memora_branch_vietqr_bank'] )     : array();
    $acc_names = isset( $_POST['memora_branch_vietqr_acc_name'] ) ? wp_unslash( $_POST['memora_branch_vietqr_acc_name'] ) : array();
    $acc_nos   = isset( $_POST['memora_branch_vietqr_acc_no'] )   ? wp_unslash( $_POST['memora_branch_vietqr_acc_no'] )   : array();
    $prefixes  = isset( $_POST['memora_branch_vietqr_prefix'] )   ? wp_unslash( $_POST['memora_branch_vietqr_prefix'] )   : array();

    foreach ( $acc_nos as $tid => $acc_no ) {
        $tid      = intval( $tid );
        $bank_val = isset( $banks[ $tid ] ) ? $banks[ $tid ] : '';
        $acc_name = isset( $acc_names[ $tid ] ) ? $acc_names[ $tid ] : '';
        $prefix   = isset( $prefixes[ $tid ] ) ? $prefixes[ $tid ] : '';

        memora_save_branch_vietqr_account( $tid, $bank_val, $acc_name, $acc_no, $prefix );
    }
}

add_filter( 'woocommerce_settings_api_sanitized_fields_vietqr', function( $sanitized_fields ) {
    unset( $sanitized_fields['memora_dia_chi_accounts'] );
    return $sanitized_fields;
} );


//====================================
// 4. CẤU HÌNH TRONG TRANG QUẢN TRỊ TAXONOMY 'dia-chi'
//    (wp-admin/edit-tags.php?taxonomy=dia-chi&post_type=phong-chup-anh)
//====================================
add_action( 'dia-chi_add_form_fields', 'memora_dia_chi_add_vietqr_fields', 20 );
function memora_dia_chi_add_vietqr_fields() {
    $banks = memora_get_vietqr_plugin_banks();
    ?>
    <div class="form-field term-vietqr-wrap" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px 16px;margin:16px 0;">
        <h3 style="margin:0 0 10px;font-size:14px;color:#0f172a;">💳 Cấu hình VietQR cho Chi nhánh này</h3>

        <label for="memora_vietqr_bank" style="font-weight:600;margin-top:8px;display:block;">Bank name (Ngân hàng)</label>
        <select name="memora_vietqr_bank" id="memora_vietqr_bank" style="width:100%;max-width:100%;">
            <option value="">— Dùng tài khoản VietQR mặc định —</option>
            <?php foreach ( $banks as $bank ) :
                $bin      = (string) ( $bank['bin'] ?? '' );
                $b_name   = (string) ( $bank['name'] ?? '' );
                $b_short  = (string) ( $bank['short_name'] ?? $bank['shortName'] ?? $bank['code'] ?? '' );
                $opt_val  = $bin . ':' . $b_name;
                $opt_text = $b_short . ' - ' . $b_name;
            ?>
                <option value="<?php echo esc_attr( $opt_val ); ?>"><?php echo esc_html( $opt_text ); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="memora_vietqr_account_name" style="font-weight:600;margin-top:10px;display:block;">Account name (Chủ tài khoản)</label>
        <input type="text" name="memora_vietqr_account_name" id="memora_vietqr_account_name" value="" placeholder="VD: NGUYEN VAN A" style="text-transform:uppercase;" />

        <label for="memora_vietqr_account_no" style="font-weight:600;margin-top:10px;display:block;">Account number (Số tài khoản)</label>
        <input type="text" name="memora_vietqr_account_no" id="memora_vietqr_account_no" value="" placeholder="VD: 0123456789" />

        <label for="memora_vietqr_prefix" style="font-weight:600;margin-top:10px;display:block;">Prefix (Tiền tố nội dung CK)</label>
        <input type="text" name="memora_vietqr_prefix" id="memora_vietqr_prefix" value="" placeholder="VD: MEMORA" />
    </div>
    <?php
}

add_action( 'dia-chi_edit_form_fields', 'memora_dia_chi_edit_vietqr_fields', 20, 2 );
function memora_dia_chi_edit_vietqr_fields( $term ) {
    $tid            = $term->term_id;
    $banks          = memora_get_vietqr_plugin_banks();
    $saved_bank_id  = (string) get_term_meta( $tid, '_memora_vietqr_bank_id', true );
    $saved_acc_name = (string) get_term_meta( $tid, '_memora_vietqr_account_name', true );
    $saved_acc_no   = (string) get_term_meta( $tid, '_memora_vietqr_account_no', true );
    $saved_prefix   = (string) get_term_meta( $tid, '_memora_vietqr_prefix', true );

    if ( ! empty( $saved_bank_id ) && ! is_numeric( $saved_bank_id ) ) {
        $m = memora_find_vietqr_bank_by_id( $saved_bank_id );
        if ( $m ) {
            $saved_bank_id = (string) $m['bin'];
        }
    }
    ?>
    <tr class="form-field term-vietqr-edit-wrap">
        <th scope="row">
            <label>💳 Cấu hình VietQR (Chi nhánh)</label>
            <p class="description" style="font-weight:400;margin-top:6px;">
                Đồng bộ trực tiếp với plugin <strong>VietQR</strong> trong WooCommerce cho chi nhánh <strong><?php echo esc_html( $term->name ); ?></strong>.
            </p>
        </th>
        <td>
            <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:16px 18px;max-width:600px;">
                <div style="margin-bottom:12px;">
                    <label for="memora_vietqr_bank" style="display:block;font-weight:600;margin-bottom:4px;">Bank name:</label>
                    <select name="memora_vietqr_bank" id="memora_vietqr_bank" style="width:100%;max-width:100%;">
                        <option value="">— Dùng tài khoản VietQR mặc định —</option>
                        <?php foreach ( $banks as $bank ) :
                            $bin         = (string) ( $bank['bin'] ?? '' );
                            $b_name      = (string) ( $bank['name'] ?? '' );
                            $b_short     = (string) ( $bank['short_name'] ?? $bank['shortName'] ?? $bank['code'] ?? '' );
                            $opt_val     = $bin . ':' . $b_name;
                            $opt_text    = $b_short . ' - ' . $b_name;
                            $is_selected = ( $saved_bank_id !== '' && $saved_bank_id === $bin );
                        ?>
                            <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $is_selected, true ); ?>>
                                <?php echo esc_html( $opt_text ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:12px;">
                    <label for="memora_vietqr_account_name" style="display:block;font-weight:600;margin-bottom:4px;">Account name:</label>
                    <input type="text" name="memora_vietqr_account_name" id="memora_vietqr_account_name" value="<?php echo esc_attr( $saved_acc_name ); ?>" placeholder="VD: NGUYEN VAN A" style="width:100%;text-transform:uppercase;" />
                </div>

                <div style="margin-bottom:12px;">
                    <label for="memora_vietqr_account_no" style="display:block;font-weight:600;margin-bottom:4px;">Account number:</label>
                    <input type="text" name="memora_vietqr_account_no" id="memora_vietqr_account_no" value="<?php echo esc_attr( $saved_acc_no ); ?>" placeholder="VD: 0123456789" style="width:100%;" />
                </div>

                <div>
                    <label for="memora_vietqr_prefix" style="display:block;font-weight:600;margin-bottom:4px;">Prefix:</label>
                    <input type="text" name="memora_vietqr_prefix" id="memora_vietqr_prefix" value="<?php echo esc_attr( $saved_prefix ); ?>" placeholder="VD: MEMORA" style="width:220px;" />
                </div>
            </div>
        </td>
    </tr>
    <?php
}

add_action( 'created_dia-chi', 'memora_save_dia_chi_vietqr_meta', 15, 2 );
add_action( 'edited_dia-chi', 'memora_save_dia_chi_vietqr_meta', 15, 2 );
function memora_save_dia_chi_vietqr_meta( $term_id ) {
    if ( ! isset( $_POST['memora_vietqr_bank'] ) && ! isset( $_POST['memora_vietqr_account_no'] ) ) {
        return;
    }

    $bank_val = isset( $_POST['memora_vietqr_bank'] )         ? wp_unslash( $_POST['memora_vietqr_bank'] )         : '';
    $acc_name = isset( $_POST['memora_vietqr_account_name'] ) ? wp_unslash( $_POST['memora_vietqr_account_name'] ) : '';
    $acc_no   = isset( $_POST['memora_vietqr_account_no'] )   ? wp_unslash( $_POST['memora_vietqr_account_no'] )   : '';
    $prefix   = isset( $_POST['memora_vietqr_prefix'] )       ? wp_unslash( $_POST['memora_vietqr_prefix'] )       : '';

    memora_save_branch_vietqr_account( $term_id, $bank_val, $acc_name, $acc_no, $prefix );
}

/**
 * Hiển thị cột "Ngân hàng (VietQR)" trên bảng danh sách Địa chỉ (edit-tags.php?taxonomy=dia-chi)
 */
add_filter( 'manage_edit-dia-chi_columns', 'memora_dia_chi_add_vietqr_column', 20 );
function memora_dia_chi_add_vietqr_column( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( $key === 'name' ) {
            $new_columns['memora_vietqr'] = 'Ngân hàng (VietQR)';
        }
    }
    if ( ! isset( $new_columns['memora_vietqr'] ) ) {
        $new_columns['memora_vietqr'] = 'Ngân hàng (VietQR)';
    }
    return $new_columns;
}

add_filter( 'manage_dia-chi_custom_column', 'memora_dia_chi_render_vietqr_column', 20, 3 );
function memora_dia_chi_render_vietqr_column( $content, $column_name, $term_id ) {
    if ( $column_name !== 'memora_vietqr' ) {
        return $content;
    }

    $cfg = memora_get_branch_vietqr_account( $term_id );
    if ( ! $cfg ) {
        return '<span style="color:#94a3b8;font-size:12px;font-style:italic;">Dùng TK VietQR mặc định</span>';
    }

    $html  = '<div style="line-height:1.4;font-size:12px;">';
    $html .= '<span style="display:inline-block;background:#dcfce7;color:#166534;font-weight:700;padding:1px 7px;border-radius:4px;margin-right:4px;">' . esc_html( $cfg['bank_short'] ) . '</span>';
    $html .= '<code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:1px 5px;border-radius:3px;">' . esc_html( $cfg['account_number'] ) . '</code>';
    if ( ! empty( $cfg['account_name'] ) ) {
        $html .= '<div style="color:#475569;margin-top:2px;font-size:11px;">' . esc_html( $cfg['account_name'] ) . '</div>';
    }
    $html .= '</div>';

    return $html;
}


//====================================
// 5. TRA CỨU TÀI KHOẢN VIETQR THEO ĐƠN HÀNG WOOCOMMERCE
//====================================
/**
 * Tìm cấu hình VietQR theo WC_Order (hoặc Order ID / Session hiện tại)
 */
function memora_get_vietqr_config( $dia_chi_id = 0, $order = null ) {
    $dia_chi_id = intval( $dia_chi_id );

    if ( is_numeric( $order ) && intval( $order ) > 0 && function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( intval( $order ) );
    }

    // 1. Lấy dia_chi_id từ WC_Order
    if ( $order instanceof WC_Order ) {
        if ( $dia_chi_id <= 0 ) {
            $dia_chi_id = intval( $order->get_meta( '_booking_dia_chi_id' ) );
        }
        if ( $dia_chi_id <= 0 ) {
            $room_id = intval( $order->get_meta( '_booking_phong_id' ) );
            if ( $room_id > 0 && function_exists( 'memora_get_location_id_from_room' ) ) {
                $dia_chi_id = memora_get_location_id_from_room( $room_id );
            }
        }
    }

    // 2. Lấy dia_chi_id từ WC Session (khi đang ở bước Checkout)
    if ( $dia_chi_id <= 0 && function_exists( 'WC' ) && WC()->session ) {
        $pending = WC()->session->get( 'memora_booking_pending_data' );
        if ( is_array( $pending ) ) {
            if ( ! empty( $pending['dia_chi_id'] ) ) {
                $dia_chi_id = intval( $pending['dia_chi_id'] );
            } elseif ( ! empty( $pending['room_id'] ) && function_exists( 'memora_get_location_id_from_room' ) ) {
                $dia_chi_id = memora_get_location_id_from_room( intval( $pending['room_id'] ) );
            }
        }
    }

    // 3. Nếu xác định được term dia-chi và term đó có cấu hình ngân hàng riêng
    if ( $dia_chi_id > 0 ) {
        $branch_acc = memora_get_branch_vietqr_account( $dia_chi_id );
        if ( $branch_acc ) {
            return array(
                'dia_chi_id'     => $branch_acc['dia_chi_id'],
                'dia_chi_name'   => $branch_acc['dia_chi_name'],
                'bank_id'        => $branch_acc['bank_id'],
                'bank_bin'       => $branch_acc['bank_id'],
                'bank_name'      => $branch_acc['bank_name'],
                'account_no'     => $branch_acc['account_number'],
                'account_number' => $branch_acc['account_number'],
                'account_name'   => $branch_acc['account_name'],
                'prefix'         => $branch_acc['prefix'],
                'template'       => 'compact',
                'source'         => 'term_dia_chi',
            );
        }
    }

    // 4. Fallback về tài khoản mặc định trong plugin VietQR (woocommerce_vietqr_accounts)
    remove_filter( 'option_woocommerce_vietqr_accounts', 'memora_filter_option_woocommerce_vietqr_accounts', 20 );
    $default_accounts = get_option( 'woocommerce_vietqr_accounts', array() );
    add_filter( 'option_woocommerce_vietqr_accounts', 'memora_filter_option_woocommerce_vietqr_accounts', 20, 1 );

    $vq_settings    = get_option( 'woocommerce_vietqr_settings', array() );
    $default_prefix = ! empty( $vq_settings['prefix'] ) ? $vq_settings['prefix'] : 'MEMORA';

    if ( is_array( $default_accounts ) && ! empty( $default_accounts[0]['account_number'] ) ) {
        $acc = $default_accounts[0];
        return array(
            'dia_chi_id'     => $dia_chi_id,
            'dia_chi_name'   => '',
            'bank_id'        => (string) ( $acc['bank_id'] ?? '' ),
            'bank_bin'       => (string) ( $acc['bank_id'] ?? '' ),
            'bank_name'      => (string) ( $acc['bank_name'] ?? '' ),
            'account_no'     => (string) ( $acc['account_number'] ?? '' ),
            'account_number' => (string) ( $acc['account_number'] ?? '' ),
            'account_name'   => (string) ( $acc['account_name'] ?? '' ),
            'prefix'         => $default_prefix,
            'template'       => 'compact',
            'source'         => 'vietqr_plugin_default',
        );
    }

    return array();
}

/**
 * Lưu snapshot tài khoản ngân hàng VietQR đã áp dụng vào đơn hàng
 */
function memora_save_vietqr_snapshot_to_order( $order, $dia_chi_id = 0 ) {
    if ( ! ( $order instanceof WC_Order ) ) {
        return;
    }
    $cfg = memora_get_vietqr_config( $dia_chi_id, $order );
    if ( ! empty( $cfg['bank_id'] ) && ! empty( $cfg['account_number'] ) ) {
        $order->update_meta_data( '_booking_vietqr_bank_id',      $cfg['bank_id'] );
        $order->update_meta_data( '_booking_vietqr_bank_name',    $cfg['bank_name'] );
        $order->update_meta_data( '_booking_vietqr_account_no',   $cfg['account_number'] );
        $order->update_meta_data( '_booking_vietqr_account_name', $cfg['account_name'] );
        $order->update_meta_data( '_booking_vietqr_prefix',       $cfg['prefix'] ?? '' );
    }
}


//====================================
// 6. HOOK TRỰC TIẾP VÀO PLUGIN VIETQR (WC_Gateway_VietQR)
//====================================
/**
 * Đồng bộ trực tiếp thuộc tính $gateway->account_details và $gateway->prefix
 * của đối tượng WC_Gateway_VietQR theo đúng chi nhánh của $order_id
 */
function memora_sync_vietqr_gateway_for_order( $order_id ) {
    $order_id = absint( $order_id );
    if ( $order_id <= 0 ) {
        return;
    }

    $GLOBALS['memora_current_thankyou_order_id'] = $order_id;

    $cfg = memora_get_vietqr_config( 0, $order_id );
    if ( empty( $cfg['bank_id'] ) || empty( $cfg['account_number'] ) ) {
        return;
    }

    if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        if ( isset( $gateways['vietqr'] ) && is_object( $gateways['vietqr'] ) ) {
            $gateways['vietqr']->account_details = array(
                array(
                    'account_name'   => $cfg['account_name'],
                    'account_number' => $cfg['account_number'],
                    'bank_name'      => $cfg['bank_name'],
                    'bank_id'        => $cfg['bank_id'],
                ),
            );
            if ( ! empty( $cfg['prefix'] ) ) {
                $gateways['vietqr']->prefix = $cfg['prefix'];
            }
        }
    }
}

// Chạy ở priority 1 (trước khi WC_Gateway_VietQR::thankyou_page chạy ở priority 10)
add_action( 'woocommerce_thankyou_vietqr', 'memora_sync_vietqr_gateway_for_order', 1, 1 );
add_action( 'woocommerce_view_order', 'memora_sync_vietqr_gateway_for_order', 1, 1 );
add_action( 'woocommerce_email_before_order_table', function( $order ) {
    if ( $order instanceof WC_Order ) {
        memora_sync_vietqr_gateway_for_order( $order->get_id() );
    }
}, 1, 1 );

/**
 * Filter chính thức của plugin VietQR: 'woocommerce_vietqr_account_fields'
 * Đảm bảo mã QR và bảng thông tin chuyển khoản của plugin VietQR luôn nhận đúng ngân hàng của chi nhánh
 */
add_filter( 'woocommerce_vietqr_account_fields', 'memora_filter_vietqr_plugin_account_fields', 20, 2 );
function memora_filter_vietqr_plugin_account_fields( $account_fields, $order_id ) {
    $cfg = memora_get_vietqr_config( 0, $order_id );
    if ( empty( $cfg['bank_id'] ) || empty( $cfg['account_number'] ) ) {
        return $account_fields;
    }

    if ( isset( $account_fields['bank_name'] ) ) {
        $account_fields['bank_name']['value'] = $cfg['bank_name'];
    }
    if ( isset( $account_fields['bank_id'] ) ) {
        $account_fields['bank_id']['value'] = $cfg['bank_id'];
    }
    if ( isset( $account_fields['account_number'] ) ) {
        $account_fields['account_number']['value'] = $cfg['account_number'];
    }
    if ( isset( $account_fields['account_name'] ) ) {
        $account_fields['account_name']['value'] = $cfg['account_name'];
    }
    if ( isset( $account_fields['memo'] ) && ! empty( $cfg['prefix'] ) ) {
        $account_fields['memo']['value'] = $cfg['prefix'] . $order_id;
    }

    return $account_fields;
}

/**
 * Filter option 'woocommerce_vietqr_accounts' khi khởi tạo gateway ngoài Frontend
 */
add_filter( 'option_woocommerce_vietqr_accounts', 'memora_filter_option_woocommerce_vietqr_accounts', 20, 1 );
function memora_filter_option_woocommerce_vietqr_accounts( $accounts ) {
    // Không can thiệp trong màn hình cài đặt WooCommerce Admin
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $accounts;
    }

    static $in_filter = false;
    if ( $in_filter ) {
        return $accounts;
    }
    $in_filter = true;

    $order_id = ! empty( $GLOBALS['memora_current_thankyou_order_id'] )
        ? absint( $GLOBALS['memora_current_thankyou_order_id'] )
        : absint( get_query_var( 'order-received' ) );

    if ( ! $order_id && ! empty( $_GET['order_id'] ) ) {
        $order_id = absint( $_GET['order_id'] );
    }

    $cfg = memora_get_vietqr_config( 0, $order_id > 0 ? $order_id : null );
    $in_filter = false;

    if ( ! empty( $cfg['bank_id'] ) && ! empty( $cfg['account_number'] ) ) {
        return array(
            array(
                'account_name'   => $cfg['account_name'],
                'account_number' => $cfg['account_number'],
                'bank_name'      => $cfg['bank_name'],
                'bank_id'        => $cfg['bank_id'],
            ),
        );
    }

    return $accounts;
}
