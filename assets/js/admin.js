jQuery(document).ready(function($) {

    // Toggle course lessons
    $('.toggle-lessons').click(function() {
        var courseId = $(this).data('course-id');
        var lessonsRow = $('#lessons-' + courseId);
        var icon = $(this).find('.dashicons');

        if (lessonsRow.is(':visible')) {
            lessonsRow.hide();
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
        } else {
            // Load lessons via AJAX
            $.post(momBookingAdmin.ajaxUrl, {
                action: 'mom_toggle_course_lessons',
                course_id: courseId,
                nonce: momBookingAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var html = '<div class="lessons-grid">';
                    response.data.lessons.forEach(function(lesson) {
                        var statusClass = lesson.status === 'cancelled' ? 'lesson-cancelled' : '';
                        var date = new Date(lesson.date_time);

                        html += '<div class="lesson-card ' + statusClass + '">';
                        html += '<div class="lesson-number">Lekce ' + lesson.lesson_number + '</div>';
                        html += '<div class="lesson-date">' + date.toLocaleDateString('cs-CZ') + '</div>';
                        html += '<div class="lesson-time">' + date.toLocaleTimeString('cs-CZ', {hour: '2-digit', minute:'2-digit'}) + '</div>';
                        html += '<div class="lesson-bookings">' + lesson.bookings_count + '/' + lesson.max_capacity + '</div>';
                        html += '<div class="lesson-status">' + (lesson.status === 'active' ? 'Aktivní' : 'Zrušena') + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    lessonsRow.find('.lessons-container').html(html);
                } else {
                    lessonsRow.find('.lessons-container').html('<p>Chyba při načítání lekcí.</p>');
                }
            });

            lessonsRow.show();
            icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    // Delete course
    $('.delete-course').click(function(e) {
        e.preventDefault();

        if (!confirm(momBookingAdmin.strings.confirmDelete)) {
            return;
        }

        var courseId = $(this).data('course-id');
        var row = $(this).closest('tr');

        $.post(momBookingAdmin.ajaxUrl, {
            action: 'mom_delete_course',
            course_id: courseId,
            nonce: momBookingAdmin.nonce
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() {
                    row.remove();
                });
                showNotice('success', response.data);
            } else {
                showNotice('error', response.data);
            }
        });
    });

    // Delete user
    $('.delete-user').click(function(e) {
        e.preventDefault();

        if (!confirm(momBookingAdmin.strings.confirmDelete)) {
            return;
        }

        var userId = $(this).data('user-id');
        var row = $(this).closest('tr');

        $.post(momBookingAdmin.ajaxUrl, {
            action: 'mom_delete_user',
            user_id: userId,
            nonce: momBookingAdmin.nonce
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() {
                    row.remove();
                });
                showNotice('success', response.data);
            } else {
                showNotice('error', response.data);
            }
        });
    });

    // Cancel booking
    $('.cancel-booking').click(function(e) {
        e.preventDefault();

        if (!confirm('Opravdu chcete zrušit tuto rezervaci?')) {
            return;
        }

        var bookingId = $(this).data('booking-id');
        var row = $(this).closest('tr');

        $.post(momBookingAdmin.ajaxUrl, {
            action: 'mom_cancel_booking',
            booking_id: bookingId,
            nonce: momBookingAdmin.nonce
        }, function(response) {
            if (response.success) {
                row.find('.booking-status').text('Zrušeno').addClass('status-cancelled');
                $(this).remove();
                showNotice('success', response.data);
            } else {
                showNotice('error', response.data);
            }
        }.bind(this));
    });

    // Add/Cancel user form
    $('#add-user-btn').click(function(e) {
        e.preventDefault();
        $('#new-user-form').slideDown();
        $(this).hide();
    });

    $('#cancel-user-btn').click(function() {
        $('#new-user-form').slideUp();
        $('#add-user-btn').show();
    });

    // Form validation
    $('form').submit(function() {
        var requiredFields = $(this).find('[required]');
        var isValid = true;

        requiredFields.each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });

        if (!isValid) {
            showNotice('error', 'Prosím vyplňte všechna povinná pole.');
            return false;
        }
    });

    // Remove error class on input
    $('input, textarea, select').on('input change', function() {
        $(this).removeClass('error');
    });

    // Auto-refresh booking stats
    if ($('#booking-stats').length) {
        refreshBookingStats();
        setInterval(refreshBookingStats, 30000); // Every 30 seconds
    }

    function refreshBookingStats() {
        $.post(momBookingAdmin.ajaxUrl, {
            action: 'mom_get_booking_stats',
            nonce: momBookingAdmin.nonce
        }, function(response) {
            if (response.success) {
                $('#total-bookings').text(response.data.total_bookings);
                $('#today-bookings').text(response.data.today_bookings);
                $('#upcoming-lessons').text(response.data.upcoming_lessons);
            }
        });
    }

    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        $('.wrap h1').after(notice);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);

        // Add dismiss functionality
        notice.find('.notice-dismiss').click(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }

    // Enhanced form styling
    $('.form-table input, .form-table textarea, .form-table select').addClass('form-control');

    // Date picker for date inputs
    if ($.fn.datepicker) {
        $('input[type="date"]').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }
});
