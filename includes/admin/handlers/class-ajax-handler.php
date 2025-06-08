<?php
/**
 * Admin AJAX Handler
 * Single Responsibility: Handle AJAX requests for admin interface
 */
class MomAdminAjaxHandler {

    private $container;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->init_ajax_hooks();
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        // Course-related AJAX actions
        add_action('wp_ajax_mom_toggle_course_lessons', [$this, 'toggle_course_lessons']);
        add_action('wp_ajax_mom_delete_course', [$this, 'delete_course']);
        add_action('wp_ajax_mom_get_course_stats', [$this, 'get_course_stats']);

        // User-related AJAX actions
        add_action('wp_ajax_mom_delete_user', [$this, 'delete_user']);
        add_action('wp_ajax_mom_search_users', [$this, 'search_users']);
        add_action('wp_ajax_mom_get_user_details', [$this, 'get_user_details']);

        // Booking-related AJAX actions
        add_action('wp_ajax_mom_cancel_booking', [$this, 'cancel_booking']);
        add_action('wp_ajax_mom_get_booking_stats', [$this, 'get_booking_stats']);
        add_action('wp_ajax_mom_update_booking_status', [$this, 'update_booking_status']);

        // Lesson-related AJAX actions
        add_action('wp_ajax_mom_toggle_lesson_status', [$this, 'toggle_lesson_status']);
        add_action('wp_ajax_mom_add_user_to_lesson', [$this, 'add_user_to_lesson']);
        add_action('wp_ajax_mom_remove_user_from_lesson', [$this, 'remove_user_from_lesson']);
        add_action('wp_ajax_mom_get_lesson_participants', [$this, 'get_lesson_participants']);

        // General utility AJAX actions
        add_action('wp_ajax_mom_validate_form', [$this, 'validate_form']);
        add_action('wp_ajax_mom_get_dashboard_stats', [$this, 'get_dashboard_stats']);
    }

    /**
     * Toggle course lessons display
     */
    public function toggle_course_lessons() {
        $this->verify_ajax_request();

        $course_id = intval($_POST['course_id'] ?? 0);

        if (!$course_id) {
            wp_send_json_error(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $course_manager = $this->container->get('course_manager');
            $lessons = $course_manager->get_course_lessons($course_id);

            wp_send_json_success(['lessons' => $lessons]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Delete course via AJAX
     */
    public function delete_course() {
        $this->verify_ajax_request();

        $course_id = intval($_POST['course_id'] ?? 0);

        if (!$course_id) {
            wp_send_json_error(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            // Check if course has bookings
            if (!$this->can_delete_course($course_id)) {
                wp_send_json_error(__('Kurz nelze smazat - obsahuje aktivní rezervace.', 'mom-booking-system'));
            }

            $course_manager = $this->container->get('course_manager');
            $success = $course_manager->delete_course($course_id);

            if ($success) {
                wp_send_json_success(__('Kurz byl úspěšně smazán.', 'mom-booking-system'));
            } else {
                wp_send_json_error(__('Chyba při mazání kurzu.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get course statistics
     */
    public function get_course_stats() {
        $this->verify_ajax_request();

        $course_id = intval($_POST['course_id'] ?? 0);

        if (!$course_id) {
            wp_send_json_error(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $course_manager = $this->container->get('course_manager');
            $stats = $course_manager->get_course_statistics($course_id);

            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Delete user via AJAX
     */
    public function delete_user() {
        $this->verify_ajax_request();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            // Check if user has active bookings
            if (!$this->can_delete_user($user_id)) {
                wp_send_json_error(__('Uživatel má aktivní rezervace a nelze ho smazat.', 'mom-booking-system'));
            }

            $user_manager = $this->container->get('user_manager');
            $result = $user_manager->delete_user($user_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } elseif ($result) {
                wp_send_json_success(__('Uživatel byl úspěšně smazán.', 'mom-booking-system'));
            } else {
                wp_send_json_error(__('Chyba při mazání uživatele.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Search users
     */
    public function search_users() {
        $this->verify_ajax_request();

        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);

        try {
            $users = $this->search_users_by_term($search_term, $limit);
            wp_send_json_success($users);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get user details
     */
    public function get_user_details() {
        $this->verify_ajax_request();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $user_manager = $this->container->get('user_manager');
            $user = $user_manager->get_user($user_id);

            if (!$user) {
                wp_send_json_error(__('Uživatel nebyl nalezen.', 'mom-booking-system'));
            }

            $user_stats = $user_manager->get_user_statistics($user_id);
            $user_bookings = $user_manager->get_user_bookings($user_id);

            wp_send_json_success([
                'user' => $user,
                'statistics' => $user_stats,
                'recent_bookings' => array_slice($user_bookings, 0, 5)
            ]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Cancel booking via AJAX
     */
    public function cancel_booking() {
        $this->verify_ajax_request();

        $booking_id = intval($_POST['booking_id'] ?? 0);

        if (!$booking_id) {
            wp_send_json_error(__('ID rezervace nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $booking_manager = $this->container->get('booking_manager');
            $success = $booking_manager->cancel_booking($booking_id);

            if ($success) {
                wp_send_json_success(__('Rezervace byla zrušena.', 'mom-booking-system'));
            } else {
                wp_send_json_error(__('Chyba při rušení rezervace.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get booking statistics
     */
    public function get_booking_stats() {
        $this->verify_ajax_request();

        try {
            $booking_manager = $this->container->get('booking_manager');
            $stats = $booking_manager->get_booking_statistics();

            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Update booking status
     */
    public function update_booking_status() {
        $this->verify_ajax_request();

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');

        if (!$booking_id || !$new_status) {
            wp_send_json_error(__('Neplatné parametry.', 'mom-booking-system'));
        }

        $valid_statuses = ['confirmed', 'cancelled', 'pending', 'waitlist'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(__('Neplatný stav rezervace.', 'mom-booking-system'));
        }

        try {
            global $wpdb;
            $result = $wpdb->update(
                $wpdb->prefix . 'mom_bookings',
                ['booking_status' => $new_status],
                ['id' => $booking_id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                wp_send_json_success(__('Stav rezervace byl aktualizován.', 'mom-booking-system'));
            } else {
                wp_send_json_error(__('Chyba při aktualizaci stavu.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Toggle lesson status
     */
    public function toggle_lesson_status() {
        $this->verify_ajax_request();

        $lesson_id = intval($_POST['lesson_id'] ?? 0);

        if (!$lesson_id) {
            wp_send_json_error(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $lesson_manager = $this->container->get('lesson_manager');
            $success = $lesson_manager->toggle_lesson_status($lesson_id);

            if ($success) {
                $lesson = $lesson_manager->get_lesson($lesson_id);
                wp_send_json_success([
                    'message' => __('Stav lekce byl změněn.', 'mom-booking-system'),
                    'new_status' => $lesson->status
                ]);
            } else {
                wp_send_json_error(__('Chyba při změně stavu lekce.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Add user to lesson
     */
    public function add_user_to_lesson() {
        $this->verify_ajax_request();

        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$lesson_id || !$user_id) {
            wp_send_json_error(__('Neplatné parametry.', 'mom-booking-system'));
        }

        try {
            $lesson_manager = $this->container->get('lesson_manager');
            $result = $lesson_manager->add_user_to_lesson($lesson_id, $user_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success(__('Uživatel byl přidán na lekci.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Remove user from lesson
     */
    public function remove_user_from_lesson() {
        $this->verify_ajax_request();

        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_identifier = $_POST['user_id'] ?? $_POST['user_email'] ?? '';

        if (!$lesson_id || empty($user_identifier)) {
            wp_send_json_error(__('Neplatné parametry.', 'mom-booking-system'));
        }

        try {
            $lesson_manager = $this->container->get('lesson_manager');
            $result = $lesson_manager->remove_user_from_lesson($lesson_id, $user_identifier);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } elseif ($result) {
                wp_send_json_success(__('Uživatel byl odebrán z lekce.', 'mom-booking-system'));
            } else {
                wp_send_json_error(__('Chyba při odebírání uživatele.', 'mom-booking-system'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get lesson participants
     */
    public function get_lesson_participants() {
        $this->verify_ajax_request();

        $lesson_id = intval($_POST['lesson_id'] ?? 0);

        if (!$lesson_id) {
            wp_send_json_error(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        try {
            $lesson_manager = $this->container->get('lesson_manager');
            $participants = $lesson_manager->get_lesson_participants($lesson_id);

            wp_send_json_success(['participants' => $participants]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Validate form data
     */
    public function validate_form() {
        $this->verify_ajax_request();

        $form_data = $_POST['form_data'] ?? [];
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (empty($form_data) || empty($action)) {
            wp_send_json_error(__('Neplatné parametry.', 'mom-booking-system'));
        }

        try {
            $form_handler = $this->container->get('form_handler');
            $errors = $form_handler->validate_form_data($action, $form_data);

            if (empty($errors)) {
                wp_send_json_success(['valid' => true]);
            } else {
                wp_send_json_success(['valid' => false, 'errors' => $errors]);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        $this->verify_ajax_request();

        try {
            $booking_manager = $this->container->get('booking_manager');
            $course_manager = $this->container->get('course_manager');
            $user_manager = $this->container->get('user_manager');

            $stats = [
                'bookings' => $booking_manager->get_booking_statistics(),
                'courses' => [
                    'total' => count($course_manager->get_all_courses()),
                    'active' => count($course_manager->get_all_courses('active'))
                ],
                'users' => [
                    'total' => count($user_manager->get_all_users())
                ]
            ];

            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Verify AJAX request security
     */
    private function verify_ajax_request() {
        // Check nonce
        if (!check_ajax_referer('mom_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Bezpečnostní kontrola selhala.', 'mom-booking-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nedostatečná oprávnění.', 'mom-booking-system'));
        }

        // Rate limiting
        $this->check_ajax_rate_limit();
    }

    /**
     * Check AJAX rate limiting
     */
    private function check_ajax_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = "mom_ajax_rate_limit_{$user_id}";

        $requests = get_transient($transient_key) ?: 0;

        // Allow max 60 AJAX requests per minute
        if ($requests >= 60) {
            wp_send_json_error(__('Příliš mnoho požadavků. Zkuste to prosím za chvíli.', 'mom-booking-system'));
        }

        set_transient($transient_key, $requests + 1, MINUTE_IN_SECONDS);
    }

    /**
     * Check if course can be deleted
     */
    private function can_delete_course($course_id) {
        global $wpdb;

        $bookings_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE l.course_id = %d AND b.booking_status = 'confirmed'
        ", $course_id));

        return $bookings_count == 0;
    }

    /**
     * Check if user can be deleted
     */
    private function can_delete_user($user_id) {
        global $wpdb;

        $future_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$wpdb->prefix}mom_customers WHERE id = %d
            ))
            AND b.booking_status = 'confirmed'
            AND l.date_time > NOW()
        ", $user_id, $user_id));

        return $future_bookings == 0;
    }

    /**
     * Search users by term
     */
    private function search_users_by_term($search_term, $limit = 10) {
        global $wpdb;

        if (empty($search_term)) {
            return [];
        }

        $search_like = '%' . $wpdb->esc_like($search_term) . '%';

        return $wpdb->get_results($wpdb->prepare("
            SELECT id, name, email, phone
            FROM {$wpdb->prefix}mom_customers
            WHERE name LIKE %s OR email LIKE %s
            ORDER BY name ASC
            LIMIT %d
        ", $search_like, $search_like, $limit));
    }

    /**
     * Log AJAX actions for debugging
     */
    private function log_ajax_action($action, $data = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'data_keys' => array_keys($data),
        ];

        error_log('MOM Booking AJAX: ' . json_encode($log_entry));
    }
}
