<?php
/**
 * Shortcode [dia_chi_noi_bat]
 *
 * Layout 2 cot (1/3 - 2/3):
 *   - Cot trai (1/3): featured image + nhan "Memora" + ten co so (post title)
 *   - Cot phai (2/3): [gallery_swiper] (goi truc tiep ham PHP)
 *
 * ACF Post Object field "dia_chi_co_so_noi_bat" -> post type "dia-chi"
 *
 * Cach dung:
 *   [dia_chi_noi_bat]
 *   [dia_chi_noi_bat post_id="option"]
 *   [dia_chi_noi_bat post_id="42"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
   0. Helper lấy ID bài viết dia-chi mới nhất / nổi bật
------------------------------------------------------------------ */
if ( ! function_exists( 'memora_get_featured_dia_chi_id' ) ) {
    function memora_get_featured_dia_chi_id() {
        static $featured_id = null;
        if ( $featured_id !== null ) {
            return $featured_id;
        }

        // 1. Nếu đã được set trong runtime
        if ( ! empty( $GLOBALS['memora_featured_dia_chi_id'] ) ) {
            $featured_id = (int) $GLOBALS['memora_featured_dia_chi_id'];
            return $featured_id;
        }

        // 2. Mặc định: Lấy bài viết post_type=dia-chi MỚI NHẤT
        $posts = get_posts( [
            'post_type'        => 'dia-chi',
            'posts_per_page'   => 1,
            'post_status'      => 'publish',
            'orderby'          => 'date',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'suppress_filters' => true,
        ] );

        $featured_id = ! empty( $posts ) ? (int) $posts[0] : 0;
        $GLOBALS['memora_featured_dia_chi_id'] = $featured_id;
        return $featured_id;
    }
}

/* ------------------------------------------------------------------
   1. Đăng ký Query ID cho Elementor Loop Grid: cacDiaChiKhac
   Lấy các bài viết post_type=dia-chi và bỏ qua bài viết mới nhất
------------------------------------------------------------------ */
function memora_elementor_query_cac_dia_chi_khac( $query ) {
    // Đảm bảo lấy đúng post_type 'dia-chi'
    $query->set( 'post_type', 'dia-chi' );

    // Lấy ID bài viết mới nhất (được hiển thị ở [dia_chi_noi_bat]) để bỏ qua
    $latest_id = function_exists( 'memora_get_featured_dia_chi_id' )
        ? memora_get_featured_dia_chi_id()
        : 0;

    if ( $latest_id > 0 ) {
        $post_not_in = (array) $query->get( 'post__not_in' );
        $post_not_in[] = $latest_id;
        $query->set( 'post__not_in', array_values( array_unique( array_filter( $post_not_in ) ) ) );
    }

    // Mặc định sắp xếp theo ngày mới nhất nếu chưa chọn
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'elementor/query/cacDiaChiKhac', 'memora_elementor_query_cac_dia_chi_khac', 10, 2 );
add_action( 'elementor/query/cac_dia_chi_khac', 'memora_elementor_query_cac_dia_chi_khac', 10, 2 );
add_action( 'elementor/query/cacdiachikhac', 'memora_elementor_query_cac_dia_chi_khac', 10, 2 );

/* ------------------------------------------------------------------
   2. Shortcode [dia_chi_noi_bat] – Mặc định lấy bài viết dia-chi mới nhất
------------------------------------------------------------------ */
add_shortcode( 'dia_chi_noi_bat', 'memora_dia_chi_noi_bat_shortcode' );

function memora_dia_chi_noi_bat_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'post_id'  => '',
        'term_id'  => '',   // term_id="123" → dùng khi Loop Grid chạy mode Post Taxonomy
        'current'  => '',   // current="1" → tự dùng get_the_ID() (cho Elementor Loop Item)
        'autoplay' => 4000,
        'speed'    => 600,
    ], $atts, 'dia_chi_noi_bat' );

    if ( ! function_exists( 'memora_gallery_swiper_shortcode' ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Thiếu file gallery-swiper-shortcode.php.</p>';
    }

    // Luôn bảo đảm assets được nạp
    if ( function_exists( 'memora_gallery_swiper_assets' ) ) {
        memora_gallery_swiper_assets();
    }

    /* ------------------------------------------------------------------
       Khởi tạo biến
    ------------------------------------------------------------------ */
    $dc_term        = null;
    $dc_term_url    = '';
    $dc_term_source = null;
    $dc_id          = 0;
    $dc_title       = '';

    /* ------------------------------------------------------------------
       Chế độ A1: term_id truyền tường minh qua attribute
       Dùng khi cần override hoặc debug.
    ------------------------------------------------------------------ */
    if ( ! empty( $atts['term_id'] ) && is_numeric( $atts['term_id'] ) ) {
        $t = get_term( (int) $atts['term_id'], 'dia-chi' );
        if ( $t && ! is_wp_error( $t ) ) {
            $dc_term        = $t;
            $dc_term_source = 'term_' . $t->term_id;
            $term_link      = get_term_link( $t, 'dia-chi' );
            $dc_term_url    = ! is_wp_error( $term_link ) ? $term_link : '';
            $dc_title       = $t->name;
        }
    }

    /* ------------------------------------------------------------------
       Chế độ A2: AUTO-DETECT Elementor taxonomy Loop Grid
       Elementor set get_the_ID() = term_id khi chạy "Post Taxonomy" loop.
       Thử get_term(get_the_ID(), 'dia-chi') — nếu hợp lệ thì đang trong loop.
       Chỉ chạy khi không có post_id/current/term_id tường minh.
    ------------------------------------------------------------------ */
    if ( ! $dc_term && empty( $atts['term_id'] ) && empty( $atts['post_id'] ) && empty( $atts['current'] ) ) {
        $maybe_term_id = (int) get_the_ID();
        if ( $maybe_term_id > 0 ) {
            $t = get_term( $maybe_term_id, 'dia-chi' );
            if ( $t && ! is_wp_error( $t ) ) {
                $dc_term        = $t;
                $dc_term_source = 'term_' . $t->term_id;
                $term_link      = get_term_link( $t, 'dia-chi' );
                $dc_term_url    = ! is_wp_error( $term_link ) ? $term_link : '';
                $dc_title       = $t->name;
            }
        }
    }

    /* ------------------------------------------------------------------
       Chế độ B: lookup qua post (fallback khi không phải taxonomy loop)
    ------------------------------------------------------------------ */
    if ( ! $dc_term ) {

        if ( ! empty( $atts['current'] ) && $atts['current'] ) {
            $dc_id = (int) get_the_ID();

        } elseif ( ! empty( $atts['post_id'] ) && is_numeric( $atts['post_id'] ) ) {
            $dc_id = (int) $atts['post_id'];

        } elseif ( $atts['post_id'] === 'option' || $atts['post_id'] === 'options' ) {
            if ( function_exists( 'get_field' ) ) {
                $opt_post = get_field( 'dia_chi_co_so_noi_bat', 'option' );
                if ( ! empty( $opt_post ) ) {
                    $dc_id = is_object( $opt_post ) ? (int) $opt_post->ID : (int) $opt_post;
                }
            }

        } else {
            $loop_id = (int) get_the_ID();
            if ( $loop_id > 0 && get_post_type( $loop_id ) === 'dia-chi' ) {
                $dc_id = $loop_id;
            }
            if ( ! $dc_id && function_exists( 'get_field' ) && $loop_id ) {
                $cur_post = get_field( 'dia_chi_co_so_noi_bat', $loop_id );
                if ( ! empty( $cur_post ) ) {
                    $dc_id = is_object( $cur_post ) ? (int) $cur_post->ID : (int) $cur_post;
                }
            }
        }

        if ( ! $dc_id && function_exists( 'memora_get_featured_dia_chi_id' ) ) {
            $dc_id = memora_get_featured_dia_chi_id();
        }

        if ( ! $dc_id ) {
            return '<p style="color:#d9534f; font-size:14px; padding:10px 14px; background:#fff2f2; border:1px solid #fecaca; border-radius:4px;">[dia_chi_noi_bat] Không tìm thấy bài viết nào thuộc post type <code>dia-chi</code>.</p>';
        }

    } // end Chế độ B

    /* -- Sau Chế độ B: cập nhật các biến từ $dc_id (nếu không dùng term_id) -- */
    if ( ! $dc_term ) {
        $GLOBALS['memora_featured_dia_chi_id'] = $dc_id;
        $dc_title = get_the_title( $dc_id );

        /* Lấy term đầu tiên của taxonomy 'dia-chi' gắn với bài viết */
        $dc_term_url = get_permalink( $dc_id ); // fallback
        $dc_terms    = get_the_terms( $dc_id, 'dia-chi' );
        if ( ! empty( $dc_terms ) && ! is_wp_error( $dc_terms ) ) {
            $dc_term        = $dc_terms[0];
            $term_link      = get_term_link( $dc_term, 'dia-chi' );
            $dc_term_url    = ! is_wp_error( $term_link ) ? $term_link : $dc_term_url;
            $dc_term_source = 'term_' . $dc_term->term_id;
        }
    }

    /* -- Featured image (thumbnail trai) -- */
    $thumb_id  = get_post_thumbnail_id( $dc_id );
    $thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    $thumb_alt = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : $dc_title;

    /* ----------------------------------------------------------
       Lấy danh sách ảnh cho slider cơ sở nổi bật (đa tầng)
    ---------------------------------------------------------- */
    $slide_ids = [];

    // Danh sách tên field gallery có thể có
    $gallery_candidates = [
        'cac-hinh-anh-cua-dia-chi',
        'cac_hinh_anh_cua_dia_chi',
        'gallery',
        'album',
        'cac_hinh_anh',
        'hinh_anh_dia_chi',
    ];

    // Helper parse gallery value (array hoặc chuỗi CSV)
    $parse_gallery = function( $gallery ) use ( &$slide_ids ) {
        if ( is_array( $gallery ) ) {
            foreach ( $gallery as $img ) {
                if ( is_array( $img ) ) {
                    $img_id = ! empty( $img['ID'] ) ? (int) $img['ID'] : ( ! empty( $img['id'] ) ? (int) $img['id'] : 0 );
                    if ( $img_id ) $slide_ids[] = $img_id;
                } elseif ( is_numeric( $img ) ) {
                    $slide_ids[] = (int) $img;
                } elseif ( is_string( $img ) && is_numeric( trim( $img ) ) ) {
                    $slide_ids[] = (int) trim( $img );
                }
            }
        } elseif ( is_string( $gallery ) && strpos( $gallery, ',' ) !== false ) {
            foreach ( explode( ',', $gallery ) as $p ) {
                if ( is_numeric( trim( $p ) ) ) $slide_ids[] = (int) trim( $p );
            }
        }
    };

    // Ưu tiên: lấy ảnh từ ACF field của TERM (taxonomy 'dia-chi')
    if ( $dc_term_source && function_exists( 'get_field' ) ) {
        foreach ( $gallery_candidates as $field_key ) {
            $gallery = get_field( $field_key, $dc_term_source );
            if ( ! empty( $gallery ) ) {
                $parse_gallery( $gallery );
                if ( ! empty( $slide_ids ) ) break;
            }
        }
    }

    // Nếu term meta rỗng, thử get_term_meta trực tiếp
    if ( empty( $slide_ids ) && $dc_term ) {
        foreach ( $gallery_candidates as $field_key ) {
            $meta_val = get_term_meta( $dc_term->term_id, $field_key, true );
            if ( ! empty( $meta_val ) ) {
                $meta_val = maybe_unserialize( $meta_val );
                $parse_gallery( $meta_val );
                if ( ! empty( $slide_ids ) ) break;
            }
        }
    }

    // Fallback: thử ACF field từ POST nếu term không có ảnh
    if ( empty( $slide_ids ) && function_exists( 'get_field' ) ) {
        foreach ( $gallery_candidates as $field_key ) {
            $gallery = get_field( $field_key, $dc_id );
            if ( ! empty( $gallery ) ) {
                $parse_gallery( $gallery );
                if ( ! empty( $slide_ids ) ) break;
            }
        }
    }

    // Fallback: postmeta trực tiếp
    if ( empty( $slide_ids ) ) {
        foreach ( $gallery_candidates as $field_key ) {
            $meta_val = get_post_meta( $dc_id, $field_key, true );
            if ( ! empty( $meta_val ) ) {
                $meta_val = maybe_unserialize( $meta_val );
                $parse_gallery( $meta_val );
                if ( ! empty( $slide_ids ) ) break;
            }
        }
    }

    // Fallback: Lấy ảnh đính kèm vào bài viết nếu gallery rỗng
    if ( empty( $slide_ids ) ) {
        $attached = get_attached_media( 'image', $dc_id );
        if ( ! empty( $attached ) ) {
            foreach ( $attached as $att ) {
                $slide_ids[] = (int) $att->ID;
            }
        }
    }

    // Fallback: Dùng featured image nếu vẫn chưa có
    if ( empty( $slide_ids ) && $thumb_id ) {
        $slide_ids[] = (int) $thumb_id;
    }

    $slide_ids = array_values( array_unique( array_filter( $slide_ids ) ) );

    if ( empty( $slide_ids ) ) {
        return '<p style="color:red;">[dia_chi_noi_bat] Khong tim thay anh nao cho dia chi nay.</p>';
    }

    /* loop chi bat khi co >= 2 slide (Swiper v11 yeu cau vay) */
    $loop_val = count( $slide_ids ) >= 2 ? 'true' : 'false';

    /* -- Unique wrapper ID -- */
    static $dcnb_instance = 0;
    $dcnb_instance++;
    $wrap_uid = 'dcnb-wrap-' . $dcnb_instance;

    /* -- Goi gallery_swiper voi IDs va ACF field -- */
    // Nếu có term, truyền post_id dạng 'term_{id}' để gallery_swiper đọc ACF field từ term
    $swiper_post_id = $dc_term_source ? $dc_term_source : (string) $dc_id;
    $slider_html = memora_gallery_swiper_shortcode( [
        'acf_gallery' => 'cac-hinh-anh-cua-dia-chi',
        'post_id'     => $swiper_post_id,
        'ids'         => implode( ',', $slide_ids ),
        'autoplay'    => $atts['autoplay'],
        'speed'       => $atts['speed'],
        'loop'        => $loop_val,
        'effect'      => 'slide',
    ] );

    ob_start();
    ?>
    <div class="dcnb-wrap" id="<?php echo esc_attr( $wrap_uid ); ?>">

        <!-- COT TRAI -->
        <div class="dcnb-info">
            <div class="dcnb-brand-label">Memora<br><em>"Angel Shot"</em></div>

            <div class="dcnb-map">
                <iframe
                    src="https://maps.google.com/maps?q=<?php echo urlencode( $dc_title ); ?>&output=embed&z=15&hl=vi"
                    width="100%"
                    height="100%"
                    style="border:0; display:block;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="<?php echo esc_attr( $dc_title ); ?>">
                </iframe>
            </div>


            <a href="<?php echo esc_url( $dc_term_url ); ?>" class="dcnb-book-btn">
                Book lịch ngay
            </a>

        </div>

        <!-- COT PHAI: gallery_swiper -->
        <div class="dcnb-slider-col">
            <?php echo $slider_html; ?>
        </div>

    </div>

    <style>
        /* === Reset Box Sizing === */
        #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap,
        #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap * {
            box-sizing: border-box;
        }

        /* === Wrapper === */
        #<?php echo esc_attr( $wrap_uid ); ?>.dcnb-wrap {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-height: 300px;    /* dam bao chieu cao toi thieu cho height:100% chain */
            overflow: hidden;
        }

        /* === Cot trai (1/3) === */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-info {
            flex: 0 0 calc(100% / 3);
            width: calc(100% / 3);
            max-width: calc(100% / 3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 28px 20px;
        }

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-brand-label {
                font-family: "Anastasia Script", Sans-serif;
                font-size: 1.25rem;
                font-weight: 500;
                color: #733e1c;
                text-align: center;
                line-height: 0.9;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-brand-label em {
            font-style: italic;
            font-size: 1.05rem;
        }

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-map {
            width: 100%;
            flex: 1 1 auto;
            min-height: 140px;
            border-radius: 8px;
            overflow: hidden;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-map iframe {
            width: 100%;
            height: 100%;
            min-height: 140px;
            border: 0;
            display: block;
        }

        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-book-btn {
            display: inline-block;
            font-family: "Cocomat Pro", Sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #733E1C;
            border-style: solid;
            border-width: 1px;
            border-color: #733E1C;
            border-radius: 151px;
            padding: 0.5em 1em 0.7em 1em;
            text-decoration: none;
            text-align: center;
            line-height: 1.3;
            transition: background 0.2s ease, color 0.2s ease;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-book-btn:hover {
            background: #733E1C;
            color: #fff;
        }

        /* === Cot phai (2/3) === */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col {
            flex: 0 0 calc(100% * 2 / 3);
            width: calc(100% * 2 / 3);
            max-width: calc(100% * 2 / 3);
            min-width: 0;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /*
         * Override gallery_swiper ben trong:
         * Keo swiper ra full height cua flex col.
         * .dcnb-wrap co min-height:300px nen height:100% se resolve chinh xac.
         */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper-wrap {
            flex: 1 1 auto;
            width: 100%;
            height: 100%;
            min-height: 300px;
            border-radius: 0;
            overflow: hidden;
            position: relative;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .memora-gallery-swiper {
            width: 100%;
            height: 100%;
            min-height: 300px;
            position: relative;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-wrapper {
            height: 100%;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-slide {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Nut mui ten dieu huong ro rang de nguoi dung thao tac tren desktop */
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-prev,
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-next {
            display: flex !important;
            align-items: center;
            justify-content: center;
            opacity: 0.85;
            transition: opacity 0.25s ease, transform 0.25s ease, background 0.25s ease;
            z-index: 10;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col:hover .swiper-button-prev,
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col:hover .swiper-button-next {
            opacity: 1;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-prev {
            left: 12px;
        }
        #<?php echo esc_attr( $wrap_uid ); ?> .dcnb-slider-col .swiper-button-next {
            right: 12px;
        }


    </style>
    <?php
    return ob_get_clean();
}