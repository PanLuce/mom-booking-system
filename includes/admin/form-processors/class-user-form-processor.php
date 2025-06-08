<?php
/**
 * User Form Processor
 * Single Responsibility: Process user-related form submissions
 */
class MomUserFormProcessor {

    private $container;
    private $user_manager;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->user_manager = $container->get('user_manager');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Process form submission
     * @param string $action Form action
     * @param array $data Form data
     */
    public function process($action, $data) {
        switch ($action) {
            case 'create_user':
                $this->handle_create_user($data);
                break;

            case 'update_user':
                $this->handle_update_user($data);
                break;

            case 'delete_user':
                $this->handle_delete_user($data);
                break;

            default:
                throw new Exception("Unknown user action: {$action}");
        }
    }

    /**
     * Handle user creation
     */
    private function handle_create_user($data) {
        // Validate required fields
        $required_fields = ['name', 'email'];
        $this->validate_required_fields($data, $required_fields);

        // Additional validation
        $this->validate_user_data($data);

        // Check for duplicate email
        $this->check_duplicate_email($data['email']);

        // Sanitize data
        $sanitized_data = $this->sanitize_user_data($data);

        // Create user
        $user_id = $this->user_manager->create_user($sanitized_data);

        if (is_wp_error($user_id)) {
            throw new Exception($user_id->get_error_message());
        }

        // Redirect with success message
        $this->redirect_handler->to_user($user_id, 'success', 'user_created');
    }

    /**
     * Handle user update
     */
    private function handle_update_user($data) {
        $user_id = intval($data['user_id'] ?? 0);

        if (!$user_id) {
            throw new Exception(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if user exists
        $existing_user = $this->user_manager->get_user($user_id);
        if (!$existing_user) {
            throw new Exception(__('Uživatel nebyl nalezen.', 'mom-booking-system'));
        }

        // Validate required fields
        $required_fields = ['name', 'email'];
        $this->validate_required_fields($data, $required_fields);

        // Additional validation
        $this->validate_user_data($data);

        // Check for duplicate email (excluding current user)
        if ($data['email'] !== $existing_user->email) {
            $this->check_duplicate_email($data['email']);
        }

        // Sanitize data
        $sanitized_data = $this->sanitize_user_data($data);

        // Update user
        $result = $this->user_manager->update_user($user_id, $sanitized_data);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        if (!$result) {
            throw new Exception(__('Chyba při aktualizaci uživatele.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->to_user($user_id, 'success', 'user_updated');
    }

    /**
     * Handle user deletion
     */
    private function handle_delete_user($data) {
        $user_id = intval($data['user_id'] ?? 0);

        if (!$user_id) {
            throw new Exception(__('ID uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if user exists
        $user = $this->user_manager->get_user($user_id);
        if (!$user) {
            throw new Exception(__('Uživatel nebyl nalezen.', 'mom-booking-system'));
        }

        // Check if user can be deleted
        if (!$this->can_delete_user($user_id)) {
            throw new Exception(__('Uživatel má aktivní budoucí rezervace a nelze ho smazat.', 'mom-booking-system'));
        }

        // Delete user
        $result = $this->user_manager->delete_user($user_id);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        if (!$result) {
            throw new Exception(__('Chyba při mazání uživatele.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-users', 'user_deleted');
    }

    /**
     * Validate required fields
     */
    private function validate_required_fields($data, $required_fields) {
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception(sprintf(
                    __('Pole %s je povinné.', 'mom-booking-system'),
                    $this->get_field_label($field)
                ));
            }
        }
    }

    /**
     * Validate user-specific data
     */
    private function validate_user_data($data) {
        // Validate email format
        if (!is_email($data['email'])) {
            throw new Exception(__('Neplatná emailová adresa.', 'mom-booking-system'));
        }

        // Validate name length
        if (strlen($data['name']) > 255) {
            throw new Exception(__('Jméno je příliš dlouhé (maximálně 255 znaků).', 'mom-booking-system'));
        }

        // Validate phone format if provided
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[\s\-\(\)]/', '', $data['phone']);
            if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
                throw new Exception(__('Neplatné telefonní číslo.', 'mom-booking-system'));
            }
        }

        // Validate child birth date if provided
        if (!empty($data['child_birth_date'])) {
            if (!$this->is_valid_date($data['child_birth_date'])) {
                throw new Exception(__('Neplatné datum narození dítěte.', 'mom-booking-system'));
            }

            // Check if birth date is not in the future
            if (strtotime($data['child_birth_date']) > time()) {
                throw new Exception(__('Datum narození dítěte nemůže být v budoucnosti.', 'mom-booking-system'));
            }

            // Check if child is not too old (reasonable limit)
            $max_age_years = 10;
            $min_birth_date = date('Y-m-d', strtotime("-{$max_age_years} years"));
            if ($data['child_birth_date'] < $min_birth_date) {
                throw new Exception(sprintf(
                    __('Datum narození dítěte nemůže být starší než %d let.', 'mom-booking-system'),
                    $max_age_years
                ));
            }
        }

        // Validate field lengths
        $max_lengths = [
            'name' => 255,
            'email' => 255,
            'phone' => 20,
            'child_name' => 255,
            'emergency_contact' => 255,
            'notes' => 1000,
        ];

        foreach ($max_lengths as $field => $max_length) {
            if (isset($data[$field]) && strlen($data[$field]) > $max_length) {
                throw new Exception(sprintf(
                    __('Pole %s je příliš dlouhé (maximálně %d znaků).', 'mom-booking-system'),
                    $this->get_field_label($field),
                    $max_length
                ));
            }
        }
    }

    /**
     * Check for duplicate email
     */
    private function check_duplicate_email($email) {
        $existing_user = $this->user_manager->get_user_by_email($email);
        if ($existing_user) {
            throw new Exception(__('Uživatel s tímto emailem už existuje.', 'mom-booking-system'));
        }
    }

    /**
     * Check if user can be deleted
     */
    private function can_delete_user($user_id) {
        global $wpdb;

        // Check if user has future bookings
        $future_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
            JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
            WHERE (b.customer_id = %d OR b.customer_email = (
                SELECT email FROM {$wpdb->prefix}mom_customers WHERE id = %d
            ))
            AND b.booking_status = 'confirmed'
            AND l.date_time > NOW()
        ", $user_id, $user_id));

        return $future_bookings == 0;
    }

    /**
     * Sanitize user data
     */
    private function sanitize_user_data($data) {
        $sanitized = [
            'name' => sanitize_text_field($data['name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'child_name' => sanitize_text_field($data['child_name'] ?? ''),
            'emergency_contact' => sanitize_text_field($data['emergency_contact'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];

        // Handle child birth date
        if (!empty($data['child_birth_date']) && $this->is_valid_date($data['child_birth_date'])) {
            $sanitized['child_birth_date'] = sanitize_text_field($data['child_birth_date']);
        } else {
            $sanitized['child_birth_date'] = null;
        }

        return $sanitized;
    }

    /**
     * Get field label for error messages
     */
    private function get_field_label($field) {
        $labels = [
            'name' => __('Jméno a příjmení', 'mom-booking-system'),
            'email' => __('Email', 'mom-booking-system'),
            'phone' => __('Telefon', 'mom-booking-system'),
            'child_name' => __('Jméno dítěte', 'mom-booking-system'),
            'child_birth_date' => __('Datum narození dítěte', 'mom-booking-system'),
            'emergency_contact' => __('Nouzový kontakt', 'mom-booking-system'),
            'notes' => __('Poznámky', 'mom-booking-system'),
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Validate date format
     */
    private function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate user permissions for specific actions
     */
    private function validate_user_permissions($action, $user_id = null) {
        // Basic capability check
        if (!current_user_can('manage_options')) {
            throw new Exception(__('Nemáte oprávnění k této akci.', 'mom-booking-system'));
        }

        // Additional checks for sensitive actions
        switch ($action) {
            case 'delete_user':
                // Only administrators can delete users
                if (!current_user_can('administrator')) {
                    throw new Exception(__('Pouze administrátoři mohou mazat uživatele.', 'mom-booking-system'));
                }
                break;
        }
    }

    /**
     * Log user actions for audit trail
     */
    private function log_user_action($action, $user_id, $data = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
            'action' => $action,
            'target_user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // Don't log sensitive data, just the fact that action occurred
        error_log('MOM Booking User Action: ' . json_encode($log_entry));
    }

    /**
     * Send notification emails for user actions
     */
    private function send_user_notification($action, $user_data) {
        // Only send notifications for certain actions
        $notify_actions = ['create_user', 'update_user'];

        if (!in_array($action, $notify_actions)) {
            return;
        }

        // Check if notifications are enabled
        if (!get_option('mom_booking_email_notifications', 1)) {
            return;
        }

        $subject = '';
        $message = '';

        switch ($action) {
            case 'create_user':
                $subject = __('Vítejte v systému kurzů maminek', 'mom-booking-system');
                $message = sprintf(
                    __("Dobrý den %s,\n\nVáš účet byl vytvořen v našem systému rezervací kurzů.\n\nVaše údaje:\nJméno: %s\nEmail: %s\n\nTěšíme se na vás na našich kurzech!", 'mom-booking-system'),
                    $user_data['name'],
                    $user_data['name'],
                    $user_data['email']
                );
                break;

            case 'update_user':
                $subject = __('Aktualizace vašich údajů', 'mom-booking-system');
                $message = sprintf(
                    __("Dobrý den %s,\n\nVaše údaje byly aktualizovány v našem systému.\n\nPokud jste tuto změnu neprovedli vy, kontaktujte nás prosím.", 'mom-booking-system'),
                    $user_data['name']
                );
                break;
        }

        if ($subject && $message) {
            wp_mail($user_data['email'], $subject, $message);
        }
    }

    /**
     * Get user statistics after action
     */
    public function get_user_action_stats($action, $user_id = null) {
        $stats = [
            'total_users' => count($this->user_manager->get_all_users()),
            'action_performed' => $action,
            'timestamp' => current_time('mysql'),
        ];

        if ($user_id) {
            $stats['affected_user_id'] = $user_id;
        }

        return $stats;
    }
}
