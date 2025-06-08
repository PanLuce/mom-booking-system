<?php
/**
 * Updated Admin Menu with new pages
 * File: includes/admin/class-admin-menu.php
 */
class MomBookingAdminMenu {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        // Main menu
        add_menu_page(
            __('Kurzy maminek', 'mom-booking-system'),
            __('Kurzy maminek', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            [MomBookingAdminPages::get_instance(), 'courses_overview_page'],
            'dashicons-groups',
            30
        );

        // Submenu pages
        add_submenu_page(
            'mom-booking-admin',
            __('Přehled kurzů', 'mom-booking-system'),
            __('Přehled kurzů', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            [MomBookingAdminPages::get_instance(), 'courses_overview_page']
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Nový kurz', 'mom-booking-system'),
            __('Nový kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-new',
            [MomBookingAdminPages::get_instance(), 'course_form_page']
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Uživatelé', 'mom-booking-system'),
            __('Uživatelé', 'mom-booking-system'),
            'manage_options',
            'mom-users',
            [MomBookingAdminPages::get_instance(), 'users_page']
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Rezervace', 'mom-booking-system'),
            __('Rezervace', 'mom-booking-system'),
            'manage_options',
            'mom-bookings',
            [MomBookingAdminPages::get_instance(), 'bookings_page']
        );

        // NEW HIDDEN SUBMENU PAGES (for detailed management)
        add_submenu_page(
            null, // Hidden from menu
            __('Detail lekce', 'mom-booking-system'),
            __('Detail lekce', 'mom-booking-system'),
            'manage_options',
            'mom-lesson-detail',
            [MomBookingAdminPages::get_instance(), 'lesson_detail_page']
        );

        add_submenu_page(
            null, // Hidden from menu
            __('Detail uživatele', 'mom-booking-system'),
            __('Detail uživatele', 'mom-booking-system'),
            'manage_options',
            'mom-user-detail',
            [MomBookingAdminPages::get_instance(), 'user_detail_page']
        );

        add_submenu_page(
            null, // Hidden from menu
            __('Registrace na kurz', 'mom-booking-system'),
            __('Registrace na kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-registration',
            [MomBookingAdminPages::get_instance(), 'course_registration_page']
        );
    }
}
