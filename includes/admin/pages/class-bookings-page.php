<?php
/**
 * Bookings Page
 * Single Responsibility: Handle bookings page rendering and data preparation
 */
class MomBookingsPage {

    private $container;
    private $booking_manager;
    private $template_renderer;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->booking_manager = $container->get('booking_manager');
        $this->template_renderer = $container->get('template_renderer');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Render bookings overview page
     */
    public function render() {
        $data = $this->prepare_bookings_data();
        $this->template_renderer->render('admin/bookings-overview', $data);
    }

    /**
     * Prepare data for bookings overview
     */
    private function prepare_bookings_data() {
        // Get filter parameters
        $status_filter = $_GET['status'] ?? 'all';
        $course_filter = $_GET['course_id'] ?? '';
        $date_filter = $_GET['date_range'] ?? 'all';

        $bookings = $this->get_filtered_bookings($status_filter, $course_filter, $date_filter);
        $bookings_with_details = $this->enrich_bookings_data($bookings);

        // Get statistics
        $stats = $this->get_booking_statistics();
        $courses = $this->get_courses_for_filter();

        return [
            'bookings' => $bookings_with_details,
            'total_bookings' => count($bookings),
            'statistics' => $stats,
            'courses' => $courses,
            'current_filters' => [
                'status' => $status_filter,
                'course_id' => $course_filter,
                'date_range' => $date_filter,
            ],
            'status_options' => $this->get_booking_status_options(),
            'date_range_options' => $this->get_date_range_options(),
            'page_title' => __('Rezervace', 'mom-booking-system'),
            'breadcrumbs' => $this->get_breadcrumbs(),
            'show_empty_state' => empty($bookings),
        ];
    }

    /**
     * Get filtered bookings based on parameters
     */
    private function get_filtered_bookings($status_filter, $course_filter, $date_filter) {
        global $wpdb;

        $where_conditions = ['1=1'];
        $params = [];

        // Status filter
        if ($status_filter !== 'all') {
            $where_conditions[] = "b.booking_status = %s";
            $params[] = $status_filter;
        }

        // Course filter
        if (!empty($course_filter)) {
            $where_conditions[] = "l.course_id = %d";
            $params[] = intval($course_filter);
        }

        // Date range filter
        $date_condition = $this->get_date_range_condition($date_filter);
        if ($date_condition) {
            $where_conditions[] = $date_condition;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "
            SELECT b.*,
                   l.title as lesson_title,
                   l.date_time,
                   l.lesson_number,
                   c.title as course_title,
                   c.id as course_id
            FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            LEFT JOIN {$wpdb->prefix}mom_courses c ON l.course_id = c.id
            WHERE {$where_clause}
            ORDER BY l.date_time DESC, b.created_at DESC
        ";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            return $wpdb->get_results($query);
        }
    }

    /**
     * Get date range SQL condition
     */
    private function get_date_range_condition($date_filter) {
        switch ($date_filter) {
            case 'today':
                return "DATE(l.date_time) = CURDATE()";
            case 'tomorrow':
                return "DATE(l.date_time) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            case 'this_week':
                return "YEARWEEK(l.date_time, 1) = YEARWEEK(CURDATE(), 1)";
            case 'next_week':
                return "YEARWEEK(l.date_time, 1) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1)";
            case 'this_month':
                return "YEAR(l.date_time) = YEAR(CURDATE()) AND MONTH(l.date_time) = MONTH(CURDATE())";
            case 'future':
                return "l.date_time > NOW()";
            case 'past':
                return "l.date_time < NOW()";
            default:
                return null;
        }
    }

    /**
     * Enrich bookings data with additional information
     */
    private function enrich_bookings_data($bookings) {
        foreach ($bookings as $booking) {
            // Add time-related information
            $booking->is_future = strtotime($booking->date_time) > time();
            $booking->days_until = $this->calculate_days_until($booking->date_time);

            // Add customer information if available
            $booking->customer_details = $this->get_customer_details($booking);

            // Add lesson status
            $booking->lesson_status = $this->get_lesson_status($booking);
        }

        return $bookings;
    }

    /**
     * Get customer details for booking
     */
    private function get_customer_details($booking) {
        if (!empty($booking->customer_id)) {
            $user_manager = $this->container->get('user_manager');
            return $user_manager->get_user($booking->customer_id);
        }

        return null;
    }

    /**
     * Get lesson status information
     */
    private function get_lesson_status($booking) {
        global $wpdb;

        $lesson = $wpdb->get_row($wpdb->prepare("
            SELECT status, current_bookings, max_capacity
            FROM {$wpdb->prefix}mom_lessons
            WHERE id = %d
        ", $booking->lesson_id));

        return $lesson;
    }

    /**
     * Calculate days until lesson
     */
    private function calculate_days_until($date_time) {
        $lesson_timestamp = strtotime($date_time);
        $now_timestamp = time();

        $diff = $lesson_timestamp - $now_timestamp;

        if ($diff < 0) {
            return 0; // Past lesson
        }

        return ceil($diff / (24 * 60 * 60));
    }

    /**
     * Get booking statistics
     */
    private function get_booking_statistics() {
        global $wpdb;

        $stats = [
            'total' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE booking_status = 'confirmed'
            "),
            'today' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
                JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
                WHERE b.booking_status = 'confirmed'
                AND DATE(l.date_time) = CURDATE()
            "),
            'this_week' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
                JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
                WHERE b.booking_status = 'confirmed'
                AND YEARWEEK(l.date_time, 1) = YEARWEEK(CURDATE(), 1)
            "),
            'future' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
                JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
                WHERE b.booking_status = 'confirmed'
                AND l.date_time > NOW()
            "),
            'cancelled' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE booking_status = 'cancelled'
            "),
        ];

        return $stats;
    }

    /**
     * Get courses for filter dropdown
     */
    private function get_courses_for_filter() {
        $course_manager = $this->container->get('course_manager');
        return $course_manager->get_all_courses();
    }

    /**
     * Get booking status options
     */
    private function get_booking_status_options() {
        return [
            'all' => __('Všechny stavy', 'mom-booking-system'),
            'confirmed' => __('Potvrzené', 'mom-booking-system'),
            'cancelled' => __('Zrušené', 'mom-booking-system'),
            'pending' => __('Čekající', 'mom-booking-system'),
        ];
    }

    /**
     * Get date range options
     */
    private function get_date_range_options() {
        return [
            'all' => __('Všechna data', 'mom-booking-system'),
            'today' => __('Dnes', 'mom-booking-system'),
            'tomorrow' => __('Zítra', 'mom-booking-system'),
            'this_week' => __('Tento týden', 'mom-booking-system'),
            'next_week' => __('Příští týden', 'mom-booking-system'),
            'this_month' => __('Tento měsíc', 'mom-booking-system'),
            'future' => __('Budoucí', 'mom-booking-system'),
            'past' => __('Minulé', 'mom-booking-system'),
        ];
    }

    /**
     * Get breadcrumbs
     */
    private function get_breadcrumbs() {
        return [
            [
                'title' => __('Kurzy maminek', 'mom-booking-system'),
                'url' => admin_url('admin.php?page=mom-booking-admin')
            ],
            [
                'title' => __('Rezervace', 'mom-booking-system'),
                'url' => null
            ]
        ];
    }

    /**
     * Get booking status badge HTML
     */
    public function get_status_badge($status) {
        $status_config = [
            'confirmed' => [
                'class' => 'status-badge status-confirmed',
                'label' => __('Potvrzená', 'mom-booking-system')
            ],
            'cancelled' => [
                'class' => 'status-badge status-cancelled',
                'label' => __('Zrušená', 'mom-booking-system')
            ],
            'pending' => [
                'class' => 'status-badge status-pending',
                'label' => __('Čekající', 'mom-booking-system')
            ],
        ];

        $config = $status_config[$status] ?? [
            'class' => 'status-badge',
            'label' => ucfirst($status)
        ];

        return sprintf(
            '<span class="%s">%s</span>',
            esc_attr($config['class']),
            esc_html($config['label'])
        );
    }

    /**
     * Format booking date and time
     */
    public function format_booking_datetime($datetime) {
        return $this->template_renderer->format_datetime($datetime);
    }

    /**
     * Check if booking can be cancelled
     */
    public function can_cancel_booking($booking) {
        // Can't cancel already cancelled bookings
        if ($booking->booking_status === 'cancelled') {
            return false;
        }

        // Can't cancel past bookings
        if (!$booking->is_future) {
            return false;
        }

        // Can't cancel if lesson is too soon (e.g., within 2 hours)
        $hours_until = $booking->days_until * 24;
        if ($hours_until < 2) {
            return false;
        }

        return true;
    }

    /**
     * Get urgency class for booking based on time until lesson
     */
    public function get_urgency_class($booking) {
        if (!$booking->is_future) {
            return 'booking-past';
        }

        if ($booking->days_until <= 1) {
            return 'booking-urgent';
        } elseif ($booking->days_until <= 3) {
            return 'booking-soon';
        } else {
            return 'booking-future';
        }
    }

    /**
     * Get time until lesson formatted
     */
    public function get_time_until_lesson($booking) {
        if (!$booking->is_future) {
            return __('Proběhla', 'mom-booking-system');
        }

        if ($booking->days_until == 0) {
            return __('Dnes', 'mom-booking-system');
        } elseif ($booking->days_until == 1) {
            return __('Zítra', 'mom-booking-system');
        } else {
            return sprintf(
                __('Za %d dní', 'mom-booking-system'),
                $booking->days_until
            );
        }
    }

    /**
     * Export bookings data (for CSV export)
     */
    public function get_bookings_export_data($filters = []) {
        $bookings = $this->get_filtered_bookings(
            $filters['status'] ?? 'all',
            $filters['course_id'] ?? '',
            $filters['date_range'] ?? 'all'
        );

        $export_data = [];

        foreach ($bookings as $booking) {
            $export_data[] = [
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'course_title' => $booking->course_title,
                'lesson_title' => $booking->lesson_title,
                'lesson_number' => $booking->lesson_number,
                'lesson_date' => date('d.m.Y', strtotime($booking->date_time)),
                'lesson_time' => date('H:i', strtotime($booking->date_time)),
                'booking_status' => $booking->booking_status,
                'booking_date' => date('d.m.Y H:i', strtotime($booking->booking_date)),
                'notes' => $booking->notes,
            ];
        }

        return $export_data;
    }

    /**
     * Get booking summary for dashboard
     */
    public function get_booking_summary() {
        $stats = $this->get_booking_statistics();

        return [
            'total_bookings' => $stats['total'],
            'today_lessons' => $stats['today'],
            'upcoming_bookings' => $stats['future'],
            'cancellation_rate' => $stats['total'] > 0 ?
                round(($stats['cancelled'] / ($stats['total'] + $stats['cancelled'])) * 100, 1) : 0,
        ];
    }
}
