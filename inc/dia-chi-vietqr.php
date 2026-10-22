<?php
/**
 * Cấu hình Ngân hàng & Thanh toán VietQR theo từng Chi nhánh (taxonomy: dia-chi)
 *
 * Tính năng:
 * 1. Danh sách ngân hàng chuẩn Napas / VietQR (mã BIN, mã viết tắt, tên hiển thị).
 * 2. Trường cấu hình Ngân hàng / VietQR trong WP Admin (Thêm mới & Chỉnh sửa term dia-chi)
 *    kèm khung Xem trước mã QR (Live Preview) trực tiếp.
 * 3. Cột "Ngân hàng (VietQR)" trên bảng danh sách Địa chỉ (edit-tags.php?taxonomy=dia-chi).
 * 4. Hàm helper trung tâm memora_get_vietqr_config() tự động tra cứu ngân hàng theo:
 *    Term dia-chi -> Snapshot trong WC Order -> ACF Options -> WooCommerce BACS mặc định.
 * 5. Đồng bộ tự động với WooCommerce BACS (filter woocommerce_bacs_accounts) theo từng đơn hàng.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//====================================
// 1. DANH SÁCH NGÂN HÀNG CHUẨN VIETQR (NAPAS)
//====================================
function memora_get_vietqr_banks_list() {
    return array(
        'MB'    => array( 'code' => 'MB',    'bin' => '970422', 'short' => 'MBBank',       'name' => 'MBBank - Ngân hàng TMCP Quân Đội' ),
        'VCB'   => array( 'code' => 'VCB',   'bin' => '970436', 'short' => 'Vietcombank',  'name' => 'Vietcombank - Ngân hàng TMCP Ngoại thương VN' ),
        'TCB'   => array( 'code' => 'TCB',   'bin' => '970407', 'short' => 'Techcombank',  'name' => 'Techcombank - Ngân hàng TMCP Kỹ thương VN' ),
        'ACB'   => array( 'code' => 'ACB',   'bin' => '970416', 'short' => 'ACB',          'name' => 'ACB - Ngân hàng TMCP Á Châu' ),
        'BIDV'  => array( 'code' => 'BIDV',  'bin' => '970418', 'short' => 'BIDV',         'name' => 'BIDV - Ngân hàng TMCP Đầu tư & Phát triển VN' ),
        'ICB'   => array( 'code' => 'ICB',   'bin' => '970415', 'short' => 'VietinBank',   'name' => 'VietinBank - Ngân hàng TMCP Công thương VN' ),
        'VPB'   => array( 'code' => 'VPB',   'bin' => '970432', 'short' => 'VPBank',       'name' => 'VPBank - Ngân hàng TMCP Việt Nam Thịnh Vượng' ),
        'TPB'   => array( 'code' => 'TPB',   'bin' => '970423', 'short' => 'TPBank',       'name' => 'TPBank - Ngân hàng TMCP Tiên Phong' ),
        'VBA'   => array( 'code' => 'VBA',   'bin' => '970405', 'short' => 'Agribank',     'name' => 'Agribank - Ngân hàng NN & PTNT Việt Nam' ),
        'STB'   => array( 'code' => 'STB',   'bin' => '970403', 'short' => 'Sacombank',    'name' => 'Sacombank - Ngân hàng TMCP Sài Gòn Thương Tín' ),
        'VIB'   => array( 'code' => 'VIB',   'bin' => '970441', 'short' => 'VIB',          'name' => 'VIB - Ngân hàng TMCP Quốc tế Việt Nam' ),
        'SHB'   => array( 'code' => 'SHB',   'bin' => '970443', 'short' => 'SHB',          'name' => 'SHB - Ngân hàng TMCP Sài Gòn - Hà Nội' ),
        'MSB'   => array( 'code' => 'MSB',   'bin' => '970426', 'short' => 'MSB',          'name' => 'MSB - Ngân hàng TMCP Hàng Hải Việt Nam' ),
        'OCB'   => array( 'code' => 'OCB',   'bin' => '970448', 'short' => 'OCB',          'name' => 'OCB - Ngân hàng TMCP Phương Đông' ),
        'HDB'   => array( 'code' => 'HDB',   'bin' => '970437', 'short' => 'HDBank',       'name' => 'HDBank - Ngân hàng TMCP Phát triển TP.HCM' ),
        'LPB'   => array( 'code' => 'LPB',   'bin' => '970449', 'short' => 'LPBank',       'name' => 'LPBank - Ngân hàng TMCP Lộc Phát Việt Nam' ),
        'SEAB'  => array( 'code' => 'SEAB',  'bin' => '970440', 'short' => 'SeABank',      'name' => 'SeABank - Ngân hàng TMCP Đông Nam Á' ),
        'EIB'   => array( 'code' => 'EIB',   'bin' => '970431', 'short' => 'Eximbank',     'name' => 'Eximbank - Ngân hàng TMCP Xuất Nhập khẩu VN' ),
        'NAB'   => array( 'code' => 'NAB',   'bin' => '970428', 'short' => 'NamABank',     'name' => 'Nam A Bank - Ngân hàng TMCP Nam Á' ),
        'BAB'   => array( 'code' => 'BAB',   'bin' => '970409', 'short' => 'BacABank',     'name' => 'Bac A Bank - Ngân hàng TMCP Bắc Á' ),
        'PVCB'  => array( 'code' => 'PVCB',  'bin' => '970412', 'short' => 'PVcomBank',    'name' => 'PVcomBank - Ngân hàng TMCP Đại Chúng VN' ),
        'BVB'   => array( 'code' => 'BVB',   'bin' => '970438', 'short' => 'BaoVietBank',  'name' => 'BaoVietBank - Ngân hàng TMCP Bảo Việt' ),
        'ABB'   => array( 'code' => 'ABB',   'bin' => '970425', 'short' => 'ABBank',       'name' => 'ABBank - Ngân hàng TMCP An Bình' ),
        'VAB'   => array( 'code' => 'VAB',   'bin' => '970427', 'short' => 'VietABank',    'name' => 'VietABank - Ngân hàng TMCP Việt Á' ),
        'KLB'   => array( 'code' => 'KLB',   'bin' => '970452', 'short' => 'KienlongBank', 'name' => 'KienlongBank - Ngân hàng TMCP Kiên Long' ),
        'CAKE'  => array( 'code' => 'CAKE',  'bin' => '546034', 'short' => 'CAKE',         'name' => 'CAKE by VPBank - Ngân hàng số CAKE' ),
        'TIMO'  => array( 'code' => 'TIMO',  'bin' => '963388', 'short' => 'Timo',         'name' => 'Timo by Ban Viet Bank' ),
    );
}

/**
 * Tra cứu thông tin ngân hàng chuẩn từ mã code, mã BIN hoặc tên ngân hàng bất kỳ
 */
function memora_match_vietqr_bank( $raw_bank ) {
    $raw_bank = trim( (string) $raw_bank );
    if ( $raw_bank === '' ) {
        return null;
    }

    $banks = memora_get_vietqr_banks_list();
    $upper = strtoupper( $raw_bank );

    // 1. Khớp trực tiếp theo key (VD: MB, VCB, TCB...)
    if ( isset( $banks[ $upper ] ) ) {
        return $banks[ $upper ];
    }

    // 2. Khớp theo BIN hoặc tên viết tắt (short)
    foreach ( $banks as $b ) {
        if ( $b['bin'] === $raw_bank || strtoupper( $b['short'] ) === $upper ) {
            return $b;
        }
    }

    // 3. Khớp gần đúng trong chuỗi tên
    foreach ( $banks as $b ) {
        if ( stripos( $raw_bank, $b['short'] ) !== false || stripos( $b['name'], $raw_bank ) !== false ) {
            return $b;
        }
    }

    return array(
        'code'  => $raw_bank,
        'bin'   => $raw_bank,
        'short' => $raw_bank,
        'name'  => $raw_bank,
    );
}


//====================================
// 2. TRƯỜNG CẤU HÌNH VIETQR TRONG WP ADMIN (TAXONOMY: dia-chi)
//====================================
add_action( 'dia-chi_add_form_fields', 'memora_dia_chi_add_vietqr_fields', 20 );
function memora_dia_chi_add_vietqr_fields() {
    $banks = memora_get_vietqr_banks_list();
    ?>
    <div class="form-field term-vietqr-wrap" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px 16px;margin:16px 0;">
        <h3 style="margin:0 0 10px;font-size:14px;color:#0f172a;">💳 Cấu hình Ngân hàng / VietQR (Chi nhánh này)</h3>

        <label for="memora_vietqr_bank_id" style="font-weight:600;margin-top:8px;display:block;">Ngân hàng thụ hưởng</label>
        <select name="memora_vietqr_bank_id" id="memora_vietqr_bank_id" style="width:100%;max-width:100%;">
            <option value="">— Dùng tài khoản mặc định —</option>
            <?php foreach ( $banks as $code => $info ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>">
                    <?php echo esc_html( $info['name'] . ' (' . $code . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="memora_vietqr_account_no" style="font-weight:600;margin-top:10px;display:block;">Số tài khoản</label>
        <input type="text" name="memora_vietqr_account_no" id="memora_vietqr_account_no" value="" placeholder="VD: 0123456789" />

        <label for="memora_vietqr_account_name" style="font-weight:600;margin-top:10px;display:block;">Tên chủ tài khoản</label>
        <input type="text" name="memora_vietqr_account_name" id="memora_vietqr_account_name" value="" placeholder="VD: NGUYEN VAN A" style="text-transform:uppercase;" />

        <label for="memora_vietqr_prefix" style="font-weight:600;margin-top:10px;display:block;">Tiền tố nội dung chuyển khoản</label>
        <input type="text" name="memora_vietqr_prefix" id="memora_vietqr_prefix" value="MEMORA" placeholder="MEMORA" />
        <p class="description">Nội dung CK thực tế sẽ là: <code>[Tiền tố] [Mã đặt lịch 4 số]</code> (VD: <code>MEMORA 6842</code>).</p>
    </div>
    <?php
}

add_action( 'dia-chi_edit_form_fields', 'memora_dia_chi_edit_vietqr_fields', 20, 2 );
function memora_dia_chi_edit_vietqr_fields( $term ) {
    $tid          = $term->term_id;
    $banks        = memora_get_vietqr_banks_list();
    $bank_id      = get_term_meta( $tid, '_memora_vietqr_bank_id', true ) ?: get_term_meta( $tid, 'vietqr_bank_id', true );
    $account_no   = get_term_meta( $tid, '_memora_vietqr_account_no', true ) ?: get_term_meta( $tid, 'vietqr_account_no', true );
    $account_name = get_term_meta( $tid, '_memora_vietqr_account_name', true ) ?: get_term_meta( $tid, 'vietqr_account_name', true );
    $template     = get_term_meta( $tid, '_memora_vietqr_template', true ) ?: 'compact2';
    $prefix       = get_term_meta( $tid, '_memora_vietqr_prefix', true ) ?: 'MEMORA';

    $preview_url  = '';
    if ( ! empty( $bank_id ) && ! empty( $account_no ) ) {
        $preview_url = memora_build_vietqr_url( array(
            'bank_id'      => $bank_id,
            'account_no'   => $account_no,
            'account_name' => $account_name,
            'template'     => $template,
        ), 200000, $prefix . ' 1234' );
    }
    ?>
    <tr class="form-field term-vietqr-edit-wrap">
        <th scope="row">
            <label>💳 Tài khoản Ngân hàng / VietQR</label>
            <p class="description" style="font-weight:400;margin-top:6px;">
                Khách đặt lịch thuộc chi nhánh <strong><?php echo esc_html( $term->name ); ?></strong> sẽ quét mã QR và chuyển khoản vào tài khoản này.
            </p>
        </th>
        <td>
            <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:18px 20px;max-width:780px;">
                <div style="flex:1 1 360px;min-width:280px;">
                    <div style="margin-bottom:14px;">
                        <label for="memora_vietqr_bank_id" style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">1. Ngân hàng thụ hưởng</label>
                        <select name="memora_vietqr_bank_id" id="memora_vietqr_bank_id" style="width:100%;max-width:100%;">
                            <option value="">— Chưa chọn (Dùng tài khoản mặc định) —</option>
                            <?php foreach ( $banks as $code => $info ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( strtoupper( (string) $bank_id ), $code ); ?>>
                                    <?php echo esc_html( $info['name'] . ' (' . $code . ' - BIN ' . $info['bin'] . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label for="memora_vietqr_account_no" style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">2. Số tài khoản nhận tiền</label>
                        <input type="text" name="memora_vietqr_account_no" id="memora_vietqr_account_no" value="<?php echo esc_attr( $account_no ); ?>" placeholder="Nhập số tài khoản ngân hàng..." style="width:100%;" />
                    </div>

                    <div style="margin-bottom:14px;">
                        <label for="memora_vietqr_account_name" style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">3. Tên chủ tài khoản (Không dấu)</label>
                        <input type="text" name="memora_vietqr_account_name" id="memora_vietqr_account_name" value="<?php echo esc_attr( $account_name ); ?>" placeholder="VD: NGUYEN VAN A" style="width:100%;text-transform:uppercase;" />
                    </div>

                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <div style="flex:1 1 160px;">
                            <label for="memora_vietqr_prefix" style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">4. Tiền tố nội dung CK</label>
                            <input type="text" name="memora_vietqr_prefix" id="memora_vietqr_prefix" value="<?php echo esc_attr( $prefix ); ?>" placeholder="MEMORA" style="width:100%;" />
                        </div>
                        <div style="flex:1 1 160px;">
                            <label for="memora_vietqr_template" style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">5. Mẫu khung QR</label>
                            <select name="memora_vietqr_template" id="memora_vietqr_template" style="width:100%;">
                                <option value="compact2" <?php selected( $template, 'compact2' ); ?>>Compact 2 (Đầy đủ thông tin)</option>
                                <option value="compact" <?php selected( $template, 'compact' ); ?>>Compact (Kèm logo VietQR)</option>
                                <option value="qr_only" <?php selected( $template, 'qr_only' ); ?>>Chỉ mã QR (QR Only)</option>
                                <option value="print" <?php selected( $template, 'print' ); ?>>Bản in đầy đủ (Print)</option>
                            </select>
                        </div>
                    </div>
                    <p class="description" style="margin-top:8px;">
                        Nội dung chuyển khoản tự động trên đơn hàng sẽ có dạng: <code><strong id="memora-qr-prefix-demo"><?php echo esc_html( $prefix ?: 'MEMORA' ); ?></strong> 1234</code>
                    </p>
                </div>

                <!-- KHUNG XEM TRƯỚC MÃ VIETQR TRỰC TIẾP -->
                <div style="flex:0 0 210px;text-align:center;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                    <div style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Xem trước VietQR</div>
                    <div id="memora-vietqr-preview-box" style="min-height:180px;display:flex;align-items:center;justify-content:center;">
                        <?php if ( $preview_url ) : ?>
                            <img id="memora-vietqr-preview-img" src="<?php echo esc_url( $preview_url ); ?>" alt="VietQR Preview" style="max-width:185px;height:auto;border-radius:6px;display:block;" />
                        <?php else : ?>
                            <div id="memora-vietqr-preview-empty" style="font-size:12px;color:#94a3b8;padding:20px 10px;line-height:1.5;">
                                Chọn <strong>Ngân hàng</strong> và nhập <strong>Số tài khoản</strong> để xem trước mã QR tại đây.
                            </div>
                            <img id="memora-vietqr-preview-img" src="" alt="VietQR Preview" style="max-width:185px;height:auto;border-radius:6px;display:none;" />
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:#64748b;margin-top:6px;">(Minh họa đơn 200.000đ)</div>
                </div>
            </div>

            <script>
            jQuery(function($) {
                function updateVietQrPreview() {
                    var bank = $.trim($('#memora_vietqr_bank_id').val());
                    var accNo = $.trim($('#memora_vietqr_account_no').val());
                    var accName = $.trim($('#memora_vietqr_account_name').val()).toUpperCase();
                    var tpl = $.trim($('#memora_vietqr_template').val()) || 'compact2';
                    var prefix = $.trim($('#memora_vietqr_prefix').val()) || 'MEMORA';

                    $('#memora-qr-prefix-demo').text(prefix);

                    if (!bank || !accNo) {
                        $('#memora-vietqr-preview-img').hide();
                        $('#memora-vietqr-preview-empty').show();
                        return;
                    }

                    var url = 'https://img.vietqr.io/image/' + encodeURIComponent(bank) + '-' + encodeURIComponent(accNo) + '-' + encodeURIComponent(tpl) + '.png'
                        + '?amount=200000'
                        + '&addInfo=' + encodeURIComponent(prefix + ' 1234')
                        + '&accountName=' + encodeURIComponent(accName);

                    $('#memora-vietqr-preview-empty').hide();
                    $('#memora-vietqr-preview-img').attr('src', url).show();
                }

                $('#memora_vietqr_bank_id, #memora_vietqr_template').on('change', updateVietQrPreview);
                $('#memora_vietqr_account_no, #memora_vietqr_account_name, #memora_vietqr_prefix').on('input', function() {
                    clearTimeout(window._memoraQrTimer);
                    window._memoraQrTimer = setTimeout(updateVietQrPreview, 350);
                });
            });
            </script>
        </td>
    </tr>
    <?php
}

/**
 * Lưu thông tin Ngân hàng / VietQR khi thêm mới hoặc cập nhật term dia-chi
 */
add_action( 'created_dia-chi', 'memora_save_dia_chi_vietqr_meta', 15, 2 );
add_action( 'edited_dia-chi', 'memora_save_dia_chi_vietqr_meta', 15, 2 );
function memora_save_dia_chi_vietqr_meta( $term_id ) {
    if ( ! isset( $_POST['memora_vietqr_bank_id'] ) && ! isset( $_POST['memora_vietqr_account_no'] ) ) {
        return;
    }

    $bank_id      = isset( $_POST['memora_vietqr_bank_id'] )      ? sanitize_text_field( wp_unslash( $_POST['memora_vietqr_bank_id'] ) )      : '';
    $account_no   = isset( $_POST['memora_vietqr_account_no'] )   ? sanitize_text_field( wp_unslash( $_POST['memora_vietqr_account_no'] ) )   : '';
    $account_name = isset( $_POST['memora_vietqr_account_name'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['memora_vietqr_account_name'] ) ) ) : '';
    $template     = isset( $_POST['memora_vietqr_template'] )     ? sanitize_text_field( wp_unslash( $_POST['memora_vietqr_template'] ) )     : 'compact2';
    $prefix       = isset( $_POST['memora_vietqr_prefix'] )       ? sanitize_text_field( wp_unslash( $_POST['memora_vietqr_prefix'] ) )       : 'MEMORA';

    $matched_bank = memora_match_vietqr_bank( $bank_id );
    $bank_name    = $matched_bank ? $matched_bank['short'] : $bank_id;

    update_term_meta( $term_id, '_memora_vietqr_bank_id',      $bank_id );
    update_term_meta( $term_id, '_memora_vietqr_bank_name',    $bank_name );
    update_term_meta( $term_id, '_memora_vietqr_account_no',   $account_no );
    update_term_meta( $term_id, '_memora_vietqr_account_name', $account_name );
    update_term_meta( $term_id, '_memora_vietqr_template',     $template ?: 'compact2' );
    update_term_meta( $term_id, '_memora_vietqr_prefix',       $prefix ?: 'MEMORA' );

    // Đồng bộ cả vào meta key không gạch dưới để tương thích nếu có đọc qua ACF get_field()
    update_term_meta( $term_id, 'vietqr_bank_id',      $bank_id );
    update_term_meta( $term_id, 'vietqr_bank_name',    $bank_name );
    update_term_meta( $term_id, 'vietqr_account_no',   $account_no );
    update_term_meta( $term_id, 'vietqr_account_name', $account_name );
    update_term_meta( $term_id, 'vietqr_template',     $template ?: 'compact2' );
}


//====================================
// 3. CỘT "NGÂN HÀNG (VIETQR)" TRONG BẢNG DANH SÁCH ĐỊA CHỈ
//====================================
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

    $bank_id      = get_term_meta( $term_id, '_memora_vietqr_bank_id', true ) ?: get_term_meta( $term_id, 'vietqr_bank_id', true );
    $account_no   = get_term_meta( $term_id, '_memora_vietqr_account_no', true ) ?: get_term_meta( $term_id, 'vietqr_account_no', true );
    $account_name = get_term_meta( $term_id, '_memora_vietqr_account_name', true ) ?: get_term_meta( $term_id, 'vietqr_account_name', true );

    if ( empty( $bank_id ) || empty( $account_no ) ) {
        return '<span style="color:#94a3b8;font-size:12px;font-style:italic;">Chưa cài đặt (Dùng mặc định)</span>';
    }

    $matched   = memora_match_vietqr_bank( $bank_id );
    $bank_disp = $matched ? $matched['short'] : $bank_id;

    $html  = '<div style="line-height:1.4;font-size:12px;">';
    $html .= '<span style="display:inline-block;background:#dcfce7;color:#166534;font-weight:700;padding:1px 7px;border-radius:4px;margin-right:4px;">' . esc_html( $bank_disp ) . '</span>';
    $html .= '<code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:1px 5px;border-radius:3px;">' . esc_html( $account_no ) . '</code>';
    if ( ! empty( $account_name ) ) {
        $html .= '<div style="color:#475569;margin-top:2px;font-size:11px;">' . esc_html( $account_name ) . '</div>';
    }
    $html .= '</div>';

    return $html;
}


//====================================
// 4. HELPER TRUNG TÂM: LẤY CẤU HÌNH VIETQR THEO CHI NHÁNH / ĐƠN HÀNG
//====================================
/**
 * Xây dựng URL ảnh mã QR từ img.vietqr.io
 *
 * @param array  $bank_config      { bank_id, account_no, account_name, template }
 * @param float  $amount           Số tiền cần chuyển
 * @param string $transfer_content Nội dung chuyển khoản
 * @return string                  URL ảnh QR hoặc chuỗi rỗng nếu thiếu thông tin
 */
function memora_build_vietqr_url( $bank_config, $amount = 0, $transfer_content = '' ) {
    $bank_id      = ! empty( $bank_config['bank_id'] ) ? trim( (string) $bank_config['bank_id'] ) : '';
    $account_no   = ! empty( $bank_config['account_no'] ) ? trim( (string) $bank_config['account_no'] ) : '';
    $account_name = ! empty( $bank_config['account_name'] ) ? trim( (string) $bank_config['account_name'] ) : '';
    $template     = ! empty( $bank_config['template'] ) ? trim( (string) $bank_config['template'] ) : 'compact2';

    if ( empty( $bank_id ) || empty( $account_no ) ) {
        return '';
    }

    $matched = memora_match_vietqr_bank( $bank_id );
    $qr_bank = $matched ? $matched['code'] : $bank_id;

    $params = array();
    if ( floatval( $amount ) > 0 ) {
        $params['amount'] = (int) round( floatval( $amount ) );
    }
    if ( $transfer_content !== '' ) {
        $params['addInfo'] = $transfer_content;
    }
    if ( $account_name !== '' ) {
        $params['accountName'] = $account_name;
    }

    $query_str = ! empty( $params ) ? ( '?' . http_build_query( $params ) ) : '';
    return 'https://img.vietqr.io/image/' . rawurlencode( $qr_bank ) . '-' . rawurlencode( $account_no ) . '-' . rawurlencode( $template ) . '.png' . $query_str;
}

/**
 * Lấy cấu hình ngân hàng VietQR của một Term dia-chi cụ thể (không fallback)
 */
function memora_get_term_vietqr_data( $dia_chi_id ) {
    $dia_chi_id = intval( $dia_chi_id );
    if ( $dia_chi_id <= 0 ) {
        return null;
    }

    $bank_id      = get_term_meta( $dia_chi_id, '_memora_vietqr_bank_id', true );
    $account_no   = get_term_meta( $dia_chi_id, '_memora_vietqr_account_no', true );
    $account_name = get_term_meta( $dia_chi_id, '_memora_vietqr_account_name', true );
    $bank_name    = get_term_meta( $dia_chi_id, '_memora_vietqr_bank_name', true );
    $template     = get_term_meta( $dia_chi_id, '_memora_vietqr_template', true );
    $prefix       = get_term_meta( $dia_chi_id, '_memora_vietqr_prefix', true );

    // Fallback đọc qua ACF nếu có
    if ( ( empty( $bank_id ) || empty( $account_no ) ) && function_exists( 'get_field' ) ) {
        $acf_source = 'term_' . $dia_chi_id;
        if ( empty( $bank_id ) )      $bank_id      = (string) get_field( 'vietqr_bank_id', $acf_source );
        if ( empty( $account_no ) )   $account_no   = (string) get_field( 'vietqr_account_no', $acf_source );
        if ( empty( $account_name ) ) $account_name = (string) get_field( 'vietqr_account_name', $acf_source );
        if ( empty( $bank_name ) )    $bank_name    = (string) get_field( 'vietqr_bank_name', $acf_source );
        if ( empty( $template ) )     $template     = (string) get_field( 'vietqr_template', $acf_source );
    }

    if ( empty( $bank_id ) || empty( $account_no ) ) {
        return null;
    }

    $matched = memora_match_vietqr_bank( $bank_id );
    $term    = get_term( $dia_chi_id, 'dia-chi' );

    return array(
        'dia_chi_id'   => $dia_chi_id,
        'dia_chi_name' => ( $term && ! is_wp_error( $term ) ) ? $term->name : '',
        'bank_id'      => $matched ? $matched['code'] : $bank_id,
        'bank_bin'     => $matched ? $matched['bin']  : $bank_id,
        'bank_name'    => ! empty( $bank_name ) ? $bank_name : ( $matched ? $matched['short'] : $bank_id ),
        'account_no'   => trim( (string) $account_no ),
        'account_name' => trim( (string) $account_name ),
        'template'     => ! empty( $template ) ? $template : 'compact2',
        'prefix'       => ! empty( $prefix ) ? $prefix : 'MEMORA',
        'source'       => 'term_dia_chi',
    );
}

/**
 * Giải mã cấu hình VietQR theo thứ tự ưu tiên:
 * 1. Term dia-chi (từ $dia_chi_id hoặc từ $order / WC Session / URL)
 * 2. Snapshot đã lưu trong WC Order (nếu term bị xóa hoặc chưa có)
 * 3. ACF Options Page chung (vietqr_bank_id, vietqr_account_no...)
 * 4. Tài khoản BACS mặc định trong WooCommerce Settings
 *
 * @param int                $dia_chi_id Term ID của taxonomy dia-chi (0 để tự phát hiện)
 * @param WC_Order|int|null  $order      Đối tượng hoặc ID đơn hàng WooCommerce
 * @return array                         Mảng cấu hình ngân hàng đầy đủ
 */
function memora_get_vietqr_config( $dia_chi_id = 0, $order = null ) {
    $dia_chi_id   = intval( $dia_chi_id );
    $dia_chi_name = '';

    if ( is_numeric( $order ) && intval( $order ) > 0 && function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( intval( $order ) );
    }

    // Bước 1: Tự động tìm dia_chi_id từ WC_Order nếu chưa truyền
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
        $dia_chi_name = (string) $order->get_meta( '_booking_dia_chi_name' );
    }

    // Bước 2: Tìm từ WC Session (khi đang ở trang Checkout) hoặc URL
    if ( $dia_chi_id <= 0 && function_exists( 'WC' ) && WC()->session ) {
        $pending = WC()->session->get( 'memora_booking_pending_data' );
        if ( is_array( $pending ) ) {
            if ( ! empty( $pending['dia_chi_id'] ) ) {
                $dia_chi_id = intval( $pending['dia_chi_id'] );
            } elseif ( ! empty( $pending['room_id'] ) && function_exists( 'memora_get_location_id_from_room' ) ) {
                $dia_chi_id = memora_get_location_id_from_room( intval( $pending['room_id'] ) );
            }
            if ( ! empty( $pending['dia_chi_name'] ) ) {
                $dia_chi_name = (string) $pending['dia_chi_name'];
            }
        }
    }

    if ( $dia_chi_id <= 0 && function_exists( 'memora_resolve_booking_location_id' ) ) {
        $dia_chi_id = memora_resolve_booking_location_id();
    }

    // Bước 3: Lấy cấu hình trực tiếp từ Term dia-chi
    if ( $dia_chi_id > 0 ) {
        $term_cfg = memora_get_term_vietqr_data( $dia_chi_id );
        if ( $term_cfg ) {
            return $term_cfg;
        }
    }

    // Bước 4: Kiểm tra Snapshot đã lưu trong WC Order (nếu có)
    if ( $order instanceof WC_Order ) {
        $snap_bank = (string) $order->get_meta( '_booking_vietqr_bank_id' );
        $snap_acc  = (string) $order->get_meta( '_booking_vietqr_account_no' );
        if ( ! empty( $snap_bank ) && ! empty( $snap_acc ) ) {
            $matched = memora_match_vietqr_bank( $snap_bank );
            return array(
                'dia_chi_id'   => $dia_chi_id,
                'dia_chi_name' => $dia_chi_name,
                'bank_id'      => $matched ? $matched['code'] : $snap_bank,
                'bank_bin'     => $matched ? $matched['bin']  : $snap_bank,
                'bank_name'    => (string) $order->get_meta( '_booking_vietqr_bank_name' ) ?: ( $matched ? $matched['short'] : $snap_bank ),
                'account_no'   => $snap_acc,
                'account_name' => (string) $order->get_meta( '_booking_vietqr_account_name' ),
                'template'     => (string) $order->get_meta( '_booking_vietqr_template' ) ?: 'compact2',
                'prefix'       => (string) $order->get_meta( '_booking_vietqr_prefix' ) ?: 'MEMORA',
                'source'       => 'order_snapshot',
            );
        }
    }

    // Bước 5: Fallback về ACF Options chung
    $opt_bank_id = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_id', 'option' ) : '';
    $opt_acc_no  = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_no', 'option' ) : '';
    if ( ! empty( $opt_bank_id ) && ! empty( $opt_acc_no ) ) {
        $opt_acc_name  = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_account_name', 'option' ) : '';
        $opt_bank_name = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_bank_name', 'option' ) : '';
        $opt_tpl       = function_exists( 'get_field' ) ? (string) get_field( 'vietqr_template', 'option' ) : 'compact2';
        $matched       = memora_match_vietqr_bank( $opt_bank_id );

        return array(
            'dia_chi_id'   => $dia_chi_id,
            'dia_chi_name' => $dia_chi_name,
            'bank_id'      => $matched ? $matched['code'] : $opt_bank_id,
            'bank_bin'     => $matched ? $matched['bin']  : $opt_bank_id,
            'bank_name'    => ! empty( $opt_bank_name ) ? $opt_bank_name : ( $matched ? $matched['short'] : $opt_bank_id ),
            'account_no'   => trim( $opt_acc_no ),
            'account_name' => trim( $opt_acc_name ),
            'template'     => ! empty( $opt_tpl ) ? $opt_tpl : 'compact2',
            'prefix'       => 'MEMORA',
            'source'       => 'acf_option',
        );
    }

    // Bước 6: Fallback về WooCommerce BACS Accounts mặc định
    $raw_bacs = get_option( 'woocommerce_bacs_accounts', array() );
    if ( is_array( $raw_bacs ) && ! empty( $raw_bacs[0]['account_number'] ) ) {
        $first    = $raw_bacs[0];
        $b_input  = ! empty( $first['bank_name'] ) ? $first['bank_name'] : ( ! empty( $first['bic'] ) ? $first['bic'] : '' );
        $matched  = memora_match_vietqr_bank( $b_input );

        return array(
            'dia_chi_id'   => $dia_chi_id,
            'dia_chi_name' => $dia_chi_name,
            'bank_id'      => $matched ? $matched['code'] : $b_input,
            'bank_bin'     => $matched ? $matched['bin']  : $b_input,
            'bank_name'    => ! empty( $first['bank_name'] ) ? $first['bank_name'] : ( $matched ? $matched['short'] : '' ),
            'account_no'   => trim( (string) $first['account_number'] ),
            'account_name' => trim( (string) ( $first['account_name'] ?? '' ) ),
            'template'     => 'compact2',
            'prefix'       => 'MEMORA',
            'source'       => 'wc_bacs_default',
        );
    }

    return array(
        'dia_chi_id'   => $dia_chi_id,
        'dia_chi_name' => $dia_chi_name,
        'bank_id'      => '',
        'bank_bin'     => '',
        'bank_name'    => '',
        'account_no'   => '',
        'account_name' => '',
        'template'     => 'compact2',
        'prefix'       => 'MEMORA',
        'source'       => 'none',
    );
}

/**
 * Lưu snapshot cấu hình ngân hàng VietQR của chi nhánh vào WC_Order
 */
function memora_save_vietqr_snapshot_to_order( $order, $dia_chi_id = 0 ) {
    if ( ! ( $order instanceof WC_Order ) ) {
        return;
    }
    $cfg = memora_get_vietqr_config( $dia_chi_id, $order );
    if ( ! empty( $cfg['bank_id'] ) && ! empty( $cfg['account_no'] ) ) {
        $order->update_meta_data( '_booking_vietqr_bank_id',      $cfg['bank_id'] );
        $order->update_meta_data( '_booking_vietqr_bank_bin',     $cfg['bank_bin'] ?? '' );
        $order->update_meta_data( '_booking_vietqr_bank_name',    $cfg['bank_name'] );
        $order->update_meta_data( '_booking_vietqr_account_no',   $cfg['account_no'] );
        $order->update_meta_data( '_booking_vietqr_account_name', $cfg['account_name'] );
        $order->update_meta_data( '_booking_vietqr_template',     $cfg['template'] );
        $order->update_meta_data( '_booking_vietqr_prefix',       $cfg['prefix'] );
    }
}


//====================================
// 5. ĐỒNG BỘ TỰ ĐỘNG VỚI WOOCOMMERCE BACS ACCOUNTS
//====================================
add_filter( 'option_woocommerce_bacs_accounts', 'memora_filter_wc_bacs_accounts_by_dia_chi', 20, 1 );
add_filter( 'woocommerce_bacs_accounts', 'memora_filter_wc_bacs_accounts_by_dia_chi', 20, 2 );
function memora_filter_wc_bacs_accounts_by_dia_chi( $accounts, $order_id = 0 ) {
    // Không can thiệp trong màn hình cài đặt WooCommerce Admin
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $accounts;
    }

    static $is_filtering = false;
    if ( $is_filtering ) {
        return $accounts;
    }
    $is_filtering = true;

    $resolved_order_id = absint( $order_id );
    if ( ! $resolved_order_id && ! empty( $GLOBALS['memora_current_thankyou_order_id'] ) ) {
        $resolved_order_id = absint( $GLOBALS['memora_current_thankyou_order_id'] );
    }
    if ( ! $resolved_order_id ) {
        $resolved_order_id = absint( get_query_var( 'order-received' ) );
    }
    if ( ! $resolved_order_id && ! empty( $_GET['order_id'] ) ) {
        $resolved_order_id = absint( $_GET['order_id'] );
    }

    $cfg = memora_get_vietqr_config( 0, $resolved_order_id > 0 ? $resolved_order_id : null );
    $is_filtering = false;

    if ( ! empty( $cfg['bank_id'] ) && ! empty( $cfg['account_no'] ) ) {
        return array(
            array(
                'account_name'   => $cfg['account_name'],
                'account_number' => $cfg['account_no'],
                'bank_name'      => $cfg['bank_name'],
                'sort_code'      => $cfg['bank_bin'] ?: $cfg['bank_id'],
                'iban'           => $cfg['bank_id'],
                'bic'            => $cfg['bank_bin'] ?: $cfg['bank_id'],
            ),
        );
    }

    return $accounts;
}

/**
 * Render khối HTML thẻ thanh toán VietQR chuẩn Memora (dùng chung cho cả [thankyou_booking] và native WC Order Received)
 */
function memora_render_vietqr_payment_box( $order_or_id = null, $fallback_code = '', $fallback_amount = 0 ) {
    static $rendered_orders = array();

    $order = null;
    if ( $order_or_id instanceof WC_Order ) {
        $order = $order_or_id;
    } elseif ( is_numeric( $order_or_id ) && intval( $order_or_id ) > 0 && function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( intval( $order_or_id ) );
    }

    $render_key = $order ? 'order_' . $order->get_id() : 'code_' . $fallback_code;
    if ( isset( $rendered_orders[ $render_key ] ) ) {
        return '';
    }

    $cfg = memora_get_vietqr_config( 0, $order );
    if ( empty( $cfg['bank_id'] ) || empty( $cfg['account_no'] ) ) {
        return '';
    }

    $rendered_orders[ $render_key ] = true;

    $code = $fallback_code;
    if ( empty( $code ) && $order ) {
        $code = $order->get_meta( '_memora_booking_code' ) ?: $order->get_meta( '_booking_code' ) ?: strval( $order->get_id() );
    }
    if ( empty( $code ) ) {
        $code = '6640';
    }

    $amount = $fallback_amount;
    if ( $amount <= 0 && $order ) {
        $amount = (int) $order->get_total();
    }

    $prefix           = ! empty( $cfg['prefix'] ) ? trim( $cfg['prefix'] ) : 'MEMORA';
    $transfer_content = $prefix . ' ' . $code;
    $qr_url           = memora_build_vietqr_url( $cfg, $amount, $transfer_content );
    $branch_name      = ! empty( $cfg['dia_chi_name'] ) ? $cfg['dia_chi_name'] : ( $order ? (string) $order->get_meta( '_booking_dia_chi_name' ) : '' );

    ob_start();
    ?>
    <!-- PHẦN THANH TOÁN VIETQR THEO CHI NHÁNH -->
    <div class="memora-payment-section">
        <div class="memora-payment-title">
            Thanh toán qua chuyển khoản
            <?php if ( ! empty( $branch_name ) ) : ?>
                <div style="font-size:0.78em;font-weight:600;color:#5a3e30;margin-top:2px;">
                    Chi nhánh: <?php echo esc_html( $branch_name ); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="memora-payment-body">
            <div class="memora-payment-qr">
                <img
                    src="<?php echo esc_url( $qr_url ); ?>"
                    alt="QR chuyển khoản <?php echo esc_attr( $transfer_content ); ?>"
                    loading="lazy"
                />
            </div>
            <div class="memora-payment-info">
                <?php if ( ! empty( $cfg['bank_name'] ) ) : ?>
                <div class="memora-payment-row">
                    <span class="memora-payment-label">Ngân hàng</span>
                    <span class="memora-payment-value"><?php echo esc_html( $cfg['bank_name'] ); ?></span>
                </div>
                <?php endif; ?>
                <div class="memora-payment-row">
                    <span class="memora-payment-label">Số TK</span>
                    <span class="memora-payment-value memora-payment-copyable" data-copy="<?php echo esc_attr( $cfg['account_no'] ); ?>">
                        <?php echo esc_html( $cfg['account_no'] ); ?>
                        <button class="memora-copy-btn" type="button" title="Sao chép">📋</button>
                    </span>
                </div>
                <?php if ( ! empty( $cfg['account_name'] ) ) : ?>
                <div class="memora-payment-row">
                    <span class="memora-payment-label">Chủ TK</span>
                    <span class="memora-payment-value"><?php echo esc_html( $cfg['account_name'] ); ?></span>
                </div>
                <?php endif; ?>
                <?php if ( $amount > 0 ) : ?>
                <div class="memora-payment-row memora-payment-row--amount">
                    <span class="memora-payment-label">Số tiền</span>
                    <span class="memora-payment-value"><?php echo esc_html( number_format( $amount, 0, ',', '.' ) ); ?>đ</span>
                </div>
                <?php endif; ?>
                <div class="memora-payment-row memora-payment-row--content">
                    <span class="memora-payment-label">Nội dung CK</span>
                    <span class="memora-payment-value memora-payment-copyable" data-copy="<?php echo esc_attr( $transfer_content ); ?>">
                        <?php echo esc_html( $transfer_content ); ?>
                        <button class="memora-copy-btn" type="button" title="Sao chép">📋</button>
                    </span>
                </div>
            </div>
        </div>
        <div class="memora-payment-note">
            ⏱ Sau khi chuyển khoản, lịch chụp sẽ được xác nhận trong vòng <strong>15 phút</strong>.
        </div>
    </div>
    <script>
    (function(){
        document.querySelectorAll('.memora-copy-btn').forEach(function(btn){
            if (btn.dataset.boundCopy) return;
            btn.dataset.boundCopy = '1';
            btn.addEventListener('click', function(){
                var row = btn.closest('.memora-payment-copyable');
                var text = row ? row.dataset.copy : '';
                if (!text) return;
                navigator.clipboard.writeText(text).then(function(){
                    btn.textContent = '✅';
                    setTimeout(function(){ btn.textContent = '📋'; }, 1500);
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Tự động hiển thị khối VietQR theo Chi nhánh trên trang Order Received chuẩn của WooCommerce
 * (Nếu trang đã dùng [thankyou_booking] / [checkout_booking] thì static cache sẽ tự bỏ qua không in trùng)
 */
add_action( 'woocommerce_thankyou_bacs', 'memora_wc_thankyou_bacs_render_branch_vietqr', 5, 1 );
function memora_wc_thankyou_bacs_render_branch_vietqr( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    $GLOBALS['memora_current_thankyou_order_id'] = absint( $order_id );
    echo memora_render_vietqr_payment_box( $order_id );
}

