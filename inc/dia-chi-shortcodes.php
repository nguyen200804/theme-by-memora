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
