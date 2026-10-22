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
 * 1. ĐĂNG KÝ MENU QUẢN TRỊ TRONG WORDPRESS
 */
add_action( 'admin_menu', 'memora_crm_register_admin_menu' );
function memora_crm_register_admin_menu() {
    $capability = 'manage_woocommerce';
    if ( ! current_user_can( $capability ) && current_user_can( 'edit_shop_orders' ) ) {
        $capability = 'edit_shop_orders';
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
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:30px;text-align:center;background:#fff;border-radius:8px;">Vui lòng đăng nhập để truy cập bàn làm việc CRM Lịch Chụp.</div>';
    }

    $can_access = current_user_can( 'manage_woocommerce' ) 
               || current_user_can( 'edit_shop_orders' ) 
               || current_user_can( 'manage_options' );
    $can_access = apply_filters( 'memora_crm_user_can_access', $can_access, get_current_user_id() );

    if ( ! $can_access ) {
        return '<div style="padding:30px;text-align:center;background:#fff;border-radius:8px;color:#c62828;">Bạn không có quyền truy cập vào CRM này.</div>';
    }

    // Nạp assets cho frontend
    memora_crm_enqueue_assets( true );

    ob_start();
    memora_crm_render_view();
    return ob_get_clean();
}

/**
 * 3. ENQUEUE SCRIPTS VÀ STYLES CHO CRM
 */
add_action( 'admin_enqueue_scripts', 'memora_crm_admin_enqueue_assets' );
function memora_crm_admin_enqueue_assets( $hook ) {
    if ( strpos( $hook, 'memora-booking-crm' ) === false ) {
        return;
    }
    memora_crm_enqueue_assets();
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
            <div class="memora-crm-toolbar__left">
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

                <!-- Bộ chọn chi nhánh / cơ sở -->
                <select id="memora-crm-location" class="memora-crm-select">
                    <option value="0">Tất cả chi nhánh</option>
                </select>

                <!-- Bộ chọn lọc phòng chụp -->
                <select id="memora-crm-room-filter" class="memora-crm-select" title="Lọc theo phòng chụp">
                    <option value="0">Tất cả phòng</option>
                </select>

                <!-- Bộ chọn bước nhảy slot -->
                <select id="memora-crm-interval" class="memora-crm-select" title="Bước nhảy khung giờ">
                    <option value="5" selected>Slot: 5 phút</option>
                    <option value="10">Slot: 10 phút</option>
                    <option value="15">Slot: 15 phút</option>
                </select>

                <!-- Ô tìm kiếm nhanh khách hàng -->
                <input type="text" id="memora-crm-search" class="memora-crm-search-input" placeholder="🔍 Tìm tên, SĐT...">
            </div>

            <div class="memora-crm-toolbar__right">
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
                <button type="button" class="memora-crm-btn-secondary" id="memora-crm-btn-print" title="In bảng lịch">
                    🖨️ In
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
                                <input type="number" id="memora-quick-deposit" class="memora-crm-form-control" placeholder="0">
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
                    <div class="memora-crm-detail-grid">
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Mã đặt lịch:</span>
                            <strong id="memora-detail-code" style="color:#733e1c;font-size:15px;">#XXXX</strong>
                        </div>
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Khách hàng:</span>
                            <strong id="memora-detail-name">...</strong>
                        </div>
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Số điện thoại:</span>
                            <strong id="memora-detail-phone">...</strong>
                        </div>
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Liên hệ khác (IG/Zalo):</span>
                            <span id="memora-detail-ig">...</span>
                        </div>
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Gói chụp:</span>
                            <span id="memora-detail-pkg">...</span>
                        </div>
                        <div class="memora-crm-detail-row">
                            <span class="lbl">Tiền cọc / Tổng:</span>
                            <span id="memora-detail-price">...</span>
                        </div>
                    </div>

                    <hr style="margin:16px 0;border:none;border-top:1px dashed #cbd5e1;">

                    <h4 style="margin:0 0 10px;font-size:13px;color:#475569;text-transform:uppercase;">Điều phối / Đổi giờ / Chuyển phòng</h4>
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
