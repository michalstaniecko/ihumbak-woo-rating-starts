<?php
/**
 * Autoloader for plugin classes
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'Ihumbak_WRS_';
    $base_dir = IHUMBAK_WRS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    
    // Check in admin directory
    if (!file_exists($file)) {
        $file = IHUMBAK_WRS_PLUGIN_DIR . 'admin/class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    }
    
    // Check in public directory
    if (!file_exists($file)) {
        $file = IHUMBAK_WRS_PLUGIN_DIR . 'public/class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    }
    
    // Check in database directory
    if (!file_exists($file)) {
        $file = IHUMBAK_WRS_PLUGIN_DIR . 'database/class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    }
    
    if (file_exists($file)) {
        require $file;
    }
});
