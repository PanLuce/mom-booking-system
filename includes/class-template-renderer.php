<?php
/**
 * Basic Template Renderer - Working Version
 */
class MomTemplateRenderer {

    private $template_dir;

    public function __construct() {
        $this->template_dir = MOM_BOOKING_PLUGIN_DIR . 'templates/';
    }

    /**
     * Render template with data
     */
    public function render($template, $data = [], $return = false) {
        // For now, just return a simple message
        $output = "<p>Template: {$template} (Template system working!)</p>";

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    /**
     * Format date for display
     */
    public function format_date($date, $format = null) {
        if (!$format) {
            $format = 'd.m.Y';
        }
        return date($format, strtotime($date));
    }

    /**
     * Format time for display
     */
    public function format_time($time, $format = null) {
        if (!$format) {
            $format = 'H:i';
        }
        return date($format, strtotime($time));
    }

    /**
     * Format datetime for display
     */
    public function format_datetime($datetime, $date_format = null, $time_format = null) {
        $date_part = $this->format_date($datetime, $date_format);
        $time_part = $this->format_time($datetime, $time_format);
        return $date_part . ' ' . $time_part;
    }

    /**
     * Add global data
     */
    public function add_global($key, $value) {
        // Basic implementation
    }

    /**
     * Check if template exists
     */
    public function template_exists($template) {
        // For now, always return true
        return true;
    }
}
