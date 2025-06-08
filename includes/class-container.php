<?php
/**
 * Basic Dependency Injection Container - Working Version
 * This version focuses on working functionality first
 */
class MomBookingContainer {

    private $instances = [];
    private $bindings = [];

    public function __construct() {
        $this->register_default_bindings();
    }

    /**
     * Register default service bindings
     */
    private function register_default_bindings() {
        $this->bindings = [
            // Core services - only register what exists
            'database' => 'MomBookingDatabase',
            'admin_loader' => 'MomAdminLoader',

            // Add more as we create them
            'template_renderer' => 'MomTemplateRenderer',
            'redirect_handler' => 'MomRedirectHandler',
        ];
    }

    /**
     * Get service instance
     */
    public function get($service) {
        // Return existing instance
        if (isset($this->instances[$service])) {
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

        // Create instance
        try {
            $instance = $this->create_instance($class_name);
            $this->instances[$service] = $instance;
            return $instance;
        } catch (Exception $e) {
            throw new Exception("Failed to create service '{$service}': " . $e->getMessage());
        }
    }

    /**
     * Create instance with basic dependency injection
     */
    private function create_instance($class_name) {
        // For classes that need the container, inject it
        $classes_needing_container = [
            'MomAdminLoader',
            'MomCourseManager',
            'MomUserManager',
            'MomBookingManager',
            'MomLessonManager',
        ];

        if (in_array($class_name, $classes_needing_container)) {
            return new $class_name($this);
        }

        // For simple classes, just create them
        return new $class_name();
    }

    /**
     * Check if service is registered
     */
    public function has($service) {
        return isset($this->bindings[$service]);
    }

    /**
     * Get all registered services
     */
    public function get_services() {
        return array_keys($this->bindings);
    }

    /**
     * Register a service
     */
    public function bind($service, $class_name) {
        $this->bindings[$service] = $class_name;
    }

    /**
     * Register an instance directly
     */
    public function instance($service, $instance) {
        $this->instances[$service] = $instance;
    }

    /**
     * Clear all instances (for testing)
     */
    public function clear_instances() {
        $this->instances = [];
    }

    /**
     * Make service (create new instance every time)
     */
    public function make($service) {
        if (!isset($this->bindings[$service])) {
            throw new Exception("Service '{$service}' not found in container.");
        }

        $class_name = $this->bindings[$service];
        return $this->create_instance($class_name);
    }

    /**
     * Register singleton
     */
    public function singleton($service, $class_name) {
        $this->bind($service, $class_name);
        // All services in this basic version are singletons by default
    }
}
