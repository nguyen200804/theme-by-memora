<?php
/**
 * Nút Liên Hệ Nổi (Floating Contact Button)
 * 
 * Hiển thị nút "Liên hệ" cố định ở góc dưới bên phải màn hình.
 * Khi người dùng nhấp vào nút "Liên hệ":
 * - Mở ra 3 icon mạng xã hội: Gọi điện thoại, Zalo, Instagram kèm nút Đóng '✕'.
 * - Có hiệu ứng chuyển động mượt mà (smooth animation), tự động đóng khi click ra ngoài hoặc bấm ESC.
 * - Cho phép quản trị viên cấu hình Số điện thoại, Link Zalo, Link Instagram trong trang Chỉnh sửa chung (ACF).
 * - Tự động nạp vào wp_footer hoặc có thể gọi qua Shortcode [nut_lien_he].
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Thoát nếu truy cập trực tiếp
}





//====================================
// START - ĐĂNG KÝ ACF FIELD GROUP CHO NÚT LIÊN HỆ
//====================================
add_action( 'acf/init', 'memora_register_contact_button_field_group' );
function memora_register_contact_button_field_group() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'                   => 'group_memora_nut_lien_he',
        'title'                 => 'Nút liên hệ nổi (Góc dưới bên phải)',
        'fields'                => array(
            array(
                'key'           => 'field_contact_button_enable',
                'label'         => 'Bật nút liên hệ nổi',
                'name'          => 'contact_button_enable',
                'type'          => 'true_false',
                'instructions'  => 'Bật hoặc tắt nút liên hệ nổi ở góc dưới bên phải toàn trang.',
                'required'      => 0,
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Bật',
                'ui_off_text'   => 'Tắt',
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'           => 'field_contact_button_label',
                'label'         => 'Chữ hiển thị nút',
                'name'          => 'contact_button_label',
                'type'          => 'text',
                'instructions'  => 'Nội dung chữ hiển thị trên nút ban đầu (mặc định: Liên hệ)',
                'default_value' => 'Liên hệ',
                'placeholder'   => 'Liên hệ',
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'           => 'field_contact_phone_number',
                'label'         => 'Số điện thoại',
                'name'          => 'contact_phone_number',
                'type'          => 'text',
                'instructions'  => 'Nhập số điện thoại gọi khi người dùng bấm vào icon Điện thoại.',
                'default_value' => '0901234567',
                'placeholder'   => '0901234567',
                'wrapper'       => array( 'width' => '33.33' ),
            ),
            array(
                'key'           => 'field_contact_zalo_url',
                'label'         => 'Link hoặc SĐT Zalo',
                'name'          => 'contact_zalo_url',
                'type'          => 'text',
                'instructions'  => 'Nhập số điện thoại Zalo hoặc link https://zalo.me/...',
                'default_value' => 'https://zalo.me/0901234567',
                'placeholder'   => '0901234567 hoặc https://zalo.me/0901234567',
                'wrapper'       => array( 'width' => '33.33' ),
            ),
            array(
                'key'           => 'field_contact_instagram_url',
                'label'         => 'Link Instagram',
                'name'          => 'contact_instagram_url',
                'type'          => 'url',
                'instructions'  => 'Nhập đường dẫn trang Instagram (ví dụ: https://instagram.com/memora)',
                'default_value' => 'https://instagram.com',
                'placeholder'   => 'https://instagram.com/memora',
                'wrapper'       => array( 'width' => '33.34' ),
            ),
            array(
                'key'           => 'field_contact_icon_phone',
                'label'         => 'Icon Điện thoại',
                'name'          => 'contact_icon_phone',
                'type'          => 'image',
                'instructions'  => 'Chọn ảnh icon Điện thoại (để trống sẽ dùng mặc định: /wp-content/uploads/2026/09/icon-phone.png)',
                'return_format' => 'url',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_contact_icon_zalo',
                'label'         => 'Icon Zalo',
                'name'          => 'contact_icon_zalo',
                'type'          => 'image',
                'instructions'  => 'Chọn ảnh icon Zalo (để trống sẽ dùng mặc định: /wp-content/uploads/2026/09/icon-zalo.png)',
                'return_format' => 'url',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_contact_icon_instagram',
                'label'         => 'Icon Instagram',
                'name'          => 'contact_icon_instagram',
                'type'          => 'image',
                'instructions'  => 'Chọn ảnh icon Instagram (để trống sẽ dùng mặc định: /wp-content/uploads/2026/09/icon-instagram.png)',
                'return_format' => 'url',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_contact_icon_close',
                'label'         => 'Icon Đóng (✕)',
                'name'          => 'contact_icon_close',
                'type'          => 'image',
                'instructions'  => 'Chọn ảnh icon Đóng (để trống sẽ dùng mặc định: /wp-content/uploads/2026/09/icon-close.png)',
                'return_format' => 'url',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_contact_icon_size',
                'label'         => 'Kích thước icon liên hệ (px)',
                'name'          => 'contact_icon_size',
                'type'          => 'number',
                'instructions'  => 'Kích thước đường kính cho 3 icon liên hệ (Điện thoại, Zalo, Instagram). Mặc định: 54px',
                'default_value' => 54,
                'min'           => 20,
                'max'           => 150,
                'step'          => 1,
                'append'        => 'px',
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'           => 'field_contact_close_size',
                'label'         => 'Kích thước icon X (px)',
                'name'          => 'contact_close_size',
                'type'          => 'number',
                'instructions'  => 'Kích thước đường kính cho nút icon Đóng (✕). Mặc định: 44px',
                'default_value' => 44,
                'min'           => 15,
                'max'           => 120,
                'step'          => 1,
                'append'        => 'px',
                'wrapper'       => array( 'width' => '50' ),
            ),
        ),
        'location'              => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'chinh_sua_chung',
                ),
            ),
        ),
        'menu_order'            => 10,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Cấu hình nút liên hệ nổi và 3 liên kết mạng xã hội (Phone, Zalo, Instagram)',
    ) );
}
//====================================
// END - ĐĂNG KÝ ACF FIELD GROUP CHO NÚT LIÊN HỆ
//====================================





//====================================
// START - ENQUEUE SCRIPTS VÀ STYLES CHO NÚT LIÊN HỆ
//====================================
add_action( 'wp_enqueue_scripts', 'memora_contact_button_scripts_styles' );
function memora_contact_button_scripts_styles() {
    if ( is_admin() ) {
        return;
    }

    // 1. Google Fonts Cormorant Garamond & Playfair Display
    wp_enqueue_style(
        'memora-contact-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&display=swap',
        array(),
        null
    );

    // 2. CSS Nút liên hệ nổi
    $css = '
    /* --- MEMORA FLOATING CONTACT WIDGET --- */
    .memora-contact-widget {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 999999;
        font-family: "Playfair Display", "Cormorant Garamond", Georgia, serif;
        user-select: none;
        -webkit-user-select: none;
        line-height: 1;
        box-sizing: border-box;
    }

    .memora-contact-widget *,
    .memora-contact-widget *::before,
    .memora-contact-widget *::after {
        box-sizing: border-box;
    }

    .memora-contact-container {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    /* Reset button mặc định */
    .memora-contact-widget button {
        background: transparent;
        border: none;
        outline: none;
        padding: 0;
        margin: 0;
        font-family: inherit;
        cursor: pointer;
    }

    /* 1. Nút "Liên hệ" dạng viên thuốc (Pill Button) */
    .memora-contact-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.3em 1em !important;
        background-color: #fbf7f4 !important;
        color: #733e1c !important;
        font-family: "Playfair Display", "Cormorant Garamond", Georgia, serif !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        letter-spacing: 0.2px;
        border-radius: 9999px !important;
        box-shadow: 0 4px 20px rgba(115, 62, 28, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04);
        transition: all 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
        white-space: nowrap;
        text-decoration: none !important;
    }

    .memora-contact-pill:hover {
        background-color: #ffffff !important;
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 28px rgba(115, 62, 28, 0.18), 0 3px 8px rgba(0, 0, 0, 0.06);
    }

    .memora-contact-pill:active {
        transform: translateY(-1px) scale(0.99);
    }

    /* 2. Nhóm 3 icon mạng xã hội + nút đóng X */
    .memora-contact-socials {
        display: flex;
        align-items: center;
        gap: 14px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: scale(0.85) translateX(25px);
        transition: all 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
        position: absolute;
        right: 0;
    }

    /* Định dạng chung nút tròn (Circle) */
    .memora-contact-circle {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: var(--memora-contact-icon-size, 54px) !important;
        height: var(--memora-contact-icon-size, 54px) !important;
        border-radius: 50% !important;
        background-color: #fbf7f4 !important;
        color: #733e1c !important;
        text-decoration: none !important;
        box-shadow: 0 4px 18px rgba(115, 62, 28, 0.12), 0 2px 5px rgba(0, 0, 0, 0.04);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease, background-color 0.2s ease;
        position: relative;
        flex-shrink: 0;
        outline: none;
    }

    .memora-contact-circle:hover {
        background-color: #ffffff !important;
        transform: translateY(-4px) scale(1.08);
        box-shadow: 0 8px 24px rgba(115, 62, 28, 0.2), 0 3px 8px rgba(0, 0, 0, 0.06);
    }

    .memora-contact-circle:active {
        transform: translateY(-1px) scale(1.02);
    }

    /* Ảnh icon mạng xã hội & nút đóng */
    .memora-contact-icon-img {
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
        pointer-events: none;
        user-select: none;
        border-radius: 50%;
    }

    /* Nút đóng tròn X (kích thước riêng theo cấu hình) */
    .memora-contact-close {
        width: var(--memora-contact-close-size, 44px) !important;
        height: var(--memora-contact-close-size, 44px) !important;
        margin-left: 2px;
        background-color: #fbf7f4 !important;
    }

    .memora-contact-close:hover {
        background-color: #ffffff !important;
        transform: translateY(-3px) rotate(90deg) scale(1.1) !important;
    }

    /* Trạng thái MỞ (.is-open) */
    .memora-contact-widget.is-open .memora-contact-pill {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: scale(0.7) translateX(20px);
    }

    .memora-contact-widget.is-open .memora-contact-socials {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: scale(1) translateX(0);
        position: relative;
    }

    /* Hiệu ứng nảy pop-in từng icon */
    .memora-contact-widget.is-open .memora-contact-circle {
        animation: memoraContactPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
    }
    .memora-contact-widget.is-open .memora-contact-circle:nth-child(1) { animation-delay: 0.03s; }
    .memora-contact-widget.is-open .memora-contact-circle:nth-child(2) { animation-delay: 0.08s; }
    .memora-contact-widget.is-open .memora-contact-circle:nth-child(3) { animation-delay: 0.13s; }
    .memora-contact-widget.is-open .memora-contact-circle:nth-child(4) { animation-delay: 0.18s; }

    @keyframes memoraContactPopIn {
        0% {
            opacity: 0;
            transform: scale(0.35) translateY(15px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }


    ';

    wp_register_style( 'memora-contact-button-style', false );
    wp_enqueue_style( 'memora-contact-button-style' );
    wp_add_inline_style( 'memora-contact-button-style', $css );

    // 3. JavaScript Xử lý Đóng/Mở
    $js = '
    document.addEventListener("DOMContentLoaded", function() {
        var widget = document.getElementById("memoraContactWidget");
        if (!widget) return;

        var trigger = document.getElementById("memoraContactTrigger");
        var closeBtn = document.getElementById("memoraContactClose");

        // Bấm "Liên hệ" để mở 3 icon
        if (trigger) {
            trigger.addEventListener("click", function(e) {
                e.stopPropagation();
                widget.classList.add("is-open");
            });
        }

        // Bấm nút X để đóng lại
        if (closeBtn) {
            closeBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                widget.classList.remove("is-open");
            });
        }

        // Bấm ra ngoài vùng widget để đóng
        document.addEventListener("click", function(e) {
            if (widget.classList.contains("is-open") && !widget.contains(e.target)) {
                widget.classList.remove("is-open");
            }
        });

        // Bấm phím ESC để đóng
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && widget.classList.contains("is-open")) {
                widget.classList.remove("is-open");
            }
        });
    });
    ';

    wp_register_script( 'memora-contact-button-script', '', array(), null, true );
    wp_enqueue_script( 'memora-contact-button-script' );
    wp_add_inline_script( 'memora-contact-button-script', $js );
}
//====================================
// END - ENQUEUE SCRIPTS VÀ STYLES CHO NÚT LIÊN HỆ
//====================================





//====================================
// START - HÀM RENDER NÚT LIÊN HỆ
//====================================
function memora_render_contact_button( $args = array() ) {
    // 1. Kiểm tra trạng thái Bật / Tắt từ cấu hình ACF (mặc định Bật)
    $is_enabled = true;
    if ( function_exists( 'get_field' ) ) {
        $enable_field = get_field( 'contact_button_enable', 'option' );
        if ( $enable_field === false || $enable_field === '0' || $enable_field === 0 ) {
            $is_enabled = false;
        }
    }

    if ( ! $is_enabled && empty( $args['force'] ) ) {
        return '';
    }

    // 2. Lấy nhãn hiển thị nút
    $label = 'Liên hệ';
    if ( ! empty( $args['label'] ) ) {
        $label = $args['label'];
    } elseif ( function_exists( 'get_field' ) && get_field( 'contact_button_label', 'option' ) ) {
        $label = get_field( 'contact_button_label', 'option' );
    }

    // 3. Số điện thoại (Gọi)
    $phone = '0901234567';
    if ( ! empty( $args['phone'] ) ) {
        $phone = $args['phone'];
    } elseif ( function_exists( 'get_field' ) && get_field( 'contact_phone_number', 'option' ) ) {
        $phone = get_field( 'contact_phone_number', 'option' );
    }
    $phone_clean = preg_replace( '/[^0-9+]/', '', $phone );
    $phone_link  = ! empty( $phone_clean ) ? 'tel:' . $phone_clean : 'javascript:void(0);';

    // 4. Link Zalo
    $zalo = 'https://zalo.me/0901234567';
    if ( ! empty( $args['zalo'] ) ) {
        $zalo = $args['zalo'];
    } elseif ( function_exists( 'get_field' ) && get_field( 'contact_zalo_url', 'option' ) ) {
        $zalo = get_field( 'contact_zalo_url', 'option' );
    }
    if ( ! empty( $zalo ) && ! preg_match( '/^https?:\/\//i', $zalo ) ) {
        $zalo_phone = preg_replace( '/[^0-9]/', '', $zalo );
        $zalo_link  = 'https://zalo.me/' . $zalo_phone;
    } else {
        $zalo_link = ! empty( $zalo ) ? $zalo : 'https://zalo.me/';
    }

    // 5. Link Instagram
    $instagram = 'https://instagram.com';
    if ( ! empty( $args['instagram'] ) ) {
        $instagram = $args['instagram'];
    } elseif ( function_exists( 'get_field' ) && get_field( 'contact_instagram_url', 'option' ) ) {
        $instagram = get_field( 'contact_instagram_url', 'option' );
    }
    if ( ! empty( $instagram ) && ! preg_match( '/^https?:\/\//i', $instagram ) ) {
        $instagram = ltrim( $instagram, '@' );
        $instagram_link = 'https://instagram.com/' . $instagram;
    } else {
        $instagram_link = ! empty( $instagram ) ? $instagram : 'https://instagram.com';
    }

    // 6. Hình ảnh các Icon mạng xã hội & nút Đóng
    $get_icon_url = function( $field_name, $default_path ) {
        $custom = function_exists( 'get_field' ) ? get_field( $field_name, 'option' ) : '';
        if ( ! empty( $custom ) ) {
            $url = is_array( $custom ) ? ( ! empty( $custom['url'] ) ? $custom['url'] : '' ) : $custom;
            if ( ! empty( $url ) ) {
                return preg_match( '/^https?:\/\//i', $url ) ? $url : home_url( '/' . ltrim( $url, '/' ) );
            }
        }
        return home_url( '/' . ltrim( $default_path, '/' ) );
    };

    $phone_icon_url     = $get_icon_url( 'contact_icon_phone', '/wp-content/uploads/2026/09/icon-phone.png' );
    $zalo_icon_url      = $get_icon_url( 'contact_icon_zalo', '/wp-content/uploads/2026/09/icon-zalo.png' );
    $instagram_icon_url = $get_icon_url( 'contact_icon_instagram', '/wp-content/uploads/2026/09/icon-instagram.png' );
    $close_icon_url     = $get_icon_url( 'contact_icon_close', '/wp-content/uploads/2026/09/icon-close.png' );

    // 7. Kích thước icon liên hệ và icon X
    $acf_icon_size  = function_exists( 'get_field' ) ? get_field( 'contact_icon_size', 'option' ) : '';
    $icon_size      = ! empty( $args['icon_size'] ) ? intval( $args['icon_size'] ) : ( ! empty( $acf_icon_size ) ? intval( $acf_icon_size ) : 54 );

    $acf_close_size = function_exists( 'get_field' ) ? get_field( 'contact_close_size', 'option' ) : '';
    $close_size     = ! empty( $args['close_size'] ) ? intval( $args['close_size'] ) : ( ! empty( $acf_close_size ) ? intval( $acf_close_size ) : 44 );

    ob_start();
    ?>
    <div class="memora-contact-widget" id="memoraContactWidget" style="--memora-contact-icon-size: <?php echo esc_attr( $icon_size ); ?>px; --memora-contact-close-size: <?php echo esc_attr( $close_size ); ?>px;">
        <div class="memora-contact-container">
            <!-- 1. Nút "Liên hệ" dạng viên thuốc (Collapsed state) -->
            <button type="button" class="memora-contact-pill" id="memoraContactTrigger" aria-label="<?php echo esc_attr( $label ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>

            <!-- 2. Nhóm 3 icon mạng xã hội + nút Đóng (Expanded state) -->
            <div class="memora-contact-socials" id="memoraContactSocials">
                <!-- Nút Gọi điện thoại -->
                <a href="<?php echo esc_url( $phone_link ); ?>" class="memora-contact-circle memora-btn-phone" title="Gọi điện thoại: <?php echo esc_attr( $phone ); ?>" aria-label="Gọi điện thoại">
                    <img src="<?php echo esc_url( $phone_icon_url ); ?>" width="<?php echo esc_attr( $icon_size ); ?>" height="<?php echo esc_attr( $icon_size ); ?>" alt="Điện thoại" class="memora-contact-icon-img" loading="eager" decoding="async" />
                </a>

                <!-- Nút Zalo -->
                <a href="<?php echo esc_url( $zalo_link ); ?>" target="_blank" rel="noopener noreferrer" class="memora-contact-circle memora-btn-zalo" title="Liên hệ qua Zalo" aria-label="Liên hệ qua Zalo">
                    <img src="<?php echo esc_url( $zalo_icon_url ); ?>" width="<?php echo esc_attr( $icon_size ); ?>" height="<?php echo esc_attr( $icon_size ); ?>" alt="Zalo" class="memora-contact-icon-img" loading="eager" decoding="async" />
                </a>

                <!-- Nút Instagram -->
                <a href="<?php echo esc_url( $instagram_link ); ?>" target="_blank" rel="noopener noreferrer" class="memora-contact-circle memora-btn-instagram" title="Xem Instagram" aria-label="Xem Instagram">
                    <img src="<?php echo esc_url( $instagram_icon_url ); ?>" width="<?php echo esc_attr( $icon_size ); ?>" height="<?php echo esc_attr( $icon_size ); ?>" alt="Instagram" class="memora-contact-icon-img" loading="eager" decoding="async" />
                </a>

                <!-- Nút Đóng (✕) -->
                <button type="button" class="memora-contact-circle memora-contact-close" id="memoraContactClose" title="Đóng liên hệ" aria-label="Đóng liên hệ">
                    <img src="<?php echo esc_url( $close_icon_url ); ?>" width="<?php echo esc_attr( $close_size ); ?>" height="<?php echo esc_attr( $close_size ); ?>" alt="Đóng" class="memora-contact-icon-img memora-contact-icon-close" loading="eager" decoding="async" />
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - HÀM RENDER NÚT LIÊN HỆ
//====================================





//====================================
// START - TỰ ĐỘNG CHÈN VÀO WP_FOOTER VÀ ĐĂNG KÝ SHORTCODE
//====================================
add_action( 'wp_footer', 'memora_contact_button_footer_output', 999 );
function memora_contact_button_footer_output() {
    if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    echo memora_render_contact_button();
}

// Đăng ký Shortcode [nut_lien_he]
add_shortcode( 'nut_lien_he', 'memora_contact_button_shortcode' );
add_shortcode( 'memora_contact_button', 'memora_contact_button_shortcode' );
function memora_contact_button_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'label'      => '',
        'phone'      => '',
        'zalo'       => '',
        'instagram'  => '',
        'icon_size'  => '',
        'close_size' => '',
        'force'      => 1,
    ), $atts, 'nut_lien_he' );

    return memora_render_contact_button( $atts );
}
//====================================
// END - TỰ ĐỘNG CHÈN VÀO WP_FOOTER VÀ ĐĂNG KÝ SHORTCODE
//====================================
