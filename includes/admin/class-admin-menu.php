<?php
/**
 * Admin Menu Management - OPRAVENÉ
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
        // Main menu - používá funkci mom_booking_admin_page
        add_menu_page(
            __('Kurzy maminek', 'mom-booking-system'),
            __('Kurzy maminek', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            'mom_booking_admin_page', // OPRAVENO - používá původní funkci
            'dashicons-groups',
            30
        );

        // Submenu pages - používají původní funkce
        add_submenu_page(
            'mom-booking-admin',
            __('Přehled kurzů', 'mom-booking-system'),
            __('Přehled kurzů', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            'mom_booking_admin_page' // OPRAVENO
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Nový kurz', 'mom-booking-system'),
            __('Nový kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-new',
            'mom_booking_new_course_page' // OPRAVENO
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Uživatelé', 'mom-booking-system'),
            __('Uživatelé', 'mom-booking-system'),
            'manage_options',
            'mom-users',
            'mom_booking_users_page' // OPRAVENO
        );

        add_submenu_page(
            'mom-booking-admin',
            __('Rezervace', 'mom-booking-system'),
            __('Rezervace', 'mom-booking-system'),
            'manage_options',
            'mom-bookings',
            'mom_booking_bookings_page' // OPRAVENO
        );

        // NEW Hidden pages for detailed management - používají nové funkce
        add_submenu_page(
            null,
            __('Detail lekce', 'mom-booking-system'),
            __('Detail lekce', 'mom-booking-system'),
            'manage_options',
            'mom-lesson-detail',
            'mom_booking_lesson_detail_page' // NOVÁ funkce
        );

        add_submenu_page(
            null,
            __('Detail uživatele', 'mom-booking-system'),
            __('Detail uživatele', 'mom-booking-system'),
            'manage_options',
            'mom-user-detail',
            'mom_booking_user_detail_page' // NOVÁ funkce
        );

        add_submenu_page(
            null,
            __('Registrace na kurz', 'mom-booking-system'),
            __('Registrace na kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-registration',
            'mom_booking_course_registration_page' // NOVÁ funkce
        );
    }
}
