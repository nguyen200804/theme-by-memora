<?php
/**
 * Shortcode [dia_chi_noi_bat]
 *
 * Hien thi layout 2 cot gioi thieu dia chi noi bat:
 *   - Cot trai : featured image + nhan "Memora" + ten co so (post title)
 *   - Cot phai : [gallery_swiper] lay ACF Gallery "cac-hinh-anh-cua-dia-chi"
 *
 * Nguon du lieu:
 *   ACF Post Object field "dia_chi_co_so_noi_bat" -> post type "dia-chi"
 *
 * Cach dung:
 *   [dia_chi_noi_bat]                  <- lay field tu post/page hien tai
 *   [dia_chi_noi_bat post_id="option"] <- lay field tu ACF Options Page
 *   [dia_chi_noi_bat post_id="42"]     <- lay field tu bai viet ID 42
 *
 * Tham so:
 *   post_id  - ID bai viet chua field "dia_chi_co_so_noi_bat"
 *              Dung "option" neu field nam tren Options Page
 *   autoplay - ms tu chay slide (truyen xuong gallery_swiper). Mac dinh 4000
 *   speed    - ms chuyen slide (truyen xuong gallery_swiper). Mac dinh 600
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

    if ( $atts['post_id'] === 'option' || $atts['post_id'] === 'options' ) {
        $source_id = 'option';
    } elseif ( ! empty( $atts['post_id'] ) ) {
        $source_id = (int) $atts['post_id'];
    } else {
        $source_id = get_the_ID();
    }

    $dia_chi_post = get_field( 'dia_chi_co_so_noi_bat', $source_id );

    if ( empty( $dia_chi_post ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Chua co dia chi noi bat duoc chon.</p>';
    }

    $dc_id    = is_object( $dia_chi_post ) ? $dia_chi_post->ID : (int) $dia_chi_post;
    $dc_title = get_the_title( $dc_id );

    $thumb_id  = get_post_thumbnail_id( $dc_id );
    $thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    $thumb_alt = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : $dc_title;

    static $dcnb_instance = 0;
    $dcnb_instance++;
    $wrap_uid = 'dcnb-wrap-' . $dcnb_instance;

    $slider_html = memora_gallery_swiper_shortcode( [
        'acf_gallery' => 'cac-hinh-anh-cua-dia-chi',
        'post_id'     => (string) $dc_id,
        'autoplay'    => $atts['autoplay'],
        'speed'       => $atts['speed'],
        'loop'        => 'true',
        'effect'      => 'slide',
    ] );

    if ( $thumb_id && ( empty( $slider_html ) || false !== strpos( $slider_html, 'color:red' ) ) ) {
        $slider_html = memora_gallery_swiper_shortcode( [
            'ids'      => (string) $thumb_id,
            'autoplay' => '0',
            'loop'     => 'false',
        ] );
    }

    ob_start();
    ?>
    <div class="dcnb-wrap" id="<?php echo esc_attr( $wrap_uid ); ?>">

        <div class="dcnb-info">
            <div class="dcnb-brand-label">Memora<br><em>"Angel Shot"</em></div>
            <?php if ( $thumb_src ) : ?>
            <div class="dcnb-thumbnail">
                <img src="<?php echo esc_url( $thumb_src ); ?>"
                     alt="<?php echo esc_attr( $thumb_alt ); ?>"
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

        <div class="dcnb-slider-col">
            <?php echo $slider_html; ?>
        </div>

    </div>

    <style>
        #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap {
            display: flex;
            align-items: stretch;
            width: 100%;
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
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
            text-align: left;
            line-height: 1.45;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-pin-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 2px;
            color: #733e1c;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col {
            flex: 1 1 0;
            min-width: 0;
            position: relative;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper-wrap {
            height: 100%;
            border-radius: 0;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper {
            height: 100%;
            min-height: 260px;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        @media (max-width: 600px) {
            #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap { flex-direction: column; }
            #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-info {
                flex: none;
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                padding: 16px;
            }
            #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper { min-height: 220px; }
        }
    </style>
    <?php
    return ob_get_clean();
}