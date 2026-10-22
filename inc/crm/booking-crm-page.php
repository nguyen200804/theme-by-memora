<?php
/**
 * Memora Booking CRM - Admin Menu & UI Render Engine
 *
 * Đăng ký menu trang quản trị WP-Admin và Shortcode [memora_booking_crm]
 * để nhân viên có thể sử dụng cả trong trang Admin lẫn giao diện Portal riêng tại quầy.
 *
 * @package Memora
 * @subpackage BookingCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 0. KIỂM TRA QUYỀN HẠN VÀ ĐIỀU HƯỚNG BẢO MẬT CHO TRANG CRM
 */

/**
 * Kiểm tra xem user có quyền truy cập CRM không.
 * Chỉ user thuộc quyền 'administrator' hoặc 'editor' mới được phép.
 *
 * @param WP_User|null $user
 * @return bool
 */
function memora_crm_user_has_access( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->exists() ) {
        return false;
    }

    $roles = (array) $user->roles;
    if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) ) {
        return true;
    }

    if ( is_multisite() && is_super_admin( $user->ID ) ) {
        return true;
    }

    return false;
}

/**
 * Kiểm tra xem request hiện tại có phải là trang /quan-li-dat-lich/ hay không.
 *
 * @return bool
 */
function memora_is_crm_page() {
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return false;
    }

    // 1. Kiểm tra qua WordPress is_page
    if ( function_exists( 'is_page' ) && is_page( 'quan-li-dat-lich' ) ) {
        return true;
    }

    // 2. Kiểm tra qua $post object
    global $post;
    if ( is_singular() && is_a( $post, 'WP_Post' ) ) {
        if ( $post->post_name === 'quan-li-dat-lich' ) {
            return true;
        }
        if ( has_shortcode( $post->post_content, 'memora_booking_crm' ) ) {
            return true;
        }
    }

    // 3. Kiểm tra qua Request URI thực tế
    if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
        $path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        if ( $path === 'quan-li-dat-lich' || substr( $path, -17 ) === 'quan-li-dat-lich' ) {
            return true;
        }
    }

    return false;
}

/**
 * BẢO VỆ TRANG /quan-li-dat-lich/:
 * Chỉ user thuộc quyền editor và administrator được vào /quan-li-dat-lich/.
 * Nếu user khác hoặc chưa đăng nhập vào /quan-li-dat-lich/ thì sẽ 301 về trang đăng nhập.
 */
add_action( 'template_redirect', 'memora_crm_protect_page_and_redirect', 1 );
function memora_crm_protect_page_and_redirect() {
    if ( ! memora_is_crm_page() ) {
        return;
    }

    if ( ! is_user_logged_in() || ! memora_crm_user_has_access() ) {
        $current_url = home_url( '/quan-li-dat-lich/' );
        $login_url   = wp_login_url( $current_url );
        wp_redirect( $login_url, 301 );
        exit;
    }
}

/**
 * TỰ ĐỘNG CHUYỂN SANG CANVAS TEMPLATE (KHÔNG HEADER, KHÔNG FOOTER) CHO /quan-li-dat-lich/
 */
add_filter( 'template_include', 'memora_crm_force_canvas_template', 999999 );
function memora_crm_force_canvas_template( $template ) {
    if ( memora_is_crm_page() ) {
        $portal_template = get_stylesheet_directory() . '/template-crm-portal.php';
        if ( file_exists( $portal_template ) ) {
            return $portal_template;
        }
    }
    return $template;
}

/**
 * ẨN HEADER VÀ FOOTER TRONG /quan-li-dat-lich/ (HELLO ELEMENTOR & THEME BUILDER HOOKS)
 */
add_filter( 'hello_elementor_header_display', 'memora_crm_disable_header_footer' );
add_filter( 'hello_elementor_footer_display', 'memora_crm_disable_header_footer' );
add_filter( 'hello_elementor_header_footer_display', 'memora_crm_disable_header_footer' );
function memora_crm_disable_header_footer( $display ) {
    if ( memora_is_crm_page() ) {
        return false;
    }
    return $display;
}

add_filter( 'elementor/theme/need_override_location', function( $need_override, $location ) {
    if ( memora_is_crm_page() && in_array( $location, array( 'header', 'footer' ), true ) ) {
        return false;
    }
    return $need_override;
}, 999, 2 );

add_filter( 'body_class', function( $classes ) {
    if ( memora_is_crm_page() ) {
        $classes[] = 'memora-crm-portal-page';
        $classes[] = 'elementor-template-canvas';
    }
    return $classes;
} );

/**
 * 1. ĐĂNG KÝ MENU QUẢN TRỊ TRONG WORDPRESS
 */
add_action( 'admin_menu', 'memora_crm_register_admin_menu' );
function memora_crm_register_admin_menu() {
    $capability = 'edit_others_posts'; // Cho phép editor và administrator truy cập
    if ( ! current_user_can( $capability ) && current_user_can( 'manage_woocommerce' ) ) {
        $capability = 'manage_woocommerce';
    } elseif ( ! current_user_can( $capability ) && current_user_can( 'manage_options' ) ) {
        $capability = 'manage_options';
    }

    add_menu_page(
        __( 'Lịch Chụp Studio', 'memora' ),
        __( 'CRM Lịch Chụp', 'memora' ),
        $capability,
        'memora-booking-crm',
        'memora_crm_render_view',
        'dashicons-calendar-alt',
        26
    );
}

/**
 * 2. ĐĂNG KÝ SHORTCODE [memora_booking_crm] CHO FRONTEND PORTAL
 */
add_shortcode( 'memora_booking_crm', 'memora_crm_shortcode_handler' );
function memora_crm_shortcode_handler() {
    if ( ! is_user_logged_in() || ! memora_crm_user_has_access() ) {
        $login_url = wp_login_url( home_url( '/quan-li-dat-lich/' ) );
        wp_redirect( $login_url, 301 );
        exit;
    }

    // Nạp assets cho frontend
    memora_crm_enqueue_assets( true );

    ob_start();
    memora_crm_render_view();
    return ob_get_clean();
}

/**
 * 3. ENQUEUE SCRIPTS VÀ STYLES CHO CRM (DÙNG CHUNG CHO CẢ ADMIN VÀ FRONTEND)
 */
add_action( 'admin_enqueue_scripts', 'memora_crm_admin_enqueue_assets' );
function memora_crm_admin_enqueue_assets( $hook ) {
    if ( strpos( $hook, 'memora-booking-crm' ) === false ) {
        return;
    }
    memora_crm_enqueue_assets();
}

add_action( 'wp_enqueue_scripts', 'memora_crm_frontend_enqueue_assets', 9999 );
function memora_crm_frontend_enqueue_assets() {
    if ( memora_is_crm_page() ) {
        memora_crm_enqueue_assets( true );
    }
}

function memora_crm_enqueue_assets( $is_frontend = false ) {
    $theme_dir = trailingslashit( get_stylesheet_directory() );
    $theme_uri = trailingslashit( get_stylesheet_directory_uri() );

    $css_path = $theme_dir . 'assets/css/booking-crm.css';
    $js_path  = $theme_dir . 'assets/js/booking-crm.js';

    $css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
    $js_ver  = file_exists( $js_path )  ? filemtime( $js_path )  : '1.0.0';

    // Nạp Font Inter chuẩn
    wp_enqueue_style( 'memora-crm-google-font-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null );

    wp_enqueue_style( 'memora-booking-crm-css', $theme_uri . 'assets/css/booking-crm.css', array( 'memora-crm-google-font-inter' ), $css_ver );
    wp_enqueue_script( 'memora-booking-crm-js', $theme_uri . 'assets/js/booking-crm.js', array( 'jquery' ), $js_ver, true );

    wp_localize_script( 'memora-booking-crm-js', 'memora_crm_vars', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'memora_crm_action' ),
        'today'           => current_time( 'Y-m-d' ),
        'today_formatted' => current_time( 'd/m/Y' ),
        'status_configs'  => function_exists( 'memora_crm_get_status_config' ) ? memora_crm_get_status_config() : array(),
    ) );
}

/**
 * 4. RENDER CẤU TRÚC GIAO DIỆN BÀN LÀM VIỆC CRM
 */
function memora_crm_render_view() {
    ?>
    <div id="memora-crm-app" class="memora-crm-app">
        <!-- 1. Toolbar điều khiển -->
        <div class="memora-crm-toolbar">
            <!-- Hàng 1: Tiêu đề & Chọn ngày (trái) + 4 Thanh lọc nằm ngang (phải) -->
            <div class="memora-crm-toolbar__top">
                <div class="memora-crm-toolbar__brand-date">
                    <div class="memora-crm-brand">
                        <span class="memora-crm-brand__badge">Memora</span>
                        <h2 class="memora-crm-brand__title">Bảng Điều Phối Lịch Chụp</h2>
                    </div>

                    <!-- Bộ điều khiển ngày -->
                    <div class="memora-crm-date-ctrl">
                        <button type="button" class="memora-crm-btn-icon" id="memora-crm-btn-prev" title="Hôm trước">‹</button>
                        <input type="date" id="memora-crm-date" class="memora-crm-date-input" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                        <button type="button" class="memora-crm-btn-icon" id="memora-crm-btn-next" title="Hôm sau">›</button>
                    </div>
                    <button type="button" class="memora-crm-btn-today" id="memora-crm-btn-today">Hôm nay</button>
                </div>

                <!-- 4 Thanh lọc nằm ngang bên phải (Chi nhánh, Phòng, Slot, Tìm kiếm) -->
                <div class="memora-crm-toolbar__filters">
                    <!-- 1. Bộ chọn chi nhánh / cơ sở -->
                    <select id="memora-crm-location" class="memora-crm-select" title="Chọn chi nhánh">
                        <option value="0">Tất cả chi nhánh</option>
                    </select>

                    <!-- 2. Bộ chọn lọc phòng chụp -->
                    <select id="memora-crm-room-filter" class="memora-crm-select" title="Lọc theo phòng chụp">
                        <option value="0">Tất cả phòng</option>
                    </select>

                    <!-- 3. Bộ chọn bước nhảy slot -->
                    <select id="memora-crm-interval" class="memora-crm-select" title="Bước nhảy khung giờ">
                        <option value="5" selected>Slot: 5 phút</option>
                        <option value="10">Slot: 10 phút</option>
                        <option value="15">Slot: 15 phút</option>
                    </select>

                    <!-- 4. Ô tìm kiếm nhanh khách hàng -->
                    <input type="text" id="memora-crm-search" class="memora-crm-search-input" placeholder="🔍 Tìm tên, SĐT...">
                </div>
            </div>

            <!-- Hàng 2: Các nút hành động thao tác nhanh -->
            <div class="memora-crm-toolbar__bottom">
                <button type="button" class="memora-crm-btn-primary" id="memora-crm-btn-quick-book">
                    <span>+</span> Đặt lịch nhanh
                </button>
                <button type="button" class="memora-crm-btn-secondary" id="memora-crm-btn-sound" title="Bật/Tắt chuông báo đơn mới">
                    🔔 <span id="memora-sound-status">Chuông: Bật</span>
                </button>
                <button type="button" class="memora-crm-btn-secondary" id="memora-crm-btn-export" title="Xuất file Excel / CSV trong ngày">
                    📥 Xuất Excel
                </button>
                <button type="button" class="memora-crm-btn-secondary" id="memora-crm-btn-refresh" title="Tải lại dữ liệu">
                    ↻ Làm mới
                </button>
            </div>
        </div>

        <!-- 2. Thanh thống kê nhanh trong ngày (Stats Bar) -->
        <div class="memora-crm-stats-bar">
            <div class="memora-crm-stat-chip">
                <span>Tổng đơn:</span> <span class="val" id="memora-stat-total">0</span>
            </div>
            <div class="memora-crm-stat-chip memora-crm-stat-chip--green">
                <span>Đã chụp:</span> <span class="val" id="memora-stat-shoot">0</span>
            </div>
            <div class="memora-crm-stat-chip memora-crm-stat-chip--blue">
                <span>Đã in ảnh:</span> <span class="val" id="memora-stat-print">0</span>
            </div>
            <div class="memora-crm-stat-chip memora-crm-stat-chip--red">
                <span>Hủy:</span> <span class="val" id="memora-stat-cancelled">0</span>
            </div>
            <div class="memora-crm-stat-chip">
                <span>Doanh thu:</span> <span class="val" id="memora-stat-revenue">0đ</span>
            </div>
        </div>

        <!-- 3. Khu vực hiển thị bảng tính ma trận đa phòng -->
        <div id="memora-crm-table-container">
            <div class="memora-crm-loading">
                <div class="memora-crm-spinner"></div>
                <span>Đang tải ma trận điều phối lịch chụp...</span>
            </div>
        </div>

        <!-- 4. Popup tạo nhanh lịch tại quầy (Quick Walk-in Modal) -->
        <div id="memora-crm-quick-modal" class="memora-crm-modal-overlay">
            <div class="memora-crm-modal-card">
                <div class="memora-crm-modal-header">
                    <h3>Đặt Lịch Nhanh Tại Quầy</h3>
                    <button type="button" class="memora-crm-modal-close" data-target="#memora-crm-quick-modal">&times;</button>
                </div>
                <form id="memora-crm-quick-form">
                    <div class="memora-crm-modal-body">
                        <div class="memora-crm-form-grid">
                            <div>
                                <label class="memora-crm-field-label">Ngày chụp</label>
                                <input type="date" id="memora-quick-date" class="memora-crm-form-control" required>
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Giờ chụp</label>
                                <input type="text" id="memora-quick-time" class="memora-crm-form-control" placeholder="10:00" required>
                            </div>
                            <div class="memora-crm-form-full">
                                <label class="memora-crm-field-label">Phòng chụp</label>
                                <select id="memora-quick-room" class="memora-crm-form-control" required></select>
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Tên khách hàng *</label>
                                <input type="text" id="memora-quick-name" class="memora-crm-form-control" placeholder="VD: Nguyễn Văn A" required>
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Số điện thoại</label>
                                <input type="tel" id="memora-quick-phone" class="memora-crm-form-control" placeholder="0901234567">
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Gói chụp</label>
                                <input type="text" id="memora-quick-pkg" class="memora-crm-form-control" placeholder="5, 10, 10p2n..." value="5">
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Background / Ghi chú</label>
                                <input type="text" id="memora-quick-bg" class="memora-crm-form-control" placeholder="VD: cọc 90k, phông be">
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Tiền cọc (VNĐ)</label>
                                <input type="text" inputmode="numeric" id="memora-quick-deposit" class="memora-crm-form-control" placeholder="VD: 150000">
                            </div>
                            <div>
                                <label class="memora-crm-field-label">Thanh toán</label>
                                <select id="memora-quick-pay" class="memora-crm-form-control">
                                    <option value="da_ck">Đã chuyển khoản (ck)</option>
                                    <option value="tien_mat">Tiền mặt</option>
                                    <option value="da_coc">Đã cọc</option>
                                    <option value="chua_thanh_toan">Chưa thanh toán</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="memora-crm-modal-footer">
                        <button type="button" class="memora-crm-btn-secondary memora-crm-btn-close-modal">Hủy bỏ</button>
                        <button type="submit" class="memora-crm-btn-primary" id="memora-crm-btn-submit-quick">Lưu Đặt Lịch</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 5. Popup Xem chi tiết & Điều phối chuyển phòng / Hủy lịch -->
        <div id="memora-crm-detail-modal" class="memora-crm-modal-overlay">
            <div class="memora-crm-modal-card">
                <div class="memora-crm-modal-header">
                    <h3 id="memora-detail-modal-title">Chi Tiết Đơn Đặt Lịch</h3>
                    <button type="button" class="memora-crm-modal-close" data-target="#memora-crm-detail-modal">&times;</button>
                </div>
                <div class="memora-crm-modal-body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h4 style="margin:0;font-size:13px;color:#475569;text-transform:uppercase;font-weight:700;">Thông tin đặt lịch (Tên, Gói, Background, SĐT)</h4>
                        <span id="memora-detail-price" style="font-size:12px;font-weight:600;color:#733e1c;">Cọc: 0đ / Tổng: 0đ</span>
                    </div>

                    <div class="memora-crm-form-grid" style="margin-bottom:12px;">
                        <div>
                            <label class="memora-crm-field-label">Tên khách hàng</label>
                            <input type="text" id="memora-detail-name-input" class="memora-crm-form-control" placeholder="Nhập tên khách hàng...">
                        </div>
                        <div>
                            <label class="memora-crm-field-label">Số điện thoại</label>
                            <input type="tel" id="memora-detail-phone-input" class="memora-crm-form-control" placeholder="Nhập số điện thoại...">
                        </div>
                        <div>
                            <label class="memora-crm-field-label">Gói chụp</label>
                            <input type="text" id="memora-detail-pkg-input" class="memora-crm-form-control" placeholder="Nhập gói chụp...">
                        </div>
                        <div>
                            <label class="memora-crm-field-label">Background / Ghi chú</label>
                            <input type="text" id="memora-detail-bg-input" class="memora-crm-form-control" placeholder="Nhập phông nền / ghi chú...">
                        </div>
                        <div class="memora-crm-form-full">
                            <label class="memora-crm-field-label">Tiền cọc / Tổng tiền (VNĐ)</label>
                            <input type="text" inputmode="numeric" id="memora-detail-deposit-input" class="memora-crm-form-control" placeholder="VD: 150000">
                        </div>
                    </div>

                    <div style="text-align:right;margin-bottom:14px;">
                        <button type="button" class="memora-crm-btn-primary" id="memora-crm-btn-save-info" style="font-size:12px;height:32px;min-height:32px;line-height:30px;padding:0 14px;">
                            💾 Lưu thông tin
                        </button>
                    </div>

                    <hr style="margin:14px 0;border:none;border-top:1px dashed #cbd5e1;">

                    <h4 style="margin:0 0 10px;font-size:13px;color:#475569;text-transform:uppercase;font-weight:700;">Điều phối / Đổi giờ / Chuyển phòng</h4>
                    <div class="memora-crm-form-grid">
                        <div>
                            <label class="memora-crm-field-label">Phòng mới</label>
                            <select id="memora-move-room" class="memora-crm-form-control"></select>
                        </div>
                        <div>
                            <label class="memora-crm-field-label">Giờ mới</label>
                            <input type="text" id="memora-move-time" class="memora-crm-form-control" placeholder="10:30">
                        </div>
                    </div>
                </div>
                <div class="memora-crm-modal-footer" style="justify-content:space-between;">
                    <div>
                        <button type="button" class="memora-crm-btn-secondary" id="memora-crm-btn-cancel-order" style="color:#b91c1c;border-color:#fca5a5;">
                            ✕ Hủy đơn
                        </button>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a href="#" target="_blank" class="memora-crm-btn-secondary" id="memora-detail-wc-link">
                            Mở đơn WooCommerce ↗
                        </a>
                        <button type="button" class="memora-crm-btn-primary" id="memora-crm-btn-save-move">
                            Lưu chuyển đổi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Toast Thông báo -->
        <div class="memora-crm-toast-container"></div>
    </div>
    <?php
}
