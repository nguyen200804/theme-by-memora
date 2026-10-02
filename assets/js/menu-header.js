/* =============================================================
 * Memora Menu Header — External Script
 * Component:  [menu__header] shortcode drilldown navigation
 * Handle:     memora-menu-header-script
 * Enqueued:   menu-header.php → memora_menu_header_scripts_styles()
 * ============================================================= */

(function() {
    function initMemoraMenuHeader() {
        var containers = document.querySelectorAll(".memora-menu-header-container");
        containers.forEach(function(container) {
            if (container.dataset.memoraInitialized === "true") return;
            container.dataset.memoraInitialized = "true";

            var menuId = container.dataset.menuId;
            var wrapper = container.querySelector(".memora-menu-modal-wrapper");
            var hamburgerBtn = container.querySelector(".memora-menu-hamburger");
            var backdrop = container.querySelector('.memora-menu-backdrop[data-menu-id="' + menuId + '"]');

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
                    var rootPanel = wrapper.querySelector('[data-panel-id="root"]');
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
                var targetPanel = wrapper.querySelector('#' + targetId);
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
                var targetPanel = wrapper.querySelector('#' + backToId);
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
