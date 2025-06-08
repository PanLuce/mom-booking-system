<?php
/**
 * User management class
 */
class MomUserManager {

    private static $instance = null;
    private $wpdb;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_all_users() {
        return $this->wpdb->get_results("
            SELECT * FROM {$this->wpdb->prefix}mom_customers
            ORDER BY created_at DESC
        ");
    }

    public function get_user($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}mom_customers WHERE id = %d",
            $id
        ));
    }

    public function get_user_by_email($email) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}mom_customers WHERE email = %s",
            $email
        ));
    }

    public function create_user($data) {
        // Check for duplicate email
        if ($this->get_user_by_email($data['email'])) {
            return new WP_Error('duplicate_email', 'Uživatel s tímto emailem už existuje.');
        }

        $sanitized = $this->sanitize_user_data($data);

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_customers',
            $sanitized
        );

        if ($result) {
            return $this->wpdb->insert_id;
        }

        return new WP_Error('db_error', 'Chyba při vytváření uživatele: ' . $this->wpdb->last_error);
    }

    public function update_user($id, $data) {
        $sanitized = $this->sanitize_user_data($data);

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_customers',
            $sanitized,
            ['id' => $id]
        );

        return $result !== false;
    }

    public function delete_user($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'mom_customers',
            ['id' => $id]
        );
    }

    public function get_user_bookings($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT b.*, l.title as lesson_title, l.date_time, c.title as course_title
            FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
            WHERE b.customer_id = %d
            ORDER BY l.date_time DESC
        ", $user_id));
    }

    public function calculate_child_age($birth_date) {
        if (!$birth_date) {
            return null;
        }

        $birth = new DateTime($birth_date);
        $now = new DateTime();
        $diff = $birth->diff($now);

        if ($diff->y > 0) {
            return $diff->y . ' ' . ($diff->y === 1 ? 'rok' : ($diff->y < 5 ? 'roky' : 'let'));
        }

        return $diff->m . ' ' . ($diff->m === 1 ? 'měsíc' : ($diff->m < 5 ? 'měsíce' : 'měsíců'));
    }

    private function sanitize_user_data($data) {
        return [
            'name' => sanitize_text_field($data['name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'child_name' => sanitize_text_field($data['child_name'] ?? ''),
            'child_birth_date' => !empty($data['child_birth_date']) ? sanitize_text_field($data['child_birth_date']) : null,
            'emergency_contact' => sanitize_text_field($data['emergency_contact'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        ];
    }

    public function get_user_statistics($user_id) {
        $bookings = $this->get_user_bookings($user_id);

        $confirmed_bookings = array_filter($bookings, function($booking) {
            return $booking->booking_status === 'confirmed';
        });

        return [
            'total_bookings' => count($bookings),
            'confirmed_bookings' => count($confirmed_bookings),
            'cancelled_bookings' => count($bookings) - count($confirmed_bookings)
        ];
    }
}
