<?php
/**
 * Frontend Loader
 * Single Responsibility: Load and initialize frontend components
 */
class MomFrontendLoader {

    private $container;
    private $frontend_components = [];

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
    }

    /**
     * Initialize frontend components
     */
    public function init() {
        // Only load frontend components on frontend
        if (is_admin()) {
            return;
        }

        $this->load_frontend_files();
        $this->register_frontend_bindings();
        $this->init_frontend_components();
        $this->init_frontend_hooks();
    }

    /**
     * Load frontend-specific files
     */
    private function load_frontend_files() {
        $frontend_files = [
            'includes/frontend/class-shortcodes.php',
            'includes/frontend/class-frontend-ajax.php',
            'includes/frontend/class-public-api.php',
            'includes/frontend/class-booking-form.php',
            'includes/frontend/class-calendar-widget.php',
        ];

        foreach ($frontend_files as $file) {
            $file_path = MOM_BOOKING_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Register frontend-specific service bindings
     */
    private function register_frontend_bindings() {
        $this->container->bind('shortcodes', 'MomBookingShortcodes');
        $this->container->bind('frontend_ajax', 'MomBookingFrontendAjax');
        $this->container->bind('public_api', 'MomBookingPublicApi');
        $this->container->bind('booking_form', 'MomBookingForm');
        $this->container->bind('calendar_widget', 'MomCalendarWidget');
    }

    /**
     * Initialize frontend components
     */
    private function init_frontend_components() {
        // Core frontend components that should be initialized immediately
        $core_components = [
            'shortcodes',
            'frontend_ajax',
            'public_api',
        ];

        foreach ($core_components as $component) {
            try {
                $this->frontend_components[$component] = $this->container->get($component);
            } catch (Exception $e) {
                error_log("Failed to initialize frontend component '{$component}': " . $e->getMessage());
            }
        }
    }

    /**
     * Initialize frontend-specific hooks
     */
    private function init_frontend_hooks() {
        // Widget registration
        add_action('widgets_init', [$this, 'register_widgets']);

        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Template hooks
        add_filter('template_include', [$this, 'template_include']);

        // Query vars for custom endpoints
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Rewrite rules for pretty URLs
        add_action('init', [$this, 'add_rewrite_rules']);
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        try {
            $calendar_widget = $this->container->get('calendar_widget');
            register_widget($calendar_widget);
        } catch (Exception $e) {
            error_log("Failed to register calendar widget: " . $e->getMessage());
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        try {
            $public_api = $this->container->get('public_api');
            $public_api->register_routes();
        } catch (Exception $e) {
            error_log("Failed to register REST API routes: " . $e->getMessage());
        }
    }

    /**
     * Handle custom template inclusion
     */
    public function template_include($template) {
        // Check if we're on a booking-related page
        if (get_query_var('mom_booking_action')) {
            $action = get_query_var('mom_booking_action');
            $custom_template = $this->get_custom_template($action);

            if ($custom_template && file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'mom_booking_action';
        $vars[] = 'mom_booking_id';
        $vars[] = 'mom_course_id';
        $vars[] = 'mom_lesson_id';

        return $vars;
    }

    /**
     * Add rewrite rules for pretty URLs
     */
    public function add_rewrite_rules() {
        // Booking action URLs
        add_rewrite_rule(
            '^booking/([^/]+)/?$',
            'index.php?mom_booking_action=$matches[1]',
            'top'
        );

        // Course URLs
        add_rewrite_rule(
            '^course/([0-9]+)/?$',
            'index.php?mom_booking_action=course&mom_course_id=$matches[1]',
            'top'
        );

        // Lesson URLs
        add_rewrite_rule(
            '^lesson/([0-9]+)/?$',
            'index.php?mom_booking_action=lesson&mom_lesson_id=$matches[1]',
            'top'
        );
    }

    /**
     * Get custom template for booking actions
     */
    private function get_custom_template($action) {
        $templates = [
            'booking' => 'booking-form.php',
            'course' => 'course-detail.php',
            'lesson' => 'lesson-detail.php',
            'confirmation' => 'booking-confirmation.php',
            'cancellation' => 'booking-cancellation.php',
        ];

        if (!isset($templates[$action])) {
            return null;
        }

        $template_file = $templates[$action];

        // Check theme first
        $theme_template = get_template_directory() . '/mom-booking/' . $template_file;
        if (file_exists($theme_template)) {
            return $theme_template;
        }

        // Check plugin templates
        $plugin_template = MOM_BOOKING_PLUGIN_DIR . 'templates/frontend/' . $template_file;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return null;
    }

    /**
     * Get initialized frontend component
     */
    public function get_component($component_name) {
        if (isset($this->frontend_components[$component_name])) {
            return $this->frontend_components[$component_name];
        }

        // Try to initialize component if not already done
        try {
            $this->frontend_components[$component_name] = $this->container->get($component_name);
            return $this->frontend_components[$component_name];
        } catch (Exception $e) {
            error_log("Failed to get frontend component '{$component_name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if component is loaded
     */
    public function has_component($component_name) {
        return isset($this->frontend_components[$component_name]);
    }

    /**
     * Get all loaded components
     */
    public function get_loaded_components() {
        return array_keys($this->frontend_components);
    }

    /**
     * Handle frontend form submissions
     */
    public function handle_frontend_forms() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['mom_frontend_action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['mom_frontend_action']);

        try {
            switch ($action) {
                case 'create_booking':
                    $this->handle_frontend_booking($_POST);
                    break;

                case 'cancel_booking':
                    $this->handle_frontend_cancellation($_POST);
                    break;

                default:
                    throw new Exception("Unknown frontend action: {$action}");
            }
        } catch (Exception $e) {
            // Handle error - could redirect to error page or show message
            $this->handle_frontend_error($e->getMessage());
        }
    }

    /**
     * Handle frontend booking submission
     */
    private function handle_frontend_booking($data) {
        // Verify nonce
        if (!wp_verify_nonce($data['_wpnonce'] ?? '', 'mom_booking_nonce')) {
            throw new Exception(__('Bezpečnostní kontrola selhala.', 'mom-booking-system'));
        }

        $booking_manager = $this->container->get('booking_manager');
        $result = $booking_manager->create_booking($data);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Redirect to confirmation page
        $confirmation_url = add_query_arg([
            'mom_booking_action' => 'confirmation',
            'booking_id' => $result,
        ], home_url('/'));

        wp_redirect($confirmation_url);
        exit;
    }

    /**
     * Handle frontend booking cancellation
     */
    private function handle_frontend_cancellation($data) {
        // Verify nonce
        if (!wp_verify_nonce($data['_wpnonce'] ?? '', 'mom_booking_nonce')) {
            throw new Exception(__('Bezpečnostní kontrola selhala.', 'mom-booking-system'));
        }

        $booking_id = intval($data['booking_id'] ?? 0);
        $customer_email = sanitize_email($data['customer_email'] ?? '');

        if (!$booking_id || !$customer_email) {
            throw new Exception(__('Neplatné parametry.', 'mom-booking-system'));
        }

        // Verify booking ownership
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mom_bookings WHERE id = %d AND customer_email = %s",
            $booking_id, $customer_email
        ));

        if (!$booking) {
            throw new Exception(__('Rezervace nebyla nalezena nebo nemáte oprávnění ji zrušit.', 'mom-booking-system'));
        }

        $booking_manager = $this->container->get('booking_manager');
        $success = $booking_manager->cancel_booking($booking_id);

        if (!$success) {
            throw new Exception(__('Chyba při rušení rezervace.', 'mom-booking-system'));
        }

        // Redirect to cancellation confirmation
        $confirmation_url = add_query_arg([
            'mom_booking_action' => 'cancellation',
            'booking_id' => $booking_id,
        ], home_url('/'));

        wp_redirect($confirmation_url);
        exit;
    }

    /**
     * Handle frontend errors
     */
    private function handle_frontend_error($message) {
        // Store error message for display
        set_transient('mom_booking_error_' . session_id(), $message, 300);

        // Redirect back to form or error page
        $error_url = add_query_arg([
            'mom_booking_action' => 'error',
            'error_id' => session_id(),
        ], wp_get_referer() ?: home_url('/'));

        wp_redirect($error_url);
        exit;
    }

    /**
     * Get frontend error message
     */
    public function get_frontend_error($error_id) {
        $error_message = get_transient('mom_booking_error_' . $error_id);
        if ($error_message) {
            delete_transient('mom_booking_error_' . $error_id);
            return $error_message;
        }
        return null;
    }

    /**
     * Enqueue conditional frontend assets
     */
    public function conditional_enqueue_assets() {
        global $post;

        // Only enqueue if shortcodes are present or on booking pages
        $should_enqueue = false;

        if (is_a($post, 'WP_Post')) {
            $shortcodes = ['mom_booking_calendar', 'mom_course_list', 'mom_booking_form'];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $should_enqueue = true;
                    break;
                }
            }
        }

        // Check for booking action pages
        if (get_query_var('mom_booking_action')) {
            $should_enqueue = true;
        }

        if ($should_enqueue) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Enqueue frontend assets
     */
    private function enqueue_frontend_assets() {
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

        // Localize script for AJAX and configuration
        wp_localize_script('mom-booking-frontend', 'momBooking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('mom-booking/v1/'),
            'nonce' => wp_create_nonce('mom_booking_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'loading' => __('Načítání...', 'mom-booking-system'),
                'error' => __('Došlo k chybě. Zkuste to prosím znovu.', 'mom-booking-system'),
                'success' => __('Rezervace byla úspěšně vytvořena!', 'mom-booking-system'),
                'confirmCancel' => __('Opravdu chcete zrušit tuto rezervaci?', 'mom-booking-system'),
                'pleaseWait' => __('Prosím čekejte...', 'mom-booking-system'),
                'tryAgain' => __('Zkuste to znovu', 'mom-booking-system'),
            ],
            'settings' => [
                'dateFormat' => get_option('mom_booking_date_format', 'd.m.Y'),
                'timeFormat' => get_option('mom_booking_time_format', 'H:i'),
                'currency' => 'Kč',
                'timezone' => get_option('mom_booking_time_zone', 'Europe/Prague'),
            ]
        ]);
    }

    /**
     * Add frontend body classes
     */
    public function add_frontend_body_classes($classes) {
        if (get_query_var('mom_booking_action')) {
            $classes[] = 'mom-booking-page';
            $classes[] = 'mom-booking-' . get_query_var('mom_booking_action');
        }

        global $post;
        if (is_a($post, 'WP_Post')) {
            $shortcodes = ['mom_booking_calendar', 'mom_course_list', 'mom_booking_form'];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $classes[] = 'has-mom-booking-shortcode';
                    $classes[] = 'has-' . str_replace('_', '-', $shortcode);
                }
            }
        }

        return $classes;
    }

    /**
     * Cleanup frontend components (for testing)
     */
    public function cleanup() {
        $this->frontend_components = [];
    }

    /**
     * Get frontend statistics
     */
    public function get_frontend_statistics() {
        return [
            'loaded_components' => count($this->frontend_components),
            'available_components' => count($this->container->get_services()),
            'shortcodes_registered' => $this->has_component('shortcodes'),
            'ajax_handler_active' => $this->has_component('frontend_ajax'),
            'rest_api_active' => $this->has_component('public_api'),
        ];
    }
}
