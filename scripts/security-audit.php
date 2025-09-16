#!/usr/bin/env php
<?php
// © After Dark Systems
// Security Audit Script - Comprehensive security check for VeriBits

declare(strict_types=1);

require_once __DIR__ . '/../app/src/Utils/Config.php';
require_once __DIR__ . '/../app/src/Utils/Logger.php';

use VeriBits\Utils\Config;
use VeriBits\Utils\Logger;

class SecurityAudit {
    private array $issues = [];
    private array $warnings = [];
    private array $passed = [];

    public function run(): void {
        echo "🔒 VeriBits Security Audit\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        Config::load();

        $this->checkEnvironmentConfiguration();
        $this->checkFilePermissions();
        $this->checkDatabaseSecurity();
        $this->checkJWTSecurity();
        $this->checkHTTPSSecurity();
        $this->checkInputValidation();
        $this->checkRateLimiting();
        $this->checkLogging();
        $this->checkDependencies();
        $this->checkProductionReadiness();

        $this->generateReport();
    }

    private function checkEnvironmentConfiguration(): void {
        echo "📋 Checking Environment Configuration...\n";

        // Check critical environment variables
        $requiredVars = [
            'JWT_SECRET',
            'DB_PASSWORD',
            'REDIS_PASSWORD'
        ];

        foreach ($requiredVars as $var) {
            $value = Config::get($var);
            if (empty($value) || $value === 'change-this-in-production' || $value === 'dev-secret') {
                $this->addIssue("CRITICAL: $var is not set or uses default value");
            } else {
                $this->addPassed("$var is properly configured");
            }
        }

        // Check JWT secret strength
        $jwtSecret = Config::get('JWT_SECRET');
        if (strlen($jwtSecret) < 32) {
            $this->addIssue("CRITICAL: JWT_SECRET should be at least 32 characters long");
        } elseif (strlen($jwtSecret) < 64) {
            $this->addWarning("JWT_SECRET should be at least 64 characters for maximum security");
        } else {
            $this->addPassed("JWT_SECRET has adequate length");
        }

        // Check environment type
        if (Config::getBool('APP_DEBUG') && Config::get('APP_ENV') === 'production') {
            $this->addIssue("CRITICAL: APP_DEBUG is enabled in production");
        } else {
            $this->addPassed("APP_DEBUG is properly configured");
        }

        echo "   ✓ Environment configuration checked\n\n";
    }

    private function checkFilePermissions(): void {
        echo "📁 Checking File Permissions...\n";

        $sensitiveFiles = [
            __DIR__ . '/../app/config/.env',
            __DIR__ . '/../app/config/.env.production',
            __DIR__ . '/../app/logs/',
        ];

        foreach ($sensitiveFiles as $file) {
            if (file_exists($file)) {
                $perms = substr(sprintf('%o', fileperms($file)), -4);

                if (is_dir($file)) {
                    if ($perms !== '0755' && $perms !== '0750') {
                        $this->addWarning("Directory $file has permissions $perms (recommended: 0755 or 0750)");
                    } else {
                        $this->addPassed("Directory $file has secure permissions");
                    }
                } else {
                    if ($perms !== '0644' && $perms !== '0640' && $perms !== '0600') {
                        $this->addWarning("File $file has permissions $perms (recommended: 0644, 0640, or 0600)");
                    } else {
                        $this->addPassed("File $file has secure permissions");
                    }
                }
            }
        }

        // Check if sensitive files are web-accessible
        $webRoot = __DIR__ . '/../app/public/';
        $sensitiveInWeb = [
            $webRoot . '.env',
            $webRoot . 'config/',
            $webRoot . 'logs/',
        ];

        foreach ($sensitiveInWeb as $file) {
            if (file_exists($file)) {
                $this->addIssue("CRITICAL: Sensitive file/directory $file is in web root");
            } else {
                $this->addPassed("Sensitive files properly isolated from web root");
                break;
            }
        }

        echo "   ✓ File permissions checked\n\n";
    }

    private function checkDatabaseSecurity(): void {
        echo "🗄️  Checking Database Security...\n";

        $dbPassword = Config::get('DB_PASSWORD');
        if (strlen($dbPassword) < 12) {
            $this->addIssue("CRITICAL: Database password is too short (minimum 12 characters)");
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $dbPassword)) {
            $this->addWarning("Database password should contain uppercase, lowercase, numbers, and special characters");
        } else {
            $this->addPassed("Database password meets complexity requirements");
        }

        // Check if using default database credentials
        $dbUser = Config::get('DB_USERNAME');
        $dbName = Config::get('DB_DATABASE');

        if (in_array($dbUser, ['root', 'admin', 'postgres', 'veribits'])) {
            $this->addWarning("Consider using a non-obvious database username");
        } else {
            $this->addPassed("Database username is not using common defaults");
        }

        if ($dbName === 'veribits' && Config::isProduction()) {
            $this->addWarning("Consider using a non-obvious database name in production");
        }

        echo "   ✓ Database security checked\n\n";
    }

    private function checkJWTSecurity(): void {
        echo "🔑 Checking JWT Security...\n";

        $jwtSecret = Config::get('JWT_SECRET');

        // Check for common weak secrets
        $weakSecrets = ['secret', 'password', 'jwt-secret', 'veribits', '123456'];
        if (in_array(strtolower($jwtSecret), $weakSecrets)) {
            $this->addIssue("CRITICAL: JWT secret is using a common weak value");
        } else {
            $this->addPassed("JWT secret is not using common weak values");
        }

        // Check entropy
        $entropy = $this->calculateEntropy($jwtSecret);
        if ($entropy < 4.5) {
            $this->addIssue("CRITICAL: JWT secret has low entropy ($entropy)");
        } elseif ($entropy < 5.5) {
            $this->addWarning("JWT secret entropy could be higher ($entropy)");
        } else {
            $this->addPassed("JWT secret has good entropy ($entropy)");
        }

        echo "   ✓ JWT security checked\n\n";
    }

    private function checkHTTPSSecurity(): void {
        echo "🔐 Checking HTTPS Security...\n";

        if (Config::isProduction()) {
            if (!Config::getBool('FORCE_HTTPS')) {
                $this->addIssue("CRITICAL: FORCE_HTTPS should be enabled in production");
            } else {
                $this->addPassed("HTTPS enforcement is enabled");
            }

            if (!Config::getBool('SESSION_SECURE')) {
                $this->addIssue("CRITICAL: SESSION_SECURE should be enabled in production");
            } else {
                $this->addPassed("Secure session cookies are enabled");
            }

            $hstsMaxAge = Config::getInt('HSTS_MAX_AGE');
            if ($hstsMaxAge < 31536000) { // 1 year
                $this->addWarning("HSTS max-age should be at least 31536000 (1 year)");
            } else {
                $this->addPassed("HSTS is properly configured");
            }
        } else {
            $this->addPassed("HTTPS checks skipped for non-production environment");
        }

        echo "   ✓ HTTPS security checked\n\n";
    }

    private function checkInputValidation(): void {
        echo "✅ Checking Input Validation...\n";

        // Check if validation classes exist
        $validationClass = __DIR__ . '/../app/src/Utils/Validator.php';
        if (!file_exists($validationClass)) {
            $this->addIssue("CRITICAL: Validator utility class not found");
        } else {
            $this->addPassed("Input validation framework is present");
        }

        // Check for dangerous PHP functions
        $dangerousFunctions = ['exec', 'system', 'shell_exec', 'passthru', 'eval'];
        $phpFiles = glob(__DIR__ . '/../app/src/**/*.php');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            foreach ($dangerousFunctions as $func) {
                if (strpos($content, $func . '(') !== false) {
                    $this->addWarning("Potentially dangerous function '$func' found in $file");
                }
            }
        }

        echo "   ✓ Input validation checked\n\n";
    }

    private function checkRateLimiting(): void {
        echo "⏱️  Checking Rate Limiting...\n";

        $rateLimitClass = __DIR__ . '/../app/src/Utils/RateLimit.php';
        if (!file_exists($rateLimitClass)) {
            $this->addIssue("CRITICAL: Rate limiting implementation not found");
        } else {
            $this->addPassed("Rate limiting framework is present");
        }

        // Check Redis configuration for rate limiting
        $redisHost = Config::get('REDIS_HOST');
        if (empty($redisHost)) {
            $this->addWarning("Redis not configured - rate limiting may not work properly");
        } else {
            $this->addPassed("Redis is configured for rate limiting");
        }

        echo "   ✓ Rate limiting checked\n\n";
    }

    private function checkLogging(): void {
        echo "📝 Checking Logging Configuration...\n";

        $logDir = __DIR__ . '/../app/logs/';
        if (!is_dir($logDir)) {
            $this->addWarning("Log directory does not exist");
        } elseif (!is_writable($logDir)) {
            $this->addIssue("CRITICAL: Log directory is not writable");
        } else {
            $this->addPassed("Log directory is properly configured");
        }

        $loggerClass = __DIR__ . '/../app/src/Utils/Logger.php';
        if (!file_exists($loggerClass)) {
            $this->addIssue("CRITICAL: Logger utility class not found");
        } else {
            $this->addPassed("Logging framework is present");
        }

        echo "   ✓ Logging configuration checked\n\n";
    }

    private function checkDependencies(): void {
        echo "📦 Checking Dependencies...\n";

        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.1.0', '<')) {
            $this->addWarning("PHP version $phpVersion is older than recommended (8.1+)");
        } else {
            $this->addPassed("PHP version $phpVersion is supported");
        }

        // Check required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_pgsql', 'redis', 'json', 'mbstring', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->addIssue("CRITICAL: Required PHP extension '$ext' is not loaded");
            } else {
                $this->addPassed("PHP extension '$ext' is loaded");
            }
        }

        echo "   ✓ Dependencies checked\n\n";
    }

    private function checkProductionReadiness(): void {
        echo "🚀 Checking Production Readiness...\n";

        if (Config::isProduction()) {
            // Check error reporting
            if (ini_get('display_errors')) {
                $this->addIssue("CRITICAL: display_errors is enabled in production");
            } else {
                $this->addPassed("Error display is disabled in production");
            }

            // Check expose_php
            if (ini_get('expose_php')) {
                $this->addWarning("expose_php is enabled - consider disabling to hide PHP version");
            } else {
                $this->addPassed("PHP version exposure is disabled");
            }

            // Check session configuration
            if (ini_get('session.cookie_httponly') != '1') {
                $this->addIssue("CRITICAL: session.cookie_httponly should be enabled");
            } else {
                $this->addPassed("HTTP-only session cookies are enabled");
            }
        }

        echo "   ✓ Production readiness checked\n\n";
    }

    private function calculateEntropy(string $string): float {
        $chars = str_split($string);
        $charCounts = array_count_values($chars);
        $length = strlen($string);
        $entropy = 0;

        foreach ($charCounts as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return round($entropy, 2);
    }

    private function addIssue(string $message): void {
        $this->issues[] = $message;
    }

    private function addWarning(string $message): void {
        $this->warnings[] = $message;
    }

    private function addPassed(string $message): void {
        $this->passed[] = $message;
    }

    private function generateReport(): void {
        echo "📊 SECURITY AUDIT REPORT\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        // Summary
        $totalChecks = count($this->issues) + count($this->warnings) + count($this->passed);
        $criticalIssues = count($this->issues);
        $warnings = count($this->warnings);
        $passed = count($this->passed);

        echo "Summary:\n";
        echo "  Total Checks: $totalChecks\n";
        echo "  🔴 Critical Issues: $criticalIssues\n";
        echo "  🟡 Warnings: $warnings\n";
        echo "  🟢 Passed: $passed\n\n";

        // Critical Issues
        if (!empty($this->issues)) {
            echo "🔴 CRITICAL ISSUES (Must fix before production):\n";
            foreach ($this->issues as $issue) {
                echo "  ❌ $issue\n";
            }
            echo "\n";
        }

        // Warnings
        if (!empty($this->warnings)) {
            echo "🟡 WARNINGS (Recommended to fix):\n";
            foreach ($this->warnings as $warning) {
                echo "  ⚠️  $warning\n";
            }
            echo "\n";
        }

        // Security Score
        $maxScore = 100;
        $score = max(0, $maxScore - ($criticalIssues * 20) - ($warnings * 5));

        echo "🏆 SECURITY SCORE: $score/$maxScore\n";

        if ($score >= 90) {
            echo "✅ Excellent security posture!\n";
        } elseif ($score >= 75) {
            echo "✅ Good security posture with minor improvements needed.\n";
        } elseif ($score >= 50) {
            echo "⚠️  Moderate security posture. Address warnings and critical issues.\n";
        } else {
            echo "❌ Poor security posture. Immediate attention required!\n";
        }

        // Production Readiness
        echo "\n🚀 PRODUCTION READINESS: ";
        if ($criticalIssues === 0) {
            echo "✅ READY\n";
            echo "All critical security issues have been addressed.\n";
        } else {
            echo "❌ NOT READY\n";
            echo "Fix all critical issues before deploying to production.\n";
        }

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Audit completed on " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the audit
$audit = new SecurityAudit();
$audit->run();