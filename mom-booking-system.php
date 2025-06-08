<?php
/**
 * Plugin Name: Mom Booking System
 * Description: Rezervační systém pro lekce maminek s dětmi
 * Version: 2.3
 * Author: Vaše jméno
 * Text Domain: mom-booking-system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MOM_BOOKING_VERSION', '2.3');
define('MOM_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOM_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Admin menu registrace
 */
add_action('admin_menu', 'mom_booking_register_admin_menu');

function mom_booking_register_admin_menu() {
    add_menu_page(
        'Kurzy maminek',
        'Kurzy maminek',
        'manage_options',
        'mom-booking-admin',
        'mom_booking_admin_page',
        'dashicons-groups',
        30
    );

    add_submenu_page(
        'mom-booking-admin',
        'Nový kurz',
        'Nový kurz',
        'manage_options',
        'mom-course-new',
        'mom_booking_new_course_page'
    );

    add_submenu_page(
        'mom-booking-admin',
        'Uživatelé', // PŘEJMENOVÁNO
        'Uživatelé',
        'manage_options',
        'mom-users',
        'mom_booking_users_page'
    );

    add_submenu_page(
        'mom-booking-admin',
        'Rezervace',
        'Rezervace',
        'manage_options',
        'mom-bookings',
        'mom_booking_bookings_page'
    );
}

/**
 * Hlavní třída pluginu
 */
class MomBookingPlugin {

    private static $instance = null;
    private $form_processed = false; // OPRAVA DUPLIKÁTŮ

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_init', [$this, 'handle_admin_actions']);

        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('mom_booking_calendar', [$this, 'booking_calendar_shortcode']);
        add_shortcode('mom_course_list', [$this, 'course_list_shortcode']);

        // AJAX hooks
        add_action('wp_ajax_get_available_lessons', [$this, 'ajax_get_lessons']);
        add_action('wp_ajax_nopriv_get_available_lessons', [$this, 'ajax_get_lessons']);
        add_action('wp_ajax_book_lesson', [$this, 'ajax_book_lesson']);
        add_action('wp_ajax_nopriv_book_lesson', [$this, 'ajax_book_lesson']);
        add_action('wp_ajax_toggle_course_lessons', [$this, 'ajax_toggle_lessons']); // NOVÉ
    }

    public function init() {
        $this->load_textdomain();
    }

    private function load_textdomain() {
        load_plugin_textdomain(
            'mom-booking-system',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    public function handle_admin_actions() {
        // OPRAVA: Zabránit duplikátům
        if ($this->form_processed) {
            return;
        }

        if (isset($_POST['mom_action']) && wp_verify_nonce($_POST['_wpnonce'], 'mom_admin_action')) {
            $this->form_processed = true;

            switch ($_POST['mom_action']) {
                case 'create_course':
                    $this->create_course();
                    break;
                case 'update_course':
                    $this->update_course();
                    break;
                case 'create_user':
                    $this->create_user();
                    break;
            }
        }
    }

    private function create_course() {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'mom_courses',
            [
                'title' => sanitize_text_field($_POST['course_title']),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'lesson_count' => intval($_POST['lesson_count']),
                'day_of_week' => intval($_POST['day_of_week']),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'lesson_duration' => intval($_POST['lesson_duration'] ?? 60),
                'max_capacity' => intval($_POST['max_capacity']),
                'price' => floatval($_POST['price'] ?? 0),
                'status' => 'active'
            ]
        );

        if ($result) {
            $course_id = $wpdb->insert_id;
            $this->generate_course_lessons($course_id);

            // OPRAVA: Přesměrování místo admin_notices
            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_created=1'));
            exit;
        }
    }

    private function update_course() {
        global $wpdb;

        $course_id = intval($_POST['course_id']);

        $result = $wpdb->update(
            $wpdb->prefix . 'mom_courses',
            [
                'title' => sanitize_text_field($_POST['course_title']),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'lesson_count' => intval($_POST['lesson_count']),
                'day_of_week' => intval($_POST['day_of_week']),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'lesson_duration' => intval($_POST['lesson_duration'] ?? 60),
                'max_capacity' => intval($_POST['max_capacity']),
                'price' => floatval($_POST['price'] ?? 0)
            ],
            ['id' => $course_id]
        );

        if ($result !== false) {
            // Přegenerovat lekce
            $wpdb->delete($wpdb->prefix . 'mom_lessons', ['course_id' => $course_id]);
            $this->generate_course_lessons($course_id);

            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_updated=1'));
            exit;
        }
    }

    private function create_user() {
        global $wpdb;

        // Zkontroluj duplicitní email
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mom_customers WHERE email = %s",
            sanitize_email($_POST['user_email'])
        ));

        if ($existing) {
            wp_redirect(admin_url('admin.php?page=mom-users&error=duplicate_email'));
            exit;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'mom_customers',
            [
                'name' => sanitize_text_field($_POST['user_name']),
                'email' => sanitize_email($_POST['user_email']),
                'phone' => sanitize_text_field($_POST['user_phone'] ?? ''),
                'child_name' => sanitize_text_field($_POST['child_name'] ?? ''),
                'child_birth_date' => !empty($_POST['child_birth_date']) ? sanitize_text_field($_POST['child_birth_date']) : null,
                'emergency_contact' => sanitize_text_field($_POST['emergency_contact'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
            ]
        );

        if ($result) {
            wp_redirect(admin_url('admin.php?page=mom-users&user_created=1'));
            exit;
        }
    }

    private function generate_course_lessons($course_id) {
        global $wpdb;

        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mom_courses WHERE id = %d",
            $course_id
        ));

        if (!$course) return;

        $start_date = new DateTime($course->start_date);

        // Najdi první správný den v týdnu
        while ($start_date->format('N') != $course->day_of_week) {
            $start_date->add(new DateInterval('P1D'));
        }

        // Vygeneruj lekce
        for ($i = 1; $i <= $course->lesson_count; $i++) {
            $lesson_datetime = clone $start_date;
            $lesson_datetime->setTime(
                date('H', strtotime($course->start_time)),
                date('i', strtotime($course->start_time))
            );

            $wpdb->insert(
                $wpdb->prefix . 'mom_lessons',
                [
                    'course_id' => $course_id,
                    'lesson_number' => $i,
                    'title' => $course->title . ' - Lekce ' . $i,
                    'date_time' => $lesson_datetime->format('Y-m-d H:i:s'),
                    'max_capacity' => $course->max_capacity,
                    'status' => 'active',
                    'description' => "Lekce č. $i kurzu: " . $course->title
                ]
            );

            $start_date->add(new DateInterval('P7D'));
        }
    }

    public function ajax_toggle_lessons() {
        check_ajax_referer('mom_admin_nonce', 'nonce');

        global $wpdb;

        $course_id = intval($_POST['course_id']);

        $lessons = $wpdb->get_results($wpdb->prepare("
            SELECT l.*,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}mom_bookings b WHERE b.lesson_id = l.id AND b.booking_status = 'confirmed') as bookings_count
            FROM {$wpdb->prefix}mom_lessons l
            WHERE l.course_id = %d
            ORDER BY l.lesson_number ASC
        ", $course_id));

        wp_send_json_success(['lessons' => $lessons]);
    }

    // Zbytek metod stejný jako předtím...
    public function enqueue_frontend_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'mom_booking_calendar') ||
            has_shortcode($post->post_content, 'mom_course_list')
        )) {
            wp_enqueue_script(
                'mom-booking-frontend',
                MOM_BOOKING_PLUGIN_URL . 'assets/booking.js',
                ['jquery'],
                MOM_BOOKING_VERSION,
                true
            );

            wp_enqueue_style(
                'mom-booking-frontend',
                MOM_BOOKING_PLUGIN_URL . 'assets/booking.css',
                [],
                MOM_BOOKING_VERSION
            );

            wp_localize_script('mom-booking-frontend', 'momBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mom_booking_nonce'),
                'strings' => [
                    'loading' => 'Načítání...',
                    'error' => 'Došlo k chybě. Zkuste to prosím znovu.',
                    'success' => 'Rezervace byla úspěšně vytvořena!',
                    'confirmCancel' => 'Opravdu chcete zrušit tuto rezervaci?'
                ]
            ]);
        }
    }

    public function booking_calendar_shortcode($atts) {
        $atts = shortcode_atts([
            'course_id' => '',
            'show_past' => 'false',
            'limit' => '10'
        ], $atts);

        ob_start();
        ?>
        <div id="mom-booking-calendar" class="mom-booking-widget">
            <h3>Dostupné lekce</h3>
            <div id="lessons-container"
                 data-course-id="<?php echo esc_attr($atts['course_id']); ?>"
                 data-show-past="<?php echo esc_attr($atts['show_past']); ?>"
                 data-limit="<?php echo esc_attr($atts['limit']); ?>">
                <p class="loading-message">Načítání lekcí...</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function course_list_shortcode($atts) {
        global $wpdb;

        $atts = shortcode_atts([
            'status' => 'active',
            'limit' => '5',
            'show_price' => 'true'
        ], $atts);

        $courses = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}mom_courses
            WHERE status = %s
            ORDER BY start_date ASC
            LIMIT %d
        ", $atts['status'], intval($atts['limit'])));

        if (empty($courses)) {
            return '<p>Momentálně nejsou dostupné žádné kurzy.</p>';
        }

        ob_start();
        echo '<div class="mom-course-list">';
        foreach ($courses as $course) {
            echo '<div class="course-item">';
            echo '<h4>' . esc_html($course->title) . '</h4>';
            if ($course->description) {
                echo '<p>' . esc_html($course->description) . '</p>';
            }
            echo '<div class="course-meta">';
            echo 'Začátek: ' . date('d.m.Y', strtotime($course->start_date));
            echo ' | ' . $course->lesson_count . ' lekcí';
            if ($atts['show_price'] === 'true' && $course->price > 0) {
                echo ' | ' . number_format($course->price, 0, ',', ' ') . ' Kč';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public function ajax_get_lessons() {
        check_ajax_referer('mom_booking_nonce', 'nonce');

        global $wpdb;

        $course_id = intval($_POST['course_id'] ?? 0);
        $show_past = $_POST['show_past'] === 'true';

        $where_clause = "WHERE l.status = 'active'";
        if ($course_id > 0) {
            $where_clause .= $wpdb->prepare(" AND l.course_id = %d", $course_id);
        }
        if (!$show_past) {
            $where_clause .= " AND l.date_time > NOW()";
        }

        $lessons = $wpdb->get_results("
            SELECT l.*,
                   (l.max_capacity - l.current_bookings) as available_spots,
                   c.title as course_title
            FROM {$wpdb->prefix}mom_lessons l
            LEFT JOIN {$wpdb->prefix}mom_courses c ON l.course_id = c.id
            $where_clause
            ORDER BY l.date_time ASC
            LIMIT 20
        ");

        wp_send_json_success($lessons);
    }

    public function ajax_book_lesson() {
        check_ajax_referer('mom_booking_nonce', 'nonce');

        global $wpdb;

        $lesson_id = intval($_POST['lesson_id']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');

        // Kontroly
        $lesson = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mom_lessons WHERE id = %d AND status = 'active'",
            $lesson_id
        ));

        if (!$lesson) {
            wp_send_json_error('Lekce nebyla nalezena.');
            return;
        }

        if ($lesson->current_bookings >= $lesson->max_capacity) {
            wp_send_json_error('Lekce je již plně obsazena.');
            return;
        }

        // Vytvoř rezervaci
        $result = $wpdb->insert(
            $wpdb->prefix . 'mom_bookings',
            [
                'lesson_id' => $lesson_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'booking_status' => 'confirmed'
            ]
        );

        if ($result) {
            // Aktualizuj počet rezervací
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}mom_lessons
                 SET current_bookings = current_bookings + 1
                 WHERE id = %d",
                $lesson_id
            ));

            wp_send_json_success('Rezervace byla úspěšně vytvořena!');
        } else {
            wp_send_json_error('Chyba při vytváření rezervace.');
        }
    }
}

/**
 * Admin stránky
 */
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
    global $wpdb;

    // Zpracování formuláře
    MomBookingPlugin::get_instance()->handle_admin_actions();

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

    // Zpracování formuláře
    MomBookingPlugin::get_instance()->handle_admin_actions();

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
                                <a href="#" class="button button-small">Upravit</a>
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
    echo '<div class="wrap"><h1>Rezervace</h1><p>Tato sekce bude přidána v další verzi.</p></div>';
}

/**
 * Aktivační hook - přidání tabulky zákazníků
 */
register_activation_hook(__FILE__, 'mom_booking_activate');

function mom_booking_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Courses table
    $table_courses = $wpdb->prefix . 'mom_courses';
    $sql_courses = "CREATE TABLE $table_courses (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text,
        start_date date NOT NULL,
        lesson_count int(11) DEFAULT 10,
        lesson_duration int(11) DEFAULT 60,
        day_of_week int(1) NOT NULL,
        start_time time NOT NULL,
        max_capacity int(11) DEFAULT 10,
        price decimal(10,2) DEFAULT 0,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Lessons table
    $table_lessons = $wpdb->prefix . 'mom_lessons';
    $sql_lessons = "CREATE TABLE $table_lessons (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        course_id mediumint(9),
        lesson_number int(11) DEFAULT 1,
        title varchar(255) NOT NULL,
        date_time datetime NOT NULL,
        max_capacity int(11) DEFAULT 10,
        current_bookings int(11) DEFAULT 0,
        status varchar(20) DEFAULT 'active',
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Bookings table
    $table_bookings = $wpdb->prefix . 'mom_bookings';
    $sql_bookings = "CREATE TABLE $table_bookings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        lesson_id mediumint(9) NOT NULL,
        customer_name varchar(255) NOT NULL,
        customer_email varchar(255) NOT NULL,
        customer_phone varchar(20),
        booking_status varchar(20) DEFAULT 'confirmed',
        booking_date datetime DEFAULT CURRENT_TIMESTAMP,
        notes text,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Customers table - NOVÁ
    $table_customers = $wpdb->prefix . 'mom_customers';
    $sql_customers = "CREATE TABLE $table_customers (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) UNIQUE NOT NULL,
        phone varchar(20),
        child_name varchar(255),
        child_birth_date date,
        emergency_contact varchar(255),
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_courses);
    dbDelta($sql_lessons);
    dbDelta($sql_bookings);
    dbDelta($sql_customers);
}

// Inicializace pluginu
add_action('plugins_loaded', function() {
    MomBookingPlugin::get_instance();
});
?>
