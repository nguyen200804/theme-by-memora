<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'hello-elementor','hello-elementor-theme-style','hello-elementor-header-footer' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION
add_filter('use_block_editor_for_post', '__return_false', 10);

// Nạp file cấu hình Custom Field Group (Marquee, etc.)
if ( file_exists( get_stylesheet_directory() . '/custom-field-group.php' ) ) {
    require_once get_stylesheet_directory() . '/custom-field-group.php';
}

// Nạp file cấu hình Menu Header Drilldown Shortcode [menu__header]
if ( file_exists( get_stylesheet_directory() . '/menu-header.php' ) ) {
    require_once get_stylesheet_directory() . '/menu-header.php';
}

// Nạp file cấu hình Hệ thống Đặt lịch chụp ảnh Memora Booking
if ( file_exists( get_stylesheet_directory() . '/inc/booking-system.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/booking-system.php';
}

// Nạp Shortcode [gallery_swiper] – Slider ảnh với pagination ngôi sao
if ( file_exists( get_stylesheet_directory() . '/inc/gallery-swiper-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/gallery-swiper-shortcode.php';
}

// Nạp Shortcode [dia_chi_noi_bat] – Giới thiệu địa chỉ nổi bật
if ( file_exists( get_stylesheet_directory() . '/inc/dia-chi-noi-bat-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/dia-chi-noi-bat-shortcode.php';
}

// Nạp Shortcode [danh_sach_phong] – Danh sách phòng chụp ảnh của địa chỉ
if ( file_exists( get_stylesheet_directory() . '/inc/danh-sach-phong-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/danh-sach-phong-shortcode.php';
}

// Nạp Shortcode [thong_tin_phong_swiper] – Slider ảnh phòng chụp kèm mô tả WYSIWYG
if ( file_exists( get_stylesheet_directory() . '/inc/thong-tin-phong-swiper-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/thong-tin-phong-swiper-shortcode.php';
}

// Nạp tính năng Nút liên hệ nổi ở góc dưới bên phải [nut_lien_he]
if ( file_exists( get_stylesheet_directory() . '/inc/nut-lien-he.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/nut-lien-he.php';
}

// Nạp tích hợp WooCommerce Checkout native + Booking (pre-fill, cart, link order)
if ( file_exists( get_stylesheet_directory() . '/inc/wc-checkout-booking.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/wc-checkout-booking.php';
}



/**
 * Force-load tất cả Elementor Custom Fonts (@font-face) vào wp_head.
 * Elementor chỉ load font khi widget Elementor trên trang dùng font đó.
 * Hook này đảm bảo font luôn available cho PHP shortcodes.
 */
add_action( 'wp_head', 'memora_force_elementor_custom_fonts', 1 );
function memora_force_elementor_custom_fonts() {
    // Chỉ chạy ở frontend
    if ( is_admin() ) return;
    // Kiểm tra Elementor Custom Fonts post type tồn tại
    if ( ! post_type_exists( 'elementor_font' ) ) return;

    $font_posts = get_posts( [
        'post_type'      => 'elementor_font',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ] );

    if ( empty( $font_posts ) ) return;

    $format_map = [
        'woff2' => 'woff2',
        'woff'  => 'woff',
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
        'eot'   => 'embedded-opentype',
        'svg'   => 'svg',
    ];

    $css = '';

    foreach ( $font_posts as $font_post ) {
        $family = $font_post->post_title;

        // Lấy font file attachments (Elementor lưu file là children của font post)
        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'post_parent'    => $font_post->ID,
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ] );

        // Fallback: đọc từ meta (một số phiên bản Elementor lưu theo cách khác)
        if ( empty( $attachments ) ) {
            $meta_all = get_post_meta( $font_post->ID );
            foreach ( $meta_all as $mk => $mv ) {
                $val = maybe_unserialize( $mv[0] );
                if ( ! is_array( $val ) ) continue;
                // Tìm attachment ID trong mảng meta
                foreach ( $val as $face ) {
                    if ( ! is_array( $face ) ) continue;
                    foreach ( $face as $k => $v ) {
                        if ( is_numeric( $v ) && (int) $v > 0 ) {
                            $url = wp_get_attachment_url( (int) $v );
                            if ( $url ) {
                                $ext     = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
                                $fmt     = $format_map[ $ext ] ?? $ext;
                                $weight  = ! empty( $face['font_weight'] ) ? $face['font_weight'] : '400';
                                $style   = ! empty( $face['font_style'] )  ? $face['font_style']  : 'normal';
                                $css .= "@font-face{font-family:'" . esc_attr( $family ) . "';font-weight:{$weight};font-style:{$style};src:url('" . esc_url( $url ) . "') format('{$fmt}');}\n";
                            }
                        }
                    }
                }
            }
            continue; // Đã xử lý qua meta
        }

        // Build @font-face từ child attachments
        $sources = [];
        foreach ( $attachments as $att ) {
            $url = wp_get_attachment_url( $att->ID );
            if ( ! $url ) continue;
            $ext     = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
            $fmt     = $format_map[ $ext ] ?? $ext;
            $sources[] = "url('" . esc_url( $url ) . "') format('" . esc_attr( $fmt ) . "')";
        }

        if ( empty( $sources ) ) continue;

        $css .= "@font-face{font-family:'" . esc_attr( $family ) . "';font-weight:400;font-style:normal;src:" . implode( ',', $sources ) . ";}\n";
    }

    if ( $css ) {
        echo "\n<style id=\"memora-elementor-custom-fonts\">\n" . $css . "</style>\n";
    }
}


// Force desktop layout on all devices by setting viewport width to 1200px on small screens, and standard viewport on large screens
function tocfl_force_desktop_viewport($html) {
    // Remove all existing viewport meta tags to avoid duplicates or overrides
    $html = preg_replace('/<meta\s+name=["\']viewport["\'][^>]*>/i', '', $html);
    
    $target_width = 600;
    if ( isset($_SERVER['REQUEST_URI']) ) {
        $path = strtok($_SERVER['REQUEST_URI'], '?');
        if ( preg_match( '#^/paper_download/?$#', $path ) ) {
            $target_width = 600;
        }
    }
    
    // Insert the dynamic desktop viewport script right after <head>
    $dynamic_viewport_script = '
<script type="text/javascript">
(function() {
    var screenWidth = window.screen.width;
    var content = (screenWidth < ' . $target_width . ') ? "width=' . $target_width . '" : "width=device-width, initial-scale=1.0";
    document.write(\'<meta name="viewport" content="\' + content + \'">\');
})();
</script>
';
    if (stripos($html, '<head>') !== false) {
        $html = str_ireplace('<head>', '<head>' . $dynamic_viewport_script, $html);
    } elseif (stripos($html, '<head') !== false) {
        $html = preg_replace('/(<head[^>]*>)/i', '$1' . $dynamic_viewport_script, $html);
    }
    return $html;
}

function tocfl_start_buffer() {
    ob_start('tocfl_force_desktop_viewport');
}
add_action('template_redirect', 'tocfl_start_buffer', 1);




/**
 * Fix taxonomy 'dia-chi' (tạo bởi SCF) hiển thị trong Elementor Archive conditions.
 * Elementor dùng get_taxonomies(['show_in_nav_menus' => true]) để build danh sách.
 * Hook này chạy sau SCF (priority 999) để đảm bảo các args cần thiết đúng.
 */
add_action( 'init', 'memora_fix_dia_chi_taxonomy_for_elementor', 999 );
function memora_fix_dia_chi_taxonomy_for_elementor() {
    global $wp_taxonomies;

    if ( ! isset( $wp_taxonomies['dia-chi'] ) ) {
        return;
    }

    // Đảm bảo taxonomy xuất hiện trong Elementor Archive conditions
    $wp_taxonomies['dia-chi']->public            = true;
    $wp_taxonomies['dia-chi']->publicly_queryable = true;
    $wp_taxonomies['dia-chi']->show_in_nav_menus = true;
    $wp_taxonomies['dia-chi']->show_ui           = true;
}