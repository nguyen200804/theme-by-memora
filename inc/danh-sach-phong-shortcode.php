<?php
/**
 * Shortcode [danh_sach_phong]
 *
 * Hiển thị danh sách phòng chụp ảnh lấy từ ACF Post Object "cac-phong-cua-dia-chi" (post type "phong-chup-anh")
 * Thiết kế giao diện chuẩn theo mockup Memora:
 *  - Hàng trên: Các phòng Selfbooth (Room 1, Room 2) dạng lưới 2 cột, tỉ lệ poster 1:1 với slider ảnh lướt & pagination ngôi sao
 *  - Hàng dưới: Các phòng Photobooth, Seasonal (Room 3, Room 4) dạng card hàng ngang rộng với Badge, Mô tả concept, Nút "Tìm hiểu thêm" và Slider ảnh lướt
 *
 * Cách dùng:
 *   [danh_sach_phong]
 *   [danh_sach_phong post_id="267"]
 *   [danh_sach_phong layout="auto"]  <!-- auto | grid | list -->
 *   [danh_sach_phong button_text="Tìm hiểu thêm"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'danh_sach_phong', 'memora_danh_sach_phong_shortcode' );

function memora_danh_sach_phong_shortcode( $atts ) {

    // Thu thập tất cả attributes thô (bao gồm cả room_{id} chưa khai báo trong shortcode_atts)
    $raw_atts = (array) $atts;

    $atts = shortcode_atts( [
        'post_id'          => '',      // Không còn dùng để đọc ACF, chỉ dự phòng fallback
        'term_slug'        => '',      // Slug term tường minh: term_slug="ha-noi"
        'taxonomy'         => 'dia-chi',
        'layout'           => 'auto',  // auto | grid | list
        'button_text'      => 'Tìm hiểu thêm',
        'speed'            => 600,
        'autoplay'         => 4000,
        'orderby'          => 'menu_order', // menu_order | title | date
        'order'            => 'ASC',
        'booking_room_url' => '',      // URL đặt lịch dùng chung cho tất cả phòng
        'room_urls'        => '',      // Fallback: "post_id:url|post_id:url"
    ], $atts, 'danh_sach_phong' );

    // Build $room_url_map từ 2 nguồn:
    // 1) Attribute riêng từng phòng: room_267="/dat-lich/" room_268="/dat-lich/"
    // 2) Fallback: room_urls="267:/dat-lich/|268:/dat-lich/"
    $room_url_map = [];

    // Nguồn 1: Scan raw_atts tìm key có pattern room_{số}
    foreach ( $raw_atts as $key => $val ) {
        if ( preg_match( '/^room_(\d+)$/i', $key, $m ) && ! empty( $val ) ) {
            $room_url_map[ (int) $m[1] ] = $val;
        }
    }

    // Nguồn 2: Fallback room_urls nếu chưa có map nào
    if ( empty( $room_url_map ) && ! empty( $atts['room_urls'] ) ) {
        foreach ( explode( '|', $atts['room_urls'] ) as $pair ) {
            $pair = trim( $pair );
            if ( strpos( $pair, ':' ) !== false ) {
                $parts = explode( ':', $pair, 2 );
                $rid   = (int) trim( $parts[0] );
                $rurl  = trim( $parts[1] );
                if ( $rid > 0 && ! empty( $rurl ) ) {
                    $room_url_map[ $rid ] = $rurl;
                }
            }
        }
    }

    // Enqueue Swiper assets
    if ( function_exists( 'memora_gallery_swiper_assets' ) ) {
        memora_gallery_swiper_assets();
    }

    /* ----------------------------------------------------------
       1. Detect term chỉ định
          Thứ tự ưu tiên:
            A. Attr term_slug="ha-noi"
            B. URL: ?term_id=123 | ?term_slug=slug | ?dia-chi=slug | ?dia_chi=slug
            C. Đang ở trang taxonomy archive (is_tax)
    ---------------------------------------------------------- */
    $resolved_term = null;
    $taxonomy_key  = $atts['taxonomy']; // "dia-chi"

    /* -- A. Attr tường minh -- */
    if ( ! empty( $atts['term_slug'] ) ) {
        $t = get_term_by( 'slug', sanitize_title( $atts['term_slug'] ), $taxonomy_key );
        if ( $t && ! is_wp_error( $t ) ) $resolved_term = $t;
    }

    /* -- B. URL query params -- */
    if ( ! $resolved_term ) {
        $url_term_id   = isset( $_GET['term_id'] )  ? (int) sanitize_text_field( $_GET['term_id'] )  : 0;
        $url_term_slug = isset( $_GET['term_slug'] ) ? sanitize_title( $_GET['term_slug'] )            : '';
        $tax_params    = [ $taxonomy_key, str_replace( '-', '_', $taxonomy_key ) ];

        if ( $url_term_id > 0 ) {
            $t = get_term( $url_term_id, $taxonomy_key );
            if ( $t && ! is_wp_error( $t ) ) $resolved_term = $t;
        }
        if ( ! $resolved_term && $url_term_slug ) {
            $t = get_term_by( 'slug', $url_term_slug, $taxonomy_key );
            if ( $t && ! is_wp_error( $t ) ) $resolved_term = $t;
        }
        if ( ! $resolved_term ) {
            foreach ( $tax_params as $pk ) {
                if ( ! empty( $_GET[ $pk ] ) ) {
                    $t = get_term_by( 'slug', sanitize_title( $_GET[ $pk ] ), $taxonomy_key );
                    if ( $t && ! is_wp_error( $t ) ) { $resolved_term = $t; break; }
                }
            }
        }
    }

    /* -- C. Taxonomy archive -- */
    if ( ! $resolved_term && empty( $atts['post_id'] ) ) {
        if ( is_tax( $taxonomy_key ) || is_tax() ) {
            $queried = get_queried_object();
            if ( $queried instanceof WP_Term ) $resolved_term = $queried;
        }
    }

    /* ----------------------------------------------------------
       2. Query bài viết phong-chup-anh thuộc term
    ---------------------------------------------------------- */
    $room_ids = [];

    if ( $resolved_term ) {
        /* --- Query theo taxonomy term --- */
        $query_args = [
            'post_type'      => 'phong-chup-anh',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => sanitize_key( $atts['orderby'] ),
            'order'          => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
            'fields'         => 'ids',
            'tax_query'      => [ [
                'taxonomy' => $resolved_term->taxonomy,
                'field'    => 'term_id',
                'terms'    => $resolved_term->term_id,
            ] ],
        ];
        $room_ids = get_posts( $query_args );

    } elseif ( ! empty( $atts['post_id'] ) ) {
        /* --- Fallback: post_id tường minh (không dùng ACF field) --- */
        $pid = (int) $atts['post_id'];
        if ( $pid > 0 && get_post_type( $pid ) === 'phong-chup-anh' ) {
            $room_ids = [ $pid ];
        }

    } else {
        /* --- Elementor Editor fallback: lấy tất cả phong-chup-anh mới nhất để preview --- */
        $is_elementor_editor = class_exists( '\Elementor\Plugin' ) && (
            ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) ||
            ( isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) ||
            isset( $_GET['elementor-preview'] ) ||
            ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' )
        );

        if ( $is_elementor_editor ) {
            $room_ids = get_posts( [
                'post_type'      => 'phong-chup-anh',
                'post_status'    => 'any',
                'posts_per_page' => 6,
                'fields'         => 'ids',
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
        }
    }

    $room_ids = array_values( array_filter( array_map( 'intval', (array) $room_ids ) ) );

    if ( empty( $room_ids ) ) {
        $debug = $resolved_term
            ? 'term "' . esc_html( $resolved_term->name ) . '" (ID: ' . $resolved_term->term_id . ')'
            : ( ! empty( $atts['term_slug'] ) ? 'slug "' . esc_html( $atts['term_slug'] ) . '"' : 'không xác định được term' );
        return '<p style="color:#733e1c; font-size:14px; padding:12px 16px; background:#faf6f2; border:1px solid #e8ded4; border-radius:6px;">[danh_sach_phong] Chưa có phòng nào thuộc ' . $debug . '.</p>';
    }

    /* ----------------------------------------------------------
       3. Thu thập dữ liệu chi tiết từng phòng
    ---------------------------------------------------------- */
    $rooms_data = [];
    $index = 0;

    foreach ( $room_ids as $r_id ) {
        $room_post = get_post( $r_id );
        if ( ! $room_post ) continue;

        $index++;
        $title = get_the_title( $r_id );

        // Ưu tiên: 1) room_urls theo post_id, 2) booking_room_url chung, 3) ACF field từng phòng, 4) permalink
        if ( isset( $room_url_map[ $r_id ] ) ) {
            $link = esc_url( $room_url_map[ $r_id ] );
        } else {
            $booking_url = ! empty( $atts['booking_room_url'] ) ? $atts['booking_room_url'] : '';
            if ( empty( $booking_url ) && function_exists( 'get_field' ) ) {
                $booking_url = get_field( 'booking_room_url', $r_id )
                            ?: get_field( 'booking_url', $r_id )
                            ?: get_field( 'dat_lich_url', $r_id )
                            ?: get_field( 'url_dat_lich', $r_id );
            }
            $link = ! empty( $booking_url ) ? esc_url( $booking_url ) : get_permalink( $r_id );
        }


        // Số phòng / Nhãn phòng
        $room_num_val = get_field( 'room_number', $r_id ) ?: get_field( 'so_phong', $r_id );
        $room_number  = ! empty( $room_num_val ) ? $room_num_val : ( 'Room ' . $index );

        // Mô tả concept
        $desc = get_field( 'mieu_ta_concept', $r_id )
             ?: get_field( 'concept', $r_id )
             ?: get_field( 'mo_ta_ngan', $r_id )
             ?: get_field( 'mo_ta', $r_id )
             ?: get_post_field( 'post_excerpt', $r_id );

        if ( empty( $desc ) ) {
            $desc = 'Miêu tả về concept phòng ' . str_pad( $index, 2, '0', STR_PAD_LEFT );
        }

        // Badge nổi bật (ví dụ: Special Room)
        $badge = get_field( 'badge', $r_id )
              ?: get_field( 'nhan_phong', $r_id )
              ?: get_field( 'loai_phong', $r_id );

        if ( empty( $badge ) && ( stripos( $title, 'special' ) !== false || stripos( $title, 'sesonal' ) !== false || stripos( $title, 'seasonal' ) !== false ) ) {
            $badge = 'Special Room';
        }

        // Thu thập hình ảnh cho slider phòng
        $image_urls = [];

        // 1. Ưu tiên: Repeater field "cac_hinh_anh_phong_chup" → sub-field "anh-phong-chup" (Image)
        $repeater_candidates = [
            [ 'repeater' => 'cac_hinh_anh_phong_chup',     'sub' => 'anh-phong-chup' ],
            [ 'repeater' => 'cac_hinh_anh_phong_chup',     'sub' => 'anh_phong_chup' ],
            [ 'repeater' => 'cac-hinh-anh-phong-chup',     'sub' => 'anh-phong-chup' ],
        ];

        foreach ( $repeater_candidates as $rc ) {
            $rows = get_field( $rc['repeater'], $r_id );
            if ( ! empty( $rows ) && is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    if ( ! is_array( $row ) ) continue;
                    $img_data = isset( $row[ $rc['sub'] ] ) ? $row[ $rc['sub'] ] : null;
                    if ( empty( $img_data ) ) continue;
                    // Image field trả về array (ACF image return format = array)
                    if ( is_array( $img_data ) && ! empty( $img_data['url'] ) ) {
                        $image_urls[] = [
                            'src' => $img_data['url'],
                            'alt' => ! empty( $img_data['alt'] ) ? $img_data['alt'] : $title,
                        ];
                    // Image field return format = ID
                    } elseif ( is_numeric( $img_data ) ) {
                        $src = wp_get_attachment_image_url( (int) $img_data, 'large' );
                        if ( $src ) {
                            $image_urls[] = [
                                'src' => $src,
                                'alt' => (string) get_post_meta( (int) $img_data, '_wp_attachment_image_alt', true ) ?: $title,
                            ];
                        }
                    // Image field return format = URL
                    } elseif ( is_string( $img_data ) && ! empty( $img_data ) ) {
                        $image_urls[] = [ 'src' => $img_data, 'alt' => $title ];
                    }
                }
                if ( ! empty( $image_urls ) ) break;
            }
        }

        // 2. Fallback: ACF Gallery field (nếu không có Repeater hoặc Repeater trống)
        if ( empty( $image_urls ) ) :
        $gallery_candidates = [
            'cac-hinh-anh-cua-phong', 'cac_hinh_anh_cua_phong',
            'cac-hinh-anh-phong', 'cac_hinh_anh_phong',
            'gallery', 'album', 'hinh_anh_phong',
            'cac-hinh-anh-cua-dia-chi', 'cac_hinh_anh_cua_dia_chi'
        ];

        foreach ( $gallery_candidates as $gc ) {
            $g_val = get_field( $gc, $r_id );
            if ( ! empty( $g_val ) && is_array( $g_val ) ) {
                foreach ( $g_val as $img_item ) {
                    if ( is_array( $img_item ) && ! empty( $img_item['url'] ) ) {
                        $image_urls[] = [
                            'src' => $img_item['url'],
                            'alt' => ! empty( $img_item['alt'] ) ? $img_item['alt'] : $title,
                        ];
                    } elseif ( is_numeric( $img_item ) ) {
                        $src = wp_get_attachment_image_url( (int) $img_item, 'large' );
                        if ( $src ) {
                            $image_urls[] = [
                                'src' => $src,
                                'alt' => (string) get_post_meta( (int) $img_item, '_wp_attachment_image_alt', true ) ?: $title,
                            ];
                        }
                    } elseif ( is_string( $img_item ) && ! empty( $img_item ) ) {
                        $image_urls[] = [ 'src' => $img_item, 'alt' => $title ];
                    }
                }
                if ( ! empty( $image_urls ) ) break;
            }
        }
        endif;

        // 2. Thử Featured Image
        $thumb_id = get_post_thumbnail_id( $r_id );
        if ( $thumb_id ) {
            $t_src = wp_get_attachment_image_url( $thumb_id, 'large' );
            if ( $t_src ) {
                // Thêm vào đầu nếu chưa có
                array_unshift( $image_urls, [
                    'src' => $t_src,
                    'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: $title,
                ] );
            }
        }

        // 3. Media library đính kèm
        if ( count( $image_urls ) < 2 ) {
            $attachments = get_attached_media( 'image', $r_id );
            if ( ! empty( $attachments ) ) {
                foreach ( $attachments as $att ) {
                    $att_src = wp_get_attachment_image_url( $att->ID, 'large' );
                    if ( $att_src ) {
                        $image_urls[] = [
                            'src' => $att_src,
                            'alt' => (string) get_post_meta( $att->ID, '_wp_attachment_image_alt', true ) ?: $title,
                        ];
                    }
                }
            }
        }

        // Khử trùng lặp ảnh theo URL
        $unique_images = [];
        $seen_urls     = [];
        foreach ( $image_urls as $img_obj ) {
            if ( ! in_array( $img_obj['src'], $seen_urls, true ) ) {
                $seen_urls[]     = $img_obj['src'];
                $unique_images[] = $img_obj;
            }
        }

        // Phân loại card: Compact (Selfbooth) vs Wide (Photobooth/Concept/Seasonal)
        $is_compact = false;
        if ( $atts['layout'] === 'grid' ) {
            $is_compact = true;
        } elseif ( $atts['layout'] === 'list' ) {
            $is_compact = false;
        } else {
            // Layout auto: Selfbooth hoặc 2 phòng đầu tiên nếu có từ self
            if ( stripos( $title, 'self' ) !== false ) {
                $is_compact = true;
            }
        }

        $rooms_data[] = [
            'id'          => $r_id,
            'index'       => $index,
            'title'       => $title,
            'link'        => $link,
            'room_number' => $room_number,
            'desc'        => $desc,
            'badge'       => $badge,
            'images'      => $unique_images,
            'is_compact'  => $is_compact,
        ];
    }

    // Tách thành 2 nhóm: Compact (hàng trên 2 cột) và Wide (hàng dưới full-width)
    $compact_rooms = [];
    $wide_rooms    = [];

    if ( $atts['layout'] === 'auto' ) {
        $total_rooms = count( $rooms_data );

        // 1. Lọc các phòng Selfbooth
        $self_rooms  = [];
        $other_rooms = [];
        foreach ( $rooms_data as $rm ) {
            if ( stripos( $rm['title'], 'self' ) !== false ) {
                $self_rooms[] = $rm;
            } else {
                $other_rooms[] = $rm;
            }
        }

        // Trường hợp 1: Có từ 2 phòng Selfbooth trở lên
        if ( count( $self_rooms ) >= 2 ) {
            // Lấy theo từng cặp chẵn (2, 4...) lên lưới 2 cột
            $even_pairs     = floor( count( $self_rooms ) / 2 ) * 2;
            $compact_rooms  = array_slice( $self_rooms, 0, $even_pairs );
            $remaining_self = array_slice( $self_rooms, $even_pairs );
            $wide_rooms     = array_merge( $remaining_self, $other_rooms );
        }
        // Trường hợp 2: Chỉ có 1 phòng Selfbooth (hoặc không có) và có từ 2 phòng trở lên
        // => Đưa 2 phòng đầu tiên lên hàng trên để lấp đầy 2 cột, TUYỆT ĐỐI KHÔNG ĐỂ BỎ TRỐNG 1 Ô!
        elseif ( $total_rooms >= 2 ) {
            $compact_rooms = array_slice( $rooms_data, 0, 2 );
            $wide_rooms    = array_slice( $rooms_data, 2 );
        }
        // Trường hợp 3: Chỉ có đúng 1 phòng duy nhất
        else {
            $compact_rooms = [];
            $wide_rooms    = $rooms_data;
        }
    } elseif ( $atts['layout'] === 'grid' ) {
        $compact_rooms = $rooms_data;
    } else {
        $wide_rooms = $rooms_data;
    }

    // Đánh số thứ tự hiển thị chuẩn xác (Room 1, Room 2, Room 3...) theo đúng vị trí trên màn hình
    $display_index = 0;
    foreach ( $compact_rooms as &$c_rm ) {
        $display_index++;
        $c_rm['room_number'] = 'Room ' . $display_index;
    }
    unset( $c_rm );

    foreach ( $wide_rooms as &$w_rm ) {
        $display_index++;
        $w_rm['room_number'] = 'Room ' . $display_index;
        // Cập nhật mô tả mẫu theo số thứ tự hiển thị nếu chưa có
        if ( empty( get_field( 'mieu_ta_concept', $w_rm['id'] ) ) && empty( get_field( 'concept', $w_rm['id'] ) ) && empty( get_field( 'mo_ta', $w_rm['id'] ) ) ) {
            $w_rm['desc'] = 'Miêu tả về concept phòng ' . str_pad( $display_index, 2, '0', STR_PAD_LEFT );
        }
    }
    unset( $w_rm );

    /* ----------------------------------------------------------
       4. Render HTML giao diện
    ---------------------------------------------------------- */
    static $dsp_instance = 0;
    $dsp_instance++;
    $uid = 'memora-dsp-' . $dsp_instance;

    // SVG icon ngôi sao encode cho pagination mask
    $star_mask = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='m19.555 23.411-6.664-3.285a1.26 1.26 0 0 0-1.202.045l.006-.003-6.416 3.75a.61.61 0 0 1-.902-.626v.003l.994-7.542q.01-.075.011-.162c0-.364-.155-.691-.403-.92l-.001-.001-4.571-4.247a1.265 1.265 0 0 1 .648-2.17l.007-.001 5.987-1.108c.421-.078.765-.355.935-.727l.003-.008L10.478.746a1.272 1.272 0 0 1 2.271-.087l.003.007 2.881 5.471c.197.365.558.62.981.666h.006l6.045.681a1.265 1.265 0 0 1 .811 2.119l.001-.001-4.27 4.562a1.25 1.25 0 0 0-.315 1.116l-.001-.008 1.52 7.453q.014.061.015.134a.61.61 0 0 1-.875.549z'/%3E%3C/svg%3E\") no-repeat center / contain";

    ob_start();
    ?>
    <div class="memora-dsp-container" id="<?php echo esc_attr( $uid ); ?>">

        <?php if ( ! empty( $compact_rooms ) ) : ?>
        <!-- HÀNG TRÊN: LƯỚI 2 CỘT CHO CÁC PHÒNG SELFBOOTH -->
        <div class="memora-dsp-compact-grid">
            <?php foreach ( $compact_rooms as $c_idx => $c_room ) : 
                $slider_id = $uid . '-cslider-' . $c_idx;
                $has_slides = ! empty( $c_room['images'] );
            ?>
            <div class="memora-dsp-compact-card">
                <!-- SLIDER POSTER 1:1 -->
                <div class="memora-dsp-compact-poster">
                    <div class="swiper memora-room-swiper" id="<?php echo esc_attr( $slider_id ); ?>" data-loop="<?php echo count( $c_room['images'] ) >= 2 ? 'true' : 'false'; ?>">
                        <div class="swiper-wrapper">
                            <?php if ( $has_slides ) : ?>
                                <?php foreach ( $c_room['images'] as $img ) : ?>
                                <div class="swiper-slide">
                                    <a href="<?php echo esc_url( $c_room['link'] ); ?>">
                                        <img src="<?php echo esc_url( $img['src'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy" />
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="swiper-slide">
                                    <div class="memora-dsp-no-img"><span>Memora Photo Room</span></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ( count( $c_room['images'] ) > 1 ) : ?>
                        <div class="swiper-pagination memora-star-pagination"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TIÊU ĐỀ PHÒNG: Room 1 Selfbooth Room -->
                <a href="<?php echo esc_url( $c_room['link'] ); ?>" class="memora-dsp-compact-meta">
                    <span class="memora-dsp-room-num"><?php echo esc_html( $c_room['room_number'] ); ?></span>
                    <span class="memora-dsp-room-name"><?php echo esc_html( $c_room['title'] ); ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $wide_rooms ) ) : ?>
        <!-- HÀNG DƯỚI: CÁC CARD NGANG CHO PHÒNG CONCEPT, PHOTOBOOTH, SEASONAL -->
        <div class="memora-dsp-wide-list">
            <?php foreach ( $wide_rooms as $w_idx => $w_room ) : 
                $slider_id = $uid . '-wslider-' . $w_idx;
                $has_slides = ! empty( $w_room['images'] );
            ?>
            <div class="memora-dsp-wide-card">

                <!-- CỘT TRÁI: THÔNG TIN PHÒNG -->
                <div class="memora-dsp-wide-info">
                    

                    <div class="memora-dsp-room-num memora-dsp-room-num--large">
                        <?php echo esc_html( $w_room['room_number'] ); ?>
                    </div>

                    <h3 class="memora-dsp-wide-title">
                        <a href="<?php echo esc_url( $w_room['link'] ); ?>"><?php echo esc_html( $w_room['title'] ); ?></a>
                    </h3>

                    <p class="memora-dsp-wide-desc">
                        <?php echo esc_html( $w_room['desc'] ); ?>
                    </p>

                    <div class="memora-dsp-wide-action">
                        <a href="<?php echo esc_url( $w_room['link'] ); ?>" class="memora-dsp-btn-more">
                            <?php echo esc_html( $atts['button_text'] ); ?>
                        </a>
                    </div>
                </div>

                <!-- CỘT PHẢI: SLIDER ẢNH -->
                <div class="memora-dsp-wide-slider-wrap">
                    
                    <div class="memora-dsp-badge">
                        <img src="/wp-content/uploads/2026/10/special-room.png">
                    </div>
                    
                    <div class="swiper memora-room-swiper" id="<?php echo esc_attr( $slider_id ); ?>" data-loop="<?php echo count( $w_room['images'] ) >= 2 ? 'true' : 'false'; ?>">
                        <div class="swiper-wrapper">
                            <?php if ( $has_slides ) : ?>
                                <?php foreach ( $w_room['images'] as $img ) : ?>
                                <div class="swiper-slide">
                                    <a href="<?php echo esc_url( $w_room['link'] ); ?>">
                                        <img src="<?php echo esc_url( $img['src'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy" />
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="swiper-slide">
                                    <div class="memora-dsp-no-img"><span>Memora Photo Room</span></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ( count( $w_room['images'] ) > 1 ) : ?>
                        <div class="swiper-pagination memora-star-pagination"></div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- STYLESHEET CHUẨN DESIGN MEMORA -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playball&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Mulish:wght@300;400;500;600;700&display=swap');

        #<?php echo esc_attr( $uid ); ?>.memora-dsp-container {
            width: 100%;
            margin: 0 auto;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #733e1c;
            box-sizing: border-box;
            font-size: 3px;
        }

        #<?php echo esc_attr( $uid ); ?> * {
            box-sizing: border-box;
        }

        /* -------------------------------------------
           Chữ "Room 1", "Room 2" font nghệ thuật Script
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-room-num {
            font-family: "Anastasia Script", cursive, Georgia, serif;
            color: #733e1c;
            font-size: 6.5em;
            line-height: 1.1;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-room-num--large {
                font-size: 16em;
    font-weight: 700;
    margin-bottom: -0.05em;
        }

        /* -------------------------------------------
           HÀNG TRÊN: LƯỚI 2 CỘT COMPACT CARDS
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            margin-bottom: 40px;
            width: 100%;
            max-width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            position: relative;
        }

        /* Safeguard: Nếu có trường hợp chỉ có 1 thẻ duy nhất trong grid, tự động căn giữa */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-card:only-child {
            grid-column: 1 / -1;
            max-width: 560px;
            margin: 0 auto;
        }

        /* Khung ảnh poster không bo góc */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster {
            width: 100%;
            max-width: 100%;
            border-radius: 0;
            overflow: hidden;
            background-color: #f7f3ef;
            position: relative;
            box-shadow: 0 4px 16px rgba(115, 62, 28, 0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(115, 62, 28, 0.12);
        }

        /* Swiper flow tự nhiên theo chiều cao ảnh */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster .swiper {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster .swiper-wrapper {
            display: flex;
            width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster .swiper-slide {
            width: 100%;
            flex-shrink: 0;
            overflow: hidden;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster .swiper-slide a {
            display: block;
            width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-poster .swiper-slide img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Tiêu đề bên dưới poster: Room 1 Selfbooth Room */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-meta {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
            text-decoration: none;
            color: #733e1c;
            transition: opacity 0.2s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-meta:hover {
            opacity: 0.85;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-compact-meta .memora-dsp-room-name {
            font-family: "Cocomat Pro", sans-serif;
            font-size: 4.5em;
            font-weight: 700;
            color: #733e1c;
        }

        /* -------------------------------------------
           HÀNG DƯỚI: WIDE CARDS (HÀNG NGANG RỘNG)
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-list {
            display: flex;
            flex-direction: column;
            gap: 36px;
            width: 100%;
            max-width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-card {
            display: flex;
            align-items: center;
            gap: 40px;
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        /* Cột thông tin bên trái */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-info {
                min-width: 40em;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
        }

        /* Badge "Special Room" — overlay ảnh PNG lên slider */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap {
            position: relative;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-badge {
             position: absolute;
            z-index: 2;
            width: 121px;
            top: -20px;
            left: -33px;
            transform: rotate(-12deg);
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-badge img {
            width: auto;
            height: auto;
            max-width: 120px;
            display: block;
        }

        /* Tiêu đề phòng lớn */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-title {
            margin: 0 0 0.364em 0;
            font-family: "Cocomat Pro", sans-serif;
            font-size: 5.5em;
            font-weight: 700;
            line-height: 1.3;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-title a {
            color: #733e1c;
            text-decoration: none;
            transition: opacity 0.2s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-title a:hover {
            opacity: 0.8;
        }

        /* Đoạn miêu tả concept */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-desc {
                      margin: 0 0 1.129em 0;
                    font-family: "Mulish", sans-serif;
                    font-size: 4.6em;
                    line-height: 1.4;
                    color: #7a6a5f;
                    max-width: 150px;
                    white-space: wrap;
                    text-align: center;
        }

        /* Nút "Tìm hiểu thêm" viên thuốc màu nâu */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-btn-more {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        background-color: #733e1c;
                        color: #ffffff !important;
                        padding: 0.443em 1.314em;
                        border-radius: 9999px;
                        font-family: "Cocomat Pro", Sans-serif;
                        font-size: 4.8em;
                        font-weight: 600;
                        text-decoration: none;
                        box-shadow: 0 4px 12px rgba(115, 62, 28, 0.22);
                        transition: background-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-btn-more:hover {
            background-color: #572e14;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(115, 62, 28, 0.32);
        }

        /* Cột slider bên phải không bo góc */
        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap {
            flex: 1 1 0;
            min-width: 0;
            max-width: 100%;
            border-radius: 0;
            background-color: #f7f3ef;
            box-shadow: 0 4px 18px rgba(115, 62, 28, 0.08);
            position: relative;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap .swiper {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap .swiper-wrapper {
            display: flex;
            width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap .swiper-slide {
            width: 100%;
            flex-shrink: 0;
            overflow: hidden;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap .swiper-slide a {
            display: block;
            width: 100%;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-wide-slider-wrap .swiper-slide img {
            width: 100%;
            height: auto;
            display: block;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-dsp-no-img {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0e9e1;
            color: #a89a8f;
            font-size: 14px;
            font-weight: 500;
        }

        /* -------------------------------------------
           PAGINATION HÌNH NGÔI SAO MÀU NÂU #733e1c
        ------------------------------------------- */
        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination {
            bottom: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: auto;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet {
            width: 18px;
            height: 18px;
            background: transparent !important;
            opacity: 1;
            margin: 0 3px;
            border-radius: 0;
            position: relative;
            cursor: pointer;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet::after {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(115, 62, 28, 0.45);
            -webkit-mask: <?php echo $star_mask; ?>;
                    mask: <?php echo $star_mask; ?>;
            transition: background-color 0.25s ease, transform 0.25s ease;
        }

        #<?php echo esc_attr( $uid ); ?> .memora-star-pagination .swiper-pagination-bullet-active::after {
            background-color: #733e1c;
            transform: scale(1.3);
        }


    </style>

    <!-- SCRIPT KHỞI TẠO TẤT CẢ SWIPER SLIDERS -->
    <script>
    (function () {
        'use strict';
        var CONTAINER_ID = '<?php echo esc_js( $uid ); ?>';

        function initAllRoomSwipers() {
            var container = document.getElementById(CONTAINER_ID);
            if (!container) return;

            if (typeof Swiper === 'undefined') {
                setTimeout(initAllRoomSwipers, 250);
                return;
            }

            var sliders = container.querySelectorAll('.memora-room-swiper');
            sliders.forEach(function (sliderEl) {
                if (sliderEl.swiper) return; // Đã init rồi

                var shouldLoop = sliderEl.getAttribute('data-loop') === 'true';
                var paginationEl = sliderEl.querySelector('.memora-star-pagination');

                new Swiper(sliderEl, {
                    speed: <?php echo (int) $atts['speed']; ?>,
                    loop: shouldLoop,
                    autoplay: {
                        delay: <?php echo (int) $atts['autoplay']; ?>,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    },
                    pagination: paginationEl ? {
                        el: paginationEl,
                        clickable: true,
                    } : false,
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAllRoomSwipers);
        } else {
            initAllRoomSwipers();
        }

        // Tương thích Elementor editor re-render
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                setTimeout(initAllRoomSwipers, 150);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
