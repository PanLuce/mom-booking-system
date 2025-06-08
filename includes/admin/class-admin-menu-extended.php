<?php
/**
 * Extended Admin menu with new pages
 */
class MomBookingAdminMenuExtended extends MomBookingAdminMenu {

    public function register_menu() {
        // Call parent menu registration
        parent::register_menu();

        // Add new submenu pages for detailed management
        add_submenu_page(
            null, // Hidden from menu
            __('Detail lekce', 'mom-booking-system'),
            __('Detail lekce', 'mom-booking-system'),
            'manage_options',
            'mom-lesson-detail',
            [MomBookingAdminPagesExtended::get_instance(), 'lesson_detail_page']
        );

        add_submenu_page(
            null, // Hidden from menu
            __('Detail uživatele', 'mom-booking-system'),
            __('Detail uživatele', 'mom-booking-system'),
            'manage_options',
            'mom-user-detail',
            [MomBookingAdminPagesExtended::get_instance(), 'user_detail_page']
        );

        add_submenu_page(
            null, // Hidden from menu
            __('Registrace na kurز', 'mom-booking-system'),
            __('Registrace na kurز', 'mom-booking-system'),
            'manage_options',
            'mom-course-registration',
            [MomBookingAdminPagesExtended::get_instance(), 'course_registration_page']
        );
    }
}
