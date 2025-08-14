<?php

namespace App\Security;

/**
 * APP_SECRET Cryptographic Strength Validator
 * 
 * Validates APP_SECRET tokens for cryptographic strength according to security requirements:
 * - Minimum entropy: 256 bits (32 bytes)
 * - Minimum length: 64+ characters for base64 encoding
 * - Character distribution analysis
 * - Shannon entropy calculation
 * 
 * Supports both base64 and hexadecimal encoded secrets.
 */
class AppSecretValidator
{
    // Security requirements
    public const MIN_ENTROPY_BITS = 256;
    public const MIN_LENGTH_BASE64 = 44; // 32 bytes * 4/3 (base64 encoding)
    public const MIN_LENGTH_HEX = 64;    // 32 bytes * 2 (hex encoding)
    public const MIN_LENGTH_GENERAL = 44; // General minimum for strong secrets (reduced to accommodate base64)
    
    // Entropy thresholds
    public const MIN_SHANNON_ENTROPY = 4.0; // bits per character
    public const GOOD_SHANNON_ENTROPY = 5.0; // bits per character
    
    /**
     * Validation result structure
     */
    public static function validate(string $secret): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'metrics' => [],
            'recommendations' => []
        ];
        
        // Basic presence check
        if (empty($secret)) {
            $result['errors'][] = 'APP_SECRET is empty or not configured';
            $result['recommendations'][] = 'Generate a secure secret with: openssl rand -base64 32';
            return $result;
        }
        
        // Calculate metrics
        $length = strlen($secret);
        $shannonEntropy = self::calculateShannonEntropy($secret);
        $totalEntropyBits = $shannonEntropy * $length;
        $format = self::detectFormat($secret);
        $charDistribution = self::analyzeCharacterDistribution($secret);
        
        $result['metrics'] = [
            'length' => $length,
            'shannon_entropy' => round($shannonEntropy, 2),
            'total_entropy_bits' => round($totalEntropyBits, 2),
            'format' => $format,
            'character_distribution' => $charDistribution
        ];
        
        // Length validation
        if ($length < self::MIN_LENGTH_GENERAL) {
            $result['errors'][] = sprintf(
                'APP_SECRET is too short (%d characters). Minimum required: %d characters',
                $length,
                self::MIN_LENGTH_GENERAL
            );
            $result['recommendations'][] = 'Generate a longer secret with: openssl rand -base64 32';
        }
        
        // Format-specific length validation
        if ($format === 'base64' && $length < self::MIN_LENGTH_BASE64) {
            $result['warnings'][] = sprintf(
                'Base64 secret should be at least %d characters for 256-bit strength (current: %d)',
                self::MIN_LENGTH_BASE64,
                $length
            );
        } elseif ($format === 'hex' && $length < self::MIN_LENGTH_HEX) {
            $result['warnings'][] = sprintf(
                'Hexadecimal secret should be at least %d characters for 256-bit strength (current: %d)',
                self::MIN_LENGTH_HEX,
                $length
            );
        }
        
        // Entropy validation with format-specific adjustments
        $effectiveEntropy = $totalEntropyBits;
        
        // For properly formatted secrets, estimate theoretical entropy
        if ($format === 'base64' && $length >= self::MIN_LENGTH_BASE64) {
            // Base64 encoding of 32 bytes should provide ~256 bits
            $theoreticalBytes = floor(($length * 3) / 4); // Approximate decoded length
            $theoreticalEntropy = $theoreticalBytes * 8;
            $effectiveEntropy = max($totalEntropyBits, $theoreticalEntropy);
        } elseif ($format === 'hex' && $length >= self::MIN_LENGTH_HEX) {
            // Hex encoding: each 2 characters = 1 byte = 8 bits
            $theoreticalBytes = floor($length / 2);
            $theoreticalEntropy = $theoreticalBytes * 8;
            $effectiveEntropy = max($totalEntropyBits, $theoreticalEntropy);
        }
        
        if ($effectiveEntropy < self::MIN_ENTROPY_BITS) {
            $result['errors'][] = sprintf(
                'APP_SECRET has insufficient entropy (%.1f bits effective). Minimum required: %d bits',
                $effectiveEntropy,
                self::MIN_ENTROPY_BITS
            );
            $result['recommendations'][] = 'Use a cryptographically secure generator: openssl rand -base64 32';
        }
        
        // Update metrics with effective entropy
        $result['metrics']['effective_entropy_bits'] = round($effectiveEntropy, 2);
        
        // Apply format-specific Shannon entropy thresholds
        $minShannonForFormat = self::MIN_SHANNON_ENTROPY;
        $goodShannonForFormat = self::GOOD_SHANNON_ENTROPY;
        
        if ($format === 'hex') {
            // Hex has theoretical max of log2(16) = 4.0, practical good threshold ~3.5
            $minShannonForFormat = 3.0;
            $goodShannonForFormat = 3.7;
        } elseif ($format === 'base64') {
            // Base64 has theoretical max of log2(64) = 6.0, practical good threshold ~5.0
            $minShannonForFormat = 4.5;
            $goodShannonForFormat = 5.2;
        }
        
        if ($shannonEntropy < $minShannonForFormat) {
            $result['errors'][] = sprintf(
                'APP_SECRET has poor character entropy (%.2f bits/char). Minimum for %s: %.1f bits/char',
                $shannonEntropy,
                $format,
                $minShannonForFormat
            );
        } elseif ($shannonEntropy < $goodShannonForFormat) {
            $result['warnings'][] = sprintf(
                'APP_SECRET entropy could be improved (%.2f bits/char). Good threshold for %s: %.1f bits/char',
                $shannonEntropy,
                $format,
                $goodShannonForFormat
            );
        }
        
        // Character distribution analysis
        if ($charDistribution['repeated_chars'] > 0) {
            $result['warnings'][] = sprintf(
                'Secret contains repeated character patterns (%d repetitions detected)',
                $charDistribution['repeated_chars']
            );
        }
        
        if ($charDistribution['low_diversity'] && $format !== 'hex') {
            // Hex naturally has lower diversity (only 16 possible characters)
            $result['warnings'][] = 'Secret has low character diversity - consider regenerating';
        }
        
        // Pattern detection
        if (self::detectWeakPatterns($secret)) {
            $result['errors'][] = 'Secret contains weak patterns (sequential, repeated, or predictable data)';
            $result['recommendations'][] = 'Generate a new secret with proper randomness: openssl rand -base64 32';
        }
        
        // Overall validation
        $result['valid'] = empty($result['errors']);
        
        // Add generation recommendations if needed
        if (!$result['valid'] || !empty($result['warnings'])) {
            $result['recommendations'][] = 'For base64: openssl rand -base64 32';
            $result['recommendations'][] = 'For hex: openssl rand -hex 32';
            $result['recommendations'] = array_unique($result['recommendations']);
        }
        
        return $result;
    }
    
    /**
     * Calculate Shannon entropy (bits per character)
     */
    private static function calculateShannonEntropy(string $data): float
    {
        if (empty($data)) {
            return 0.0;
        }
        
        $length = strlen($data);
        $frequencies = [];
        
        // Count character frequencies
        for ($i = 0; $i < $length; $i++) {
            $char = $data[$i];
            $frequencies[$char] = ($frequencies[$char] ?? 0) + 1;
        }
        
        // Calculate Shannon entropy
        $entropy = 0.0;
        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }
    
    /**
     * Detect the format of the secret (base64, hex, or unknown)
     */
    private static function detectFormat(string $secret): string
    {
        // Remove potential padding and check patterns
        $trimmed = rtrim($secret, '=');
        
        // Hex pattern: only hexadecimal characters (check first, more specific)
        if (preg_match('/^[0-9a-fA-F]+$/', $secret)) {
            return 'hex';
        }
        
        // Base64 pattern: alphanumeric + / + (optional padding =)
        if (preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $secret) && strlen($secret) % 4 === 0) {
            return 'base64';
        }
        
        // Check if it looks like URL-safe base64
        if (preg_match('/^[A-Za-z0-9_-]+$/', $secret)) {
            return 'base64url';
        }
        
        return 'unknown';
    }
    
    /**
     * Analyze character distribution for patterns and diversity
     */
    private static function analyzeCharacterDistribution(string $secret): array
    {
        $length = strlen($secret);
        $charCounts = [];
        $uniqueChars = 0;
        $repeatedChars = 0;
        
        // Count characters
        for ($i = 0; $i < $length; $i++) {
            $char = $secret[$i];
            $charCounts[$char] = ($charCounts[$char] ?? 0) + 1;
        }
        
        $uniqueChars = count($charCounts);
        
        // Count characters that appear more than once
        foreach ($charCounts as $count) {
            if ($count > 1) {
                $repeatedChars += $count - 1;
            }
        }
        
        // Diversity analysis
        $diversityRatio = $uniqueChars / $length;
        $lowDiversity = $diversityRatio < 0.7; // Less than 70% unique characters
        
        return [
            'unique_chars' => $uniqueChars,
            'repeated_chars' => $repeatedChars,
            'diversity_ratio' => round($diversityRatio, 3),
            'low_diversity' => $lowDiversity,
            'char_counts' => $charCounts
        ];
    }
    
    /**
     * Detect weak patterns in the secret
     */
    private static function detectWeakPatterns(string $secret): bool
    {
        // Check for sequential patterns (123, abc, etc.)
        if (preg_match('/(?:123|abc|xyz|012)/i', $secret)) {
            return true;
        }
        
        // Check for repeated substrings (only longer patterns that are more suspicious)
        $length = strlen($secret);
        for ($i = 6; $i <= min(12, $length / 2); $i++) {
            $pattern = substr($secret, 0, $i);
            if (strpos($secret, $pattern, $i) !== false) {
                return true;
            }
        }
        
        // Check for obvious repeated patterns within the string
        if (preg_match('/(.{4,})\1/', $secret)) {
            return true;
        }
        
        // Check for common weak patterns
        $weakPatterns = [
            'password', 'secret', 'key', 'admin', 'test', 'demo',
            '000000', '111111', 'aaaaaa', 'AAAAAA'
        ];
        
        foreach ($weakPatterns as $pattern) {
            if (stripos($secret, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a human-readable validation summary
     */
    public static function getValidationSummary(array $validationResult): string
    {
        $metrics = $validationResult['metrics'];
        $summary = [];
        
        $summary[] = "APP_SECRET Validation Report:";
        $summary[] = "================================";
        $summary[] = sprintf("Length: %d characters", $metrics['length']);
        $summary[] = sprintf("Format: %s", ucfirst($metrics['format']));
        $summary[] = sprintf("Shannon Entropy: %.2f bits/char", $metrics['shannon_entropy']);
        $summary[] = sprintf("Total Entropy: %.1f bits", $metrics['total_entropy_bits']);
        $summary[] = sprintf("Status: %s", $validationResult['valid'] ? 'VALID ✅' : 'INVALID ❌');
        
        if (!empty($validationResult['errors'])) {
            $summary[] = "\nErrors:";
            foreach ($validationResult['errors'] as $error) {
                $summary[] = "❌ " . $error;
            }
        }
        
        if (!empty($validationResult['warnings'])) {
            $summary[] = "\nWarnings:";
            foreach ($validationResult['warnings'] as $warning) {
                $summary[] = "⚠️  " . $warning;
            }
        }
        
        if (!empty($validationResult['recommendations'])) {
            $summary[] = "\nRecommendations:";
            foreach ($validationResult['recommendations'] as $recommendation) {
                $summary[] = "💡 " . $recommendation;
            }
        }
        
        return implode("\n", $summary);
    }
    
    /**
     * Quick validation check - returns true if secret meets minimum requirements
     */
    public static function isValid(string $secret): bool
    {
        $result = self::validate($secret);
        return $result['valid'];
    }
    
    /**
     * Generate a secure APP_SECRET
     */
    public static function generateSecureSecret(string $format = 'base64'): string
    {
        $bytes = random_bytes(32); // 256 bits
        
        switch ($format) {
            case 'hex':
                return bin2hex($bytes);
            case 'base64url':
                return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
            case 'base64':
            default:
                return base64_encode($bytes);
        }
    }
}