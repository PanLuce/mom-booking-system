<?php
/**
 * Lessons Page
 * Single Responsibility: Handle lessons page rendering and data preparation
 */
class MomLessonsPage {

    private $container;
    private $lesson_manager;
    private $template_renderer;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->lesson_manager = $container->get('lesson_manager');
        $this->template_renderer = $container->get('template_renderer');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Render lesson detail page
     */
    public function render_detail() {
        if (!isset($_GET['id'])) {
            wp_die(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        $lesson_id = intval($_GET['id']);
        $data = $this->prepare_lesson_detail_data($lesson_id);

        if (!$data['lesson_schedule']) {
            wp_die(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        $this->template_renderer->render('admin/lesson-detail', $data);
    }

    /**
     * Prepare data for lesson detail page
     */
    private function prepare_lesson_detail_data($lesson_id) {
        $lesson_schedule = $this->lesson_manager->get_lesson_schedule($lesson_id);

        if (!$lesson_schedule) {
            return ['lesson_schedule' => null];
        }

        $lesson = $lesson_schedule['lesson'];
        $participants = $lesson_schedule['participants'];
        $available_spots = $lesson_schedule['available_spots'];

        // Get available users for adding to lesson
        $user_manager = $this->container->get('user_manager');
        $available_users = $user_manager->get_available_users_for_lesson($lesson_id);

        // Get lesson statistics
        $lesson_stats = $this->get_lesson_statistics($lesson_id);

        // Get course information
        $course_manager = $this->container->get('course_manager');
        $course = $course_manager->get_course($lesson->course_id);

        return [
            'lesson_schedule' => $lesson_schedule,
            'lesson' => $lesson,
            'course' => $course,
            'participants' => $participants,
            'available_spots' => $available_spots,
            'available_users' => $available_users,
            'lesson_stats' => $lesson_stats,
            'can_edit_lesson' => $this->can_edit_lesson($lesson),
            'can_add_participants' => $this->can_add_participants($lesson),
            'lesson_status_options' => $this->get_lesson_status_options(),
            'page_title' => sprintf(__('Detail lekce: %s', 'mom-booking-system'), $lesson->title),
            'breadcrumbs' => $this->get_breadcrumbs($lesson),
        ];
    }

    /**
     * Get lesson statistics
     */
    private function get_lesson_statistics($lesson_id) {
        global $wpdb;

        return [
            'total_bookings' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE lesson_id = %d
            ", $lesson_id)),

            'confirmed_bookings' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE lesson_id = %d AND booking_status = 'confirmed'
            ", $lesson_id)),

            'cancelled_bookings' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE lesson_id = %d AND booking_status = 'cancelled'
            ", $lesson_id)),

            'waitlist_count' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings
                WHERE lesson_id = %d AND booking_status = 'waitlist'
            ", $lesson_id)),
        ];
    }

    /**
     * Check if lesson can be edited
     */
    private function can_edit_lesson($lesson) {
        // Can't edit lessons that already happened
        if (strtotime($lesson->date_time) < time()) {
            return false;
        }

        // Can't edit if lesson is starting very soon (within 2 hours)
        $hours_until = (strtotime($lesson->date_time) - time()) / 3600;
        if ($hours_until < 2) {
            return false;
        }

        return true;
    }

    /**
     * Check if participants can be added to lesson
     */
    private function can_add_participants($lesson) {
        // Can't add to cancelled lessons
        if ($lesson->status === 'cancelled') {
            return false;
        }

        // Can't add to past lessons
        if (strtotime($lesson->date_time) < time()) {
            return false;
        }

        // Can't add if lesson is full
        if ($lesson->current_bookings >= $lesson->max_capacity) {
            return false;
        }

        return true;
    }

    /**
     * Get lesson status options
     */
    private function get_lesson_status_options() {
        return [
            'active' => __('Aktivní', 'mom-booking-system'),
            'cancelled' => __('Zrušená', 'mom-booking-system'),
            'completed' => __('Dokončená', 'mom-booking-system'),
        ];
    }

    /**
     * Get breadcrumbs for lesson detail
     */
    private function get_breadcrumbs($lesson) {
        return [
            [
                'title' => __('Kurzy maminek', 'mom-booking-system'),
                'url' => admin_url('admin.php?page=mom-booking-admin')
            ],
            [
                'title' => __('Detail lekce', 'mom-booking-system'),
                'url' => null
            ]
        ];
    }

    /**
     * Get lesson status badge HTML
     */
    public function get_lesson_status_badge($status) {
        $status_config = [
            'active' => [
                'class' => 'status-badge status-active',
                'label' => __('Aktivní', 'mom-booking-system')
            ],
            'cancelled' => [
                'class' => 'status-badge status-cancelled',
                'label' => __('Zrušená', 'mom-booking-system')
            ],
            'completed' => [
                'class' => 'status-badge status-completed',
                'label' => __('Dokončená', 'mom-booking-system')
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
     * Get capacity status information
     */
    public function get_capacity_status($lesson) {
        $occupancy_rate = ($lesson->current_bookings / $lesson->max_capacity) * 100;

        if ($occupancy_rate >= 100) {
            return [
                'status' => 'full',
                'class' => 'capacity-full',
                'label' => __('Plně obsazena', 'mom-booking-system')
            ];
        } elseif ($occupancy_rate >= 80) {
            return [
                'status' => 'almost_full',
                'class' => 'capacity-almost-full',
                'label' => __('Téměř plná', 'mom-booking-system')
            ];
        } elseif ($occupancy_rate >= 50) {
            return [
                'status' => 'half_full',
                'class' => 'capacity-half-full',
                'label' => __('Napůl obsazena', 'mom-booking-system')
            ];
        } else {
            return [
                'status' => 'available',
                'class' => 'capacity-available',
                'label' => __('Dostupná místa', 'mom-booking-system')
            ];
        }
    }

    /**
     * Get capacity badge HTML
     */
    public function get_capacity_badge($lesson) {
        $capacity_status = $this->get_capacity_status($lesson);

        return sprintf(
            '<span class="capacity-badge %s">%d/%d</span>',
            esc_attr($capacity_status['class']),
            $lesson->current_bookings,
            $lesson->max_capacity
        );
    }

    /**
     * Format lesson date and time
     */
    public function format_lesson_datetime($lesson) {
        return $this->template_renderer->format_datetime($lesson->date_time);
    }

    /**
     * Get lesson duration formatted
     */
    public function format_lesson_duration($lesson) {
        $duration = $lesson->lesson_duration ?? 60;

        if ($duration < 60) {
            return sprintf(__('%d minut', 'mom-booking-system'), $duration);
        }

        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        if ($minutes === 0) {
            return sprintf(__('%d hodin', 'mom-booking-system'), $hours);
        }

        return sprintf(__('%d h %d min', 'mom-booking-system'), $hours, $minutes);
    }

    /**
     * Get time until lesson
     */
    public function get_time_until_lesson($lesson) {
        $lesson_time = strtotime($lesson->date_time);
        $now = time();

        if ($lesson_time < $now) {
            return __('Proběhla', 'mom-booking-system');
        }

        $diff = $lesson_time - $now;
        $days = floor($diff / (24 * 60 * 60));
        $hours = floor(($diff % (24 * 60 * 60)) / (60 * 60));

        if ($days > 0) {
            return sprintf(__('Za %d dní', 'mom-booking-system'), $days);
        } elseif ($hours > 0) {
            return sprintf(__('Za %d hodin', 'mom-booking-system'), $hours);
        } else {
            return __('Brzy', 'mom-booking-system');
        }
    }

    /**
     * Check if lesson is in the past
     */
    public function is_lesson_past($lesson) {
        return strtotime($lesson->date_time) < time();
    }

    /**
     * Check if lesson is today
     */
    public function is_lesson_today($lesson) {
        return date('Y-m-d', strtotime($lesson->date_time)) === date('Y-m-d');
    }

    /**
     * Get participant summary
     */
    public function get_participant_summary($participants) {
        $adults = count($participants);
        $children = count(array_filter($participants, function($p) {
            return !empty($p->child_name);
        }));

        $summary_parts = [];

        if ($adults > 0) {
            $summary_parts[] = sprintf(
                __('%d dospělých', 'mom-booking-system'),
                $adults
            );
        }

        if ($children > 0) {
            $summary_parts[] = sprintf(
                __('%d dětí', 'mom-booking-system'),
                $children
            );
        }

        return implode(', ', $summary_parts);
    }

    /**
     * Get participant age groups
     */
    public function get_participant_age_groups($participants) {
        $age_groups = [
            'infant' => 0,
            'toddler' => 0,
            'small_child' => 0,
            'preschooler' => 0,
            'older_child' => 0,
            'no_info' => 0,
        ];

        $user_manager = $this->container->get('user_manager');

        foreach ($participants as $participant) {
            if (empty($participant->child_birth_date)) {
                $age_groups['no_info']++;
                continue;
            }

            $age_months = $user_manager->calculate_age_in_months($participant->child_birth_date);

            if ($age_months < 6) {
                $age_groups['infant']++;
            } elseif ($age_months < 12) {
                $age_groups['toddler']++;
            } elseif ($age_months < 24) {
                $age_groups['small_child']++;
            } elseif ($age_months < 36) {
                $age_groups['preschooler']++;
            } else {
                $age_groups['older_child']++;
            }
        }

        return $age_groups;
    }

    /**
     * Export lesson participants (for attendance sheet)
     */
    public function get_participants_export_data($lesson_id) {
        $lesson_schedule = $this->lesson_manager->get_lesson_schedule($lesson_id);

        if (!$lesson_schedule) {
            return [];
        }

        $participants = $lesson_schedule['participants'];
        $export_data = [];

        foreach ($participants as $participant) {
            $user_manager = $this->container->get('user_manager');
            $child_age = $user_manager->calculate_child_age($participant->child_birth_date);

            $export_data[] = [
                'participant_name' => $participant->customer_name,
                'child_name' => $participant->child_name,
                'child_age' => $child_age,
                'phone' => $participant->customer_phone,
                'email' => $participant->customer_email,
                'emergency_contact' => $participant->emergency_contact ?? '',
                'notes' => $participant->notes ?? '',
                'attendance' => '', // Empty field for manual marking
            ];
        }

        return $export_data;
    }

    /**
     * Get lesson warnings (capacity, timing, etc.)
     */
    public function get_lesson_warnings($lesson) {
        $warnings = [];

        // Check if lesson is overfull
        if ($lesson->current_bookings > $lesson->max_capacity) {
            $warnings[] = [
                'type' => 'error',
                'message' => __('Lekce je přeplněná!', 'mom-booking-system')
            ];
        }

        // Check if lesson is starting soon
        $hours_until = (strtotime($lesson->date_time) - time()) / 3600;
        if ($hours_until > 0 && $hours_until < 2) {
            $warnings[] = [
                'type' => 'warning',
                'message' => __('Lekce začíná za méně než 2 hodiny!', 'mom-booking-system')
            ];
        }

        // Check if lesson has no participants
        if ($lesson->current_bookings === 0 && $lesson->status === 'active') {
            $warnings[] = [
                'type' => 'info',
                'message' => __('Lekce nemá žádné účastníky.', 'mom-booking-system')
            ];
        }

        return $warnings;
    }
}
