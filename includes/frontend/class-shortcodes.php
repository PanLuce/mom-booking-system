<?php
/**
 * Frontend shortcodes class
 */
class MomBookingShortcodes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('mom_booking_calendar', [$this, 'booking_calendar']);
        add_shortcode('mom_course_list', [$this, 'course_list']);
        add_shortcode('mom_booking_form', [$this, 'booking_form']);
    }

    public function booking_calendar($atts) {
        $atts = shortcode_atts([
            'course_id' => '',
            'show_past' => 'false',
            'limit' => '10',
            'show_price' => 'true'
        ], $atts);

        ob_start();
        include MOM_BOOKING_PLUGIN_DIR . 'templates/frontend/booking-calendar.php';
        return ob_get_clean();
    }

    public function course_list($atts) {
        $atts = shortcode_atts([
            'status' => 'active',
            'limit' => '5',
            'show_price' => 'true',
            'show_description' => 'true'
        ], $atts);

        $courses = MomCourseManager::get_instance()->get_all_courses($atts['status']);
        if (!empty($atts['limit'])) {
            $courses = array_slice($courses, 0, intval($atts['limit']));
        }

        if (empty($courses)) {
            return '<p>' . __('Momentálně nejsou dostupné žádné kurzy.', 'mom-booking-system') . '</p>';
        }

        ob_start();
        include MOM_BOOKING_PLUGIN_DIR . 'templates/frontend/course-list.php';
        return ob_get_clean();
    }

    public function booking_form($atts) {
        $atts = shortcode_atts([
            'lesson_id' => '',
            'redirect_url' => ''
        ], $atts);

        if (empty($atts['lesson_id'])) {
            return '<p>' . __('ID lekce nebylo specifikováno.', 'mom-booking-system') . '</p>';
        }

        ob_start();
        include MOM_BOOKING_PLUGIN_DIR . 'templates/frontend/booking-form.php';
        return ob_get_clean();
    }
}
