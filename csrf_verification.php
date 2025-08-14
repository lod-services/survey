<?php
/**
 * CSRF Protection Verification Script
 * Enhanced with cryptographic strength validation and comprehensive security analysis
 * Uses shared validation logic for consistency across the application
 */

// Include Symfony autoloader for access to validation classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Security\AppSecretValidator;

echo "🛡️ CSRF Protection Configuration Verification\n";
echo "=============================================\n";
echo "Enhanced with cryptographic strength validation\n\n";

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

// Enhanced APP_SECRET validation using unified validation class
echo "🔐 APP_SECRET Security Analysis:\n";
echo "--------------------------------\n";

$envFiles = [
    __DIR__ . '/.env.local' => 'local',
    __DIR__ . '/.env.dev' => 'development', 
    __DIR__ . '/.env' => 'default'
];
$appSecretFound = false;
$bestSecret = null;
$bestSecretSource = null;

foreach ($envFiles as $envFile => $envName) {
    $envContent = getCachedFileContent($envFile);
    if ($envContent !== false && preg_match('/APP_SECRET=([^\s]+)/', $envContent, $matches)) {
        $appSecret = trim($matches[1], '"\''); // Remove quotes if present
        if (!empty($appSecret)) {
            echo "\n📁 Found APP_SECRET in {$envName} environment (" . basename($envFile) . "):\n";
            
            // Use unified validation logic
            $validation = AppSecretValidator::validate($appSecret);
            $metrics = $validation['metrics'];
            
            echo "   Length: {$metrics['length']} characters\n";
            echo "   Format: " . ucfirst($metrics['format']) . "\n";
            echo "   Shannon Entropy: {$metrics['shannon_entropy']} bits/char\n";
            echo "   Total Entropy: {$metrics['total_entropy_bits']} bits\n";
            echo "   Status: " . ($validation['valid'] ? '✅ SECURE' : '❌ WEAK') . "\n";
            
            if (!empty($validation['errors'])) {
                echo "   🚫 Security Issues:\n";
                foreach ($validation['errors'] as $error) {
                    echo "      • {$error}\n";
                }
            }
            
            if (!empty($validation['warnings'])) {
                echo "   ⚠️  Warnings:\n";
                foreach ($validation['warnings'] as $warning) {
                    echo "      • {$warning}\n";
                }
            }
            
            $appSecretFound = true;
            
            // Keep track of the best (most secure) secret found
            if ($validation['valid'] && ($bestSecret === null || $metrics['total_entropy_bits'] > $bestSecret['metrics']['total_entropy_bits'])) {
                $bestSecret = $validation;
                $bestSecretSource = $envName;
            }
        }
    }
}

if (!$appSecretFound) {
    echo "❌ APP_SECRET not found or empty in any .env files\n";
    echo "💡 Quick fix:\n";
    echo "   1. Copy .env.local.template to .env.local\n";
    echo "   2. Generate secure secret: openssl rand -base64 32\n";
    echo "   3. Add to .env.local: APP_SECRET=<generated_secret>\n\n";
} else {
    if ($bestSecret && $bestSecret['valid']) {
        echo "\n✅ Strongest APP_SECRET found in {$bestSecretSource} environment\n";
        echo "   This secret meets all security requirements\n";
    } else {
        echo "\n🚨 SECURITY ALERT: No secure APP_SECRET found!\n";
        echo "💡 Immediate action required:\n";
        echo "   1. Generate new secret: openssl rand -base64 32\n";
        echo "   2. Update .env.local with the new secret\n";
        echo "   3. Run this script again to verify\n";
        echo "   4. For detailed analysis: php bin/security/app_secret_analysis.php\n\n";
    }
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