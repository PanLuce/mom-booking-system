<?php
/**
 * Plugin Name: Mom Booking System
 * Description: Rezervační systém pro lekce maminek s dětmi
 * Version: 2.4
 * Author: Lukáš Vitala
 * Text Domain: mom-booking-system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MOM_BOOKING_VERSION', '2.4');
define('MOM_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOM_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class - only coordination and initialization
 */
class MomBookingSystem {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Core classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-user-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-booking-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-lesson-manager.php'; // NEW
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-registration-manager.php'; // NEW

        // Admin classes (only in admin)
        if (is_admin()) {
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-pages.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-ajax.php';
        }

        // Frontend classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-frontend-ajax.php';
    }

    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize components
        add_action('plugins_loaded', [$this, 'init_components']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function activate() {
        MomBookingDatabase::create_tables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init_components() {
        // Initialize managers
        MomCourseManager::get_instance();
        MomUserManager::get_instance();
        MomBookingManager::get_instance();
        MomLessonManager::get_instance(); // NEW
        MomCourseRegistrationManager::get_instance(); // NEW

        // Initialize admin (only in admin)
        if (is_admin()) {
            MomBookingAdminMenu::get_instance();
            MomBookingAdminPages::get_instance();
            MomBookingAdminAjax::get_instance();

            // DEBUG: Ověř že třídy existují
            error_log('Admin classes check:');
            error_log('- MomBookingAdminMenu exists: ' . (class_exists('MomBookingAdminMenu') ? 'YES' : 'NO'));
            error_log('- MomBookingAdminPages exists: ' . (class_exists('MomBookingAdminPages') ? 'YES' : 'NO'));
            error_log('- MomBookingAdminAjax exists: ' . (class_exists('MomBookingAdminAjax') ? 'YES' : 'NO'));
        }

        // Initialize frontend
        MomBookingShortcodes::get_instance();
        MomBookingFrontendAjax::get_instance();
    }

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
                    'confirmCancel' => __('Opravdu chcete zrušit tuto rezervaci?', 'mom-booking-system')
                ]
            ]);
        }
    }

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
                    'loading' => __('Načítání...', 'mom-booking-system')
                ]
            ]);
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    MomBookingSystem::get_instance();
}, 10);

// Plugin info for WordPress
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
?>
