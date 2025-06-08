<?php
/**
 * Course Registration Manager - Dependency Inversion Principle
 */
class MomCourseRegistrationManager {

    private static $instance = null;
    private $wpdb;
    private $user_manager;
    private $lesson_manager;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_manager = MomUserManager::get_instance();
        $this->lesson_manager = MomLessonManager::get_instance();
    }

    /**
     * NEW: Register user for entire course
     */
    public function register_user_for_course($course_id, $user_id, $options = []) {
        $course = MomCourseManager::get_instance()->get_course($course_id);
        if (!$course) {
            return new WP_Error('course_not_found', 'Kurz nebyl nalezen.');
        }

        $user = $this->user_manager->get_user($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'Uživatel nebyl nalezen.');
        }

        // Get all course lessons
        $lessons = MomCourseManager::get_instance()->get_course_lessons($course_id);
        if (empty($lessons)) {
            return new WP_Error('no_lessons', 'Kurz nemá žádné lekce.');
        }

        // Check if user is already registered for any lesson
        $existing_registrations = $this->get_user_course_registrations($user_id, $course_id);
        if (!empty($existing_registrations)) {
            return new WP_Error('already_registered', 'Uživatel je již na některé lekce kurzu registrován.');
        }

        $successful_bookings = [];
        $failed_bookings = [];

        // Register for each lesson
        foreach ($lessons as $lesson) {
            // Skip past lessons unless specified
            if (!isset($options['include_past']) || !$options['include_past']) {
                if (strtotime($lesson->date_time) < time()) {
                    continue;
                }
            }

            // Skip cancelled lessons unless specified
            if ($lesson->status !== 'active' && (!isset($options['include_cancelled']) || !$options['include_cancelled'])) {
                continue;
            }

            // Check capacity
            if ($lesson->current_bookings >= $lesson->max_capacity) {
                $failed_bookings[] = [
                    'lesson_id' => $lesson->id,
                    'lesson_title' => $lesson->title,
                    'reason' => 'Lekce je plně obsazena'
                ];
                continue;
            }

            // Create booking
            $booking_result = $this->lesson_manager->add_user_to_lesson($lesson->id, $user_id);
            if (is_wp_error($booking_result)) {
                $failed_bookings[] = [
                    'lesson_id' => $lesson->id,
                    'lesson_title' => $lesson->title,
                    'reason' => $booking_result->get_error_message()
                ];
            } else {
                $successful_bookings[] = [
                    'lesson_id' => $lesson->id,
                    'lesson_title' => $lesson->title,
                    'booking_id' => $booking_result
                ];
            }
        }

        // Log course registration
        $this->log_course_registration($course_id, $user_id, count($successful_bookings), count($failed_bookings));

        return [
            'successful_bookings' => $successful_bookings,
            'failed_bookings' => $failed_bookings,
            'total_registered' => count($successful_bookings)
        ];
    }

    /**
     * NEW: Unregister user from entire course
     */
    public function unregister_user_from_course($course_id, $user_id, $options = []) {
        $registrations = $this->get_user_course_registrations($user_id, $course_id);

        if (empty($registrations)) {
            return new WP_Error('not_registered', 'Uživatel není na kurz registrován.');
        }

        $successful_cancellations = [];
        $failed_cancellations = [];

        foreach ($registrations as $booking) {
            // Skip past lessons unless specified
            if (!isset($options['include_past']) || !$options['include_past']) {
                if (strtotime($booking->date_time) < time()) {
                    continue;
                }
            }

            $cancel_result = $this->lesson_manager->remove_user_from_lesson($booking->lesson_id, $user_id);
            if (is_wp_error($cancel_result)) {
                $failed_cancellations[] = [
                    'lesson_id' => $booking->lesson_id,
                    'lesson_title' => $booking->lesson_title,
                    'reason' => $cancel_result->get_error_message()
                ];
            } else {
                $successful_cancellations[] = [
                    'lesson_id' => $booking->lesson_id,
                    'lesson_title' => $booking->lesson_title
                ];
            }
        }

        return [
            'successful_cancellations' => $successful_cancellations,
            'failed_cancellations' => $failed_cancellations,
            'total_cancelled' => count($successful_cancellations)
        ];
    }

    /**
     * NEW: Get user's registrations for specific course
     */
    public function get_user_course_registrations($user_id, $course_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT b.*, l.title as lesson_title, l.date_time, l.lesson_number
            FROM {$this->wpdb->prefix}mom_bookings b
            JOIN {$this->wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE l.course_id = %d
            AND (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$this->wpdb->prefix}mom_customers WHERE id = %d
            ))
            AND b.booking_status = 'confirmed'
            ORDER BY l.lesson_number ASC
        ", $course_id, $user_id, $user_id));
    }

    /**
     * NEW: Register multiple users for course (bulk registration)
     */
    public function bulk_register_for_course($course_id, $user_ids, $options = []) {
        $results = [];

        foreach ($user_ids as $user_id) {
            $result = $this->register_user_for_course($course_id, $user_id, $options);
            $results[$user_id] = $result;
        }

        return $results;
    }

    /**
     * NEW: Get course registration statistics
     */
    public function get_course_registration_stats($course_id) {
        $lessons = MomCourseManager::get_instance()->get_course_lessons($course_id);

        $stats = [
            'total_lessons' => count($lessons),
            'total_capacity' => 0,
            'total_bookings' => 0,
            'unique_participants' => 0,
            'lessons_stats' => []
        ];

        $unique_emails = [];

        foreach ($lessons as $lesson) {
            $lesson_bookings = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT customer_email FROM {$this->wpdb->prefix}mom_bookings
                WHERE lesson_id = %d AND booking_status = 'confirmed'
            ", $lesson->id));

            $stats['total_capacity'] += $lesson->max_capacity;
            $stats['total_bookings'] += $lesson->bookings_count;

            foreach ($lesson_bookings as $booking) {
                $unique_emails[$booking->customer_email] = true;
            }

            $stats['lessons_stats'][] = [
                'lesson_id' => $lesson->id,
                'lesson_number' => $lesson->lesson_number,
                'title' => $lesson->title,
                'date_time' => $lesson->date_time,
                'capacity' => $lesson->max_capacity,
                'bookings' => $lesson->bookings_count,
                'available' => $lesson->max_capacity - $lesson->bookings_count
            ];
        }

        $stats['unique_participants'] = count($unique_emails);
        $stats['occupancy_rate'] = $stats['total_capacity'] > 0
            ? round(($stats['total_bookings'] / $stats['total_capacity']) * 100, 1)
            : 0;

        return $stats;
    }

    private function log_course_registration($course_id, $user_id, $successful, $failed) {
        // Log registration for analytics/audit trail
        $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_course_registrations',
            [
                'course_id' => $course_id,
                'user_id' => $user_id,
                'successful_bookings' => $successful,
                'failed_bookings' => $failed,
                'registration_date' => current_time('mysql')
            ]
        );
    }

    /**
     * NEW: Check if user can register for course
     */
    public function can_user_register_for_course($course_id, $user_id) {
        $course = MomCourseManager::get_instance()->get_course($course_id);
        if (!$course || $course->status !== 'active') {
            return ['can_register' => false, 'reason' => 'Kurz není aktivní'];
        }

        $existing_registrations = $this->get_user_course_registrations($user_id, $course_id);
        if (!empty($existing_registrations)) {
            return ['can_register' => false, 'reason' => 'Uživatel je již registrován'];
        }

        $lessons = MomCourseManager::get_instance()->get_course_lessons($course_id);
        $available_lessons = array_filter($lessons, function($lesson) {
            return $lesson->status === 'active'
                && strtotime($lesson->date_time) > time()
                && $lesson->bookings_count < $lesson->max_capacity;
        });

        if (empty($available_lessons)) {
            return ['can_register' => false, 'reason' => 'Žádné dostupné lekce'];
        }

        return ['can_register' => true, 'available_lessons' => count($available_lessons)];
    }
}
