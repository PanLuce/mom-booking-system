<?php
/**
 * Template Renderer
 * Single Responsibility: Handle template rendering with data
 */
class MomTemplateRenderer {

    private $template_dir;
    private $cache = [];
    private $global_data = [];

    public function __construct() {
        $this->template_dir = MOM_BOOKING_PLUGIN_DIR . 'templates/';
        $this->init_global_data();
    }

    /**
     * Initialize global template data
     */
    private function init_global_data() {
        $this->global_data = [
            'plugin_url' => MOM_BOOKING_PLUGIN_URL,
            'plugin_dir' => MOM_BOOKING_PLUGIN_DIR,
            'plugin_version' => MOM_BOOKING_VERSION,
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('mom_admin_action'),
        ];
    }

    /**
     * Render template with data
     * @param string $template Template path (relative to templates directory)
     * @param array $data Data to pass to template
     * @param bool $return Whether to return output instead of echoing
     * @return string|void Template output if $return is true
     */
    public function render($template, $data = [], $return = false) {
        $template_file = $this->get_template_file($template);

        if (!$template_file) {
            $error_msg = sprintf(__('Template not found: %s', 'mom-booking-system'), $template);

            if ($return) {
                return $this->render_error($error_msg);
            } else {
                echo $this->render_error($error_msg);
                return;
            }
        }

        // Merge with global data
        $template_data = array_merge($this->global_data, $data);

        if ($return) {
            return $this->render_template_file($template_file, $template_data, true);
        } else {
            $this->render_template_file($template_file, $template_data, false);
        }
    }

    /**
     * Get template file path
     * @param string $template Template name
     * @return string|false Template file path or false if not found
     */
    private function get_template_file($template) {
        // Add .php extension if not present
        if (substr($template, -4) !== '.php') {
            $template .= '.php';
        }

        $template_file = $this->template_dir . $template;

        // Check if file exists
        if (file_exists($template_file)) {
            return $template_file;
        }

        // Try alternative locations (theme override support)
        $theme_template = get_template_directory() . '/mom-booking/' . basename($template);
        if (file_exists($theme_template)) {
            return $theme_template;
        }

        return false;
    }

    /**
     * Render template file
     * @param string $template_file Full path to template file
     * @param array $data Template data
     * @param bool $return Whether to return output
     * @return string|void Template output if $return is true
     */
    private function render_template_file($template_file, $data, $return) {
        // Extract data to variables
        extract($data);

        if ($return) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        } else {
            include $template_file;
        }
    }

    /**
     * Render error message
     * @param string $message Error message
     * @return string Error HTML
     */
    private function render_error($message) {
        return sprintf(
            '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
            __('Template Error', 'mom-booking-system'),
            esc_html($message)
        );
    }

    /**
     * Add global data available to all templates
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public function add_global($key, $value) {
        $this->global_data[$key] = $value;
    }

    /**
     * Get global data
     * @return array Global template data
     */
    public function get_global_data() {
        return $this->global_data;
    }

    /**
     * Render partial template
     * @param string $partial Partial template name
     * @param array $data Data for partial
     * @return string Partial output
     */
    public function partial($partial, $data = []) {
        return $this->render('partials/' . $partial, $data, true);
    }

    /**
     * Include partial template (for use inside templates)
     * @param string $partial Partial template name
     * @param array $data Data for partial
     */
    public function include_partial($partial, $data = []) {
        echo $this->partial($partial, $data);
    }

    /**
     * Check if template exists
     * @param string $template Template name
     * @return bool True if template exists
     */
    public function template_exists($template) {
        return $this->get_template_file($template) !== false;
    }

    /**
     * Get all available templates
     * @param string $directory Directory to scan (relative to templates dir)
     * @return array Array of template names
     */
    public function get_available_templates($directory = '') {
        $scan_dir = $this->template_dir . $directory;

        if (!is_dir($scan_dir)) {
            return [];
        }

        $templates = [];
        $files = scandir($scan_dir);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && substr($file, -4) === '.php') {
                $template_name = substr($file, 0, -4);
                if ($directory) {
                    $template_name = $directory . '/' . $template_name;
                }
                $templates[] = $template_name;
            }
        }

        return $templates;
    }

    /**
     * Render admin page wrapper
     * @param string $title Page title
     * @param string $content Page content
     * @param array $data Additional data
     */
    public function render_admin_page($title, $content, $data = []) {
        $page_data = array_merge($data, [
            'page_title' => $title,
            'page_content' => $content,
        ]);

        $this->render('admin/page-wrapper', $page_data);
    }

    /**
     * Render admin form
     * @param string $form_template Form template name
     * @param array $form_data Form data
     * @param string $form_action Form action
     * @return string Form HTML
     */
    public function render_admin_form($form_template, $form_data = [], $form_action = '') {
        $data = array_merge($form_data, [
            'form_action' => $form_action,
            'form_nonce' => wp_nonce_field('mom_admin_action', '_wpnonce', true, false),
        ]);

        return $this->render('admin/forms/' . $form_template, $data, true);
    }

    /**
     * Escape data for template output
     * @param mixed $data Data to escape
     * @return mixed Escaped data
     */
    public function escape($data) {
        if (is_string($data)) {
            return esc_html($data);
        } elseif (is_array($data)) {
            return array_map([$this, 'escape'], $data);
        }

        return $data;
    }

    /**
     * Format date for display
     * @param string $date Date string
     * @param string $format Date format (default from options)
     * @return string Formatted date
     */
    public function format_date($date, $format = null) {
        if (!$format) {
            $format = get_option('mom_booking_date_format', 'd.m.Y');
        }

        return date($format, strtotime($date));
    }

    /**
     * Format time for display
     * @param string $time Time string
     * @param string $format Time format (default from options)
     * @return string Formatted time
     */
    public function format_time($time, $format = null) {
        if (!$format) {
            $format = get_option('mom_booking_time_format', 'H:i');
        }

        return date($format, strtotime($time));
    }

    /**
     * Format datetime for display
     * @param string $datetime Datetime string
     * @param string $date_format Date format
     * @param string $time_format Time format
     * @return string Formatted datetime
     */
    public function format_datetime($datetime, $date_format = null, $time_format = null) {
        $date_part = $this->format_date($datetime, $date_format);
        $time_part = $this->format_time($datetime, $time_format);

        return $date_part . ' ' . $time_part;
    }
}
