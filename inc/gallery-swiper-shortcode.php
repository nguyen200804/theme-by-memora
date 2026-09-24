<?php
/**
 * Shortcode [gallery_swiper]
 *
 * Tạo slider ảnh dạng Swiper với pagination hình ngôi sao.
 *
 * Cách dùng:
 *   Dùng ACF Gallery field:
 *     [gallery_swiper acf_gallery="ten_field"]
 *     [gallery_swiper acf_gallery="ten_field" post_id="42"]
 *
 *   Dùng attachment IDs thủ công:
 *     [gallery_swiper ids="10,11,12" speed="600" autoplay="3000"]
 *
 * Tham số (attrs):
 *   acf_gallery     – Tên ACF Gallery field (ưu tiên hơn ids nếu cả hai được truyền)
 *   post_id         – ID bài viết để lấy ACF field (mặc định: post hiện tại)
 *   ids             – Danh sách attachment ID cách nhau bởi dấu phẩy
 *   speed           – Tốc độ chuyển slide (ms), mặc định 600
 *   autoplay        – Thời gian tự động chuyển (ms), mặc định 4000 (0 = tắt)
 *   loop            – true/false, mặc định true
 *   effect          – slide | fade | cube | coverflow | flip, mặc định "slide"
 *   slides_per_view – Số slide hiển thị cùng lúc, mặc định 1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
   0. Hàm hỗ trợ tìm đúng Post ID (đặc biệt khi đang sửa Elementor Template)
------------------------------------------------------------------ */
if ( ! function_exists( 'memora_resolve_gallery_post_id' ) ) {
    function memora_resolve_gallery_post_id( $post_id = 0, $field_candidates = [] ) {
        $resolved_id = ! empty( $post_id ) ? (int) $post_id : 0;

        // 1. Kiểm tra Elementor Preview post nếu đang trong editor hoặc preview
        if ( ! $resolved_id && class_exists( '\Elementor\Plugin' ) ) {
            // Elementor Pro Theme Builder Preview
            if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
                try {
                    $tb_preview = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_preview();
                    if ( $tb_preview && method_exists( $tb_preview, 'get_preview_id' ) ) {
                        $pid = (int) $tb_preview->get_preview_id();
                        if ( $pid > 0 ) {
                            $resolved_id = $pid;
                        }
                    }
                } catch ( \Throwable $e ) {}
            }

            // Elementor Document Preview ID
            if ( ! $resolved_id && isset( \Elementor\Plugin::$instance->documents ) ) {
                $doc = \Elementor\Plugin::$instance->documents->get_current();
                if ( $doc ) {
                    $pid = (int) $doc->get_settings( 'preview_id' );
                    if ( $pid > 0 ) {
                        $resolved_id = $pid;
                    }
                }
            }
        }

        // 2. Lấy post hiện tại từ WordPress
        if ( ! $resolved_id ) {
            $resolved_id = get_the_ID();
        }
        if ( ! $resolved_id ) {
            $resolved_id = get_queried_object_id();
        }

        // 3. Nếu post_id là Elementor Library Template (ví dụ post 130),
        // hoặc nếu post_id hiện tại không có dữ liệu field ACF trong môi trường editor/admin,
        // tự động fallback sang bài viết mới nhất của post type 'dia-chi' để preview trực quan!
        $is_elementor_editor = class_exists( '\Elementor\Plugin' ) && (
            \Elementor\Plugin::$instance->editor->is_edit_mode() ||
            \Elementor\Plugin::$instance->preview->is_preview_mode() ||
            isset( $_GET['elementor-preview'] ) ||
            ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' )
        );

        $is_template = $resolved_id && get_post_type( $resolved_id ) === 'elementor_library';

        if ( $is_template || $is_elementor_editor ) {
            $has_field = false;
            if ( $resolved_id && function_exists( 'get_field' ) && ! empty( $field_candidates ) ) {
                foreach ( $field_candidates as $f ) {
                    if ( ! empty( get_field( $f, $resolved_id ) ) ) {
                        $has_field = true;
                        break;
                    }
                }
            }

            if ( ! $has_field ) {
                $sample_posts = get_posts( [
                    'post_type'      => 'dia-chi',
                    'posts_per_page' => 1,
                    'post_status'    => 'any',
                    'fields'         => 'ids',
                ] );
                if ( ! empty( $sample_posts ) ) {
                    $resolved_id = (int) $sample_posts[0];
                }
            }
        }

        return (int) $resolved_id;
    }
}

/* ------------------------------------------------------------------
   1. Đăng ký shortcode
------------------------------------------------------------------ */
add_shortcode( 'gallery_swiper', 'memora_gallery_swiper_shortcode' );

function memora_gallery_swiper_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'acf_gallery'     => '',
        'post_id'         => '',
        'ids'             => '',
        'speed'           => 600,
        'autoplay'        => 4000,
        'loop'            => 'true',
        'effect'          => 'slide',
        'slides_per_view' => 1,
    ], $atts, 'gallery_swiper' );

    /* -------------------------------------------------------
       Lấy ảnh từ ACF Gallery field (hoặc ids)
    ------------------------------------------------------- */
    $slides_data = []; // Mỗi phần tử: ['src' => '...', 'alt' => '...']

    if ( ! empty( $atts['acf_gallery'] ) ) {
        if ( function_exists( 'get_field' ) ) {
            $raw_field = trim( $atts['acf_gallery'] );
            // Thử cả 2 dạng: có gạch ngang và có gạch dưới
            $field_candidates = array_unique( array_filter( [
                $raw_field,
                str_replace( '-', '_', $raw_field ),
                str_replace( '_', '-', $raw_field ),
                sanitize_key( $raw_field ),
            ] ) );

            $post_id = memora_resolve_gallery_post_id( $atts['post_id'], $field_candidates );

            $acf_images = null;
            foreach ( $field_candidates as $candidate ) {
                $val = get_field( $candidate, $post_id );
                if ( ! empty( $val ) ) {
                    $acf_images = $val;
                    break;
                }
            }

            if ( ! empty( $acf_images ) ) {
                if ( is_array( $acf_images ) ) {
                    foreach ( $acf_images as $img ) {
                        if ( is_array( $img ) ) {
                            // ACF Return Format = Image Array
                            $img_id = ! empty( $img['ID'] ) ? (int) $img['ID'] : ( ! empty( $img['id'] ) ? (int) $img['id'] : 0 );
                            $src    = ! empty( $img['url'] ) ? $img['url'] : '';
                            $alt    = ! empty( $img['alt'] ) ? $img['alt'] : ( ! empty( $img['title'] ) ? $img['title'] : '' );

                            if ( ! $src && $img_id ) {
                                $src = wp_get_attachment_image_url( $img_id, 'full' );
                            }
                            if ( $src ) {
                                $slides_data[] = [ 'src' => $src, 'alt' => $alt ];
                            }
                        } elseif ( is_numeric( $img ) ) {
                            // ACF Return Format = Image ID
                            $img_id = (int) $img;
                            $src    = wp_get_attachment_image_url( $img_id, 'full' );
                            $alt    = (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true );
                            if ( $src ) {
                                $slides_data[] = [ 'src' => $src, 'alt' => $alt ];
                            }
                        } elseif ( is_string( $img ) && ! empty( $img ) ) {
                            // ACF Return Format = Image URL
                            $slides_data[] = [ 'src' => $img, 'alt' => '' ];
                        }
                    }
                } elseif ( is_string( $acf_images ) && ! empty( $acf_images ) ) {
                    $slides_data[] = [ 'src' => $acf_images, 'alt' => '' ];
                }
            }
        } else {
            return '<p style="color:red; font-size:14px; padding:10px; background:#fff2f2; border:1px solid #fecaca; border-radius:4px;">[gallery_swiper] Plugin ACF chưa được kích hoạt.</p>';
        }
    }

    /* -------------------------------------------------------
       Nếu acf_gallery rỗng hoặc không có ảnh → dùng fallback ids
    ------------------------------------------------------- */
    if ( empty( $slides_data ) && ! empty( $atts['ids'] ) ) {
        $raw_ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
        foreach ( $raw_ids as $img_id ) {
            $src = wp_get_attachment_image_url( $img_id, 'full' );
            $alt = (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true );
            if ( $src ) {
                $slides_data[] = [ 'src' => $src, 'alt' => $alt ];
            }
        }
    }

    if ( empty( $slides_data ) ) {
        return '<p style="color:#d9534f; font-size:14px; padding:10px 14px; background:#fff2f2; border:1px solid #fecaca; border-radius:4px;">[gallery_swiper] Vui lòng truyền <code>acf_gallery</code> hoặc <code>ids</code> hợp lệ.</p>';
    }

    /* -- Tạo unique ID cho instance -- */
    static $instance = 0;
    $instance++;
    $uid = 'gallery-swiper-' . $instance;

    /* -- Xây dựng danh sách slide -- */
    $slides_html = '';
    foreach ( $slides_data as $slide ) {
        $slides_html .= sprintf(
            '<div class="swiper-slide"><img src="%s" alt="%s" loading="lazy" /></div>',
            esc_url( $slide['src'] ),
            esc_attr( $slide['alt'] )
        );
    }

    $total_slides = count( $slides_data );

    /* -- Tham số JS -- */
    $loop     = ( $atts['loop'] === 'false' || $atts['loop'] === '0' || $total_slides < 2 ) ? 'false' : 'true';
    $speed    = (int) $atts['speed'];
    $autoplay = (int) $atts['autoplay'];
    $effect   = esc_js( $atts['effect'] );
    $spv      = (float) $atts['slides_per_view'];

    $autoplay_js = $autoplay > 0
        ? "autoplay: { delay: {$autoplay}, disableOnInteraction: false },"
        : '';

    /* -- SVG ngôi sao encode cho CSS mask -- */
    $star_mask = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='m19.555 23.411-6.664-3.285a1.26 1.26 0 0 0-1.202.045l.006-.003-6.416 3.75a.61.61 0 0 1-.902-.626v.003l.994-7.542q.01-.075.011-.162c0-.364-.155-.691-.403-.92l-.001-.001-4.571-4.247a1.265 1.265 0 0 1 .648-2.17l.007-.001 5.987-1.108c.421-.078.765-.355.935-.727l.003-.008L10.478.746a1.272 1.272 0 0 1 2.271-.087l.003.007 2.881 5.471c.197.365.558.62.981.666h.006l6.045.681a1.265 1.265 0 0 1 .811 2.119l.001-.001-4.27 4.562a1.25 1.25 0 0 0-.315 1.116l-.001-.008 1.52 7.453q.014.061.015.134a.61.61 0 0 1-.875.549z'/%3E%3C/svg%3E\") no-repeat center / contain";

    ob_start();
    ?>
    <div class="memora-gallery-swiper-wrap" id="<?php echo esc_attr( $uid ); ?>-wrap">

        <div class="swiper memora-gallery-swiper" id="<?php echo esc_attr( $uid ); ?>">
            <div class="swiper-wrapper">
                <?php echo $slides_html; ?>
            </div>

            <!-- Navigation arrows -->
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>

            <!-- Star pagination -->
            <div class="swiper-pagination memora-star-pagination" id="<?php echo esc_attr( $uid ); ?>-pagination"></div>
        </div>

    </div>

    <style>
        /* ===== Wrapper ===== */
        #<?php echo esc_attr( $uid ); ?>-wrap {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        /* ===== Swiper container ===== */
        #<?php echo esc_attr( $uid ); ?>.memora-gallery-swiper {
            width: 100%;
        }

        /* ===== Slides ===== */
        #<?php echo esc_attr( $uid ); ?> .swiper-slide img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        /* ===== Arrows ===== */
        #<?php echo esc_attr( $uid ); ?> .swiper-button-prev,
        #<?php echo esc_attr( $uid ); ?> .swiper-button-next {
            color: #fff;
            background: rgba(115, 62, 28, 0.55);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: background 0.25s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .swiper-button-prev:hover,
        #<?php echo esc_attr( $uid ); ?> .swiper-button-next:hover {
            background: rgba(115, 62, 28, 0.9);
        }
        #<?php echo esc_attr( $uid ); ?> .swiper-button-prev::after,
        #<?php echo esc_attr( $uid ); ?> .swiper-button-next::after {
            font-size: 14px;
            font-weight: 700;
        }

        /* ===== Star Pagination ===== */
        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination {
            bottom: 14px;
        }

        /* Ẩn background tròn mặc định của Swiper bullet */
        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet {
            width: 20px;
            height: 20px;
            background: transparent !important;
            opacity: 1;
            margin: 0 4px;
            border-radius: 0;
            position: relative;
        }

        /* Hiển thị ngôi sao qua CSS mask */
        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet::after {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(115, 62, 28, 0.35);
            -webkit-mask: <?php echo $star_mask; ?>;
                    mask: <?php echo $star_mask; ?>;
            transition: background-color 0.25s ease, transform 0.25s ease;
        }

        /* Bullet đang active */
        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet-active::after {
            background-color: #733e1c;
            transform: scale(1.3);
        }
    </style>

    <script>
    (function () {
        'use strict';

        var SWIPER_ID = '<?php echo esc_js( $uid ); ?>';

        function initGallerySwiper() {
            var el = document.getElementById(SWIPER_ID);
            if (!el) return;
            if (el.swiper) return;

            if (typeof Swiper === 'undefined') {
                setTimeout(initGallerySwiper, 250);
                return;
            }

            new Swiper('#' + SWIPER_ID, {
                effect    : '<?php echo $effect; ?>',
                speed     : <?php echo $speed; ?>,
                loop      : <?php echo $loop; ?>,
                slidesPerView: <?php echo $spv; ?>,
                <?php echo $autoplay_js; ?>
                pagination: {
                    el      : '#' + SWIPER_ID + '-pagination',
                    clickable: true,
                },
                navigation: {
                    prevEl: '#' + SWIPER_ID + ' .swiper-button-prev',
                    nextEl: '#' + SWIPER_ID + ' .swiper-button-next',
                },
                a11y: {
                    prevSlideMessage: 'Slide trước',
                    nextSlideMessage: 'Slide tiếp theo',
                },
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGallerySwiper);
        } else {
            initGallerySwiper();
        }

        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                setTimeout(initGallerySwiper, 100);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------
   2. Enqueue Swiper CSS + JS từ CDN (jsDelivr – Swiper v11)
------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', 'memora_gallery_swiper_assets' );
add_action( 'elementor/editor/after_enqueue_scripts', 'memora_gallery_swiper_assets' );
add_action( 'elementor/frontend/after_enqueue_scripts', 'memora_gallery_swiper_assets' );

function memora_gallery_swiper_assets() {
    wp_enqueue_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11'
    );
    wp_enqueue_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11',
        true
    );
}
