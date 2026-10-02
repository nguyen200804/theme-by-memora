<?php
/**
 * Menu Header Drilldown Component
 * 
 * Shortcode: [menu__header]
 * Bắt và xuất động dữ liệu Menu WordPress có ID=5 từ hệ thống (Appearance > Menus).
 * Tích hợp nút bấm 3 gạch ngang (Hamburger Button) chuẩn thanh lịch trên header.
 * Dạng thẻ kính mờ (Frosted Glass) đa tầng (Drill-down Navigation).
 * 
 * Căn chỉnh vị trí chuẩn xác:
 * - Vị trí thẻ menu kính mờ bám đúng mép phải và mép trên của nút 3 gạch ngang (khớp đúng khung chữ nhật lớn đã chỉ định).
 * - Không bị trôi dạt ra mép màn hình khi header đặt trong khung giới hạn (boxed container) của Elementor.
 * - Trạng thái đóng: Hiển thị 3 gạch ngang màu #733e1c.
 * - Trạng thái mở: 3 gạch ẩn đi, thẻ menu xuất hiện trùng khớp vị trí, nút '✕' thay thế vị trí nút 3 gạch.
 * - Kích thước, padding, khoảng cách hoàn toàn bằng đơn vị 'em' giúp responsive co giãn linh hoạt theo font-size.
 * - Viền mỏng màu #cddce8.
 * - Nền kính mờ 85% (rgba(255, 255, 255, 0.85)) kết hợp backdrop-filter blur.
 * - Nút đóng '✕' màu #733e1c.
 * - 2 thanh kẻ phân cách màu #dfb0bf.
 * - Mũi tên '<' căn trái, chữ căn phải phong cách typography Serif sang trọng.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Thoát nếu truy cập trực tiếp
}





//====================================
// START - ENQUEUE SCRIPTS VÀ STYLES CHO MENU HEADER
//====================================
add_action( 'wp_enqueue_scripts', 'memora_menu_header_scripts_styles' );
function memora_menu_header_scripts_styles() {
    // 1. Google Fonts: Cormorant Garamond & Playfair Display
    wp_enqueue_style(
        'memora-menu-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,600&subset=latin,vietnamese&display=swap',
        array(),
        null
    );

    // 2. CSS Menu Header (External file)
    wp_enqueue_style(
        'memora-menu-header-style',
        get_stylesheet_directory_uri() . '/assets/css/menu-header.css',
        array(),
        '1.0.0'
    );

    // 3. JavaScript Drilldown & Modal Controller (External file)
    wp_enqueue_script(
        'memora-menu-header-script',
        get_stylesheet_directory_uri() . '/assets/js/menu-header.js',
        array(),
        '1.0.0',
        true
    );
}
//====================================
// END - ENQUEUE SCRIPTS VÀ STYLES CHO MENU HEADER
//====================================





//====================================
// START - XÂY DỰNG CÂY PHÂN CẤP MENU (MENU TREE HELPER)
//====================================
/**
 * Chuyển mảng phẳng các menu items từ WordPress thành cấu trúc cây đệ quy
 * 
 * @param array $items Danh sách WP_Post menu items từ wp_get_nav_menu_items()
 * @param int   $parent_id ID menu item cha (mặc định 0 cho cấp gốc)
 * @return array Mảng cấu trúc cây phân cấp
 */
function memora_build_nav_menu_tree( array $items, $parent_id = 0 ) {
    $branch = array();
    foreach ( $items as $item ) {
        $item_parent = (int) $item->menu_item_parent;
        if ( $item_parent === (int) $parent_id ) {
            $children = memora_build_nav_menu_tree( $items, $item->ID );
            $item->children = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}
//====================================
// END - XÂY DỰNG CÂY PHÂN CẤP MENU (MENU TREE HELPER)
//====================================





//====================================
// START - RENDER VÀ SHORTCODE MENU HEADER [menu__header]
//====================================
/**
 * Đệ quy sinh HTML các Panels con cho các mục có cấp dưới từ Menu WordPress
 */
function memora_render_submenu_panels( array $items, $parent_panel_id, $unique_id ) {
    $output = '';

    foreach ( $items as $item ) {
        if ( ! empty( $item->children ) ) {
            $panel_dom_id = $unique_id . '-panel-' . $item->ID;

            $output .= '<div class="memora-menu-panel" id="' . esc_attr( $panel_dom_id ) . '" data-panel-id="' . esc_attr( $item->ID ) . '" data-parent-panel="' . esc_attr( $parent_panel_id ) . '">';

            // Nút Back kẹp giữa 2 thanh kẻ hồng phấn (#dfb0bf)
            $output .= '<div class="memora-menu-subhead" data-back-to="' . esc_attr( $parent_panel_id ) . '">';
            $output .= '<span class="memora-menu-arrow">&lt;</span>';
            $output .= '<span class="memora-menu-back-title">' . esc_html( $item->title ) . '</span>';
            $output .= '</div>';

            // Thanh kẻ hồng phấn thứ 2 (dưới tiêu đề Back)
            $output .= '<div class="memora-menu-divider memora-menu-divider--sub"></div>';

            // Danh sách các mục con
            $output .= '<ul class="memora-menu-list">';
            foreach ( $item->children as $child ) {
                $has_sub = ! empty( $child->children );
                $child_target = ! empty( $child->target ) ? ' target="' . esc_attr( $child->target ) . '" rel="noopener noreferrer"' : '';
                $child_classes = ! empty( $child->classes ) && is_array( $child->classes ) ? ' ' . esc_attr( implode( ' ', array_filter( $child->classes ) ) ) : '';

                $output .= '<li class="memora-menu-item' . $child_classes . '">';
                if ( $has_sub ) {
                    $child_panel_dom_id = $unique_id . '-panel-' . $child->ID;
                    $output .= '<button type="button" class="memora-menu-item-btn" data-target-panel="' . esc_attr( $child_panel_dom_id ) . '">';
                    $output .= '<span class="memora-menu-arrow">&lt;</span>';
                    $output .= '<span class="memora-menu-label">' . esc_html( $child->title ) . '</span>';
                    $output .= '</button>';
                } else {
                    $output .= '<a href="' . esc_url( $child->url ) . '"' . $child_target . ' class="memora-menu-item-link">';
                    $output .= '<span class="memora-menu-label">' . esc_html( $child->title ) . '</span>';
                    $output .= '</a>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';

            $output .= '</div>';

            // Đệ quy cho các cấp con sâu hơn
            $output .= memora_render_submenu_panels( $item->children, $panel_dom_id, $unique_id );
        }
    }

    return $output;
}

/**
 * Hàm render shortcode [menu__header]
 * Bắt menu có ID=5 từ WordPress và hiển thị nút 3 gạch ngang (Hamburger)
 */
function memora_render_menu_header_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array(
        'id'        => 5,             // ID menu trong WordPress (mặc định ID=5)
        'toggle'    => 'true',        // Mặc định luôn hiển thị nút 3 gạch ngang
        'inline'    => 'false',       // 'true' nếu muốn hiển thị trực tiếp dạng card không qua popup
        'open'      => 'false',       // 'true' nếu muốn mở sẵn khi tải trang
        'font_size' => '',           // Tùy chỉnh font-size gốc (VD: 22px, 18px), các thông số em sẽ co giãn theo
        'class'     => '',            // Thêm class tùy biến
    ), $atts, 'menu__header' );

    $menu_identifier = ! empty( $atts['id'] ) ? $atts['id'] : 5;

    // Lấy menu từ WordPress theo ID hoặc slug/name
    $menu_obj = wp_get_nav_menu_object( $menu_identifier );
    $raw_items = false;

    if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
        $raw_items = wp_get_nav_menu_items( $menu_obj->term_id );
    } else {
        // Fallback lấy trực tiếp bằng ID số
        $raw_items = wp_get_nav_menu_items( intval( $menu_identifier ) );
    }

    // Nếu không tìm thấy menu hoặc menu chưa có item nào
    if ( empty( $raw_items ) || ! is_array( $raw_items ) ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            return '<div class="memora-menu-not-found" style="color: #733e1c; font-size: 14px; padding: 12px; border: 1.5px dashed #dfb0bf; border-radius: 12px; background: rgba(255,255,255,0.85); font-family: sans-serif;"><strong>[menu__header]</strong>: Không tìm thấy Menu có ID = ' . esc_html( $menu_identifier ) . ' trong hệ thống WordPress (Giao diện > Menu). Vui lòng kiểm tra lại ID menu.</div>';
        }
        return '';
    }

    // Xây dựng cây phân cấp động từ danh sách WordPress menu items
    $menu_tree = memora_build_nav_menu_tree( $raw_items, 0 );

    $unique_id   = 'memora-menu-' . wp_rand( 1000, 9999 );
    $root_dom_id = $unique_id . '-panel-root';

    $is_inline   = ( $atts['inline'] === 'true' );
    $is_open     = ( $atts['open'] === 'true' || $is_inline );
    $show_toggle = ( $atts['toggle'] !== 'false' && ! $is_inline );

    // Style ghi đè font-size nếu có
    $card_style = '';
    if ( ! empty( $atts['font_size'] ) ) {
        $card_style = ' style="--memora-menu-font-base: ' . esc_attr( $atts['font_size'] ) . ';"';
    }

    ob_start();
    ?>
    <div class="memora-menu-header-container <?php echo esc_attr( $atts['class'] ); ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
        
        <?php if ( $show_toggle ) : ?>
            <!-- Nút 3 gạch ngang (Hamburger Icon) -->
            <button type="button" class="memora-menu-hamburger" aria-label="<?php esc_attr_e( 'Mở menu', 'memora' ); ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--1"></span>
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--2"></span>
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--3"></span>
            </button>
        <?php endif; ?>

        <?php if ( ! $is_inline ) : ?>
            <div class="memora-menu-backdrop" data-menu-id="<?php echo esc_attr( $unique_id ); ?>"></div>
        <?php endif; ?>

        <div class="memora-menu-modal-wrapper<?php echo $is_inline ? ' is-inline' : ''; ?><?php echo ( $is_open && ! $is_inline ) ? ' is-open' : ''; ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
            <div class="memora-menu-card"<?php echo $card_style; ?>>
                
                <!-- Thanh điều hướng trên cùng: Nút đóng X (#733e1c) -->
                <div class="memora-menu-topbar">
                    <button type="button" class="memora-menu-close-btn" aria-label="<?php esc_attr_e( 'Close menu', 'memora' ); ?>">✕</button>
                </div>

                <!-- Thanh kẻ hồng phấn thứ 1 (#dfb0bf - dưới nút X) -->
                <div class="memora-menu-divider memora-menu-divider--top"></div>

                <!-- Viewport chứa các Panels chuyển tầng trượt -->
                <div class="memora-menu-viewport">
                    
                    <!-- PANEL GỐC (LEVEL 0) -->
                    <div class="memora-menu-panel is-active" id="<?php echo esc_attr( $root_dom_id ); ?>" data-panel-id="root">
                        <ul class="memora-menu-list">
                            <?php foreach ( $menu_tree as $root_item ) :
                                $has_sub = ! empty( $root_item->children );
                                $target_attr = ! empty( $root_item->target ) ? ' target="' . esc_attr( $root_item->target ) . '" rel="noopener noreferrer"' : '';
                                $item_classes = ! empty( $root_item->classes ) && is_array( $root_item->classes ) ? ' ' . esc_attr( implode( ' ', array_filter( $root_item->classes ) ) ) : '';
                                ?>
                                <li class="memora-menu-item<?php echo $item_classes; ?>">
                                    <?php if ( $has_sub ) :
                                        $target_panel_id = $unique_id . '-panel-' . $root_item->ID;
                                        ?>
                                        <button type="button" class="memora-menu-item-btn" data-target-panel="<?php echo esc_attr( $target_panel_id ); ?>">
                                            <span class="memora-menu-arrow">&lt;</span>
                                            <span class="memora-menu-label"><?php echo esc_html( $root_item->title ); ?></span>
                                        </button>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $root_item->url ); ?>"<?php echo $target_attr; ?> class="memora-menu-item-link">
                                            <span class="memora-menu-label"><?php echo esc_html( $root_item->title ); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- CÁC PANELS CON (LEVEL 1, LEVEL 2,...) SINH HOÀN TOÀN ĐỘNG TỪ WORDPRESS MENU -->
                    <?php echo memora_render_submenu_panels( $menu_tree, $root_dom_id, $unique_id ); ?>

                </div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'menu__header', 'memora_render_menu_header_shortcode' );
//====================================
// END - RENDER VÀ SHORTCODE MENU HEADER [menu__header]
//====================================
