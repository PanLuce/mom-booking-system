<?php
/**
 * Správa přihlášek na kurzy
 */
class MomEnrollmentManager {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_course_enrollments($course_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("
                SELECT e.*,
                       c.name as customer_name,
                       c.email as customer_email,
                       c.phone as customer_phone,
                       c.child_name,
                       c.child_birth_date
                FROM {$this->wpdb->prefix}mom_course_enrollments e
                JOIN {$this->wpdb->prefix}mom_customers c ON e.customer_id = c.id
                WHERE e.course_id = %d AND e.status = 'active'
                ORDER BY c.name ASC
            ", $course_id)
        );
    }

    public function enroll_customer() {
        $course_id = intval($_POST['course_id']);
        $customer_id = intval($_POST['customer_id']);

        if (!$course_id || !$customer_id) {
            wp_die('Neplatné údaje pro přihlášení.');
        }

        // Kontrola kapacity kurzu
        if (!$this->has_course_capacity($course_id)) {
            wp_die('Kurz je již plně obsazen.');
        }

        // Kontrola duplikátní přihlášky
        if ($this->is_customer_enrolled($course_id, $customer_id)) {
            wp_die('Zákaznice je již na tento kurz přihlášena.');
        }

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'mom_course_enrollments',
            [
                'course_id' => $course_id,
                'customer_id' => $customer_id,
                'status' => 'active'
            ]
        );

        if ($result) {
            // Automatické přihlášení na všechny lekce kurzu
            $this->auto_book_course_lessons($course_id, $customer_id);

            wp_redirect(admin_url('admin.php?page=mom-booking-admin&course_id=' . $course_id));
            exit;
        } else {
            wp_die('Chyba při přihlašování: ' . $this->wpdb->last_error);
        }
    }

    public function unenroll_customer($enrollment_id) {
        // Získej informace o přihlášce
        $enrollment = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_course_enrollments WHERE id = %d",
                $enrollment_id
            )
        );

        if (!$enrollment) {
            wp_die('Přihláška nenalezena.');
        }

        // Zruš přihlášku
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'mom_course_enrollments',
            ['status' => 'cancelled'],
            ['id' => $enrollment_id]
        );

        if ($result !== false) {
            // Zruš všechny rezervace na lekce tohoto kurzu
            $this->cancel_course_bookings($enrollment->course_id, $enrollment->customer_id);

            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        } else {
            wp_die('Chyba při odhlašování: ' . $this->wpdb->last_error);
        }
    }

    private function has_course_capacity($course_id) {
        $course = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT max_capacity FROM {$this->wpdb->prefix}mom_courses WHERE id = %d",
                $course_id
            )
        );

        if (!$course) {
            return false;
        }

        $current_enrollments = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}mom_course_enrollments
                 WHERE course_id = %d AND status = 'active'",
                $course_id
            )
        );

        return $current_enrollments < $course->max_capacity;
    }

    private function is_customer_enrolled($course_id, $customer_id) {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}mom_course_enrollments
                 WHERE course_id = %d AND customer_id = %d AND status = 'active'",
                $course_id, $customer_id
            )
        );

        return (bool) $existing;
    }

    private function auto_book_course_lessons($course_id, $customer_id) {
        // Získej zákaznici pro email a jméno
        $customer = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_customers WHERE id = %d",
                $customer_id
            )
        );

        if (!$customer) {
            return;
        }

        // Získej všechny aktivní lekce kurzu
        $lessons = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}mom_lessons
                 WHERE course_id = %d AND status = 'active'
                 ORDER BY lesson_number ASC",
                $course_id
            )
        );

        foreach ($lessons as $lesson) {
            // Zkontroluj, zda už není rezervováno
            $existing_booking = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->wpdb->prefix}mom_bookings
                     WHERE lesson_id = %d AND customer_email = %s",
                    $lesson->id, $customer->email
                )
            );

            if (!$existing_booking) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'mom_bookings',
                    [
                        'lesson_id' => $lesson->id,
                        'customer_id' => $customer_id,
                        'customer_name' => $customer->name,
                        'customer_email' => $customer->email,
                        'customer_phone' => $customer->phone,
                        'booking_status' => 'confirmed'
                    ]
                );

                // Aktualizuj počet rezervací lekce
                $this->wpdb->query(
                    $this->wpdb->prepare(
                        "UPDATE {$this->wpdb->prefix}mom_lessons
                         SET current_bookings = current_bookings + 1
                         WHERE id = %d",
                        $lesson->id
                    )
                );
            }
        }
    }

    private function cancel_course_bookings($course_id, $customer_id) {
        // Získej všechny lekce kurzu
        $lesson_ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}mom_lessons WHERE course_id = %d",
                $course_id
            )
        );

        if (empty($lesson_ids)) {
            return;
        }

        // Zruš rezervace
        $placeholders = implode(',', array_fill(0, count($lesson_ids), '%d'));
        $query = $this->wpdb->prepare(
            "UPDATE {$this->wpdb->prefix}mom_bookings
             SET booking_status = 'cancelled'
             WHERE lesson_id IN ($placeholders) AND customer_id = %d",
            array_merge($lesson_ids, [$customer_id])
        );

        $this->wpdb->query($query);

        // Sniž počet rezervací u lekcí
        foreach ($lesson_ids as $lesson_id) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->wpdb->prefix}mom_lessons
                     SET current_bookings = GREATEST(0, current_bookings - 1)
                     WHERE id = %d",
                    $lesson_id
                )
            );
        }
    }

    public function get_enrollment_statistics($course_id) {
        $enrollments = $this->get_course_enrollments($course_id);
        $course = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT max_capacity FROM {$this->wpdb->prefix}mom_courses WHERE id = %d",
                $course_id
            )
        );

        return [
            'enrolled_count' => count($enrollments),
            'max_capacity' => $course ? $course->max_capacity : 0,
            'available_spots' => $course ? ($course->max_capacity - count($enrollments)) : 0
        ];
    }
}
