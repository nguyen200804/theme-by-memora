/* =============================================================
 * Memora Marquee — External Script
 * Component:  [memora_marquee] / [marquee] shortcode
 * Handle:     memora-marquee-script
 * Enqueued:   custom-field-group.php → memora_marquee_enqueue_assets()
 * Tự động clone items để lấp đầy màn hình rộng (2K, 4K)
 * ============================================================= */

(function() {
    function checkAndFillMarquees() {
        var marquees = document.querySelectorAll('.memora-marquee-wrap');
        marquees.forEach(function(wrap) {
            var contents = wrap.querySelectorAll('.memora-marquee-content');
            if (contents.length < 2) return;
            var content1 = contents[0];
            var content2 = contents[1];
            var wrapWidth = wrap.clientWidth || window.innerWidth;
            if (wrapWidth > 0 && content1.offsetWidth < wrapWidth * 1.2) {
                var times = Math.ceil((wrapWidth * 1.5) / (content1.offsetWidth || 1));
                if (times > 1) {
                    var originalItems = Array.from(content1.children);
                    for (var i = 1; i < times; i++) {
                        originalItems.forEach(function(item) {
                            content1.appendChild(item.cloneNode(true));
                            content2.appendChild(item.cloneNode(true));
                        });
                    }
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndFillMarquees);
    } else {
        checkAndFillMarquees();
    }

    window.addEventListener('resize', checkAndFillMarquees);
})();
