<?php
/**
 * Custom Field Group: Marquee
 * 
 * Định nghĩa Field Group "Marquee" sử dụng Advanced Custom Fields (ACF).
 * Cho phép quản trị viên thêm tùy ý số lượng phần tử (Repeater), mỗi phần tử có thể chọn là Text hoặc Image.
 * Gán vào trang cấu hình: /wp-admin/admin.php?page=chinh_sua_chung
 * Kèm Shortcode [marquee] và hàm helper để render ra giao diện.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Thoát nếu truy cập trực tiếp
}





//====================================
// START - ĐĂNG KÝ OPTIONS PAGE CHỈNH SỬA CHUNG
//====================================
add_action( 'acf/init', 'memora_register_chinh_sua_chung_options_page' );
function memora_register_chinh_sua_chung_options_page() {
    if ( function_exists( 'acf_add_options_page' ) ) {
        if ( ! function_exists( 'acf_get_options_page' ) || ! acf_get_options_page( 'chinh_sua_chung' ) ) {
            acf_add_options_page( array(
                'page_title' => __( 'Chỉnh sửa chung', 'memora' ),
                'menu_title' => __( 'Chỉnh sửa chung', 'memora' ),
                'menu_slug'  => 'chinh_sua_chung',
                'capability' => 'manage_options',
                'icon_url'   => 'dashicons-admin-generic',
                'position'   => 30,
                'redirect'   => false,
            ) );
        }
    }
}
//====================================
// END - ĐĂNG KÝ OPTIONS PAGE CHỈNH SỬA CHUNG
//====================================





//====================================
// START - ĐĂNG KÝ FIELD GROUP MARQUEE
//====================================
add_action( 'acf/init', 'memora_register_marquee_field_group' );
function memora_register_marquee_field_group() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'                   => 'group_marquee',
        'title'                 => 'Marquee',
        'fields'                => array(
            // --- TAB 1: DANH SÁCH PHẦN TỬ ---
            array(
                'key'   => 'field_tab_marquee_items',
                'label' => 'Danh sách phần tử',
                'name'  => '',
                'type'  => 'tab',
            ),
            array(
                'key'          => 'field_marquee_items',
                'label'        => 'Các phần tử Marquee',
                'name'         => 'marquee_items',
                'type'         => 'repeater',
                'instructions' => 'Thêm các phần tử văn bản hoặc hình ảnh vào dải Marquee. Có thể thêm bao nhiêu phần tử tùy ý và kéo thả để sắp xếp.',
                'required'     => 0,
                'layout'       => 'block',
                'button_label' => '+ Thêm phần tử Marquee',
                'sub_fields'   => array(
                    array(
                        'key'           => 'field_marquee_item_type',
                        'label'         => 'Loại phần tử',
                        'name'          => 'item_type',
                        'type'          => 'radio',
                        'instructions'  => 'Chọn định dạng nội dung hiển thị',
                        'required'      => 1,
                        'choices'       => array(
                            'text'  => 'Văn bản (Text)',
                            'image' => 'Hình ảnh (Image)',
                        ),
                        'default_value' => 'text',
                        'layout'        => 'horizontal',
                        'return_format' => 'value',
                    ),
                    array(
                        'key'               => 'field_marquee_item_text',
                        'label'             => 'Nội dung văn bản',
                        'name'              => 'item_text',
                        'type'              => 'text',
                        'instructions'      => 'Nhập nội dung chữ cần chạy trong Marquee',
                        'required'          => 0,
                        'placeholder'       => 'Nhập text tại đây (ví dụ: Ưu đãi độc quyền hôm nay ⭐)',
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field'    => 'field_marquee_item_type',
                                    'operator' => '==',
                                    'value'    => 'text',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'key'               => 'field_marquee_item_image',
                        'label'             => 'Hình ảnh',
                        'name'              => 'item_image',
                        'type'              => 'image',
                        'instructions'      => 'Chọn hoặc tải ảnh lên (Logo đối tác, Icon, Huy hiệu...)',
                        'required'          => 0,
                        'return_format'     => 'array',
                        'preview_size'      => 'medium',
                        'library'           => 'all',
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field'    => 'field_marquee_item_type',
                                    'operator' => '==',
                                    'value'    => 'image',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'key'               => 'field_marquee_item_image_height',
                        'label'             => 'Chiều cao riêng của ảnh (px)',
                        'name'              => 'item_image_height',
                        'type'              => 'number',
                        'instructions'      => 'Tùy chọn: Đặt chiều cao riêng cho ảnh này (để trống nếu dùng kích thước chung).',
                        'required'          => 0,
                        'placeholder'       => 'Ví dụ: 44',
                        'append'            => 'px',
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field'    => 'field_marquee_item_type',
                                    'operator' => '==',
                                    'value'    => 'image',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'key'          => 'field_marquee_item_link',
                        'label'        => 'Đường dẫn liên kết (Tùy chọn)',
                        'name'         => 'item_link',
                        'type'         => 'url',
                        'instructions' => 'Nhập link nếu muốn bấm vào phần tử để mở trang web khác',
                        'required'     => 0,
                        'placeholder'  => 'https://example.com',
                    ),
                    array(
                        'key'           => 'field_marquee_item_target',
                        'label'         => 'Mở trong tab mới',
                        'name'          => 'item_target',
                        'type'          => 'true_false',
                        'message'       => 'Mở liên kết trong tab mới (_blank)',
                        'default_value' => 0,
                        'ui'            => 1,
                    ),
                ),
            ),

            // --- TAB 2: CÀI ĐẶT HIỂN THỊ ---
            array(
                'key'   => 'field_tab_marquee_settings',
                'label' => 'Cài đặt hiển thị',
                'name'  => '',
                'type'  => 'tab',
            ),
            array(
                'key'           => 'field_marquee_speed',
                'label'         => 'Tốc độ chạy (giây)',
                'name'          => 'marquee_speed',
                'type'          => 'number',
                'instructions'  => 'Thời gian hoàn thành một lượt chạy (số càng nhỏ thì chạy càng nhanh). Mặc định: 25s',
                'default_value' => 25,
                'min'           => 5,
                'max'           => 120,
                'step'          => 1,
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_marquee_gap',
                'label'         => 'Khoảng cách giữa các phần tử (px)',
                'name'          => 'marquee_gap',
                'type'          => 'number',
                'instructions'  => 'Khoảng cách ngang giữa các item. Mặc định: 40px',
                'default_value' => 40,
                'min'           => 5,
                'max'           => 200,
                'step'          => 5,
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_marquee_direction',
                'label'         => 'Hướng chạy',
                'name'          => 'marquee_direction',
                'type'          => 'select',
                'choices'       => array(
                    'left'  => 'Phải sang Trái (Mặc định)',
                    'right' => 'Trái sang Phải',
                ),
                'default_value' => 'left',
                'wrapper'       => array( 'width' => '34' ),
            ),
            array(
                'key'           => 'field_marquee_pause_hover',
                'label'         => 'Dừng khi di chuột',
                'name'          => 'marquee_pause_hover',
                'type'          => 'true_false',
                'message'       => 'Tự động tạm dừng chạy khi người dùng rê chuột qua',
                'default_value' => 1,
                'ui'            => 1,
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'          => 'field_marquee_bg_color',
                'label'        => 'Màu nền dải Marquee',
                'name'         => 'marquee_bg_color',
                'type'         => 'color_picker',
                'default_value'=> '',
                'wrapper'      => array( 'width' => '33' ),
            ),
            array(
                'key'          => 'field_marquee_text_color',
                'label'        => 'Màu chữ',
                'name'         => 'marquee_text_color',
                'type'         => 'color_picker',
                'default_value'=> '',
                'wrapper'      => array( 'width' => '34' ),
            ),
            array(
                'key'           => 'field_marquee_font_family',
                'label'         => 'Font chữ',
                'name'          => 'marquee_font_family',
                'type'          => 'text',
                'instructions'  => 'Nhập tên font chữ (ví dụ: Playfair Display, Cormorant Garamond, Montserrat, Dancing Script... hoặc để trống để dùng mặc định của theme).',
                'default_value' => '',
                'placeholder'   => 'Ví dụ: Playfair Display',
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_marquee_font_size',
                'label'         => 'Kích thước chữ (px)',
                'name'          => 'marquee_font_size',
                'type'          => 'number',
                'instructions'  => 'Cỡ chữ cho nội dung văn bản. Mặc định: 16px',
                'default_value' => 16,
                'min'           => 10,
                'max'           => 100,
                'step'          => 1,
                'append'        => 'px',
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_marquee_image_height',
                'label'         => 'Kích thước hình ảnh (px)',
                'name'          => 'marquee_image_height',
                'type'          => 'number',
                'instructions'  => 'Chiều cao hiển thị của hình ảnh/logo. Mặc định: 44px',
                'default_value' => 44,
                'min'           => 15,
                'max'           => 200,
                'step'          => 1,
                'append'        => 'px',
                'wrapper'       => array( 'width' => '34' ),
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
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Cấu hình dải Marquee (Text & Ảnh không giới hạn)',
    ) );
}
//====================================
// END - ĐĂNG KÝ FIELD GROUP MARQUEE
//====================================





//====================================
// START - ENQUEUE CSS VÀ JS MARQUEE
//====================================
add_action( 'wp_enqueue_scripts', 'memora_marquee_enqueue_assets' );
function memora_marquee_enqueue_assets() {
    // Tải Google Fonts hỗ trợ các font chữ nghệ thuật/sang trọng
    wp_enqueue_style(
        'memora-marquee-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,600&family=Dancing+Script:wght@600;700&family=Montserrat:wght@400;500;600;700&family=Playball&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
        array(),
        null
    );

    $css = '
    .memora-marquee-wrap {
        overflow: hidden;
        width: 100%;
        position: relative;
        padding: 14px 0;
        box-sizing: border-box;
        line-height: normal;
        font-family: inherit;
    }
    .memora-marquee-track {
        display: flex;
        align-items: center;
        width: max-content;
        will-change: transform;
        user-select: none;
    }
    .memora-marquee-content {
        display: flex;
        align-items: center;
        gap: var(--marquee-gap, 40px);
        padding-right: var(--marquee-gap, 40px);
        flex-shrink: 0;
        animation: memora-scroll-left var(--marquee-speed, 25s) linear infinite;
    }
    .memora-marquee-wrap.direction-right .memora-marquee-content {
        animation-name: memora-scroll-right;
    }
    .memora-marquee-wrap.has-pause-hover:hover .memora-marquee-content {
        animation-play-state: paused;
    }
    .memora-marquee-item {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .memora-marquee-link {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        color: inherit;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .memora-marquee-link:hover {
        opacity: 0.8;
    }
    .memora-marquee-img {
        height: var(--marquee-img-height, 44px);
        max-height: var(--marquee-img-height, 44px);
        width: auto;
        object-fit: contain;
        display: block;
        vertical-align: middle;
    }
    .memora-marquee-text {
        font-weight: 500;
        white-space: nowrap;
        font-size: var(--marquee-font-size, 1rem);
        line-height: 1.5;
        letter-spacing: 0.02em;
        font-family: inherit;
    }
    @keyframes memora-scroll-left {
        from { transform: translateX(0); }
        to { transform: translateX(-100%); }
    }
    @keyframes memora-scroll-right {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }';

    wp_register_style( 'memora-marquee-style', false );
    wp_enqueue_style( 'memora-marquee-style' );
    wp_add_inline_style( 'memora-marquee-style', $css );

    // Script hỗ trợ tự động clone nếu màn hình quá rộng (màn hình 2K, 4K)
    $js = "
    (function() {
        function checkAndFillMarquees() {
            var marquees = document.querySelectorAll('.memora-marquee-wrap');
            marquees.forEach(function(wrap) {
                var contents = wrap.querySelectorAll('.memora-marquee-content');
                if (contents.length < 2) return;
                var content1 = contents[0];
                var content2 = contents[1];
                var wrapWidth = wrap.clientWidth || window.innerWidth;
                if (wrapWidth > 0 && content1.offsetWidth < wrapWidth * 1.2) {
                    var times = Math.ceil((wrapWidth * 1.5) / (content1.offsetWidth || 1));
                    if (times > 1) {
                        var originalItems = Array.from(content1.children);
                        for (var i = 1; i < times; i++) {
                            originalItems.forEach(function(item) {
                                content1.appendChild(item.cloneNode(true));
                                content2.appendChild(item.cloneNode(true));
                            });
                        }
                    }
                }
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkAndFillMarquees);
        } else {
            checkAndFillMarquees();
        }
        window.addEventListener('resize', checkAndFillMarquees);
    })();
    ";
    wp_register_script( 'memora-marquee-script', '', array(), false, true );
    wp_enqueue_script( 'memora-marquee-script' );
    wp_add_inline_script( 'memora-marquee-script', $js );
}
//====================================
// END - ENQUEUE CSS VÀ JS MARQUEE
//====================================





//====================================
// START - RENDER VÀ SHORTCODE MARQUEE
//====================================
function memora_render_marquee( $atts = array() ) {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    $atts = shortcode_atts( array(
        'id'          => 0,
        'source'      => '',
        'speed'       => '',
        'direction'   => '',
        'gap'         => '',
        'pause'       => '',
        'bg'          => '',
        'color'       => '',
        'font'        => '',
        'font_family' => '',
        'size'         => '',
        'font_size'    => '',
        'img_size'     => '',
        'img_height'   => '',
        'image_height' => '',
        'class'        => '',
    ), $atts, 'marquee' );

    // Nguồn dữ liệu: Mặc định lấy từ trang "Chỉnh sửa chung" ('option')
    $data_source = 'option';
    if ( ! empty( $atts['id'] ) ) {
        $data_source = intval( $atts['id'] );
    }

    $items = get_field( 'marquee_items', $data_source );

    // Nếu lấy theo ID bài viết cụ thể mà không có, tự động chuyển về 'option'
    if ( empty( $items ) && $data_source !== 'option' ) {
        $items = get_field( 'marquee_items', 'option' );
        $data_source = 'option';
    }

    if ( empty( $items ) || ! is_array( $items ) ) {
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) && isset( $_GET['elementor-preview'] ) ) {
            return '<div style="padding: 12px; background: #fff3cd; color: #856404; font-size: 13px; text-align: center; border: 1px dashed #ffeeba;">[memora_marquee]: Chưa có phần tử nào. Vui lòng thêm phần tử trong WP Admin &gt; Chỉnh sửa chung &gt; Marquee.</div>';
        }
        return '';
    }

    // Các thiết lập hiển thị (ưu tiên shortcode atts > ACF option > giá trị mặc định)
    $acf_speed = get_field( 'marquee_speed', $data_source );
    $speed     = ! empty( $atts['speed'] ) ? intval( $atts['speed'] ) : ( ! empty( $acf_speed ) ? intval( $acf_speed ) : 25 );

    $acf_gap   = get_field( 'marquee_gap', $data_source );
    $gap       = ( $atts['gap'] !== '' ) ? intval( $atts['gap'] ) : ( ( $acf_gap !== '' && $acf_gap !== false && $acf_gap !== null ) ? intval( $acf_gap ) : 40 );

    $acf_dir   = get_field( 'marquee_direction', $data_source );
    $direction = ! empty( $atts['direction'] ) ? sanitize_text_field( $atts['direction'] ) : ( ! empty( $acf_dir ) ? $acf_dir : 'left' );

    // Pause on hover
    $acf_pause = get_field( 'marquee_pause_hover', $data_source );
    $should_pause = ( $acf_pause === null || $acf_pause === '' || $acf_pause == 1 || $acf_pause === true );
    if ( $atts['pause'] !== '' ) {
        $should_pause = ! in_array( strtolower( $atts['pause'] ), array( '0', 'false', 'no' ), true );
    }
    $pause_class = $should_pause ? 'has-pause-hover' : '';

    // Màu nền và màu chữ
    $bg_color   = ! empty( $atts['bg'] ) ? sanitize_text_field( $atts['bg'] ) : get_field( 'marquee_bg_color', $data_source );
    $text_color = ! empty( $atts['color'] ) ? sanitize_text_field( $atts['color'] ) : get_field( 'marquee_text_color', $data_source );

    // Font chữ, Kích thước chữ và Kích thước hình ảnh
    $font_input  = ! empty( $atts['font'] ) ? $atts['font'] : ( ! empty( $atts['font_family'] ) ? $atts['font_family'] : get_field( 'marquee_font_family', $data_source ) );
    $size_input  = ( $atts['size'] !== '' ) ? $atts['size'] : ( ( $atts['font_size'] !== '' ) ? $atts['font_size'] : get_field( 'marquee_font_size', $data_source ) );
    $acf_img_h   = get_field( 'marquee_image_height', $data_source );
    $img_h_input = ( $atts['img_height'] !== '' ) ? $atts['img_height'] : ( ( $atts['image_height'] !== '' ) ? $atts['image_height'] : ( ( $atts['img_size'] !== '' ) ? $atts['img_size'] : ( ! empty( $acf_img_h ) ? $acf_img_h : 44 ) ) );

    $unique_id = 'memora-marquee-' . wp_rand( 1000, 9999 );

    // Inline CSS variables & styles
    $container_styles = array();
    if ( ! empty( $bg_color ) ) {
        $container_styles[] = 'background-color: ' . esc_attr( $bg_color );
    }
    if ( ! empty( $text_color ) ) {
        $container_styles[] = 'color: ' . esc_attr( $text_color );
    }
    if ( ! empty( $font_input ) && strtolower( trim( $font_input ) ) !== 'inherit' ) {
        $clean_font = trim( $font_input );
        $quoted_font = ( strpos( $clean_font, ' ' ) !== false && strpos( $clean_font, '"' ) === false && strpos( $clean_font, "'" ) === false && strpos( $clean_font, ',' ) === false ) ? ( "'" . $clean_font . "', sans-serif" ) : $clean_font;
        $container_styles[] = 'font-family: ' . esc_attr( $quoted_font );

        // Tự động nạp Google Font nếu người dùng nhập tên font từ Google Fonts
        $first_font = trim( explode( ',', $clean_font )[0], " '\"" );
        $system_fonts = array( 'inherit', 'initial', 'unset', 'arial', 'helvetica', 'times new roman', 'times', 'georgia', 'courier new', 'courier', 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy' );
        if ( ! empty( $first_font ) && ! in_array( strtolower( $first_font ), $system_fonts, true ) ) {
            $font_handle = 'memora-custom-gf-' . sanitize_title( $first_font );
            if ( ! wp_style_is( $font_handle, 'enqueued' ) ) {
                $gf_url = 'https://fonts.googleapis.com/css2?family=' . urlencode( $first_font ) . ':ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&display=swap';
                wp_enqueue_style( $font_handle, $gf_url, array(), null );
            }
        }
    }
    if ( ! empty( $size_input ) ) {
        $size_val = is_numeric( $size_input ) ? ( intval( $size_input ) . 'px' ) : esc_attr( $size_input );
        $container_styles[] = '--marquee-font-size: ' . $size_val;
        $container_styles[] = 'font-size: ' . $size_val;
    }
    if ( ! empty( $img_h_input ) ) {
        $img_h_val = is_numeric( $img_h_input ) ? ( intval( $img_h_input ) . 'px' ) : esc_attr( $img_h_input );
        $container_styles[] = '--marquee-img-height: ' . $img_h_val;
    }
    $container_styles[] = '--marquee-speed: ' . esc_attr( $speed ) . 's';
    $container_styles[] = '--marquee-gap: ' . esc_attr( $gap ) . 'px';
    $container_style_attr = ! empty( $container_styles ) ? ' style="' . implode( '; ', $container_styles ) . '"' : '';

    $direction_class = ( $direction === 'right' ) ? 'direction-right' : 'direction-left';

    // ĐẢM BẢO ĐỦ SỐ LƯỢNG PHẦN TỬ ĐỂ LẤP ĐẦY MÀN HÌNH VÀ CUỘN VÔ TẬN MƯỢT MÀ:
    // Nếu ít hơn 16 items (ví dụ người dùng chỉ nhập 1 hoặc vài phần tử ngắn),
    // tự động lặp lại danh sách để mỗi khối content có tối thiểu 16 items (độ rộng >= 2000px).
    $min_target_items = 16;
    $count            = count( $items );
    $render_items     = $items;
    if ( $count > 0 && $count < $min_target_items ) {
        $multiplier   = (int) ceil( $min_target_items / $count );
        $render_items = array();
        for ( $m = 0; $m < $multiplier; $m++ ) {
            foreach ( $items as $it ) {
                $render_items[] = $it;
            }
        }
    }

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $unique_id ); ?>" class="memora-marquee-wrap <?php echo esc_attr( $direction_class . ' ' . $pause_class . ' ' . $atts['class'] ); ?>"<?php echo $container_style_attr; ?>>
        <div class="memora-marquee-track">
            <?php
            // Lặp lại 2 lần liên tiếp để tạo hiệu ứng cuộn mượt không ngắt quãng (infinite seamless loop)
            for ( $loop = 0; $loop < 2; $loop++ ) :
                $aria_hidden = ( $loop === 1 ) ? ' aria-hidden="true"' : '';
                ?>
                <div class="memora-marquee-content"<?php echo $aria_hidden; ?>>
                    <?php foreach ( $render_items as $item ) :
                        $type   = isset( $item['item_type'] ) ? $item['item_type'] : 'text';
                        $link   = ! empty( $item['item_link'] ) ? esc_url( $item['item_link'] ) : '';
                        $target = ! empty( $item['item_target'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
                        ?>
                        <div class="memora-marquee-item memora-marquee-item--<?php echo esc_attr( $type ); ?>">
                            <?php if ( $link ) : ?><a href="<?php echo $link; ?>"<?php echo $target; ?> class="memora-marquee-link"><?php endif; ?>

                            <?php if ( $type === 'image' && ! empty( $item['item_image'] ) ) :
                                $img = $item['item_image'];
                                $img_url = is_array( $img ) ? $img['url'] : $img;
                                $img_alt = is_array( $img ) && ! empty( $img['alt'] ) ? $img['alt'] : 'Marquee Image';
                                $item_h = ! empty( $item['item_image_height'] ) ? intval( $item['item_image_height'] ) . 'px' : '';
                                $img_style = $item_h ? ' style="height:' . esc_attr( $item_h ) . '; max-height:' . esc_attr( $item_h ) . ';"' : '';
                                ?>
                                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" class="memora-marquee-img" decoding="async"<?php echo $img_style; ?> />
                            <?php elseif ( $type === 'text' && ! empty( $item['item_text'] ) ) : ?>
                                <span class="memora-marquee-text"><?php echo esc_html( $item['item_text'] ); ?></span>
                            <?php endif; ?>

                            <?php if ( $link ) : ?></a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'marquee', 'memora_render_marquee' );
add_shortcode( 'memora_marquee', 'memora_render_marquee' );
//====================================
// END - RENDER VÀ SHORTCODE MARQUEE
//====================================
