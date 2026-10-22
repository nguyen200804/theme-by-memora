/**
 * Memora Booking CRM - Spreadsheet Grid Controller (v2.0)
 * 
 * Bàn làm việc điều phối lịch chụp studio đa phòng:
 * - Render ma trận bảng tính đa phòng
 * - Cập nhật trạng thái trực tiếp 1-click (Inline AJAX)
 * - Sửa nhanh Tên / SĐT / Background trực tiếp trên ô (nhấp đúp)
 * - Thao tác chọn ô kiểu Excel / Google Sheets
 * - Đặt lịch nhanh khách vãng lai (Quick Walk-in Booking)
 * - Tự động đồng bộ ngầm & Chuông âm thanh khi có đơn mới
 * - Tìm kiếm nhanh khách hàng theo tên / SĐT / mã đơn
 * - Xuất file Excel / CSV trong ngày (UTF-8 BOM tiếng Việt)
 * - Modal chi tiết, đổi giờ, chuyển phòng & hủy lịch
 */

(function($) {
    'use strict';

    // State toàn cục ứng dụng
    const state = {
        date: '',            // YYYY-MM-DD
        diaChiId: 0,         // ID chi nhánh
        filterRoomId: 0,     // ID phòng lọc (0 = Tất cả phòng)
        interval: 5,         // Bước nhảy phút (5p, 10p, 15p)
        schedule: null,      // Dữ liệu ma trận từ API
        selectedSlot: null,  // { roomId, roomName, time }
        activeBooking: null, // Đơn hàng đang mở xem chi tiết
        statusConfigs: {},   // Cấu hình nhãn & màu sắc
        pollTimer: null,     // Bộ đếm polling ngầm
        isLoading: false,
        soundEnabled: true,  // Bật/tắt chuông thông báo
        prevBookingCount: -1 // Đếm số đơn để phát chuông khi có đơn mới
    };

    /**
     * Khởi tạo ứng dụng khi DOM sẵn sàng
     */
    $(document).ready(function() {
        if (!$('#memora-crm-app').length) return;

        // Khởi tạo biến từ WordPress localize
        if (typeof memora_crm_vars !== 'undefined') {
            state.date = memora_crm_vars.today || '';
            state.statusConfigs = memora_crm_vars.status_configs || {};
        }

        initControls();
        bindEvents();
        fetchSchedule();
        startAutoPolling();
    });

    /**
     * Khởi tạo giá trị ban đầu các control
     */
    function initControls() {
        const $dateInput = $('#memora-crm-date');
        if ($dateInput.length && state.date) {
            $dateInput.val(state.date);
        }
    }

    /**
     * Gắn toàn bộ sự kiện giao diện (Event Delegation)
     */
    function bindEvents() {
        // 1. Điều khiển ngày
        $('#memora-crm-date').on('change', function() {
            state.date = $(this).val();
            fetchSchedule();
        });

        $('#memora-crm-btn-prev').on('click', function() {
            changeDateByDays(-1);
        });

        $('#memora-crm-btn-next').on('click', function() {
            changeDateByDays(1);
        });

        $('#memora-crm-btn-today').on('click', function() {
            if (typeof memora_crm_vars !== 'undefined') {
                state.date = memora_crm_vars.today;
                $('#memora-crm-date').val(state.date);
                fetchSchedule();
            }
        });

        // 2. Chọn chi nhánh, lọc phòng & bước nhảy slot
        $('#memora-crm-location').on('change', function() {
            state.diaChiId = parseInt($(this).val(), 10) || 0;
            state.filterRoomId = 0; // Reset lọc phòng khi đổi chi nhánh
            fetchSchedule();
        });

        $('#memora-crm-room-filter').on('change', function() {
            state.filterRoomId = parseInt($(this).val(), 10) || 0;
            if (state.schedule) {
                renderGrid(state.schedule);
            }
        });

        $('#memora-crm-interval').on('change', function() {
            state.interval = parseInt($(this).val(), 10) || 5;
            fetchSchedule();
        });

        // 3. Tìm kiếm nhanh khách hàng
        $('#memora-crm-search').on('input', function() {
            handleFastSearch($(this).val().trim().toLowerCase());
        });

        // 4. Bật/Tắt chuông báo đơn mới
        $('#memora-crm-btn-sound').on('click', function() {
            state.soundEnabled = !state.soundEnabled;
            $('#memora-sound-status').text(state.soundEnabled ? 'Chuông: Bật' : 'Chuông: Tắt');
            $(this).css('opacity', state.soundEnabled ? '1' : '0.6');
            if (state.soundEnabled) playNotificationChime();
        });

        // 5. Xuất Excel / CSV
        $('#memora-crm-btn-export').on('click', function() {
            exportScheduleToCSV();
        });

        // 6. Làm mới & In ấn
        $('#memora-crm-btn-refresh').on('click', function() {
            fetchSchedule();
            showToast('Đang làm mới dữ liệu...');
        });

        $('#memora-crm-btn-print').on('click', function() {
            window.print();
        });

        // 7. Mở modal Đặt lịch nhanh
        $('#memora-crm-btn-quick-book').on('click', function() {
            openQuickBookModal();
        });

        // Đóng các popup modal
        $('.memora-crm-modal-close, .memora-crm-btn-close-modal, #memora-crm-btn-cancel-quick').on('click', function() {
            const target = $(this).data('target');
            if (target) {
                $(target).removeClass('is-active');
            } else {
                $('.memora-crm-modal-overlay').removeClass('is-active');
            }
        });

        // Đóng modal khi click ra ngoài vùng card
        $('.memora-crm-modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('memora-crm-modal-overlay')) {
                $(this).removeClass('is-active');
            }
        });

        // Submit form Đặt nhanh
        $('#memora-crm-quick-form').on('submit', function(e) {
            e.preventDefault();
            handleQuickBookSubmit();
        });

        // 8. Tương tác bảng tính (Click ô)
        const $tableBody = $('#memora-crm-table-body');

        // Click ô trống -> Chọn ô và mở popup đặt nhanh
        $tableBody.on('click', '.memora-crm-cell--empty', function() {
            $('.memora-crm-cell--selected').removeClass('memora-crm-cell--selected');
            const $cell = $(this);
            $cell.addClass('memora-crm-cell--selected');

            const roomId   = $cell.data('room-id');
            const roomName = $cell.data('room-name');
            const time     = $cell.data('time');

            state.selectedSlot = { roomId, roomName, time };
            openQuickBookModal(state.selectedSlot);
        });

        // Click vào mã đặt lịch hoặc tên khách -> Mở popup Chi tiết & Điều phối
        $tableBody.on('click', '.memora-crm-code-tag', function(e) {
            e.stopPropagation();
            const orderId = $(this).data('order-id');
            openDetailModal(orderId);
        });

        // Thay đổi Dropdown trạng thái (Inline Quick Update)
        $tableBody.on('change', '.memora-crm-badge-select', function() {
            const $select = $(this);
            const orderId = $select.data('order-id');
            const field   = $select.data('field');
            const group   = $select.data('group');
            const newVal  = $select.val();

            updateBadgeStyle($select, group, newVal);
            updateOrderField(orderId, field, newVal);
        });

        // Sửa nhanh trực tiếp Inline Text (Tên, Gói chụp, Background, SĐT) khi nhấp đúp 2 lần
        $tableBody.on('dblclick', '.memora-crm-cell--editable', function(e) {
            if ($(e.target).closest('.memora-crm-code-tag').length) {
                return;
            }
            startInlineEdit($(this));
        });

        // Lưu thông tin từ Modal Chi Tiết Đơn
        $('#memora-crm-btn-save-info').on('click', function() {
            handleSaveBookingInfoSubmit();
        });

        // 9. Thao tác trong Modal Chi tiết: Chuyển phòng & Hủy đơn
        $('#memora-crm-btn-save-move').on('click', function() {
            handleMoveBookingSubmit();
        });

        $('#memora-crm-btn-cancel-order').on('click', function() {
            handleCancelBookingSubmit();
        });
    }

    /**
     * Thay đổi ngày hiện tại (+1 hoặc -1 ngày)
     */
    function changeDateByDays(days) {
        if (!state.date) return;
        const current = new Date(state.date);
        current.setDate(current.getDate() + days);
        const y = current.getFullYear();
        const m = String(current.getMonth() + 1).padStart(2, '0');
        const d = String(current.getDate()).padStart(2, '0');
        state.date = `${y}-${m}-${d}`;
        $('#memora-crm-date').val(state.date);
        fetchSchedule();
    }

    /**
     * Gửi AJAX lấy ma trận lịch chụp
     */
    function fetchSchedule(isBackground = false) {
        if (state.isLoading && !isBackground) return;
        if (!isBackground) state.isLoading = true;

        const $tableContainer = $('#memora-crm-table-container');
        if (!isBackground) {
            $tableContainer.html(`
                <div class="memora-crm-loading">
                    <div class="memora-crm-spinner"></div>
                    <span>Đang tải ma trận điều phối lịch chụp...</span>
                </div>
            `);
        }

        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'memora_crm_get_schedule',
                nonce: memora_crm_vars.nonce,
                date: state.date,
                dia_chi_id: state.diaChiId,
                interval: state.interval
            },
            success: function(res) {
                state.isLoading = false;
                if (res.success && res.data) {
                    const prevCount = state.schedule ? (state.schedule.stats?.total_bookings || 0) : -1;
                    const newCount  = res.data.stats?.total_bookings || 0;

                    // Nếu có đơn mới được đặt trong lần sync ngầm -> phát chuông thông báo
                    if (isBackground && prevCount >= 0 && newCount > prevCount) {
                        playNotificationChime();
                        showToast(`🔔 Có ${newCount - prevCount} đơn đặt lịch mới từ website!`);
                    }

                    state.schedule = res.data;
                    renderLocationOptions(res.data.locations, res.data.dia_chi_id);
                    renderRoomFilterOptions(res.data.rooms);
                    renderStats(res.data.stats);
                    renderGrid(res.data);

                    // Re-apply search filter nếu đang có từ khóa
                    const searchVal = $('#memora-crm-search').val().trim().toLowerCase();
                    if (searchVal) handleFastSearch(searchVal);
                } else if (!isBackground) {
                    $tableContainer.html(`<div style="padding:40px;text-align:center;color:#b91c1c;">${res.data?.message || 'Không thể tải dữ liệu lịch.'}</div>`);
                }
            },
            error: function() {
                state.isLoading = false;
                if (!isBackground) {
                    $tableContainer.html('<div style="padding:40px;text-align:center;color:#b91c1c;">Lỗi kết nối máy chủ. Vui lòng thử lại.</div>');
                }
            }
        });
    }

    /**
     * Đổ danh sách chi nhánh vào dropdown
     */
    function renderLocationOptions(locations, currentId) {
        const $select = $('#memora-crm-location');
        if (!$select.length || !locations) return;

        if ($select.children().length <= 1) {
            $select.empty();
            locations.forEach(loc => {
                const isSelected = (loc.id === currentId) ? 'selected' : '';
                $select.append(`<option value="${loc.id}" ${isSelected}>${escapeHtml(loc.name)}</option>`);
            });
        }
    }

    /**
     * Đổ danh sách phòng vào dropdown lọc phòng
     */
    function renderRoomFilterOptions(rooms) {
        const $select = $('#memora-crm-room-filter');
        if (!$select.length) return;

        const currentVal = state.filterRoomId;
        $select.empty();
        $select.append('<option value="0">Tất cả phòng</option>');

        if (rooms && rooms.length) {
            rooms.forEach(r => {
                const isSelected = (r.id === currentVal) ? 'selected' : '';
                $select.append(`<option value="${r.id}" ${isSelected}>${escapeHtml(r.name)}</option>`);
            });
        }

        // Nếu phòng đang chọn không tồn tại trong danh sách phòng mới, reset về 0
        if (currentVal > 0 && rooms && !rooms.some(r => r.id === currentVal)) {
            state.filterRoomId = 0;
            $select.val('0');
        }
    }

    /**
     * Cập nhật các con số thống kê nhanh
     */
    function renderStats(stats) {
        if (!stats) return;
        $('#memora-stat-total').text(stats.total_bookings || 0);
        $('#memora-stat-shoot').text(stats.completed_shoot || 0);
        $('#memora-stat-print').text(stats.completed_print || 0);
        $('#memora-stat-cancelled').text(stats.cancelled || 0);
        
        const rev = stats.revenue_total ? new Intl.NumberFormat('vi-VN').format(stats.revenue_total) + 'đ' : '0đ';
        $('#memora-stat-revenue').text(rev);
    }

    /**
     * Dựng bảng tính ma trận đa phòng
     */
    function renderGrid(data) {
        let rooms       = data.rooms || [];
        if (state.filterRoomId > 0) {
            const filtered = rooms.filter(r => r.id === state.filterRoomId);
            if (filtered.length > 0) {
                rooms = filtered;
            }
        }
        const slots     = data.time_slots || [];
        const matrix    = data.matrix || {};
        const dateFormatted = data.date_formatted || '';
        
        // Rút gọn ngày (VD: "9/8" hoặc "22/10")
        let displayShortDate = dateFormatted;
        if (dateFormatted.indexOf('/') !== -1) {
            const parts = dateFormatted.split('/');
            displayShortDate = `${parseInt(parts[0], 10)}/${parseInt(parts[1], 10)}`;
        }

        let html = `
            <div class="memora-crm-grid-wrapper">
                <table class="memora-crm-table">
                    <thead>
                        <tr class="memora-crm-tr-room">
                            <th class="memora-crm-th-date">${displayShortDate}</th>
        `;

        rooms.forEach(room => {
            html += `<th class="memora-crm-th-room" colspan="8">${escapeHtml(room.name)}</th>`;
        });

        html += `
                        </tr>
                        <tr class="memora-crm-tr-cols">
                            <th class="memora-crm-th-time-hdr">Giờ</th>
        `;

        rooms.forEach(room => {
            html += `
                <th class="memora-crm-th-sub memora-crm-w-name">Tên</th>
                <th class="memora-crm-th-sub memora-crm-w-pkg">Gói chụp</th>
                <th class="memora-crm-th-sub memora-crm-w-bg">Background</th>
                <th class="memora-crm-th-sub memora-crm-w-phone">SĐT</th>
                <th class="memora-crm-th-sub memora-crm-w-pay">Thanh toán</th>
                <th class="memora-crm-th-sub memora-crm-w-shoot">Tình trạng chụp</th>
                <th class="memora-crm-th-sub memora-crm-w-inv">Hóa đơn</th>
                <th class="memora-crm-th-sub memora-crm-w-prt">Tình trạng in</th>
            `;
        });

        html += `
                        </tr>
                    </thead>
                    <tbody id="memora-crm-table-body">
        `;

        slots.forEach(slotTime => {
            html += `
                <tr class="memora-crm-row">
                    <td class="memora-crm-col-time">${slotTime}</td>
            `;

            rooms.forEach(room => {
                const rId = String(room.id);
                const booking = (matrix[rId] && matrix[rId][slotTime]) ? matrix[rId][slotTime] : null;

                if (booking) {
                    const orderId = booking.order_id;
                    const code = booking.code ? `#${booking.code}` : `#${orderId}`;

                    html += `
                        <td class="memora-crm-cell memora-crm-cell--editable memora-crm-w-name" data-order-id="${orderId}" data-field="customer_name" title="Nhấp đúp 2 lần để sửa Tên">
                            <span class="memora-crm-code-tag" data-order-id="${orderId}" title="Xem chi tiết & Điều phối">${code}</span>
                            <span class="memora-crm-inline-text">${booking.name ? escapeHtml(booking.name) : '<span class="memora-crm-empty-placeholder">(trống)</span>'}</span>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--editable memora-crm-w-pkg text-center" data-order-id="${orderId}" data-field="package_name" title="Nhấp đúp 2 lần để sửa Gói chụp">
                            <span class="memora-crm-inline-text">${booking.package_name ? escapeHtml(booking.package_name) : '<span class="memora-crm-empty-placeholder">(trống)</span>'}</span>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--editable memora-crm-w-bg" data-order-id="${orderId}" data-field="background_note" title="Nhấp đúp 2 lần để sửa Background">
                            <span class="memora-crm-inline-text">${booking.background_note ? escapeHtml(booking.background_note) : '<span class="memora-crm-empty-placeholder">(trống)</span>'}</span>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--editable memora-crm-w-phone" data-order-id="${orderId}" data-field="phone" title="Nhấp đúp 2 lần để sửa SĐT">
                            <span class="memora-crm-inline-text">${booking.phone ? escapeHtml(booking.phone) : '<span class="memora-crm-empty-placeholder">(trống)</span>'}</span>
                        </td>
                        <td class="memora-crm-cell memora-crm-w-pay">
                            ${renderBadgeSelect(orderId, 'payment_status', booking.payment_status, 'payment')}
                        </td>
                        <td class="memora-crm-cell memora-crm-w-shoot">
                            ${renderBadgeSelect(orderId, 'shooting_status', booking.shooting_status, 'shooting')}
                        </td>
                        <td class="memora-crm-cell memora-crm-w-inv">
                            ${renderBadgeSelect(orderId, 'invoice_status', booking.invoice_status, 'invoice')}
                        </td>
                        <td class="memora-crm-cell memora-crm-w-prt">
                            ${renderBadgeSelect(orderId, 'print_status', booking.print_status, 'print')}
                        </td>
                    `;
                } else {
                    html += `
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-name" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}"></td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-pkg" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}"></td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-bg" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}"></td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-phone" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}"></td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-pay" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}">
                            <div class="memora-crm-badge-wrap"><span class="memora-crm-badge-select memora-crm-badge--gray">-</span></div>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-shoot" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}">
                            <div class="memora-crm-badge-wrap"><span class="memora-crm-badge-select memora-crm-badge--gray">-</span></div>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-inv" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}">
                            <div class="memora-crm-badge-wrap"><span class="memora-crm-badge-select memora-crm-badge--gray">-</span></div>
                        </td>
                        <td class="memora-crm-cell memora-crm-cell--empty memora-crm-w-prt" data-room-id="${room.id}" data-room-name="${escapeHtml(room.name)}" data-time="${slotTime}">
                            <div class="memora-crm-badge-wrap"><span class="memora-crm-badge-select memora-crm-badge--gray">-</span></div>
                        </td>
                    `;
                }
            });

            html += `</tr>`;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $('#memora-crm-table-container').html(html);
    }

    /**
     * Xác định class màu sắc dạng viên con nhộng theo trạng thái
     */
    function getBadgeColorClass(val) {
        if (['da_ck', 'da_chup', 'da_in', 'tien_mat'].indexOf(val) !== -1) return 'memora-crm-badge--green';
        if (['dang_chup', 'dang_in', 'da_coc'].indexOf(val) !== -1) return 'memora-crm-badge--orange';
        if (['huy'].indexOf(val) !== -1) return 'memora-crm-badge--red';
        if (['da_tra'].indexOf(val) !== -1) return 'memora-crm-badge--purple';
        return 'memora-crm-badge--gray';
    }

    /**
     * Tạo phần tử select dropdown dạng badge viên con nhộng
     */
    function renderBadgeSelect(orderId, field, currentValue, groupKey) {
        const groupConfigs = state.statusConfigs[groupKey] || {};
        const colorClass   = getBadgeColorClass(currentValue);

        let selectHtml = `
            <div class="memora-crm-badge-wrap">
                <select class="memora-crm-badge-select ${colorClass}" data-order-id="${orderId}" data-field="${field}" data-group="${groupKey}">
        `;

        Object.keys(groupConfigs).forEach(valKey => {
            const conf = groupConfigs[valKey];
            const isSelected = (valKey === currentValue) ? 'selected' : '';
            selectHtml += `<option value="${valKey}" ${isSelected}>${escapeHtml(conf.label || valKey)}</option>`;
        });

        selectHtml += `
                </select>
            </div>
        `;

        return selectHtml;
    }

    /**
     * Cập nhật màu sắc của dropdown badge ngay lập tức
     */
    function updateBadgeStyle($select, groupKey, newVal) {
        $select.removeClass('memora-crm-badge--green memora-crm-badge--orange memora-crm-badge--gray memora-crm-badge--red memora-crm-badge--purple');
        $select.addClass(getBadgeColorClass(newVal));
    }

    /**
     * Gửi AJAX cập nhật 1 trường trực tiếp
     */
    function updateOrderField(orderId, field, value) {
        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'memora_crm_update_field',
                nonce: memora_crm_vars.nonce,
                order_id: orderId,
                field: field,
                value: value
            },
            success: function(res) {
                if (res.success) {
                    showToast('Đã lưu thay đổi.');
                } else {
                    showToast(res.data?.message || 'Lỗi khi cập nhật.', 'error');
                }
            },
            error: function() {
                showToast('Lỗi kết nối khi cập nhật.', 'error');
            }
        });
    }

    /**
     * Bắt đầu sửa nhanh inline text khi nhấp đúp 2 lần (Tên, Gói chụp, Background, SĐT)
     */
    function startInlineEdit($cell) {
        if ($cell.find('input.memora-crm-inline-input').length) return;

        const orderId = $cell.data('order-id');
        const field   = $cell.data('field');
        if (!orderId || !field) return;

        const $textSpan = $cell.find('.memora-crm-inline-text');
        if (!$textSpan.length) return;

        // Nếu đang hiển thị placeholder (trống) thì giá trị thực là rỗng
        let currentVal = '';
        if (!$textSpan.find('.memora-crm-empty-placeholder').length) {
            currentVal = $textSpan.text().trim();
        }

        const inputType = (field === 'phone') ? 'tel' : 'text';
        const placeholderText = field === 'customer_name' ? 'Tên khách...' :
                                field === 'package_name' ? 'Gói chụp...' :
                                field === 'background_note' ? 'Background...' : 'SĐT...';

        const $input = $(`<input type="${inputType}" class="memora-crm-inline-input" value="${escapeHtml(currentVal)}" placeholder="${placeholderText}">`);
        
        $textSpan.hide();
        $cell.append($input);
        $input.focus().select();

        let isFinished = false;

        function finishEdit() {
            if (isFinished) return;
            isFinished = true;

            const newVal = $input.val().trim();
            $input.remove();

            if (newVal) {
                $textSpan.text(newVal).show();
            } else {
                $textSpan.html('<span class="memora-crm-empty-placeholder">(trống)</span>').show();
            }

            if (newVal !== currentVal) {
                updateOrderField(orderId, field, newVal);

                // Cập nhật ma trận bộ nhớ
                if (state.schedule && state.schedule.matrix) {
                    Object.keys(state.schedule.matrix).forEach(rId => {
                        const slots = state.schedule.matrix[rId];
                        Object.keys(slots).forEach(sTime => {
                            if (slots[sTime].order_id === orderId) {
                                if (field === 'customer_name') slots[sTime].name = newVal;
                                if (field === 'package_name') slots[sTime].package_name = newVal;
                                if (field === 'background_note') slots[sTime].background_note = newVal;
                                if (field === 'phone') slots[sTime].phone = newVal;
                            }
                        });
                    });
                }
            }
        }

        $input.on('blur', finishEdit);
        $input.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $input.off('blur');
                finishEdit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                isFinished = true;
                $input.remove();
                $textSpan.show();
            }
        });
    }

    /**
     * Tìm kiếm nhanh khách hàng theo từ khóa
     */
    function handleFastSearch(query) {
        const $cells = $('#memora-crm-table-body td.memora-crm-cell');
        $cells.removeClass('memora-crm-cell--matched');

        if (!query) return;

        let firstMatch = null;
        $cells.each(function() {
            const $cell = $(this);
            const text = $cell.text().toLowerCase();
            if (text.indexOf(query) !== -1) {
                $cell.addClass('memora-crm-cell--matched');
                if (!firstMatch) firstMatch = $cell;
            }
        });

        // Cuộn tới ô khớp đầu tiên
        if (firstMatch && firstMatch.length) {
            const container = document.querySelector('.memora-crm-grid-wrapper');
            if (container) {
                container.scrollTop = firstMatch[0].offsetTop - 120;
            }
        }
    }

    /**
     * Mở modal tạo nhanh đặt lịch tại quầy
     */
    function openQuickBookModal(slotData) {
        const $modal = $('#memora-crm-quick-modal');
        if (!$modal.length) return;

        $('#memora-quick-date').val(state.date);

        const $roomSelect = $('#memora-quick-room');
        $roomSelect.empty();
        if (state.schedule && state.schedule.rooms) {
            state.schedule.rooms.forEach(r => {
                $roomSelect.append(`<option value="${r.id}">${escapeHtml(r.name)}</option>`);
            });
        }

        if (slotData) {
            if (slotData.roomId) $roomSelect.val(slotData.roomId);
            if (slotData.time)   $('#memora-quick-time').val(slotData.time);
        } else {
            $('#memora-quick-time').val('10:00');
        }

        $('#memora-quick-name').val('');
        $('#memora-quick-phone').val('');
        $('#memora-quick-pkg').val('5');
        $('#memora-quick-bg').val('');
        $('#memora-quick-deposit').val('');

        $modal.addClass('is-active');
        $('#memora-quick-name').focus();
    }

    /**
     * Submit form đặt lịch nhanh
     */
    function handleQuickBookSubmit() {
        const formData = {
            action: 'memora_crm_quick_book',
            nonce: memora_crm_vars.nonce,
            date: $('#memora-quick-date').val(),
            time: $('#memora-quick-time').val(),
            room_id: $('#memora-quick-room').val(),
            dia_chi_id: state.diaChiId,
            name: $('#memora-quick-name').val(),
            phone: $('#memora-quick-phone').val(),
            package_name: $('#memora-quick-pkg').val(),
            background_note: $('#memora-quick-bg').val(),
            payment_status: $('#memora-quick-pay').val(),
            deposit_price: parseFloat($('#memora-quick-deposit').val()) || 0
        };

        if (!formData.name) {
            alert('Vui lòng nhập tên khách hàng');
            return;
        }

        const $btnSubmit = $('#memora-crm-btn-submit-quick');
        $btnSubmit.prop('disabled', true).text('Đang lưu...');

        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(res) {
                $btnSubmit.prop('disabled', false).text('Lưu Đặt Lịch');
                if (res.success) {
                    $('#memora-crm-quick-modal').removeClass('is-active');
                    showToast('Đã tạo đặt lịch thành công!');
                    fetchSchedule();
                } else {
                    alert(res.data?.message || 'Lỗi khi tạo đặt lịch.');
                }
            },
            error: function() {
                $btnSubmit.prop('disabled', false).text('Lưu Đặt Lịch');
                alert('Lỗi kết nối khi gửi dữ liệu.');
            }
        });
    }

    /**
     * Mở popup Xem chi tiết & Điều phối
     */
    function openDetailModal(orderId) {
        if (!state.schedule || !state.schedule.matrix) return;

        let foundBooking = null;
        Object.keys(state.schedule.matrix).forEach(rId => {
            const slots = state.schedule.matrix[rId];
            Object.keys(slots).forEach(sTime => {
                if (slots[sTime].order_id === orderId) {
                    foundBooking = slots[sTime];
                }
            });
        });

        if (!foundBooking) return;
        state.activeBooking = foundBooking;

        $('#memora-detail-modal-title').text(`Đơn Đặt Lịch #${foundBooking.code || foundBooking.order_id}`);
        $('#memora-detail-name-input').val(foundBooking.name || '');
        $('#memora-detail-phone-input').val(foundBooking.phone || '');
        $('#memora-detail-pkg-input').val(foundBooking.package_name || '');
        $('#memora-detail-bg-input').val(foundBooking.background_note || '');

        const depositFmt = foundBooking.deposit_price ? new Intl.NumberFormat('vi-VN').format(foundBooking.deposit_price) + 'đ' : '0đ';
        const totalFmt   = foundBooking.total_price ? new Intl.NumberFormat('vi-VN').format(foundBooking.total_price) + 'đ' : '0đ';
        $('#memora-detail-price').text(`Cọc: ${depositFmt} / Tổng: ${totalFmt}`);

        $('#memora-detail-wc-link').attr('href', foundBooking.edit_order_url || '#');

        // Đổ danh sách phòng để chuyển
        const $moveRoom = $('#memora-move-room');
        $moveRoom.empty();
        if (state.schedule.rooms) {
            state.schedule.rooms.forEach(r => {
                const isCur = (r.id === foundBooking.room_id) ? 'selected' : '';
                $moveRoom.append(`<option value="${r.id}" ${isCur}>${escapeHtml(r.name)}</option>`);
            });
        }
        $('#memora-move-time').val(foundBooking.time || '10:00');

        $('#memora-crm-detail-modal').addClass('is-active');
    }

    /**
     * Submit Lưu thông tin đặt lịch (Tên, Gói chụp, Background, SĐT) từ Modal
     */
    function handleSaveBookingInfoSubmit() {
        if (!state.activeBooking) return;

        const customerName   = $('#memora-detail-name-input').val().trim();
        const phone          = $('#memora-detail-phone-input').val().trim();
        const packageName    = $('#memora-detail-pkg-input').val().trim();
        const backgroundNote = $('#memora-detail-bg-input').val().trim();

        const $btn = $('#memora-crm-btn-save-info');
        $btn.prop('disabled', true).text('Đang lưu...');

        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'memora_crm_update_booking_info',
                nonce: memora_crm_vars.nonce,
                order_id: state.activeBooking.order_id,
                customer_name: customerName,
                phone: phone,
                package_name: packageName,
                background_note: backgroundNote
            },
            success: function(res) {
                $btn.prop('disabled', false).text('💾 Lưu thông tin');
                if (res.success && res.data) {
                    showToast('Đã lưu thông tin đơn đặt lịch thành công!');
                    
                    if (res.data.updated_data) {
                        const updated = res.data.updated_data;
                        state.activeBooking = updated;
                        
                        if (state.schedule && state.schedule.matrix) {
                            Object.keys(state.schedule.matrix).forEach(rId => {
                                const slots = state.schedule.matrix[rId];
                                Object.keys(slots).forEach(sTime => {
                                    if (slots[sTime].order_id === updated.order_id) {
                                        slots[sTime] = updated;
                                    }
                                });
                            });
                        }
                    }

                    renderGrid(state.schedule);
                } else {
                    alert(res.data?.message || 'Lỗi khi lưu thông tin.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('💾 Lưu thông tin');
                alert('Lỗi kết nối khi gửi dữ liệu.');
            }
        });
    }

    /**
     * Submit Chuyển phòng / Đổi giờ
     */
    function handleMoveBookingSubmit() {
        if (!state.activeBooking) return;

        const newRoomId = $('#memora-move-room').val();
        const newTime   = $('#memora-move-time').val().trim();

        if (!newTime) {
            alert('Vui lòng nhập giờ chụp mới');
            return;
        }

        const $btn = $('#memora-crm-btn-save-move');
        $btn.prop('disabled', true).text('Đang chuyển...');

        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'memora_crm_move_booking',
                nonce: memora_crm_vars.nonce,
                order_id: state.activeBooking.order_id,
                room_id: newRoomId,
                time: newTime
            },
            success: function(res) {
                $btn.prop('disabled', false).text('Lưu chuyển đổi');
                if (res.success) {
                    $('#memora-crm-detail-modal').removeClass('is-active');
                    showToast('Đã chuyển lịch chụp thành công!');
                    fetchSchedule();
                } else {
                    alert(res.data?.message || 'Lỗi khi chuyển lịch.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Lưu chuyển đổi');
                alert('Lỗi kết nối khi chuyển lịch.');
            }
        });
    }

    /**
     * Submit Hủy lịch hẹn
     */
    function handleCancelBookingSubmit() {
        if (!state.activeBooking) return;

        if (!confirm('Bạn có chắc chắn muốn HỦY đơn đặt lịch này không?')) {
            return;
        }

        const $btn = $('#memora-crm-btn-cancel-order');
        $btn.prop('disabled', true).text('Đang hủy...');

        $.ajax({
            url: memora_crm_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'memora_crm_cancel_booking',
                nonce: memora_crm_vars.nonce,
                order_id: state.activeBooking.order_id,
                reason: 'Hủy trực tiếp từ CRM quầy lễ tân'
            },
            success: function(res) {
                $btn.prop('disabled', false).text('✕ Hủy đơn');
                if (res.success) {
                    $('#memora-crm-detail-modal').removeClass('is-active');
                    showToast('Đã hủy lịch hẹn.', 'error');
                    fetchSchedule();
                } else {
                    alert(res.data?.message || 'Lỗi khi hủy lịch.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('✕ Hủy đơn');
                alert('Lỗi kết nối khi hủy lịch.');
            }
        });
    }

    /**
     * Xuất danh sách đặt lịch trong ngày ra file CSV/Excel (UTF-8 BOM)
     */
    function exportScheduleToCSV() {
        if (!state.schedule || !state.schedule.matrix) {
            alert('Không có dữ liệu để xuất.');
            return;
        }

        const headers = [
            'Mã đặt lịch', 'Ngày chụp', 'Giờ chụp', 'Phòng', 'Tên khách hàng', 
            'Số điện thoại', 'Liên hệ khác (IG/Zalo)', 'Gói chụp', 'Background / Ghi chú', 
            'Thanh toán', 'Tình trạng chụp', 'Hóa đơn', 'Tình trạng in', 'Tiền cọc', 'Tổng tiền'
        ];

        let csvRows = [];
        csvRows.push(headers.map(h => `"${h}"`).join(','));

        let rooms  = state.schedule.rooms || [];
        if (state.filterRoomId > 0) {
            const filtered = rooms.filter(r => r.id === state.filterRoomId);
            if (filtered.length > 0) {
                rooms = filtered;
            }
        }
        const matrix = state.schedule.matrix || {};

        rooms.forEach(room => {
            const rId = String(room.id);
            const slots = matrix[rId] || {};

            Object.keys(slots).forEach(sTime => {
                const b = slots[sTime];
                const row = [
                    b.code ? `#${b.code}` : `#${b.order_id}`,
                    b.date || state.date,
                    b.time || sTime,
                    room.name,
                    b.name || '',
                    b.phone || '',
                    b.contact_other || '',
                    b.package_name || '',
                    b.background_note || '',
                    b.payment_status || '',
                    b.shooting_status || '',
                    b.invoice_status || '',
                    b.print_status || '',
                    b.deposit_price || 0,
                    b.total_price || 0
                ];
                csvRows.push(row.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','));
            });
        });

        if (csvRows.length <= 1) {
            alert('Chưa có đơn đặt lịch nào trong ngày được chọn để xuất.');
            return;
        }

        // Thêm UTF-8 BOM (\uFEFF) để Excel mở tiếng Việt không bị lỗi font
        const csvContent = '\uFEFF' + csvRows.join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Memora_Lich_Chup_${state.date}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast('Đã xuất file Excel thành công!');
    }

    /**
     * Phát chuông âm thanh thông báo đơn mới (Web Audio API)
     */
    function playNotificationChime() {
        if (!state.soundEnabled) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();

            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            // Nốt C5 (523Hz) -> E5 (659Hz)
            osc.frequency.setValueAtTime(523.25, now);
            osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.15);

            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.4);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.4);
        } catch (e) {
            // Audio context blocked by autoplay policy until user gesture
        }
    }

    /**
     * Tự động kiểm tra làm mới dữ liệu ngầm (Auto-polling mỗi 25 giây)
     */
    function startAutoPolling() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = setInterval(function() {
            if (!document.hidden && !$('.memora-crm-modal-overlay.is-active').length && !state.isLoading) {
                fetchSchedule(true); // Sync ngầm không hiện loading che màn hình
            }
        }, 25000);
    }

    /**
     * Hiển thị thông báo nhỏ góc màn hình (Toast)
     */
    function showToast(message, type = 'success') {
        let $container = $('.memora-crm-toast-container');
        if (!$container.length) {
            $container = $('<div class="memora-crm-toast-container"></div>').appendTo('body');
        }

        const toastClass = (type === 'error') ? 'memora-crm-toast--error' : 'memora-crm-toast--success';
        const $toast = $(`<div class="memora-crm-toast ${toastClass}">${escapeHtml(message)}</div>`).appendTo($container);

        setTimeout(() => {
            $toast.fadeOut(300, () => $toast.remove());
        }, 3000);
    }

    /**
     * Escape HTML an toàn
     */
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

})(jQuery);
