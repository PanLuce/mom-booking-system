<?php
/**
 * Database management class
 */
class MomBookingDatabase {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // Courses table
        $sql[] = "CREATE TABLE {$wpdb->prefix}mom_courses (
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
        ) $charset_collate;";

        // Lessons table
        $sql[] = "CREATE TABLE {$wpdb->prefix}mom_lessons (
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
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY date_time (date_time),
            KEY status (status)
        ) $charset_collate;";

        // Customers table
        $sql[] = "CREATE TABLE {$wpdb->prefix}mom_customers (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) UNIQUE NOT NULL,
            phone varchar(20),
            child_name varchar(255),
            child_birth_date date,
            emergency_contact varchar(255),
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY name (name)
        ) $charset_collate;";

        // Bookings table
        $sql[] = "CREATE TABLE {$wpdb->prefix}mom_bookings (
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
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($sql as $query) {
            dbDelta($query);
        }

        // Save database version
        update_option('mom_booking_db_version', MOM_BOOKING_VERSION);
    }

    public static function drop_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'mom_courses',
            $wpdb->prefix . 'mom_lessons',
            $wpdb->prefix . 'mom_customers',
            $wpdb->prefix . 'mom_bookings'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('mom_booking_db_version');
    }

    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'mom_' . $table;
    }
}
