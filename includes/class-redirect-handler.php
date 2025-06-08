<?php
/**
 * Redirect Handler
 * Single Responsibility: Handle admin redirects with messages
 */
class MomRedirectHandler {

    /**
     * Redirect with success message
     * @param string $page Admin page slug
     * @param string $message_key Success message key
     * @param array $extra_params Additional URL parameters
     */
    public function success($page, $message_key, $extra_params = []) {
        $params = array_merge(['page' => $page, 'success' => $message_key], $extra_params);
        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect with error message
     * @param string $page Admin page slug
     * @param string $error_key Error message key
     * @param array $extra_params Additional URL parameters
     */
    public function error($page, $error_key, $extra_params = []) {
        $params = array_merge(['page' => $page, 'error' => $error_key], $extra_params);
        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect to specific page with custom parameters
     * @param string $page Admin page slug
     * @param array $params URL parameters
     */
    public function to_page($page, $params = []) {
        $url_params = array_merge(['page' => $page], $params);
        $url = admin_url('admin.php?' . http_build_query($url_params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect back to referrer with message
     * @param string $message_type Type of message (success, error, info)
     * @param string $message_key Message key
     * @param array $extra_params Additional parameters
     */
    public function back($message_type, $message_key, $extra_params = []) {
        $referrer = wp_get_referer();

        if (!$referrer) {
            // Fallback to main admin page
            $this->to_page('mom-booking-admin', [$message_type => $message_key]);
            return;
        }

        // Parse referrer URL
        $url_parts = parse_url($referrer);
        parse_str($url_parts['query'] ?? '', $query_params);

        // Add message parameters
        $query_params[$message_type] = $message_key;
        $query_params = array_merge($query_params, $extra_params);

        // Rebuild URL
        $url = admin_url('admin.php?' . http_build_query($query_params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect to course detail page
     * @param int $course_id Course ID
     * @param string $message_type Message type (optional)
     * @param string $message_key Message key (optional)
     */
    public function to_course($course_id, $message_type = null, $message_key = null) {
        $params = ['page' => 'mom-booking-admin', 'course_id' => $course_id];

        if ($message_type && $message_key) {
            $params[$message_type] = $message_key;
        }

        $this->to_page('mom-booking-admin', $params);
    }

    /**
     * Redirect to user detail page
     * @param int $user_id User ID
     * @param string $message_type Message type (optional)
     * @param string $message_key Message key (optional)
     */
    public function to_user($user_id, $message_type = null, $message_key = null) {
        $params = ['id' => $user_id];

        if ($message_type && $message_key) {
            $params[$message_type] = $message_key;
        }

        $this->to_page('mom-user-detail', $params);
    }

    /**
     * Redirect to lesson detail page
     * @param int $lesson_id Lesson ID
     * @param string $message_type Message type (optional)
     * @param string $message_key Message key (optional)
     */
    public function to_lesson($lesson_id, $message_type = null, $message_key = null) {
        $params = ['id' => $lesson_id];

        if ($message_type && $message_key) {
            $params[$message_type] = $message_key;
        }

        $this->to_page('mom-lesson-detail', $params);
    }

    /**
     * Redirect with multiple messages
     * @param string $page Admin page slug
     * @param array $messages Array of message_type => message_key pairs
     * @param array $extra_params Additional parameters
     */
    public function with_messages($page, $messages, $extra_params = []) {
        $params = array_merge(['page' => $page], $messages, $extra_params);
        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect to course form (create/edit)
     * @param int|null $course_id Course ID for editing (null for create)
     * @param string $message_type Message type (optional)
     * @param string $message_key Message key (optional)
     */
    public function to_course_form($course_id = null, $message_type = null, $message_key = null) {
        $params = [];

        if ($course_id) {
            $params['edit'] = $course_id;
        }

        if ($message_type && $message_key) {
            $params[$message_type] = $message_key;
        }

        $this->to_page('mom-course-new', $params);
    }

    /**
     * Build admin URL with parameters
     * @param string $page Admin page slug
     * @param array $params URL parameters
     * @return string Complete admin URL
     */
    public function build_admin_url($page, $params = []) {
        $url_params = array_merge(['page' => $page], $params);
        return admin_url('admin.php?' . http_build_query($url_params));
    }

    /**
     * Safe redirect (checks for valid admin URLs only)
     * @param string $url URL to redirect to
     * @param array $params Additional parameters
     */
    public function safe_redirect($url, $params = []) {
        // Only allow admin URLs
        if (strpos($url, admin_url()) !== 0) {
            $url = admin_url('admin.php?page=mom-booking-admin');
        }

        // Add parameters if provided
        if (!empty($params)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect with custom success message count
     * @param string $page Admin page slug
     * @param string $action Action performed
     * @param int $count Number of items affected
     * @param array $extra_params Additional parameters
     */
    public function success_with_count($page, $action, $count, $extra_params = []) {
        $params = array_merge([
            'page' => $page,
            'success' => $action,
            'count' => $count
        ], $extra_params);

        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect after bulk action
     * @param string $page Admin page slug
     * @param string $action Bulk action performed
     * @param int $success_count Number of successful operations
     * @param int $error_count Number of failed operations
     */
    public function bulk_action($page, $action, $success_count, $error_count = 0) {
        $params = [
            'page' => $page,
            'bulk_action' => $action,
            'success_count' => $success_count
        ];

        if ($error_count > 0) {
            $params['error_count'] = $error_count;
        }

        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Get current page from request
     * @return string|null Current admin page slug
     */
    public function get_current_page() {
        return $_GET['page'] ?? null;
    }

    /**
     * Check if we're on a specific admin page
     * @param string $page Page slug to check
     * @return bool True if on specified page
     */
    public function is_page($page) {
        return $this->get_current_page() === $page;
    }

    /**
     * Check if we're on any of our plugin pages
     * @return bool True if on plugin page
     */
    public function is_plugin_page() {
        $current_page = $this->get_current_page();
        return $current_page && strpos($current_page, 'mom-') === 0;
    }
}
