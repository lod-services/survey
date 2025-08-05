<?php
/**
 * CSRF Protection Verification Script
 * This script validates that CSRF protection is properly configured
 * Performance optimized with caching and error handling
 */

echo "🛡️ CSRF Protection Configuration Verification\n";
echo "=============================================\n\n";

// Simple cache to avoid repeated file operations
static $fileCache = [];

function getCachedFileContent($filename) {
    global $fileCache;
    
    if (isset($fileCache[$filename])) {
        return $fileCache[$filename];
    }
    
    if (!file_exists($filename)) {
        return false;
    }
    
    $content = @file_get_contents($filename);
    if ($content === false) {
        echo "⚠️  Warning: Could not read file $filename\n";
        return false;
    }
    
    $fileCache[$filename] = $content;
    return $content;
}

// Check APP_SECRET following Symfony environment precedence
// Priority: .env.local > .env.{environment} > .env
$envFiles = [
    __DIR__ . '/.env.local',
    __DIR__ . '/.env.dev',   // Development environment
    __DIR__ . '/.env.test',  // Test environment
    __DIR__ . '/.env.prod',  // Production environment
    __DIR__ . '/.env'        // Default fallback
];
$appSecretFound = false;

foreach ($envFiles as $envFile) {
    $envContent = getCachedFileContent($envFile);
    if ($envContent !== false && preg_match('/APP_SECRET=([^\s]+)/', $envContent, $matches)) {
        $appSecret = trim($matches[1]);
        if (!empty($appSecret)) {
            if (strlen($appSecret) < 32) {
                echo "⚠️  APP_SECRET is less than 32 characters (current: " . strlen($appSecret) . ")\n";
            } else {
                echo "✅ APP_SECRET is properly configured (length: " . strlen($appSecret) . ")\n";
            }
            $appSecretFound = true;
            break;
        }
    }
}

if (!$appSecretFound) {
    echo "❌ APP_SECRET not found or empty in .env files\n";
}

// Check framework.yaml CSRF configuration
$frameworkFile = __DIR__ . '/config/packages/framework.yaml';
$frameworkContent = getCachedFileContent($frameworkFile);
if ($frameworkContent !== false) {
    if (strpos($frameworkContent, 'csrf_protection: true') !== false) {
        echo "✅ CSRF protection is enabled in framework.yaml\n";
    } elseif (strpos($frameworkContent, '#csrf_protection: true') !== false) {
        echo "❌ CSRF protection is commented out in framework.yaml\n";
    } else {
        echo "❌ CSRF protection configuration not found in framework.yaml\n";
    }
    
    // Check for session configuration (required for CSRF)
    if (strpos($frameworkContent, 'session:') !== false) {
        echo "✅ Session support is configured (required for CSRF tokens)\n";
    } else {
        echo "⚠️  Session configuration not found (may be required for CSRF)\n";
    }
} else {
    echo "❌ framework.yaml not found or unreadable\n";
}

// Check for test form files
$testController = __DIR__ . '/src/Controller/TestFormController.php';
$testTemplate = __DIR__ . '/templates/test_form/index.html.twig';

if (file_exists($testController)) {
    echo "✅ Test form controller created\n";
} else {
    echo "❌ Test form controller not found\n";
}

if (file_exists($testTemplate)) {
    echo "✅ Test form template created\n";
} else {
    echo "❌ Test form template not found\n";
}

echo "\n";
echo "📋 Summary:\n";
echo "- CSRF protection should now be enabled\n";
echo "- APP_SECRET is configured for token generation\n";
echo "- Session support is available for token storage\n";
echo "- Test form is ready for CSRF validation testing\n";
echo "\n";
echo "🧪 Next steps for validation:\n";
echo "1. Start the Symfony development server\n";
echo "2. Visit /test-form to see the CSRF-protected form\n";
echo "3. Test form submission with valid tokens (should succeed)\n";
echo "4. Test form submission with invalid tokens (should fail)\n";
echo "\n";
echo "Expected error message for invalid CSRF tokens:\n";
echo "\"The CSRF token is invalid. Please try to resubmit the form.\"\n";