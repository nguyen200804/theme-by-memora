<?php
/**
 * Booking Shortcodes
 * 
 * Khai báo và render 7 shortcodes cho hệ thống đặt lịch:
 * 1. [choose_date]                  - Chọn Ngày, Tháng, Năm
 * 2. [choose_time]                  - Chọn Khung giờ chụp ảnh
 * 3. [choose_photography_package]   - Chọn Gói chụp ảnh
 * 4. [confirm_booking]              - Xác nhận lịch chụp (Ảnh 1)
 * 5. [checkout_booking]             - Form thông tin & Nút Thanh Toán Trái Tim (Ảnh 2)
 * 6. [thankyou_booking]             - Màn hình thanh toán thành công (Ảnh 3)
 * 7. [lookup_booking]               - Tra cứu đơn đặt lịch (Ảnh 4)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





//====================================
// START - SHORTCODE 1: [choose_date]
//====================================
add_shortcode( 'choose_date', 'memora_shortcode_choose_date' );
function memora_shortcode_choose_date( $atts ) {
    $current_day   = intval( current_time( 'd' ) );
    $current_month = intval( current_time( 'm' ) );
    $current_year  = intval( current_time( 'Y' ) );

    ob_start();
    ?>
    <div class="memora-booking-container memora-choose-date-wrap">
        <div class="memora-date-inputs">
            <div class="memora-date-field">
                <input type="number" id="memora_select_day" class="memora-date-input" min="1" max="31" placeholder="Ngày" value="<?php echo esc_attr( sprintf( '%02d', $current_day ) ); ?>" />
            </div>
            <div class="memora-date-field">
                <input type="number" id="memora_select_month" class="memora-date-input" min="1" max="12" placeholder="Tháng" value="<?php echo esc_attr( sprintf( '%02d', $current_month ) ); ?>" />
            </div>
            <div class="memora-date-field">
                <input type="number" id="memora_select_year" class="memora-date-input" min="<?php echo esc_attr( $current_year ); ?>" max="<?php echo esc_attr( $current_year + 5 ); ?>" placeholder="Năm" value="<?php echo esc_attr( $current_year ); ?>" />
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 1: [choose_date]
//====================================





//====================================
// START - SHORTCODE 2: [choose_time]
//====================================
add_shortcode( 'choose_time', 'memora_shortcode_choose_time' );
function memora_shortcode_choose_time( $atts ) {
    $slots = memora_generate_time_slots();

    ob_start();
    ?>
    <div class="memora-booking-container memora-choose-time-wrap">
        
        <div class="memora-time-slots-grid">
            <?php foreach ( $slots as $slot ) : ?>
                <button type="button" class="memora-time-slot-btn" data-time="<?php echo esc_attr( $slot ); ?>">
                    <?php echo esc_html( $slot ); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 2: [choose_time]
//====================================





//====================================
// START - SHORTCODE 3: [choose_photography_package]
//====================================
add_shortcode( 'choose_photography_package', 'memora_shortcode_choose_photography_package' );
function memora_shortcode_choose_photography_package( $atts ) {
    $config   = memora_get_booking_config();
    $packages = $config['packages'];

    ob_start();
    ?>
    <div class="memora-booking-container memora-choose-pkg-wrap">
       
        <div class="memora-pkg-list">
            <?php foreach ( $packages as $index => $pkg ) : 
                $name  = isset( $pkg['ten_goi_chup'] ) ? $pkg['ten_goi_chup'] : 'Gói Chụp';
                $price = isset( $pkg['gia_goi_chup'] ) ? floatval( $pkg['gia_goi_chup'] ) : 0;
                $is_first = ( $index === 0 ) ? ' is-selected' : '';
            ?>
                <div class="memora-pkg-card<?php echo esc_attr( $is_first ); ?>" data-pkg-name="<?php echo esc_attr( $name ); ?>" data-pkg-price="<?php echo esc_attr( $price ); ?>">
                    <span class="memora-pkg-name"><?php echo esc_html( $name ); ?></span>
<!--                     <span class="memora-pkg-price"><?php echo esc_html( memora_format_price( $price ) ); ?></span> -->
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 3: [choose_photography_package]
//====================================





//====================================
// START - SHORTCODE 4: [confirm_booking]
//====================================
add_shortcode( 'confirm_booking', 'memora_shortcode_confirm_booking' );
function memora_shortcode_confirm_booking( $atts ) {
    $atts = shortcode_atts( array(
        'checkout_url' => home_url( '/thanh-toan/' ),
    ), $atts, 'confirm_booking' );

    ob_start();
    ?>
    <div class="memora-booking-container memora-confirm-wrapper">
        <div class="memora-confirm-card">
            <div class="memora-star-decor">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="#a2c7e2" stroke="#a2c7e2" stroke-width="1.2" stroke-linejoin="round">
                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                </svg>
            </div>
            <h2 class="memora-confirm-title">Xác nhận lịch của bạn</h2>
            <div class="memora-confirm-subtitle">Confirm your booking</div>

            <div class="memora-confirm-pills">
                <div class="memora-pill-item memora-pill-time">12:00</div>
                <div class="memora-pill-item memora-pill-item--center memora-pill-date">04/09/2026</div>
                <div class="memora-pill-item memora-pill-pkg">5p</div>
            </div>
        </div>

        <div class="memora-confirm-action">
            <button type="button" class="memora-btn-brown memora-btn-confirm-pay" data-checkout-url="<?php echo esc_url( $atts['checkout_url'] ); ?>">
                THANH TOÁN
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 4: [confirm_booking]
//====================================





//====================================
// START - SHORTCODE 5: [checkout_booking]
//====================================
add_shortcode( 'checkout_booking', 'memora_shortcode_checkout_booking' );
function memora_shortcode_checkout_booking( $atts ) {
    $atts = shortcode_atts( array(
        'thankyou_url' => '',
    ), $atts, 'checkout_booking' );

    ob_start();
    ?>
    <div class="memora-booking-container memora-checkout-wrap">
        <h2 class="memora-checkout-title">Thông tin thanh toán</h2>
        <div class="memora-checkout-subtitle">Memora xin vài thông tin nhaaa</div>

        <form id="memora_checkout_form" data-thankyou-url="<?php echo esc_url( $atts['thankyou_url'] ); ?>">
            <div class="memora-alert"></div>

            <div class="memora-input-group">
                <div class="memora-input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-0.5 0 33 33"><title>user</title><path d="M16.5 0a9.5 9.5 0 0 1 4.581 17.825C27.427 19.947 32 25.94 32 33H0c0-7.3 4.888-13.458 11.57-15.379A9.5 9.5 0 0 1 16.5 0" fill="#733e1c"/></svg>
                </div>
                <input type="text" id="memora_input_name" class="memora-input-text" placeholder="Nhập tên của bạn" required />
            </div>

            <div class="memora-input-group">
                <div class="memora-input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="#733e1c" viewBox="32 24 200 200">
                      <path d="M231.556 175.08A56.07 56.07 0 0 1 176 224C96.598 224 32 159.402 32 80a56.07 56.07 0 0 1 48.92-55.556 16.03 16.03 0 0 1 16.652 9.583l20.092 46.878a15.97 15.97 0 0 1-1.32 15.067L99.709 121.39l-.002.002a76.54 76.54 0 0 0 35.205 35.05l25.043-16.694a15.95 15.95 0 0 1 15.179-1.394l46.838 20.073a16.035 16.035 0 0 1 9.584 16.652M157.352 47.728a72.12 72.12 0 0 1 50.92 50.92 8 8 0 0 0 15.456-4.131 88.16 88.16 0 0 0-62.246-62.247 8 8 0 0 0-4.13 15.457m-8.285 30.917a40.07 40.07 0 0 1 28.287 28.287 8 8 0 0 0 15.457-4.131 56.1 56.1 0 0 0-39.613-39.614 8 8 0 0 0-4.13 15.457"/>
                    </svg>
                </div>
                <input type="tel" id="memora_input_phone" class="memora-input-text" placeholder="Nhập số điện thoại của bạn" required />
            </div>

            <div class="memora-input-group">
                <div class="memora-input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="#733e1c" viewBox="0 0 24 24"><path d="M16 12a4 4 0 1 0-1.172 2.829A3.84 3.84 0 0 0 16 12.06l-.001-.063zm2.16 0a6.135 6.135 0 1 1-1.797-4.359 5.92 5.92 0 0 1 1.798 4.256l-.001.109zm1.687-6.406v.002a1.44 1.44 0 1 1-.422-1.018c.256.251.415.601.415.988v.029-.001zm-7.84-3.44-1.195-.008q-1.086-.008-1.649 0t-1.508.047c-.585.02-1.14.078-1.683.17l.073-.01c-.425.07-.802.17-1.163.303l.043-.014a4.12 4.12 0 0 0-2.272 2.254l-.01.027a6 6 0 0 0-.284 1.083l-.005.037a12 12 0 0 0-.159 1.589l-.001.021q-.039.946-.047 1.508t0 1.649.008 1.195-.008 1.195 0 1.649.047 1.508c.02.585.078 1.14.17 1.683l-.01-.073c.07.425.17.802.303 1.163l-.014-.043a4.12 4.12 0 0 0 2.254 2.272l.027.01c.318.119.695.219 1.083.284l.037.005c.469.082 1.024.14 1.588.159l.021.001q.946.039 1.508.047t1.649 0l1.188-.024 1.195.008q1.086.008 1.649 0t1.508-.047c.585-.02 1.14-.078 1.683-.17l-.073.01c.425-.07.802-.17 1.163-.303l-.043.014a4.12 4.12 0 0 0 2.272-2.254l.01-.027c.119-.318.219-.695.284-1.083l.005-.037c.082-.469.14-1.024.159-1.588l.001-.021q.039-.946.047-1.508t0-1.649-.008-1.195.008-1.195 0-1.649-.047-1.508c-.02-.585-.078-1.14-.17-1.683l.01.073a6.3 6.3 0 0 0-.303-1.163l.014.043a4.12 4.12 0 0 0-2.254-2.272l-.027-.01a6 6 0 0 0-1.083-.284l-.037-.005a12 12 0 0 0-1.588-.159l-.021-.001q-.946-.039-1.508-.047t-1.649 0zM24 12q0 3.578-.08 4.953a6.64 6.64 0 0 1-6.985 6.968l.016.001q-1.375.08-4.953.08t-4.953-.08a6.64 6.64 0 0 1-6.968-6.985l-.001.016q-.08-1.375-.08-4.953t.08-4.953A6.64 6.64 0 0 1 7.061.079L7.045.078q1.375-.08 4.953-.08t4.953.08a6.64 6.64 0 0 1 6.968 6.985l.001-.016Q24 8.421 24 12"/></svg>
                </div>
                <input type="text" id="memora_input_ig" class="memora-input-text" placeholder="Phương thức liên hệ khác (IG của bạn)" />
            </div>

            <div class="memora-checkout-pills">
                <div class="memora-blue-pill memora-pill-time">12:00</div>
                <div class="memora-blue-pill memora-pill-date">04/09/2026</div>
                <div class="memora-blue-pill memora-pill-pkg">5p</div>
            </div>

            <div class="memora-pricing-table">
                <div class="memora-pricing-row">
                    <span class="memora-pricing-label">Cần thanh toán</span>
                    <span class="memora-pricing-value memora-price-total">200.000vnd</span>
                </div>
                <div class="memora-pricing-row">
                    <span class="memora-pricing-label">Thanh toán trước 50%</span>
                    <span class="memora-pricing-value memora-price-deposit">100.000vnd</span>
                </div>
            </div>

            <div class="memora-disclaimer">
                <span class="memora-disclaimer-icon"><svg  viewBox="2 2 20 20" fill="#733e1c" xmlns="http://www.w3.org/2000/svg"><path d="M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12"/><path d="M12 14a1 1 0 0 1-1-1V7a1 1 0 1 1 2 0v6a1 1 0 0 1-1 1m-1.5 2.5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0"/></svg></span>
                <span>Khi nhấn nút “Thanh Toán”, bạn xác nhận đã kiểm tra kỹ thông tin và đồng ý rằng lịch chụp không thể hủy dưới bất kỳ hình thức nào</span>
            </div>

            <div class="memora-heart-button-wrap">
                <button type="submit" class="memora-heart-btn" title="Nhấn để Thanh Toán">
					<svg class="memora-heart-svg"  fill="#dfb0bf" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 1200" xml:space="preserve"><path d="M1176.629 250.347c54.502 168.401 8.89 339.761-87.232 468.872-63.446 87.553-139.273 163.012-216.796 228.983-71.322 66.39-230.933 197.753-273.241 201.402-37.394-7.148-79.353-49.433-109.039-71.196C323.503 951.599 143.93 797.388 52.878 628.779c-76.34-161.871-76.48-362.086 42.333-486.189C249.271 3.702 481.533 30.841 599.359 175.944q47.466-61.575 116.737-96.853c187.213-74.728 381.972 1.418 460.533 171.256"/></svg>
                    <span class="memora-heart-text">Thanh<br>Toán</span>
                </button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 5: [checkout_booking]
//====================================





//====================================
// START - SHORTCODE 6: [thankyou_booking]
//====================================
add_shortcode( 'thankyou_booking', 'memora_shortcode_thankyou_booking' );
function memora_shortcode_thankyou_booking( $atts ) {
    $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
    
    $booking = null;
    if ( ! empty( $code ) ) {
        $posts = get_posts( array(
            'post_type'      => 'memora_booking',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_booking_code',
                    'value'   => $code,
                    'compare' => '=',
                ),
            ),
        ) );
        if ( ! empty( $posts ) ) {
            $booking = $posts[0];
        }
    }

    $date = $booking ? get_post_meta( $booking->ID, '_booking_date', true ) : '04/09/2026';
    $time = $booking ? get_post_meta( $booking->ID, '_booking_time', true ) : '12:00';
    $pkg  = $booking ? get_post_meta( $booking->ID, '_booking_package_name', true ) : '5p';
    $display_code = $code ? $code : '6640';

    ob_start();
    ?>
    <div class="memora-booking-container memora-thankyou-wrap">
        <h2 class="memora-thankyou-title">Thank You!</h2>
        <div class="memora-thankyou-subtitle">Thanh Toán Thành Công ✨</div>

        <div class="memora-success-icon-wrap">
            <div class="memora-success-check-circle">
                <img src="/wp-content/uploads/2026/09/checked.png">
            </div>
        </div>

        <div class="memora-banner-pill">Thông tin đặt lịch của bạn:</div>

        <div class="memora-info-3cols">
            <div class="memora-info-col">
                <div class="memora-info-label">Ngày</div>
                <div class="memora-info-box"><?php echo esc_html( $date ); ?></div>
            </div>
            <div class="memora-info-col">
                <div class="memora-info-label">Giờ</div>
                <div class="memora-info-box"><?php echo esc_html( $time ); ?></div>
            </div>
            <div class="memora-info-col">
                <div class="memora-info-label">Gói chụp</div>
                <div class="memora-info-box"><?php echo esc_html( $pkg ); ?></div>
            </div>
        </div>

        <div class="memora-code-section">
            <div class="memora-code-label-row">
                <span class="memora-code-label">Code :</span>
               
            </div>
            <div class="memora-code-brown-card">
                 <span class="memora-code-yellow-badge">random 4 số</span>
                <div class="memora-code-digits"><?php echo esc_html( $display_code ); ?></div>
            </div>
            <div class="memora-code-notice">**Quý khách vui lòng lưu lại code chụp để tra cứu</div>
        </div>

        <div class="memora-note-card">
            <div class="memora-note-tag">Note</div>
            <p class="memora-note-p1">
                Một lưu ý nhỏ là bạn iu hãy <strong>đến sớm trước 15 phút</strong> so với lịch đã đặt để có thời gian chỉnh lại Makeup và chọn phụ kiện xinh nhaaa
            </p>
            <div class="memora-note-cursive">See you at Memora!</div>
            <p class="memora-note-p2">
                Memora cảm ơn và hẹn gặp bạn iu, <strong>nếu có câu hỏi hoặc thắc mắc nào</strong>, đừng ngại liên hệ chúng tớ qua <strong>IG : Memora.film</strong> nhé
            </p>
        </div>

        <div class="memora-home-btn-wrap">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="memora-home-btn">
                <span class="memora-home-icon">🏠</span> Quay về trang chủ
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 6: [thankyou_booking]
//====================================





//====================================
// START - SHORTCODE 7: [lookup_booking]
//====================================
add_shortcode( 'lookup_booking', 'memora_shortcode_lookup_booking' );
add_shortcode( 'tra_cuu_lich', 'memora_shortcode_lookup_booking' );

function memora_shortcode_lookup_booking( $atts ) {
    ob_start();
    ?>
    <div class="memora-booking-container memora-lookup-wrap">
        <div class="memora-lookup-header-pill">
            <span>🔍</span> Tra cứu đơn đặt lịch
        </div>

        <form id="memora_lookup_form">
            <div class="memora-lookup-inputs-row">
                <div class="memora-lookup-field">
                    <label for="memora_lookup_phone">Số điện thoại</label>
                    <input type="tel" id="memora_lookup_phone" class="memora-lookup-input" required />
                </div>
                <div class="memora-lookup-field">
                    <label for="memora_lookup_code">Code chụp</label>
                    <input type="text" id="memora_lookup_code" class="memora-lookup-input" maxlength="6" required />
                </div>
            </div>

            <button type="submit" class="memora-btn-brown">
                TRA CỨU
            </button>
        </form>

        <div id="memora_lookup_result"></div>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 7: [lookup_booking]
//====================================





//====================================
// START - SHORTCODE 8: GỌI TEMPLATE ELEMENTOR PHÒNG CHỤP [booking_room_template]
//====================================
add_shortcode( 'booking_room_template', 'memora_shortcode_booking_room_template' );
add_shortcode( 'dat_lich_phong_template', 'memora_shortcode_booking_room_template' );

function memora_shortcode_booking_room_template( $atts ) {
    $atts = shortcode_atts( array(
        'id'               => '286',
        'booking_page_url' => '/dat-lich/',
    ), $atts, 'booking_room_template' );

    $post_id    = get_the_ID();
    $room_title = get_the_title( $post_id );
    $template_id = ! empty( $atts['id'] ) ? intval( $atts['id'] ) : 286;

    // Chuẩn bị URL đặt lịch kèm tham số phòng
    $booking_url = add_query_arg( array(
        'phong_id' => $post_id,
        'phong'    => rawurlencode( $room_title ),
    ), home_url( $atts['booking_page_url'] ) );

    ob_start();
    ?>
    <div class="memora-room-template-wrapper" data-room-id="<?php echo esc_attr( $post_id ); ?>" data-room-title="<?php echo esc_attr( $room_title ); ?>" data-booking-url="<?php echo esc_url( $booking_url ); ?>">
        <script>
            if (typeof window.MemoraBookingState !== 'undefined') {
                window.MemoraBookingState.roomId = "<?php echo esc_js( $post_id ); ?>";
                window.MemoraBookingState.roomName = "<?php echo esc_js( $room_title ); ?>";
            }
        </script>
        <?php
        // Gọi Elementor Template
        echo do_shortcode( '[elementor-template id="' . esc_attr( $template_id ) . '"]' );
        ?>
    </div>
    <?php
    return ob_get_clean();
}
//====================================
// END - SHORTCODE 8: GỌI TEMPLATE ELEMENTOR PHÒNG CHỤP [booking_room_template]
//====================================





//====================================
// START - SHORTCODE 9: LẤY URL ĐẶT LỊCH PHÒNG CHỤP [booking_room_url]
//====================================
add_shortcode( 'booking_room_url', 'memora_shortcode_booking_room_url' );
add_shortcode( 'booking_url', 'memora_shortcode_booking_room_url' );
add_shortcode( 'url_dat_lich', 'memora_shortcode_booking_room_url' );

function memora_shortcode_booking_room_url( $atts ) {
    $atts = shortcode_atts( array(
        'page_url' => '/dat-lich/',
        'room_id'  => '',
    ), $atts, 'booking_room_url' );

    $post_id = ! empty( $atts['room_id'] ) ? intval( $atts['room_id'] ) : get_the_ID();
    $room_title = get_the_title( $post_id );

    // Trả về chuỗi URL thuần túy để có thể gán trực tiếp vào trường Link của Elementor
    $url = add_query_arg( array(
        'phong_id' => $post_id,
        'phong'    => rawurlencode( $room_title ),
    ), home_url( $atts['page_url'] ) );

    return esc_url( $url );
}
//====================================
// END - SHORTCODE 9: LẤY URL ĐẶT LỊCH PHÒNG CHỤP [booking_room_url]
//====================================





//====================================
// START - SHORTCODE 10: NÚT ĐẶT LỊCH PHÒNG CHỤP KÈM MODAL [booking_room_button]
//====================================
add_shortcode( 'booking_room_button', 'memora_shortcode_booking_room_button' );
add_shortcode( 'dat_lich_phong', 'memora_shortcode_booking_room_button' );

function memora_shortcode_booking_room_button( $atts ) {
    $atts = shortcode_atts( array(
        'id'       => '286',
        'action'   => 'popup', // 'popup' (mở modal chứa template) hoặc 'link' (chuyển hướng)
        'text'     => 'ĐẶT LỊCH CHỤP',
        'page_url' => '/dat-lich/',
        'class'    => '',
    ), $atts, 'booking_room_button' );

    $post_id     = get_the_ID();
    $room_title  = get_the_title( $post_id );
    $template_id = intval( $atts['id'] );
    $modal_id    = 'memora-modal-room-' . $post_id . '-' . wp_rand( 100, 999 );

    $booking_url = add_query_arg( array(
        'phong_id' => $post_id,
        'phong'    => rawurlencode( $room_title ),
    ), home_url( $atts['page_url'] ) );

    ob_start();

    if ( $atts['action'] === 'link' ) :
        ?>
        <a href="<?php echo esc_url( $booking_url ); ?>" class="memora-btn-brown memora-room-booking-btn <?php echo esc_attr( $atts['class'] ); ?>">
            <?php echo esc_html( $atts['text'] ); ?>
        </a>
        <?php
    else :
        // Action = popup (Mở modal chứa Elementor Template ID 286)
        ?>
        <button type="button" class="memora-btn-brown memora-room-trigger-modal <?php echo esc_attr( $atts['class'] ); ?>" data-modal-target="<?php echo esc_attr( $modal_id ); ?>">
            <?php echo esc_html( $atts['text'] ); ?>
        </button>

        <div id="<?php echo esc_attr( $modal_id ); ?>" class="memora-room-modal" style="display:none;">
            <div class="memora-room-modal-backdrop"></div>
            <div class="memora-room-modal-dialog">
                <button type="button" class="memora-room-modal-close" aria-label="Đóng">✕</button>
                <div class="memora-room-modal-content">
                    <?php echo do_shortcode( '[elementor-template id="' . esc_attr( $template_id ) . '"]' ); ?>
                </div>
            </div>
        </div>
        <?php
    endif;

    return ob_get_clean();
}
//====================================
// END - SHORTCODE 10: NÚT ĐẶT LỊCH PHÒNG CHỤP KÈM MODAL [booking_room_button]
//====================================
