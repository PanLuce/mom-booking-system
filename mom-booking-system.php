<?php
/**
 * Plugin Name: Mom Booking System
 * Description: Rezervační systém pro lekce maminek s dětmi
 * Version: 2.4
 * Author: Lukáš Vitala
 * Text Domain: mom-booking-system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MOM_BOOKING_VERSION', '2.4');
define('MOM_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOM_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class - only coordination and initialization
 */
class MomBookingSystem {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
        $this->init_form_handlers();
    }

    private function init_form_handlers() {
        if (is_admin()) {
            add_action('admin_init', [$this, 'handle_admin_actions']);
        }
    }

    // zpracování formulářů
    public function handle_admin_actions() {
        if (isset($_POST['mom_action']) && wp_verify_nonce($_POST['_wpnonce'], 'mom_admin_action')) {
            switch ($_POST['mom_action']) {
                case 'create_course':
                    $course_id = MomCourseManager::get_instance()->create_course($_POST);
                    if ($course_id) {
                        wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_created=1'));
                        exit;
                    }
                    break;
                case 'update_course':
                    $course_id = intval($_POST['course_id']);
                    $success = MomCourseManager::get_instance()->update_course($course_id, $_POST);
                    if ($success) {
                        wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_updated=1'));
                        exit;
                    }
                    break;
                case 'create_user':
                    $user_id = MomUserManager::get_instance()->create_user($_POST);
                    if (!is_wp_error($user_id)) {
                        wp_redirect(admin_url('admin.php?page=mom-users&user_created=1'));
                        exit;
                    }
                    break;
            }
        }
    }

    private function load_dependencies() {
        // Core classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-user-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-booking-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-lesson-manager.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/class-course-registration-manager.php';

        // Admin classes (only in admin)
        if (is_admin()) {
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-pages.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
            require_once MOM_BOOKING_PLUGIN_DIR . 'includes/admin/class-admin-ajax.php';
        }

        // Frontend classes
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
        require_once MOM_BOOKING_PLUGIN_DIR . 'includes/frontend/class-frontend-ajax.php';
    }

    private function init_components() {
        // Initialize managers
        MomCourseManager::get_instance();
        MomUserManager::get_instance();
        MomBookingManager::get_instance();
        MomLessonManager::get_instance();
        MomCourseRegistrationManager::get_instance();

        // Initialize admin (only in admin)
        if (is_admin()) {
            MomBookingAdminPages::get_instance();
            MomBookingAdminMenu::get_instance();
            MomBookingAdminAjax::get_instance();
        }

        // Initialize frontend
        MomBookingShortcodes::get_instance();
        MomBookingFrontendAjax::get_instance();
    }

    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function activate() {
        MomBookingDatabase::create_tables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function enqueue_frontend_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'mom_booking_calendar') ||
            has_shortcode($post->post_content, 'mom_course_list')
        )) {
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

            wp_localize_script('mom-booking-frontend', 'momBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_booking_nonce'),
                'strings' => [
                    'loading' => __('Načítání...', 'mom-booking-system'),
                    'error' => __('Došlo k chybě. Zkuste to prosím znovu.', 'mom-booking-system'),
                    'success' => __('Rezervace byla úspěšně vytvořena!', 'mom-booking-system'),
                    'confirmCancel' => __('Opravdu chcete zrušit tuto rezervaci?', 'mom-booking-system')
                ]
            ]);
        }
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mom-') !== false) {
            wp_enqueue_script(
                'mom-booking-admin',
                MOM_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                MOM_BOOKING_VERSION,
                true
            );

            wp_enqueue_style(
                'mom-booking-admin',
                MOM_BOOKING_PLUGIN_URL . 'assets/css/admin.css',
                [],
                MOM_BOOKING_VERSION
            );

            wp_localize_script('mom-booking-admin', 'momBookingAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_admin_nonce'),
                'strings' => [
                    'confirmDelete' => __('Opravdu chcete smazat tento záznam?', 'mom-booking-system'),
                    'loading' => __('Načítání...', 'mom-booking-system')
                ]
            ]);
        }
    }
}

// Initialize plugin
MomBookingSystem::get_instance();

// Plugin info for WordPress
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

/**
 * NEW ADMIN PAGE FUNCTIONS
 */
function mom_booking_lesson_detail_page() {
    if (!isset($_GET['id'])) {
        wp_die(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
    }

    $lesson_id = intval($_GET['id']);
    $lesson_data = MomLessonManager::get_instance()->get_lesson_schedule($lesson_id);

    if (!$lesson_data) {
        wp_die(__('Lekce nebyla nalezena.', 'mom-booking-system'));
    }

    $available_users = MomUserManager::get_instance()->get_available_users_for_lesson($lesson_id);

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/lesson-detail.php';
}

function mom_booking_user_detail_page() {
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

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/user-detail.php';
}

function mom_booking_course_registration_page() {
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

    include MOM_BOOKING_PLUGIN_DIR . 'templates/admin/course-registration.php';
}

/**
 * ROZŠÍŘENÉ ADMIN NOTICES
 */
add_action('admin_notices', 'mom_booking_display_extended_notices');

function mom_booking_display_extended_notices() {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'mom-') !== 0) {
        return;
    }

    // NEW SUCCESS MESSAGES
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

    // EXTENDED ERROR MESSAGES
    if (isset($_GET['error'])) {
        $extended_error_messages = [
            'lesson_not_found' => __('Lekce nebyla nalezena.', 'mom-booking-system'),
            'user_not_found' => __('Uživatel nebyl nalezen.', 'mom-booking-system'),
            'lesson_full' => __('Lekce je již plně obsazena.', 'mom-booking-system'),
            'already_booked' => __('Uživatel je již na tuto lekci přihlášen.', 'mom-booking-system'),
            'already_registered' => __('Uživatel je již na kurz registrován.', 'mom-booking-system'),
            'has_bookings' => __('Nelze smazat - existují aktivní rezervace.', 'mom-booking-system'),
            'duplicate_email' => __('Email je již používán jiným uživatelem.', 'mom-booking-system'),
            'update_failed' => __('Chyba při aktualizaci záznamu.', 'mom-booking-system')
        ];

        $error = $_GET['error'];
        if (isset($extended_error_messages[$error])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . $extended_error_messages[$error] . '</p></div>';
        }
    }
}

function mom_booking_admin_page() {
    global $wpdb;

    // Zprávy o úspěchu
    if (isset($_GET['course_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Kurz byl úspěšně vytvořen a lekce vygenerovány!</p></div>';
    }
    if (isset($_GET['course_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Kurz byl úspěšně aktualizován!</p></div>';
    }

    $courses = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mom_courses ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Kurzy maminek</h1>

        <?php if (empty($courses)): ?>
            <div class="card">
                <h2>Začněte s prvním kurzem</h2>
                <p>Zatím nemáte žádné kurzy. <a href="<?php echo admin_url('admin.php?page=mom-course-new'); ?>" class="button button-primary">Vytvořte první kurz</a></p>
            </div>
        <?php else: ?>
            <h2>Přehled kurzů</h2>
            <table class="wp-list-table widefat fixed striped" id="courses-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th>
                        <th>Název kurzu</th>
                        <th>Začátek</th>
                        <th>Den/Čas</th>
                        <th>Lekcí</th>
                        <th>Kapacita</th>
                        <th>Status</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = ['', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
                    foreach ($courses as $course):
                        $lesson_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}mom_lessons WHERE course_id = %d",
                            $course->id
                        ));
                        $total_bookings = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b
                             JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
                             WHERE l.course_id = %d AND b.booking_status = 'confirmed'",
                            $course->id
                        ));
                    ?>
                        <tr class="course-row" data-course-id="<?php echo $course->id; ?>">
                            <td>
                                <button class="toggle-lessons button-link" data-course-id="<?php echo $course->id; ?>">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </button>
                            </td>
                            <td><strong><?php echo esc_html($course->title); ?></strong></td>
                            <td><?php echo date('d.m.Y', strtotime($course->start_date)); ?></td>
                            <td><?php echo $days[$course->day_of_week] . ' ' . date('H:i', strtotime($course->start_time)); ?></td>
                            <td><?php echo $lesson_count . '/' . $course->lesson_count; ?></td>
                            <td><?php echo $total_bookings . '/' . ($course->max_capacity * $lesson_count); ?></td>
                            <td><span class="status-<?php echo $course->status; ?>"><?php echo ucfirst($course->status); ?></span></td>
                            <td>
                                <a href="?page=mom-course-new&edit=<?php echo $course->id; ?>" class="button button-small">Upravit</a>
                            </td>
                        </tr>
                        <tr class="lessons-row" id="lessons-<?php echo $course->id; ?>" style="display: none;">
                            <td colspan="8">
                                <div class="lessons-container">
                                    <div class="loading">Načítání lekcí...</div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($) {
            $('.toggle-lessons').click(function() {
                var courseId = $(this).data('course-id');
                var lessonsRow = $('#lessons-' + courseId);
                var icon = $(this).find('.dashicons');

                if (lessonsRow.is(':visible')) {
                    lessonsRow.hide();
                    icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                } else {
                    // Načti lekce přes AJAX
                    $.post(ajaxurl, {
                        action: 'toggle_course_lessons',
                        course_id: courseId,
                        nonce: '<?php echo wp_create_nonce('mom_admin_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            var html = '<div class="lessons-grid">';
                            response.data.lessons.forEach(function(lesson) {
                                var statusClass = lesson.status === 'cancelled' ? 'lesson-cancelled' : '';
                                var date = new Date(lesson.date_time);
                                html += '<div class="lesson-card ' + statusClass + '">';
                                html += '<div class="lesson-number">Lekce ' + lesson.lesson_number + '</div>';
                                html += '<div class="lesson-date">' + date.toLocaleDateString('cs-CZ') + '</div>';
                                html += '<div class="lesson-time">' + date.toLocaleTimeString('cs-CZ', {hour: '2-digit', minute:'2-digit'}) + '</div>';
                                html += '<div class="lesson-bookings">' + lesson.bookings_count + '/' + lesson.max_capacity + '</div>';
                                html += '<div class="lesson-status">' + (lesson.status === 'active' ? 'Aktivní' : 'Zrušena') + '</div>';
                                html += '</div>';
                            });
                            html += '</div>';
                            lessonsRow.find('.lessons-container').html(html);
                        }
                    });

                    lessonsRow.show();
                    icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                }
            });
        });
        </script>

        <style>
        .status-active { color: #46b450; font-weight: bold; }
        .status-inactive { color: #f56e28; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .toggle-lessons { border: none; background: none; cursor: pointer; padding: 5px; }
        .toggle-lessons:hover { background: #f0f0f1; border-radius: 3px; }
        .lessons-row { background: #f8f9fa; }
        .lessons-container { padding: 20px; }
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .lesson-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #0073aa;
        }
        .lesson-cancelled {
            border-left-color: #dc3232;
            background: #ffeaea;
        }
        .lesson-number { font-weight: bold; color: #23282d; }
        .lesson-date { color: #666; font-size: 0.9em; }
        .lesson-time { color: #666; font-size: 0.9em; }
        .lesson-bookings { color: #0073aa; font-weight: bold; margin-top: 5px; }
        .lesson-status { font-size: 0.8em; color: #666; }
        </style>
    </div>
    <?php
}

function mom_booking_new_course_page() {
    // Implementace z původního kódu - bude dlouhá, zkrátím pro přehlednost
    global $wpdb;

    // Kontrola editace
    $editing = false;
    $course = null;

    if (isset($_GET['edit'])) {
        $editing = true;
        $course_id = intval($_GET['edit']);
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mom_courses WHERE id = %d",
            $course_id
        ));

        if (!$course) {
            echo '<div class="notice notice-error"><p>Kurz nebyl nalezen.</p></div>';
            return;
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo $editing ? 'Upravit kurz: ' . esc_html($course->title) : 'Nový kurz'; ?></h1>

        <form method="post">
            <?php wp_nonce_field('mom_admin_action'); ?>
            <input type="hidden" name="mom_action" value="<?php echo $editing ? 'update_course' : 'create_course'; ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="course_id" value="<?php echo $course->id; ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="course_title">Název kurzu</label></th>
                    <td><input name="course_title" type="text" id="course_title" class="regular-text" required
                               value="<?php echo $editing ? esc_attr($course->title) : ''; ?>"
                               placeholder="Cvičení maminek s dětmi"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">Popis kurzu</label></th>
                    <td><textarea name="description" id="description" rows="3" class="large-text"
                                  placeholder="Krátký popis kurzu..."><?php echo $editing ? esc_textarea($course->description) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="start_date">Datum začátku</label></th>
                    <td><input name="start_date" type="date" id="start_date" required
                               value="<?php echo $editing ? $course->start_date : ''; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lesson_count">Počet lekcí</label></th>
                    <td><input name="lesson_count" type="number" id="lesson_count" min="1" max="52" required
                               value="<?php echo $editing ? $course->lesson_count : '10'; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="day_of_week">Den v týdnu</label></th>
                    <td>
                        <select name="day_of_week" id="day_of_week" required>
                            <option value="">Vyberte den...</option>
                            <?php
                            $days = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];
                            foreach ($days as $num => $name):
                                $selected = ($editing && $course->day_of_week == $num) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $num; ?>" <?php echo $selected; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="start_time">Čas začátku</label></th>
                    <td><input name="start_time" type="time" id="start_time" required
                               value="<?php echo $editing ? $course->start_time : '10:00'; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lesson_duration">Délka lekce (minuty)</label></th>
                    <td><input name="lesson_duration" type="number" id="lesson_duration" min="30" max="180" step="15"
                               value="<?php echo $editing ? $course->lesson_duration : '60'; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_capacity">Maximální kapacita</label></th>
                    <td><input name="max_capacity" type="number" id="max_capacity" min="1" max="50" required
                               value="<?php echo $editing ? $course->max_capacity : '10'; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="price">Cena kurzu (Kč)</label></th>
                    <td><input name="price" type="number" id="price" min="0" step="0.01"
                               value="<?php echo $editing ? $course->price : '0'; ?>"></td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                       value="<?php echo $editing ? 'Aktualizovat kurz' : 'Vytvořit kurz'; ?>">
                <a href="<?php echo admin_url('admin.php?page=mom-booking-admin'); ?>" class="button">Zpět na přehled</a>
            </p>
        </form>
    </div>
    <?php
}

function mom_booking_users_page() {
    global $wpdb;

    // Zprávy
    if (isset($_GET['user_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Uživatel byl úspěšně vytvořen!</p></div>';
    }
    if (isset($_GET['error']) && $_GET['error'] === 'duplicate_email') {
        echo '<div class="notice notice-error is-dismissible"><p>Uživatel s tímto emailem už existuje!</p></div>';
    }

    $users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mom_customers ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Uživatelé</h1>

        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="#" id="add-user-btn" class="button button-primary">Přidat nového uživatele</a>
            </div>
        </div>

        <!-- Formulář pro nového uživatele -->
        <div id="new-user-form" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>Nový uživatel</h2>
            <form method="post">
                <?php wp_nonce_field('mom_admin_action'); ?>
                <input type="hidden" name="mom_action" value="create_user">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_name">Jméno a příjmení</label></th>
                        <td><input name="user_name" type="text" id="user_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_email">Email</label></th>
                        <td><input name="user_email" type="email" id="user_email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_phone">Telefon</label></th>
                        <td><input name="user_phone" type="tel" id="user_phone" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="child_name">Jméno dítěte</label></th>
                        <td><input name="child_name" type="text" id="child_name" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="child_birth_date">Datum narození dítěte</label></th>
                        <td><input name="child_birth_date" type="date" id="child_birth_date"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="emergency_contact">Nouzový kontakt</label></th>
                        <td><input name="emergency_contact" type="text" id="emergency_contact" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes">Poznámky</label></th>
                        <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Vytvořit uživatele">
                    <button type="button" id="cancel-user-btn" class="button">Zrušit</button>
                </p>
            </form>
        </div>

        <?php if (!empty($users)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Jméno</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Dítě</th>
                        <th>Věk dítěte</th>
                        <th>Vytvořeno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $child_age = '';
                        if ($user->child_birth_date) {
                            $birth = new DateTime($user->child_birth_date);
                            $now = new DateTime();
                            $diff = $birth->diff($now);
                            $child_age = $diff->y > 0 ? $diff->y . ' let' : $diff->m . ' měsíců';
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($user->name); ?></strong></td>
                            <td><?php echo esc_html($user->email); ?></td>
                            <td><?php echo esc_html($user->phone ?: '-'); ?></td>
                            <td><?php echo esc_html($user->child_name ?: '-'); ?></td>
                            <td><?php echo $child_age ?: '-'; ?></td>
                            <td><?php echo date('d.m.Y', strtotime($user->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=mom-user-detail&id=' . $user->id); ?>" class="button button-small">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Zatím nemáte žádné uživatele.</p>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($) {
            $('#add-user-btn').click(function(e) {
                e.preventDefault();
                $('#new-user-form').slideDown();
                $(this).hide();
            });

            $('#cancel-user-btn').click(function() {
                $('#new-user-form').slideUp();
                $('#add-user-btn').show();
            });
        });
        </script>
    </div>
    <?php
}

function mom_booking_bookings_page() {
    global $wpdb;

    $bookings = $wpdb->get_results("
        SELECT b.*,
               l.title as lesson_title,
               l.date_time,
               c.title as course_title
        FROM {$wpdb->prefix}mom_bookings b
        JOIN {$wpdb->prefix}mom_lessons l ON b.lesson_id = l.id
        LEFT JOIN {$wpdb->prefix}mom_courses c ON l.course_id = c.id
        ORDER BY l.date_time DESC
    ");

    ?>
    <div class="wrap">
        <h1>Rezervace</h1>

        <?php if (!empty($bookings)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Email</th>
                        <th>Kurz</th>
                        <th>Lekce</th>
                        <th>Datum</th>
                        <th>Status</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?php echo esc_html($booking->customer_name); ?></strong></td>
                            <td><?php echo esc_html($booking->customer_email); ?></td>
                            <td><?php echo esc_html($booking->course_title); ?></td>
                            <td><?php echo esc_html($booking->lesson_title); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($booking->date_time)); ?></td>
                            <td>
                                <span class="status-<?php echo $booking->booking_status; ?>">
                                    <?php echo ucfirst($booking->booking_status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking->booking_status === 'confirmed'): ?>
                                    <button class="button button-small cancel-booking"
                                            data-booking-id="<?php echo $booking->id; ?>">
                                        Zrušit
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Zatím nejsou žádné rezervace.</p>
        <?php endif; ?>

        <style>
        .status-confirmed { color: #46b450; font-weight: bold; }
        .status-cancelled { color: #dc3545; font-weight: bold; }
        </style>
    </div>
    <?php
}
?>
