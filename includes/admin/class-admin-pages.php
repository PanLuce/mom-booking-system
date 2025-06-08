<?php
/**
 * Admin pages management class
 */
class MomBookingAdminPages {

    private static $instance = null;
    private $form_processed = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', [$this, 'handle_form_submissions']);
    }

    public function handle_form_submissions() {
        if ($this->form_processed || !isset($_POST['mom_action'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'mom_admin_action')) {
            wp_die(__('Bezpečnostní kontrola selhala', 'mom-booking-system'));
        }

        $this->form_processed = true;

        switch ($_POST['mom_action']) {
            case 'create_course':
                $this->handle_create_course();
                break;
            case 'update_course':
                $this->handle_update_course();
                break;
            case 'create_user':
                $this->handle_create_user();
                break;
        }
    }

    private function handle_create_course() {
        $course_id = MomCourseManager::get_instance()->create_course($_POST);

        if ($course_id) {
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_created=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=mom-course-new&error=create_failed'));
            exit;
        }
    }

    private function handle_update_course() {
        $course_id = intval($_POST['course_id']);
        $success = MomCourseManager::get_instance()->update_course($course_id, $_POST);

        if ($success) {
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_updated=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=mom-course-new&edit=' . $course_id . '&error=update_failed'));
            exit;
        }
    }

    private function handle_create_user() {
        $user_id = MomUserManager::get_instance()->create_user($_POST);

        if (is_wp_error($user_id)) {
            if ($user_id->get_error_code() === 'duplicate_email') {
                wp_redirect(admin_url('admin.php?page=mom-users&error=duplicate_email'));
            } else {
                wp_redirect(admin_url('admin.php?page=mom-users&error=create_failed'));
            }
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=mom-users&user_created=1'));
            exit;
        }
    }

    public function courses_overview_page() {
        // Success messages
        $this->display_admin_notices();

        $courses = MomCourseManager::get_instance()->get_all_courses();

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/courses-overview.php';
    }

    public function course_form_page() {
        $editing = isset($_GET['edit']);
        $course = null;

        if ($editing) {
            $course_id = intval($_GET['edit']);
            $course = MomCourseManager::get_instance()->get_course($course_id);

            if (!$course) {
                wp_die(__('Kurz nebyl nalezen.', 'mom-booking-system'));
            }
        }

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/course-form.php';
    }

    public function users_page() {
        $this->display_admin_notices();

        $users = MomUserManager::get_instance()->get_all_users();

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/users.php';
    }

    public function bookings_page() {
        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/bookings.php';
    }

    public function settings_page() {
        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    private function display_admin_notices() {
        if (isset($_GET['course_created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Kurz byl úspěšně vytvořen a lekce vygenerovány!', 'mom-booking-system') . '</p></div>';
        }

        if (isset($_GET['course_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Kurz byl úspěšně aktualizován!', 'mom-booking-system') . '</p></div>';
        }

        if (isset($_GET['user_created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Uživatel byl úspěšně vytvořen!', 'mom-booking-system') . '</p></div>';
        }

        if (isset($_GET['error'])) {
            $error_messages = [
                'duplicate_email' => __('Uživatel s tímto emailem už existuje!', 'mom-booking-system'),
                'create_failed' => __('Chyba při vytváření záznamu.', 'mom-booking-system'),
                'update_failed' => __('Chyba při aktualizaci záznamu.', 'mom-booking-system')
            ];

            $error = $_GET['error'];
            if (isset($error_messages[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $error_messages[$error] . '</p></div>';
            }
        }
    }
}
