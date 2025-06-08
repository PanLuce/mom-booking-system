<?php
/**
 * Database Management Class
 * Single Responsibility: Handle database schema and operations
 */
class MomBookingDatabase {

    private static $instance = null;
    private $wpdb;
    private $db_version = '2.5.0';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Create all database tables
     */
    public function create_tables() {
        $this->create_courses_table();
        $this->create_lessons_table();
        $this->create_customers_table();
        $this->create_bookings_table();
        $this->create_course_registrations_table();
        $this->create_audit_log_table();

        $this->save_database_version();
        $this->maybe_upgrade_database();
    }

    /**
     * Create courses table
     */
    private function create_courses_table() {
        $table_name = $this->get_table_name('courses');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
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
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY start_date (start_date),
            KEY day_of_week (day_of_week),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Create lessons table
     */
    private function create_lessons_table() {
        $table_name = $this->get_table_name('lessons');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            course_id mediumint(9) NOT NULL,
            lesson_number int(11) DEFAULT 1,
            title varchar(255) NOT NULL,
            date_time datetime NOT NULL,
            max_capacity int(11) DEFAULT 10,
            current_bookings int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY date_time (date_time),
            KEY status (status),
            KEY lesson_number (lesson_number),
            FOREIGN KEY (course_id) REFERENCES {$this->get_table_name('courses')}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Create customers table
     */
    private function create_customers_table() {
        $table_name = $this->get_table_name('customers');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20),
            child_name varchar(255),
            child_birth_date date,
            emergency_contact varchar(255),
            notes text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY name (name),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Create bookings table
     */
    private function create_bookings_table() {
        $table_name = $this->get_table_name('bookings');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            lesson_id mediumint(9) NOT NULL,
            customer_id mediumint(9),
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20),
            booking_status varchar(20) DEFAULT 'confirmed',
            booking_date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            cancellation_reason text,
            cancelled_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lesson_id (lesson_id),
            KEY customer_id (customer_id),
            KEY customer_email (customer_email),
            KEY booking_status (booking_status),
            KEY booking_date (booking_date),
            UNIQUE KEY unique_booking (lesson_id, customer_email),
            FOREIGN KEY (lesson_id) REFERENCES {$this->get_table_name('lessons')}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$this->get_table_name('customers')}(id) ON DELETE SET NULL
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Create course registrations table (for tracking bulk registrations)
     */
    private function create_course_registrations_table() {
        $table_name = $this->get_table_name('course_registrations');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            course_id mediumint(9) NOT NULL,
            user_id mediumint(9) NOT NULL,
            successful_bookings int(11) DEFAULT 0,
            failed_bookings int(11) DEFAULT 0,
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY registration_date (registration_date),
            UNIQUE KEY unique_registration (course_id, user_id),
            FOREIGN KEY (course_id) REFERENCES {$this->get_table_name('courses')}(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$this->get_table_name('customers')}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Create audit log table for tracking changes
     */
    private function create_audit_log_table() {
        $table_name = $this->get_table_name('audit_log');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            table_name varchar(64) NOT NULL,
            record_id mediumint(9) NOT NULL,
            action varchar(20) NOT NULL,
            old_values longtext,
            new_values longtext,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY table_record (table_name, record_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $this->execute_sql($sql);
    }

    /**
     * Execute SQL with error handling
     */
    private function execute_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        if (!empty($this->wpdb->last_error)) {
            error_log("MOM Booking Database Error: " . $this->wpdb->last_error);
            error_log("SQL: " . $sql);
        }
    }

    /**
     * Drop all plugin tables
     */
    public function drop_tables() {
        $tables = [
            'audit_log',
            'course_registrations',
            'bookings',
            'lessons',
            'courses',
            'customers'
        ];

        // Disable foreign key checks temporarily
        $this->wpdb->query('SET foreign_key_checks = 0');

        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        // Re-enable foreign key checks
        $this->wpdb->query('SET foreign_key_checks = 1');

        delete_option('mom_booking_db_version');
    }

    /**
     * Get full table name with prefix
     */
    public function get_table_name($table) {
        return $this->wpdb->prefix . 'mom_' . $table;
    }

    /**
     * Check if all tables exist
     */
    public function tables_exist() {
        $required_tables = ['courses', 'lessons', 'customers', 'bookings'];

        foreach ($required_tables as $table) {
            $table_name = $this->get_table_name($table);
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

            if (!$exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get database version
     */
    public function get_database_version() {
        return get_option('mom_booking_db_version', '1.0.0');
    }

    /**
     * Save current database version
     */
    private function save_database_version() {
        update_option('mom_booking_db_version', $this->db_version);
    }

    /**
     * Maybe upgrade database if version changed
     */
    private function maybe_upgrade_database() {
        $current_version = $this->get_database_version();

        if (version_compare($current_version, $this->db_version, '<')) {
            $this->upgrade_database($current_version, $this->db_version);
        }
    }

    /**
     * Upgrade database schema
     */
    private function upgrade_database($from_version, $to_version) {
        // Version-specific upgrades
        if (version_compare($from_version, '2.0.0', '<')) {
            $this->upgrade_to_2_0_0();
        }

        if (version_compare($from_version, '2.5.0', '<')) {
            $this->upgrade_to_2_5_0();
        }

        $this->save_database_version();

        error_log("MOM Booking Database upgraded from {$from_version} to {$to_version}");
    }

    /**
     * Upgrade to version 2.0.0
     */
    private function upgrade_to_2_0_0() {
        // Add updated_at columns
        $this->add_updated_at_columns();

        // Add status column to customers
        $customers_table = $this->get_table_name('customers');
        $this->wpdb->query("ALTER TABLE {$customers_table} ADD COLUMN status varchar(20) DEFAULT 'active' AFTER notes");

        // Add indexes for better performance
        $this->add_performance_indexes();
    }

    /**
     * Upgrade to version 2.5.0
     */
    private function upgrade_to_2_5_0() {
        // Add cancellation tracking to bookings
        $bookings_table = $this->get_table_name('bookings');
        $this->wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN cancellation_reason text AFTER notes");
        $this->wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN cancelled_at datetime NULL AFTER cancellation_reason");

        // Create new tables if they don't exist
        if (!$this->table_exists('course_registrations')) {
            $this->create_course_registrations_table();
        }

        if (!$this->table_exists('audit_log')) {
            $this->create_audit_log_table();
        }
    }

    /**
     * Add updated_at columns to existing tables
     */
    private function add_updated_at_columns() {
        $tables = ['courses', 'lessons', 'customers', 'bookings'];

        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'updated_at'");

            if (empty($column_exists)) {
                $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
        }
    }

    /**
     * Add performance indexes
     */
    private function add_performance_indexes() {
        $indexes = [
            'courses' => ['created_at', 'day_of_week'],
            'lessons' => ['lesson_number'],
            'customers' => ['status', 'created_at'],
            'bookings' => ['booking_date']
        ];

        foreach ($indexes as $table => $table_indexes) {
            $table_name = $this->get_table_name($table);

            foreach ($table_indexes as $index) {
                // Check if index exists before adding
                $index_exists = $this->wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = '{$index}'");

                if (empty($index_exists)) {
                    $this->wpdb->query("ALTER TABLE {$table_name} ADD KEY {$index} ({$index})");
                }
            }
        }
    }

    /**
     * Check if specific table exists
     */
    private function table_exists($table) {
        $table_name = $this->get_table_name($table);
        $result = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        return !empty($result);
    }

    /**
     * Get database statistics
     */
    public function get_database_stats() {
        $stats = [];

        $tables = ['courses', 'lessons', 'customers', 'bookings'];

        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$table] = intval($count);
        }

        // Additional stats
        $stats['database_version'] = $this->get_database_version();
        $stats['tables_exist'] = $this->tables_exist();

        return $stats;
    }

    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        $tables = ['courses', 'lessons', 'customers', 'bookings', 'course_registrations', 'audit_log'];

        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $table_name = $this->get_table_name($table);
                $this->wpdb->query("OPTIMIZE TABLE {$table_name}");
            }
        }
    }

    /**
     * Clean up old audit log entries
     */
    public function cleanup_audit_log($days_to_keep = 90) {
        if (!$this->table_exists('audit_log')) {
            return;
        }

        $table_name = $this->get_table_name('audit_log');
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));

        $deleted = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            $cutoff_date
        ));

        return $deleted;
    }

    /**
     * Log changes to audit table
     */
    public function log_change($table_name, $record_id, $action, $old_values = [], $new_values = []) {
        if (!$this->table_exists('audit_log')) {
            return;
        }

        $audit_table = $this->get_table_name('audit_log');

        $this->wpdb->insert($audit_table, [
            'table_name' => $table_name,
            'record_id' => $record_id,
            'action' => $action,
            'old_values' => json_encode($old_values),
            'new_values' => json_encode($new_values),
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    /**
     * Get recent changes from audit log
     */
    public function get_recent_changes($limit = 50) {
        if (!$this->table_exists('audit_log')) {
            return [];
        }

        $audit_table = $this->get_table_name('audit_log');

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT a.*, u.display_name as user_name
            FROM {$audit_table} a
            LEFT JOIN {$this->wpdb->users} u ON a.user_id = u.ID
            ORDER BY a.created_at DESC
            LIMIT %d
        ", $limit));
    }
}
