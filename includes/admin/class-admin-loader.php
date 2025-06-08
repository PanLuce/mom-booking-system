<?php
/**
 * Admin Loader
 * Single Responsibility: Load and initialize admin components
 */
class MomAdminLoader {

    private $container;
    private $admin_components = [];

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
    }

    /**
     * Initialize admin components
     */
    public function init() {
        // Only load admin components in admin area
        if (!is_admin()) {
            return;
        }

        $this->load_admin_files();
        $this->register_admin_bindings();
        $this->init_admin_components();
        $this->init_admin_hooks();
    }

    /**
     * Load admin-specific files
     */
    private function load_admin_files() {
        $admin_files = [
            // Menu and page management
            'includes/admin/class-admin-menu-manager.php',
            'includes/admin/class-admin-notice-manager.php',

            // Page classes
            'includes/admin/pages/class-courses-page.php',
            'includes/admin/pages/class-users-page.php',
            'includes/admin/pages/class-bookings-page.php',
            'includes/admin/pages/class-lessons-page.php',

            // Form handling
            'includes/admin/handlers/class-form-handler.php',
            'includes/admin/handlers/class-ajax-handler.php',

            // Form processors
            'includes/admin/form-processors/class-course-form-processor.php',
            'includes/admin/form-processors/class-user-form-processor.php',
            'includes/admin/form-processors/class-booking-form-processor.php',
            'includes/admin/form-processors/class-lesson-form-processor.php',
        ];

        foreach ($admin_files as $file) {
            $file_path = MOM_BOOKING_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Register admin-specific service bindings
     */
    private function register_admin_bindings() {
        // Admin page classes
        $this->container->bind('courses_page', 'MomCoursesPage');
        $this->container->bind('users_page', 'MomUsersPage');
        $this->container->bind('bookings_page', 'MomBookingsPage');
        $this->container->bind('lessons_page', 'MomLessonsPage');

        // Admin handlers
        $this->container->bind('admin_menu_manager', 'MomAdminMenuManager');
        $this->container->bind('admin_notice_manager', 'MomAdminNoticeManager');
        $this->container->bind('form_handler', 'MomAdminFormHandler');
        $this->container->bind('ajax_handler', 'MomAdminAjaxHandler');

        // Form processors
        $this->container->bind('course_form_processor', 'MomCourseFormProcessor');
        $this->container->bind('user_form_processor', 'MomUserFormProcessor');
        $this->container->bind('booking_form_processor', 'MomBookingFormProcessor');
        $this->container->bind('lesson_form_processor', 'MomLessonFormProcessor');
    }

    /**
     * Initialize admin components
     */
    private function init_admin_components() {
        // Core admin components that should be initialized immediately
        $core_components = [
            'admin_menu_manager',
            'admin_notice_manager',
            'form_handler',
            'ajax_handler',
        ];

        foreach ($core_components as $component) {
            try {
                $this->admin_components[$component] = $this->container->get($component);
            } catch (Exception $e) {
                error_log("Failed to initialize admin component '{$component}': " . $e->getMessage());
            }
        }
    }

    /**
     * Initialize admin-specific hooks
     */
    private function init_admin_hooks() {
        // Admin initialization hook
        add_action('admin_init', [$this, 'admin_init']);

        // Admin menu hook (handled by menu manager)
        // Form handling hook (handled by form handler)
        // AJAX hooks (handled by AJAX handler)

        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);

        // Admin bar items
        add_action('admin_bar_menu', [$this, 'add_admin_bar_items'], 100);

        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(MOM_BOOKING_PLUGIN_DIR . 'mom-booking-system.php'), [$this, 'add_plugin_action_links']);
    }

    /**
     * Admin initialization callback
     */
    public function admin_init() {
        // Check user capabilities for plugin pages
        if (isset($_GET['page']) && strpos($_GET['page'], 'mom-') === 0) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'mom-booking-system'));
            }
        }

        // Initialize page-specific components based on current page
        $this->init_page_specific_components();
    }

    /**
     * Initialize components based on current admin page
     */
    private function init_page_specific_components() {
        $current_page = $_GET['page'] ?? '';

        $page_components = [
            'mom-booking-admin' => ['courses_page'],
            'mom-course-new' => ['courses_page'],
            'mom-users' => ['users_page'],
            'mom-user-detail' => ['users_page'],
            'mom-bookings' => ['bookings_page'],
            'mom-lesson-detail' => ['lessons_page'],
        ];

        if (isset($page_components[$current_page])) {
            foreach ($page_components[$current_page] as $component) {
                if (!isset($this->admin_components[$component])) {
                    try {
                        $this->admin_components[$component] = $this->container->get($component);
                    } catch (Exception $e) {
                        error_log("Failed to initialize page component '{$component}': " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'mom_booking_dashboard_widget',
            __('Kurzy maminek - Přehled', 'mom-booking-system'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        try {
            $booking_manager = $this->container->get('booking_manager');
            $course_manager = $this->container->get('course_manager');

            $stats = [
                'total_bookings' => $booking_manager->get_booking_statistics()['total_bookings'],
                'active_courses' => count($course_manager->get_all_courses('active')),
                'today_bookings' => $booking_manager->get_booking_statistics()['today_bookings'],
            ];

            $template_renderer = $this->container->get('template_renderer');
            $template_renderer->render('admin/dashboard-widget', ['stats' => $stats]);

        } catch (Exception $e) {
            echo '<p>' . __('Chyba při načítání statistik.', 'mom-booking-system') . '</p>';
        }
    }

    /**
     * Add admin bar items
     */
    public function add_admin_bar_items($admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_bar->add_menu([
            'id' => 'mom-booking-admin-bar',
            'title' => __('Kurzy maminek', 'mom-booking-system'),
            'href' => admin_url('admin.php?page=mom-booking-admin'),
            'meta' => [
                'title' => __('Přejít na administraci kurzů', 'mom-booking-system'),
            ],
        ]);

        // Add submenu items
        $admin_bar->add_menu([
            'id' => 'mom-booking-new-course',
            'parent' => 'mom-booking-admin-bar',
            'title' => __('Nový kurz', 'mom-booking-system'),
            'href' => admin_url('admin.php?page=mom-course-new'),
        ]);

        $admin_bar->add_menu([
            'id' => 'mom-booking-users',
            'parent' => 'mom-booking-admin-bar',
            'title' => __('Uživatelé', 'mom-booking-system'),
            'href' => admin_url('admin.php?page=mom-users'),
        ]);

        $admin_bar->add_menu([
            'id' => 'mom-booking-bookings',
            'parent' => 'mom-booking-admin-bar',
            'title' => __('Rezervace', 'mom-booking-system'),
            'href' => admin_url('admin.php?page=mom-bookings'),
        ]);
    }

    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=mom-booking-admin') . '">' . __('Nastavení', 'mom-booking-system') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * Get initialized admin component
     */
    public function get_component($component_name) {
        if (isset($this->admin_components[$component_name])) {
            return $this->admin_components[$component_name];
        }

        // Try to initialize component if not already done
        try {
            $this->admin_components[$component_name] = $this->container->get($component_name);
            return $this->admin_components[$component_name];
        } catch (Exception $e) {
            error_log("Failed to get admin component '{$component_name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if component is loaded
     */
    public function has_component($component_name) {
        return isset($this->admin_components[$component_name]);
    }

    /**
     * Get all loaded components
     */
    public function get_loaded_components() {
        return array_keys($this->admin_components);
    }

    /**
     * Cleanup admin components (for testing)
     */
    public function cleanup() {
        $this->admin_components = [];
    }
}
