<?php
/**
 * Shortcode [dia_chi_noi_bat]
 *
 * Hiển thị layout 2 cột giới thiệu địa chỉ nổi bật:
 *   - Cột trái : featured image + nhãn "Memora" + tên cơ sở (post title)
 *   - Cột phải : slider ảnh (ACF Gallery "cac-hinh-anh-cua-dia-chi") với
 *                pagination hình ngôi sao màu #733e1c
 *
 * Nguồn dữ liệu:
 *   ACF Post Object field "dia_chi_co_so_noi_bat" → post type "dia-chi"
 *   (có thể đặt trên Options Page hoặc bất kỳ bài viết nào)
 *
 * Cách dùng:
 *   [dia_chi_noi_bat]                        ← lấy từ post/page hiện tại
 *   [dia_chi_noi_bat post_id="option"]       ← lấy từ ACF Options Page
 *   [dia_chi_noi_bat post_id="42"]           ← lấy từ bài viết ID 42
 *
 * Tham số:
 *   post_id  – ID bài viết chứa field "dia_chi_co_so_noi_bat"
 *              Dùng "option" nếu field nằm trên Options Page
 *              Mặc định: post/page hiện tại
 *   autoplay – ms tự chạy slide, 0 = tắt. Mặc định 4000
 *   speed    – ms chuyển slide. Mặc định 600
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
   1. Đăng ký shortcode
------------------------------------------------------------------ */
add_shortcode( 'dia_chi_noi_bat', 'memora_dia_chi_noi_bat_shortcode' );

function memora_dia_chi_noi_bat_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'post_id'  => '',
        'autoplay' => 4000,
        'speed'    => 600,
    ], $atts, 'dia_chi_noi_bat' );

    /* -- Kiểm tra ACF -- */
    if ( ! function_exists( 'get_field' ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Plugin ACF chưa được kích hoạt.</p>';
    }

    /* -- Xác định nguồn lấy field -- */
    if ( $atts['post_id'] === 'option' || $atts['post_id'] === 'options' ) {
        $source_id = 'option';
    } elseif ( ! empty( $atts['post_id'] ) ) {
        $source_id = (int) $atts['post_id'];
    } else {
        $source_id = get_the_ID();
    }

    /* -- Lấy Post Object từ ACF -- */
    $dia_chi_post = get_field( 'dia_chi_co_so_noi_bat', $source_id );

    if ( empty( $dia_chi_post ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Chưa có địa chỉ nổi bật được chọn.</p>';
    }

    /* -- Lấy thông tin từ post dia-chi -- */
    $dc_id    = is_object( $dia_chi_post ) ? $dia_chi_post->ID : (int) $dia_chi_post;
    $dc_title = get_the_title( $dc_id );

    /* Featured image (ảnh thumbnail trái) */
    $thumb_id  = get_post_thumbnail_id( $dc_id );
    $thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    $thumb_alt = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : $dc_title;

    /* ACF Gallery slider bên phải */
    $gallery = get_field( 'cac-hinh-anh-cua-dia-chi', $dc_id );

    /* -- Unique ID cho Swiper instance -- */
    static $dcnb_instance = 0;
    $dcnb_instance++;
    $uid = 'dcnb-swiper-' . $dcnb_instance;

    /* -- Build slides HTML -- */
    $slides_html = '';
    if ( ! empty( $gallery ) && is_array( $gallery ) ) {
        foreach ( $gallery as $img ) {
            if ( is_array( $img ) && isset( $img['url'] ) ) {
                $src = esc_url( $img['url'] );
                $alt = esc_attr( isset( $img['alt'] ) ? $img['alt'] : $dc_title );
            } elseif ( is_numeric( $img ) ) {
                $src = esc_url( (string) wp_get_attachment_image_url( (int) $img, 'full' ) );
                $alt = esc_attr( (string) get_post_meta( (int) $img, '_wp_attachment_image_alt', true ) );
            } else {
                continue;
            }
            $slides_html .= '<div class="swiper-slide"><img src="' . $src . '" alt="' . $alt . '" loading="lazy" /></div>';
        }
    }

    /* Nếu gallery rỗng, dùng featured image làm slide đơn */
    if ( empty( $slides_html ) && $thumb_src ) {
        $slides_html = '<div class="swiper-slide"><img src="' . esc_url( $thumb_src ) . '" alt="' . esc_attr( (string) $thumb_alt ) . '" /></div>';
    }

    if ( empty( $slides_html ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Không tìm thấy ảnh nào cho địa chỉ này.</p>';
    }

    /* -- Tham số JS -- */
    $autoplay = (int) $atts['autoplay'];
    $speed    = (int) $atts['speed'];
    $autoplay_js = $autoplay > 0
        ? "autoplay: { delay: {$autoplay}, disableOnInteraction: false },"
        : '';

    /* -- CSS mask ngôi sao -- */
    $star_mask = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='m19.555 23.411-6.664-3.285a1.26 1.26 0 0 0-1.202.045l.006-.003-6.416 3.75a.61.61 0 0 1-.902-.626v.003l.994-7.542q.01-.075.011-.162c0-.364-.155-.691-.403-.92l-.001-.001-4.571-4.247a1.265 1.265 0 0 1 .648-2.17l.007-.001 5.987-1.108c.421-.078.765-.355.935-.727l.003-.008L10.478.746a1.272 1.272 0 0 1 2.271-.087l.003.007 2.881 5.471c.197.365.558.62.981.666h.006l6.045.681a1.265 1.265 0 0 1 .811 2.119l.001-.001-4.27 4.562a1.25 1.25 0 0 0-.315 1.116l-.001-.008 1.52 7.453q.014.061.015.134a.61.61 0 0 1-.875.549z'/%3E%3C/svg%3E\") no-repeat center / contain";

    ob_start();
    ?>
    <div class="dcnb-wrap" id="<?php echo esc_attr( $uid ); ?>-wrap">

        <!-- ===== CỘT TRÁI ===== -->
        <div class="dcnb-info">

            <div class="dcnb-brand-label">Memora<br><em>"Angel Shot"</em></div>

            <?php if ( $thumb_src ) : ?>
            <div class="dcnb-thumbnail">
                <img src="<?php echo esc_url( $thumb_src ); ?>"
                     alt="<?php echo esc_attr( (string) $thumb_alt ); ?>"
                     loading="lazy" />
            </div>
            <?php endif; ?>

            <div class="dcnb-address">
                <svg class="dcnb-pin-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
                <span><?php echo esc_html( $dc_title ); ?></span>
            </div>

        </div>

        <!-- ===== CỘT PHẢI: SLIDER ===== -->
        <div class="dcnb-slider-col">
            <div class="swiper dcnb-swiper" id="<?php echo esc_attr( $uid ); ?>">
                <div class="swiper-wrapper">
                    <?php echo $slides_html; ?>
                </div>
                <!-- Star pagination -->
                <div class="swiper-pagination dcnb-star-pagination" id="<?php echo esc_attr( $uid ); ?>-pagination"></div>
            </div>
        </div>

    </div>

    <style>
        /* ===== Layout wrapper ===== */
        #<?php echo esc_attr( $uid ); ?>-wrap.dcnb-wrap {
            display: flex;
            align-items: stretch;
            gap: 0;
            width: 100%;
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }

        /* ===== Cột trái ===== */
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-info {
            flex: 0 0 200px;
            width: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 28px 16px;
            background: #fff;
        }

        /* Nhãn thương hiệu */
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-brand-label {
            font-family: 'Dancing Script', 'Pacifico', cursive, serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #733e1c;
            text-align: center;
            line-height: 1.3;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-brand-label em {
            font-style: italic;
            font-size: 1.05rem;
        }

        /* Ảnh thumbnail */
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-thumbnail {
            width: 110px;
            height: 110px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Địa chỉ */
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-address {
            display: flex;
            align-items: flex-start;
            gap: 5px;
            font-size: 0.82rem;
            color: #555;
            text-align: left;
            line-height: 1.45;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-pin-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 2px;
            color: #733e1c;
        }

        /* ===== Cột phải – slider ===== */
        #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-slider-col {
            flex: 1 1 0;
            min-width: 0;
            position: relative;
        }
        #<?php echo esc_attr( $uid ); ?>.dcnb-swiper {
            width: 100%;
            height: 100%;
            min-height: 260px;
        }
        #<?php echo esc_attr( $uid ); ?> .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* ===== Star Pagination ===== */
        #<?php echo esc_attr( $uid ); ?> .dcnb-star-pagination {
            bottom: 14px;
            right: 14px;
            left: auto;
            width: auto;
            display: flex;
            justify-content: flex-end;
        }
        #<?php echo esc_attr( $uid ); ?> .dcnb-star-pagination .swiper-pagination-bullet {
            width: 20px;
            height: 20px;
            background: transparent !important;
            opacity: 1;
            margin: 0 3px;
            border-radius: 0;
            position: relative;
        }
        #<?php echo esc_attr( $uid ); ?> .dcnb-star-pagination .swiper-pagination-bullet::after {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(115, 62, 28, 0.35);
            -webkit-mask: <?php echo $star_mask; ?>;
                    mask: <?php echo $star_mask; ?>;
            transition: background-color 0.25s ease, transform 0.25s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .dcnb-star-pagination .swiper-pagination-bullet-active::after {
            background-color: #733e1c;
            transform: scale(1.3);
        }

        /* ===== Responsive ===== */
        @media (max-width: 600px) {
            #<?php echo esc_attr( $uid ); ?>-wrap.dcnb-wrap {
                flex-direction: column;
            }
            #<?php echo esc_attr( $uid ); ?>-wrap .dcnb-info {
                flex: none;
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                padding: 16px;
            }
            #<?php echo esc_attr( $uid ); ?>.dcnb-swiper {
                min-height: 220px;
            }
        }
    </style>

    <script>
    (function () {
        'use strict';
        var UID = '<?php echo esc_js( $uid ); ?>';

        function initDcnbSwiper() {
            if (typeof Swiper === 'undefined') {
                setTimeout(initDcnbSwiper, 300);
                return;
            }
            new Swiper('#' + UID, {
                loop        : true,
                speed       : <?php echo $speed; ?>,
                <?php echo $autoplay_js; ?>
                pagination  : {
                    el       : '#' + UID + '-pagination',
                    clickable: true,
                },
                a11y: {
                    prevSlideMessage: 'Ảnh trước',
                    nextSlideMessage: 'Ảnh tiếp theo',
                },
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDcnbSwiper);
        } else {
            initDcnbSwiper();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
