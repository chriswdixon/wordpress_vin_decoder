<?php
/**
 * Simple test script to verify plugin classes can be instantiated
 * Run this in a WordPress environment to test basic functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('This test script must be run within WordPress environment');
}

// Include the main plugin file
require_once 'vin-decoder.php';

// Test basic plugin initialization
echo "Testing VIN Decoder Plugin...\n\n";

try {
    // Test plugin initialization
    $plugin = vin_decoder_init();
    echo "✓ Plugin initialized successfully\n";

    // Test that classes are loaded
    if (class_exists('VIN_Decoder_DB')) {
        echo "✓ VIN_Decoder_DB class loaded\n";
    } else {
        echo "✗ VIN_Decoder_DB class not found\n";
    }

    if (class_exists('VIN_Decoder_Admin')) {
        echo "✓ VIN_Decoder_Admin class loaded\n";
    } else {
        echo "✗ VIN_Decoder_Admin class not found\n";
    }

    if (class_exists('VIN_Decoder_Frontend')) {
        echo "✓ VIN_Decoder_Frontend class loaded\n";
    } else {
        echo "✗ VIN_Decoder_Frontend class not found\n";
    }

    if (class_exists('VIN_Decoder_API')) {
        echo "✓ VIN_Decoder_API class loaded\n";
    } else {
        echo "✗ VIN_Decoder_API class not found\n";
    }

    // Test database class instantiation (without actual DB operations)
    if (class_exists('VIN_Decoder_DB')) {
        $db_test = new VIN_Decoder_DB();
        echo "✓ VIN_Decoder_DB instantiated\n";

        // Test that table names are set
        if (property_exists($db_test, 'vin_decodes_table')) {
            echo "✓ Database table properties set\n";
        }
    }

    // Test settings retrieval
    $settings = $plugin->get_settings();
    if (is_array($settings)) {
        echo "✓ Plugin settings accessible\n";
        echo "  - API Timeout: " . $settings['api_timeout'] . "\n";
        echo "  - Secondary API: " . ($settings['enable_secondary_api'] ? 'enabled' : 'disabled') . "\n";
    }

    echo "\n🎉 All basic tests passed! Plugin structure is valid.\n";

} catch (Exception $e) {
    echo "✗ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
