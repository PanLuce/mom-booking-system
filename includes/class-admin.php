<?php
/**
 * Hlavní admin třída - koordinuje ostatní komponenty
 */
class MomBookingAdmin {

    private $course_manager;
    private $customer_manager;
    private $enrollment_manager;
    private $admin_pages;

    public function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    private function load_dependencies() {
        $admin_path = plugin_dir_path(__FILE__) . 'admin/';
        $helpers_path = plugin_dir_path(__FILE__) . 'helpers/';

        require_once $helpers_path . 'class-date-helper.php';
        require_once $admin_path . 'class-course-manager.php';
        require_once $admin_path . 'class-customer-manager.php';
        require_once $admin_path . 'class-enrollment-manager.php';
        require_once $admin_path . 'class-admin-pages.php';
    }

    private function init_components() {
        $this->course_manager = new MomCourseManager();
        $this->customer_manager = new MomCustomerManager();
        $this->enrollment_manager = new MomEnrollmentManager();
        $this->admin_pages = new MomAdminPages(
            $this->course_manager,
            $this->customer_manager,
            $this->enrollment_manager
        );
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Kurzy maminek',
            'Kurzy maminek',
            'manage_options',
            'mom-booking-admin',
            [$this->admin_pages, 'courses_overview_page'],
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'mom-booking-admin',
            'Přehled kurzů',
            'Přehled kurzů',
            'manage_options',
            'mom-booking-admin',
            [$this->admin_pages, 'courses_overview_page']
        );

        add_submenu_page(
            'mom-booking-admin',
            'Nový kurz',
            'Nový kurz',
            'manage_options',
            'mom-course-new',
            [$this->admin_pages, 'course_form_page']
        );

        add_submenu_page(
            'mom-booking-admin',
            'Maminky',
            'Maminky',
            'manage_options',
            'mom-customers',
            [$this->admin_pages, 'customers_page']
        );
    }

    public function admin_scripts($hook) {
        if (strpos($hook, 'mom-') !== false) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');

            // Vlastní admin styly
            wp_enqueue_style(
                'mom-admin-css',
                plugin_dir_url(__FILE__) . '../assets/admin.css',
                [],
                '1.0'
            );
        }
    }

    public function handle_admin_actions() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mom_admin_nonce')) {
            return;
        }

        // Deleguj akce na příslušné managery
        if (isset($_POST['create_course']) || isset($_POST['update_course'])) {
            $this->course_manager->handle_course_form();
        }

        if (isset($_POST['enroll_customer'])) {
            $this->enrollment_manager->enroll_customer();
        }

        if (isset($_GET['action'])) {
            $this->handle_get_actions();
        }
    }

    private function handle_get_actions() {
        $action = sanitize_text_field($_GET['action']);

        switch ($action) {
            case 'unenroll':
                if (isset($_GET['enrollment_id'])) {
                    $this->enrollment_manager->unenroll_customer($_GET['enrollment_id']);
                }
                break;

            case 'cancel_lesson':
                if (isset($_GET['lesson_id'])) {
                    $this->course_manager->cancel_lesson($_GET['lesson_id']);
                }
                break;

            case 'generate_lessons':
                if (isset($_GET['course_id'])) {
                    $this->course_manager->generate_course_lessons($_GET['course_id']);
                }
                break;
        }
    }
}
