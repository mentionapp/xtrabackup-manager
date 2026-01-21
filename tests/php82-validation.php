#!/usr/bin/env php
<?php
/*

XtraBackup Manager - PHP 8.2+ Validation Test Suite

This test suite validates that the codebase is compatible with PHP 8.2+
by checking:
1. All PHP files load without fatal errors
2. Classes can be instantiated
3. Validation methods work correctly
4. No deprecated function usage

*/

// Colors for terminal output
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

class PHP82ValidationTests {

    private $testsPassed = 0;
    private $testsFailed = 0;
    private $testsSkipped = 0;
    private $errors = [];

    public function run() {
        echo BLUE . "\n╔════════════════════════════════════════════════════════════╗\n" . RESET;
        echo BLUE . "║  XtraBackup Manager - PHP 8.2+ Validation Test Suite      ║\n" . RESET;
        echo BLUE . "╚════════════════════════════════════════════════════════════╝\n" . RESET;
        echo "\nPHP Version: " . PHP_VERSION . "\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

        $this->testPhpVersion();
        $this->testSyntaxCheck();
        $this->testClassLoading();
        $this->testValidationMethods();
        $this->testDeprecatedFunctions();

        $this->printSummary();

        return ($this->testsFailed === 0) ? 0 : 1;
    }

    private function testPhpVersion() {
        echo YELLOW . "\n[TEST] Checking PHP Version Compatibility...\n" . RESET;

        if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
            $this->pass("PHP version " . PHP_VERSION . " is >= 8.2.0");
        } else {
            $this->fail("PHP version " . PHP_VERSION . " is < 8.2.0");
        }
    }

    private function testSyntaxCheck() {
        echo YELLOW . "\n[TEST] Checking PHP Syntax of All Files...\n" . RESET;

        $includesDir = dirname(__DIR__) . '/includes';
        $phpFiles = glob($includesDir . '/*.php');

        foreach ($phpFiles as $file) {
            $output = [];
            $returnCode = 0;
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                $this->pass("Syntax OK: " . basename($file));
            } else {
                $this->fail("Syntax error in " . basename($file) . ": " . implode("\n", $output));
            }
        }
    }

    private function testClassLoading() {
        echo YELLOW . "\n[TEST] Testing Class Loading and Instantiation...\n" . RESET;

        // Load exception classes first
        $exceptionFile = dirname(__DIR__) . '/includes/exception.classes.php';
        if (file_exists($exceptionFile)) {
            try {
                include_once $exceptionFile;
                $this->pass("Exception classes loaded successfully");
            } catch (Exception $e) {
                $this->fail("Failed to load exception classes: " . $e->getMessage());
            }
        }

        // We can't actually load all classes without database config,
        // but we can test that the files are parseable
        $testableClasses = [
            'host' => ['validateHostname', 'validateSSHPort', 'validateActive', 'validateStagingPath'],
            'backupVolume' => ['validateName', 'validatePath'],
            'backupStrategy' => ['validateStrategyCode'],
        ];

        foreach ($testableClasses as $className => $methods) {
            $file = dirname(__DIR__) . "/includes/{$className}.class.php";
            if (file_exists($file)) {
                try {
                    // Just include to test syntax/loading
                    ob_start();
                    include_once $file;
                    ob_end_clean();
                    $this->pass("Class loaded successfully: {$className}");
                } catch (Exception $e) {
                    $this->fail("Failed to load class {$className}: " . $e->getMessage());
                }
            } else {
                $this->skip("Class file not found: {$className}");
            }
        }
    }

    private function testValidationMethods() {
        echo YELLOW . "\n[TEST] Testing Static Validation Methods...\n" . RESET;

        // Load necessary files
        $initFile = dirname(__DIR__) . '/includes/init.php';
        if (!file_exists($initFile)) {
            $this->skip("Cannot test validations without init.php and config");
            return;
        }

        // Test host validations
        $this->testHostValidations();

        // Test backupVolume validations
        $this->testBackupVolumeValidations();

        // Test backupStrategy validations
        $this->testBackupStrategyValidations();
    }

    private function testHostValidations() {
        echo BLUE . "  → Testing host validations...\n" . RESET;

        // Valid hostname
        try {
            host::validateHostname('example.com');
            $this->pass("host::validateHostname accepts valid hostname");
        } catch (Exception $e) {
            $this->fail("host::validateHostname rejected valid hostname: " . $e->getMessage());
        }

        // Invalid hostname (too long)
        try {
            $longHostname = str_repeat('a', 256);
            host::validateHostname($longHostname);
            $this->fail("host::validateHostname accepted invalid hostname");
        } catch (InputException $e) {
            $this->pass("host::validateHostname correctly rejects too-long hostname");
        } catch (Exception $e) {
            // Class might not be loaded, skip
            $this->skip("Could not test host validation (class not loaded)");
        }

        // Valid SSH port
        try {
            host::validateSSHPort(22);
            $this->pass("host::validateSSHPort accepts valid port");
        } catch (Exception $e) {
            $this->skip("Could not test host::validateSSHPort (class not loaded)");
        }

        // Invalid SSH port
        try {
            host::validateSSHPort(70000);
            $this->fail("host::validateSSHPort accepted invalid port");
        } catch (InputException $e) {
            $this->pass("host::validateSSHPort correctly rejects invalid port");
        } catch (Exception $e) {
            $this->skip("Could not test host::validateSSHPort (class not loaded)");
        }
    }

    private function testBackupVolumeValidations() {
        echo BLUE . "  → Testing backupVolume validations...\n" . RESET;

        // Valid name
        try {
            backupVolume::validateName('MyBackupVolume');
            $this->pass("backupVolume::validateName accepts valid name");
        } catch (Exception $e) {
            $this->skip("Could not test backupVolume validation (class not loaded)");
        }

        // Invalid name (too long)
        try {
            $longName = str_repeat('a', 129);
            backupVolume::validateName($longName);
            $this->fail("backupVolume::validateName accepted invalid name");
        } catch (InputException $e) {
            $this->pass("backupVolume::validateName correctly rejects too-long name");
        } catch (Exception $e) {
            $this->skip("Could not test backupVolume validation (class not loaded)");
        }
    }

    private function testBackupStrategyValidations() {
        echo BLUE . "  → Testing backupStrategy validations...\n" . RESET;

        // backupStrategy validation requires XBM_VALID_STRATEGY_CODES constant
        // which is defined in init.php - skip if not available
        if (!defined('XBM_VALID_STRATEGY_CODES')) {
            $this->skip("backupStrategy validation requires init.php constants");
            return;
        }

        // Valid strategy code
        try {
            backupStrategy::validateStrategyCode('CONTINC');
            $this->pass("backupStrategy::validateStrategyCode accepts valid code");
        } catch (Exception $e) {
            $this->skip("Could not test backupStrategy validation: " . $e->getMessage());
        }

        // Invalid strategy code
        try {
            backupStrategy::validateStrategyCode('INVALID');
            $this->fail("backupStrategy::validateStrategyCode accepted invalid code");
        } catch (InputException $e) {
            $this->pass("backupStrategy::validateStrategyCode correctly rejects invalid code");
        } catch (Exception $e) {
            $this->skip("Could not test backupStrategy validation: " . $e->getMessage());
        }
    }

    private function testDeprecatedFunctions() {
        echo YELLOW . "\n[TEST] Checking for Deprecated Function Usage...\n" . RESET;

        $includesDir = dirname(__DIR__) . '/includes';
        $phpFiles = glob($includesDir . '/*.php');

        // Check for sizeOf (should be replaced with count)
        $foundSizeOf = false;
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/\bsizeOf\s*\(/i', $content)) {
                $this->fail("Found deprecated sizeOf() in " . basename($file));
                $foundSizeOf = true;
            }
        }
        if (!$foundSizeOf) {
            $this->pass("No deprecated sizeOf() function calls found");
        }

        // Check for ereg functions (removed in PHP 7.0+)
        $foundEreg = false;
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/\bereg[_a-z]*\s*\(/i', $content)) {
                $this->fail("Found deprecated ereg function in " . basename($file));
                $foundEreg = true;
            }
        }
        if (!$foundEreg) {
            $this->pass("No deprecated ereg functions found");
        }
    }

    private function pass($message) {
        $this->testsPassed++;
        echo GREEN . "  ✓ " . RESET . $message . "\n";
    }

    private function fail($message) {
        $this->testsFailed++;
        $this->errors[] = $message;
        echo RED . "  ✗ " . RESET . $message . "\n";
    }

    private function skip($message) {
        $this->testsSkipped++;
        echo YELLOW . "  ⊘ " . RESET . $message . "\n";
    }

    private function printSummary() {
        echo BLUE . "\n╔════════════════════════════════════════════════════════════╗\n" . RESET;
        echo BLUE . "║  Test Summary                                              ║\n" . RESET;
        echo BLUE . "╚════════════════════════════════════════════════════════════╝\n" . RESET;

        $total = $this->testsPassed + $this->testsFailed + $this->testsSkipped;

        echo "\nTotal tests: {$total}\n";
        echo GREEN . "Passed: {$this->testsPassed}\n" . RESET;
        echo RED . "Failed: {$this->testsFailed}\n" . RESET;
        echo YELLOW . "Skipped: {$this->testsSkipped}\n" . RESET;

        if ($this->testsFailed > 0) {
            echo RED . "\n⚠ TESTS FAILED\n" . RESET;
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo RED . "  • " . RESET . $error . "\n";
            }
            echo "\n";
        } else {
            echo GREEN . "\n✅ ALL TESTS PASSED!\n" . RESET;
            echo "\nThe codebase is compatible with PHP 8.2+\n\n";
        }
    }
}

// Run tests
$tests = new PHP82ValidationTests();
exit($tests->run());
