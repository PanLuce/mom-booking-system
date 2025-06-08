<?php
class MomBookingSystem {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        add_action('wp_ajax_get_available_lessons', [$this, 'get_available_lessons']);
        add_action('wp_ajax_nopriv_get_available_lessons', [$this, 'get_available_lessons']);
        add_action('wp_ajax_book_lesson', [$this, 'handle_booking']);
        add_action('wp_ajax_nopriv_book_lesson', [$this, 'handle_booking']);
    }

    public function get_available_lessons() {
        $lessons = $this->wpdb->get_results("
            SELECT l.*,
                   (l.max_capacity - l.current_bookings) as available_spots
            FROM {$this->wpdb->prefix}mom_lessons l
            WHERE l.date_time > NOW()
            AND l.status = 'active'
            AND l.current_bookings < l.max_capacity
            ORDER BY l.date_time ASC
        ");

        wp_send_json_success($lessons);
    }

    public function handle_booking() {
        // Validate nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mom_booking_nonce')) {
            wp_send_json_error('Bezpečnostní kontrola selhala.');
            return;
        }

        $lesson_id = intval($_POST['lesson_id']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_phone = sanitize_text_field($_POST['customer_phone']);

        // Check if lesson exists and has capacity
        $lesson = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_lessons WHERE id = %d AND status = 'active'",
                $lesson_id
            )
        );

        if (!$lesson) {
            wp_send_json_error('Lekce nebyla nalezena.');
            return;
        }

        if ($lesson->current_bookings >= $lesson->max_capacity) {
            wp_send_json_error('Lekce je již plně obsazená.');
            return;
        }

        // Check for duplicate booking
        $existing_booking = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}mom_bookings
                 WHERE lesson_id = %d AND customer_email = %s AND booking_status = 'confirmed'",
                $lesson_id, $customer_email
            )
        );

        if ($existing_booking) {
            wp_send_json_error('Již máte rezervaci na tuto lekci.');
            return;
        }

        // Create booking
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_bookings',
            [
                'lesson_id' => $lesson_id,
                'customer_email' => $customer_email,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'booking_status' => 'confirmed'
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            // Update lesson booking count
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->wpdb->prefix}mom_lessons
                     SET current_bookings = current_bookings + 1
                     WHERE id = %d",
                    $lesson_id
                )
            );

            $this->send_confirmation_email($customer_email, $customer_name, $lesson);
            wp_send_json_success('Rezervace byla úspěšně vytvořena!');
        } else {
            wp_send_json_error('Chyba při vytváření rezervace.');
        }
    }

    private function send_confirmation_email($email, $name, $lesson) {
        $subject = 'Potvrzení rezervace - ' . $lesson->title;
        $message = "Dobrý den {$name},\n\n";
        $message .= "Vaše rezervace byla úspěšně vytvořena:\n\n";
        $message .= "Lekce: {$lesson->title}\n";
        $message .= "Datum a čas: " . date('d.m.Y H:i', strtotime($lesson->date_time)) . "\n\n";
        $message .= "Těšíme se na vás!\n";

        wp_mail($email, $subject, $message);
    }
}
