<?php
/**
 * Đăng ký và Xử lý Shortcode [instagram_post]
 */

// Chống truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - ĐĂNG KÝ VÀ RENDER SHORTCODE [instagram_post]
//====================================
if ( ! function_exists( 'memora_ig_shortcode_handler' ) ) {
    add_shortcode( 'instagram_post', 'memora_ig_shortcode_handler' );
    add_shortcode( 'memora_instagram_post', 'memora_ig_shortcode_handler' );

    /**
     * Xử lý Shortcode [instagram_post]
     *
     * @param array $atts Các thuộc tính truyền vào từ shortcode
     * @return string HTML hiển thị danh sách bài viết Instagram
     */
    function memora_ig_shortcode_handler( $atts ) {
        // Đảm bảo enqueue CSS frontend khi shortcode được gọi
        wp_enqueue_style( 'memora-instagram-post' );

    $settings = memora_ig_get_settings();

    // Chuẩn hóa và gộp các thuộc tính mặc định
    $args = shortcode_atts(
        array(
            'limit'        => $settings['post_count'],
            'count'        => '',
            'columns'      => $settings['columns'],
            'col'          => '',
            'gap'          => $settings['gap'],
            'ratio'        => $settings['aspect_ratio'],
            'radius'       => $settings['border_radius'],
            'link'         => '',
            'show_button'  => $settings['show_button'],
            'button_text'  => $settings['button_text'],
            'show_caption' => $settings['show_caption'],
            'class'        => '',
        ),
        $atts,
        'instagram_post'
    );

    // Xử lý các alias
    $limit = ! empty( $args['count'] ) ? (int) $args['count'] : (int) $args['limit'];
    $limit = max( 1, min( 50, $limit ) );

    $columns = ! empty( $args['col'] ) ? (int) $args['col'] : (int) $args['columns'];
    $columns = max( 1, min( 6, $columns ) );

    $gap = max( 0, min( 60, (int) $args['gap'] ) );

    $raw_ratio = str_replace( ':', '/', trim( (string) $args['ratio'] ) );
    if ( in_array( $raw_ratio, array( '4/5', 'portrait' ), true ) ) {
        $ratio = '4/5';
    } elseif ( in_array( $raw_ratio, array( '9/16', 'story', 'stories', 'reel', 'reels', 'vertical' ), true ) ) {
        $ratio = '9/16';
    } else {
        $ratio = '1/1';
    }

    $radius = max( 0, min( 40, (int) $args['radius'] ) );

    $profile_url = ! empty( $args['link'] ) ? esc_url( $args['link'] ) : memora_ig_get_profile_url();

    $show_button = filter_var( $args['show_button'], FILTER_VALIDATE_BOOLEAN );
    $show_caption = filter_var( $args['show_caption'], FILTER_VALIDATE_BOOLEAN );
    $button_text = ! empty( $args['button_text'] ) ? esc_html( $args['button_text'] ) : 'Theo dõi trên Instagram';
    $extra_class = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : '';

    // Lấy bài viết từ API / Cache
    $posts = Memora_IG_API::fetch_posts( $limit );

    // Xử lý trường hợp lỗi hoặc chưa có bài viết
    if ( is_wp_error( $posts ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            return sprintf(
                '<div class="memora-ig-admin-warning"><span class="dashicons dashicons-warning"></span> <strong>[Instagram Post]</strong> %s <a href="%s" style="margin-left: 8px;">Cài đặt ngay &rarr;</a></div>',
                esc_html( $posts->get_error_message() ),
                esc_url( admin_url( 'admin.php?page=setting-instagram' ) )
            );
        }
        return ''; // Khách vãng lai không hiển thị lỗi để giữ tính thẩm mỹ
    }

    if ( empty( $posts ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            return sprintf(
                '<div class="memora-ig-admin-warning"><span class="dashicons dashicons-info"></span> <strong>[Instagram Post]</strong> Chưa tìm thấy bài viết nào trên tài khoản Instagram. <a href="%s" style="margin-left: 8px;">Kiểm tra Setting &rarr;</a></div>',
                esc_url( admin_url( 'admin.php?page=setting-instagram' ) )
            );
        }
        return '';
    }

    // Giới hạn số lượng bài viết đúng chuẩn
    $posts = array_slice( $posts, 0, $limit );

    // Tạo ID container duy nhất
    static $feed_index = 0;
    $feed_index++;
    $unique_id = 'memora-ig-feed-' . $feed_index;

    // Build CSS Variables cho container
    $style_vars = sprintf(
        '--memora-ig-cols: %d; --memora-ig-gap: %dpx; --memora-ig-ratio: %s; --memora-ig-radius: %dpx;',
        $columns,
        $gap,
        $ratio,
        $radius
    );

    // Bắt đầu Output Buffer
    ob_start();
    ?>
    <div id="<?php echo esc_attr( $unique_id ); ?>" 
         class="memora-ig-container <?php echo $extra_class; ?>" 
         style="<?php echo esc_attr( $style_vars ); ?>" 
         data-cols="<?php echo esc_attr( $columns ); ?>">

        <!-- DANH SÁCH BÀI VIẾT (GRID) -->
        <div class="memora-ig-grid">
            <?php foreach ( $posts as $post ) : 
                $img_url   = esc_url( $post['image_url'] );
                $permalink = ! empty( $post['permalink'] ) ? esc_url( $post['permalink'] ) : $profile_url;
                $caption   = ! empty( $post['caption'] ) ? $post['caption'] : '';
                $excerpt   = memora_ig_truncate_caption( $caption, 85 );
                $is_video  = ( isset( $post['media_type'] ) && 'VIDEO' === $post['media_type'] );
            ?>
                <div class="memora-ig-item">
                    <a href="<?php echo $permalink; ?>" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="memora-ig-link" 
                       title="<?php echo esc_attr( wp_strip_all_tags( $caption ) ); ?>">
                        
                        <!-- ẢNH BÀI VIẾT -->
                        <div class="memora-ig-thumb-wrap">
                            <img src="<?php echo $img_url; ?>" 
                                 alt="<?php echo esc_attr( ! empty( $excerpt ) ? $excerpt : 'Memora Instagram Post' ); ?>" 
                                 loading="lazy" 
                                 decoding="async" 
                                 class="memora-ig-img" />

                            <?php if ( $is_video ) : ?>
                                <!-- BADGE VIDEO/REELS -->
                                <span class="memora-ig-video-badge" aria-label="Video / Reels">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </span>
                            <?php endif; ?>

                            <!-- OVERLAY HOVER -->
                            <div class="memora-ig-overlay">
                                <div class="memora-ig-overlay-content">
                                    <!-- ICON INSTAGRAM TRUNG TÂM -->
                                    <span class="memora-ig-hover-icon">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="2" y="2" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>
                                        </svg>
                                    </span>

                                    <?php if ( $show_caption && ! empty( $excerpt ) ) : ?>
                                        <p class="memora-ig-hover-caption">
                                            <?php echo esc_html( $excerpt ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NÚT THEO DÕI PROFILE INSTAGRAM -->
        <?php if ( $show_button && ! empty( $profile_url ) ) : ?>
            <div class="memora-ig-footer">
                <a href="<?php echo esc_url( $profile_url ); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="memora-ig-btn-follow" 
                   title="<?php echo esc_attr( $button_text ); ?>">
                    <span class="memora-ig-btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="2" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                            <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="memora-ig-btn-text"><?php echo $button_text; ?></span>
                </a>
            </div>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}
}
//====================================
// END - ĐĂNG KÝ VÀ RENDER SHORTCODE [instagram_post]
//====================================
