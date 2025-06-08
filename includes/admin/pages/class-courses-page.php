<?php
/**
 * Courses Page
 * Single Responsibility: Handle courses page rendering and data preparation
 */
class MomCoursesPage {

    private $container;
    private $course_manager;
    private $template_renderer;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->course_manager = $container->get('course_manager');
        $this->template_renderer = $container->get('template_renderer');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Render courses overview page
     */
    public function render() {
        $data = $this->prepare_courses_data();
        $this->template_renderer->render('admin/courses-overview', $data);
    }

    /**
     * Render course form page (create/edit)
     */
    public function render_form() {
        $data = $this->prepare_form_data();
        $this->template_renderer->render('admin/course-form', $data);
    }

    /**
     * Render course registration page
     */
    public function render_registration() {
        if (!isset($_GET['course_id'])) {
            wp_die(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
        }

        $course_id = intval($_GET['course_id']);
        $data = $this->prepare_registration_data($course_id);

        if (!$data['course']) {
            wp_die(__('Kurz nebyl nalezen.', 'mom-booking-system'));
        }

        $this->template_renderer->render('admin/course-registration', $data);
    }

    /**
     * Prepare data for courses overview
     */
    private function prepare_courses_data() {
        $courses = $this->course_manager->get_all_courses();
        $courses_with_stats = [];

        foreach ($courses as $course) {
            $course_stats = $this->course_manager->get_course_statistics($course->id);
            $course->statistics = $course_stats;
            $courses_with_stats[] = $course;
        }

        return [
            'courses' => $courses_with_stats,
            'total_courses' => count($courses),
            'active_courses' => count(array_filter($courses, function($course) {
                return $course->status === 'active';
            })),
            'page_title' => __('Přehled kurzů', 'mom-booking-system'),
            'breadcrumbs' => $this->get_breadcrumbs('overview'),
            'show_empty_state' => empty($courses),
        ];
    }

    /**
     * Prepare data for course form
     */
    private function prepare_form_data() {
        $editing = isset($_GET['edit']);
        $course = null;

        if ($editing) {
            $course_id = intval($_GET['edit']);
            $course = $this->course_manager->get_course($course_id);

            if (!$course) {
                wp_die(__('Kurz nebyl nalezen.', 'mom-booking-system'));
            }
        }

        return [
            'editing' => $editing,
            'course' => $course,
            'days_of_week' => $this->get_days_of_week(),
            'form_action' => $editing ? 'update_course' : 'create_course',
            'page_title' => $editing ?
                sprintf(__('Upravit kurz: %s', 'mom-booking-system'), $course->title) :
                __('Nový kurz', 'mom-booking-system'),
            'breadcrumbs' => $this->get_breadcrumbs($editing ? 'edit' : 'new'),
            'submit_text' => $editing ?
                __('Aktualizovat kurz', 'mom-booking-system') :
                __('Vytvořit kurz', 'mom-booking-system'),
        ];
    }

    /**
     * Prepare data for course registration page
     */
    private function prepare_registration_data($course_id) {
        $course = $this->course_manager->get_course($course_id);

        if (!$course) {
            return ['course' => null];
        }

        $user_manager = $this->container->get('user_manager');
        $registration_manager = $this->container->get('registration_manager');

        $all_users = $user_manager->get_all_users();
        $registered_users = $user_manager->get_users_for_course($course_id);
        $course_stats = $registration_manager->get_course_registration_stats($course_id);

        // Get available users (not registered for this course)
        $registered_emails = array_column($registered_users, 'email');
        $available_users = array_filter($all_users, function($user) use ($registered_emails) {
            return !in_array($user->email, $registered_emails);
        });

        return [
            'course' => $course,
            'all_users' => $all_users,
            'registered_users' => $registered_users,
            'available_users' => $available_users,
            'course_stats' => $course_stats,
            'page_title' => sprintf(__('Registrace na kurz: %s', 'mom-booking-system'), $course->title),
            'breadcrumbs' => $this->get_breadcrumbs('registration'),
        ];
    }

    /**
     * Get days of week for form select
     */
    private function get_days_of_week() {
        return [
            1 => __('Pondělí', 'mom-booking-system'),
            2 => __('Úterý', 'mom-booking-system'),
            3 => __('Středa', 'mom-booking-system'),
            4 => __('Čtvrtek', 'mom-booking-system'),
            5 => __('Pátek', 'mom-booking-system'),
            6 => __('Sobota', 'mom-booking-system'),
            7 => __('Neděle', 'mom-booking-system'),
        ];
    }

    /**
     * Get breadcrumbs for different page states
     */
    private function get_breadcrumbs($type) {
        $base_breadcrumb = [
            'title' => __('Kurzy maminek', 'mom-booking-system'),
            'url' => admin_url('admin.php?page=mom-booking-admin')
        ];

        switch ($type) {
            case 'overview':
                return [$base_breadcrumb];

            case 'new':
                return [
                    $base_breadcrumb,
                    ['title' => __('Nový kurz', 'mom-booking-system'), 'url' => null]
                ];

            case 'edit':
                return [
                    $base_breadcrumb,
                    ['title' => __('Upravit kurz', 'mom-booking-system'), 'url' => null]
                ];

            case 'registration':
                return [
                    $base_breadcrumb,
                    ['title' => __('Registrace na kurz', 'mom-booking-system'), 'url' => null]
                ];

            default:
                return [$base_breadcrumb];
        }
    }

    /**
     * Get course status options
     */
    public function get_status_options() {
        return [
            'active' => __('Aktivní', 'mom-booking-system'),
            'inactive' => __('Neaktivní', 'mom-booking-system'),
            'completed' => __('Dokončený', 'mom-booking-system'),
            'cancelled' => __('Zrušený', 'mom-booking-system'),
        ];
    }

    /**
     * Get formatted course duration
     */
    public function format_course_duration($minutes) {
        if ($minutes < 60) {
            return sprintf(__('%d minut', 'mom-booking-system'), $minutes);
        }

        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($remaining_minutes === 0) {
            return sprintf(__('%d hodin', 'mom-booking-system'), $hours);
        }

        return sprintf(__('%d hodin %d minut', 'mom-booking-system'), $hours, $remaining_minutes);
    }

    /**
     * Get course occupancy rate as percentage
     */
    public function get_occupancy_rate($course) {
        if (!isset($course->statistics)) {
            return 0;
        }

        $stats = $course->statistics;
        if ($stats['total_capacity'] === 0) {
            return 0;
        }

        return round(($stats['total_bookings'] / $stats['total_capacity']) * 100, 1);
    }

    /**
     * Get occupancy rate CSS class for styling
     */
    public function get_occupancy_class($rate) {
        if ($rate >= 90) {
            return 'occupancy-high';
        } elseif ($rate >= 60) {
            return 'occupancy-medium';
        } else {
            return 'occupancy-low';
        }
    }

    /**
     * Check if course can be deleted
     */
    public function can_delete_course($course_id) {
        global $wpdb;

        // Check if course has any confirmed bookings
        $bookings_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE l.course_id = %d AND b.booking_status = 'confirmed'
        ", $course_id));

        return $bookings_count == 0;
    }

    /**
     * Get course lessons with booking information
     */
    public function get_course_lessons_with_bookings($course_id) {
        return $this->course_manager->get_course_lessons($course_id);
    }

    /**
     * Format course schedule for display
     */
    public function format_course_schedule($course) {
        $days = $this->get_days_of_week();
        $day_name = $days[$course->day_of_week] ?? '';
        $time = date('H:i', strtotime($course->start_time));

        return sprintf('%s %s', $day_name, $time);
    }

    /**
     * Get course price formatted
     */
    public function format_course_price($price) {
        if ($price == 0) {
            return __('Zdarma', 'mom-booking-system');
        }

        return number_format($price, 0, ',', ' ') . ' Kč';
    }

    /**
     * Check if course is starting soon (within 7 days)
     */
    public function is_course_starting_soon($course) {
        $start_timestamp = strtotime($course->start_date);
        $week_from_now = time() + (7 * 24 * 60 * 60);

        return $start_timestamp <= $week_from_now && $start_timestamp > time();
    }

    /**
     * Get course status badge HTML
     */
    public function get_status_badge($status) {
        $status_classes = [
            'active' => 'status-badge status-active',
            'inactive' => 'status-badge status-inactive',
            'completed' => 'status-badge status-completed',
            'cancelled' => 'status-badge status-cancelled',
        ];

        $status_labels = $this->get_status_options();

        $class = $status_classes[$status] ?? 'status-badge';
        $label = $status_labels[$status] ?? ucfirst($status);

        return sprintf('<span class="%s">%s</span>', esc_attr($class), esc_html($label));
    }
}
