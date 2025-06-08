<?php
/**
 * Debug Admin Menu - přidej do includes/admin/class-admin-menu.php
 */
class MomBookingAdminMenu {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // DEBUG: Přidej logování
        error_log('MomBookingAdminMenu: Constructor called');

        add_action('admin_menu', [$this, 'register_menu']);

        // DEBUG: Zkontroluj, jestli se hook registruje
        add_action('admin_init', function() {
            error_log('MomBookingAdminMenu: admin_init hook fired');
        });
    }

    public function register_menu() {
        // DEBUG: Logování při registraci menu
        error_log('MomBookingAdminMenu: register_menu called');
        error_log('Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));

        // Zkus nejprv jednodušší verzi
        $hook = add_menu_page(
            'Kurzy maminek',  // Bez __() pro test
            'Kurzy maminek',  // Bez __() pro test
            'manage_options',
            'mom-booking-admin',
            [$this, 'test_page'], // Jednoduchá test funkce
            'dashicons-groups',
            30
        );

        // DEBUG: Zkontroluj výsledek
        error_log('Menu hook result: ' . ($hook ? $hook : 'FALSE'));

        if ($hook) {
            error_log('Menu successfully registered with hook: ' . $hook);
        } else {
            error_log('ERROR: Menu registration failed!');
        }

        // Zkus přidat submenu
        $submenu_hook = add_submenu_page(
            'mom-booking-admin',
            'Test Submenu',
            'Test Submenu',
            'manage_options',
            'mom-test-submenu',
            [$this, 'test_page']
        );

        error_log('Submenu hook result: ' . ($submenu_hook ? $submenu_hook : 'FALSE'));
    }

    public function test_page() {
        echo '<div class="wrap">';
        echo '<h1>Test stránka funguje!</h1>';
        echo '<p>Pokud vidíš tuto stránku, menu funguje správně.</p>';
        echo '<p>Current user: ' . wp_get_current_user()->user_login . '</p>';
        echo '<p>User capabilities: ' . implode(', ', wp_get_current_user()->allcaps) . '</p>';
        echo '</div>';
    }
}

// DEBUG: Test přímé registrace (přidej dočasně do hlavního plugin souboru)
add_action('admin_menu', function() {
    error_log('Direct admin_menu hook fired');

    $direct_hook = add_menu_page(
        'TEST MENU',
        'TEST MENU',
        'manage_options',
        'test-direct-menu',
        function() {
            echo '<div class="wrap"><h1>Přímé menu funguje!</h1></div>';
        },
        'dashicons-admin-tools',
        31
    );

    error_log('Direct menu hook: ' . ($direct_hook ? $direct_hook : 'FALSE'));
});

// DEBUG: Zkontroluj loading order
add_action('plugins_loaded', function() {
    error_log('plugins_loaded hook fired');
    error_log('MomBookingAdminMenu class exists: ' . (class_exists('MomBookingAdminMenu') ? 'YES' : 'NO'));
});

add_action('admin_init', function() {
    error_log('admin_init hook fired');
    error_log('Current screen: ' . (get_current_screen() ? get_current_screen()->id : 'NONE'));
});
?>
