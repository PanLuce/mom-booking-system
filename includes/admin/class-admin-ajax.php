<?php
/**
 * Admin AJAX handler class
 */
class MomBookingAdminAjax {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_mom_toggle_course_lessons', [$this, 'toggle_course_lessons']);
        add_action('wp_ajax_mom_delete_course', [$this, 'delete_course']);
        add_action('wp_ajax_mom_delete_user', [$this, 'delete_user']);
        add_action('wp_ajax_mom_cancel_booking', [$this, 'cancel_booking']);
        add_action('wp_ajax_mom_get_booking_stats', [$this, 'get_booking_stats']);
    }

    public function toggle_course_lessons() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nedostatečná oprávnění.');
        }

        $course_id = intval($_POST['course_id']);
        $lessons = MomCourseManager::get_instance()->get_course_lessons($course_id);

        wp_send_json_success(['lessons' => $lessons]);
    }

    public function delete_course() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nedostatečná oprávnění.');
        }

        $course_id = intval($_POST['course_id']);

        // Check if course has bookings
        global $wpdb;
        $has_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE l.course_id = %d AND b.booking_status = 'confirmed'
        ", $course_id));

        if ($has_bookings > 0) {
            wp_send_json_error('Kurz nelze smazat - obsahuje aktivní rezervace.');
        }

        $success = MomCourseManager::get_instance()->delete_course($course_id);

        if ($success) {
            wp_send_json_success('Kurz byl úspěšně smazán.');
        } else {
            wp_send_json_error('Chyba při mazání kurzu.');
        }
    }

    public function delete_user() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nedostatečná oprávnění.');
        }

        $user_id = intval($_POST['user_id']);

        // Check if user has active bookings
        global $wpdb;
        $has_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
            WHERE customer_id = %d AND booking_status = 'confirmed'
        ", $user_id));

        if ($has_bookings > 0) {
            wp_send_json_error('Uživatel nelze smazat - má aktivní rezervace.');
        }

        $success = MomUserManager::get_instance()->delete_user($user_id);

        if ($success) {
            wp_send_json_success('Uživatel byl úspěšně smazán.');
        } else {
            wp_send_json_error('Chyba při mazání uživatele.');
        }
    }

    public function cancel_booking() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nedostatečná oprávnění.');
        }

        $booking_id = intval($_POST['booking_id']);
        $success = MomBookingManager::get_instance()->cancel_booking($booking_id);

        if ($success) {
            wp_send_json_success('Rezervace byla zrušena.');
        } else {
            wp_send_json_error('Chyba při rušení rezervace.');
        }
    }

    public function get_booking_stats() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nedostatečná oprávnění.');
        }

        $stats = MomBookingManager::get_instance()->get_booking_statistics();
        wp_send_json_success($stats);
    }
}
