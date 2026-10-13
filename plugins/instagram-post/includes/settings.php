<?php
/**
 * Trang Cài đặt Quản trị: Setting Instagram
 */

// Chống truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - ĐĂNG KÝ MENU VÀ CÀI ĐẶT TRONG WP-ADMIN
//====================================
add_action( 'admin_menu', 'memora_ig_register_admin_menu' );
function memora_ig_register_admin_menu() {
    add_menu_page(
        __( 'Setting Instagram', 'instagram-post' ),
        __( 'Setting Instagram', 'instagram-post' ),
        'manage_options',
        'setting-instagram',
        'memora_ig_render_settings_page',
        'dashicons-camera-alt',
        31
    );
}

add_action( 'admin_init', 'memora_ig_register_settings' );
function memora_ig_register_settings() {
    register_setting(
        'memora_ig_settings_group',
        'memora_instagram_settings',
        'memora_ig_sanitize_settings'
    );
}

/**
 * Sanitize dữ liệu khi lưu Form
 */
function memora_ig_sanitize_settings( $input ) {
    $current = memora_ig_get_settings();
    $output  = array();

    $output['access_token']     = ! empty( $input['access_token'] ) ? trim( sanitize_text_field( $input['access_token'] ) ) : '';
    $output['instagram_url']    = ! empty( $input['instagram_url'] ) ? esc_url_raw( trim( $input['instagram_url'] ) ) : '';
    $output['username']         = ! empty( $input['username'] ) ? sanitize_text_field( trim( $input['username'] ) ) : '';
    $output['user_id']          = ! empty( $input['user_id'] ) ? sanitize_text_field( trim( $input['user_id'] ) ) : '';
    $output['post_count']       = ! empty( $input['post_count'] ) ? max( 1, min( 50, (int) $input['post_count'] ) ) : 6;
    $output['columns']          = ! empty( $input['columns'] ) ? max( 1, min( 6, (int) $input['columns'] ) ) : 4;
    $output['gap']              = isset( $input['gap'] ) ? max( 0, min( 60, (int) $input['gap'] ) ) : 16;
    $output['aspect_ratio']     = in_array( $input['aspect_ratio'], array( '1/1', '4/5' ), true ) ? $input['aspect_ratio'] : '1/1';
    $output['border_radius']    = isset( $input['border_radius'] ) ? max( 0, min( 40, (int) $input['border_radius'] ) ) : 10;
    $output['show_button']      = ! empty( $input['show_button'] ) ? 1 : 0;
    $output['button_text']      = ! empty( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : 'Theo dõi chúng tôi trên Instagram';
    $output['show_caption']     = ! empty( $input['show_caption'] ) ? 1 : 0;
    $output['cache_time_hours'] = ! empty( $input['cache_time_hours'] ) ? max( 1, min( 48, (int) $input['cache_time_hours'] ) ) : 4;
    $output['token_expires_at'] = isset( $current['token_expires_at'] ) ? $current['token_expires_at'] : '';
    $output['last_sync_time']   = isset( $current['last_sync_time'] ) ? $current['last_sync_time'] : '';

    // Xóa cache khi cập nhật token hoặc cấu hình
    if ( $output['access_token'] !== $current['access_token'] ) {
        Memora_IG_API::clear_all_cache();
    }

    return $output;
}
//====================================
// END - ĐĂNG KÝ MENU VÀ CÀI ĐẶT TRONG WP-ADMIN
//====================================





//====================================
// START - XỬ LÝ CÁC ACTION AJAX TRONG ADMIN
//====================================
add_action( 'wp_ajax_memora_ig_test_token', 'memora_ig_ajax_test_token' );
function memora_ig_ajax_test_token() {
    check_ajax_referer( 'memora_ig_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Bạn không có quyền thực hiện thao tác này.', 'instagram-post' ) ) );
    }

    $token = isset( $_POST['token'] ) ? trim( sanitize_text_field( $_POST['token'] ) ) : '';
    if ( empty( $token ) ) {
        $settings = memora_ig_get_settings();
        $token = $settings['access_token'];
    }

    if ( empty( $token ) ) {
        wp_send_json_error( array( 'message' => __( 'Vui lòng nhập Access Token để kiểm tra.', 'instagram-post' ) ) );
    }

    $result = Memora_IG_API::verify_access_token( $token );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // Cập nhật thông tin username và user_id nếu có
    $settings = memora_ig_get_settings();
    if ( ! empty( $result['username'] ) ) {
        $settings['username'] = $result['username'];
        if ( empty( $settings['instagram_url'] ) || $settings['instagram_url'] === 'https://instagram.com/memora_film' ) {
            $settings['instagram_url'] = 'https://www.instagram.com/' . $result['username'];
        }
    }
    if ( ! empty( $result['id'] ) ) {
        $settings['user_id'] = $result['id'];
    }
    update_option( 'memora_instagram_settings', $settings );

    wp_send_json_success( array(
        'message'     => __( 'Kết nối Instagram thành công!', 'instagram-post' ),
        'account'     => $result,
        'profile_url' => $settings['instagram_url'],
    ) );
}

add_action( 'wp_ajax_memora_ig_clear_cache', 'memora_ig_ajax_clear_cache' );
function memora_ig_ajax_clear_cache() {
    check_ajax_referer( 'memora_ig_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Bạn không có quyền thực hiện thao tác này.', 'instagram-post' ) ) );
    }

    Memora_IG_API::clear_all_cache();
    wp_send_json_success( array( 'message' => __( 'Đã xóa toàn bộ bộ nhớ đệm (Cache). Hệ thống sẽ lấy bài viết mới nhất ở lần xem tiếp theo.', 'instagram-post' ) ) );
}

add_action( 'wp_ajax_memora_ig_refresh_token', 'memora_ig_ajax_refresh_token' );
function memora_ig_ajax_refresh_token() {
    check_ajax_referer( 'memora_ig_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Bạn không có quyền thực hiện thao tác này.', 'instagram-post' ) ) );
    }

    $success = Memora_IG_API::refresh_access_token();
    if ( $success ) {
        wp_send_json_success( array( 'message' => __( 'Làm mới Access Token thành công! Thời hạn sử dụng được gia hạn thêm 60 ngày.', 'instagram-post' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Không thể làm mới Token. Vui lòng kiểm tra lại Access Token hiện tại.', 'instagram-post' ) ) );
    }
}
//====================================
// END - XỬ LÝ CÁC ACTION AJAX TRONG ADMIN
//====================================





//====================================
// START - GIAO DIỆN TRANG SETTING INSTAGRAM TRONG ADMIN
//====================================
function memora_ig_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = memora_ig_get_settings();
    $has_token = ! empty( $settings['access_token'] );
    ?>
    <div class="wrap memora-ig-admin-wrap">
        <div class="memora-ig-header">
            <div class="memora-ig-header-icon">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="6" fill="url(#ig-grad-admin)"/>
                    <path d="M12 7.00003C9.23858 7.00003 7 9.23861 7 12C7 14.7615 9.23858 17 12 17C14.7614 17 17 14.7615 17 12C17 9.23861 14.7614 7.00003 12 7.00003ZM12 15.35C10.15 15.35 8.65 13.85 8.65 12C8.65 10.15 10.15 8.65003 12 8.65003C13.85 8.65003 15.35 10.15 15.35 12C15.35 13.85 13.85 15.35 12 15.35Z" fill="white"/>
                    <circle cx="16.5" cy="7.5" r="1.1" fill="white"/>
                    <defs>
                        <linearGradient id="ig-grad-admin" x1="2" y1="22" x2="22" y2="2" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#FD5949"/>
                            <stop offset="0.5" stop-color="#D6249F"/>
                            <stop offset="1" stop-color="#285AEB"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div>
                <h1 class="memora-ig-title">Setting Instagram</h1>
                <p class="memora-ig-subtitle">Cấu hình kết nối Instagram Graph API, tùy chỉnh số lượng bài viết và link Instagram cho shortcode <code>[instagram_post]</code></p>
            </div>
        </div>

        <?php settings_errors(); ?>

        <form method="post" action="options.php" class="memora-ig-form">
            <?php settings_fields( 'memora_ig_settings_group' ); ?>

            <div class="memora-ig-grid-container">
                <!-- CỘT CHÍNH: FORM CẤU HÌNH -->
                <div class="memora-ig-main-col">

                    <!-- CARD 1: KẾT NỐI API INSTAGRAM -->
                    <div class="memora-ig-card">
                        <div class="memora-ig-card-header">
                            <span class="dashicons dashicons-admin-links"></span>
                            <h2>1. Kết nối Instagram Graph API</h2>
                        </div>
                        <div class="memora-ig-card-body">
                            <table class="form-table memora-ig-table">
                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_access_token">Instagram Access Token <span class="required">*</span></label>
                                    </th>
                                    <td>
                                        <div class="memora-ig-token-input-group">
                                            <input type="password" 
                                                   id="memora_ig_access_token" 
                                                   name="memora_instagram_settings[access_token]" 
                                                   value="<?php echo esc_attr( $settings['access_token'] ); ?>" 
                                                   class="large-text code" 
                                                   placeholder="EAA..." 
                                                   autocomplete="off" />
                                            <button type="button" class="button memora-btn-toggle-token" title="Ẩn/Hiện Token">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                        </div>
                                        <p class="description">
                                            Nhập Long-Lived User Access Token được cấp từ <strong>Meta for Developers &gt; Instagram Basic Display</strong>.
                                        </p>
                                        <div class="memora-ig-token-actions">
                                            <button type="button" id="memora-ig-btn-test" class="button button-primary">
                                                <span class="dashicons dashicons-update"></span> Kiểm tra kết nối
                                            </button>
                                            <button type="button" id="memora-ig-btn-clear-cache" class="button">
                                                <span class="dashicons dashicons-trash"></span> Xóa Cache bài viết
                                            </button>
                                            <button type="button" id="memora-ig-btn-refresh-token" class="button">
                                                <span class="dashicons dashicons-image-rotate"></span> Gia hạn Token (60 ngày)
                                            </button>
                                        </div>
                                        <div id="memora-ig-ajax-notice" class="memora-ig-ajax-notice"></div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_url">Link Profile Instagram <span class="required">*</span></label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               id="memora_ig_url" 
                                               name="memora_instagram_settings[instagram_url]" 
                                               value="<?php echo esc_attr( $settings['instagram_url'] ); ?>" 
                                               class="regular-text" 
                                               placeholder="https://instagram.com/memora_film" />
                                        <p class="description">Đường dẫn trang cá nhân Instagram của bạn. Dùng cho nút <strong>"Theo dõi trên Instagram"</strong>.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_username">Tên người dùng (Username)</label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="memora_ig_username" 
                                               name="memora_instagram_settings[username]" 
                                               value="<?php echo esc_attr( $settings['username'] ); ?>" 
                                               class="regular-text" 
                                               placeholder="memora_film" />
                                        <p class="description">Tự động điền khi bấm nút <em>"Kiểm tra kết nối"</em> thành công.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>


                    <!-- CARD 2: CẤU HÌNH HIỂN THỊ VÀ SHORTCODE -->
                    <div class="memora-ig-card">
                        <div class="memora-ig-card-header">
                            <span class="dashicons dashicons-layout"></span>
                            <h2>2. Cấu hình Hiển thị Shortcode Mặc định</h2>
                        </div>
                        <div class="memora-ig-card-body">
                            <table class="form-table memora-ig-table">
                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_post_count">Số bài viết xuất ra</label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="memora_ig_post_count" 
                                               name="memora_instagram_settings[post_count]" 
                                               value="<?php echo esc_attr( $settings['post_count'] ); ?>" 
                                               min="1" 
                                               max="50" 
                                               class="small-text" />
                                        <span class="description">bài viết (Mặc định: <strong>6</strong>. Có thể ghi đè bằng thuộc tính <code>[instagram_post limit="8"]</code>).</span>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_columns">Số cột hiển thị (Desktop)</label>
                                    </th>
                                    <td>
                                        <select id="memora_ig_columns" name="memora_instagram_settings[columns]">
                                            <option value="2" <?php selected( $settings['columns'], 2 ); ?>>2 Cột</option>
                                            <option value="3" <?php selected( $settings['columns'], 3 ); ?>>3 Cột</option>
                                            <option value="4" <?php selected( $settings['columns'], 4 ); ?>>4 Cột (Mặc định)</option>
                                            <option value="5" <?php selected( $settings['columns'], 5 ); ?>>5 Cột</option>
                                            <option value="6" <?php selected( $settings['columns'], 6 ); ?>>6 Cột</option>
                                        </select>
                                        <p class="description">Trên Tablet tự động co thành 2-3 cột, trên Mobile hiển thị 2 cột chuẩn responsive.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_gap">Khoảng cách giữa các bài (Gap)</label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="memora_ig_gap" 
                                               name="memora_instagram_settings[gap]" 
                                               value="<?php echo esc_attr( $settings['gap'] ); ?>" 
                                               min="0" 
                                               max="60" 
                                               class="small-text" />
                                        <span class="description">px (Mặc định: <strong>16px</strong>).</span>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_aspect_ratio">Tỉ lệ ảnh bài viết</label>
                                    </th>
                                    <td>
                                        <select id="memora_ig_aspect_ratio" name="memora_instagram_settings[aspect_ratio]">
                                            <option value="1/1" <?php selected( $settings['aspect_ratio'], '1/1' ); ?>>1:1 (Vuông chuẩn Instagram)</option>
                                            <option value="4/5" <?php selected( $settings['aspect_ratio'], '4/5' ); ?>>4:5 (Khổ dọc thời trang)</option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_border_radius">Bo góc bài viết</label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="memora_ig_border_radius" 
                                               name="memora_instagram_settings[border_radius]" 
                                               value="<?php echo esc_attr( $settings['border_radius'] ); ?>" 
                                               min="0" 
                                               max="40" 
                                               class="small-text" />
                                        <span class="description">px (Mặc định: <strong>10px</strong>).</span>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Hiệu ứng Hover</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="memora_instagram_settings[show_caption]" 
                                                   value="1" 
                                                   <?php checked( $settings['show_caption'], 1 ); ?> />
                                            Hiển thị Icon Instagram và trích đoạn Caption bài viết khi di chuột qua ảnh.
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Nút Theo dõi Instagram</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   id="memora_ig_show_button" 
                                                   name="memora_instagram_settings[show_button]" 
                                                   value="1" 
                                                   <?php checked( $settings['show_button'], 1 ); ?> />
                                            Hiển thị nút dẫn tới link Profile Instagram ở dưới danh sách ảnh.
                                        </label>
                                        <div style="margin-top: 10px;">
                                            <input type="text" 
                                                   name="memora_instagram_settings[button_text]" 
                                                   value="<?php echo esc_attr( $settings['button_text'] ); ?>" 
                                                   class="regular-text" 
                                                   placeholder="Theo dõi chúng tôi trên Instagram" />
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="memora_ig_cache">Thời gian lưu Cache</label>
                                    </th>
                                    <td>
                                        <select id="memora_ig_cache" name="memora_instagram_settings[cache_time_hours]">
                                            <option value="1" <?php selected( $settings['cache_time_hours'], 1 ); ?>>1 Giờ</option>
                                            <option value="4" <?php selected( $settings['cache_time_hours'], 4 ); ?>>4 Giờ (Khuyên dùng)</option>
                                            <option value="12" <?php selected( $settings['cache_time_hours'], 12 ); ?>>12 Giờ</option>
                                            <option value="24" <?php selected( $settings['cache_time_hours'], 24 ); ?>>24 Giờ</option>
                                        </select>
                                        <p class="description">Lưu bài viết vào bộ nhớ tạm (Transient) giúp website tải siêu nhanh mà không bị nghẽn giới hạn lượt gọi API của Instagram.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="memora-ig-submit-wrap">
                        <?php submit_button( __( 'Lưu tất cả Cài đặt', 'instagram-post' ), 'primary large' ); ?>
                    </div>
                </div>

                <!-- CỘT PHỤ (SIDEBAR): HƯỚNG DẪN & SHORTCODE INFO -->
                <div class="memora-ig-sidebar-col">
                    <!-- CARD SIDEBAR 1: CÁCH DÙNG SHORTCODE -->
                    <div class="memora-ig-card memora-ig-sidebar-card">
                        <div class="memora-ig-card-header">
                            <span class="dashicons dashicons-shortcode"></span>
                            <h3>Hướng dẫn Shortcode</h3>
                        </div>
                        <div class="memora-ig-card-body">
                            <p>Sử dụng shortcode cơ bản (tự động đọc số bài và link Instagram từ cấu hình bên trái):</p>
                            <div class="memora-ig-code-box">
                                <code>[instagram_post]</code>
                                <button type="button" class="button button-small memora-copy-btn" data-copy="[instagram_post]">Copy</button>
                            </div>

                            <p style="margin-top: 15px;"><strong>Các tham số tùy chọn (Attributes):</strong></p>
                            <ul class="memora-ig-attr-list">
                                <li><code>limit="6"</code>: Đổi số bài viết (VD: <code>[instagram_post limit="8"]</code>)</li>
                                <li><code>columns="4"</code>: Đổi số cột (VD: <code>[instagram_post columns="6"]</code>)</li>
                                <li><code>gap="12"</code>: Khoảng cách giữa các ảnh (px)</li>
                                <li><code>link="..."</code>: Ghi đè link profile Instagram</li>
                                <li><code>show_button="false"</code>: Ẩn nút theo dõi</li>
                                <li><code>button_text="..."</code>: Đổi chữ nút theo dõi</li>
                                <li><code>ratio="square"</code>: Vuông (1:1) hoặc <code>ratio="portrait"</code> (4:5)</li>
                                <li><code>class="custom-class"</code>: Thêm class CSS riêng</li>
                            </ul>
                        </div>
                    </div>

                    <!-- CARD SIDEBAR 2: TRẠNG THÁI HỆ THỐNG -->
                    <div class="memora-ig-card memora-ig-sidebar-card">
                        <div class="memora-ig-card-header">
                            <span class="dashicons dashicons-dashboard"></span>
                            <h3>Trạng thái Kết nối</h3>
                        </div>
                        <div class="memora-ig-card-body">
                            <div class="memora-ig-status-box <?php echo $has_token ? 'status-ok' : 'status-empty'; ?>">
                                <span class="dashicons <?php echo $has_token ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <div>
                                    <strong><?php echo $has_token ? 'Đã cấu hình Token' : 'Chưa nhập Access Token'; ?></strong>
                                    <p><?php echo $has_token ? 'Hệ thống đã sẵn sàng xuất bài viết qua shortcode.' : 'Hãy nhập token bên trái để kích hoạt feed bài viết.'; ?></p>
                                </div>
                            </div>
                            <?php if ( ! empty( $settings['last_sync_time'] ) ) : ?>
                                <p class="description" style="margin-top: 10px;">
                                    Đồng bộ gần nhất: <strong><?php echo esc_html( $settings['last_sync_time'] ); ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- CARD SIDEBAR 3: HƯỚNG DẪN LẤY TOKEN -->
                    <div class="memora-ig-card memora-ig-sidebar-card">
                        <div class="memora-ig-card-header">
                            <span class="dashicons dashicons-info"></span>
                            <h3>Cách lấy Access Token Meta</h3>
                        </div>
                        <div class="memora-ig-card-body memora-ig-guide-body">
                            <ol>
                                <li>Truy cập <a href="https://developers.facebook.com" target="_blank" rel="noopener">developers.facebook.com</a> &gt; <strong>My Apps</strong> &gt; Tạo App mới.</li>
                                <li>Chọn sản phẩm: <strong>Instagram Basic Display</strong>.</li>
                                <li>Tại <em>User Token Generator</em>, thêm tài khoản Instagram Tester và chấp nhận lời mời trên Instagram.</li>
                                <li>Bấm <strong>Generate Token</strong> và chọn thời hạn 60 ngày (Long-Lived).</li>
                                <li>Dán mã Token vào ô <strong>Instagram Access Token</strong> rồi bấm <strong>Lưu cài đặt</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
}
//====================================
// END - GIAO DIỆN TRANG SETTING INSTAGRAM TRONG ADMIN
//====================================
