<?php
/**
 * Users Page
 * Single Responsibility: Handle users page rendering and data preparation
 */
class MomUsersPage {

    private $container;
    private $user_manager;
    private $template_renderer;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->user_manager = $container->get('user_manager');
        $this->template_renderer = $container->get('template_renderer');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Render users overview page
     */
    public function render() {
        $data = $this->prepare_users_data();
        $this->template_renderer->render('admin/users-overview', $data);
    }

    /**
     * Render user detail page
     */
    public function render_detail() {
        if (!isset($_GET['id'])) {
            wp_die(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        $user_id = intval($_GET['id']);
        $data = $this->prepare_user_detail_data($user_id);

        if (!$data['user']) {
            wp_die(__('Uživatel nebyl nalezen.', 'mom-booking-system'));
        }

        $this->template_renderer->render('admin/user-detail', $data);
    }

    /**
     * Prepare data for users overview
     */
    private function prepare_users_data() {
        $users = $this->user_manager->get_all_users();
        $users_with_stats = [];

        foreach ($users as $user) {
            $user_stats = $this->user_manager->get_user_statistics($user->id);
            $user->statistics = $user_stats;
            $user->child_age = $this->user_manager->calculate_child_age($user->child_birth_date);
            $users_with_stats[] = $user;
        }

        return [
            'users' => $users_with_stats,
            'total_users' => count($users),
            'users_with_children' => count(array_filter($users, function($user) {
                return !empty($user->child_name);
            })),
            'page_title' => __('Uživatelé', 'mom-booking-system'),
            'breadcrumbs' => $this->get_breadcrumbs('overview'),
            'show_empty_state' => empty($users),
            'form_fields' => $this->get_user_form_fields(),
        ];
    }

    /**
     * Prepare data for user detail page
     */
    private function prepare_user_detail_data($user_id) {
        $user = $this->user_manager->get_user($user_id);

        if (!$user) {
            return ['user' => null];
        }

        $user_bookings = $this->user_manager->get_user_bookings($user_id);
        $user_stats = $this->user_manager->get_user_statistics($user_id);

        // Separate future and past bookings
        $future_bookings = [];
        $past_bookings = [];

        foreach ($user_bookings as $booking) {
            if (strtotime($booking->date_time) > time()) {
                $future_bookings[] = $booking;
            } else {
                $past_bookings[] = $booking;
            }
        }

        // Get courses user is registered for
        $registered_courses = $this->get_user_registered_courses($user_id);

        return [
            'user' => $user,
            'user_bookings' => $user_bookings,
            'future_bookings' => $future_bookings,
            'past_bookings' => $past_bookings,
            'user_stats' => $user_stats,
            'registered_courses' => $registered_courses,
            'child_age' => $this->user_manager->calculate_child_age($user->child_birth_date),
            'page_title' => sprintf(__('Detail uživatele: %s', 'mom-booking-system'), $user->name),
            'breadcrumbs' => $this->get_breadcrumbs('detail'),
            'form_fields' => $this->get_user_form_fields(),
            'can_delete' => $this->can_delete_user($user_id),
        ];
    }

    /**
     * Get user form fields configuration
     */
    private function get_user_form_fields() {
        return [
            'name' => [
                'label' => __('Jméno a příjmení', 'mom-booking-system'),
                'type' => 'text',
                'required' => true,
                'placeholder' => __('Jana Nováková', 'mom-booking-system'),
            ],
            'email' => [
                'label' => __('Email', 'mom-booking-system'),
                'type' => 'email',
                'required' => true,
                'placeholder' => __('jana@example.com', 'mom-booking-system'),
            ],
            'phone' => [
                'label' => __('Telefon', 'mom-booking-system'),
                'type' => 'tel',
                'required' => false,
                'placeholder' => __('606 123 456', 'mom-booking-system'),
            ],
            'child_name' => [
                'label' => __('Jméno dítěte', 'mom-booking-system'),
                'type' => 'text',
                'required' => false,
                'placeholder' => __('Anička', 'mom-booking-system'),
            ],
            'child_birth_date' => [
                'label' => __('Datum narození dítěte', 'mom-booking-system'),
                'type' => 'date',
                'required' => false,
            ],
            'emergency_contact' => [
                'label' => __('Nouzový kontakt', 'mom-booking-system'),
                'type' => 'text',
                'required' => false,
                'placeholder' => __('Petr Novák - 777 888 999', 'mom-booking-system'),
            ],
            'notes' => [
                'label' => __('Poznámky', 'mom-booking-system'),
                'type' => 'textarea',
                'required' => false,
                'placeholder' => __('Další informace o uživateli...', 'mom-booking-system'),
            ],
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
                return [
                    $base_breadcrumb,
                    ['title' => __('Uživatelé', 'mom-booking-system'), 'url' => null]
                ];

            case 'detail':
                return [
                    $base_breadcrumb,
                    ['title' => __('Uživatelé', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-users')],
                    ['title' => __('Detail uživatele', 'mom-booking-system'), 'url' => null]
                ];

            default:
                return [$base_breadcrumb];
        }
    }

    /**
     * Get courses user is registered for
     */
    private function get_user_registered_courses($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT c.*, COUNT(b.id) as lessons_booked
            FROM {$wpdb->prefix}mom_courses c
            JOIN {$wpdb->prefix}mom_lessons l ON c.id = l.course_id
            JOIN {$wpdb->prefix}mom_bookings b ON l.id = b.lesson_id
            WHERE (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$wpdb->prefix}mom_customers WHERE id = %d
            ))
            AND b.booking_status = 'confirmed'
            GROUP BY c.id
            ORDER BY c.start_date DESC
        ", $user_id, $user_id));
    }

    /**
     * Check if user can be deleted
     */
    private function can_delete_user($user_id) {
        global $wpdb;

        // Check if user has future bookings
        $future_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$wpdb->prefix}mom_customers WHERE id = %d
            ))
            AND b.booking_status = 'confirmed'
            AND l.date_time > NOW()
        ", $user_id, $user_id));

        return $future_bookings == 0;
    }

    /**
     * Format user registration date
     */
    public function format_registration_date($date) {
        return $this->template_renderer->format_date($date);
    }

    /**
     * Get user activity status
     */
    public function get_user_activity_status($user_stats) {
        if ($user_stats['future_bookings'] > 0) {
            return [
                'status' => 'active',
                'label' => __('Aktivní', 'mom-booking-system'),
                'class' => 'status-active'
            ];
        } elseif ($user_stats['total_bookings'] > 0) {
            return [
                'status' => 'inactive',
                'label' => __('Neaktivní', 'mom-booking-system'),
                'class' => 'status-inactive'
            ];
        } else {
            return [
                'status' => 'new',
                'label' => __('Nový', 'mom-booking-system'),
                'class' => 'status-new'
            ];
        }
    }

    /**
     * Get user activity badge HTML
     */
    public function get_activity_badge($user_stats) {
        $activity = $this->get_user_activity_status($user_stats);

        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($activity['class']),
            esc_html($activity['label'])
        );
    }

    /**
     * Calculate child age category
     */
    public function get_child_age_category($birth_date) {
        if (!$birth_date) {
            return null;
        }

        $age_months = $this->user_manager->calculate_age_in_months($birth_date);

        if ($age_months < 6) {
            return __('Kojenec (0-6 měsíců)', 'mom-booking-system');
        } elseif ($age_months < 12) {
            return __('Batole (6-12 měsíců)', 'mom-booking-system');
        } elseif ($age_months < 24) {
            return __('Malé dítě (1-2 roky)', 'mom-booking-system');
        } elseif ($age_months < 36) {
            return __('Předškolák (2-3 roky)', 'mom-booking-system');
        } else {
            return __('Starší dítě (3+ let)', 'mom-booking-system');
        }
    }

    /**
     * Get user contact information formatted
     */
    public function format_contact_info($user) {
        $contact_parts = [];

        if (!empty($user->email)) {
            $contact_parts[] = sprintf('<a href="mailto:%s">%s</a>',
                esc_attr($user->email),
                esc_html($user->email)
            );
        }

        if (!empty($user->phone)) {
            $contact_parts[] = sprintf('<a href="tel:%s">%s</a>',
                esc_attr($user->phone),
                esc_html($user->phone)
            );
        }

        return implode(' • ', $contact_parts);
    }

    /**
     * Get booking history summary
     */
    public function get_booking_summary($user_stats) {
        $summary_parts = [];

        if ($user_stats['total_bookings'] > 0) {
            $summary_parts[] = sprintf(
                __('Celkem %d rezervací', 'mom-booking-system'),
                $user_stats['total_bookings']
            );
        }

        if ($user_stats['future_bookings'] > 0) {
            $summary_parts[] = sprintf(
                __('%d nadcházejících', 'mom-booking-system'),
                $user_stats['future_bookings']
            );
        }

        if ($user_stats['cancelled_bookings'] > 0) {
            $summary_parts[] = sprintf(
                __('%d zrušených', 'mom-booking-system'),
                $user_stats['cancelled_bookings']
            );
        }

        return implode(' • ', $summary_parts);
    }

    /**
     * Check if user has emergency contact
     */
    public function has_emergency_contact($user) {
        return !empty($user->emergency_contact);
    }

    /**
     * Get users by age group for statistics
     */
    public function get_users_by_age_group() {
        $users = $this->user_manager->get_all_users();
        $age_groups = [
            'infant' => 0,     // 0-6 months
            'toddler' => 0,    // 6-12 months
            'small_child' => 0, // 1-2 years
            'preschooler' => 0, // 2-3 years
            'older_child' => 0, // 3+ years
            'no_child' => 0,    // No child info
        ];

        foreach ($users as $user) {
            if (!$user->child_birth_date) {
                $age_groups['no_child']++;
                continue;
            }

            $age_months = $this->user_manager->calculate_age_in_months($user->child_birth_date);

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
     * Export users data (for CSV export)
     */
    public function get_users_export_data() {
        $users = $this->user_manager->get_all_users();
        $export_data = [];

        foreach ($users as $user) {
            $user_stats = $this->user_manager->get_user_statistics($user->id);

            $export_data[] = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'child_name' => $user->child_name,
                'child_birth_date' => $user->child_birth_date,
                'child_age' => $this->user_manager->calculate_child_age($user->child_birth_date),
                'emergency_contact' => $user->emergency_contact,
                'total_bookings' => $user_stats['total_bookings'],
                'future_bookings' => $user_stats['future_bookings'],
                'created_at' => $user->created_at,
                'notes' => $user->notes,
            ];
        }

        return $export_data;
    }
}
