<?php
/**
 * Detailní debug admin menu - nahraď celý obsah includes/admin/class-admin-menu.php
 */
class MomBookingAdminMenu {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            error_log('MomBookingAdminMenu: Instance created');
        }
        return self::$instance;
    }

    private function __construct() {
        error_log('MomBookingAdminMenu: Constructor called');
        error_log('MomBookingAdminMenu: is_admin() = ' . (is_admin() ? 'TRUE' : 'FALSE'));
        error_log('MomBookingAdminMenu: current_user_can(manage_options) = ' . (current_user_can('manage_options') ? 'TRUE' : 'FALSE'));

        // Registruj hook
        add_action('admin_menu', [$this, 'register_menu']);
        error_log('MomBookingAdminMenu: admin_menu hook registered');

        // Přidej další debug hooky
        add_action('admin_init', function() {
            error_log('MomBookingAdminMenu: admin_init hook fired');
        });

        add_action('current_screen', function() {
            error_log('MomBookingAdminMenu: current_screen hook fired');
        });
    }

    public function register_menu() {
        error_log('MomBookingAdminMenu: register_menu() called!');
        error_log('MomBookingAdminMenu: Current user ID: ' . get_current_user_id());
        error_log('MomBookingAdminMenu: Current user roles: ' . implode(', ', wp_get_current_user()->roles));
        error_log('MomBookingAdminMenu: manage_options capability: ' . (current_user_can('manage_options') ? 'TRUE' : 'FALSE'));

        // Zkontroluj globální $menu proměnnou
        global $menu;
        error_log('MomBookingAdminMenu: Global $menu exists: ' . (isset($menu) ? 'TRUE' : 'FALSE'));

        // Zkus nejprve úplně základní menu
        error_log('MomBookingAdminMenu: Attempting to register basic menu...');

        $hook = add_menu_page(
            'Kurzy maminek',
            'Kurzy maminek',
            'manage_options',
            'mom-booking-admin',
            [$this, 'test_callback'],
            'dashicons-groups',
            30
        );

        error_log('MomBookingAdminMenu: add_menu_page returned: ' . var_export($hook, true));

        if ($hook) {
            error_log('MomBookingAdminMenu: SUCCESS - Menu registered with hook: ' . $hook);
        } else {
            error_log('MomBookingAdminMenu: ERROR - Menu registration failed!');
        }

        // Zkontroluj, jestli se menu přidalo do globálního $menu
        global $menu;
        if (isset($menu)) {
            error_log('MomBookingAdminMenu: Current menu items count: ' . count($menu));
            foreach ($menu as $key => $item) {
                if (isset($item[2]) && $item[2] === 'mom-booking-admin') {
                    error_log('MomBookingAdminMenu: FOUND our menu in global $menu at position: ' . $key);
                    error_log('MomBookingAdminMenu: Menu item details: ' . var_export($item, true));
                }
            }
        }

        // Zkus ještě submenu
        if ($hook) {
            error_log('MomBookingAdminMenu: Adding submenu...');

            $submenu_hook = add_submenu_page(
                'mom-booking-admin',
                'Test Submenu',
                'Test Submenu',
                'manage_options',
                'mom-test-sub',
                [$this, 'test_callback']
            );

            error_log('MomBookingAdminMenu: Submenu hook: ' . var_export($submenu_hook, true));
        }
    }

    public function test_callback() {
        error_log('MomBookingAdminMenu: test_callback() called!');

        echo '<div class="wrap">';
        echo '<h1>Test Admin Page</h1>';
        echo '<p>Pokud vidíš tuto stránku, callback funguje!</p>';
        echo '<p><strong>Debug info:</strong></p>';
        echo '<ul>';
        echo '<li>Current user: ' . wp_get_current_user()->user_login . '</li>';
        echo '<li>User ID: ' . get_current_user_id() . '</li>';
        echo '<li>Can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO') . '</li>';
        echo '<li>Current screen: ' . (get_current_screen() ? get_current_screen()->id : 'Unknown') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
}

// Přidej debug hook přímo do souboru
add_action('admin_menu', function() {
    error_log('Direct admin_menu hook in class-admin-menu.php fired!');
}, 5); // Vysoká priorita

add_action('_admin_menu', function() {
    error_log('_admin_menu hook fired!');
}, 5);
?>
