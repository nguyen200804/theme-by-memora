<?php
/**
 * Plugin Name:       Instagram Post
 * Plugin URI:        https://memora.vn
 * Description:       Hiển thị bài viết Instagram qua shortcode [instagram_post] kết nối Instagram Graph API, tùy chỉnh số lượng bài viết và link Instagram trong trang Setting Instagram.
 * Version:           1.0.0
 * Author:            Memora Team
 * Author URI:        https://memora.vn
 * Text Domain:       instagram-post
 * Domain Path:       /languages
 */

// Chống truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Định nghĩa các hằng số plugin
if ( ! defined( 'MEMORA_IG_VERSION' ) ) {
    define( 'MEMORA_IG_VERSION', '1.0.0' );
}
if ( ! defined( 'MEMORA_IG_PLUGIN_DIR' ) ) {
    define( 'MEMORA_IG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MEMORA_IG_PLUGIN_URL' ) ) {
    define( 'MEMORA_IG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}





//====================================
// START - KHỞI TẠO CLASS CORE CHO PLUGIN INSTAGRAM POST
//====================================
if ( ! class_exists( 'Memora_Instagram_Post' ) ) {

    class Memora_Instagram_Post {

        /**
         * Singleton instance
         */
        private static $instance = null;

        /**
         * Lấy instance duy nhất (Singleton)
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Hàm khởi tạo (Constructor)
         */
        private function __construct() {
            $this->load_dependencies();
            $this->init_hooks();
        }

        /**
         * Nạp các file chức năng thành phần
         */
        private function load_dependencies() {
            require_once MEMORA_IG_PLUGIN_DIR . 'includes/helpers.php';
            require_once MEMORA_IG_PLUGIN_DIR . 'includes/api.php';
            require_once MEMORA_IG_PLUGIN_DIR . 'includes/settings.php';
            require_once MEMORA_IG_PLUGIN_DIR . 'includes/shortcode.php';
        }

        /**
         * Đăng ký các hook hệ thống WordPress
         */
        private function init_hooks() {
            // Nạp assets frontend
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

            // Nạp assets admin
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

            // Lập lịch cron gia hạn token tự động
            add_action( 'memora_ig_weekly_token_refresh', array( 'Memora_IG_API', 'refresh_access_token' ) );
        }

        /**
         * Nạp Stylesheet & Script cho Frontend
         */
        public function enqueue_frontend_assets() {
            wp_register_style(
                'memora-instagram-post',
                MEMORA_IG_PLUGIN_URL . 'assets/css/instagram-post.css',
                array(),
                MEMORA_IG_VERSION
            );
        }

        /**
         * Nạp Stylesheet & Script cho Admin
         */
        public function enqueue_admin_assets( $hook ) {
            // Chỉ nạp ở trang Setting Instagram
            if ( strpos( $hook, 'setting-instagram' ) === false ) {
                return;
            }

            wp_enqueue_style(
                'memora-instagram-post-admin',
                MEMORA_IG_PLUGIN_URL . 'assets/css/instagram-post-admin.css',
                array(),
                MEMORA_IG_VERSION
            );

            wp_enqueue_script(
                'memora-instagram-post-admin',
                MEMORA_IG_PLUGIN_URL . 'assets/js/instagram-post-admin.js',
                array( 'jquery' ),
                MEMORA_IG_VERSION,
                true
            );

            wp_localize_script(
                'memora-instagram-post-admin',
                'memoraIgAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'memora_ig_admin_nonce' ),
                )
            );
        }
    }
}
//====================================
// END - KHỞI TẠO CLASS CORE CHO PLUGIN INSTAGRAM POST
//====================================





//====================================
// START - KÍCH HOẠT VÀ HỦY KÍCH HOẠT PLUGIN
//====================================
/**
 * Khi kích hoạt plugin: Đăng ký lịch Cron hàng tuần để tự động làm mới token
 */
register_activation_hook( __FILE__, 'memora_ig_activate_plugin' );
function memora_ig_activate_plugin() {
    if ( ! wp_next_scheduled( 'memora_ig_weekly_token_refresh' ) ) {
        wp_schedule_event( time(), 'weekly', 'memora_ig_weekly_token_refresh' );
    }
}

/**
 * Khi hủy kích hoạt: Hủy lịch Cron
 */
register_deactivation_hook( __FILE__, 'memora_ig_deactivate_plugin' );
function memora_ig_deactivate_plugin() {
    $timestamp = wp_next_scheduled( 'memora_ig_weekly_token_refresh' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'memora_ig_weekly_token_refresh' );
    }
}
//====================================
// END - KÍCH HOẠT VÀ HỦY KÍCH HOẠT PLUGIN
//====================================





// Khởi chạy plugin
add_action( 'plugins_loaded', array( 'Memora_Instagram_Post', 'get_instance' ) );
