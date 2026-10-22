<?php
/**
 * Shortcodes xuất thông tin Địa Chỉ (taxonomy: dia-chi)
 *
 * Các shortcode đọc ?dia_chi_id=X hoặc ?phong_id=Y từ URL,
 * tra cứu term taxonomy 'dia-chi' và xuất thông tin tương ứng.
 *
 * Shortcodes:
 *   [ten_dia_chi]           — Xuất tên chi nhánh (VD: "Hà Nội - Lê Duẩn")
 *   [dia_chi_info]          — Xuất block thông tin đầy đủ (tên + địa chỉ + ACF fields)
 *   [dia_chi_field field="x"] — Xuất 1 ACF field bất kỳ của term
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


//====================================
// START - HELPER: LẤY TERM ID TỪ URL
//====================================
/**
 * Đọc dia_chi_id từ URL params, hỗ trợ cả ?dia_chi_id= và ?phong_id=
 */
function memora_resolve_dia_chi_id_from_url() {
    // 1. ?dia_chi_id=X trực tiếp
    if ( ! empty( $_GET['dia_chi_id'] ) && intval( $_GET['dia_chi_id'] ) > 0 ) {
        return intval( $_GET['dia_chi_id'] );
    }

    // 2. ?phong_id=Y → tra ngược qua taxonomy
    if ( ! empty( $_GET['phong_id'] ) && intval( $_GET['phong_id'] ) > 0 ) {
        $phong_id = intval( $_GET['phong_id'] );
        $terms    = wp_get_object_terms( $phong_id, 'dia-chi' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            return (int) $terms[0]->term_id;
        }
    }

    // 3. Đang ở trang taxonomy archive dia-chi
    if ( is_tax( 'dia-chi' ) ) {
        $queried = get_queried_object();
        if ( $queried instanceof WP_Term ) {
            return (int) $queried->term_id;
        }
    }

    return 0;
}

/**
 * Lấy WP_Term từ URL, có cache 1 lần/request
 */
function memora_get_dia_chi_term_from_url() {
    static $cached_term = null;
    if ( $cached_term !== null ) {
        return $cached_term;
    }

    $term_id = memora_resolve_dia_chi_id_from_url();
    if ( $term_id <= 0 ) {
        $cached_term = false;
        return false;
    }

    $term = get_term( $term_id, 'dia-chi' );
    $cached_term = ( $term && ! is_wp_error( $term ) ) ? $term : false;
    return $cached_term;
}
//====================================
// END - HELPER: LẤY TERM ID TỪ URL
//====================================




//====================================
// START - SHORTCODE [ten_dia_chi]
// Xuất tên chi nhánh từ ?dia_chi_id= hoặc ?phong_id=
// Dùng: [ten_dia_chi] hoặc [ten_dia_chi id="13"]
//====================================
add_shortcode( 'ten_dia_chi', 'memora_shortcode_ten_dia_chi' );
function memora_shortcode_ten_dia_chi( $atts ) {
    $atts = shortcode_atts( [
        'id'       => 0,   // Truyền cứng: [ten_dia_chi id="13"]
        'fallback' => '',  // Hiển thị nếu không tìm thấy
        'prefix'   => '',  // Thêm trước tên: [ten_dia_chi prefix="Chi nhánh: "]
        'tag'      => '',  // Wrap bằng tag HTML: span | h1 | h2 | p ...
        'class'    => '',
    ], $atts, 'ten_dia_chi' );

    $term = null;

    // Ưu tiên id từ attribute
    if ( intval( $atts['id'] ) > 0 ) {
        $t = get_term( intval( $atts['id'] ), 'dia-chi' );
        if ( $t && ! is_wp_error( $t ) ) {
            $term = $t;
        }
    }

    // Fallback: đọc từ URL
    if ( ! $term ) {
        $term = memora_get_dia_chi_term_from_url();
    }

    if ( ! $term ) {
        return ! empty( $atts['fallback'] )
            ? '<span class="memora-dia-chi-fallback">' . esc_html( $atts['fallback'] ) . '</span>'
            : '';
    }

    $name   = $atts['prefix'] . $term->name;
    $output = esc_html( $name );

    // Wrap tag
    if ( ! empty( $atts['tag'] ) ) {
        $allowed_tags = [ 'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'strong', 'em' ];
        $tag          = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'span';
        $class_attr   = ! empty( $atts['class'] ) ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        $output       = "<{$tag}{$class_attr}>{$output}</{$tag}>";
    }

    return $output;
}
//====================================
// END - SHORTCODE [ten_dia_chi]
//====================================




//====================================
// START - SHORTCODE [dia_chi_field]
// Xuất ACF field bất kỳ của term dia-chi
// Dùng: [dia_chi_field field="dia_chi_day_du"]
//       [dia_chi_field field="so_dien_thoai" id="13"]
//====================================
add_shortcode( 'dia_chi_field', 'memora_shortcode_dia_chi_field' );
function memora_shortcode_dia_chi_field( $atts ) {
    $atts = shortcode_atts( [
        'field'    => '',  // Tên ACF field (bắt buộc)
        'id'       => 0,
        'fallback' => '',
        'tag'      => '',
        'class'    => '',
    ], $atts, 'dia_chi_field' );

    if ( empty( $atts['field'] ) ) {
        return '';
    }

    $term = null;
    if ( intval( $atts['id'] ) > 0 ) {
        $t = get_term( intval( $atts['id'] ), 'dia-chi' );
        if ( $t && ! is_wp_error( $t ) ) {
            $term = $t;
        }
    }
    if ( ! $term ) {
        $term = memora_get_dia_chi_term_from_url();
    }
    if ( ! $term ) {
        return esc_html( $atts['fallback'] );
    }

    // Đọc ACF field từ term
    $value = '';
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $atts['field'], 'dia-chi_' . $term->term_id );
    }
    // Fallback: term meta thường
    if ( empty( $value ) ) {
        $value = get_term_meta( $term->term_id, $atts['field'], true );
    }
    if ( empty( $value ) ) {
        return esc_html( $atts['fallback'] );
    }

    $output = esc_html( $value );

    if ( ! empty( $atts['tag'] ) ) {
        $allowed_tags = [ 'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'strong', 'em', 'a' ];
        $tag          = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'span';
        $class_attr   = ! empty( $atts['class'] ) ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        $output       = "<{$tag}{$class_attr}>{$output}</{$tag}>";
    }

    return $output;
}
//====================================
// END - SHORTCODE [dia_chi_field]
//====================================




//====================================
// START - SHORTCODE [dia_chi_info]
// Xuất block thông tin đầy đủ của chi nhánh
// Dùng: [dia_chi_info] hoặc [dia_chi_info id="13" show="name,address,phone"]
//====================================
add_shortcode( 'dia_chi_info', 'memora_shortcode_dia_chi_info' );
function memora_shortcode_dia_chi_info( $atts ) {
    $atts = shortcode_atts( [
        'id'      => 0,
        'show'    => 'name,address',  // name | address | phone | map | all
        'class'   => 'memora-dia-chi-info',
        'fallback'=> '',
    ], $atts, 'dia_chi_info' );

    $term = null;
    if ( intval( $atts['id'] ) > 0 ) {
        $t = get_term( intval( $atts['id'] ), 'dia-chi' );
        if ( $t && ! is_wp_error( $t ) ) {
            $term = $t;
        }
    }
    if ( ! $term ) {
        $term = memora_get_dia_chi_term_from_url();
    }
    if ( ! $term ) {
        return ! empty( $atts['fallback'] )
            ? '<p class="memora-dia-chi-fallback">' . esc_html( $atts['fallback'] ) . '</p>'
            : '';
    }

    $show   = array_map( 'trim', explode( ',', $atts['show'] ) );
    $all    = in_array( 'all', $show, true );
    $tid    = $term->term_id;
    $prefix = 'dia-chi_' . $tid;

    // Lấy các field ACF phổ biến
    $acf = function( $key ) use ( $prefix, $tid ) {
        if ( ! function_exists( 'get_field' ) ) {
            return get_term_meta( $tid, $key, true ) ?: '';
        }
        return get_field( $key, $prefix )
            ?: get_term_meta( $tid, $key, true )
            ?: '';
    };

    ob_start();
    echo '<div class="' . esc_attr( $atts['class'] ) . '">';

    // Tên chi nhánh
    if ( $all || in_array( 'name', $show, true ) ) {
        echo '<span class="memora-dc-name">' . esc_html( $term->name ) . '</span>';
    }

    // Địa chỉ
    if ( $all || in_array( 'address', $show, true ) ) {
        $addr = $acf( 'dia_chi_day_du' ) ?: $acf( 'address' ) ?: $acf( 'dia_chi' ) ?: $acf( 'diachi' );
        if ( $addr ) {
            echo '<span class="memora-dc-address">📍 ' . esc_html( $addr ) . '</span>';
        }
    }

    // Số điện thoại
    if ( $all || in_array( 'phone', $show, true ) ) {
        $phone = $acf( 'so_dien_thoai' ) ?: $acf( 'phone' ) ?: $acf( 'dien_thoai' );
        if ( $phone ) {
            echo '<span class="memora-dc-phone">📞 <a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></span>';
        }
    }

    // Google Map embed
    if ( $all || in_array( 'map', $show, true ) ) {
        $map_url = $acf( 'google_map_url' ) ?: $acf( 'map_url' ) ?: $acf( 'ban_do' );
        if ( $map_url ) {
            echo '<div class="memora-dc-map"><iframe src="' . esc_url( $map_url ) . '" width="100%" height="200" frameborder="0" allowfullscreen loading="lazy"></iframe></div>';
        }
    }

    echo '</div>';
    return ob_get_clean();
}
//====================================
// END - SHORTCODE [dia_chi_info]
//====================================




//====================================
// START - SẮP XẾP THỨ TỰ TERM TAXONOMY: dia-chi
// Hỗ trợ kéo-thả (Drag & Drop) trực tiếp trong WP Admin
// và tự động đồng bộ thứ tự ra Elementor Loop Grid + [dia_chi_noi_bat]
//====================================

/**
 * Lấy thứ tự hiển thị của 1 term dia-chi (mặc định 999999 nếu chưa đặt)
 */
function memora_get_dia_chi_term_order( $term_id ) {
    $val = get_term_meta( (int) $term_id, '_memora_term_order', true );
    if ( $val === '' || $val === false || $val === null ) {
        return 999999;
    }
    return intval( $val );
}

/**
 * 1. Thêm trường "Thứ tự hiển thị" vào form Thêm mới & Chỉnh sửa term dia-chi
 */
add_action( 'dia-chi_add_form_fields', 'memora_dia_chi_add_order_field' );
function memora_dia_chi_add_order_field() {
    ?>
    <div class="form-field term-order-wrap">
        <label for="memora_term_order">Thứ tự hiển thị</label>
        <input type="number" name="memora_term_order" id="memora_term_order" value="0" min="0" step="1" style="width: 100px;" />
        <p class="description">Số nhỏ hiển thị trước (1, 2, 3...). Bạn cũng có thể kéo-thả trực tiếp các dòng ở bảng bên phải.</p>
    </div>
    <?php
}

add_action( 'dia-chi_edit_form_fields', 'memora_dia_chi_edit_order_field', 10, 2 );
function memora_dia_chi_edit_order_field( $term ) {
    $order = get_term_meta( $term->term_id, '_memora_term_order', true );
    ?>
    <tr class="form-field term-order-wrap">
        <th scope="row"><label for="memora_term_order">Thứ tự hiển thị</label></th>
        <td>
            <input type="number" name="memora_term_order" id="memora_term_order" value="<?php echo esc_attr( $order !== '' ? intval( $order ) : '' ); ?>" min="0" step="1" style="width: 100px;" placeholder="Tự động" />
            <p class="description">Số nhỏ hiển thị trước (1, 2, 3...). Hoặc kéo-thả trực tiếp ngoài bảng danh sách Địa chỉ.</p>
        </td>
    </tr>
    <?php
}

/**
 * 2. Lưu meta _memora_term_order khi tạo mới hoặc cập nhật term
 */
add_action( 'created_dia-chi', 'memora_save_dia_chi_order_meta', 10, 2 );
add_action( 'edited_dia-chi', 'memora_save_dia_chi_order_meta', 10, 2 );
function memora_save_dia_chi_order_meta( $term_id ) {
    if ( ! isset( $_POST['memora_term_order'] ) ) {
        return;
    }
    $raw = trim( sanitize_text_field( wp_unslash( $_POST['memora_term_order'] ) ) );
    if ( $raw === '' ) {
        delete_term_meta( $term_id, '_memora_term_order' );
    } else {
        update_term_meta( $term_id, '_memora_term_order', intval( $raw ) );
    }
}

/**
 * 3. Thêm cột "Thứ tự" (có icon kéo-thả) vào bảng danh sách Địa chỉ trong WP Admin
 */
add_filter( 'manage_edit-dia-chi_columns', 'memora_dia_chi_add_order_column' );
function memora_dia_chi_add_order_column( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $label ) {
        if ( $key === 'name' ) {
            $new_columns['memora_order'] = 'Thứ tự';
        }
        $new_columns[ $key ] = $label;
    }
    if ( ! isset( $new_columns['memora_order'] ) ) {
        $new_columns['memora_order'] = 'Thứ tự';
    }
    return $new_columns;
}

add_filter( 'manage_dia-chi_custom_column', 'memora_dia_chi_render_order_column', 10, 3 );
function memora_dia_chi_render_order_column( $content, $column_name, $term_id ) {
    if ( $column_name !== 'memora_order' ) {
        return $content;
    }
    $order = get_term_meta( $term_id, '_memora_term_order', true );
    $badge = ( $order !== '' && $order !== false ) ? intval( $order ) : '—';
    return '<div class="memora-term-drag-handle" title="Giữ chuột và kéo lên/xuống để đổi thứ tự" data-term-id="' . esc_attr( $term_id ) . '">'
        . '<span class="dashicons dashicons-menu"></span>'
        . '<span class="memora-term-order-badge">' . esc_html( $badge ) . '</span>'
        . '</div>';
}

/**
 * 4. Nạp jQuery UI Sortable + JS/CSS kéo-thả trên trang edit-tags.php?taxonomy=dia-chi
 */
add_action( 'admin_enqueue_scripts', 'memora_dia_chi_sortable_admin_assets' );
function memora_dia_chi_sortable_admin_assets( $hook ) {
    if ( $hook !== 'edit-tags.php' ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || $screen->taxonomy !== 'dia-chi' ) {
        return;
    }
    wp_enqueue_script( 'jquery-ui-sortable' );
}

add_action( 'admin_footer-edit-tags.php', 'memora_dia_chi_sortable_admin_footer' );
function memora_dia_chi_sortable_admin_footer() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->taxonomy !== 'dia-chi' ) {
        return;
    }
    $nonce = wp_create_nonce( 'memora_dia_chi_sort_nonce' );
    ?>
    <style>
        .column-memora_order {
            width: 82px !important;
            text-align: center !important;
        }
        #the-list tr {
            transition: background-color 0.15s ease;
        }
        .memora-term-drag-handle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: grab;
            padding: 4px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-weight: 600;
            font-size: 12px;
            user-select: none;
        }
        .memora-term-drag-handle:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .memora-term-drag-handle:active {
            cursor: grabbing;
        }
        .memora-term-drag-handle .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #64748b;
        }
        .memora-term-sort-placeholder {
            background: #fef9c3 !important;
            border: 2px dashed #eab308 !important;
            height: 52px;
        }
        .ui-sortable-helper {
            background: #ffffff !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.14) !important;
            display: table !important;
        }
        #memora-sort-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            background: #0f172a;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 6px 18px rgba(0,0,0,0.18);
            display: none;
        }
    </style>
    <div id="memora-sort-toast"></div>
    <script>
    jQuery(function($) {
        var $list = $('#the-list');
        if (!$list.length || typeof $list.sortable !== 'function') return;

        function showToast(msg, isError) {
            var $t = $('#memora-sort-toast');
            $t.text(msg).css('background', isError ? '#dc2626' : '#0f172a').stop(true, true).fadeIn(150);
            setTimeout(function() { $t.fadeOut(250); }, 2200);
        }

        $list.sortable({
            items: 'tr[id^="tag-"]',
            cursor: 'grabbing',
            axis: 'y',
            placeholder: 'memora-term-sort-placeholder',
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function() {
                var termIds = [];
                $list.find('tr[id^="tag-"]').each(function(idx) {
                    var idAttr = $(this).attr('id') || '';
                    var termId = parseInt(idAttr.replace('tag-', ''), 10);
                    if (termId > 0) {
                        termIds.push(termId);
                        $(this).find('.memora-term-order-badge').text(idx + 1);
                    }
                });

                if (!termIds.length) return;

                $.post(ajaxurl, {
                    action: 'memora_save_dia_chi_term_order',
                    nonce: <?php echo wp_json_encode( $nonce ); ?>,
                    term_ids: termIds
                }).done(function(res) {
                    if (res && res.success) {
                        showToast('✓ Đã lưu thứ tự hiển thị Địa chỉ!', false);
                    } else {
                        showToast('Lỗi khi lưu thứ tự!', true);
                    }
                }).fail(function() {
                    showToast('Lỗi kết nối khi lưu thứ tự!', true);
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * 5. AJAX lưu thứ tự kéo-thả
 */
add_action( 'wp_ajax_memora_save_dia_chi_term_order', 'memora_ajax_save_dia_chi_term_order' );
function memora_ajax_save_dia_chi_term_order() {
    check_ajax_referer( 'memora_dia_chi_sort_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Không có quyền thực hiện.' ] );
    }

    $term_ids = isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] )
        ? array_map( 'intval', $_POST['term_ids'] )
        : [];

    if ( empty( $term_ids ) ) {
        wp_send_json_error( [ 'message' => 'Danh sách trống.' ] );
    }

    $clean_ids = [];
    foreach ( array_values( $term_ids ) as $index => $term_id ) {
        if ( $term_id > 0 ) {
            update_term_meta( $term_id, '_memora_term_order', $index + 1 );
            $clean_ids[] = $term_id;
        }
    }

    if ( ! empty( $clean_ids ) ) {
        clean_term_cache( $clean_ids, 'dia-chi' );
    }

    wp_send_json_success( [ 'updated' => count( $clean_ids ) ] );
}

/**
 * 6. Tự động áp dụng thứ tự _memora_term_order cho mọi truy vấn taxonomy 'dia-chi'
 *    (Bao gồm: Elementor Loop Grid, shortcode [dia_chi_noi_bat], bảng WP Admin, và CRM)
 */
add_filter( 'terms_clauses', 'memora_dia_chi_order_terms_clauses', 999, 3 );
function memora_dia_chi_order_terms_clauses( $clauses, $taxonomies, $args ) {
    if ( ! is_array( $taxonomies ) || count( $taxonomies ) !== 1 || ! in_array( 'dia-chi', $taxonomies, true ) ) {
        return $clauses;
    }

    // Không can thiệp vào truy vấn đếm số lượng (COUNT)
    if ( isset( $clauses['fields'] ) && stripos( $clauses['fields'], 'COUNT' ) !== false ) {
        return $clauses;
    }

    // Trong WP Admin: nếu người dùng bấm tiêu đề cột khác để sort tạm thời thì tôn trọng
    if ( is_admin() && ! wp_doing_ajax() && ! empty( $_GET['orderby'] ) && $_GET['orderby'] !== 'memora_order' ) {
        return $clauses;
    }

    global $wpdb;
    if ( strpos( $clauses['join'], 'memora_tm_order' ) === false ) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS memora_tm_order ON (t.term_id = memora_tm_order.term_id AND memora_tm_order.meta_key = '_memora_term_order') ";
    }

    $custom_order_sql = "COALESCE(CAST(NULLIF(memora_tm_order.meta_value, '') AS SIGNED), 999999) ASC";
    if ( ! empty( $clauses['orderby'] ) && preg_match( '/^ORDER BY\s+/i', $clauses['orderby'] ) ) {
        $clauses['orderby'] = preg_replace(
            '/^ORDER BY\s+/i',
            'ORDER BY ' . $custom_order_sql . ', ',
            $clauses['orderby'],
            1
        );
    } else {
        $clauses['orderby'] = 'ORDER BY ' . $custom_order_sql;
        $clauses['order']   = '';
    }

    return $clauses;
}

add_filter( 'get_terms', 'memora_dia_chi_sort_get_terms_result', 999, 4 );
function memora_dia_chi_sort_get_terms_result( $terms, $taxonomies, $args, $term_query ) {
    if ( ! is_array( $taxonomies ) || count( $taxonomies ) !== 1 || ! in_array( 'dia-chi', $taxonomies, true ) ) {
        return $terms;
    }
    if ( ! is_array( $terms ) || count( $terms ) < 2 ) {
        return $terms;
    }

    // Chỉ sắp xếp khi mảng kết quả là danh sách WP_Term objects
    $first = reset( $terms );
    if ( ! ( $first instanceof WP_Term ) ) {
        return $terms;
    }

    if ( is_admin() && ! wp_doing_ajax() && ! empty( $_GET['orderby'] ) && $_GET['orderby'] !== 'memora_order' ) {
        return $terms;
    }

    usort( $terms, function( $a, $b ) {
        $order_a = memora_get_dia_chi_term_order( $a->term_id );
        $order_b = memora_get_dia_chi_term_order( $b->term_id );
        if ( $order_a !== $order_b ) {
            return ( $order_a < $order_b ) ? -1 : 1;
        }
        return strcmp( $b->name, $a->name );
    } );

    return array_values( $terms );
}
//====================================
// END - SẮP XẾP THỨ TỰ TERM TAXONOMY: dia-chi
//====================================
