<?php
/**
 * Frontend AJAX handler class
 */
class MomBookingFrontendAjax {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_mom_get_available_lessons', [$this, 'get_available_lessons']);
        add_action('wp_ajax_nopriv_mom_get_available_lessons', [$this, 'get_available_lessons']);
        add_action('wp_ajax_mom_book_lesson', [$this, 'book_lesson']);
        add_action('wp_ajax_nopriv_mom_book_lesson', [$this, 'book_lesson']);
        add_action('wp_ajax_mom_cancel_booking', [$this, 'cancel_booking']);
        add_action('wp_ajax_nopriv_mom_cancel_booking', [$this, 'cancel_booking']);
    }

    public function get_available_lessons() {
        check_ajax_referer('mom_booking_nonce', 'nonce');

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
        $show_past = isset($_POST['show_past']) ? ($_POST['show_past'] === 'true') : false;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;

        $lessons = MomBookingManager::get_instance()->get_available_lessons($course_id, $show_past, $limit);

        wp_send_json_success($lessons);
    }

    public function book_lesson() {
        check_ajax_referer('mom_booking_nonce', 'nonce');

        $required_fields = ['lesson_id', 'customer_name', 'customer_email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(sprintf(__('Pole %s je povinné.', 'mom-booking-system'), $field));
            }
        }

        // Validate email
        if (!is_email($_POST['customer_email'])) {
            wp_send_json_error(__('Neplatná emailová adresa.', 'mom-booking-system'));
        }

        $booking_data = [
            'lesson_id' => intval($_POST['lesson_id']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        ];

        // Try to find existing customer
        $customer = MomUserManager::get_instance()->get_user_by_email($booking_data['customer_email']);
        if ($customer) {
            $booking_data['customer_id'] = $customer->id;
        } else {
            // Create new customer
            $customer_data = [
                'name' => $booking_data['customer_name'],
                'email' => $booking_data['customer_email'],
                'phone' => $booking_data['customer_phone']
            ];

            $customer_id = MomUserManager::get_instance()->create_user($customer_data);
            if (!is_wp_error($customer_id)) {
                $booking_data['customer_id'] = $customer_id;
            }
        }

        $booking_id = MomBookingManager::get_instance()->create_booking($booking_data);

        if (is_wp_error($booking_id)) {
            wp_send_json_error($booking_id->get_error_message());
        } else {
            wp_send_json_success(__('Rezervace byla úspěšně vytvořena! Potvrzení vám bylo zasláno na email.', 'mom-booking-system'));
        }
    }

    public function cancel_booking() {
        check_ajax_referer('mom_booking_nonce', 'nonce');

        $booking_id = intval($_POST['booking_id']);
        $customer_email = sanitize_email($_POST['customer_email']);

        // Verify booking ownership
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mom_bookings WHERE id = %d AND customer_email = %s",
            $booking_id, $customer_email
        ));

        if (!$booking) {
            wp_send_json_error(__('Rezervace nebyla nalezena nebo nemáte oprávnění ji zrušit.', 'mom-booking-system'));
        }

        $success = MomBookingManager::get_instance()->cancel_booking($booking_id);

        if ($success) {
            wp_send_json_success(__('Rezervace byla úspěšně zrušena.', 'mom-booking-system'));
        } else {
            wp_send_json_error(__('Chyba při rušení rezervace.', 'mom-booking-system'));
        }
    }
}
