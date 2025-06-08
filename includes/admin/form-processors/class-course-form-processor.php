<?php
/**
 * Course Form Processor
 * Single Responsibility: Process course-related form submissions
 */
class MomCourseFormProcessor {

    private $container;
    private $course_manager;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->course_manager = $container->get('course_manager');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Process form submission
     * @param string $action Form action
     * @param array $data Form data
     */
    public function process($action, $data) {
        switch ($action) {
            case 'create_course':
                $this->handle_create_course($data);
                break;

            case 'update_course':
                $this->handle_update_course($data);
                break;

            case 'delete_course':
                $this->handle_delete_course($data);
                break;

            case 'register_user_for_course':
                $this->handle_register_user_for_course($data);
                break;

            case 'bulk_register_users':
                $this->handle_bulk_register_users($data);
                break;

            default:
                throw new Exception("Unknown course action: {$action}");
        }
    }

    /**
     * Handle course creation
     */
    private function handle_create_course($data) {
        // Validate required fields
        $required_fields = ['title', 'start_date', 'lesson_count', 'day_of_week', 'start_time', 'max_capacity'];
        $this->validate_required_fields($data, $required_fields);

        // Additional validation
        $this->validate_course_data($data);

        // Check for schedule conflicts
        $this->check_schedule_conflicts($data);

        // Sanitize data
        $sanitized_data = $this->sanitize_course_data($data);

        // Create course
        $course_id = $this->course_manager->create_course($sanitized_data);

        if (!$course_id) {
            throw new Exception(__('Chyba při vytváření kurzu.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-booking-admin', 'course_created', [
            'course_id' => $course_id
        ]);
    }

    /**
     * Handle course update
     */
    private function handle_update_course($data) {
        $course_id = intval($data['course_id'] ?? 0);

        if (!$course_id) {
            throw new Exception(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if course exists
        $existing_course = $this->course_manager->get_course($course_id);
        if (!$existing_course) {
            throw new Exception(__('Kurz nebyl nalezen.', 'mom-booking-system'));
        }

        // Validate required fields
        $required_fields = ['title', 'start_date', 'lesson_count', 'day_of_week', 'start_time', 'max_capacity'];
        $this->validate_required_fields($data, $required_fields);

        // Additional validation
        $this->validate_course_data($data);

        // Check for schedule conflicts (excluding current course)
        $this->check_schedule_conflicts($data, $course_id);

        // Check if course can be updated
        $this->validate_course_update($course_id, $data);

        // Sanitize data
        $sanitized_data = $this->sanitize_course_data($data);

        // Update course
        $success = $this->course_manager->update_course($course_id, $sanitized_data);

        if (!$success) {
            throw new Exception(__('Chyba při aktualizaci kurzu.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-booking-admin', 'course_updated', [
            'course_id' => $course_id
        ]);
    }

    /**
     * Handle course deletion
     */
    private function handle_delete_course($data) {
        $course_id = intval($data['course_id'] ?? 0);

        if (!$course_id) {
            throw new Exception(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if course exists
        $course = $this->course_manager->get_course($course_id);
        if (!$course) {
            throw new Exception(__('Kurz nebyl nalezen.', 'mom-booking-system'));
        }

        // Check if course can be deleted
        if (!$this->can_delete_course($course_id)) {
            throw new Exception(__('Kurz nelze smazat - obsahuje aktivní rezervace.', 'mom-booking-system'));
        }

        // Delete course
        $success = $this->course_manager->delete_course($course_id);

        if (!$success) {
            throw new Exception(__('Chyba při mazání kurzu.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-booking-admin', 'course_deleted');
    }

    /**
     * Handle user registration for course
     */
    private function handle_register_user_for_course($data) {
        $course_id = intval($data['course_id'] ?? 0);
        $user_id = intval($data['user_id'] ?? 0);

        if (!$course_id || !$user_id) {
            throw new Exception(__('ID kurzu nebo uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        $registration_manager = $this->container->get('registration_manager');
        $result = $registration_manager->register_user_for_course($course_id, $user_id);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-course-registration', 'user_registered', [
            'course_id' => $course_id,
            'registered_count' => $result['total_registered']
        ]);
    }

    /**
     * Handle bulk user registration
     */
    private function handle_bulk_register_users($data) {
        $course_id = intval($data['course_id'] ?? 0);
        $user_ids = $data['user_ids'] ?? [];

        if (!$course_id) {
            throw new Exception(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        if (empty($user_ids) || !is_array($user_ids)) {
            throw new Exception(__('Žádní uživatelé nebyli vybráni.', 'mom-booking-system'));
        }

        // Convert to integers
        $user_ids = array_map('intval', $user_ids);

        $registration_manager = $this->container->get('registration_manager');
        $results = $registration_manager->bulk_register_for_course($course_id, $user_ids);

        // Count successful registrations
        $total_success = 0;
        $total_errors = 0;

        foreach ($results as $user_id => $result) {
            if (is_wp_error($result)) {
                $total_errors++;
            } else {
                $total_success += $result['total_registered'];
            }
        }

        // Redirect with results
        $this->redirect_handler->bulk_action('mom-course-registration', 'bulk_register', $total_success, $total_errors);
    }

    /**
     * Validate required fields
     */
    private function validate_required_fields($data, $required_fields) {
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception(sprintf(
                    __('Pole %s je povinné.', 'mom-booking-system'),
                    $this->get_field_label($field)
                ));
            }
        }
    }

    /**
     * Validate course-specific data
     */
    private function validate_course_data($data) {
        // Validate lesson count
        $lesson_count = intval($data['lesson_count']);
        if ($lesson_count < 1 || $lesson_count > 52) {
            throw new Exception(__('Počet lekcí musí být mezi 1 a 52.', 'mom-booking-system'));
        }

        // Validate day of week
        $day_of_week = intval($data['day_of_week']);
        if ($day_of_week < 1 || $day_of_week > 7) {
            throw new Exception(__('Neplatný den v týdnu.', 'mom-booking-system'));
        }

        // Validate capacity
        $max_capacity = intval($data['max_capacity']);
        if ($max_capacity < 1 || $max_capacity > 100) {
            throw new Exception(__('Kapacita musí být mezi 1 a 100.', 'mom-booking-system'));
        }

        // Validate start date
        $start_date = $data['start_date'];
        if (!$this->is_valid_date($start_date)) {
            throw new Exception(__('Neplatné datum začátku.', 'mom-booking-system'));
        }

        // Check if start date is not in the past
        if (strtotime($start_date) < strtotime('today')) {
            throw new Exception(__('Datum začátku nemůže být v minulosti.', 'mom-booking-system'));
        }

        // Validate time format
        $start_time = $data['start_time'];
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start_time)) {
            throw new Exception(__('Neplatný formát času.', 'mom-booking-system'));
        }

        // Validate price if provided
        if (isset($data['price']) && !empty($data['price'])) {
            $price = floatval($data['price']);
            if ($price < 0) {
                throw new Exception(__('Cena nemůže být záporná.', 'mom-booking-system'));
            }
        }
    }

    /**
     * Check for schedule conflicts
     */
    private function check_schedule_conflicts($data, $exclude_course_id = null) {
        global $wpdb;

        $day_of_week = intval($data['day_of_week']);
        $start_time = $data['start_time'];
        $lesson_duration = intval($data['lesson_duration'] ?? 60);

        // Calculate end time
        $end_time = date('H:i', strtotime($start_time . ' +' . $lesson_duration . ' minutes'));

        $where_clause = "WHERE day_of_week = %d AND status = 'active'";
        $params = [$day_of_week];

        if ($exclude_course_id) {
            $where_clause .= " AND id != %d";
            $params[] = $exclude_course_id;
        }

        $conflicting_courses = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, start_time, lesson_duration
            FROM {$wpdb->prefix}mom_courses
            {$where_clause}
        ", $params));

        foreach ($conflicting_courses as $course) {
            $course_end_time = date('H:i', strtotime($course->start_time . ' +' . $course->lesson_duration . ' minutes'));

            // Check for time overlap
            if ($this->times_overlap($start_time, $end_time, $course->start_time, $course_end_time)) {
                throw new Exception(sprintf(
                    __('Konflikt v rozvrhu s kurzem "%s" (%s - %s).', 'mom-booking-system'),
                    $course->title,
                    $course->start_time,
                    $course_end_time
                ));
            }
        }
    }

    /**
     * Check if two time periods overlap
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        return (strtotime($start1) < strtotime($end2)) && (strtotime($end1) > strtotime($start2));
    }

    /**
     * Validate course update
     */
    private function validate_course_update($course_id, $data) {
        // Check if course has started
        $course = $this->course_manager->get_course($course_id);
        $start_date = strtotime($course->start_date);

        if ($start_date < strtotime('today')) {
            // Course has started - restrict what can be changed
            $restricted_fields = ['start_date', 'lesson_count', 'day_of_week', 'start_time'];
            $current_values = [
                'start_date' => $course->start_date,
                'lesson_count' => $course->lesson_count,
                'day_of_week' => $course->day_of_week,
                'start_time' => $course->start_time,
            ];

            foreach ($restricted_fields as $field) {
                if (isset($data[$field]) && $data[$field] != $current_values[$field]) {
                    throw new Exception(sprintf(
                        __('Pole %s nelze změnit u zahájeného kurzu.', 'mom-booking-system'),
                        $this->get_field_label($field)
                    ));
                }
            }
        }
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
     * Sanitize course data
     */
    private function sanitize_course_data($data) {
        return [
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date']),
            'lesson_count' => intval($data['lesson_count']),
            'day_of_week' => intval($data['day_of_week']),
            'start_time' => sanitize_text_field($data['start_time']),
            'lesson_duration' => intval($data['lesson_duration'] ?? 60),
            'max_capacity' => intval($data['max_capacity']),
            'price' => floatval($data['price'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
        ];
    }

    /**
     * Get field label for error messages
     */
    private function get_field_label($field) {
        $labels = [
            'title' => __('Název kurzu', 'mom-booking-system'),
            'start_date' => __('Datum začátku', 'mom-booking-system'),
            'lesson_count' => __('Počet lekcí', 'mom-booking-system'),
            'day_of_week' => __('Den v týdnu', 'mom-booking-system'),
            'start_time' => __('Čas začátku', 'mom-booking-system'),
            'max_capacity' => __('Maximální kapacita', 'mom-booking-system'),
            'price' => __('Cena', 'mom-booking-system'),
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Validate date format
     */
    private function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
