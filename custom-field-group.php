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
// START - ENQUEUE CSS MARQUEE
//====================================
add_action( 'wp_enqueue_scripts', 'memora_marquee_enqueue_styles' );
function memora_marquee_enqueue_styles() {
    $css = '
    .memora-marquee-wrap {
        overflow: hidden;
        width: 100%;
        position: relative;
        padding: 14px 0;
        box-sizing: border-box;
    }
    .memora-marquee-track {
        display: flex;
        width: max-content;
        will-change: transform;
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
        max-height: 44px;
        width: auto;
        object-fit: contain;
        display: block;
    }
    .memora-marquee-text {
        font-weight: 500;
        white-space: nowrap;
        font-size: 1rem;
        line-height: 1.5;
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
}
//====================================
// END - ENQUEUE CSS MARQUEE
//====================================





//====================================
// START - RENDER VÀ SHORTCODE MARQUEE
//====================================
function memora_render_marquee( $atts = array() ) {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    $atts = shortcode_atts( array(
        'id'        => 0,
        'source'    => '',
        'speed'     => '',
        'direction' => '',
        'gap'       => '',
        'class'     => '',
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
        return '';
    }

    // Các thiết lập hiển thị
    $speed       = ! empty( $atts['speed'] ) ? intval( $atts['speed'] ) : intval( get_field( 'marquee_speed', $data_source ) ?: 25 );
    $gap         = ! empty( $atts['gap'] ) ? intval( $atts['gap'] ) : intval( get_field( 'marquee_gap', $data_source ) ?: 40 );
    $direction   = ! empty( $atts['direction'] ) ? sanitize_text_field( $atts['direction'] ) : ( get_field( 'marquee_direction', $data_source ) ?: 'left' );
    $pause_hover = get_field( 'marquee_pause_hover', $data_source );
    $pause_class = ( $pause_hover !== false && $pause_hover != 0 ) ? 'has-pause-hover' : '';
    $bg_color    = get_field( 'marquee_bg_color', $data_source );
    $text_color  = get_field( 'marquee_text_color', $data_source );

    $unique_id = 'memora-marquee-' . wp_rand( 1000, 9999 );

    // Inline CSS variables & styles
    $container_styles = array();
    if ( ! empty( $bg_color ) ) {
        $container_styles[] = 'background-color: ' . esc_attr( $bg_color );
    }
    if ( ! empty( $text_color ) ) {
        $container_styles[] = 'color: ' . esc_attr( $text_color );
    }
    $container_styles[] = '--marquee-speed: ' . esc_attr( $speed ) . 's';
    $container_styles[] = '--marquee-gap: ' . esc_attr( $gap ) . 'px';
    $container_style_attr = ! empty( $container_styles ) ? ' style="' . implode( '; ', $container_styles ) . '"' : '';

    $direction_class = ( $direction === 'right' ) ? 'direction-right' : 'direction-left';

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
                    <?php foreach ( $items as $item ) :
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
                                ?>
                                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" class="memora-marquee-img" loading="lazy" />
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
