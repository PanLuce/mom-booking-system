<?php
/**
 * Správa kurzů - vytváření, úpravy, generování lekcí
 */
class MomCourseManager {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_all_courses() {
        return $this->wpdb->get_results("
            SELECT * FROM {$this->wpdb->prefix}mom_courses
            ORDER BY start_date DESC
        ");
    }

    public function get_course($course_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_courses WHERE id = %d",
                $course_id
            )
        );
    }

    public function get_course_lessons($course_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}mom_lessons
                WHERE course_id = %d
                ORDER BY lesson_number ASC
            ", $course_id)
        );
    }

    public function handle_course_form() {
        $course_data = $this->sanitize_course_data($_POST);

        if (isset($_POST['course_id'])) {
            // Aktualizace existujícího kurzu
            $this->update_course($_POST['course_id'], $course_data);
        } else {
            // Vytvoření nového kurzu
            $this->create_course($course_data);
        }
    }

    private function sanitize_course_data($data) {
        return [
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date']),
            'lesson_count' => intval($data['lesson_count']),
            'day_of_week' => intval($data['day_of_week']),
            'start_time' => sanitize_text_field($data['start_time']),
            'lesson_duration' => intval($data['lesson_duration']),
            'max_capacity' => intval($data['max_capacity']),
            'price' => floatval($data['price'] ?? 0)
        ];
    }

    private function create_course($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_courses',
            $data
        );

        if ($result) {
            $course_id = $this->wpdb->insert_id;
            $this->generate_course_lessons($course_id);

            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_id=' . $course_id));
            exit;
        } else {
            wp_die('Chyba při vytváření kurzu: ' . $this->wpdb->last_error);
        }
    }

    private function update_course($course_id, $data) {
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_courses',
            $data,
            ['id' => $course_id]
        );

        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_id=' . $course_id));
            exit;
        } else {
            wp_die('Chyba při aktualizaci kurzu: ' . $this->wpdb->last_error);
        }
    }

    public function generate_course_lessons($course_id) {
        $course = $this->get_course($course_id);
        if (!$course) {
            return false;
        }

        // Smaž existující lekce (pokud jsou)
        $this->wpdb->delete(
            $this->wpdb->prefix . 'mom_lessons',
            ['course_id' => $course_id]
        );

        $date_helper = new MomDateHelper();
        $lesson_dates = $date_helper->generate_lesson_dates(
            $course->start_date,
            $course->day_of_week,
            $course->lesson_count
        );

        foreach ($lesson_dates as $index => $date) {
            $lesson_number = $index + 1;
            $lesson_datetime = $date . ' ' . $course->start_time;

            $this->wpdb->insert(
                $this->wpdb->prefix . 'mom_lessons',
                [
                    'course_id' => $course_id,
                    'lesson_number' => $lesson_number,
                    'title' => $course->title . ' - Lekce ' . $lesson_number,
                    'date_time' => $lesson_datetime,
                    'max_capacity' => $course->max_capacity,
                    'status' => 'active'
                ]
            );
        }

        return true;
    }

    public function cancel_lesson($lesson_id) {
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_lessons',
            ['status' => 'cancelled'],
            ['id' => $lesson_id]
        );

        if ($result !== false) {
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    public function get_course_statistics($course_id) {
        $lessons = $this->get_course_lessons($course_id);
        $total_lessons = count($lessons);
        $cancelled_lessons = count(array_filter($lessons, function($lesson) {
            return $lesson->status === 'cancelled';
        }));

        return [
            'total_lessons' => $total_lessons,
            'active_lessons' => $total_lessons - $cancelled_lessons,
            'cancelled_lessons' => $cancelled_lessons
        ];
    }
}
