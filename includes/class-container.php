<?php
/**
 * Dependency Injection Container
 * Single Responsibility: Manage and provide application dependencies
 */
class MomBookingContainer {

    private $instances = [];
    private $bindings = [];
    private $singletons = [];

    public function __construct() {
        $this->register_bindings();
        $this->register_singletons();
    }

    /**
     * Register service bindings
     */
    private function register_bindings() {
        $this->bindings = [
            // Core services
            'database' => 'MomBookingDatabase',
            'template_renderer' => 'MomTemplateRenderer',
            'redirect_handler' => 'MomRedirectHandler',

            // Business logic managers
            'course_manager' => 'MomCourseManager',
            'user_manager' => 'MomUserManager',
            'booking_manager' => 'MomBookingManager',
            'lesson_manager' => 'MomLessonManager',
            'registration_manager' => 'MomCourseRegistrationManager',

            // Application loaders
            'admin_loader' => 'MomAdminLoader',
            'frontend_loader' => 'MomFrontendLoader',

            // Admin components (lazy loaded)
            'admin_menu_manager' => 'MomAdminMenuManager',
            'courses_page' => 'MomCoursesPage',
            'users_page' => 'MomUsersPage',
            'bookings_page' => 'MomBookingsPage',
            'lessons_page' => 'MomLessonsPage',
            'form_handler' => 'MomAdminFormHandler',
            'ajax_handler' => 'MomAdminAjaxHandler',

            // Form processors
            'course_form_processor' => 'MomCourseFormProcessor',
            'user_form_processor' => 'MomUserFormProcessor',
            'booking_form_processor' => 'MomBookingFormProcessor',

            // Frontend components
            'shortcodes' => 'MomBookingShortcodes',
            'frontend_ajax' => 'MomBookingFrontendAjax',
        ];
    }

    /**
     * Register singleton services (services that should have only one instance)
     */
    private function register_singletons() {
        $this->singletons = [
            'database',
            'template_renderer',
            'redirect_handler',
            'course_manager',
            'user_manager',
            'booking_manager',
            'lesson_manager',
            'registration_manager',
        ];
    }

    /**
     * Get service instance
     * @param string $service Service name
     * @return mixed Service instance
     * @throws Exception If service not found
     */
    public function get($service) {
        // Return existing singleton instance
        if (in_array($service, $this->singletons) && isset($this->instances[$service])) {
            return $this->instances[$service];
        }

        // Check if service is registered
        if (!isset($this->bindings[$service])) {
            throw new Exception("Service '{$service}' not found in container.");
        }

        $class_name = $this->bindings[$service];

        // Check if class exists
        if (!class_exists($class_name)) {
            throw new Exception("Class '{$class_name}' for service '{$service}' does not exist.");
        }

        // Create instance with dependency injection
        $instance = $this->create_instance($class_name);

        // Store singleton instances
        if (in_array($service, $this->singletons)) {
            $this->instances[$service] = $instance;
        }

        return $instance;
    }

    /**
     * Create instance with dependency injection
     * @param string $class_name Class name to instantiate
     * @return mixed Class instance
     */
    private function create_instance($class_name) {
        $reflection = new ReflectionClass($class_name);
        $constructor = $reflection->getConstructor();

        // If no constructor, create simple instance
        if (!$constructor) {
            return new $class_name();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        // Resolve constructor dependencies
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $type_name = $type->getName();

                // If dependency is the container itself, inject it
                if ($type_name === 'MomBookingContainer' || $type_name === get_class($this)) {
                    $dependencies[] = $this;
                } else {
                    // Try to resolve dependency by class name
                    $service_name = $this->find_service_by_class($type_name);
                    if ($service_name) {
                        $dependencies[] = $this->get($service_name);
                    } else {
                        // If we can't resolve it, try to create it directly
                        if (class_exists($type_name)) {
                            $dependencies[] = $this->create_instance($type_name);
                        } else {
                            throw new Exception("Cannot resolve dependency '{$type_name}' for class '{$class_name}'.");
                        }
                    }
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                // For simple types without defaults, inject container
                $dependencies[] = $this;
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Find service name by class name
     * @param string $class_name Class name to find
     * @return string|null Service name or null if not found
     */
    private function find_service_by_class($class_name) {
        return array_search($class_name, $this->bindings) ?: null;
    }

    /**
     * Register a new service binding
     * @param string $service Service name
     * @param string $class_name Class name
     * @param bool $singleton Whether service should be singleton
     */
    public function bind($service, $class_name, $singleton = false) {
        $this->bindings[$service] = $class_name;

        if ($singleton) {
            $this->singletons[] = $service;
        }
    }

    /**
     * Register a singleton service
     * @param string $service Service name
     * @param string $class_name Class name
     */
    public function singleton($service, $class_name) {
        $this->bind($service, $class_name, true);
    }

    /**
     * Register an instance directly
     * @param string $service Service name
     * @param mixed $instance Instance to register
     */
    public function instance($service, $instance) {
        $this->instances[$service] = $instance;
    }

    /**
     * Check if service is registered
     * @param string $service Service name
     * @return bool True if service is registered
     */
    public function has($service) {
        return isset($this->bindings[$service]);
    }

    /**
     * Get all registered services
     * @return array Array of service names
     */
    public function get_services() {
        return array_keys($this->bindings);
    }

    /**
     * Clear all instances (useful for testing)
     */
    public function clear_instances() {
        $this->instances = [];
    }

    /**
     * Make service (create new instance every time)
     * @param string $service Service name
     * @return mixed New service instance
     */
    public function make($service) {
        if (!isset($this->bindings[$service])) {
            throw new Exception("Service '{$service}' not found in container.");
        }

        $class_name = $this->bindings[$service];
        return $this->create_instance($class_name);
    }
}
