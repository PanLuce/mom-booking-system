<?php
/**
 * Lesson Form Processor
 * Single Responsibility: Process lesson-related form submissions
 */
class MomLessonFormProcessor {

    private $container;
    private $lesson_manager;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->lesson_manager = $container->get('lesson_manager');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Process form submission
     * @param string $action Form action
     * @param array $data Form data
     */
    public function process($action, $data) {
        switch ($action) {
            case 'update_lesson':
                $this->handle_update_lesson($data);
                break;

            case 'add_user_to_lesson':
                $this->handle_add_user_to_lesson($data);
                break;

            case 'remove_user_from_lesson':
                $this->handle_remove_user_from_lesson($data);
                break;

            case 'toggle_lesson_status':
                $this->handle_toggle_lesson_status($data);
                break;

            case 'bulk_update_lessons':
                $this->handle_bulk_update_lessons($data);
                break;

            case 'export_lesson_participants':
                $this->handle_export_lesson_participants($data);
                break;

            default:
                throw new Exception("Unknown lesson action: {$action}");
        }
    }

    /**
     * Handle lesson update
     */
    private function handle_update_lesson($data) {
        $lesson_id = intval($data['lesson_id'] ?? 0);

        if (!$lesson_id) {
            throw new Exception(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if lesson exists
        $existing_lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$existing_lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        // Check if lesson can be updated
        if (!$this->can_update_lesson($existing_lesson)) {
            throw new Exception(__('Tato lekce již nemůže být upravena.', 'mom-booking-system'));
        }

        // Validate update data
        $this->validate_lesson_update_data($data);

        // Sanitize data
        $sanitized_data = $this->sanitize_lesson_data($data);

        // Update lesson
        $success = $this->lesson_manager->update_lesson($lesson_id, $sanitized_data);

        if (!$success) {
            throw new Exception(__('Chyba při aktualizaci lekce.', 'mom-booking-system'));
        }

        // Send notifications if lesson was cancelled
        if (isset($sanitized_data['status']) && $sanitized_data['status'] === 'cancelled') {
            $this->send_lesson_cancellation_notifications($lesson_id);
        }

        // Redirect with success message
        $this->redirect_handler->to_lesson($lesson_id, 'success', 'lesson_updated');
    }

    /**
     * Handle adding user to lesson
     */
    private function handle_add_user_to_lesson($data) {
        $lesson_id = intval($data['lesson_id'] ?? 0);
        $user_id = intval($data['user_id'] ?? 0);

        if (!$lesson_id || !$user_id) {
            throw new Exception(__('ID lekce nebo uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if lesson exists and can accept new participants
        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        if (!$this->can_add_participant($lesson)) {
            throw new Exception(__('Na tuto lekci nelze přidat další účastníky.', 'mom-booking-system'));
        }

        // Add user to lesson
        $result = $this->lesson_manager->add_user_to_lesson($lesson_id, $user_id);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Send notification to user
        $this->send_participant_added_notification($lesson_id, $user_id);

        // Redirect with success message
        $this->redirect_handler->to_lesson($lesson_id, 'success', 'user_added');
    }

    /**
     * Handle removing user from lesson
     */
    private function handle_remove_user_from_lesson($data) {
        $lesson_id = intval($data['lesson_id'] ?? 0);
        $user_identifier = $data['user_id'] ?? $data['user_email'] ?? '';

        if (!$lesson_id || empty($user_identifier)) {
            throw new Exception(__('ID lekce nebo uživatele nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if lesson exists
        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        // Remove user from lesson
        $result = $this->lesson_manager->remove_user_from_lesson($lesson_id, $user_identifier);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        if (!$result) {
            throw new Exception(__('Chyba při odebírání uživatele z lekce.', 'mom-booking-system'));
        }

        // Send notification to user
        $this->send_participant_removed_notification($lesson_id, $user_identifier);

        // Redirect with success message
        $this->redirect_handler->to_lesson($lesson_id, 'success', 'user_removed');
    }

    /**
     * Handle lesson status toggle
     */
    private function handle_toggle_lesson_status($data) {
        $lesson_id = intval($data['lesson_id'] ?? 0);

        if (!$lesson_id) {
            throw new Exception(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if lesson exists
        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        // Toggle lesson status
        $success = $this->lesson_manager->toggle_lesson_status($lesson_id);

        if (!$success) {
            throw new Exception(__('Chyba při změně stavu lekce.', 'mom-booking-system'));
        }

        // Get new status
        $updated_lesson = $this->lesson_manager->get_lesson($lesson_id);
        $new_status = $updated_lesson->status;

        // Send notifications if lesson was cancelled
        if ($new_status === 'cancelled') {
            $this->send_lesson_cancellation_notifications($lesson_id);
        }

        // Redirect with success message
        $status_message = $new_status === 'active' ? 'lesson_activated' : 'lesson_cancelled';
        $this->redirect_handler->to_lesson($lesson_id, 'success', $status_message);
    }

    /**
     * Handle bulk lesson updates
     */
    private function handle_bulk_update_lessons($data) {
        $lesson_ids = $data['lesson_ids'] ?? [];
        $bulk_action = $data['bulk_action'] ?? '';

        if (empty($lesson_ids) || !is_array($lesson_ids)) {
            throw new Exception(__('Žádné lekce nebyly vybrány.', 'mom-booking-system'));
        }

        if (empty($bulk_action)) {
            throw new Exception(__('Žádná akce nebyla vybrána.', 'mom-booking-system'));
        }

        // Convert to integers
        $lesson_ids = array_map('intval', $lesson_ids);

        $success_count = 0;
        $error_count = 0;

        foreach ($lesson_ids as $lesson_id) {
            try {
                switch ($bulk_action) {
                    case 'cancel':
                        $success = $this->bulk_cancel_lesson($lesson_id);
                        break;
                    case 'activate':
                        $success = $this->bulk_activate_lesson($lesson_id);
                        break;
                    case 'delete':
                        $success = $this->bulk_delete_lesson($lesson_id);
                        break;
                    default:
                        throw new Exception("Unknown bulk action: {$bulk_action}");
                }

                if ($success) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }

        // Redirect with results
        $this->redirect_handler->bulk_action('mom-bookings', $bulk_action, $success_count, $error_count);
    }

    /**
     * Handle lesson participants export
     */
    private function handle_export_lesson_participants($data) {
        $lesson_id = intval($data['lesson_id'] ?? 0);

        if (!$lesson_id) {
            throw new Exception(__('ID lekce nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if lesson exists
        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        // Get lessons page to handle export
        $lessons_page = $this->container->get('lessons_page');
        $export_data = $lessons_page->get_participants_export_data($lesson_id);

        if (empty($export_data)) {
            throw new Exception(__('Žádní účastníci k exportu.', 'mom-booking-system'));
        }

        // Generate CSV
        $filename = sanitize_file_name($lesson->title) . '_ucastnici';
        $this->generate_csv_export($export_data, $filename);
    }

    /**
     * Check if lesson can be updated
     */
    private function can_update_lesson($lesson) {
        // Can't update lessons that already happened (with some grace period)
        $grace_period = 2 * HOUR_IN_SECONDS; // 2 hours
        if (strtotime($lesson->date_time) < (time() - $grace_period)) {
            return false;
        }

        return true;
    }

    /**
     * Check if participants can be added to lesson
     */
    private function can_add_participant($lesson) {
        // Can't add to cancelled lessons
        if ($lesson->status === 'cancelled') {
            return false;
        }

        // Can't add to past lessons
        if (strtotime($lesson->date_time) < time()) {
            return false;
        }

        // Can't add if lesson is full
        if ($lesson->current_bookings >= $lesson->max_capacity) {
            return false;
        }

        return true;
    }

    /**
     * Validate lesson update data
     */
    private function validate_lesson_update_data($data) {
        // Validate allowed fields
        $allowed_fields = ['title', 'description', 'date_time', 'max_capacity', 'status'];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed_fields) && $field !== 'lesson_id') {
                throw new Exception(sprintf(
                    __('Pole %s nelze upravit.', 'mom-booking-system'),
                    $this->get_field_label($field)
                ));
            }
        }

        // Validate specific fields
        if (isset($data['max_capacity'])) {
            $capacity = intval($data['max_capacity']);
            if ($capacity < 1 || $capacity > 100) {
                throw new Exception(__('Kapacita musí být mezi 1 a 100.', 'mom-booking-system'));
            }
        }

        if (isset($data['date_time'])) {
            if (!$this->is_valid_datetime($data['date_time'])) {
                throw new Exception(__('Neplatné datum a čas.', 'mom-booking-system'));
            }
        }

        if (isset($data['status'])) {
            $valid_statuses = ['active', 'cancelled', 'completed'];
            if (!in_array($data['status'], $valid_statuses)) {
                throw new Exception(__('Neplatný stav lekce.', 'mom-booking-system'));
            }
        }
    }

    /**
     * Sanitize lesson data
     */
    private function sanitize_lesson_data($data) {
        $sanitized = [];

        if (isset($data['title'])) {
            $sanitized['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['date_time'])) {
            $sanitized['date_time'] = sanitize_text_field($data['date_time']);
        }

        if (isset($data['max_capacity'])) {
            $sanitized['max_capacity'] = intval($data['max_capacity']);
        }

        if (isset($data['status'])) {
            $sanitized['status'] = sanitize_text_field($data['status']);
        }

        return $sanitized;
    }

    /**
     * Bulk cancel lesson
     */
    private function bulk_cancel_lesson($lesson_id) {
        $lesson = $this->lesson_manager->get_lesson($lesson_id);

        if (!$lesson || $lesson->status === 'cancelled') {
            return false;
        }

        $success = $this->lesson_manager->update_lesson($lesson_id, ['status' => 'cancelled']);

        if ($success) {
            $this->send_lesson_cancellation_notifications($lesson_id);
        }

        return $success;
    }

    /**
     * Bulk activate lesson
     */
    private function bulk_activate_lesson($lesson_id) {
        $lesson = $this->lesson_manager->get_lesson($lesson_id);

        if (!$lesson || $lesson->status === 'active') {
            return false;
        }

        return $this->lesson_manager->update_lesson($lesson_id, ['status' => 'active']);
    }

    /**
     * Bulk delete lesson (careful operation)
     */
    private function bulk_delete_lesson($lesson_id) {
        $lesson = $this->lesson_manager->get_lesson($lesson_id);

        if (!$lesson) {
            return false;
        }

        // Check if lesson has bookings
        if ($lesson->current_bookings > 0) {
            return false; // Can't delete lessons with bookings
        }

        // This would require implementing delete_lesson method in lesson manager
        // For now, just mark as cancelled
        return $this->lesson_manager->update_lesson($lesson_id, ['status' => 'cancelled']);
    }

    /**
     * Send lesson cancellation notifications
     */
    private function send_lesson_cancellation_notifications($lesson_id) {
        // Check if notifications are enabled
        if (!get_option('mom_booking_email_notifications', 1)) {
            return;
        }

        $participants = $this->lesson_manager->get_lesson_participants($lesson_id);
        $lesson = $this->lesson_manager->get_lesson($lesson_id);

        foreach ($participants as $participant) {
            $subject = __('Zrušení lekce', 'mom-booking-system');
            $message = sprintf(
                __("Dobrý den %s,\n\nLekce byla zrušena:\n\n%s\nDatum: %s\n\nOmlouváme se za případné nepříjemnosti.", 'mom-booking-system'),
                $participant->customer_name,
                $lesson->title,
                date('d.m.Y H:i', strtotime($lesson->date_time))
            );

            wp_mail($participant->customer_email, $subject, $message);
        }
    }

    /**
     * Send participant added notification
     */
    private function send_participant_added_notification($lesson_id, $user_id) {
        if (!get_option('mom_booking_email_notifications', 1)) {
            return;
        }

        $user_manager = $this->container->get('user_manager');
        $user = $user_manager->get_user($user_id);
        $lesson = $this->lesson_manager->get_lesson($lesson_id);

        if (!$user || !$lesson) {
            return;
        }

        $subject = __('Přidání na lekci', 'mom-booking-system');
        $message = sprintf(
            __("Dobrý den %s,\n\nByli jste přidáni na lekci:\n\n%s\nDatum: %s\n\nTěšíme se na vás!", 'mom-booking-system'),
            $user->name,
            $lesson->title,
            date('d.m.Y H:i', strtotime($lesson->date_time))
        );

        wp_mail($user->email, $subject, $message);
    }

    /**
     * Send participant removed notification
     */
    private function send_participant_removed_notification($lesson_id, $user_identifier) {
        if (!get_option('mom_booking_email_notifications', 1)) {
            return;
        }

        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        if (!$lesson) {
            return;
        }

        // Get user email
        $email = '';
        if (is_numeric($user_identifier)) {
            $user_manager = $this->container->get('user_manager');
            $user = $user_manager->get_user($user_identifier);
            $email = $user ? $user->email : '';
        } else {
            $email = $user_identifier;
        }

        if (empty($email)) {
            return;
        }

        $subject = __('Odebrání z lekce', 'mom-booking-system');
        $message = sprintf(
            __("Dobrý den,\n\nByli jste odebráni z lekce:\n\n%s\nDatum: %s\n\nV případě dotazů nás kontaktujte.", 'mom-booking-system'),
            $lesson->title,
            date('d.m.Y H:i', strtotime($lesson->date_time))
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Generate CSV export
     */
    private function generate_csv_export($data, $filename_prefix) {
        $filename = $filename_prefix . '_' . date('Y-m-d_H-i-s') . '.csv';

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys($data[0]));

            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Get field label for error messages
     */
    private function get_field_label($field) {
        $labels = [
            'title' => __('Název lekce', 'mom-booking-system'),
            'description' => __('Popis lekce', 'mom-booking-system'),
            'date_time' => __('Datum a čas', 'mom-booking-system'),
            'max_capacity' => __('Maximální kapacita', 'mom-booking-system'),
            'status' => __('Stav lekce', 'mom-booking-system'),
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Validate datetime format
     */
    private function is_valid_datetime($datetime) {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }
}
