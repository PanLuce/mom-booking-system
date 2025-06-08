<?php
/**
 * Plugin Name: Mom Booking System
 * Description: Rezervační systém pro lekce maminek s dětmi
 * Version: 2.5
 * Author: Lukáš Vitala
 * Text Domain: mom-booking-system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MOM_BOOKING_VERSION', '2.5');
define('MOM_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOM_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Simple autoloader for plugin classes
 */
spl_autoload_register(function($class_name) {
    // Only autoload our plugin classes
    if (strpos($class_name, 'MomBooking') !== 0) {
        return;
    }

    $class_map = [
        // Core classes
        'MomBookingContainer' => 'includes/class-container.php',
        'MomTemplateRenderer' => 'includes/class-template-renderer.php',
        'MomRedirectHandler' => 'includes/class-redirect-handler.php',
        'MomBookingDatabase' => 'includes/core/class-database.php',

        // Business logic
        'MomCourseManager' => 'includes/core/class-course-manager.php',
        'MomUserManager' => 'includes/core/class-user-manager.php',
        'MomBookingManager' => 'includes/core/class-booking-manager.php',
        'MomLessonManager' => 'includes/core/class-lesson-manager.php',
        'MomCourseRegistrationManager' => 'includes/core/class-course-registration-manager.php',

        // Admin classes
        'MomAdminLoader' => 'includes/admin/class-admin-loader.php',
        'MomAdminMenuManager' => 'includes/admin/class-admin-menu-manager.php',
        'MomAdminNoticeManager' => 'includes/admin/class-admin-notice-manager.php',
        'MomCoursesPage' => 'includes/admin/pages/class-courses-page.php',
        'MomUsersPage' => 'includes/admin/pages/class-users-page.php',
        'MomBookingsPage' => 'includes/admin/pages/class-bookings-page.php',
        'MomLessonsPage' => 'includes/admin/pages/class-lessons-page.php',
        'MomAdminFormHandler' => 'includes/admin/handlers/class-form-handler.php',
        'MomAdminAjaxHandler' => 'includes/admin/handlers/class-ajax-handler.php',
        'MomCourseFormProcessor' => 'includes/admin/form-processors/class-course-form-processor.php',
        'MomUserFormProcessor' => 'includes/admin/form-processors/class-user-form-processor.php',
        'MomBookingFormProcessor' => 'includes/admin/form-processors/class-booking-form-processor.php',
        'MomLessonFormProcessor' => 'includes/admin/form-processors/class-lesson-form-processor.php',

        // Frontend classes
        'MomFrontendLoader' => 'includes/frontend/class-frontend-loader.php',
        'MomBookingShortcodes' => 'includes/frontend/class-shortcodes.php',
        'MomBookingFrontendAjax' => 'includes/frontend/class-frontend-ajax.php',
    ];

    if (isset($class_map[$class_name])) {
        $file_path = MOM_BOOKING_PLUGIN_DIR . $class_map[$class_name];
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

/**
 * Main plugin class - ONLY coordination and initialization
 */
class MomBookingSystem {

    private static $instance = null;
    private $container;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load core files first
        $this->load_core_files();

        // Setup container
        $this->setup_container();

        // Initialize hooks
        $this->init_hooks();

        // Load components based on context
        $this->load_components();
    }

    /**
     * Load essential core files manually (before autoloader)
     */
    private function load_core_files() {
        $core_files = [
            'includes/class-container.php',
            'includes/class-template-renderer.php',
            'includes/class-redirect-handler.php',
        ];

        foreach ($core_files as $file) {
            $file_path = MOM_BOOKING_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Setup dependency injection container
     */
    private function setup_container() {
        $this->container = new MomBookingContainer();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Asset loading hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Initialization hook
        add_action('init', [$this, 'init_plugin']);
    }

    /**
     * Load components based on context
     */
    private function load_components() {
        add_action('plugins_loaded', [$this, 'load_context_components']);
    }

    /**
     * Load components based on admin/frontend context
     */
    public function load_context_components() {
        try {
            if (is_admin()) {
                $this->container->get('admin_loader')->init();
            } else {
                // Only load frontend if class exists
                if (class_exists('MomFrontendLoader')) {
                    $this->container->get('frontend_loader')->init();
                }
            }
        } catch (Exception $e) {
            // Log error but don't break the site
            error_log('MOM Booking System Error: ' . $e->getMessage());

            // Show admin notice if in admin
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>MOM Booking System Error:</strong> ' . esc_html($e->getMessage());
                    echo '</p></div>';
                });
            }
        }
    }

    /**
     * Initialize plugin (called on WordPress 'init' hook)
     */
    public function init_plugin() {
        load_plugin_textdomain(
            'mom-booking-system',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        try {
            $this->container->get('database')->create_tables();
            flush_rewrite_rules();
            update_option('mom_booking_activated_at', current_time('mysql'));
        } catch (Exception $e) {
            wp_die('Plugin activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'mom_booking_calendar') ||
            has_shortcode($post->post_content, 'mom_course_list')
        )) {
            wp_enqueue_script(
                'mom-booking-frontend',
                MOM_BOOKING_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                MOM_BOOKING_VERSION,
                true
            );

            wp_enqueue_style(
                'mom-booking-frontend',
                MOM_BOOKING_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                MOM_BOOKING_VERSION
            );

            wp_localize_script('mom-booking-frontend', 'momBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_booking_nonce'),
                'strings' => [
                    'loading' => __('Načítání...', 'mom-booking-system'),
                    'error' => __('Došlo k chybě. Zkuste to prosím znovu.', 'mom-booking-system'),
                    'success' => __('Rezervace byla úspěšně vytvořena!', 'mom-booking-system'),
                ]
            ]);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mom-') !== false) {
            wp_enqueue_script(
                'mom-booking-admin',
                MOM_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                MOM_BOOKING_VERSION,
                true
            );

            wp_enqueue_style(
                'mom-booking-admin',
                MOM_BOOKING_PLUGIN_URL . 'assets/css/admin.css',
                [],
                MOM_BOOKING_VERSION
            );

            wp_localize_script('mom-booking-admin', 'momBookingAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_admin_nonce'),
                'strings' => [
                    'confirmDelete' => __('Opravdu chcete smazat tento záznam?', 'mom-booking-system'),
                    'loading' => __('Načítání...', 'mom-booking-system'),
                ]
            ]);
        }
    }

    /**
     * Get container instance
     */
    public function get_container() {
        return $this->container;
    }
}

/**
 * Initialize the plugin
 */
MomBookingSystem::get_instance();

/**
 * Helper functions
 */
function mom_booking() {
    return MomBookingSystem::get_instance();
}

function mom_booking_container() {
    return mom_booking()->get_container();
}
