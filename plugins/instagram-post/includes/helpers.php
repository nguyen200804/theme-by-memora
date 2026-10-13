<?php
/**
 * Helper Functions cho Plugin Instagram Post
 */

// Chống truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - HÀM LẤY CẤU HÌNH PLUGIN TỪ DATABASE
//====================================
if ( ! function_exists( 'memora_ig_get_settings' ) ) {
    /**
     * Lấy toàn bộ cấu hình Setting Instagram kèm giá trị mặc định chuẩn
     *
     * @return array
     */
    function memora_ig_get_settings() {
        $defaults = array(
            'access_token'     => '',
            'instagram_url'    => 'https://instagram.com/memora_film',
            'username'         => '',
            'user_id'          => '',
            'post_count'       => 6,
            'columns'          => 4,
            'gap'              => 16,
            'aspect_ratio'     => '1/1',
            'border_radius'    => 10,
            'show_button'      => 1,
            'button_text'      => 'Theo dõi @memora trên Instagram',
            'show_caption'     => 1,
            'cache_time_hours' => 4,
            'token_expires_at' => '',
            'last_sync_time'   => '',
        );

        $saved = get_option( 'memora_instagram_settings', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, $defaults );
    }
}
//====================================
// END - HÀM LẤY CẤU HÌNH PLUGIN TỪ DATABASE
//====================================





//====================================
// START - HÀM TIỆN ÍCH XỬ LÝ URL VÀ NỘI DUNG BÀI VIẾT
//====================================
if ( ! function_exists( 'memora_ig_get_profile_url' ) ) {
    /**
     * Lấy đường dẫn trang cá nhân Instagram
     */
    function memora_ig_get_profile_url() {
        $settings = memora_ig_get_settings();
        $url = ! empty( $settings['instagram_url'] ) ? trim( $settings['instagram_url'] ) : '';

        if ( empty( $url ) && ! empty( $settings['username'] ) ) {
            $url = 'https://www.instagram.com/' . ltrim( $settings['username'], '@' );
        }

        if ( empty( $url ) ) {
            $url = 'https://www.instagram.com/';
        }

        return esc_url( $url );
    }
}

if ( ! function_exists( 'memora_ig_truncate_caption' ) ) {
    /**
     * Cắt ngắn caption hiển thị trên hover
     *
     * @param string $caption Nội dung gốc
     * @param int $limit Số ký tự tối đa
     * @return string
     */
    function memora_ig_truncate_caption( $caption, $limit = 90 ) {
        $caption = wp_strip_all_tags( $caption );
        if ( mb_strlen( $caption, 'UTF-8' ) <= $limit ) {
            return $caption;
        }
        return mb_substr( $caption, 0, $limit, 'UTF-8' ) . '...';
    }
}
//====================================
// END - HÀM TIỆN ÍCH XỬ LÝ URL VÀ NỘI DUNG BÀI VIẾT
//====================================
