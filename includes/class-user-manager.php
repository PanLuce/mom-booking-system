<?php
/**
 * Extended User management class - Single Responsibility Principle
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

    /**
     * NEW: Update user with validation
     */
    public function update_user($id, $data) {
        $existing_user = $this->get_user($id);
        if (!$existing_user) {
            return new WP_Error('user_not_found', 'Uživatel nebyl nalezen.');
        }

        // Check email uniqueness (exclude current user)
        if (isset($data['email']) && $data['email'] !== $existing_user->email) {
            $duplicate = $this->get_user_by_email($data['email']);
            if ($duplicate && $duplicate->id != $id) {
                return new WP_Error('duplicate_email', 'Email je již používán jiným uživatelem.');
            }
        }

        $sanitized = $this->sanitize_user_data($data);

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_customers',
            $sanitized,
            ['id' => $id]
        );

        return $result !== false;
    }

    public function delete_user($id) {
        // Check for active bookings first
        $active_bookings = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$this->wpdb->prefix}mom_customers WHERE id = %d
            )) AND b.booking_status = 'confirmed' AND l.date_time > NOW()
        ", $id, $id));

        if ($active_bookings > 0) {
            return new WP_Error('has_bookings', 'Uživatel má aktivní budoucí rezervace a nelze ho smazat.');
        }

        return $this->wpdb->delete(
            $this->wpdb->prefix . 'mom_customers',
            ['id' => $id]
        );
    }

    public function get_user_bookings($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT b.*,
                   l.title as lesson_title,
                   l.date_time,
                   l.lesson_number,
                   c.title as course_title
            FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
            WHERE b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$this->wpdb->prefix}mom_customers WHERE id = %d
            )
            ORDER BY l.date_time DESC
        ", $user_id, $user_id));
    }

    /**
     * NEW: Get users available for booking (not already booked for lesson)
     */
    public function get_available_users_for_lesson($lesson_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT c.* FROM {$this->wpdb->prefix}mom_customers c
            WHERE c.id NOT IN (
                SELECT DISTINCT COALESCE(b.customer_id, 0)
                FROM {$this->wpdb->prefix}mom_bookings b
                WHERE b.lesson_id = %d AND b.booking_status = 'confirmed'
            )
            AND c.email NOT IN (
                SELECT DISTINCT b.customer_email
                FROM {$this->wpdb->prefix}mom_bookings b
                WHERE b.lesson_id = %d AND b.booking_status = 'confirmed'
            )
            ORDER BY c.name ASC
        ", $lesson_id, $lesson_id));
    }

    /**
     * NEW: Get users for specific course
     */
    public function get_users_for_course($course_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DISTINCT c.*, COUNT(b.id) as lessons_booked
            FROM {$this->wpdb->prefix}mom_customers c
            JOIN {$this->wpdb->prefix}mom_bookings b ON (c.id = b.customer_id OR c.email = b.customer_email)
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE l.course_id = %d AND b.booking_status = 'confirmed'
            GROUP BY c.id
            ORDER BY c.name ASC
        ", $course_id));
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

        $future_bookings = array_filter($confirmed_bookings, function($booking) {
            return strtotime($booking->date_time) > time();
        });

        return [
            'total_bookings' => count($bookings),
            'confirmed_bookings' => count($confirmed_bookings),
            'future_bookings' => count($future_bookings),
            'cancelled_bookings' => count($bookings) - count($confirmed_bookings)
        ];
    }
}
