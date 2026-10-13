<?php
/**
 * Class xử lý kết nối Instagram Graph API và Caching
 */

// Chống truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - CLASS QUẢN LÝ INSTAGRAM GRAPH API
//====================================
if ( ! class_exists( 'Memora_IG_API' ) ) {

    class Memora_IG_API {

        /**
         * Endpoint Graph API cơ bản của Instagram
         */
        const GRAPH_API_BASE = 'https://graph.instagram.com/';

        /**
         * Lấy danh sách bài viết từ Instagram (có Transient Cache)
         *
         * @param int  $limit         Số lượng bài viết cần lấy
         * @param bool $force_refresh Bắt buộc lấy mới bỏ qua cache
         * @return array|WP_Error
         */
        public static function fetch_posts( $limit = 6, $force_refresh = false ) {
            $settings = memora_ig_get_settings();
            $token    = ! empty( $settings['access_token'] ) ? trim( $settings['access_token'] ) : '';

            if ( empty( $token ) ) {
                return new WP_Error(
                    'no_token',
                    __( 'Chưa cấu hình Access Token trong trang Quản trị > Setting Instagram.', 'instagram-post' )
                );
            }

            $limit = max( 1, min( 50, (int) $limit ) );
            $cache_key = 'memora_ig_feed_' . $limit;

            // Kiểm tra cache transient nếu không yêu cầu làm mới
            if ( ! $force_refresh ) {
                $cached_data = get_transient( $cache_key );
                if ( false !== $cached_data && is_array( $cached_data ) ) {
                    return $cached_data;
                }
            }

            // Gọi Instagram Graph API
            $endpoint = add_query_arg(
                array(
                    'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username',
                    'access_token' => $token,
                    'limit'        => $limit,
                ),
                self::GRAPH_API_BASE . 'me/media'
            );

            $response = wp_remote_get(
                $endpoint,
                array(
                    'timeout'   => 15,
                    'sslverify' => true,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $body          = wp_remote_retrieve_body( $response );
            $data          = json_decode( $body, true );

            if ( 200 !== $response_code || empty( $data['data'] ) ) {
                $error_msg = ! empty( $data['error']['message'] )
                    ? $data['error']['message']
                    : __( 'Không thể kết nối đến Instagram Graph API.', 'instagram-post' );

                return new WP_Error( 'api_error', $error_msg, $data );
            }

            // Chuẩn hóa danh sách bài viết
            $posts = array();
            foreach ( $data['data'] as $item ) {
                $media_type = isset( $item['media_type'] ) ? $item['media_type'] : 'IMAGE';

                // Đối với VIDEO thì dùng ảnh thumbnail_url, nếu không có thì fallback sang media_url
                $image_url = ( 'VIDEO' === $media_type && ! empty( $item['thumbnail_url'] ) )
                    ? $item['thumbnail_url']
                    : ( ! empty( $item['media_url'] ) ? $item['media_url'] : '' );

                if ( empty( $image_url ) ) {
                    continue;
                }

                $posts[] = array(
                    'id'         => isset( $item['id'] ) ? $item['id'] : '',
                    'media_type' => $media_type,
                    'image_url'  => $image_url,
                    'permalink'  => isset( $item['permalink'] ) ? $item['permalink'] : '',
                    'caption'    => isset( $item['caption'] ) ? $item['caption'] : '',
                    'timestamp'  => isset( $item['timestamp'] ) ? $item['timestamp'] : '',
                    'username'   => isset( $item['username'] ) ? $item['username'] : '',
                );
            }

            // Lưu vào transient cache
            $cache_hours = ! empty( $settings['cache_time_hours'] ) ? (int) $settings['cache_time_hours'] : 4;
            $cache_time  = $cache_hours * HOUR_IN_SECONDS;
            set_transient( $cache_key, $posts, $cache_time );

            // Cập nhật thời điểm đồng bộ gần nhất
            $settings['last_sync_time'] = current_time( 'mysql' );
            if ( ! empty( $posts[0]['username'] ) && empty( $settings['username'] ) ) {
                $settings['username'] = $posts[0]['username'];
            }
            update_option( 'memora_instagram_settings', $settings );

            return $posts;
        }

        /**
         * Kiểm tra tính hợp lệ của Access Token và lấy thông tin tài khoản
         *
         * @param string $token
         * @return array|WP_Error
         */
        public static function verify_access_token( $token ) {
            if ( empty( $token ) ) {
                return new WP_Error( 'empty_token', __( 'Vui lòng nhập Access Token.', 'instagram-post' ) );
            }

            $endpoint = add_query_arg(
                array(
                    'fields'       => 'id,username,account_type,media_count',
                    'access_token' => trim( $token ),
                ),
                self::GRAPH_API_BASE . 'me'
            );

            $response = wp_remote_get(
                $endpoint,
                array(
                    'timeout'   => 15,
                    'sslverify' => true,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $body          = wp_remote_retrieve_body( $response );
            $data          = json_decode( $body, true );

            if ( 200 !== $response_code || empty( $data['id'] ) ) {
                $error_msg = ! empty( $data['error']['message'] )
                    ? $data['error']['message']
                    : __( 'Token không hợp lệ hoặc đã hết hạn.', 'instagram-post' );

                return new WP_Error( 'invalid_token', $error_msg, $data );
            }

            return $data;
        }

        /**
         * Tự động làm mới Long-Lived Token trước khi hết hạn (60 ngày)
         *
         * @return bool
         */
        public static function refresh_access_token() {
            $settings = memora_ig_get_settings();
            $token    = ! empty( $settings['access_token'] ) ? trim( $settings['access_token'] ) : '';

            if ( empty( $token ) ) {
                return false;
            }

            $endpoint = add_query_arg(
                array(
                    'grant_type'   => 'ig_refresh_token',
                    'access_token' => $token,
                ),
                self::GRAPH_API_BASE . 'refresh_access_token'
            );

            $response = wp_remote_get(
                $endpoint,
                array(
                    'timeout'   => 15,
                    'sslverify' => true,
                )
            );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( ! empty( $data['access_token'] ) ) {
                $settings['access_token'] = $data['access_token'];
                if ( ! empty( $data['expires_in'] ) ) {
                    $settings['token_expires_at'] = time() + (int) $data['expires_in'];
                }
                update_option( 'memora_instagram_settings', $settings );
                return true;
            }

            return false;
        }

        /**
         * Xóa toàn bộ Transient Cache bài viết
         */
        public static function clear_all_cache() {
            global $wpdb;
            $pattern = $wpdb->esc_like( '_transient_memora_ig_feed_' ) . '%';
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
            $pattern_timeout = $wpdb->esc_like( '_transient_timeout_memora_ig_feed_' ) . '%';
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern_timeout ) );
            return true;
        }
    }
}
//====================================
// END - CLASS QUẢN LÝ INSTAGRAM GRAPH API
//====================================
