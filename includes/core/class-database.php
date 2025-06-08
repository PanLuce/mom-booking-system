<?php
/**
 * Basic Database Management - Working Version
 */
class MomBookingDatabase {

    private $wpdb;

    public function __construct() {
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

        update_option('mom_booking_db_version', MOM_BOOKING_VERSION);
    }

    /**
     * Create courses table
     */
    private function create_courses_table() {
        $table_name = $this->wpdb->prefix . 'mom_courses';
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
            PRIMARY KEY (id),
            KEY status (status),
            KEY start_date (start_date)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create lessons table
     */
    private function create_lessons_table() {
        $table_name = $this->wpdb->prefix . 'mom_lessons';
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
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY date_time (date_time),
            KEY status (status)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create customers table
     */
    private function create_customers_table() {
        $table_name = $this->wpdb->prefix . 'mom_customers';
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY name (name)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create bookings table
     */
    private function create_bookings_table() {
        $table_name = $this->wpdb->prefix . 'mom_bookings';
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lesson_id (lesson_id),
            KEY customer_id (customer_id),
            KEY customer_email (customer_email),
            KEY booking_status (booking_status),
            UNIQUE KEY unique_booking (lesson_id, customer_email)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if all tables exist
     */
    public function tables_exist() {
        $required_tables = ['courses', 'lessons', 'customers', 'bookings'];

        foreach ($required_tables as $table) {
            $table_name = $this->wpdb->prefix . 'mom_' . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

            if (!$exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get table name with prefix
     */
    public function get_table_name($table) {
        return $this->wpdb->prefix . 'mom_' . $table;
    }

    /**
     * Drop all tables
     */
    public function drop_tables() {
        $tables = ['bookings', 'lessons', 'courses', 'customers'];

        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . 'mom_' . $table;
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        delete_option('mom_booking_db_version');
    }
}
