<?php
/**
 * Extended Admin pages with new functionality
 */
class MomBookingAdminPagesExtended extends MomBookingAdminPages {

    public function handle_form_submissions() {
        parent::handle_form_submissions();

        if ($this->form_processed || !isset($_POST['mom_action'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'mom_admin_action')) {
            wp_die(__('Bezpečnostní kontrola selhala', 'mom-booking-system'));
        }

        $this->form_processed = true;

        switch ($_POST['mom_action']) {
            case 'update_lesson':
                $this->handle_update_lesson();
                break;
            case 'update_user':
                $this->handle_update_user();
                break;
            case 'add_user_to_lesson':
                $this->handle_add_user_to_lesson();
                break;
            case 'register_user_for_course':
                $this->handle_register_user_for_course();
                break;
        }
    }

    private function handle_update_lesson() {
        $lesson_id = intval($_POST['lesson_id']);
        $success = MomLessonManager::get_instance()->update_lesson($lesson_id, $_POST);

        if ($success) {
            wp_redirect(admin_url('admin.php?page=mom-lesson-detail&id=' . $lesson_id . '&lesson_updated=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=mom-lesson-detail&id=' . $lesson_id . '&error=update_failed'));
        }
        exit;
    }

    private function handle_update_user() {
        $user_id = intval($_POST['user_id']);
        $result = MomUserManager::get_instance()->update_user($user_id, $_POST);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=mom-user-detail&id=' . $user_id . '&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=mom-user-detail&id=' . $user_id . '&user_updated=1'));
        }
        exit;
    }

    private function handle_add_user_to_lesson() {
        $lesson_id = intval($_POST['lesson_id']);
        $user_id = intval($_POST['user_id']);

        $result = MomLessonManager::get_instance()->add_user_to_lesson($lesson_id, $user_id);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=mom-lesson-detail&id=' . $lesson_id . '&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=mom-lesson-detail&id=' . $lesson_id . '&user_added=1'));
        }
        exit;
    }

    private function handle_register_user_for_course() {
        $course_id = intval($_POST['course_id']);
        $user_id = intval($_POST['user_id']);

        $result = MomCourseRegistrationManager::get_instance()->register_user_for_course($course_id, $user_id);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_registration=1&registered=' . $result['total_registered']));
        }
        exit;
    }

    /**
     * NEW: Lesson detail page
     */
    public function lesson_detail_page() {
        if (!isset($_GET['id'])) {
            wp_die(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        $lesson_id = intval($_GET['id']);
        $lesson_data = MomLessonManager::get_instance()->get_lesson_schedule($lesson_id);

        if (!$lesson_data) {
            wp_die(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        $available_users = MomUserManager::get_instance()->get_available_users_for_lesson($lesson_id);

        $this->display_admin_notices();

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/lesson-detail.php';
    }

    /**
     * NEW: User detail page
     */
    public function user_detail_page() {
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

        $this->display_admin_notices();

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/user-detail.php';
    }

    /**
     * NEW: Course registration page
     */
    public function course_registration_page() {
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

        $this->display_admin_notices();

        include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/course-registration.php';
    }

    /**
     * Extended admin notices with new messages
     */
    protected function display_admin_notices() {
        parent::display_admin_notices();

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

        // Extended error messages
        if (isset($_GET['error'])) {
            $extended_error_messages = [
                'lesson_not_found' => __('Lekce nebyla nalezena.', 'mom-booking-system'),
                'user_not_found' => __('Uživatel nebyl nalezen.', 'mom-booking-system'),
                'lesson_full' => __('Lekce je již plně obsazena.', 'mom-booking-system'),
                'already_booked' => __('Uživatel je již na tuto lekci přihlášen.', 'mom-booking-system'),
                'already_registered' => __('Uživatel je již na kurz registrován.', 'mom-booking-system'),
                'has_bookings' => __('Nelze smazat - existují aktivní rezervace.', 'mom-booking-system')
            ];

            $error = $_GET['error'];
            if (isset($extended_error_messages[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $extended_error_messages[$error] . '</p></div>';
            }
        }
    }
}
