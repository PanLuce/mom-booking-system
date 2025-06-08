<?php
/**
 * Plugin Name: Mom Booking System
 * Description: Rezervační systém pro lekce maminek s dětmi
 * Version: 2.4
 * Author: Lukáš Vitala
 * Text Domain: mom-booking-system
 */

 error_log('=== MOM BOOKING SYSTEM DEBUG START ===');
 error_log('Plugin file loaded: ' . __FILE__);

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
        error_log('=== LOADING DEPENDENCIES ===');
        error_log('Plugin dir: ' . MOM_BOOKING_PLUGIN_DIR);
        error_log('Is admin: ' . (is_admin() ? 'YES' : 'NO'));

        // Core classes
        $core_files = [
            'includes/class-database.php',
            'includes/class-course-manager.php',
            'includes/class-user-manager.php',
            'includes/class-booking-manager.php'
        ];

        foreach ($core_files as $file) {
            $full_path = MOM_BOOKING_PLUGIN_DIR . $file;
            error_log('Checking core file: ' . $full_path);
            error_log('File exists: ' . (file_exists($full_path) ? 'YES' : 'NO'));

            if (file_exists($full_path)) {
                require_once $full_path;
                error_log('Successfully loaded: ' . $file);
            } else {
                error_log('ERROR: Cannot find file: ' . $file);
            }
        }

        // Admin classes (only in admin)
        if (is_admin()) {
            error_log('Loading admin classes...');

            $admin_files = [
                'includes/admin/class-admin-pages.php',
                'includes/admin/class-admin-menu.php',
                'includes/admin/class-admin-ajax.php'
            ];

            foreach ($admin_files as $file) {
                $full_path = MOM_BOOKING_PLUGIN_DIR . $file;
                error_log('Checking admin file: ' . $full_path);
                error_log('File exists: ' . (file_exists($full_path) ? 'YES' : 'NO'));

                if (file_exists($full_path)) {
                    require_once $full_path;
                    error_log('Successfully loaded: ' . $file);
                } else {
                    error_log('ERROR: Cannot find admin file: ' . $file);
                }
            }
        }

        // Frontend classes
        $frontend_files = [
            'includes/frontend/class-shortcodes.php',
            'includes/frontend/class-frontend-ajax.php'
        ];

        foreach ($frontend_files as $file) {
            $full_path = MOM_BOOKING_PLUGIN_DIR . $file;
            error_log('Checking frontend file: ' . $full_path);
            error_log('File exists: ' . (file_exists($full_path) ? 'YES' : 'NO'));

            if (file_exists($full_path)) {
                require_once $full_path;
                error_log('Successfully loaded: ' . $file);
            } else {
                error_log('ERROR: Cannot find frontend file: ' . $file);
            }
        }

        error_log('=== DEPENDENCIES LOADING COMPLETE ===');
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
        error_log('=== INIT COMPONENTS START ===');

        // Initialize managers
        error_log('Initializing core managers...');

        if (class_exists('MomCourseManager')) {
            MomCourseManager::get_instance();
            error_log('MomCourseManager initialized');
        } else {
            error_log('ERROR: MomCourseManager class not found');
        }

        if (class_exists('MomUserManager')) {
            MomUserManager::get_instance();
            error_log('MomUserManager initialized');
        } else {
            error_log('ERROR: MomUserManager class not found');
        }

        if (class_exists('MomBookingManager')) {
            MomBookingManager::get_instance();
            error_log('MomBookingManager initialized');
        } else {
            error_log('ERROR: MomBookingManager class not found');
        }

        // Initialize admin (only in admin)
        if (is_admin()) {
            error_log('=== ADMIN CONTEXT DEBUG ===');
            error_log('is_admin(): ' . (is_admin() ? 'TRUE' : 'FALSE'));
            error_log('Current user ID: ' . get_current_user_id());
            error_log('Current hook: ' . current_action());
            error_log('Admin menu classes about to initialize...');

            MomBookingAdminMenu::get_instance();
            MomBookingAdminPages::get_instance();
            MomBookingAdminAjax::get_instance();

            error_log('Admin classes initialized, checking existence:');
            error_log('- MomBookingAdminMenu instance: ' . (MomBookingAdminMenu::get_instance() ? 'EXISTS' : 'NULL'));
        }

        // Initialize frontend
        error_log('Initializing frontend components...');

        if (class_exists('MomBookingShortcodes')) {
            MomBookingShortcodes::get_instance();
            error_log('MomBookingShortcodes initialized');
        } else {
            error_log('ERROR: MomBookingShortcodes class not found');
        }

        if (class_exists('MomBookingFrontendAjax')) {
            MomBookingFrontendAjax::get_instance();
            error_log('MomBookingFrontendAjax initialized');
        } else {
            error_log('ERROR: MomBookingFrontendAjax class not found');
        }

        error_log('=== INIT COMPONENTS COMPLETE ===');
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

// PŘÍMÝ TEST MENU - mimo třídy
add_action('admin_menu', function() {
    error_log('=== DIRECT MENU TEST ===');
    error_log('Direct hook fired at priority 10');
    error_log('User can manage_options: ' . (current_user_can('manage_options') ? 'TRUE' : 'FALSE'));

    $direct_menu = add_menu_page(
        'DIRECT TEST',
        'DIRECT TEST',
        'manage_options',
        'direct-test-menu',
        function() {
            echo '<h1>Direct menu works!</h1>';
        },
        'dashicons-admin-tools',
        25
    );

    error_log('Direct menu result: ' . var_export($direct_menu, true));
}, 5); // Vyšší priorita než normálně
?>
