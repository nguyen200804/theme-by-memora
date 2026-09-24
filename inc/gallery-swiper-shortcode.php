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

    /* -- Xác định post ID dùng cho ACF -- */
    $post_id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : get_the_ID();

    /* -------------------------------------------------------
       Lấy ảnh từ ACF Gallery field (ưu tiên hơn ids)
    ------------------------------------------------------- */
    $ids = [];

    if ( ! empty( $atts['acf_gallery'] ) ) {
        if ( function_exists( 'get_field' ) ) {
            $acf_images = get_field( sanitize_key( $atts['acf_gallery'] ), $post_id );

            if ( ! empty( $acf_images ) && is_array( $acf_images ) ) {
                foreach ( $acf_images as $image ) {
                    // ACF trả về array (return format = array) hoặc int (ID)
                    if ( is_array( $image ) && isset( $image['ID'] ) ) {
                        $ids[] = (int) $image['ID'];
                    } elseif ( is_numeric( $image ) ) {
                        $ids[] = (int) $image;
                    }
                }
            }
        } else {
            return '<p style="color:red;">[gallery_swiper] Plugin ACF chưa được kích hoạt.</p>';
        }
    }

    /* -------------------------------------------------------
       Nếu acf_gallery rỗng hoặc không trả về ảnh → dùng ids
    ------------------------------------------------------- */
    if ( empty( $ids ) && ! empty( $atts['ids'] ) ) {
        $ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
    }

    if ( empty( $ids ) ) {
        return '<p style="color:red;">[gallery_swiper] Vui lòng truyền <code>acf_gallery</code> hoặc <code>ids</code>.</p>';
    }

    /* -- Tạo unique ID cho instance -- */
    static $instance = 0;
    $instance++;
    $uid = 'gallery-swiper-' . $instance;

    /* -- Xây dựng danh sách slide -- */
    $slides_html = '';
    foreach ( $ids as $id ) {
        $id  = (int) $id;
        $src = wp_get_attachment_image_url( $id, 'full' );
        $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
        $alt = $alt ? esc_attr( $alt ) : '';

        if ( ! $src ) continue;

        $slides_html .= sprintf(
            '<div class="swiper-slide"><img src="%s" alt="%s" loading="lazy" /></div>',
            esc_url( $src ),
            $alt
        );
    }

    if ( empty( $slides_html ) ) {
        return '<p style="color:red;">[gallery_swiper] Không tìm thấy ảnh hợp lệ.</p>';
    }

    /* -- Tham số JS -- */
    $loop     = ( $atts['loop'] === 'false' || $atts['loop'] === '0' ) ? 'false' : 'true';
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
            border-radius: 12px;
        }

        /* ===== Swiper container ===== */
        #<?php echo esc_attr( $uid ); ?>.memora-gallery-swiper {
            width: 100%;
            height: 100%;
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
            if (typeof Swiper === 'undefined') {
                setTimeout(initGallerySwiper, 300);
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
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------
   2. Enqueue Swiper CSS + JS từ CDN (jsDelivr – Swiper v11)
------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', 'memora_gallery_swiper_assets' );

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
