<?php
/**
 * Booking Form Processor
 * Single Responsibility: Process booking-related form submissions
 */
class MomBookingFormProcessor {

    private $container;
    private $booking_manager;
    private $redirect_handler;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
        $this->booking_manager = $container->get('booking_manager');
        $this->redirect_handler = $container->get('redirect_handler');
    }

    /**
     * Process form submission
     * @param string $action Form action
     * @param array $data Form data
     */
    public function process($action, $data) {
        switch ($action) {
            case 'create_booking':
                $this->handle_create_booking($data);
                break;

            case 'update_booking':
                $this->handle_update_booking($data);
                break;

            case 'cancel_booking':
                $this->handle_cancel_booking($data);
                break;

            case 'bulk_cancel_bookings':
                $this->handle_bulk_cancel_bookings($data);
                break;

            case 'export_bookings':
                $this->handle_export_bookings($data);
                break;

            default:
                throw new Exception("Unknown booking action: {$action}");
        }
    }

    /**
     * Handle booking creation
     */
    private function handle_create_booking($data) {
        // Validate required fields
        $required_fields = ['lesson_id', 'customer_name', 'customer_email'];
        $this->validate_required_fields($data, $required_fields);

        // Additional validation
        $this->validate_booking_data($data);

        // Check lesson availability
        $this->check_lesson_availability($data['lesson_id']);

        // Sanitize data
        $sanitized_data = $this->sanitize_booking_data($data);

        // Try to find or create customer
        $customer_id = $this->get_or_create_customer($sanitized_data);
        if ($customer_id) {
            $sanitized_data['customer_id'] = $customer_id;
        }

        // Create booking
        $booking_id = $this->booking_manager->create_booking($sanitized_data);

        if (is_wp_error($booking_id)) {
            throw new Exception($booking_id->get_error_message());
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-bookings', 'booking_created', [
            'booking_id' => $booking_id
        ]);
    }

    /**
     * Handle booking update
     */
    private function handle_update_booking($data) {
        $booking_id = intval($data['booking_id'] ?? 0);

        if (!$booking_id) {
            throw new Exception(__('ID rezervace nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if booking exists
        $existing_booking = $this->booking_manager->get_booking($booking_id);
        if (!$existing_booking) {
            throw new Exception(__('Rezervace nebyla nalezena.', 'mom-booking-system'));
        }

        // Check if booking can be updated
        if (!$this->can_update_booking($existing_booking)) {
            throw new Exception(__('Tato rezervace již nemůže být upravena.', 'mom-booking-system'));
        }

        // Validate data
        $this->validate_booking_update_data($data);

        // Sanitize data
        $sanitized_data = $this->sanitize_booking_update_data($data);

        // Update booking
        $success = $this->update_booking_data($booking_id, $sanitized_data);

        if (!$success) {
            throw new Exception(__('Chyba při aktualizaci rezervace.', 'mom-booking-system'));
        }

        // Redirect with success message
        $this->redirect_handler->success('mom-bookings', 'booking_updated', [
            'booking_id' => $booking_id
        ]);
    }

    /**
     * Handle booking cancellation
     */
    private function handle_cancel_booking($data) {
        $booking_id = intval($data['booking_id'] ?? 0);

        if (!$booking_id) {
            throw new Exception(__('ID rezervace nebylo specifikováno.', 'mom-booking-system'));
        }

        // Check if booking exists
        $booking = $this->booking_manager->get_booking($booking_id);
        if (!$booking) {
            throw new Exception(__('Rezervace nebyla nalezena.', 'mom-booking-system'));
        }

        // Check if booking can be cancelled
        if (!$this->can_cancel_booking($booking)) {
            throw new Exception(__('Tato rezervace již nemůže být zrušena.', 'mom-booking-system'));
        }

        // Cancel booking
        $success = $this->booking_manager->cancel_booking($booking_id);

        if (!$success) {
            throw new Exception(__('Chyba při rušení rezervace.', 'mom-booking-system'));
        }

        // Send cancellation notification
        $this->send_cancellation_notification($booking);

        // Redirect with success message
        $this->redirect_handler->success('mom-bookings', 'booking_cancelled', [
            'booking_id' => $booking_id
        ]);
    }

    /**
     * Handle bulk booking cancellations
     */
    private function handle_bulk_cancel_bookings($data) {
        $booking_ids = $data['booking_ids'] ?? [];

        if (empty($booking_ids) || !is_array($booking_ids)) {
            throw new Exception(__('Žádné rezervace nebyly vybrány.', 'mom-booking-system'));
        }

        // Convert to integers
        $booking_ids = array_map('intval', $booking_ids);

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($booking_ids as $booking_id) {
            try {
                $booking = $this->booking_manager->get_booking($booking_id);

                if (!$booking) {
                    $error_count++;
                    continue;
                }

                if (!$this->can_cancel_booking($booking)) {
                    $error_count++;
                    $errors[] = sprintf(__('Rezervace #%d nemůže být zrušena.', 'mom-booking-system'), $booking_id);
                    continue;
                }

                $success = $this->booking_manager->cancel_booking($booking_id);

                if ($success) {
                    $success_count++;
                    $this->send_cancellation_notification($booking);
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = sprintf(__('Rezervace #%d: %s', 'mom-booking-system'), $booking_id, $e->getMessage());
            }
        }

        // Redirect with results
        $this->redirect_handler->bulk_action('mom-bookings', 'cancel', $success_count, $error_count);
    }

    /**
     * Handle booking export
     */
    private function handle_export_bookings($data) {
        // Get filters
        $filters = [
            'status' => $data['status_filter'] ?? 'all',
            'course_id' => $data['course_filter'] ?? '',
            'date_range' => $data['date_filter'] ?? 'all',
        ];

        // Get bookings page to handle export
        $bookings_page = $this->container->get('bookings_page');
        $export_data = $bookings_page->get_bookings_export_data($filters);

        if (empty($export_data)) {
            throw new Exception(__('Žádná data k exportu.', 'mom-booking-system'));
        }

        // Generate CSV
        $this->generate_csv_export($export_data, 'rezervace');
    }

    /**
     * Validate required fields
     */
    private function validate_required_fields($data, $required_fields) {
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception(sprintf(
                    __('Pole %s je povinné.', 'mom-booking-system'),
                    $this->get_field_label($field)
                ));
            }
        }
    }

    /**
     * Validate booking-specific data
     */
    private function validate_booking_data($data) {
        // Validate email format
        if (!is_email($data['customer_email'])) {
            throw new Exception(__('Neplatná emailová adresa.', 'mom-booking-system'));
        }

        // Validate lesson ID
        $lesson_id = intval($data['lesson_id']);
        if ($lesson_id <= 0) {
            throw new Exception(__('Neplatné ID lekce.', 'mom-booking-system'));
        }

        // Validate phone format if provided
        if (!empty($data['customer_phone'])) {
            $phone = preg_replace('/[\s\-\(\)]/', '', $data['customer_phone']);
            if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
                throw new Exception(__('Neplatné telefonní číslo.', 'mom-booking-system'));
            }
        }

        // Validate field lengths
        $max_lengths = [
            'customer_name' => 255,
            'customer_email' => 255,
            'customer_phone' => 20,
            'notes' => 1000,
        ];

        foreach ($max_lengths as $field => $max_length) {
            if (isset($data[$field]) && strlen($data[$field]) > $max_length) {
                throw new Exception(sprintf(
                    __('Pole %s je příliš dlouhé (maximálně %d znaků).', 'mom-booking-system'),
                    $this->get_field_label($field),
                    $max_length
                ));
            }
        }
    }

    /**
     * Check lesson availability
     */
    private function check_lesson_availability($lesson_id) {
        $lesson_manager = $this->container->get('lesson_manager');
        $lesson = $lesson_manager->get_lesson($lesson_id);

        if (!$lesson) {
            throw new Exception(__('Lekce nebyla nalezena.', 'mom-booking-system'));
        }

        if ($lesson->status !== 'active') {
            throw new Exception(__('Lekce není aktivní.', 'mom-booking-system'));
        }

        if (strtotime($lesson->date_time) < time()) {
            throw new Exception(__('Nelze rezervovat na lekci v minulosti.', 'mom-booking-system'));
        }

        if ($lesson->current_bookings >= $lesson->max_capacity) {
            throw new Exception(__('Lekce je již plně obsazena.', 'mom-booking-system'));
        }
    }

    /**
     * Get or create customer
     */
    private function get_or_create_customer($booking_data) {
        $user_manager = $this->container->get('user_manager');

        // Try to find existing customer by email
        $existing_customer = $user_manager->get_user_by_email($booking_data['customer_email']);

        if ($existing_customer) {
            return $existing_customer->id;
        }

        // Create new customer if not exists
        $customer_data = [
            'name' => $booking_data['customer_name'],
            'email' => $booking_data['customer_email'],
            'phone' => $booking_data['customer_phone'] ?? '',
        ];

        $customer_id = $user_manager->create_user($customer_data);

        if (is_wp_error($customer_id)) {
            // If customer creation fails, continue without customer_id
            return null;
        }

        return $customer_id;
    }

    /**
     * Check if booking can be updated
     */
    private function can_update_booking($booking) {
        // Can't update cancelled bookings
        if ($booking->booking_status === 'cancelled') {
            return false;
        }

        // Can't update past bookings
        if (strtotime($booking->date_time) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Check if booking can be cancelled
     */
    private function can_cancel_booking($booking) {
        // Can't cancel already cancelled bookings
        if ($booking->booking_status === 'cancelled') {
            return false;
        }

        // Can cancel past bookings for admin purposes
        return true;
    }

    /**
     * Validate booking update data
     */
    private function validate_booking_update_data($data) {
        // Allow updating limited fields
        $allowed_updates = ['customer_name', 'customer_phone', 'notes', 'booking_status'];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed_updates) && $field !== 'booking_id') {
                throw new Exception(sprintf(
                    __('Pole %s nelze upravit.', 'mom-booking-system'),
                    $this->get_field_label($field)
                ));
            }
        }

        // Validate status if provided
        if (isset($data['booking_status'])) {
            $valid_statuses = ['confirmed', 'cancelled', 'pending', 'waitlist'];
            if (!in_array($data['booking_status'], $valid_statuses)) {
                throw new Exception(__('Neplatný stav rezervace.', 'mom-booking-system'));
            }
        }
    }

    /**
     * Sanitize booking data
     */
    private function sanitize_booking_data($data) {
        return [
            'lesson_id' => intval($data['lesson_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'booking_status' => 'confirmed',
        ];
    }

    /**
     * Sanitize booking update data
     */
    private function sanitize_booking_update_data($data) {
        $sanitized = [];

        if (isset($data['customer_name'])) {
            $sanitized['customer_name'] = sanitize_text_field($data['customer_name']);
        }

        if (isset($data['customer_phone'])) {
            $sanitized['customer_phone'] = sanitize_text_field($data['customer_phone']);
        }

        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }

        if (isset($data['booking_status'])) {
            $sanitized['booking_status'] = sanitize_text_field($data['booking_status']);
        }

        return $sanitized;
    }

    /**
     * Update booking data
     */
    private function update_booking_data($booking_id, $data) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'mom_bookings',
            $data,
            ['id' => $booking_id],
            null,
            ['%d']
        ) !== false;
    }

    /**
     * Send cancellation notification
     */
    private function send_cancellation_notification($booking) {
        // Check if notifications are enabled
        if (!get_option('mom_booking_email_notifications', 1)) {
            return;
        }

        $subject = __('Zrušení rezervace', 'mom-booking-system');
        $message = sprintf(
            __("Dobrý den %s,\n\nVaše rezervace byla zrušena:\n\nLekce: %s\nDatum: %s\n\nOmlouváme se za případné nepříjemnosti.", 'mom-booking-system'),
            $booking->customer_name,
            $booking->lesson_title,
            date('d.m.Y H:i', strtotime($booking->date_time))
        );

        wp_mail($booking->customer_email, $subject, $message);
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
            'lesson_id' => __('Lekce', 'mom-booking-system'),
            'customer_name' => __('Jméno zákazníka', 'mom-booking-system'),
            'customer_email' => __('Email zákazníka', 'mom-booking-system'),
            'customer_phone' => __('Telefon zákazníka', 'mom-booking-system'),
            'notes' => __('Poznámky', 'mom-booking-system'),
            'booking_status' => __('Stav rezervace', 'mom-booking-system'),
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Log booking actions for audit trail
     */
    private function log_booking_action($action, $booking_id, $details = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
            'action' => $action,
            'booking_id' => $booking_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'details' => $details,
        ];

        error_log('MOM Booking Action: ' . json_encode($log_entry));
    }

    /**
     * Get booking statistics after action
     */
    public function get_booking_action_stats($action, $booking_id = null) {
        $stats = $this->booking_manager->get_booking_statistics();
        $stats['action_performed'] = $action;
        $stats['timestamp'] = current_time('mysql');

        if ($booking_id) {
            $stats['affected_booking_id'] = $booking_id;
        }

        return $stats;
    }
}
