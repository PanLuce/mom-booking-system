<?php
/**
 * Správa zákaznic - CRUD operace
 */
class MomCustomerManager {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_all_customers() {
        return $this->wpdb->get_results("
            SELECT * FROM {$this->wpdb->prefix}mom_customers
            ORDER BY name ASC
        ");
    }

    public function get_customer($customer_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_customers WHERE id = %d",
                $customer_id
            )
        );
    }

    public function get_customer_by_email($email) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_customers WHERE email = %s",
                $email
            )
        );
    }

    public function create_customer($data) {
        $sanitized_data = $this->sanitize_customer_data($data);

        // Zkontroluj duplicitní email
        if ($this->get_customer_by_email($sanitized_data['email'])) {
            return new WP_Error('duplicate_email', 'Zákaznice s tímto emailem už existuje.');
        }

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_customers',
            $sanitized_data
        );

        if ($result) {
            return $this->wpdb->insert_id;
        }

        return new WP_Error('db_error', 'Chyba při vytváření zákaznice: ' . $this->wpdb->last_error);
    }

    public function update_customer($customer_id, $data) {
        $sanitized_data = $this->sanitize_customer_data($data);

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_customers',
            $sanitized_data,
            ['id' => $customer_id]
        );

        return $result !== false;
    }

    private function sanitize_customer_data($data) {
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

    public function get_customer_active_courses($customer_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("
                SELECT c.*, e.enrollment_date, e.status as enrollment_status
                FROM {$this->wpdb->prefix}mom_courses c
                JOIN {$this->wpdb->prefix}mom_course_enrollments e ON c.id = e.course_id
                WHERE e.customer_id = %d AND e.status = 'active'
                ORDER BY c.start_date DESC
            ", $customer_id)
        );
    }

    public function get_customer_statistics($customer_id) {
        $active_courses = $this->get_customer_active_courses($customer_id);

        $total_bookings = $this->wpdb->get_var(
            $this->wpdb->prepare("
                SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_bookings
                WHERE customer_id = %d AND booking_status = 'confirmed'
            ", $customer_id)
        );

        return [
            'active_courses' => count($active_courses),
            'total_bookings' => (int) $total_bookings
        ];
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

    public function get_customers_not_in_course($course_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("
                SELECT c.* FROM {$this->wpdb->prefix}mom_customers c
                WHERE c.id NOT IN (
                    SELECT e.customer_id FROM {$this->wpdb->prefix}mom_course_enrollments e
                    WHERE e.course_id = %d AND e.status = 'active'
                )
                ORDER BY c.name ASC
            ", $course_id)
        );
    }
}
