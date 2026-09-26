<?php
/**
 * Menu Header Drilldown Component
 * 
 * Shortcode: [menu__header]
 * Bắt và xuất động dữ liệu Menu WordPress có ID=5 từ hệ thống (Appearance > Menus).
 * Tích hợp nút bấm 3 gạch ngang (Hamburger Button) chuẩn thanh lịch trên header.
 * Dạng thẻ kính mờ (Frosted Glass) đa tầng (Drill-down Navigation).
 * 
 * Căn chỉnh vị trí chuẩn xác:
 * - Vị trí thẻ menu kính mờ bám đúng mép phải và mép trên của nút 3 gạch ngang (khớp đúng khung chữ nhật lớn đã chỉ định).
 * - Không bị trôi dạt ra mép màn hình khi header đặt trong khung giới hạn (boxed container) của Elementor.
 * - Trạng thái đóng: Hiển thị 3 gạch ngang màu #733e1c.
 * - Trạng thái mở: 3 gạch ẩn đi, thẻ menu xuất hiện trùng khớp vị trí, nút '✕' thay thế vị trí nút 3 gạch.
 * - Kích thước, padding, khoảng cách hoàn toàn bằng đơn vị 'em' giúp responsive co giãn linh hoạt theo font-size.
 * - Viền mỏng màu #cddce8.
 * - Nền kính mờ 85% (rgba(255, 255, 255, 0.85)) kết hợp backdrop-filter blur.
 * - Nút đóng '✕' màu #733e1c.
 * - 2 thanh kẻ phân cách màu #dfb0bf.
 * - Mũi tên '<' căn trái, chữ căn phải phong cách typography Serif sang trọng.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Thoát nếu truy cập trực tiếp
}





//====================================
// START - ENQUEUE SCRIPTS VÀ STYLES CHO MENU HEADER
//====================================
add_action( 'wp_enqueue_scripts', 'memora_menu_header_scripts_styles' );
function memora_menu_header_scripts_styles() {
    // 1. Google Fonts: Cormorant Garamond & Playfair Display
    wp_enqueue_style(
        'memora-menu-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&display=swap',
        array(),
        null
    );

    // 2. CSS Menu Header (Toàn bộ kích thước dùng đơn vị em)
    $css = '
    :root {
        --memora-menu-font-base: 18px;
        --memora-menu-color-text: #733e1c;
        --memora-menu-color-border: #cddce8;
        --memora-menu-color-divider: #dfb0bf;
        --memora-menu-bg: rgba(255, 255, 255, 0.85);
    }

    @media (max-width: 768px) {
        :root {
            --memora-menu-font-base: 20px;
        }
    }
    @media (max-width: 480px) {
        :root {
            --memora-menu-font-base: 18px;
        }
    }

    /* Container: Định vị relative để card menu con bám chính xác mép phải và trên của nút 3 gạch */
    .memora-menu-header-container {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        line-height: 1;
        z-index: 9999;
    }
    .memora-menu-header-container.is-active {
        z-index: 999999;
    }

    /* Reset triệt để background cho tất cả button và thẻ a */
    .memora-menu-header-container button,
    .memora-menu-header-container a,
    .memora-menu-modal-wrapper button,
    .memora-menu-modal-wrapper a {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
    }
    .memora-menu-header-container button:hover,
    .memora-menu-header-container a:hover,
    .memora-menu-modal-wrapper button:hover,
    .memora-menu-modal-wrapper a:hover {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
    }

    /* Nút 3 gạch ngang (Hamburger Button) */
    .memora-menu-hamburger {
        display: inline-flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        width: 28px;
        height: 19px;
        padding: 0;
        margin: 0;
        background: transparent !important;
        background-color: transparent !important;
        border: none;
        outline: none;
        cursor: pointer;
        box-sizing: border-box;
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
        user-select: none;
    }
    .memora-menu-hamburger:hover {
        opacity: 0.75;
        transform: scale(1.06);
        background: transparent !important;
        background-color: transparent !important;
    }
    .memora-menu-hamburger.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    .memora-menu-hamburger-line {
        display: block;
        width: 100%;
        height: 2.5px;
        background-color: var(--memora-menu-color-text, #733e1c);
        border-radius: 2px;
        transition: all 0.25s ease;
    }

    /* Modal Backdrop (Phủ toàn màn hình để bắt click ra ngoài) */
    .memora-menu-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.08);
        z-index: 999998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
        pointer-events: none;
    }
    .memora-menu-backdrop.is-active {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    /* Wrapper thẻ Menu Card kính mờ: Khớp chuẩn xác top: 0, right: 0 theo nút 3 gạch */
    .memora-menu-modal-wrapper {
        position: absolute;
        top: 0;
        right: 0;
        z-index: 1000000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px) scale(0.98);
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s;
        pointer-events: none;
    }
    .memora-menu-modal-wrapper.is-open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    /* Inline mode */
    .memora-menu-modal-wrapper.is-inline {
        position: relative;
        top: auto;
        right: auto;
        opacity: 1;
        visibility: visible;
        transform: none;
        pointer-events: auto;
        display: inline-block;
    }

    /* Main Menu Card */
    .memora-menu-card {
        font-size: var(--memora-menu-font-base);
        font-family: "Cormorant Garamond", "Playfair Display", Georgia, serif;
        width: 16.5em;
        max-width: calc(100vw - 24px);
        background: var(--memora-menu-bg);
        border: 0.08em solid var(--memora-menu-color-border);
        border-radius: 1.1em;
        box-shadow: 0 0.8em 2.2em rgba(60, 80, 110, 0.14), 0 0.2em 0.6em rgba(0, 0, 0, 0.04);
        padding: 0.9em 0 1.2em 0;
        box-sizing: border-box;
        position: relative;
        overflow: hidden;
        color: var(--memora-menu-color-text);
        user-select: none;
    }

    /* Header Bar (Nút đóng X) */
    .memora-menu-topbar {
        position: relative;
        height: 1.6em;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 1.1em;
        box-sizing: border-box;
    }

    .memora-menu-close-btn {
        background: transparent !important;
        background-color: transparent !important;
        border: none;
        outline: none;
        color: var(--memora-menu-color-text);
        font-size: 1.15em;
        line-height: 1;
        cursor: pointer;
        padding: 0.1em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.22s ease, opacity 0.22s ease;
        opacity: 0.88;
    }
    .memora-menu-close-btn:hover {
        opacity: 1;
        transform: rotate(90deg) scale(1.1);
        background: transparent !important;
        background-color: transparent !important;
    }

    /* Pink Dividers */
    .memora-menu-divider {
        height: 0.1em;
        background-color: var(--memora-menu-color-divider);
        border-radius: 0.05em;
        margin: 0.35em 1.1em;
        box-sizing: border-box;
    }
    .memora-menu-divider--top {
        margin-top: 0.2em;
        margin-bottom: 0.4em;
    }
    .memora-menu-divider--sub {
        margin-top: 0.3em;
        margin-bottom: 0.4em;
    }

    /* Viewport Panels Container */
    .memora-menu-viewport {
        position: relative;
        width: 100%;
        overflow: hidden;
        transition: height 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    }

    /* Individual Panel */
    .memora-menu-panel {
        width: 100%;
        box-sizing: border-box;
        display: none;
        opacity: 0;
        transform: translateX(2em);
        transition: opacity 0.28s cubic-bezier(0.25, 1, 0.5, 1), transform 0.28s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .memora-menu-panel.is-active {
        display: block;
        opacity: 1;
        transform: translateX(0);
    }
    .memora-menu-panel.slide-out-left {
        opacity: 0;
        transform: translateX(-2em);
    }
    .memora-menu-panel.slide-out-right {
        opacity: 0;
        transform: translateX(2em);
    }

    /* Submenu Header (Back Row) */
    .memora-menu-subhead {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.35em 1.1em;
        background: transparent !important;
        background-color: transparent !important;
        cursor: pointer;
        color: var(--memora-menu-color-text);
        text-decoration: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .memora-menu-subhead:hover {
        opacity: 0.82;
        transform: translateX(-0.15em);
        background: transparent !important;
        background-color: transparent !important;
    }

    .memora-menu-back-title {
        font-size: 1.15em;
        font-weight: 600;
        text-align: right;
        margin-left: auto;
        letter-spacing: 0.02em;
    }

    /* Menu Lists */
    .memora-menu-list {
        list-style: none;
        margin: 0;
        padding: 0.3em 0 0.8em 0;
        box-sizing: border-box;
    }

    .memora-menu-item {
        margin: 0;
        padding: 0;
    }

    /* Menu Item Interactive Element (Link or Drilldown Button) */
    .memora-menu-item-link,
    .memora-menu-item-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        background: transparent !important;
        background-color: transparent !important;
        border: none;
        outline: none;
        padding: 0.36em 1.1em;
        box-sizing: border-box;
        color: var(--memora-menu-color-text);
        text-decoration: none;
        font-family: inherit;
        font-size: 1.12em;
        font-weight: 600;
        cursor: pointer;
        text-align: right;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .memora-menu-item-link:hover,
    .memora-menu-item-btn:hover {
        opacity: 0.78;
        transform: translateX(-0.18em);
        background: transparent !important;
        background-color: transparent !important;
    }

    /* Left Arrow < */
    .memora-menu-arrow {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 0.85em;
        font-weight: 600;
        color: var(--memora-menu-color-text);
        margin-right: auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        transition: transform 0.2s ease;
    }
    .memora-menu-item-btn:hover .memora-menu-arrow,
    .memora-menu-subhead:hover .memora-menu-arrow {
        transform: translateX(-0.15em);
    }

    /* Right Label */
    .memora-menu-label {
        margin-left: auto;
        text-align: right;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    ';

    wp_register_style( 'memora-menu-header-style', false );
    wp_enqueue_style( 'memora-menu-header-style' );
    wp_add_inline_style( 'memora-menu-header-style', $css );

    // 3. JavaScript Drilldown & Modal Controller
    $js = '
    (function() {
        function initMemoraMenuHeader() {
            var containers = document.querySelectorAll(".memora-menu-header-container");
            containers.forEach(function(container) {
                if (container.dataset.memoraInitialized === "true") return;
                container.dataset.memoraInitialized = "true";

                var menuId = container.dataset.menuId;
                var wrapper = container.querySelector(".memora-menu-modal-wrapper");
                var hamburgerBtn = container.querySelector(".memora-menu-hamburger");
                var backdrop = container.querySelector(\'.memora-menu-backdrop[data-menu-id="\' + menuId + \'"]\');

                if (!wrapper) return;

                var viewport = wrapper.querySelector(".memora-menu-viewport");
                var closeBtn = wrapper.querySelector(".memora-menu-close-btn");

                // Di chuyển backdrop ra document.body để che phủ toàn trang bắt click
                if (backdrop && backdrop.parentElement !== document.body) {
                    document.body.appendChild(backdrop);
                }

                var hiddenParents = [];

                // Adjust viewport height to active panel
                function adjustHeight(panel) {
                    if (!viewport || !panel) return;
                    viewport.style.height = panel.offsetHeight + "px";
                }

                // Initial height setup
                var activePanel = wrapper.querySelector(".memora-menu-panel.is-active");
                if (activePanel) {
                    setTimeout(function() { adjustHeight(activePanel); }, 50);
                }

                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(function() {
                        var cur = wrapper.querySelector(".memora-menu-panel.is-active");
                        if (cur) adjustHeight(cur);
                    });
                }

                // Logic Mở Menu: card bám đúng mép phải và trên của nút 3 gạch
                function openModal() {
                    // Mở tạm overflow cho các thẻ cha Elementor nếu bị giới hạn
                    var p = container.parentElement;
                    hiddenParents = [];
                    while (p && p !== document.body) {
                        var comp = window.getComputedStyle(p);
                        if (comp.overflow === "hidden" || comp.overflowX === "hidden" || comp.overflowY === "hidden") {
                            hiddenParents.push({ el: p, orig: p.style.overflow, origX: p.style.overflowX, origY: p.style.overflowY });
                            p.style.overflow = "visible";
                        }
                        p = p.parentElement;
                    }

                    container.classList.add("is-active");
                    wrapper.classList.add("is-open");
                    if (backdrop) backdrop.classList.add("is-active");
                    if (hamburgerBtn) hamburgerBtn.classList.add("is-hidden");

                    var cur = wrapper.querySelector(".memora-menu-panel.is-active");
                    if (cur) adjustHeight(cur);
                }

                // Logic Đóng Menu: đóng card, hiện lại 3 gạch đúng vị trí
                function closeModal() {
                    container.classList.remove("is-active");
                    wrapper.classList.remove("is-open");
                    if (backdrop) backdrop.classList.remove("is-active");
                    if (hamburgerBtn) hamburgerBtn.classList.remove("is-hidden");

                    // Phục hồi lại overflow ban đầu của các thẻ cha
                    hiddenParents.forEach(function(item) {
                        item.el.style.overflow = item.orig;
                        item.el.style.overflowX = item.origX;
                        item.el.style.overflowY = item.origY;
                    });
                    hiddenParents = [];

                    // Reset về panel gốc
                    setTimeout(function() {
                        var rootPanel = wrapper.querySelector(\'[data-panel-id="root"]\');
                        var allPanels = wrapper.querySelectorAll(".memora-menu-panel");
                        allPanels.forEach(function(p) { p.classList.remove("is-active", "slide-out-left", "slide-out-right"); });
                        if (rootPanel) {
                            rootPanel.classList.add("is-active");
                            adjustHeight(rootPanel);
                        }
                    }, 280);

                    // Elementor Popup integration nếu có
                    if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
                        var elemPopup = container.closest(".elementor-popup-modal");
                        if (elemPopup) {
                            elementorProFrontend.modules.popup.closePopup({}, null);
                        }
                    }
                }

                function toggleModal() {
                    if (wrapper.classList.contains("is-open")) {
                        closeModal();
                    } else {
                        openModal();
                    }
                }

                // Bấm vào nút 3 gạch
                if (hamburgerBtn) {
                    hamburgerBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleModal();
                    });
                }

                // Bấm vào nút X trong card
                if (closeBtn) {
                    closeBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal();
                    });
                }

                // Bấm vào backdrop
                if (backdrop) {
                    backdrop.addEventListener("click", function(e) {
                        e.preventDefault();
                        closeModal();
                    });
                }

                // Click ngoài vùng card menu để đóng
                document.addEventListener("click", function(e) {
                    if (wrapper.classList.contains("is-open")) {
                        if (!container.contains(e.target)) {
                            closeModal();
                        }
                    }
                });

                // Phím ESC để đóng
                document.addEventListener("keydown", function(e) {
                    if (e.key === "Escape" && wrapper.classList.contains("is-open")) {
                        closeModal();
                    }
                });

                // Navigation: Next (Forward)
                wrapper.addEventListener("click", function(e) {
                    var btn = e.target.closest(".memora-menu-item-btn");
                    if (!btn) return;
                    e.preventDefault();

                    var targetId = btn.dataset.targetPanel;
                    var targetPanel = wrapper.querySelector(\'#\' + targetId);
                    var currentPanel = wrapper.querySelector(".memora-menu-panel.is-active");

                    if (targetPanel && currentPanel && targetPanel !== currentPanel) {
                        currentPanel.classList.remove("is-active");
                        currentPanel.classList.add("slide-out-left");

                        targetPanel.classList.remove("slide-out-left", "slide-out-right");
                        targetPanel.classList.add("is-active");

                        adjustHeight(targetPanel);

                        setTimeout(function() {
                            currentPanel.classList.remove("slide-out-left");
                        }, 300);
                    }
                });

                // Navigation: Back (Reverse)
                wrapper.addEventListener("click", function(e) {
                    var backBtn = e.target.closest(".memora-menu-subhead");
                    if (!backBtn) return;
                    e.preventDefault();

                    var backToId = backBtn.dataset.backTo;
                    var targetPanel = wrapper.querySelector(\'#\' + backToId);
                    var currentPanel = wrapper.querySelector(".memora-menu-panel.is-active");

                    if (targetPanel && currentPanel && targetPanel !== currentPanel) {
                        currentPanel.classList.remove("is-active");
                        currentPanel.classList.add("slide-out-right");

                        targetPanel.classList.remove("slide-out-left", "slide-out-right");
                        targetPanel.classList.add("is-active");

                        adjustHeight(targetPanel);

                        setTimeout(function() {
                            currentPanel.classList.remove("slide-out-right");
                        }, 300);
                    }
                });

                // Expose global controller
                window.MemoraMenu = window.MemoraMenu || {};
                window.MemoraMenu.open = function(id) { if (!id || id == menuId) openModal(); };
                window.MemoraMenu.close = function(id) { if (!id || id == menuId) closeModal(); };
                window.MemoraMenu.toggle = function(id) { if (!id || id == menuId) toggleModal(); };
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initMemoraMenuHeader);
        } else {
            initMemoraMenuHeader();
        }

        window.addEventListener("elementor/frontend/init", function() {
            initMemoraMenuHeader();
        });
    })();
    ';

    wp_register_script( 'memora-menu-header-script', false );
    wp_enqueue_script( 'memora-menu-header-script' );
    wp_add_inline_script( 'memora-menu-header-script', $js );
}
//====================================
// END - ENQUEUE SCRIPTS VÀ STYLES CHO MENU HEADER
//====================================





//====================================
// START - XÂY DỰNG CÂY PHÂN CẤP MENU (MENU TREE HELPER)
//====================================
/**
 * Chuyển mảng phẳng các menu items từ WordPress thành cấu trúc cây đệ quy
 * 
 * @param array $items Danh sách WP_Post menu items từ wp_get_nav_menu_items()
 * @param int   $parent_id ID menu item cha (mặc định 0 cho cấp gốc)
 * @return array Mảng cấu trúc cây phân cấp
 */
function memora_build_nav_menu_tree( array $items, $parent_id = 0 ) {
    $branch = array();
    foreach ( $items as $item ) {
        $item_parent = (int) $item->menu_item_parent;
        if ( $item_parent === (int) $parent_id ) {
            $children = memora_build_nav_menu_tree( $items, $item->ID );
            $item->children = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}
//====================================
// END - XÂY DỰNG CÂY PHÂN CẤP MENU (MENU TREE HELPER)
//====================================





//====================================
// START - RENDER VÀ SHORTCODE MENU HEADER [menu__header]
//====================================
/**
 * Đệ quy sinh HTML các Panels con cho các mục có cấp dưới từ Menu WordPress
 */
function memora_render_submenu_panels( array $items, $parent_panel_id, $unique_id ) {
    $output = '';

    foreach ( $items as $item ) {
        if ( ! empty( $item->children ) ) {
            $panel_dom_id = $unique_id . '-panel-' . $item->ID;

            $output .= '<div class="memora-menu-panel" id="' . esc_attr( $panel_dom_id ) . '" data-panel-id="' . esc_attr( $item->ID ) . '" data-parent-panel="' . esc_attr( $parent_panel_id ) . '">';

            // Nút Back kẹp giữa 2 thanh kẻ hồng phấn (#dfb0bf)
            $output .= '<div class="memora-menu-subhead" data-back-to="' . esc_attr( $parent_panel_id ) . '">';
            $output .= '<span class="memora-menu-arrow">&lt;</span>';
            $output .= '<span class="memora-menu-back-title">' . esc_html( $item->title ) . '</span>';
            $output .= '</div>';

            // Thanh kẻ hồng phấn thứ 2 (dưới tiêu đề Back)
            $output .= '<div class="memora-menu-divider memora-menu-divider--sub"></div>';

            // Danh sách các mục con
            $output .= '<ul class="memora-menu-list">';
            foreach ( $item->children as $child ) {
                $has_sub = ! empty( $child->children );
                $child_target = ! empty( $child->target ) ? ' target="' . esc_attr( $child->target ) . '" rel="noopener noreferrer"' : '';
                $child_classes = ! empty( $child->classes ) && is_array( $child->classes ) ? ' ' . esc_attr( implode( ' ', array_filter( $child->classes ) ) ) : '';

                $output .= '<li class="memora-menu-item' . $child_classes . '">';
                if ( $has_sub ) {
                    $child_panel_dom_id = $unique_id . '-panel-' . $child->ID;
                    $output .= '<button type="button" class="memora-menu-item-btn" data-target-panel="' . esc_attr( $child_panel_dom_id ) . '">';
                    $output .= '<span class="memora-menu-arrow">&lt;</span>';
                    $output .= '<span class="memora-menu-label">' . esc_html( $child->title ) . '</span>';
                    $output .= '</button>';
                } else {
                    $output .= '<a href="' . esc_url( $child->url ) . '"' . $child_target . ' class="memora-menu-item-link">';
                    $output .= '<span class="memora-menu-label">' . esc_html( $child->title ) . '</span>';
                    $output .= '</a>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';

            $output .= '</div>';

            // Đệ quy cho các cấp con sâu hơn
            $output .= memora_render_submenu_panels( $item->children, $panel_dom_id, $unique_id );
        }
    }

    return $output;
}

/**
 * Hàm render shortcode [menu__header]
 * Bắt menu có ID=5 từ WordPress và hiển thị nút 3 gạch ngang (Hamburger)
 */
function memora_render_menu_header_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array(
        'id'        => 5,             // ID menu trong WordPress (mặc định ID=5)
        'toggle'    => 'true',        // Mặc định luôn hiển thị nút 3 gạch ngang
        'inline'    => 'false',       // 'true' nếu muốn hiển thị trực tiếp dạng card không qua popup
        'open'      => 'false',       // 'true' nếu muốn mở sẵn khi tải trang
        'font_size' => '',           // Tùy chỉnh font-size gốc (VD: 22px, 18px), các thông số em sẽ co giãn theo
        'class'     => '',            // Thêm class tùy biến
    ), $atts, 'menu__header' );

    $menu_identifier = ! empty( $atts['id'] ) ? $atts['id'] : 5;

    // Lấy menu từ WordPress theo ID hoặc slug/name
    $menu_obj = wp_get_nav_menu_object( $menu_identifier );
    $raw_items = false;

    if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
        $raw_items = wp_get_nav_menu_items( $menu_obj->term_id );
    } else {
        // Fallback lấy trực tiếp bằng ID số
        $raw_items = wp_get_nav_menu_items( intval( $menu_identifier ) );
    }

    // Nếu không tìm thấy menu hoặc menu chưa có item nào
    if ( empty( $raw_items ) || ! is_array( $raw_items ) ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            return '<div class="memora-menu-not-found" style="color: #733e1c; font-size: 14px; padding: 12px; border: 1.5px dashed #dfb0bf; border-radius: 12px; background: rgba(255,255,255,0.85); font-family: sans-serif;"><strong>[menu__header]</strong>: Không tìm thấy Menu có ID = ' . esc_html( $menu_identifier ) . ' trong hệ thống WordPress (Giao diện > Menu). Vui lòng kiểm tra lại ID menu.</div>';
        }
        return '';
    }

    // Xây dựng cây phân cấp động từ danh sách WordPress menu items
    $menu_tree = memora_build_nav_menu_tree( $raw_items, 0 );

    $unique_id   = 'memora-menu-' . wp_rand( 1000, 9999 );
    $root_dom_id = $unique_id . '-panel-root';

    $is_inline   = ( $atts['inline'] === 'true' );
    $is_open     = ( $atts['open'] === 'true' || $is_inline );
    $show_toggle = ( $atts['toggle'] !== 'false' && ! $is_inline );

    // Style ghi đè font-size nếu có
    $card_style = '';
    if ( ! empty( $atts['font_size'] ) ) {
        $card_style = ' style="--memora-menu-font-base: ' . esc_attr( $atts['font_size'] ) . ';"';
    }

    ob_start();
    ?>
    <div class="memora-menu-header-container <?php echo esc_attr( $atts['class'] ); ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
        
        <?php if ( $show_toggle ) : ?>
            <!-- Nút 3 gạch ngang (Hamburger Icon) -->
            <button type="button" class="memora-menu-hamburger" aria-label="<?php esc_attr_e( 'Mở menu', 'memora' ); ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--1"></span>
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--2"></span>
                <span class="memora-menu-hamburger-line memora-menu-hamburger-line--3"></span>
            </button>
        <?php endif; ?>

        <?php if ( ! $is_inline ) : ?>
            <div class="memora-menu-backdrop" data-menu-id="<?php echo esc_attr( $unique_id ); ?>"></div>
        <?php endif; ?>

        <div class="memora-menu-modal-wrapper<?php echo $is_inline ? ' is-inline' : ''; ?><?php echo ( $is_open && ! $is_inline ) ? ' is-open' : ''; ?>" data-menu-id="<?php echo esc_attr( $unique_id ); ?>">
            <div class="memora-menu-card"<?php echo $card_style; ?>>
                
                <!-- Thanh điều hướng trên cùng: Nút đóng X (#733e1c) -->
                <div class="memora-menu-topbar">
                    <button type="button" class="memora-menu-close-btn" aria-label="<?php esc_attr_e( 'Close menu', 'memora' ); ?>">✕</button>
                </div>

                <!-- Thanh kẻ hồng phấn thứ 1 (#dfb0bf - dưới nút X) -->
                <div class="memora-menu-divider memora-menu-divider--top"></div>

                <!-- Viewport chứa các Panels chuyển tầng trượt -->
                <div class="memora-menu-viewport">
                    
                    <!-- PANEL GỐC (LEVEL 0) -->
                    <div class="memora-menu-panel is-active" id="<?php echo esc_attr( $root_dom_id ); ?>" data-panel-id="root">
                        <ul class="memora-menu-list">
                            <?php foreach ( $menu_tree as $root_item ) :
                                $has_sub = ! empty( $root_item->children );
                                $target_attr = ! empty( $root_item->target ) ? ' target="' . esc_attr( $root_item->target ) . '" rel="noopener noreferrer"' : '';
                                $item_classes = ! empty( $root_item->classes ) && is_array( $root_item->classes ) ? ' ' . esc_attr( implode( ' ', array_filter( $root_item->classes ) ) ) : '';
                                ?>
                                <li class="memora-menu-item<?php echo $item_classes; ?>">
                                    <?php if ( $has_sub ) :
                                        $target_panel_id = $unique_id . '-panel-' . $root_item->ID;
                                        ?>
                                        <button type="button" class="memora-menu-item-btn" data-target-panel="<?php echo esc_attr( $target_panel_id ); ?>">
                                            <span class="memora-menu-arrow">&lt;</span>
                                            <span class="memora-menu-label"><?php echo esc_html( $root_item->title ); ?></span>
                                        </button>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $root_item->url ); ?>"<?php echo $target_attr; ?> class="memora-menu-item-link">
                                            <span class="memora-menu-label"><?php echo esc_html( $root_item->title ); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- CÁC PANELS CON (LEVEL 1, LEVEL 2,...) SINH HOÀN TOÀN ĐỘNG TỪ WORDPRESS MENU -->
                    <?php echo memora_render_submenu_panels( $menu_tree, $root_dom_id, $unique_id ); ?>

                </div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'menu__header', 'memora_render_menu_header_shortcode' );
//====================================
// END - RENDER VÀ SHORTCODE MENU HEADER [menu__header]
//====================================
