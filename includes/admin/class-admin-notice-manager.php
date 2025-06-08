<?php
/**
 * Admin Notice Manager
 * Single Responsibility: Handle admin notices and messages
 */
class MomAdminNoticeManager {

    private $container;
    private $notices = [];

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        add_action('admin_notices', [$this, 'display_notices']);
    }

    /**
     * Display admin notices
     */
    public function display_notices() {
        // Only show notices on our plugin pages
        if (!$this->is_plugin_page()) {
            return;
        }

        $this->display_url_notices();
        $this->display_stored_notices();
    }

    /**
     * Display notices from URL parameters
     */
    private function display_url_notices() {
        // Success messages
        if (isset($_GET['success'])) {
            $message = $this->get_success_message($_GET['success']);
            if ($message) {
                $this->render_notice($message, 'success', true);
            }
        }

        // Error messages
        if (isset($_GET['error'])) {
            $message = $this->get_error_message($_GET['error']);
            if ($message) {
                $this->render_notice($message, 'error', true);
            }
        }

        // Info messages
        if (isset($_GET['info'])) {
            $message = $this->get_info_message($_GET['info']);
            if ($message) {
                $this->render_notice($message, 'info', true);
            }
        }

        // Warning messages
        if (isset($_GET['warning'])) {
            $message = $this->get_warning_message($_GET['warning']);
            if ($message) {
                $this->render_notice($message, 'warning', true);
            }
        }

        // Bulk action messages
        if (isset($_GET['bulk_action'])) {
            $this->display_bulk_action_notice();
        }
    }

    /**
     * Display stored notices (from session/transients)
     */
    private function display_stored_notices() {
        foreach ($this->notices as $notice) {
            $this->render_notice($notice['message'], $notice['type'], $notice['dismissible'] ?? true);
        }
        $this->notices = []; // Clear after displaying
    }

    /**
     * Get success message by key
     */
    private function get_success_message($key) {
        $messages = [
            'course_created' => __('Kurz byl úspěšně vytvořen a lekce vygenerovány!', 'mom-booking-system'),
            'course_updated' => __('Kurz byl úspěšně aktualizován!', 'mom-booking-system'),
            'course_deleted' => __('Kurz byl úspěšně smazán!', 'mom-booking-system'),
            'user_created' => __('Uživatel byl úspěšně vytvořen!', 'mom-booking-system'),
            'user_updated' => __('Uživatel byl úspěšně aktualizován!', 'mom-booking-system'),
            'user_deleted' => __('Uživatel byl úspěšně smazán!', 'mom-booking-system'),
            'lesson_updated' => __('Lekce byla úspěšně aktualizována!', 'mom-booking-system'),
            'user_added' => __('Uživatel byl přidán na lekci!', 'mom-booking-system'),
            'booking_cancelled' => __('Rezervace byla zrušena!', 'mom-booking-system'),
            'course_registration' => $this->get_course_registration_message(),
            'settings_saved' => __('Nastavení bylo uloženo!', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }

    /**
     * Get error message by key
     */
    private function get_error_message($key) {
        $messages = [
            'duplicate_email' => __('Uživatel s tímto emailem už existuje!', 'mom-booking-system'),
            'create_failed' => __('Chyba při vytváření záznamu.', 'mom-booking-system'),
            'update_failed' => __('Chyba při aktualizaci záznamu.', 'mom-booking-system'),
            'delete_failed' => __('Chyba při mazání záznamu.', 'mom-booking-system'),
            'lesson_not_found' => __('Lekce nebyla nalezena.', 'mom-booking-system'),
            'user_not_found' => __('Uživatel nebyl nalezen.', 'mom-booking-system'),
            'course_not_found' => __('Kurz nebyl nalezen.', 'mom-booking-system'),
            'lesson_full' => __('Lekce je již plně obsazena.', 'mom-booking-system'),
            'already_booked' => __('Uživatel je již na tuto lekci přihlášen.', 'mom-booking-system'),
            'already_registered' => __('Uživatel je již na kurz registrován.', 'mom-booking-system'),
            'has_bookings' => __('Nelze smazat - existují aktivní rezervace.', 'mom-booking-system'),
            'invalid_data' => __('Neplatná data ve formuláři.', 'mom-booking-system'),
            'permission_denied' => __('Nemáte oprávnění k této akci.', 'mom-booking-system'),
            'database_error' => __('Chyba databáze. Zkuste to prosím znovu.', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }

    /**
     * Get info message by key
     */
    private function get_info_message($key) {
        $messages = [
            'no_courses' => __('Zatím nemáte žádné kurzy. Vytvořte první kurz.', 'mom-booking-system'),
            'no_users' => __('Zatím nemáte žádné uživatele.', 'mom-booking-system'),
            'no_bookings' => __('Zatím nejsou žádné rezervace.', 'mom-booking-system'),
            'course_ended' => __('Tento kurz již skončil.', 'mom-booking-system'),
            'lesson_cancelled' => __('Tato lekce byla zrušena.', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }

    /**
     * Get warning message by key
     */
    private function get_warning_message($key) {
        $messages = [
            'course_starting_soon' => __('Kurz začíná brzy. Zkontrolujte rezervace.', 'mom-booking-system'),
            'low_capacity' => __('Zbývá málo míst v kurzu.', 'mom-booking-system'),
            'backup_recommended' => __('Doporučujeme pravidelně zálohovat data.', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }

    /**
     * Get course registration message with count
     */
    private function get_course_registration_message() {
        $registered = intval($_GET['registered'] ?? 0);
        return sprintf(
            __('Uživatel byl registrován na %d lekcí kurzu!', 'mom-booking-system'),
            $registered
        );
    }

    /**
     * Display bulk action notice
     */
    private function display_bulk_action_notice() {
        $action = $_GET['bulk_action'] ?? '';
        $success_count = intval($_GET['success_count'] ?? 0);
        $error_count = intval($_GET['error_count'] ?? 0);

        $action_messages = [
            'delete' => __('smazáno', 'mom-booking-system'),
            'activate' => __('aktivováno', 'mom-booking-system'),
            'deactivate' => __('deaktivováno', 'mom-booking-system'),
            'export' => __('exportováno', 'mom-booking-system'),
        ];

        $action_text = $action_messages[$action] ?? $action;

        if ($success_count > 0) {
            $message = sprintf(
                __('Úspěšně %s %d záznamů.', 'mom-booking-system'),
                $action_text,
                $success_count
            );

            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    __('%d záznamů se nepodařilo zpracovat.', 'mom-booking-system'),
                    $error_count
                );
            }

            $type = $error_count > 0 ? 'warning' : 'success';
            $this->render_notice($message, $type, true);
        } elseif ($error_count > 0) {
            $message = sprintf(
                __('Nepodařilo se zpracovat %d záznamů.', 'mom-booking-system'),
                $error_count
            );
            $this->render_notice($message, 'error', true);
        }
    }

    /**
     * Render notice HTML
     */
    private function render_notice($message, $type = 'info', $dismissible = true) {
        $classes = ['notice', 'notice-' . $type];

        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        $class_string = implode(' ', $classes);

        echo '<div class="' . esc_attr($class_string) . '">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
    }

    /**
     * Add notice to be displayed later
     */
    public function add_notice($message, $type = 'info', $dismissible = true) {
        $this->notices[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
        ];
    }

    /**
     * Add success notice
     */
    public function add_success($message, $dismissible = true) {
        $this->add_notice($message, 'success', $dismissible);
    }

    /**
     * Add error notice
     */
    public function add_error($message, $dismissible = true) {
        $this->add_notice($message, 'error', $dismissible);
    }

    /**
     * Add warning notice
     */
    public function add_warning($message, $dismissible = true) {
        $this->add_notice($message, 'warning', $dismissible);
    }

    /**
     * Add info notice
     */
    public function add_info($message, $dismissible = true) {
        $this->add_notice($message, 'info', $dismissible);
    }

    /**
     * Check if current page is plugin page
     */
    private function is_plugin_page() {
        $current_page = $_GET['page'] ?? '';
        return strpos($current_page, 'mom-') === 0;
    }

    /**
     * Display conditional notices based on plugin state
     */
    public function display_conditional_notices() {
        // Check for first-time setup
        if (!get_option('mom_booking_setup_complete')) {
            $this->add_info(
                sprintf(
                    __('Vítejte v systému rezervací! <a href="%s">Vytvořte svůj první kurz</a> pro začátek.', 'mom-booking-system'),
                    admin_url('admin.php?page=mom-course-new')
                ),
                false
            );
        }

        // Check for plugin updates
        $installed_version = get_option('mom_booking_version');
        if ($installed_version && version_compare($installed_version, MOM_BOOKING_VERSION, '<')) {
            $this->add_warning(
                __('Plugin byl aktualizován. Doporučujeme zkontrolovat nastavení.', 'mom-booking-system'),
                true
            );
        }
    }

    /**
     * Clear all notices
     */
    public function clear_notices() {
        $this->notices = [];
    }

    /**
     * Get all pending notices
     */
    public function get_notices() {
        return $this->notices;
    }
}
