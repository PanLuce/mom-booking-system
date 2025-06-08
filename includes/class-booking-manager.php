<?php
/**
 * Booking management class
 */
class MomBookingManager {

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

    public function get_all_bookings($status = null) {
        $where = '';
        if ($status) {
            $where = $this->wpdb->prepare("WHERE b.booking_status = %s", $status);
        }

        return $this->wpdb->get_results("
            SELECT b.*,
                   l.title as lesson_title,
                   l.date_time,
                   c.title as course_title
            FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
            $where
            ORDER BY l.date_time DESC
        ");
    }

    public function get_booking($id) {
        return $this->wpdb->get_row($this->wpdb->prepare("
            SELECT b.*,
                   l.title as lesson_title,
                   l.date_time,
                   c.title as course_title
            FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
            WHERE b.id = %d
        ", $id));
    }

    public function create_booking($data) {
        $lesson_id = intval($data['lesson_id']);

        // Check lesson availability
        $lesson = $this->get_lesson_availability($lesson_id);
        if (!$lesson) {
            return new WP_Error('lesson_not_found', 'Lekce nebyla nalezena.');
        }

        if ($lesson->current_bookings >= $lesson->max_capacity) {
            return new WP_Error('lesson_full', 'Lekce je již plně obsazena.');
        }

        // Check for duplicate booking
        $existing = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT id FROM {$this->wpdb->prefix}mom_bookings
            WHERE lesson_id = %d AND customer_email = %s AND booking_status = 'confirmed'
        ", $lesson_id, $data['customer_email']));

        if ($existing) {
            return new WP_Error('duplicate_booking', 'Již máte rezervaci na tuto lekci.');
        }

        $sanitized = $this->sanitize_booking_data($data);

        // Create booking
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_bookings',
            $sanitized
        );

        if ($result) {
            $booking_id = $this->wpdb->insert_id;

            // Update lesson booking count
            $this->update_lesson_booking_count($lesson_id, 1);

            // Send confirmation email
            $this->send_confirmation_email($booking_id);

            return $booking_id;
        }

        return new WP_Error('db_error', 'Chyba při vytváření rezervace: ' . $this->wpdb->last_error);
    }

    public function cancel_booking($id) {
        $booking = $this->get_booking($id);
        if (!$booking) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_bookings',
            ['booking_status' => 'cancelled'],
            ['id' => $id]
        );

        if ($result) {
            // Update lesson booking count
            $this->update_lesson_booking_count($booking->lesson_id, -1);
            return true;
        }

        return false;
    }

    public function get_available_lessons($course_id = null, $show_past = false, $limit = 20) {
        $where_conditions = ["l.status = 'active'"];

        if ($course_id) {
            $where_conditions[] = $this->wpdb->prepare("l.course_id = %d", $course_id);
        }

        if (!$show_past) {
            $where_conditions[] = "l.date_time > NOW()";
        }

        $where_conditions[] = "l.current_bookings < l.max_capacity";

        $where_clause = "WHERE " . implode(" AND ", $where_conditions);

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT l.*,
                   (l.max_capacity - l.current_bookings) as available_spots,
                   c.title as course_title,
                   c.price as course_price
            FROM {$this->wpdb->prefix}mom_lessons l
            LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
            $where_clause
            ORDER BY l.date_time ASC
            LIMIT %d
        ", $limit));
    }

    private function get_lesson_availability($lesson_id) {
        return $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->wpdb->prefix}mom_lessons
            WHERE id = %d AND status = 'active'
        ", $lesson_id));
    }

    private function update_lesson_booking_count($lesson_id, $change) {
        $this->wpdb->query($this->wpdb->prepare("
            UPDATE {$this->wpdb->prefix}mom_lessons
            SET current_bookings = GREATEST(0, current_bookings + %d)
            WHERE id = %d
        ", $change, $lesson_id));
    }

    private function sanitize_booking_data($data) {
        return [
            'lesson_id' => intval($data['lesson_id']),
            'customer_id' => isset($data['customer_id']) ? intval($data['customer_id']) : null,
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'booking_status' => 'confirmed',
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        ];
    }

    private function send_confirmation_email($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) return;

        $subject = sprintf(__('Potvrzení rezervace - %s', 'mom-booking-system'), $booking->lesson_title);

        $message = sprintf(__("Dobrý den %s,\n\nVaše rezervace byla úspěšně vytvořena:\n\nLekce: %s\nDatum a čas: %s\n\nTěšíme se na vás!", 'mom-booking-system'),
            $booking->customer_name,
            $booking->lesson_title,
            date('d.m.Y H:i', strtotime($booking->date_time))
        );

        wp_mail($booking->customer_email, $subject, $message);
    }

    public function get_booking_statistics() {
        $total_bookings = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_bookings
            WHERE booking_status = 'confirmed'
        ");

        $today_bookings = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_bookings
            WHERE booking_status = 'confirmed' AND DATE(created_at) = CURDATE()
        ");

        $upcoming_lessons = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_lessons
            WHERE status = 'active' AND date_time > NOW()
        ");

        return [
            'total_bookings' => (int) $total_bookings,
            'today_bookings' => (int) $today_bookings,
            'upcoming_lessons' => (int) $upcoming_lessons
        ];
    }
}
