/**
 * JavaScript Quản trị cho Plugin Instagram Post
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $noticeBox   = $('#memora-ig-ajax-notice');
        var $tokenInput  = $('#memora_ig_access_token');
        var $testBtn     = $('#memora-ig-btn-test');
        var $clearBtn    = $('#memora-ig-btn-clear-cache');
        var $refreshBtn  = $('#memora-ig-btn-refresh-token');

        // 1. Ẩn / Hiện Access Token
        $('.memora-btn-toggle-token').on('click', function(e) {
            e.preventDefault();
            var type = $tokenInput.attr('type') === 'password' ? 'text' : 'password';
            $tokenInput.attr('type', type);
            $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
        });

        // Hàm hiển thị thông báo AJAX
        function showNotice(message, isSuccess) {
            $noticeBox.removeClass('notice-success notice-error')
                      .addClass(isSuccess ? 'notice-success' : 'notice-error')
                      .html(message)
                      .fadeIn(200);
        }

        // 2. Nút Kiểm tra Kết nối Token
        $testBtn.on('click', function(e) {
            e.preventDefault();
            var token = $.trim($tokenInput.val());

            if (!token) {
                showNotice('Vui lòng nhập Access Token trước khi kiểm tra.', false);
                $tokenInput.focus();
                return;
            }

            var originalText = $testBtn.html();
            $testBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Đang kiểm tra...');

            $.ajax({
                url: memoraIgAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'memora_ig_test_token',
                    nonce: memoraIgAdmin.nonce,
                    token: token
                },
                success: function(res) {
                    if (res.success) {
                        var acc = res.data.account;
                        var msg = '<strong>' + res.data.message + '</strong><br/>' +
                                  'Tài khoản: <strong>@' + (acc.username || '') + '</strong>' +
                                  ' | Loại: ' + (acc.account_type || 'INSTAGRAM') +
                                  ' | Tổng bài: ' + (acc.media_count !== undefined ? acc.media_count : 'N/A');

                        showNotice(msg, true);

                        // Tự động điền username nếu đang trống
                        if (acc.username) {
                            var $uInput = $('#memora_ig_username');
                            if (!$uInput.val()) {
                                $uInput.val(acc.username);
                            }
                            var $urlInput = $('#memora_ig_url');
                            if (!$urlInput.val() || $urlInput.val() === 'https://instagram.com/memora_film') {
                                $urlInput.val('https://www.instagram.com/' + acc.username);
                            }
                        }
                    } else {
                        showNotice('<strong>Lỗi kết nối:</strong> ' + (res.data && res.data.message ? res.data.message : 'Không rõ lỗi'), false);
                    }
                },
                error: function() {
                    showNotice('Lỗi kết nối máy chủ WordPress. Vui lòng thử lại sau.', false);
                },
                complete: function() {
                    $testBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 3. Nút Xóa Cache bài viết
        $clearBtn.on('click', function(e) {
            e.preventDefault();
            var originalText = $clearBtn.html();
            $clearBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Đang xóa...');

            $.ajax({
                url: memoraIgAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'memora_ig_clear_cache',
                    nonce: memoraIgAdmin.nonce
                },
                success: function(res) {
                    if (res.success) {
                        showNotice(res.data.message, true);
                    } else {
                        showNotice(res.data.message || 'Lỗi khi xóa cache', false);
                    }
                },
                error: function() {
                    showNotice('Không thể gửi yêu cầu xóa cache.', false);
                },
                complete: function() {
                    $clearBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 4. Nút Gia hạn Token 60 ngày
        $refreshBtn.on('click', function(e) {
            e.preventDefault();
            if (!confirm('Bạn có chắc chắn muốn gửi yêu cầu gia hạn Access Token thêm 60 ngày tới Instagram Meta?')) {
                return;
            }

            var originalText = $refreshBtn.html();
            $refreshBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Đang gia hạn...');

            $.ajax({
                url: memoraIgAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'memora_ig_refresh_token',
                    nonce: memoraIgAdmin.nonce
                },
                success: function(res) {
                    if (res.success) {
                        showNotice(res.data.message, true);
                    } else {
                        showNotice(res.data.message || 'Lỗi khi gia hạn token', false);
                    }
                },
                error: function() {
                    showNotice('Không thể gửi yêu cầu gia hạn token.', false);
                },
                complete: function() {
                    $refreshBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 5. Nút Copy mã shortcode
        $('.memora-copy-btn').on('click', function() {
            var text = $(this).data('copy');
            var $btn = $(this);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $btn.text('Đã chép!').addClass('button-primary');
                    setTimeout(function() {
                        $btn.text('Copy').removeClass('button-primary');
                    }, 2000);
                });
            }
        });
    });
})(jQuery);
