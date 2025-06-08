<?php
/**
 * Admin Menu Manager
 * Single Responsibility: ONLY menu registration and routing to page classes
 */
class MomAdminMenuManager {

    private $container;
    private $menu_pages = [];

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        add_action('admin_menu', [$this, 'register_menus']);
    }

    /**
     * Register all admin menus
     */
    public function register_menus() {
        // Main menu page
        $this->menu_pages['main'] = add_menu_page(
            __('Kurzy maminek', 'mom-booking-system'),
            __('Kurzy maminek', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            [$this, 'render_courses_page'],
            'dashicons-groups',
            30
        );

        // Courses submenu (same as main page)
        $this->menu_pages['courses'] = add_submenu_page(
            'mom-booking-admin',
            __('Přehled kurzů', 'mom-booking-system'),
            __('Přehled kurzů', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            [$this, 'render_courses_page']
        );

        // New course page
        $this->menu_pages['new_course'] = add_submenu_page(
            'mom-booking-admin',
            __('Nový kurz', 'mom-booking-system'),
            __('Nový kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-new',
            [$this, 'render_course_form_page']
        );

        // Users page
        $this->menu_pages['users'] = add_submenu_page(
            'mom-booking-admin',
            __('Uživatelé', 'mom-booking-system'),
            __('Uživatelé', 'mom-booking-system'),
            'manage_options',
            'mom-users',
            [$this, 'render_users_page']
        );

        // Bookings page
        $this->menu_pages['bookings'] = add_submenu_page(
            'mom-booking-admin',
            __('Rezervace', 'mom-booking-system'),
            __('Rezervace', 'mom-booking-system'),
            'manage_options',
            'mom-bookings',
            [$this, 'render_bookings_page']
        );

        // Hidden detail pages (not shown in menu)
        $this->register_hidden_pages();
    }

    /**
     * Register hidden pages (detail pages not shown in menu)
     */
    private function register_hidden_pages() {
        // User detail page
        $this->menu_pages['user_detail'] = add_submenu_page(
            null, // Hidden from menu
            __('Detail uživatele', 'mom-booking-system'),
            __('Detail uživatele', 'mom-booking-system'),
            'manage_options',
            'mom-user-detail',
            [$this, 'render_user_detail_page']
        );

        // Lesson detail page
        $this->menu_pages['lesson_detail'] = add_submenu_page(
            null, // Hidden from menu
            __('Detail lekce', 'mom-booking-system'),
            __('Detail lekce', 'mom-booking-system'),
            'manage_options',
            'mom-lesson-detail',
            [$this, 'render_lesson_detail_page']
        );

        // Course registration page
        $this->menu_pages['course_registration'] = add_submenu_page(
            null, // Hidden from menu
            __('Registrace na kurz', 'mom-booking-system'),
            __('Registrace na kurz', 'mom-booking-system'),
            'manage_options',
            'mom-course-registration',
            [$this, 'render_course_registration_page']
        );
    }

    /**
     * Render courses overview page
     */
    public function render_courses_page() {
        try {
            $courses_page = $this->container->get('courses_page');
            $courses_page->render();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání stránky kurzů.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render course form page (new/edit)
     */
    public function render_course_form_page() {
        try {
            $courses_page = $this->container->get('courses_page');
            $courses_page->render_form();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání formuláře kurzu.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render users page
     */
    public function render_users_page() {
        try {
            $users_page = $this->container->get('users_page');
            $users_page->render();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání stránky uživatelů.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render user detail page
     */
    public function render_user_detail_page() {
        try {
            $users_page = $this->container->get('users_page');
            $users_page->render_detail();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání detailu uživatele.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render bookings page
     */
    public function render_bookings_page() {
        try {
            $bookings_page = $this->container->get('bookings_page');
            $bookings_page->render();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání stránky rezervací.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render lesson detail page
     */
    public function render_lesson_detail_page() {
        try {
            $lessons_page = $this->container->get('lessons_page');
            $lessons_page->render_detail();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání detailu lekce.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render course registration page
     */
    public function render_course_registration_page() {
        try {
            $courses_page = $this->container->get('courses_page');
            $courses_page->render_registration();
        } catch (Exception $e) {
            $this->render_error_page(__('Chyba při načítání registrace na kurz.', 'mom-booking-system'), $e);
        }
    }

    /**
     * Render error page when page class fails
     */
    private function render_error_page($message, Exception $exception = null) {
        echo '<div class="wrap">';
        echo '<h1>' . __('Chyba', 'mom-booking-system') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';

        if (defined('WP_DEBUG') && WP_DEBUG && $exception) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Debug info:</strong></p>';
            echo '<pre>' . esc_html($exception->getMessage()) . '</pre>';
            echo '<pre>' . esc_html($exception->getTraceAsString()) . '</pre>';
            echo '</div>';
        }

        echo '<p><a href="' . admin_url('admin.php?page=mom-booking-admin') . '" class="button">' .
             __('Zpět na hlavní stránku', 'mom-booking-system') . '</a></p>';
        echo '</div>';
    }

    /**
     * Get menu page hook
     */
    public function get_page_hook($page_key) {
        return $this->menu_pages[$page_key] ?? null;
    }

    /**
     * Get all registered menu pages
     */
    public function get_menu_pages() {
        return $this->menu_pages;
    }

    /**
     * Check if current page is our plugin page
     */
    public function is_plugin_page() {
        $current_page = $_GET['page'] ?? '';
        return strpos($current_page, 'mom-') === 0;
    }

    /**
     * Get current plugin page slug
     */
    public function get_current_page() {
        if (!$this->is_plugin_page()) {
            return null;
        }

        return $_GET['page'] ?? null;
    }

    /**
     * Add custom admin menu styling
     */
    public function add_menu_styles() {
        if (!$this->is_plugin_page()) {
            return;
        }

        ?>
        <style>
        .mom-booking-admin-page .wrap h1 {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mom-booking-admin-page .wrap h1::before {
            content: "\f307";
            font-family: dashicons;
            color: #0073aa;
        }

        .mom-booking-page-header {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .mom-booking-breadcrumb {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .mom-booking-breadcrumb a {
            color: #0073aa;
            text-decoration: none;
        }

        .mom-booking-breadcrumb a:hover {
            text-decoration: underline;
        }
        </style>
        <?php
    }

    /**
     * Add admin body classes for styling
     */
    public function add_admin_body_class($classes) {
        if ($this->is_plugin_page()) {
            $classes .= ' mom-booking-admin-page';

            $current_page = $this->get_current_page();
            if ($current_page) {
                $classes .= ' mom-booking-page-' . str_replace('mom-', '', $current_page);
            }
        }

        return $classes;
    }

    /**
     * Render page header with breadcrumbs
     */
    public function render_page_header($title, $breadcrumbs = []) {
        $template_renderer = $this->container->get('template_renderer');

        $header_data = [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'current_page' => $this->get_current_page(),
        ];

        $template_renderer->render('admin/partials/page-header', $header_data);
    }

    /**
     * Get breadcrumbs for current page
     */
    public function get_page_breadcrumbs() {
        $current_page = $this->get_current_page();

        $breadcrumbs_map = [
            'mom-booking-admin' => [
                ['title' => __('Kurzy maminek', 'mom-booking-system'), 'url' => null]
            ],
            'mom-course-new' => [
                ['title' => __('Kurzy maminek', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-booking-admin')],
                ['title' => __('Nový kurz', 'mom-booking-system'), 'url' => null]
            ],
            'mom-users' => [
                ['title' => __('Kurzy maminek', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-booking-admin')],
                ['title' => __('Uživatelé', 'mom-booking-system'), 'url' => null]
            ],
            'mom-user-detail' => [
                ['title' => __('Kurzy maminek', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-booking-admin')],
                ['title' => __('Uživatelé', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-users')],
                ['title' => __('Detail uživatele', 'mom-booking-system'), 'url' => null]
            ],
            'mom-bookings' => [
                ['title' => __('Kurzy maminek', 'mom-booking-system'), 'url' => admin_url('admin.php?page=mom-booking-admin')],
                ['title' => __('Rezervace', 'mom-booking-system'), 'url' => null]
            ],
        ];

        return $breadcrumbs_map[$current_page] ?? [];
    }
}
