<?php
/**
 * HTML výstupy pro admin stránky
 */
class MomAdminPages {

    private $course_manager;
    private $customer_manager;
    private $enrollment_manager;

    public function __construct($course_manager, $customer_manager, $enrollment_manager) {
        $this->course_manager = $course_manager;
        $this->customer_manager = $customer_manager;
        $this->enrollment_manager = $enrollment_manager;
    }

    public function courses_overview_page() {
        $courses = $this->course_manager->get_all_courses();

        echo '<div class="wrap">';
        echo '<h1>Kurzy maminek - Přehled</h1>';

        if (empty($courses)) {
            $this->render_no_courses_message();
        } else {
            $this->render_courses_table($courses);
        }

        if (isset($_GET['course_id'])) {
            $this->render_course_detail($_GET['course_id']);
        }

        $this->render_admin_styles();
        echo '</div>';
    }

    private function render_no_courses_message() {
        echo '<div class="notice notice-info">';
        echo '<p>Zatím nemáte žádné kurzy. <a href="' . admin_url('admin.php?page=mom-course-new') . '">Vytvořte první kurz</a></p>';
        echo '</div>';
    }

    private function render_courses_table($courses) {
        $days = ['', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Název kurzu</th>';
        echo '<th>Začátek</th>';
        echo '<th>Den/Čas</th>';
        echo '<th>Lekcí</th>';
        echo '<th>Kapacita</th>';
        echo '<th>Status</th>';
        echo '<th>Akce</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($courses as $course) {
            $enrollments = $this->enrollment_manager->get_course_enrollments($course->id);
            $lessons = $this->course_manager->get_course_lessons($course->id);

            echo '<tr>';
            echo '<td><strong>' . esc_html($course->title) . '</strong></td>';
            echo '<td>' . date('d.m.Y', strtotime($course->start_date)) . '</td>';
            echo '<td>' . $days[$course->day_of_week] . ' ' . date('H:i', strtotime($course->start_time)) . '</td>';
            echo '<td>' . count($lessons) . '/' . $course->lesson_count . '</td>';
            echo '<td>' . count($enrollments) . '/' . $course->max_capacity . '</td>';
            echo '<td><span class="status-' . $course->status . '">' . ucfirst($course->status) . '</span></td>';
            echo '<td>';
            echo '<a href="?page=mom-booking-admin&course_id=' . $course->id . '" class="button">Detail</a> ';
            echo '<a href="?page=mom-course-new&edit=' . $course->id . '" class="button">Upravit</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    public function render_course_detail($course_id) {
        $course = $this->course_manager->get_course($course_id);
        $enrollments = $this->enrollment_manager->get_course_enrollments($course_id);
        $lessons = $this->course_manager->get_course_lessons($course_id);
        $available_customers = $this->customer_manager->get_customers_not_in_course($course_id);

        if (!$course) return;

        echo '<div class="wrap" style="margin-top: 30px;">';
        echo '<h2>Detail kurzu: ' . esc_html($course->title) . '</h2>';
        echo '<div class="course-detail-container">';

        // Levý sloupec - Přihlášené maminky
        echo '<div class="course-detail-column">';
        $this->render_enrollments_section($course, $enrollments, $available_customers);
        echo '</div>';

        // Pravý sloupec - Rozvrh lekcí
        echo '<div class="course-detail-column">';
        $this->render_lessons_section($course, $lessons);
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private function render_enrollments_section($course, $enrollments, $available_customers) {
        echo '<h3>Přihlášené maminky (' . count($enrollments) . '/' . $course->max_capacity . ')</h3>';

        if (!empty($enrollments)) {
            foreach ($enrollments as $enrollment) {
                echo '<div class="enrollment-item">';
                echo '<div>';
                echo '<strong>' . esc_html($enrollment->customer_name) . '</strong><br>';
                echo '<small>Dítě: ' . esc_html($enrollment->child_name ?: 'Nezadáno') . '</small><br>';
                echo '<small>' . esc_html($enrollment->customer_email) . '</small>';
                echo '</div>';
                echo '<div>';
                echo '<a href="?page=mom-booking-admin&course_id=' . $course->id . '&action=unenroll&enrollment_id=' . $enrollment->id . '" ';
                echo 'class="button button-secondary" ';
                echo 'onclick="return confirm(\'Opravdu chcete odhlásit tuto maminku?\')">Odhlásit</a>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>Zatím nejsou přihlášené žádné maminky.</p>';
        }

        // Formulář pro přihlášení nové maminky
        if (count($enrollments) < $course->max_capacity && !empty($available_customers)) {
            $this->render_enrollment_form($course->id, $available_customers);
        }
    }

    private function render_enrollment_form($course_id, $available_customers) {
        echo '<h4>Přihlásit maminku</h4>';
        echo '<form method="post">';
        wp_nonce_field('mom_admin_nonce', 'nonce');
        echo '<input type="hidden" name="course_id" value="' . $course_id . '">';
        echo '<select name="customer_id" required>';
        echo '<option value="">Vyberte maminku...</option>';

        foreach ($available_customers as $customer) {
            echo '<option value="' . $customer->id . '">';
            echo esc_html($customer->name . ' (' . $customer->email . ')');
            echo '</option>';
        }

        echo '</select>';
        echo '<button type="submit" name="enroll_customer" class="button button-primary">Přihlásit</button>';
        echo '</form>';
    }

    private function render_lessons_section($course, $lessons) {
        echo '<h3>Rozvrh lekcí</h3>';

        if (!empty($lessons)) {
            foreach ($lessons as $lesson) {
                $css_class = $lesson->status === 'cancelled' ? 'lesson-cancelled' : '';
                echo '<div class="lesson-item ' . $css_class . '">';
                echo '<div class="lesson-info">';
                echo '<strong>Lekce ' . $lesson->lesson_number . ': ' . esc_html($lesson->title) . '</strong><br>';
                echo '<small>' . date('d.m.Y H:i', strtotime($lesson->date_time)) . '</small><br>';
                echo '<small>Rezervace: ' . $lesson->current_bookings . '/' . $lesson->max_capacity . '</small>';
                echo '</div>';
                echo '<div class="lesson-actions">';

                if ($lesson->status === 'active') {
                    echo '<a href="?page=mom-booking-admin&course_id=' . $course->id . '&action=cancel_lesson&lesson_id=' . $lesson->id . '" ';
                    echo 'class="button button-secondary" ';
                    echo 'onclick="return confirm(\'Opravdu chcete zrušit tuto lekci?\')">Zrušit</a>';
                } else {
                    echo '<span class="lesson-status-cancelled">Zrušena</span>';
                }

                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>Pro tento kurz ještě nejsou vygenerovány lekce.</p>';
            echo '<a href="?page=mom-booking-admin&course_id=' . $course->id . '&action=generate_lessons" class="button button-primary">';
            echo 'Vygenerovat lekce</a>';
        }
    }

    public function course_form_page() {
        $editing = isset($_GET['edit']);
        $course = $editing ? $this->course_manager->get_course($_GET['edit']) : null;

        echo '<div class="wrap">';
        echo '<h1>' . ($editing ? 'Upravit kurz' : 'Nový kurz') . '</h1>';

        $this->render_course_form($course, $editing);

        echo '</div>';
    }

    private function render_course_form($course, $editing) {
        echo '<form method="post">';
        wp_nonce_field('mom_admin_nonce', 'nonce');

        if ($editing) {
            echo '<input type="hidden" name="course_id" value="' . $course->id . '">';
        }

        echo '<table class="form-table">';

        $this->render_form_field('text', 'title', 'Název kurzu', $course->title ?? '', true);
        $this->render_form_field('textarea', 'description', 'Popis', $course->description ?? '');
        $this->render_form_field('date', 'start_date', 'Datum začátku', $course->start_date ?? '', true);
        $this->render_form_field('number', 'lesson_count', 'Počet lekcí', $course->lesson_count ?? '10', true, ['min' => 1, 'max' => 52]);
        $this->render_day_select($course->day_of_week ?? null);
        $this->render_form_field('time', 'start_time', 'Čas začátku', $course->start_time ?? '10:00', true);
        $this->render_form_field('number', 'lesson_duration', 'Délka lekce (minuty)', $course->lesson_duration ?? '60', true, ['min' => 30, 'max' => 180, 'step' => 15]);
        $this->render_form_field('number', 'max_capacity', 'Maximální kapacita', $course->max_capacity ?? '10', true, ['min' => 1, 'max' => 50]);
        $this->render_form_field('number', 'price', 'Cena kurzu (Kč)', $course->price ?? '0', false, ['min' => 0, 'step' => '0.01']);

        echo '</table>';

        echo '<p class="submit">';
        echo '<button type="submit" name="' . ($editing ? 'update_course' : 'create_course') . '" class="button button-primary">';
        echo $editing ? 'Aktualizovat kurz' : 'Vytvořit kurz';
        echo '</button>';
        echo ' <a href="' . admin_url('admin.php?page=mom-booking-admin') . '" class="button">Zpět</a>';
        echo '</p>';

        echo '</form>';
    }

    private function render_form_field($type, $name, $label, $value = '', $required = false, $attributes = []) {
        echo '<tr>';
        echo '<th><label for="' . $name . '">' . $label . '</label></th>';
        echo '<td>';

        if ($type === 'textarea') {
            echo '<textarea id="' . $name . '" name="' . $name . '" rows="3" class="large-text"' . ($required ? ' required' : '') . '>';
            echo esc_textarea($value);
            echo '</textarea>';
        } else {
            echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . esc_attr($value) . '"';
            echo ' class="regular-text"' . ($required ? ' required' : '');

            foreach ($attributes as $attr => $attr_value) {
                echo ' ' . $attr . '="' . $attr_value . '"';
            }

            echo '>';
        }

        echo '</td>';
        echo '</tr>';
    }

    private function render_day_select($selected_day) {
        $days = [
            '' => 'Vyberte den...',
            1 => 'Pondělí',
            2 => 'Úterý',
            3 => 'Středa',
            4 => 'Čtvrtek',
            5 => 'Pátek',
            6 => 'Sobota',
            7 => 'Neděle'
        ];

        echo '<tr>';
        echo '<th><label for="day_of_week">Den v týdnu</label></th>';
        echo '<td>';
        echo '<select id="day_of_week" name="day_of_week" required>';

        foreach ($days as $value => $label) {
            $selected = ($selected_day == $value) ? ' selected' : '';
            echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        echo '</select>';
        echo '</td>';
        echo '</tr>';
    }

    public function customers_page() {
        $customers = $this->customer_manager->get_all_customers();

        echo '<div class="wrap">';
        echo '<h1>Maminky</h1>';

        $this->render_customers_table($customers);

        echo '</div>';
    }

    private function render_customers_table($customers) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Jméno</th>';
        echo '<th>Email</th>';
        echo '<th>Telefon</th>';
        echo '<th>Dítě</th>';
        echo '<th>Věk dítěte</th>';
        echo '<th>Aktivní kurzy</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($customers as $customer) {
            $child_age = $this->customer_manager->calculate_child_age($customer->child_birth_date);
            $stats = $this->customer_manager->get_customer_statistics($customer->id);

            echo '<tr>';
            echo '<td><strong>' . esc_html($customer->name) . '</strong></td>';
            echo '<td>' . esc_html($customer->email) . '</td>';
            echo '<td>' . esc_html($customer->phone ?: '-') . '</td>';
            echo '<td>' . esc_html($customer->child_name ?: '-') . '</td>';
            echo '<td>' . ($child_age ?: '-') . '</td>';
            echo '<td>' . $stats['active_courses'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    private function render_admin_styles() {
        echo '<style>
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: orange; }
        .status-completed { color: gray; }

        .course-detail-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .course-detail-column {
            flex: 1;
        }

        .enrollment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid #ddd;
            margin: 8px 0;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .lesson-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-left: 4px solid #0073aa;
            margin: 8px 0;
            background: white;
            border-radius: 0 4px 4px 0;
        }

        .lesson-cancelled {
            border-left-color: #dc3232;
            background: #ffeaea;
        }

        .lesson-status-cancelled {
            color: #dc3232;
            font-weight: bold;
        }
        </style>';
    }
}
