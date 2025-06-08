<?php
/**
 * Lesson management class - Open/Closed Principle
 */
class MomLessonManager {

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

    public function get_lesson($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT l.*, c.title as course_title, c.lesson_duration
             FROM {$this->wpdb->prefix}mom_lessons l
             LEFT JOIN {$this->wpdb->prefix}mom_courses c ON l.course_id = c.id
             WHERE l.id = %d",
            $id
        ));
    }

    /**
     * NEW: Update individual lesson
     */
    public function update_lesson($lesson_id, $data) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) {
            return new WP_Error('lesson_not_found', 'Lekce nebyla nalezena.');
        }

        $sanitized = $this->sanitize_lesson_data($data);

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_lessons',
            $sanitized,
            ['id' => $lesson_id]
        );

        return $result !== false;
    }

    /**
     * NEW: Get lesson with time calculations
     */
    public function get_lesson_with_times($lesson_id) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) {
            return null;
        }

        // Calculate end time
        $start_time = new DateTime($lesson->date_time);
        $end_time = clone $start_time;
        $end_time->add(new DateInterval('PT' . $lesson->lesson_duration . 'M'));

        $lesson->start_time = $start_time->format('H:i');
        $lesson->end_time = $end_time->format('H:i');
        $lesson->start_datetime = $start_time->format('Y-m-d H:i:s');
        $lesson->end_datetime = $end_time->format('Y-m-d H:i:s');

        return $lesson;
    }

    /**
     * NEW: Get lesson participants
     */
    public function get_lesson_participants($lesson_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT b.*,
                   COALESCE(c.name, b.customer_name) as customer_name,
                   COALESCE(c.child_name, '') as child_name,
                   COALESCE(c.phone, b.customer_phone) as customer_phone,
                   c.id as customer_id
            FROM {$this->wpdb->prefix}mom_bookings b
            LEFT JOIN {$this->wpdb->prefix}mom_customers c ON (b.customer_id = c.id OR b.customer_email = c.email)
            WHERE b.lesson_id = %d AND b.booking_status = 'confirmed'
            ORDER BY customer_name ASC
        ", $lesson_id));
    }

    /**
     * NEW: Add user to lesson (admin function)
     */
    public function add_user_to_lesson($lesson_id, $user_id) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) {
            return new WP_Error('lesson_not_found', 'Lekce nebyla nalezena.');
        }

        $user = MomUserManager::get_instance()->get_user($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'Uživatel nebyl nalezen.');
        }

        // Check capacity
        if ($lesson->current_bookings >= $lesson->max_capacity) {
            return new WP_Error('lesson_full', 'Lekce je již plně obsazena.');
        }

        // Check if already booked
        $existing = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT id FROM {$this->wpdb->prefix}mom_bookings
            WHERE lesson_id = %d AND (customer_id = %d OR customer_email = %s) AND booking_status = 'confirmed'
        ", $lesson_id, $user_id, $user->email));

        if ($existing) {
            return new WP_Error('already_booked', 'Uživatel je již na tuto lekci přihlášen.');
        }

        // Create booking
        $booking_data = [
            'lesson_id' => $lesson_id,
            'customer_id' => $user_id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'booking_status' => 'confirmed',
            'notes' => 'Přidáno administrátorem'
        ];

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_bookings',
            $booking_data
        );

        if ($result) {
            // Update lesson booking count
            $this->update_lesson_booking_count($lesson_id, 1);
            return $this->wpdb->insert_id;
        }

        return new WP_Error('db_error', 'Chyba při vytváření rezervace.');
    }

    /**
     * NEW: Remove user from lesson
     */
    public function remove_user_from_lesson($lesson_id, $user_identifier) {
        // User identifier can be user_id or email
        if (is_numeric($user_identifier)) {
            $where_clause = $this->wpdb->prepare("(customer_id = %d OR customer_email = (SELECT email FROM {$this->wpdb->prefix}mom_customers WHERE id = %d))", $user_identifier, $user_identifier);
        } else {
            $where_clause = $this->wpdb->prepare("customer_email = %s", $user_identifier);
        }

        $booking = $this->wpdb->get_row("
            SELECT * FROM {$this->wpdb->prefix}mom_bookings
            WHERE lesson_id = $lesson_id AND $where_clause AND booking_status = 'confirmed'
        ");

        if (!$booking) {
            return new WP_Error('booking_not_found', 'Rezervace nebyla nalezena.');
        }

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_bookings',
            ['booking_status' => 'cancelled'],
            ['id' => $booking->id]
        );

        if ($result) {
            $this->update_lesson_booking_count($lesson_id, -1);
            return true;
        }

        return false;
    }

    /**
     * NEW: Cancel/Activate lesson
     */
    public function toggle_lesson_status($lesson_id) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) {
            return false;
        }

        $new_status = ($lesson->status === 'active') ? 'cancelled' : 'active';

        return $this->wpdb->update(
            $this->wpdb->prefix . 'mom_lessons',
            ['status' => $new_status],
            ['id' => $lesson_id]
        ) !== false;
    }

    private function update_lesson_booking_count($lesson_id, $change) {
        $this->wpdb->query($this->wpdb->prepare("
            UPDATE {$this->wpdb->prefix}mom_lessons
            SET current_bookings = GREATEST(0, current_bookings + %d)
            WHERE id = %d
        ", $change, $lesson_id));
    }

    private function sanitize_lesson_data($data) {
        $sanitized = [];

        if (isset($data['title'])) {
            $sanitized['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['date_time'])) {
            $sanitized['date_time'] = sanitize_text_field($data['date_time']);
        }

        if (isset($data['max_capacity'])) {
            $sanitized['max_capacity'] = intval($data['max_capacity']);
        }

        if (isset($data['status'])) {
            $sanitized['status'] = sanitize_text_field($data['status']);
        }

        return $sanitized;
    }

    /**
     * NEW: Get lesson schedule for display
     */
    public function get_lesson_schedule($lesson_id) {
        $lesson = $this->get_lesson_with_times($lesson_id);
        if (!$lesson) {
            return null;
        }

        $participants = $this->get_lesson_participants($lesson_id);

        return [
            'lesson' => $lesson,
            'participants' => $participants,
            'available_spots' => $lesson->max_capacity - $lesson->current_bookings
        ];
    }
}
