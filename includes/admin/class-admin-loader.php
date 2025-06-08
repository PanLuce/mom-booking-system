<?php
/**
 * Basic Admin Loader - Minimal Working Version
 * This version focuses on getting the plugin working first
 */
class MomAdminLoader {

    private $container;

    public function __construct(MomBookingContainer $container) {
        $this->container = $container;
    }

    /**
     * Initialize admin components
     */
    public function init() {
        if (!is_admin()) {
            return;
        }

        // Add basic admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * Add basic admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Kurzy maminek', 'mom-booking-system'),
            __('Kurzy maminek', 'mom-booking-system'),
            'manage_options',
            'mom-booking-admin',
            [$this, 'admin_page'],
            'dashicons-groups',
            30
        );
    }

    /**
     * Basic admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Kurzy maminek', 'mom-booking-system'); ?></h1>

            <div class="notice notice-success">
                <p><?php echo esc_html__('Plugin is working! The refactored architecture is loading correctly.', 'mom-booking-system'); ?></p>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('System Status', 'mom-booking-system'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Plugin Version', 'mom-booking-system'); ?></th>
                        <td><?php echo esc_html(MOM_BOOKING_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Database Tables', 'mom-booking-system'); ?></th>
                        <td>
                            <?php
                            try {
                                $database = $this->container->get('database');
                                if ($database->tables_exist()) {
                                    echo '<span style="color: green;">✓ ' . esc_html__('Created', 'mom-booking-system') . '</span>';
                                } else {
                                    echo '<span style="color: red;">✗ ' . esc_html__('Missing', 'mom-booking-system') . '</span>';
                                }
                            } catch (Exception $e) {
                                echo '<span style="color: orange;">? ' . esc_html__('Unable to check', 'mom-booking-system') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Container Services', 'mom-booking-system'); ?></th>
                        <td>
                            <?php
                            try {
                                $services = $this->container->get_services();
                                echo esc_html(count($services)) . ' ' . esc_html__('services registered', 'mom-booking-system');
                                echo '<br><small>' . esc_html(implode(', ', array_slice($services, 0, 5))) . '...</small>';
                            } catch (Exception $e) {
                                echo '<span style="color: red;">Error: ' . esc_html($e->getMessage()) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('Quick Actions', 'mom-booking-system'); ?></h2>
                <p>
                    <button type="button" class="button button-primary" onclick="testDatabase()">
                        <?php echo esc_html__('Test Database Connection', 'mom-booking-system'); ?>
                    </button>
                    <button type="button" class="button" onclick="clearCache()">
                        <?php echo esc_html__('Clear Container Cache', 'mom-booking-system'); ?>
                    </button>
                </p>
                <div id="test-results"></div>
            </div>
        </div>

        <script>
        function testDatabase() {
            document.getElementById('test-results').innerHTML = '<div class="notice notice-info"><p>Testing database...</p></div>';

            jQuery.post(ajaxurl, {
                action: 'mom_test_database',
                nonce: '<?php echo wp_create_nonce('mom_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    document.getElementById('test-results').innerHTML = '<div class="notice notice-success"><p>Database test passed!</p></div>';
                } else {
                    document.getElementById('test-results').innerHTML = '<div class="notice notice-error"><p>Database test failed: ' + response.data + '</p></div>';
                }
            });
        }

        function clearCache() {
            document.getElementById('test-results').innerHTML = '<div class="notice notice-info"><p>Clearing cache...</p></div>';

            jQuery.post(ajaxurl, {
                action: 'mom_clear_cache',
                nonce: '<?php echo wp_create_nonce('mom_admin_nonce'); ?>'
            }, function(response) {
                document.getElementById('test-results').innerHTML = '<div class="notice notice-success"><p>Cache cleared!</p></div>';
                location.reload();
            });
        }
        </script>

        <style>
        .card {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        .card h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if we're on our plugin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'mom-') !== 0) {
            return;
        }

        // Show success messages
        if (isset($_GET['success'])) {
            $message = $this->get_success_message($_GET['success']);
            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }

        // Show error messages
        if (isset($_GET['error'])) {
            $message = $this->get_error_message($_GET['error']);
            if ($message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }

    /**
     * Get success message
     */
    private function get_success_message($key) {
        $messages = [
            'plugin_activated' => __('Plugin activated successfully!', 'mom-booking-system'),
            'database_created' => __('Database tables created successfully!', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }

    /**
     * Get error message
     */
    private function get_error_message($key) {
        $messages = [
            'database_failed' => __('Database operation failed.', 'mom-booking-system'),
            'permission_denied' => __('Permission denied.', 'mom-booking-system'),
        ];

        return $messages[$key] ?? null;
    }
}
