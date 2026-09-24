/**
 * Memora Booking Script
 * Đồng bộ trạng thái chọn Ngày/Giờ/Gói, xử lý AJAX Đặt lịch & Tra cứu
 */

(function ($) {
    'use strict';

    // State quản lý lịch chụp hiện tại
    window.MemoraBookingState = {
        date: '',
        time: '',
        packageName: '',
        totalPrice: 0,
        depositPrice: 0,
        roomId: '',
        roomName: '',

        saveToStorage: function () {
            try {
                sessionStorage.setItem('memora_booking_state', JSON.stringify({
                    date: this.date,
                    time: this.time,
                    packageName: this.packageName,
                    totalPrice: this.totalPrice,
                    depositPrice: this.depositPrice,
                    roomId: this.roomId,
                    roomName: this.roomName
                }));
            } catch (e) { }
        },

        loadFromStorage: function () {
            try {
                var stored = sessionStorage.getItem('memora_booking_state');
                if (stored) {
                    var data = JSON.parse(stored);
                    this.date = data.date || '';
                    this.time = data.time || '';
                    this.packageName = data.packageName || '';
                    this.totalPrice = data.totalPrice || 0;
                    this.depositPrice = data.depositPrice || 0;
                    this.roomId = data.roomId || '';
                    this.roomName = data.roomName || '';
                }
            } catch (e) { }
        }
    };

    $(document).ready(function () {
        var state = window.MemoraBookingState;
        state.loadFromStorage();

        // Nhận diện phòng chụp nếu có trên URL hoặc từ wrapper
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('phong_id')) {
            state.roomId = urlParams.get('phong_id');
        }
        if (urlParams.has('phong')) {
            state.roomName = decodeURIComponent(urlParams.get('phong'));
        }
        var $roomWrap = $('.memora-room-template-wrapper');
        if ($roomWrap.length) {
            state.roomId = $roomWrap.data('room-id') || state.roomId;
            state.roomName = $roomWrap.data('room-title') || state.roomName;
        }
        state.saveToStorage();

        var ajaxUrl = (typeof memora_booking_vars !== 'undefined') ? memora_booking_vars.ajax_url : '/wp-admin/admin-ajax.php';
        var nonce = (typeof memora_booking_vars !== 'undefined') ? memora_booking_vars.nonce : '';

        // =========================================================================
        // 1. XỬ LÝ SHORTCODE [choose_date]
        // =========================================================================
        var $dateWrap = $('.memora-choose-date-wrap');
        if ($dateWrap.length) {
            var $selectDay = $('#memora_select_day');
            var $selectMonth = $('#memora_select_month');
            var $selectYear = $('#memora_select_year');

            function getDaysInMonth(month, year) {
                return new Date(year, month, 0).getDate();
            }

            var isInput = $selectDay.is('input');

            if (isInput) {
                function syncDateInputs() {
                    var d = parseInt($selectDay.val(), 10);
                    var m = parseInt($selectMonth.val(), 10);
                    var y = parseInt($selectYear.val(), 10);

                    if (isNaN(m) || m < 1) m = 1;
                    if (m > 12) m = 12;

                    var now = new Date();
                    if (isNaN(y) || y < 1900) y = now.getFullYear();

                    var maxDays = getDaysInMonth(m, y);
                    $selectDay.attr('max', maxDays);

                    if (isNaN(d) || d < 1) d = 1;
                    if (d > maxDays) d = maxDays;

                    var dVal = (d < 10) ? '0' + d : '' + d;
                    var mVal = (m < 10) ? '0' + m : '' + m;
                    var yVal = '' + y;

                    var formattedDate = dVal + '/' + mVal + '/' + yVal;
                    state.date = formattedDate;
                    state.saveToStorage();
                    updatePillDisplays();
                    fetchAvailableSlots(formattedDate);
                }

                var dateInputTimeout;
                $selectDay.add($selectMonth).add($selectYear).on('input', function () {
                    clearTimeout(dateInputTimeout);
                    dateInputTimeout = setTimeout(function () {
                        syncDateInputs();
                    }, 350);
                });

                $selectDay.add($selectMonth).add($selectYear).on('change blur', function () {
                    syncDateInputs();
                    var d = parseInt($selectDay.val(), 10);
                    var m = parseInt($selectMonth.val(), 10);
                    if (!isNaN(d)) $selectDay.val((d < 10) ? '0' + d : '' + d);
                    if (!isNaN(m)) $selectMonth.val((m < 10) ? '0' + m : '' + m);
                });

                syncDateInputs();
            } else {
                function updateDaysOptions() {
                    var month = parseInt($selectMonth.val(), 10);
                    var year = parseInt($selectYear.val(), 10);
                    var currentDay = parseInt($selectDay.val(), 10);
                    var totalDays = getDaysInMonth(month, year);

                    $selectDay.empty();
                    for (var d = 1; d <= totalDays; d++) {
                        var dVal = (d < 10) ? '0' + d : '' + d;
                        var selected = (d === currentDay || (currentDay > totalDays && d === totalDays)) ? ' selected' : '';
                        $selectDay.append('<option value="' + dVal + '"' + selected + '>' + dVal + '</option>');
                    }
                }

                function onDateChanged() {
                    var d = $selectDay.val();
                    var m = $selectMonth.val();
                    var y = $selectYear.val();

                    if (d && m && y) {
                        var formattedDate = d + '/' + m + '/' + y;
                        state.date = formattedDate;
                        state.saveToStorage();
                        updatePillDisplays();
                        fetchAvailableSlots(formattedDate);
                    }
                }

                $selectMonth.on('change', function () {
                    updateDaysOptions();
                    onDateChanged();
                });

                $selectYear.on('change', function () {
                    updateDaysOptions();
                    onDateChanged();
                });

                $selectDay.on('change', function () {
                    onDateChanged();
                });

                updateDaysOptions();
                onDateChanged();
            }
        }

        // =========================================================================
        // 2. XỬ LÝ SHORTCODE [choose_time]
        // =========================================================================
        function fetchAvailableSlots(dateStr) {
            var $slotsGrid = $('.memora-time-slots-grid');
            if (!$slotsGrid.length) return;

            $slotsGrid.css('opacity', '0.5');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'memora_get_slots',
                    date: dateStr
                },
                success: function (response) {
                    $slotsGrid.css('opacity', '1');
                    if (response.success && response.data.slots) {
                        var slots = response.data.slots;
                        $slotsGrid.empty();

                        var hasSelected = false;
                        slots.forEach(function (slot) {
                            var isSelected = (state.time && state.time === slot.time && slot.available);
                            if (isSelected) hasSelected = true;

                            var disabledAttr = slot.available ? '' : ' disabled';
                            var activeClass = isSelected ? ' is-selected' : '';
                            var bookedClass = slot.booked ? ' is-booked' : '';
                            var titleAttr = slot.booked ? '' : (slot.past ? '' : '');

                            var $btn = $('<button type="button" class="memora-time-slot-btn' + activeClass + bookedClass + '" data-time="' + slot.time + '"' + disabledAttr + '>' + slot.time + titleAttr + '</button>');
                            $slotsGrid.append($btn);
                        });

                        // Nếu slot cũ không còn available thì xóa khỏi state
                        if (!hasSelected && state.time) {
                            state.time = '';
                            state.saveToStorage();
                            updatePillDisplays();
                        }
                    }
                },
                error: function () {
                    $slotsGrid.css('opacity', '1');
                }
            });
        }

        $(document).on('click', '.memora-time-slot-btn', function (e) {
            e.preventDefault();
            if ($(this).is(':disabled')) return;

            $('.memora-time-slot-btn').removeClass('is-selected');
            $(this).addClass('is-selected');

            var time = $(this).data('time');
            state.time = time;
            state.saveToStorage();
            updatePillDisplays();
        });

        // =========================================================================
        // 3. XỬ LÝ SHORTCODE [choose_photography_package]
        // =========================================================================
        $(document).on('click', '.memora-pkg-card', function (e) {
            e.preventDefault();
            $('.memora-pkg-card').removeClass('is-selected');
            $(this).addClass('is-selected');

            var pkgName = $(this).data('pkg-name');
            var pkgPrice = parseFloat($(this).data('pkg-price')) || 0;

            state.packageName = pkgName;
            state.totalPrice = pkgPrice;
            state.depositPrice = pkgPrice * 0.5;
            state.saveToStorage();
            updatePillDisplays();
        });

        // =========================================================================
        // ĐỒNG BỘ HIỂN THỊ CÁC VIÊN PILL & BẢNG GIÁ
        // =========================================================================
        function formatMoney(amount) {
            var num = parseFloat(amount) || 0;
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + 'vnd';
        }

        function updatePillDisplays() {
            // Pills ở [confirm_booking]
            var displayTime = state.time || '12:00';
            var displayDate = state.date || '04/09/2026';
            var displayPkg = state.packageName || '5p';

            $('.memora-pill-time').text(displayTime);
            $('.memora-pill-date').text(displayDate);
            $('.memora-pill-pkg').text(displayPkg);

            // Bảng giá ở [checkout_booking]
            var displayTotal = state.totalPrice > 0 ? formatMoney(state.totalPrice) : '200.000vnd';
            var displayDeposit = state.depositPrice > 0 ? formatMoney(state.depositPrice) : '100.000vnd';

            $('.memora-price-total').text(displayTotal);
            $('.memora-price-deposit').text(displayDeposit);
        }

        // Gọi đồng bộ ngay khi load trang
        updatePillDisplays();

        // =========================================================================
        // 4. XỬ LÝ NÚT 'THANH TOÁN' TẠI [confirm_booking]
        // =========================================================================
        $(document).on('click', '.memora-btn-confirm-pay', function (e) {
            e.preventDefault();

            if (!state.date || !state.time || !state.packageName) {
                alert('Bạn vui lòng chọn đầy đủ Ngày, Giờ và Gói chụp trước khi tiếp tục nhaaa!');
                return;
            }

            var redirectUrl = $(this).data('checkout-url') || '/thanh-toan/';

            // Lưu trạng thái trước khi chuyển trang
            state.saveToStorage();

            // Nếu có thông tin phòng, gắn kèm lên URL để đảm bảo đồng bộ
            if (state.roomId) {
                redirectUrl += (redirectUrl.indexOf('?') !== -1 ? '&' : '?') + 'phong_id=' + encodeURIComponent(state.roomId);
                if (state.roomName) {
                    redirectUrl += '&phong=' + encodeURIComponent(state.roomName);
                }
            }

            // Chuyển hướng sang trang thanh toán
            window.location.href = redirectUrl;
        });

        // =========================================================================
        // 5. XỬ LÝ SUBMIT TẠI [checkout_booking] (Nút Trái Tim)
        // =========================================================================
        $(document).on('submit', '#memora_checkout_form', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.memora-heart-btn');
            var $alert = $form.find('.memora-alert');
            var name = $.trim($('#memora_input_name').val());
            var phone = $.trim($('#memora_input_phone').val());
            var ig = $.trim($('#memora_input_ig').val());

            if (!name) {
                $alert.removeClass('memora-alert--success').addClass('memora-alert--error')
                    .text('Vui lòng nhập họ và tên của bạn nhaaa!').show();
                $('#memora_input_name').focus();
                return;
            }

            if (!phone) {
                $alert.removeClass('memora-alert--success').addClass('memora-alert--error')
                    .text('Vui lòng nhập số điện thoại để Memora liên hệ nhé!').show();
                $('#memora_input_phone').focus();
                return;
            }

            if (!state.date || !state.time || !state.packageName) {
                $alert.removeClass('memora-alert--success').addClass('memora-alert--error')
                    .text('Vui lòng kiểm tra lại thông tin Ngày, Giờ và Gói chụp!').show();
                return;
            }

            $alert.hide();
            $btn.prop('disabled', true).css('opacity', '0.7');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'memora_submit_booking',
                    nonce: nonce,
                    name: name,
                    phone: phone,
                    contact_other: ig,
                    date: state.date,
                    time: state.time,
                    package_name: state.packageName,
                    total_price: state.totalPrice,
                    deposit_price: state.depositPrice,
                    room_id: state.roomId || '',
                    room_name: state.roomName || ''
                },
                success: function (response) {
                    $btn.prop('disabled', false).css('opacity', '1');

                    if (response.success && response.data) {
                        var bookingData = response.data;
                        sessionStorage.setItem('memora_last_booking', JSON.stringify(bookingData));

                        var thankyouUrl = $form.data('thankyou-url');
                        if (thankyouUrl) {
                            var redirectUrl = thankyouUrl + (thankyouUrl.indexOf('?') !== -1 ? '&' : '?') + 'code=' + bookingData.booking_code;
                            window.location.href = redirectUrl;
                        } else {
                            // Hiển thị trực tiếp trang Thank You trên trang hiện tại
                            renderThankYouView(bookingData);
                        }
                    } else {
                        var errMsg = (response.data && response.data.message) ? response.data.message : 'Có lỗi xảy ra, vui lòng thử lại!';
                        $alert.removeClass('memora-alert--success').addClass('memora-alert--error')
                            .text(errMsg).show();
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).css('opacity', '1');
                    $alert.removeClass('memora-alert--success').addClass('memora-alert--error')
                        .text('Lỗi kết nối máy chủ. Bạn vui lòng thử lại sau giây lát!').show();
                }
            });
        });

        // Hàm render giao diện thành công nếu không cấu hình redirect URL riêng
        function renderThankYouView(data) {
            var html = `
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
                        <div class="memora-info-box">${data.date}</div>
                    </div>
                    <div class="memora-info-col">
                        <div class="memora-info-label">Giờ</div>
                        <div class="memora-info-box">${data.time}</div>
                    </div>
                    <div class="memora-info-col">
                        <div class="memora-info-label">Gói chụp</div>
                        <div class="memora-info-box">${data.package_name}</div>
                    </div>
                </div>
                <div class="memora-code-section">
                    <div class="memora-code-label-row">
                        <span class="memora-code-label">Code :</span>
                    </div>
                    <div class="memora-code-brown-card">
                        <span class="memora-code-yellow-badge">random 4 số</span>
                        <div class="memora-code-digits">${data.booking_code}</div>
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
                    <a href="/" class="memora-home-btn">
                        <span class="memora-home-icon">🏠</span> Quay về trang chủ
                    </a>
                </div>
            </div>`;

            var $checkout = $('.memora-checkout-wrap').closest('.memora-booking-container');
            if ($checkout.length) {
                $checkout.replaceWith(html);
                $('html, body').animate({ scrollTop: 0 }, 500);
            }
        }

        // =========================================================================
        // 6. XỬ LÝ TRA CỨU ĐƠN LỊCH ĐẶT [lookup_booking] (Ảnh 4)
        // =========================================================================
        $(document).on('submit', '#memora_lookup_form', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var $result = $('#memora_lookup_result');
            var phone = $.trim($('#memora_lookup_phone').val());
            var code = $.trim($('#memora_lookup_code').val());

            if (!phone || !code) {
                alert('Vui lòng nhập cả Số điện thoại và Code chụp để tra cứu nhaaa!');
                return;
            }

            $btn.prop('disabled', true).text('ĐANG TÌM...');
            $result.empty();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'memora_lookup_booking',
                    phone: phone,
                    code: code
                },
                success: function (response) {
                    $btn.prop('disabled', false).text('TRA CỨU');

                    if (response.success && response.data.data) {
                        var d = response.data.data;
                        var resultHtml = `
                        <div class="memora-lookup-result-box">
                            <div class="memora-banner-pill">Thông tin đặt lịch của bạn:</div>
                            <div class="memora-info-3cols">
                                <div class="memora-info-col">
                                    <div class="memora-info-label">Ngày</div>
                                    <div class="memora-info-box">${d.date}</div>
                                </div>
                                <div class="memora-info-col">
                                    <div class="memora-info-label">Giờ</div>
                                    <div class="memora-info-box">${d.time}</div>
                                </div>
                                <div class="memora-info-col">
                                    <div class="memora-info-label">Gói chụp</div>
                                    <div class="memora-info-box">${d.package_name}</div>
                                </div>
                            </div>
                            <div class="memora-code-section">
                                <div class="memora-code-label-row">
                                    <span class="memora-code-label">Code :</span>
                                    <span class="memora-code-yellow-badge">random 4 số</span>
                                </div>
                                <div class="memora-code-brown-card">
                                    <div class="memora-code-digits">${d.code}</div>
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
                        </div>`;
                        $result.html(resultHtml);
                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : 'Không tìm thấy thông tin đơn đặt!';
                        $result.html('<div class="memora-alert memora-alert--error">' + msg + '</div>');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text('TRA CỨU');
                    $result.html('<div class="memora-alert memora-alert--error">Lỗi kết nối máy chủ. Vui lòng thử lại sau!</div>');
                }
            });
        });

        // =========================================================================
        // 7. XỬ LÝ MODAL POPUP PHÒNG CHỤP [booking_room_button]
        // =========================================================================
        $(document).on('click', '.memora-room-trigger-modal', function (e) {
            e.preventDefault();
            var target = $(this).data('modal-target');
            $('#' + target).fadeIn(200);
            $('body').css('overflow', 'hidden');
        });

        $(document).on('click', '.memora-room-modal-close, .memora-room-modal-backdrop', function (e) {
            e.preventDefault();
            $(this).closest('.memora-room-modal').fadeOut(200);
            $('body').css('overflow', '');
        });
    });

})(jQuery);
