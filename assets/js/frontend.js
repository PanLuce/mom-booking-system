jQuery(document).ready(function($) {

    // Initialize booking calendar
    if ($('#mom-booking-calendar').length) {
        loadAvailableLessons();
    }

    // Filter controls
    $('#show-past-lessons').change(function() {
        loadAvailableLessons();
    });

    $('#course-filter').change(function() {
        loadAvailableLessons();
    });

    // Modal controls
    $(document).on('click', '.book-button', function() {
        var lessonId = $(this).data('lesson-id');
        var lessonTitle = $(this).closest('.lesson-card').find('.lesson-title').text();
        var lessonDate = $(this).closest('.lesson-card').find('.lesson-date').text();
        var lessonTime = $(this).closest('.lesson-card').find('.lesson-time').text();

        $('#lesson-id').val(lessonId);
        $('#lesson-info').html(
            '<div class="selected-lesson">' +
            '<h4>' + lessonTitle + '</h4>' +
            '<p><strong>Datum:</strong> ' + lessonDate + '</p>' +
            '<p><strong>Čas:</strong> ' + lessonTime + '</p>' +
            '</div>'
        );

        $('#booking-modal').fadeIn();
    });

    $('.mom-modal-close, #cancel-booking').click(function() {
        $('#booking-modal').fadeOut();
        clearBookingForm();
    });

    // Close modal on outside click
    $('#booking-modal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut();
            clearBookingForm();
        }
    });

    // Handle booking form submission
    $('#booking-form').submit(function(e) {
        e.preventDefault();

        var formData = {
            action: 'mom_book_lesson',
            nonce: momBooking.nonce,
            lesson_id: $('#lesson-id').val(),
            customer_name: $('#customer-name').val(),
            customer_email: $('#customer-email').val(),
            customer_phone: $('#customer-phone').val(),
            notes: $('#booking-notes').val()
        };

        // Disable submit button
        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Zpracovávám...');

        $.post(momBooking.ajaxUrl, formData, function(response) {
            if (response.success) {
                showMessage('success', response.data);
                $('#booking-modal').fadeOut();
                clearBookingForm();
                loadAvailableLessons(); // Refresh lessons
            } else {
                showMessage('error', response.data);
            }
        }).fail(function() {
            showMessage('error', momBooking.strings.error);
        }).always(function() {
            submitBtn.prop('disabled', false).text('Potvrdit rezervaci');
        });
    });

    function loadAvailableLessons() {
        var container = $('#lessons-container');
        var courseId = container.data('course-id') || $('#course-filter').val();
        var showPast = $('#show-past-lessons').is(':checked') || container.data('show-past') === 'true';
        var limit = container.data('limit') || 10;
        var showPrice = container.data('show-price') !== 'false';

        // Show loading
        container.html(
            '<div class="loading-spinner">' +
            '<div class="spinner"></div>' +
            '<p>' + momBooking.strings.loading + '</p>' +
            '</div>'
        );

        $.post(momBooking.ajaxUrl, {
            action: 'mom_get_available_lessons',
            nonce: momBooking.nonce,
            course_id: courseId,
            show_past: showPast,
            limit: limit
        }, function(response) {
            if (response.success) {
                renderLessons(response.data, showPrice);
            } else {
                container.html('<p class="error">Chyba při načítání lekcí.</p>');
            }
        }).fail(function() {
            container.html('<p class="error">' + momBooking.strings.error + '</p>');
        });
    }

    function renderLessons(lessons, showPrice) {
        var container = $('#lessons-container');

        if (!lessons || lessons.length === 0) {
            container.html('<p class="no-lessons">Momentálně nejsou dostupné žádné lekce.</p>');
            return;
        }

        var html = '<div class="lessons-grid">';

        lessons.forEach(function(lesson) {
            var date = new Date(lesson.date_time);
            var isLowAvailability = lesson.available_spots <= 2;
            var availabilityClass = isLowAvailability ? 'availability-low' : '';

            html += '<div class="lesson-card">';
            html += '<div class="lesson-title">' + lesson.title + '</div>';
            html += '<div class="lesson-meta">';
            html += '<div class="lesson-date">' + date.toLocaleDateString('cs-CZ', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) + '</div>';
            html += '<div class="lesson-time">' + date.toLocaleTimeString('cs-CZ', {
                hour: '2-digit',
                minute: '2-digit'
            }) + '</div>';

            if (showPrice && lesson.course_price > 0) {
                html += '<div class="lesson-price">' +
                    new Intl.NumberFormat('cs-CZ', {
                        style: 'currency',
                        currency: 'CZK',
                        minimumFractionDigits: 0
                    }).format(lesson.course_price) + '</div>';
            }

            html += '</div>';

            html += '<div class="lesson-availability">';
            html += '<span class="availability-count ' + availabilityClass + '">';
            html += 'Volná místa: ' + lesson.available_spots;
            html += '</span>';
            html += '</div>';

            if (lesson.available_spots > 0) {
                html += '<button class="book-button" data-lesson-id="' + lesson.id + '">';
                html += 'Rezervovat';
                html += '</button>';
            } else {
                html += '<button class="book-button" disabled>';
                html += 'Obsazeno';
                html += '</button>';
            }

            html += '</div>';
        });

        html += '</div>';
        container.html(html);
    }

    function clearBookingForm() {
        $('#booking-form')[0].reset();
        $('#lesson-info').empty();
    }

    function showMessage(type, message) {
        var messageClass = type === 'success' ? 'success-message' : 'error-message';
        var messageHtml = '<div class="booking-message ' + messageClass + '">' + message + '</div>';

        // Remove existing messages
        $('.booking-message').remove();

        // Add new message
        $('#mom-booking-calendar').prepend(messageHtml);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.booking-message').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Scroll to message
        $('html, body').animate({
            scrollTop: $('.booking-message').offset().top - 20
        }, 500);
    }

    // Form validation helpers
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function validatePhone(phone) {
        var re = /^[\+]?[0-9\s\-\(\)]+$/;
        return !phone || re.test(phone);
    }

    // Real-time form validation
    $('#customer-email').blur(function() {
        var email = $(this).val();
        if (email && !validateEmail(email)) {
            $(this).addClass('error');
            showFieldError($(this), 'Neplatná emailová adresa');
        } else {
            $(this).removeClass('error');
            hideFieldError($(this));
        }
    });

    $('#customer-phone').blur(function() {
        var phone = $(this).val();
        if (phone && !validatePhone(phone)) {
            $(this).addClass('error');
            showFieldError($(this), 'Neplatné telefonní číslo');
        } else {
            $(this).removeClass('error');
            hideFieldError($(this));
        }
    });

    function showFieldError(field, message) {
        hideFieldError(field);
        field.after('<div class="field-error">' + message + '</div>');
    }

    function hideFieldError(field) {
        field.siblings('.field-error').remove();
    }

    // Remove error styling on input
    $('.form-group input, .form-group textarea').on('input', function() {
        $(this).removeClass('error');
        hideFieldError($(this));
    });
});
