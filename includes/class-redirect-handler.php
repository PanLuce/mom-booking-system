<?php
/**
 * Basic Redirect Handler - Working Version
 */
class MomRedirectHandler {

    /**
     * Redirect with success message
     */
    public function success($page, $message_key, $extra_params = []) {
        $params = array_merge(['page' => $page, 'success' => $message_key], $extra_params);
        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect with error message
     */
    public function error($page, $error_key, $extra_params = []) {
        $params = array_merge(['page' => $page, 'error' => $error_key], $extra_params);
        $url = admin_url('admin.php?' . http_build_query($params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect to specific page
     */
    public function to_page($page, $params = []) {
        $url_params = array_merge(['page' => $page], $params);
        $url = admin_url('admin.php?' . http_build_query($url_params));

        wp_redirect($url);
        exit;
    }

    /**
     * Redirect back to referrer
     */
    public function back($message_type, $message_key, $extra_params = []) {
        $referrer = wp_get_referer();

        if (!$referrer) {
            $this->to_page('mom-booking-admin', [$message_type => $message_key]);
            return;
        }

        $url_parts = parse_url($referrer);
        parse_str($url_parts['query'] ?? '', $query_params);
        $query_params[$message_type] = $message_key;
        $query_params = array_merge($query_params, $extra_params);

        $url = admin_url('admin.php?' . http_build_query($query_params));
        wp_redirect($url);
        exit;
    }

    /**
     * Build admin URL
     */
    public function build_admin_url($page, $params = []) {
        $url_params = array_merge(['page' => $page], $params);
        return admin_url('admin.php?' . http_build_query($url_params));
    }

    /**
     * Get current page
     */
    public function get_current_page() {
        return $_GET['page'] ?? null;
    }

    /**
     * Check if on plugin page
     */
    public function is_plugin_page() {
        $current_page = $this->get_current_page();
        return $current_page && strpos($current_page, 'mom-') === 0;
    }
}
