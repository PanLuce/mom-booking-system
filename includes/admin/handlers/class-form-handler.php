<?php
/**
 * Admin Form Handler
 * Single Responsibility: Route form submissions to appropriate processors
 */
class MomAdminFormHandler {

    private $container;
    private $processors = [];
    private $form_processed = false;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->init_processors();
        add_action('admin_init', [$this, 'handle_forms']);
    }

    /**
     * Initialize form processors
     */
    private function init_processors() {
        // Lazy loading of processors - they will be created when needed
        $this->processor_map = [
            'create_course' => 'course_form_processor',
            'update_course' => 'course_form_processor',
            'delete_course' => 'course_form_processor',

            'create_user' => 'user_form_processor',
            'update_user' => 'user_form_processor',
            'delete_user' => 'user_form_processor',

            'create_booking' => 'booking_form_processor',
            'cancel_booking' => 'booking_form_processor',
            'update_booking' => 'booking_form_processor',

            'update_lesson' => 'lesson_form_processor',
            'add_user_to_lesson' => 'lesson_form_processor',
            'remove_user_from_lesson' => 'lesson_form_processor',
            'toggle_lesson_status' => 'lesson_form_processor',

            'register_user_for_course' => 'course_form_processor',
            'bulk_register_users' => 'course_form_processor',
        ];
    }

    /**
     * Handle form submissions
     */
    public function handle_forms() {
        // Prevent duplicate processing
        if ($this->form_processed) {
            return;
        }

        // Check if this is a form submission
        if (!$this->is_form_submission()) {
            return;
        }

        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_die(__('Bezpečnostní kontrola selhala', 'mom-booking-system'));
        }

        // Check user permissions
        if (!$this->check_permissions()) {
            wp_die(__('Nemáte oprávnění k této akci', 'mom-booking-system'));
        }

        $this->form_processed = true;
        $action = $_POST['mom_action'];

        try {
            $this->process_form($action, $_POST);
        } catch (Exception $e) {
            $this->handle_form_error($action, $e);
        }
    }

    /**
     * Check if this is a form submission
     */
    private function is_form_submission() {
        return isset($_POST['mom_action']) &&
               $_SERVER['REQUEST_METHOD'] === 'POST' &&
               $this->is_plugin_page();
    }

    /**
     * Verify security nonce
     */
    private function verify_nonce() {
        return wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mom_admin_action');
    }

    /**
     * Check user permissions
     */
    private function check_permissions() {
        // Basic admin capability check
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Additional action-specific permission checks can be added here
        return true;
    }

    /**
     * Check if we're on a plugin page
     */
    private function is_plugin_page() {
        $current_page = $_GET['page'] ?? '';
        return strpos($current_page, 'mom-') === 0;
    }

    /**
     * Process form based on action
     */
    private function process_form($action, $data) {
        if (!isset($this->processor_map[$action])) {
            throw new Exception("Unknown form action: {$action}");
        }

        $processor_service = $this->processor_map[$action];
        $processor = $this->get_processor($processor_service);

        $processor->process($action, $data);
    }

    /**
     * Get processor instance (lazy loading)
     */
    private function get_processor($processor_service) {
        if (!isset($this->processors[$processor_service])) {
            $this->processors[$processor_service] = $this->container->get($processor_service);
        }

        return $this->processors[$processor_service];
    }

    /**
     * Handle form processing errors
     */
    private function handle_form_error($action, Exception $e) {
        error_log("Form processing error for action '{$action}': " . $e->getMessage());

        // Get current page for redirect
        $current_page = $_GET['page'] ?? 'mom-booking-admin';

        // Redirect with error message
        $redirect_handler = $this->container->get('redirect_handler');
        $redirect_handler->error($current_page, 'processing_error', [
            'action' => $action,
            'message' => $e->getMessage()
        ]);
    }

    /**
     * Validate form data
     */
    public function validate_form_data($action, $data) {
        $validation_rules = $this->get_validation_rules($action);
        $errors = [];

        foreach ($validation_rules as $field => $rules) {
            $value = $data[$field] ?? '';

            // Required field check
            if (isset($rules['required']) && $rules['required'] && empty($value)) {
                $errors[$field] = sprintf(
                    __('Pole %s je povinné.', 'mom-booking-system'),
                    $rules['label'] ?? $field
                );
                continue;
            }

            // Skip other validations if field is empty and not required
            if (empty($value)) {
                continue;
            }

            // Type validation
            if (isset($rules['type'])) {
                switch ($rules['type']) {
                    case 'email':
                        if (!is_email($value)) {
                            $errors[$field] = __('Neplatná emailová adresa.', 'mom-booking-system');
                        }
                        break;

                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = __('Pole musí obsahovat číslo.', 'mom-booking-system');
                        }
                        break;

                    case 'date':
                        if (!$this->is_valid_date($value)) {
                            $errors[$field] = __('Neplatné datum.', 'mom-booking-system');
                        }
                        break;

                    case 'time':
                        if (!$this->is_valid_time($value)) {
                            $errors[$field] = __('Neplatný čas.', 'mom-booking-system');
                        }
                        break;
                }
            }

            // Length validation
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $errors[$field] = sprintf(
                    __('Pole může obsahovat maximálně %d znaků.', 'mom-booking-system'),
                    $rules['max_length']
                );
            }

            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $errors[$field] = sprintf(
                    __('Pole musí obsahovat alespoň %d znaků.', 'mom-booking-system'),
                    $rules['min_length']
                );
            }

            // Range validation for numeric fields
            if (isset($rules['min_value']) && is_numeric($value) && $value < $rules['min_value']) {
                $errors[$field] = sprintf(
                    __('Hodnota musí být alespoň %d.', 'mom-booking-system'),
                    $rules['min_value']
                );
            }

            if (isset($rules['max_value']) && is_numeric($value) && $value > $rules['max_value']) {
                $errors[$field] = sprintf(
                    __('Hodnota může být maximálně %d.', 'mom-booking-system'),
                    $rules['max_value']
                );
            }
        }

        return $errors;
    }

    /**
     * Get validation rules for specific action
     */
    private function get_validation_rules($action) {
        $rules = [
            'create_course' => [
                'title' => [
                    'required' => true,
                    'label' => __('Název kurzu', 'mom-booking-system'),
                    'max_length' => 255
                ],
                'start_date' => [
                    'required' => true,
                    'type' => 'date',
                    'label' => __('Datum začátku', 'mom-booking-system')
                ],
                'lesson_count' => [
                    'required' => true,
                    'type' => 'numeric',
                    'min_value' => 1,
                    'max_value' => 52,
                    'label' => __('Počet lekcí', 'mom-booking-system')
                ],
                'day_of_week' => [
                    'required' => true,
                    'type' => 'numeric',
                    'min_value' => 1,
                    'max_value' => 7,
                    'label' => __('Den v týdnu', 'mom-booking-system')
                ],
                'start_time' => [
                    'required' => true,
                    'type' => 'time',
                    'label' => __('Čas začátku', 'mom-booking-system')
                ],
                'max_capacity' => [
                    'required' => true,
                    'type' => 'numeric',
                    'min_value' => 1,
                    'max_value' => 100,
                    'label' => __('Maximální kapacita', 'mom-booking-system')
                ],
                'price' => [
                    'type' => 'numeric',
                    'min_value' => 0,
                    'label' => __('Cena', 'mom-booking-system')
                ]
            ],

            'create_user' => [
                'name' => [
                    'required' => true,
                    'label' => __('Jméno', 'mom-booking-system'),
                    'max_length' => 255
                ],
                'email' => [
                    'required' => true,
                    'type' => 'email',
                    'label' => __('Email', 'mom-booking-system'),
                    'max_length' => 255
                ],
                'phone' => [
                    'max_length' => 20,
                    'label' => __('Telefon', 'mom-booking-system')
                ],
                'child_birth_date' => [
                    'type' => 'date',
                    'label' => __('Datum narození dítěte', 'mom-booking-system')
                ]
            ],
        ];

        // Update course uses same rules as create
        $rules['update_course'] = $rules['create_course'];
        $rules['update_user'] = $rules['create_user'];

        return $rules[$action] ?? [];
    }

    /**
     * Validate date format
     */
    private function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate time format
     */
    private function is_valid_time($time) {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    /**
     * Sanitize form data
     */
    public function sanitize_form_data($action, $data) {
        $sanitized = [];
        $sanitization_rules = $this->get_sanitization_rules($action);

        foreach ($data as $key => $value) {
            if (isset($sanitization_rules[$key])) {
                $rule = $sanitization_rules[$key];

                switch ($rule) {
                    case 'text':
                        $sanitized[$key] = sanitize_text_field($value);
                        break;
                    case 'textarea':
                        $sanitized[$key] = sanitize_textarea_field($value);
                        break;
                    case 'email':
                        $sanitized[$key] = sanitize_email($value);
                        break;
                    case 'int':
                        $sanitized[$key] = intval($value);
                        break;
                    case 'float':
                        $sanitized[$key] = floatval($value);
                        break;
                    case 'url':
                        $sanitized[$key] = esc_url_raw($value);
                        break;
                    default:
                        $sanitized[$key] = sanitize_text_field($value);
                }
            } else {
                // Default sanitization
                if (is_string($value)) {
                    $sanitized[$key] = sanitize_text_field($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get sanitization rules for specific action
     */
    private function get_sanitization_rules($action) {
        $rules = [
            'create_course' => [
                'title' => 'text',
                'description' => 'textarea',
                'start_date' => 'text',
                'lesson_count' => 'int',
                'day_of_week' => 'int',
                'start_time' => 'text',
                'lesson_duration' => 'int',
                'max_capacity' => 'int',
                'price' => 'float',
                'status' => 'text'
            ],

            'create_user' => [
                'name' => 'text',
                'email' => 'email',
                'phone' => 'text',
                'child_name' => 'text',
                'child_birth_date' => 'text',
                'emergency_contact' => 'text',
                'notes' => 'textarea'
            ],

            'create_booking' => [
                'lesson_id' => 'int',
                'customer_id' => 'int',
                'customer_name' => 'text',
                'customer_email' => 'email',
                'customer_phone' => 'text',
                'notes' => 'textarea'
            ],

            'update_lesson' => [
                'lesson_id' => 'int',
                'title' => 'text',
                'description' => 'textarea',
                'date_time' => 'text',
                'max_capacity' => 'int',
                'status' => 'text'
            ]
        ];

        // Reuse rules for update actions
        $rules['update_course'] = $rules['create_course'];
        $rules['update_user'] = $rules['create_user'];
        $rules['update_booking'] = $rules['create_booking'];

        return $rules[$action] ?? [];
    }

    /**
     * Log form submission for audit trail
     */
    private function log_form_submission($action, $data, $result) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_data = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => $action,
            'page' => $_GET['page'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'result' => $result ? 'success' : 'failure',
            'data_keys' => array_keys($data), // Don't log actual data for privacy
        ];

        error_log('MOM Booking Form Submission: ' . json_encode($log_data));
    }

    /**
     * Get allowed actions for current user
     */
    public function get_allowed_actions() {
        $all_actions = array_keys($this->processor_map);

        // Filter actions based on user capabilities
        $allowed_actions = [];

        foreach ($all_actions as $action) {
            if ($this->can_user_perform_action($action)) {
                $allowed_actions[] = $action;
            }
        }

        return $allowed_actions;
    }

    /**
     * Check if user can perform specific action
     */
    private function can_user_perform_action($action) {
        // Basic capability check
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Action-specific checks can be added here
        switch ($action) {
            case 'delete_course':
            case 'delete_user':
                // Only admins can delete
                return current_user_can('administrator');

            default:
                return true;
        }
    }

    /**
     * Rate limiting for form submissions
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = "mom_form_rate_limit_{$user_id}";

        $submissions = get_transient($transient_key) ?: 0;

        // Allow max 20 submissions per minute
        if ($submissions >= 20) {
            wp_die(__('Příliš mnoho požadavků. Zkuste to prosím za chvíli.', 'mom-booking-system'));
        }

        set_transient($transient_key, $submissions + 1, MINUTE_IN_SECONDS);
    }

    /**
     * Handle AJAX form submissions
     */
    public function handle_ajax_forms() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nemáte oprávnění k této akci.', 'mom-booking-system'));
        }

        $action = $_POST['mom_action'] ?? '';

        if (empty($action)) {
            wp_send_json_error(__('Nespecifikovaná akce.', 'mom-booking-system'));
        }

        try {
            $this->process_form($action, $_POST);
            wp_send_json_success(__('Operace byla úspěšná.', 'mom-booking-system'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get form processing statistics
     */
    public function get_form_statistics() {
        // This could be expanded to track form submission stats
        return [
            'total_processors' => count($this->processor_map),
            'loaded_processors' => count($this->processors),
            'available_actions' => count($this->get_allowed_actions()),
        ];
    }

    /**
     * Clear cached processors (for testing)
     */
    public function clear_processors() {
        $this->processors = [];
        $this->form_processed = false;
    }
}
