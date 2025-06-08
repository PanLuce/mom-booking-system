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
        $this->init_components();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Core classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-user-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-booking-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-lesson-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-registration-manager.php';

        // Admin classes (only in admin)
        if (is_admin()) {
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-pages.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-ajax.php';
        }

        // Frontend classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-frontend-ajax.php';
    }

    private function init_components() {
        // Initialize managers
        MomCourseManager::get_instance();
        MomUserManager::get_instance();
        MomBookingManager::get_instance();
        MomLessonManager::get_instance();
        MomCourseRegistrationManager::get_instance();

        // Initialize admin (only in admin)
        if (is_admin()) {
            MomBookingAdminPages::get_instance();
            MomBookingAdminMenu::get_instance();
            MomBookingAdminAjax::get_instance();
        }

        // Initialize frontend
        MomBookingShortcodes::get_instance();
        MomBookingFrontendAjax::get_instance();
    }

    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

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
MomBookingSystem::get_instance();

// Plugin info for WordPress
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

/**
 * NEW ADMIN PAGE FUNCTIONS
 */
function mom_booking_lesson_detail_page() {
    if (!isset($_GET['id'])) {
        wp_die(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
    }

    $lesson_id = intval($_GET['id']);
    $lesson_data = MomLessonManager::get_instance()->get_lesson_schedule($lesson_id);

    if (!$lesson_data) {
        wp_die(__('Lekce nebyla nalezena.', 'mom-booking-system'));
    }

    $available_users = MomUserManager::get_instance()->get_available_users_for_lesson($lesson_id);

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/lesson-detail.php';
}

function mom_booking_user_detail_page() {
    if (!isset($_GET['id'])) {
        wp_die(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
    }

    $user_id = intval($_GET['id']);
    $user = MomUserManager::get_instance()->get_user($user_id);

    if (!$user) {
        wp_die(__('Uživatel nebyl nalezen.', 'mom-booking-system'));
    }

    $user_bookings = MomUserManager::get_instance()->get_user_bookings($user_id);
    $user_stats = MomUserManager::get_instance()->get_user_statistics($user_id);

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/user-detail.php';
}

function mom_booking_course_registration_page() {
    if (!isset($_GET['course_id'])) {
        wp_die(__('ID kurzu nebylo specifikováno.', 'mom-booking-system'));
    }

    $course_id = intval($_GET['course_id']);
    $course = MomCourseManager::get_instance()->get_course($course_id);

    if (!$course) {
        wp_die(__('Kurz nebyl nalezen.', 'mom-booking-system'));
    }

    $all_users = MomUserManager::get_instance()->get_all_users();
    $registered_users = MomUserManager::get_instance()->get_users_for_course($course_id);
    $course_stats = MomCourseRegistrationManager::get_instance()->get_course_registration_stats($course_id);

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/course-registration.php';
}

/**
 * ROZŠÍŘENÉ ADMIN NOTICES
 */
add_action('admin_notices', 'mom_booking_display_extended_notices');

function mom_booking_display_extended_notices() {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'mom-') !== 0) {
        return;
    }

    // NEW SUCCESS MESSAGES
    if (isset($_GET['lesson_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Lekce byla úspěšně aktualizována!', 'mom-booking-system') . '</p></div>';
    }

    if (isset($_GET['user_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Uživatel byl úspěšně aktualizován!', 'mom-booking-system') . '</p></div>';
    }

    if (isset($_GET['user_added'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Uživatel byl přidán na lekci!', 'mom-booking-system') . '</p></div>';
    }

    if (isset($_GET['course_registration'])) {
        $registered = intval($_GET['registered'] ?? 0);
        echo '<div class="notice notice-success is-dismissible"><p>' .
             sprintf(__('Uživatel byl registrován na %d lekcí kurzu!', 'mom-booking-system'), $registered) .
             '</p></div>';
    }

    // EXTENDED ERROR MESSAGES
    if (isset($_GET['error'])) {
        $extended_error_messages = [
            'lesson_not_found' => __('Lekce nebyla nalezena.', 'mom-booking-system'),
            'user_not_found' => __('Uživatel nebyl nalezen.', 'mom-booking-system'),
            'lesson_full' => __('Lekce je již plně obsazena.', 'mom-booking-system'),
            'already_booked' => __('Uživatel je již na tuto lekci přihlášen.', 'mom-booking-system'),
            'already_registered' => __('Uživatel je již na kurz registrován.', 'mom-booking-system'),
            'has_bookings' => __('Nelze smazat - existují aktivní rezervace.', 'mom-booking-system'),
            'duplicate_email' => __('Email je již používán jiným uživatelem.', 'mom-booking-system'),
            'update_failed' => __('Chyba při aktualizaci záznamu.', 'mom-booking-system')
        ];

        $error = $_GET['error'];
        if (isset($extended_error_messages[$error])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . $extended_error_messages[$error] . '</p></div>';
        }
    }
}
?>
