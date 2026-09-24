<?php
/**
 * Shortcode [dia_chi_noi_bat]
 *
 * Layout 2 cot:
 *   - Cot trai : featured image + nhan "Memora" + ten co so (post title)
 *   - Cot phai : [gallery_swiper] (goi truc tiep ham PHP)
 *
 * ACF Post Object field "dia_chi_co_so_noi_bat" -> post type "dia-chi"
 *
 * Cach dung:
 *   [dia_chi_noi_bat]
 *   [dia_chi_noi_bat post_id="option"]
 *   [dia_chi_noi_bat post_id="42"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'dia_chi_noi_bat', 'memora_dia_chi_noi_bat_shortcode' );

function memora_dia_chi_noi_bat_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'post_id'  => '',
        'autoplay' => 4000,
        'speed'    => 600,
    ], $atts, 'dia_chi_noi_bat' );

    if ( ! function_exists( 'get_field' ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Plugin ACF chua duoc kich hoat.</p>';
    }
    if ( ! function_exists( 'memora_gallery_swiper_shortcode' ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Thieu file gallery-swiper-shortcode.php.</p>';
    }

    /* -- Nguon lay field -- */
    if ( $atts['post_id'] === 'option' || $atts['post_id'] === 'options' ) {
        $source_id = 'option';
    } elseif ( ! empty( $atts['post_id'] ) ) {
        $source_id = (int) $atts['post_id'];
    } else {
        $source_id = get_the_ID();
    }

    /* -- Lay Post Object tu ACF -- */
    $dia_chi_post = get_field( 'dia_chi_co_so_noi_bat', $source_id );
    if ( empty( $dia_chi_post ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Chua co dia chi noi bat duoc chon.</p>';
    }

    $dc_id    = is_object( $dia_chi_post ) ? $dia_chi_post->ID : (int) $dia_chi_post;
    $dc_title = get_the_title( $dc_id );

    /* -- Featured image (thumbnail trai) -- */
    $thumb_id  = get_post_thumbnail_id( $dc_id );
    $thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    $thumb_alt = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : $dc_title;

    /* ----------------------------------------------------------
       Lay gallery tu ACF, trich xuat IDs de truyen vao gallery_swiper.
       Viec tu lay IDs truoc giup:
         1. Dem duoc so luong anh => quyet dinh loop mode
         2. Fallback sang featured image neu gallery trong
         3. Tranh van de sanitize_key voi field name co dau ga
    ---------------------------------------------------------- */
    $slide_ids = [];
    $gallery   = get_field( 'cac-hinh-anh-cua-dia-chi', $dc_id );

    if ( ! empty( $gallery ) && is_array( $gallery ) ) {
        foreach ( $gallery as $img ) {
            if ( is_array( $img ) && ! empty( $img['ID'] ) ) {
                $slide_ids[] = (int) $img['ID'];
            } elseif ( is_numeric( $img ) ) {
                $slide_ids[] = (int) $img;
            }
        }
    }

    /* Fallback: dung featured image neu gallery trong */
    if ( empty( $slide_ids ) && $thumb_id ) {
        $slide_ids[] = (int) $thumb_id;
    }

    if ( empty( $slide_ids ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Khong tim thay anh nao cho dia chi nay.</p>';
    }

    /* loop chi bat khi co >= 2 slide (Swiper v11 yeu cau vay) */
    $loop_val = count( $slide_ids ) >= 2 ? 'true' : 'false';

    /* -- Unique wrapper ID -- */
    static $dcnb_instance = 0;
    $dcnb_instance++;
    $wrap_uid = 'dcnb-wrap-' . $dcnb_instance;

    /* -- Goi gallery_swiper voi IDs cu the -- */
    $slider_html = memora_gallery_swiper_shortcode( [
        'ids'      => implode( ',', $slide_ids ),
        'autoplay' => $atts['autoplay'],
        'speed'    => $atts['speed'],
        'loop'     => $loop_val,
        'effect'   => 'slide',
    ] );

    ob_start();
    ?>
    <div class="dcnb-wrap" id="<?php echo esc_attr( $wrap_uid ); ?>">

        <!-- COT TRAI -->
        <div class="dcnb-info">
            <div class="dcnb-brand-label">Memora<br><em>"Angel Shot"</em></div>

            <?php if ( $thumb_src ) : ?>
            <div class="dcnb-thumbnail">
                <img src="<?php echo esc_url( $thumb_src ); ?>"
                     alt="<?php echo esc_attr( $thumb_alt ); ?>"
                     loading="lazy" />
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( get_permalink( $dc_id ) ); ?>" class="dcnb-address">
                <svg class="dcnb-pin-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
                <span><?php echo esc_html( $dc_title ); ?></span>
            </a>

        </div>

        <!-- COT PHAI: gallery_swiper -->
        <div class="dcnb-slider-col">
            <?php echo $slider_html; ?>
        </div>

    </div>

    <style>
        /* === Wrapper === */
        #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-height: 300px;    /* dam bao chieu cao toi thieu cho height:100% chain */
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }

        /* === Cot trai === */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-info {
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

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-brand-label {
            font-family: 'Dancing Script', 'Pacifico', cursive, serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #733e1c;
            text-align: center;
            line-height: 1.3;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-brand-label em {
            font-style: italic;
            font-size: 1.05rem;
        }

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-thumbnail {
            width: 110px;
            height: 110px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-address {
            display: flex;
            align-items: flex-start;
            gap: 5px;
            font-size: 0.82rem;
            color: #555;
            line-height: 1.45;
            text-decoration: none;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-address:hover {
            color: #733e1c;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-address:hover .dcnb-pin-icon {
            color: #733e1c;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-pin-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 2px;
            color: #733e1c;
        }

        /* === Cot phai === */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col {
            flex: 1 1 0;
            min-width: 0;
            position: relative;
        }

        /*
         * Override gallery_swiper ben trong:
         * Keo swiper ra full height cua flex col.
         * .dcnb-wrap co min-height:300px nen height:100% se resolve chinh xac.
         */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper-wrap {
            height: 100%;
            min-height: 300px;
            border-radius: 0;
            overflow: hidden;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper {
            height: 100%;
            min-height: 300px;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-slide {
            height: 100%;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        /* An arrow cua gallery_swiper ben trong (khong can thiet) */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-prev,
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-next {
            display: none;
        }

        /* === Responsive === */
        @media (max-width: 600px) {
            #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap {
                flex-direction: column;
                min-height: auto;
            }
            #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-info {
                flex: none;
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                padding: 16px;
            }
            #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper-wrap,
            #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper {
                min-height: 220px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}