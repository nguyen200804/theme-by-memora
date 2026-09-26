<?php
/**
 * Shortcode [thong_tin_phong_swiper]
 *
 * Hiển thị slider ảnh phòng chụp kèm mô tả WYSIWYG bên dưới từ ACF Repeater:
 * cac_hinh_anh_phong_chup (Repeater)
 *  |_ anh-phong-chup (Image)
 *  |_ mo_ta_hinh_anh_phong_chup (WYSIWYG Editor)
 *
 * Thiết kế chuẩn theo mockup:
 *  - Khung ảnh poster 1:1 góc thẳng (bỏ bo góc)
 *  - Phân trang hình ngôi sao màu nâu #733e1c
 *  - Nút điều hướng tròn màu nâu #733e1c ở 2 bên ảnh
 *  - Đoạn mô tả WYSIWYG bên dưới tự động đồng bộ theo từng slide ảnh
 *
 * Cách dùng:
 *   [thong_tin_phong_swiper]
 *   [thong_tin_phong_swiper post_id="42"]
 *   [thong_tin_phong_swiper aspect_ratio="1/1"]
 *   [thong_tin_phong_swiper autoplay="4000" speed="600"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'thong_tin_phong_swiper', 'memora_thong_tin_phong_swiper_shortcode' );

function memora_thong_tin_phong_swiper_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'post_id'      => '',
        'repeater'     => 'cac_hinh_anh_phong_chup',
        'aspect_ratio' => '1/1', // 1/1, 4/3, 16/9, auto
        'speed'        => 600,
        'autoplay'     => 4000,
        'loop'         => 'true',
        'effect'       => 'slide',
    ], $atts, 'thong_tin_phong_swiper' );

    // Enqueue Swiper assets
    if ( function_exists( 'memora_gallery_swiper_assets' ) ) {
        memora_gallery_swiper_assets();
    }

    /* ----------------------------------------------------------
       1. Xác định Post ID chính xác (chuẩn cả Frontend & Elementor Editor)
    ---------------------------------------------------------- */
    $repeater_candidates = array_unique( array_filter( [
        $atts['repeater'],
        str_replace( '-', '_', $atts['repeater'] ),
        str_replace( '_', '-', $atts['repeater'] ),
    ] ) );

    if ( function_exists( 'memora_resolve_gallery_post_id' ) ) {
        $post_id = memora_resolve_gallery_post_id( $atts['post_id'], $repeater_candidates );
    } else {
        $post_id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : get_the_ID();
        if ( ! $post_id ) $post_id = get_queried_object_id();
    }

    // Nếu đang trong Elementor Template editor của phong-chup-anh, tự động tìm bài mẫu
    $is_elementor_editor = class_exists( '\Elementor\Plugin' ) && (
        ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) ||
        ( isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) ||
        isset( $_GET['elementor-preview'] ) ||
        ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' )
    );

    if ( ( ! $post_id || get_post_type( $post_id ) === 'elementor_library' ) && $is_elementor_editor ) {
        $sample_posts = get_posts( [
            'post_type'      => 'phong-chup-anh',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );

        if ( ! empty( $sample_posts ) ) {
            $post_id = (int) $sample_posts[0];
            foreach ( $sample_posts as $sp_id ) {
                foreach ( $repeater_candidates as $rc ) {
                    if ( function_exists( 'get_field' ) && ! empty( get_field( $rc, $sp_id ) ) ) {
                        $post_id = (int) $sp_id;
                        break 2;
                    }
                }
            }
        }
    }

    if ( ! function_exists( 'get_field' ) ) {
        return '<p style="color:red; font-size:14px; padding:10px; background:#fff2f2; border:1px solid #fecaca; border-radius:4px;">[thong_tin_phong_swiper] Plugin ACF/SCF chưa được kích hoạt.</p>';
    }

    /* ----------------------------------------------------------
       2. Lấy dữ liệu Repeater cac_hinh_anh_phong_chup
    ---------------------------------------------------------- */
    $raw_rows = null;
    foreach ( $repeater_candidates as $rc ) {
        $val = get_field( $rc, $post_id );
        if ( ! empty( $val ) && is_array( $val ) ) {
            $raw_rows = $val;
            break;
        }
    }

    // Đọc trực tiếp từ postmeta nếu get_field rỗng
    if ( empty( $raw_rows ) && $post_id ) {
        foreach ( $repeater_candidates as $rc ) {
            $meta_v = get_post_meta( $post_id, $rc, true );
            if ( ! empty( $meta_v ) ) {
                $raw_rows = maybe_unserialize( $meta_v );
                break;
            }
        }
    }

    // Fallback quét tất cả ACF field của bài viết nếu tên repeater có chút khác biệt
    if ( empty( $raw_rows ) && $post_id && function_exists( 'get_field_objects' ) ) {
        $f_objects = get_field_objects( $post_id );
        if ( ! empty( $f_objects ) && is_array( $f_objects ) ) {
            foreach ( $f_objects as $f_obj ) {
                if ( isset( $f_obj['type'] ) && $f_obj['type'] === 'repeater' && ! empty( $f_obj['value'] ) ) {
                    $raw_rows = $f_obj['value'];
                    break;
                }
            }
        }
    }

    if ( empty( $raw_rows ) || ! is_array( $raw_rows ) ) {
        if ( current_user_can( 'edit_posts' ) ) {
            return sprintf(
                '<p style="color:#733e1c; font-size:14px; padding:12px 16px; background:#faf6f2; border:1px solid #e8ded4; border-radius:4px;">[thong_tin_phong_swiper] Chưa có hình ảnh hoặc mô tả nào trong repeater <code>%s</code> cho post #%d (%s).</p>',
                esc_html( $atts['repeater'] ),
                $post_id,
                esc_html( get_post_type( $post_id ) ?: 'chưa rõ' )
            );
        }
        return '';
    }

    /* ----------------------------------------------------------
       3. Chuẩn hóa danh sách slides (Ảnh + Mô tả WYSIWYG)
    ---------------------------------------------------------- */
    $slides = [];
    $img_subfields = [ 'anh-phong-chup', 'anh_phong_chup', 'anh', 'image', 'hinh_anh' ];
    $desc_subfields = [ 'mo_ta_hinh_anh_phong_chup', 'mo-ta-hinh-anh-phong-chup', 'mo_ta', 'desc', 'description' ];

    foreach ( $raw_rows as $row ) {
        if ( ! is_array( $row ) ) continue;

        // 1. Trích xuất ảnh
        $img_src = '';
        $img_alt = '';
        foreach ( $img_subfields as $isf ) {
            if ( ! empty( $row[ $isf ] ) ) {
                $img_val = $row[ $isf ];
                if ( is_array( $img_val ) ) {
                    $img_src = ! empty( $img_val['url'] ) ? $img_val['url'] : '';
                    $img_alt = ! empty( $img_val['alt'] ) ? $img_val['alt'] : ( ! empty( $img_val['title'] ) ? $img_val['title'] : '' );
                    $img_id  = ! empty( $img_val['ID'] ) ? (int) $img_val['ID'] : ( ! empty( $img_val['id'] ) ? (int) $img_val['id'] : 0 );
                    if ( ! $img_src && $img_id ) {
                        $img_src = wp_get_attachment_image_url( $img_id, 'full' ) ?: wp_get_attachment_url( $img_id );
                    }
                } elseif ( is_numeric( $img_val ) ) {
                    $img_id  = (int) $img_val;
                    $img_src = wp_get_attachment_image_url( $img_id, 'full' ) ?: wp_get_attachment_url( $img_id );
                    $img_alt = (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true );
                } elseif ( is_string( $img_val ) ) {
                    if ( is_numeric( trim( $img_val ) ) ) {
                        $img_id  = (int) trim( $img_val );
                        $img_src = wp_get_attachment_image_url( $img_id, 'full' ) ?: wp_get_attachment_url( $img_id );
                    } else {
                        $img_src = $img_val;
                    }
                }
                if ( $img_src ) break;
            }
        }

        // 2. Trích xuất mô tả WYSIWYG
        $desc_html = '';
        foreach ( $desc_subfields as $dsf ) {
            if ( ! empty( $row[ $dsf ] ) ) {
                $desc_html = $row[ $dsf ];
                break;
            }
        }

        if ( $img_src || $desc_html ) {
            $slides[] = [
                'src'  => $img_src,
                'alt'  => $img_alt ?: get_the_title( $post_id ),
                'desc' => $desc_html,
            ];
        }
    }

    if ( empty( $slides ) ) {
        return '';
    }

    $total_slides = count( $slides );

    /* ----------------------------------------------------------
       4. Render HTML giao diện
    ---------------------------------------------------------- */
    static $ttp_instance = 0;
    $ttp_instance++;
    $uid = 'ttp-swiper-' . $ttp_instance;

    // SVG icon ngôi sao encode cho pagination mask
    $star_mask = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='m19.555 23.411-6.664-3.285a1.26 1.26 0 0 0-1.202.045l.006-.003-6.416 3.75a.61.61 0 0 1-.902-.626v.003l.994-7.542q.01-.075.011-.162c0-.364-.155-.691-.403-.92l-.001-.001-4.571-4.247a1.265 1.265 0 0 1 .648-2.17l.007-.001 5.987-1.108c.421-.078.765-.355.935-.727l.003-.008L10.478.746a1.272 1.272 0 0 1 2.271-.087l.003.007 2.881 5.471c.197.365.558.62.981.666h.006l6.045.681a1.265 1.265 0 0 1 .811 2.119l.001-.001-4.27 4.562a1.25 1.25 0 0 0-.315 1.116l-.001-.008 1.52 7.453q.014.061.015.134a.61.61 0 0 1-.875.549z'/%3E%3C/svg%3E\") no-repeat center / contain";

    $aspect_ratio_style = 'aspect-ratio: 1 / 1;';
    if ( $atts['aspect_ratio'] === '4/3' ) {
        $aspect_ratio_style = 'aspect-ratio: 4 / 3;';
    } elseif ( $atts['aspect_ratio'] === '16/9' ) {
        $aspect_ratio_style = 'aspect-ratio: 16 / 9;';
    } elseif ( $atts['aspect_ratio'] === 'auto' ) {
        $aspect_ratio_style = 'aspect-ratio: auto; min-height: 380px;';
    }

    $loop_val = ( $atts['loop'] === 'false' || $atts['loop'] === '0' || $total_slides < 2 ) ? 'false' : 'true';
    $autoplay_speed = (int) $atts['autoplay'];
    $trans_speed    = (int) $atts['speed'];

    ob_start();
    ?>
    <div class="memora-ttp-wrap" id="<?php echo esc_attr( $uid ); ?>-wrap">

        <!-- KHUNG SLIDER ẢNH POSTER (GÓC THẲNG, KHÔNG BO GÓC) -->
        <div class="memora-ttp-poster-box" style="<?php echo esc_attr( $aspect_ratio_style ); ?>">
            <div class="swiper memora-ttp-swiper" id="<?php echo esc_attr( $uid ); ?>">
                <div class="swiper-wrapper">
                    <?php foreach ( $slides as $s_idx => $slide ) : ?>
                    <div class="swiper-slide">
                        <?php if ( ! empty( $slide['src'] ) ) : ?>
                            <img src="<?php echo esc_url( $slide['src'] ); ?>" alt="<?php echo esc_attr( $slide['alt'] ); ?>" loading="lazy" />
                        <?php else : ?>
                            <div class="memora-ttp-no-img"><span>Memora Photo Room</span></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Nút điều hướng tròn màu nâu #733e1c -->
                <?php if ( $total_slides > 1 ) : ?>
                <div class="swiper-button-prev memora-ttp-arrow memora-ttp-prev" title="Slide trước">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </div>
                <div class="swiper-button-next memora-ttp-arrow memora-ttp-next" title="Slide tiếp theo">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>

                <!-- Pagination hình ngôi sao màu nâu #733e1c -->
                <div class="swiper-pagination memora-star-pagination" id="<?php echo esc_attr( $uid ); ?>-pagination"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MÔ TẢ WYSIWYG BÊN DƯỚI (ĐỒNG BỘ THEO SLIDE ĐANG ACTIVE) -->
        <div class="memora-ttp-desc-container" id="<?php echo esc_attr( $uid ); ?>-desc">
            <?php foreach ( $slides as $s_idx => $slide ) : ?>
            <div class="memora-ttp-desc-item <?php echo $s_idx === 0 ? 'is-active' : ''; ?>" data-slide-index="<?php echo esc_attr( $s_idx ); ?>">
                <?php if ( ! empty( $slide['desc'] ) ) : ?>
                    <div class="memora-ttp-wysiwyg">
                        <?php echo wp_kses_post( wpautop( $slide['desc'] ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- CSS STYLESHEET CHUẨN DESIGN MEMORA -->
    <style>
        #<?php echo esc_attr( $uid ); ?>-wrap.memora-ttp-wrap {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            position: relative;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap * {
            box-sizing: border-box;
        }

        /* -------------------------------------------
           KHUNG ẢNH POSTER (KHÔNG BO GÓC)
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-poster-box {
            width: 100%;
            max-width: 100%;
            position: relative;
            background-color: #f7f3ef;
            overflow: hidden;
            border-radius: 0 !important; /* Tuyệt đối không bo góc */
            box-shadow: 0 4px 18px rgba(115, 62, 28, 0.08);
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-swiper {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            border-radius: 0 !important;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .swiper-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .swiper-slide {
            width: 100%;
            height: 100%;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
            border-radius: 0 !important;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 0 !important;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-no-img {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0e9e1;
            color: #733e1c;
            font-size: 15px;
            font-weight: 600;
        }

        /* -------------------------------------------
           NÚT MŨI TÊN TRÒN MÀU NÂU #733e1c
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 50% !important;
            background: #733e1c;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(115, 62, 28, 0.35);
            transition: background-color 0.25s ease, transform 0.25s ease, opacity 0.25s ease;
            user-select: none;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-arrow:hover {
            background-color: #572e14;
            transform: translateY(-50%) scale(1.08);
            box-shadow: 0 6px 18px rgba(115, 62, 28, 0.45);
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-prev {
            left: 16px;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-next {
            right: 16px;
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-arrow::after {
            display: none !important; /* Ẩn icon mặc định của swiper */
        }
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-arrow svg {
            stroke: #ffffff;
        }

        /* -------------------------------------------
           PAGINATION NGÔI SAO MÀU NÂU #733e1c
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-star-pagination {
            bottom: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: auto;
            z-index: 10;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-star-pagination .swiper-pagination-bullet {
            width: 20px;
            height: 20px;
            background: transparent !important;
            opacity: 1;
            margin: 0 3px;
            border-radius: 0;
            position: relative;
            cursor: pointer;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-star-pagination .swiper-pagination-bullet::after {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(115, 62, 28, 0.45);
            -webkit-mask: <?php echo $star_mask; ?>;
                    mask: <?php echo $star_mask; ?>;
            transition: background-color 0.25s ease, transform 0.25s ease;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-star-pagination .swiper-pagination-bullet-active::after {
            background-color: #733e1c;
            transform: scale(1.3);
        }

        /* -------------------------------------------
           MÔ TẢ WYSIWYG BÊN DƯỚI
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-desc-container {
            width: 100%;
            margin-top: 18px;
            padding: 0 4px;
            position: relative;
            min-height: 48px;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-desc-item {
            display: none;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-desc-item.is-active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-wysiwyg {
            color: #733e1c;
            font-size: 15px;
            line-height: 1.65;
            text-align: left;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-wysiwyg p {
            margin: 0 0 10px 0;
            color: #733e1c;
            font-size: 10px;
            line-height: 1.65;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-wysiwyg strong,
        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-wysiwyg b {
            color: #572e14;
            font-weight: 700;
        }

        #<?php echo esc_attr( $uid ); ?>-wrap .memora-ttp-wysiwyg a {
            color: #733e1c;
            text-decoration: underline;
        }


    </style>

    <!-- JAVASCRIPT KHỞI TẠO SWIPER & ĐỒNG BỘ MÔ TẢ WYSIWYG -->
    <script>
    (function () {
        'use strict';
        var WRAP_ID   = '<?php echo esc_js( $uid ); ?>-wrap';
        var SWIPER_ID = '<?php echo esc_js( $uid ); ?>';
        var DESC_ID   = '<?php echo esc_js( $uid ); ?>-desc';

        function initTtpSwiper() {
            var el = document.getElementById(SWIPER_ID);
            if (!el) return;
            if (el.swiper) return; // Đã init

            if (typeof Swiper === 'undefined') {
                setTimeout(initTtpSwiper, 250);
                return;
            }

            var descContainer = document.getElementById(DESC_ID);
            var descItems = descContainer ? descContainer.querySelectorAll('.memora-ttp-desc-item') : [];

            function updateDesc(realIdx) {
                if (!descItems || descItems.length === 0) return;
                descItems.forEach(function (item) {
                    var idx = parseInt(item.getAttribute('data-slide-index'), 10);
                    if (idx === realIdx) {
                        item.classList.add('is-active');
                    } else {
                        item.classList.remove('is-active');
                    }
                });
            }

            var swiperInstance = new Swiper('#' + SWIPER_ID, {
                speed    : <?php echo $trans_speed; ?>,
                loop     : <?php echo $loop_val; ?>,
                effect   : '<?php echo esc_js( $atts['effect'] ); ?>',
                <?php if ( $autoplay_speed > 0 ) : ?>
                autoplay : {
                    delay: <?php echo $autoplay_speed; ?>,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                },
                <?php endif; ?>
                pagination: {
                    el       : '#' + SWIPER_ID + '-pagination',
                    clickable: true,
                },
                navigation: {
                    prevEl: '#' + WRAP_ID + ' .memora-ttp-prev',
                    nextEl: '#' + WRAP_ID + ' .memora-ttp-next',
                },
                on: {
                    slideChange: function () {
                        updateDesc(this.realIndex);
                    },
                    init: function () {
                        updateDesc(this.realIndex);
                    },
                },
                a11y: {
                    prevSlideMessage: 'Slide trước',
                    nextSlideMessage: 'Slide tiếp theo',
                },
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTtpSwiper);
        } else {
            initTtpSwiper();
        }

        // Tương thích Elementor editor re-render
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                setTimeout(initTtpSwiper, 150);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
