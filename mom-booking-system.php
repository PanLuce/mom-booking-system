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
 * Main plugin class - ONLY coordination and initialization
 * Single Responsibility: Plugin bootstrapping and lifecycle management
 */
class MomBookingSystem {

    private static $instance = null;
    private $container;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - initialize plugin
     */
    private function __construct() {
        $this->init_autoloader();
        $this->setup_container();
        $this->init_hooks();
        $this->load_components();
    }

    /**
     * Setup autoloader for plugin classes
     */
    private function init_autoloader() {
        spl_autoload_register([$this, 'autoload_classes']);
    }

    /**
     * Autoload plugin classes
     */
    private function autoload_classes($class_name) {
        // Only load our plugin classes
        if (strpos($class_name, 'MomBooking') !== 0) {
            return;
        }

        $class_map = [
            'MomBookingContainer' => 'includes/class-container.php',
            'MomBookingDatabase' => 'includes/core/class-database.php',
            'MomCourseManager' => 'includes/core/class-course-manager.php',
            'MomUserManager' => 'includes/core/class-user-manager.php',
            'MomBookingManager' => 'includes/core/class-booking-manager.php',
            'MomLessonManager' => 'includes/core/class-lesson-manager.php',
            'MomTemplateRenderer' => 'includes/class-template-renderer.php',
            'MomRedirectHandler' => 'includes/class-redirect-handler.php',
            'MomAdminLoader' => 'includes/admin/class-admin-loader.php',
            'MomFrontendLoader' => 'includes/frontend/class-frontend-loader.php',
        ];

        if (isset($class_map[$class_name])) {
            $file_path = MOM_BOOKING_PLUGIN_DIR . $class_map[$class_name];
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
     * Load appropriate components based on context
     */
    private function load_components() {
        // Load components after WordPress is fully initialized
        add_action('plugins_loaded', [$this, 'load_context_components']);
    }

    /**
     * Load components based on admin/frontend context
     */
    public function load_context_components() {
        if (is_admin()) {
            $this->container->get('admin_loader')->init();
        } else {
            $this->container->get('frontend_loader')->init();
        }
    }

    /**
     * Initialize plugin (called on WordPress 'init' hook)
     */
    public function init_plugin() {
        // Load textdomain for translations
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
        // Create database tables
        $this->container->get('database')->create_tables();

        // Set default options
        $this->set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation time
        update_option('mom_booking_activated_at', current_time('mysql'));
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('mom_booking_daily_cleanup');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'mom_booking_email_notifications' => 1,
            'mom_booking_time_zone' => 'Europe/Prague',
            'mom_booking_date_format' => 'd.m.Y',
            'mom_booking_time_format' => 'H:i',
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Only load assets when shortcodes are present
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'mom_booking_calendar') ||
            has_shortcode($post->post_content, 'mom_course_list') ||
            has_shortcode($post->post_content, 'mom_booking_form')
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

            // Localize script for AJAX
            wp_localize_script('mom-booking-frontend', 'momBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_booking_nonce'),
                'strings' => [
                    'loading' => __('Načítání...', 'mom-booking-system'),
                    'error' => __('Došlo k chybě. Zkuste to prosím znovu.', 'mom-booking-system'),
                    'success' => __('Rezervace byla úspěšně vytvořena!', 'mom-booking-system'),
                    'confirmCancel' => __('Opravdu chcete zrušit tuto rezervaci?', 'mom-booking-system'),
                ]
            ]);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
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

            // Localize script for admin AJAX
            wp_localize_script('mom-booking-admin', 'momBookingAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_admin_nonce'),
                'strings' => [
                    'confirmDelete' => __('Opravdu chcete smazat tento záznam?', 'mom-booking-system'),
                    'loading' => __('Načítání...', 'mom-booking-system'),
                    'error' => __('Došlo k chybě.', 'mom-booking-system'),
                    'success' => __('Operace byla úspěšná.', 'mom-booking-system'),
                ]
            ]);
        }
    }

    /**
     * Get container instance (for testing/debugging)
     */
    public function get_container() {
        return $this->container;
    }

    /**
     * Get plugin version
     */
    public function get_version() {
        return MOM_BOOKING_VERSION;
    }
}

/**
 * Initialize the plugin
 */
function mom_booking_init() {
    return MomBookingSystem::get_instance();
}

// Start the plugin
mom_booking_init();

/**
 * Helper function to get plugin instance
 */
function mom_booking() {
    return MomBookingSystem::get_instance();
}

/**
 * Helper function to get container
 */
function mom_booking_container() {
    return mom_booking()->get_container();
}
