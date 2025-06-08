<?php
/**
 * Course management class
 */
class MomCourseManager {

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

    public function get_all_courses($status = null) {
        $where = '';
        if ($status) {
            $where = $this->wpdb->prepare("WHERE status = %s", $status);
        }

        return $this->wpdb->get_results("
            SELECT * FROM {$this->wpdb->prefix}mom_courses
            $where
            ORDER BY created_at DESC
        ");
    }

    public function get_course($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}mom_courses WHERE id = %d",
            $id
        ));
    }

    public function create_course($data) {
        $sanitized = $this->sanitize_course_data($data);

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_courses',
            $sanitized
        );

        if ($result) {
            $course_id = $this->wpdb->insert_id;
            $this->generate_lessons($course_id);
            return $course_id;
        }

        return false;
    }

    public function update_course($id, $data) {
        $sanitized = $this->sanitize_course_data($data);

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_courses',
            $sanitized,
            ['id' => $id]
        );

        if ($result !== false) {
            // Regenerate lessons
            $this->delete_course_lessons($id);
            $this->generate_lessons($id);
            return true;
        }

        return false;
    }

    public function delete_course($id) {
        // Delete lessons first
        $this->delete_course_lessons($id);

        // Delete course
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'mom_courses',
            ['id' => $id]
        );
    }

    public function get_course_lessons($course_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT l.*,
                   (SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_bookings b
                    WHERE b.lesson_id = l.id AND b.booking_status = 'confirmed') as bookings_count
            FROM {$this->wpdb->prefix}mom_lessons l
            WHERE l.course_id = %d
            ORDER BY l.lesson_number ASC
        ", $course_id));
    }

    public function generate_lessons($course_id) {
        $course = $this->get_course($course_id);
        if (!$course) return false;

        $start_date = new DateTime($course->start_date);

        // Find first correct day of week
        while ($start_date->format('N') != $course->day_of_week) {
            $start_date->add(new DateInterval('P1D'));
        }

        // Generate lessons
        for ($i = 1; $i <= $course->lesson_count; $i++) {
            $lesson_datetime = clone $start_date;
            $lesson_datetime->setTime(
                date('H', strtotime($course->start_time)),
                date('i', strtotime($course->start_time))
            );

            $this->wpdb->insert(
                $this->wpdb->prefix . 'mom_lessons',
                [
                    'course_id' => $course_id,
                    'lesson_number' => $i,
                    'title' => $course->title . ' - Lekce ' . $i,
                    'date_time' => $lesson_datetime->format('Y-m-d H:i:s'),
                    'max_capacity' => $course->max_capacity,
                    'status' => 'active',
                    'description' => "Lekce č. $i kurzu: " . $course->title
                ]
            );

            $start_date->add(new DateInterval('P7D'));
        }

        return true;
    }

    private function delete_course_lessons($course_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'mom_lessons',
            ['course_id' => $course_id]
        );
    }

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
            'status' => sanitize_text_field($data['status'] ?? 'active')
        ];
    }

    public function get_course_statistics($course_id) {
        $lessons = $this->get_course_lessons($course_id);
        $total_bookings = 0;
        $total_capacity = 0;

        foreach ($lessons as $lesson) {
            $total_bookings += $lesson->bookings_count;
            $total_capacity += $lesson->max_capacity;
        }

        return [
            'lessons_count' => count($lessons),
            'total_bookings' => $total_bookings,
            'total_capacity' => $total_capacity,
            'occupancy_rate' => $total_capacity > 0 ? round(($total_bookings / $total_capacity) * 100, 1) : 0
        ];
    }
}
