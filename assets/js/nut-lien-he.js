/* =============================================================
 * Memora Nút Liên Hệ Nổi — External Script
 * Component:  Floating Contact Widget
 * Handle:     memora-contact-button-script
 * Enqueued:   inc/nut-lien-he.php → memora_contact_button_scripts_styles()
 * ============================================================= */

document.addEventListener("DOMContentLoaded", function() {
    var widget = document.getElementById("memoraContactWidget");
    if (!widget) return;

    var trigger = document.getElementById("memoraContactTrigger");
    var closeBtn = document.getElementById("memoraContactClose");

    // Bấm "Liên hệ" để mở 3 icon
    if (trigger) {
        trigger.addEventListener("click", function(e) {
            e.stopPropagation();
            widget.classList.add("is-open");
        });
    }

    // Bấm nút X để đóng lại
    if (closeBtn) {
        closeBtn.addEventListener("click", function(e) {
            e.stopPropagation();
            widget.classList.remove("is-open");
        });
    }

    // Bấm ra ngoài vùng widget để đóng
    document.addEventListener("click", function(e) {
        if (widget.classList.contains("is-open") && !widget.contains(e.target)) {
            widget.classList.remove("is-open");
        }
    });

    // Bấm phím ESC để đóng
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && widget.classList.contains("is-open")) {
            widget.classList.remove("is-open");
        }
    });
});
