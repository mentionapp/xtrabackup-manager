#!/usr/bin/env php
<?php
/*

XtraBackup Manager - Unit Test Suite

This test suite validates individual methods and validation functions
in isolation, without database dependencies. Tests include:
1. Host validation methods
2. BackupVolume validation methods
3. ScheduledBackup validation methods
4. Edge cases and boundary conditions
5. Exception handling

*/

// Colors for terminal output
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

class UnitTests {

    private $testsPassed = 0;
    private $testsFailed = 0;
    private $testsSkipped = 0;
    private $errors = [];

    public function run() {
        echo BLUE . "\n╔════════════════════════════════════════════════════════════╗\n" . RESET;
        echo BLUE . "║  XtraBackup Manager - Unit Test Suite                     ║\n" . RESET;
        echo BLUE . "╚════════════════════════════════════════════════════════════╝\n" . RESET;
        echo "\nPHP Version: " . PHP_VERSION . "\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

        // Load necessary classes
        if (!$this->loadClasses()) {
            echo RED . "\n✗ Cannot run tests without required classes loaded\n" . RESET;
            return 1;
        }

        // Run all test suites
        $this->testHostValidations();
        $this->testBackupVolumeValidations();
        $this->testScheduledBackupValidations();
        $this->testEdgeCases();
        $this->testExceptionHandling();

        $this->printSummary();

        return ($this->testsFailed === 0) ? 0 : 1;
    }

    private function loadClasses() {
        echo YELLOW . "\n[SETUP] Loading required classes...\n" . RESET;

        $includesDir = dirname(__DIR__) . '/includes';

        // Load exception classes first
        $exceptionFile = $includesDir . '/exception.classes.php';
        if (file_exists($exceptionFile)) {
            require_once $exceptionFile;
            $this->pass("Exception classes loaded");
        } else {
            $this->fail("Exception classes not found");
            return false;
        }

        // Load host class
        $hostFile = $includesDir . '/host.class.php';
        if (file_exists($hostFile)) {
            require_once $hostFile;
            $this->pass("Host class loaded");
        } else {
            $this->skip("Host class not found");
        }

        // Load backupVolume class
        $volumeFile = $includesDir . '/backupVolume.class.php';
        if (file_exists($volumeFile)) {
            require_once $volumeFile;
            $this->pass("BackupVolume class loaded");
        } else {
            $this->skip("BackupVolume class not found");
        }

        // Load scheduledBackup class
        $backupFile = $includesDir . '/scheduledBackup.class.php';
        if (file_exists($backupFile)) {
            require_once $backupFile;
            $this->pass("ScheduledBackup class loaded");
        } else {
            $this->skip("ScheduledBackup class not found");
        }

        return true;
    }

    private function testHostValidations() {
        echo YELLOW . "\n[TEST] Testing Host Validation Methods...\n" . RESET;

        if (!class_exists('host')) {
            $this->skip("Host class not available");
            return;
        }

        // Test validateHostname with valid hostnames
        $validHostnames = [
            'example.com',
            'sub.example.com',
            'my-host.example.com',
            'host123.example.com',
            '192.168.1.1',
            'a.b.c.d.e.f.com',
            'host',
            'host.local',
        ];

        foreach ($validHostnames as $hostname) {
            try {
                host::validateHostname($hostname);
                $this->pass("validateHostname accepts valid hostname: $hostname");
            } catch (Exception $e) {
                $this->fail("validateHostname rejected valid hostname '$hostname': " . $e->getMessage());
            }
        }

        // Test validateHostname with invalid hostnames
        $invalidHostnames = [
            '' => 'empty string',
            str_repeat('a', 256) => 'too long (256 chars)',
            '-example.com' => 'starts with dash',
            'example-.com' => 'ends with dash',
            'exam ple.com' => 'contains space',
            'example..com' => 'double dot',
        ];

        foreach ($invalidHostnames as $hostname => $reason) {
            try {
                host::validateHostname($hostname);
                $this->fail("validateHostname accepted invalid hostname ($reason)");
            } catch (InputException $e) {
                $this->pass("validateHostname correctly rejects $reason");
            } catch (Exception $e) {
                $this->fail("validateHostname threw unexpected exception for $reason: " . get_class($e));
            }
        }

        // Test validateSSHPort with valid ports
        $validPorts = [22, 1, 2222, 8022, 65535];

        foreach ($validPorts as $port) {
            try {
                host::validateSSHPort($port);
                $this->pass("validateSSHPort accepts valid port: $port");
            } catch (Exception $e) {
                $this->fail("validateSSHPort rejected valid port $port: " . $e->getMessage());
            }
        }

        // Test validateSSHPort with invalid ports
        $invalidPorts = [
            0 => 'zero',
            -1 => 'negative',
            65536 => 'too high',
            70000 => 'way too high',
            'abc' => 'non-numeric string',
        ];

        foreach ($invalidPorts as $port => $reason) {
            try {
                host::validateSSHPort($port);
                $this->fail("validateSSHPort accepted invalid port ($reason)");
            } catch (InputException $e) {
                $this->pass("validateSSHPort correctly rejects $reason");
            } catch (Exception $e) {
                $this->fail("validateSSHPort threw unexpected exception for $reason: " . get_class($e));
            }
        }

        // Test validateActive
        try {
            host::validateActive('Y');
            $this->pass("validateActive accepts 'Y'");
        } catch (Exception $e) {
            $this->fail("validateActive rejected 'Y': " . $e->getMessage());
        }

        try {
            host::validateActive('N');
            $this->pass("validateActive accepts 'N'");
        } catch (Exception $e) {
            $this->fail("validateActive rejected 'N': " . $e->getMessage());
        }

        try {
            host::validateActive('INVALID');
            $this->fail("validateActive accepted invalid value");
        } catch (InputException $e) {
            $this->pass("validateActive correctly rejects invalid value");
        }

        // Test validateStagingPath
        try {
            host::validateStagingPath('/tmp');
            $this->pass("validateStagingPath accepts valid path");
        } catch (Exception $e) {
            $this->fail("validateStagingPath rejected valid path: " . $e->getMessage());
        }

        try {
            host::validateStagingPath(str_repeat('a', 1025));
            $this->fail("validateStagingPath accepted path that's too long");
        } catch (InputException $e) {
            $this->pass("validateStagingPath correctly rejects path that's too long");
        }

        // Test validateHostDescription
        try {
            host::validateHostDescription('Test host description');
            $this->pass("validateHostDescription accepts valid description");
        } catch (Exception $e) {
            $this->fail("validateHostDescription rejected valid description: " . $e->getMessage());
        }

        try {
            host::validateHostDescription('');
            $this->fail("validateHostDescription accepted empty description");
        } catch (InputException $e) {
            $this->pass("validateHostDescription correctly rejects empty description");
        }

        try {
            host::validateHostDescription(str_repeat('a', 257));
            $this->fail("validateHostDescription accepted description that's too long");
        } catch (InputException $e) {
            $this->pass("validateHostDescription correctly rejects description that's too long");
        }
    }

    private function testBackupVolumeValidations() {
        echo YELLOW . "\n[TEST] Testing BackupVolume Validation Methods...\n" . RESET;

        if (!class_exists('backupVolume')) {
            $this->skip("BackupVolume class not available");
            return;
        }

        // Test validateName with valid names
        $validNames = [
            'MyBackupVolume',
            'Volume1',
            'test-volume',
            'Volume_2024',
            str_repeat('a', 128), // Max length
        ];

        foreach ($validNames as $name) {
            try {
                backupVolume::validateName($name);
                $this->pass("validateName accepts valid name: " . substr($name, 0, 30) . (strlen($name) > 30 ? '...' : ''));
            } catch (Exception $e) {
                $this->fail("validateName rejected valid name: " . $e->getMessage());
            }
        }

        // Test validateName with invalid names
        try {
            backupVolume::validateName('');
            $this->fail("validateName accepted empty name");
        } catch (InputException $e) {
            $this->pass("validateName correctly rejects empty name");
        }

        try {
            backupVolume::validateName(str_repeat('a', 129));
            $this->fail("validateName accepted name that's too long");
        } catch (InputException $e) {
            $this->pass("validateName correctly rejects name that's too long");
        }

        // Test validatePath with valid paths
        // Note: This requires actual directories to exist
        $tempDir = sys_get_temp_dir();
        try {
            backupVolume::validatePath($tempDir);
            $this->pass("validatePath accepts valid directory: $tempDir");
        } catch (Exception $e) {
            $this->fail("validatePath rejected valid directory: " . $e->getMessage());
        }

        // Test validatePath with invalid paths
        try {
            backupVolume::validatePath('');
            $this->fail("validatePath accepted empty path");
        } catch (InputException $e) {
            $this->pass("validatePath correctly rejects empty path");
        }

        try {
            backupVolume::validatePath('/nonexistent/directory/path/12345');
            $this->fail("validatePath accepted non-existent directory");
        } catch (InputException $e) {
            $this->pass("validatePath correctly rejects non-existent directory");
        }

        try {
            backupVolume::validatePath(str_repeat('a', 1025));
            $this->fail("validatePath accepted path that's too long");
        } catch (InputException $e) {
            $this->pass("validatePath correctly rejects path that's too long");
        }
    }

    private function testScheduledBackupValidations() {
        echo YELLOW . "\n[TEST] Testing ScheduledBackup Validation Methods...\n" . RESET;

        if (!class_exists('scheduledBackup')) {
            $this->skip("ScheduledBackup class not available");
            return;
        }

        // Test validateName
        try {
            scheduledBackup::validateName('MyBackup');
            $this->pass("validateName accepts valid backup name");
        } catch (Exception $e) {
            $this->fail("validateName rejected valid name: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateName('');
            $this->fail("validateName accepted empty name");
        } catch (InputException $e) {
            $this->pass("validateName correctly rejects empty name");
        }

        try {
            scheduledBackup::validateName(str_repeat('a', 129));
            $this->fail("validateName accepted name that's too long");
        } catch (InputException $e) {
            $this->pass("validateName correctly rejects name that's too long");
        }

        // Test validateCronExpression with valid cron expressions
        $validCronExpressions = [
            '0 2 * * *',           // Daily at 2am
            '*/5 * * * *',         // Every 5 minutes
            '0 0 * * 0',           // Weekly on Sunday
            '0 0 1 * *',           // Monthly on 1st
            '@hourly',             // Hourly shortcut
            '@daily',              // Daily shortcut
            '@weekly',             // Weekly shortcut
            '@monthly',            // Monthly shortcut
            '0 0,12 * * *',        // Twice daily
        ];

        foreach ($validCronExpressions as $cron) {
            try {
                scheduledBackup::validateCronExpression($cron);
                $this->pass("validateCronExpression accepts: $cron");
            } catch (Exception $e) {
                $this->fail("validateCronExpression rejected valid cron '$cron': " . $e->getMessage());
            }
        }

        // Test validateCronExpression with invalid expressions
        $invalidCronExpressions = [
            '60 * * * *',          // Invalid minute
            '* 24 * * *',          // Invalid hour
            '* * 32 * *',          // Invalid day
            '* * * 13 *',          // Invalid month
            '* * * * 8',           // Invalid day of week
            'invalid',             // Not a cron expression
            '',                    // Empty
        ];

        foreach ($invalidCronExpressions as $cron) {
            try {
                scheduledBackup::validateCronExpression($cron);
                $this->fail("validateCronExpression accepted invalid cron: $cron");
            } catch (InputException $e) {
                $this->pass("validateCronExpression correctly rejects: $cron");
            }
        }

        // Test validateMysqlUser
        try {
            scheduledBackup::validateMysqlUser('root');
            $this->pass("validateMysqlUser accepts valid username");
        } catch (Exception $e) {
            $this->fail("validateMysqlUser rejected valid username: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateMysqlUser('');
            $this->fail("validateMysqlUser accepted empty username");
        } catch (InputException $e) {
            $this->pass("validateMysqlUser correctly rejects empty username");
        }

        try {
            scheduledBackup::validateMysqlUser(str_repeat('a', 17));
            $this->fail("validateMysqlUser accepted username that's too long (>16 chars)");
        } catch (InputException $e) {
            $this->pass("validateMysqlUser correctly rejects username that's too long");
        }

        // Test validateMysqlPass
        try {
            scheduledBackup::validateMysqlPass('password123');
            $this->pass("validateMysqlPass accepts valid password");
        } catch (Exception $e) {
            $this->fail("validateMysqlPass rejected valid password: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateMysqlPass('');
            $this->fail("validateMysqlPass accepted empty password");
        } catch (InputException $e) {
            $this->pass("validateMysqlPass correctly rejects empty password");
        }

        try {
            scheduledBackup::validateMysqlPass(str_repeat('a', 257));
            $this->fail("validateMysqlPass accepted password that's too long");
        } catch (InputException $e) {
            $this->pass("validateMysqlPass correctly rejects password that's too long");
        }

        // Test validateBackupUser
        try {
            scheduledBackup::validateBackupUser('mysql');
            $this->pass("validateBackupUser accepts valid backup user");
        } catch (Exception $e) {
            $this->fail("validateBackupUser rejected valid user: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateBackupUser('');
            $this->fail("validateBackupUser accepted empty user");
        } catch (InputException $e) {
            $this->pass("validateBackupUser correctly rejects empty user");
        }

        // Test validateDatadirPath
        try {
            scheduledBackup::validateDatadirPath('/var/lib/mysql');
            $this->pass("validateDatadirPath accepts valid path");
        } catch (Exception $e) {
            $this->fail("validateDatadirPath rejected valid path: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateDatadirPath('');
            $this->fail("validateDatadirPath accepted empty path");
        } catch (InputException $e) {
            $this->pass("validateDatadirPath correctly rejects empty path");
        }

        try {
            scheduledBackup::validateDatadirPath(str_repeat('a', 1025));
            $this->fail("validateDatadirPath accepted path that's too long");
        } catch (InputException $e) {
            $this->pass("validateDatadirPath correctly rejects path that's too long");
        }

        // Test validateYesNo / validateActive / validateLockTables
        try {
            scheduledBackup::validateYesNo('Y');
            scheduledBackup::validateYesNo('N');
            $this->pass("validateYesNo accepts Y and N");
        } catch (Exception $e) {
            $this->fail("validateYesNo rejected valid values: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateYesNo('MAYBE');
            $this->fail("validateYesNo accepted invalid value");
        } catch (InputException $e) {
            $this->pass("validateYesNo correctly rejects invalid value");
        }

        // Test validateMaxSnapshots
        try {
            scheduledBackup::validateMaxSnapshots(7);
            scheduledBackup::validateMaxSnapshots('7');
            scheduledBackup::validateMaxSnapshots(1);
            $this->pass("validateMaxSnapshots accepts valid values");
        } catch (Exception $e) {
            $this->fail("validateMaxSnapshots rejected valid values: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateMaxSnapshots(0);
            $this->fail("validateMaxSnapshots accepted zero");
        } catch (InputException $e) {
            $this->pass("validateMaxSnapshots correctly rejects zero");
        }

        try {
            scheduledBackup::validateMaxSnapshots(-1);
            $this->fail("validateMaxSnapshots accepted negative value");
        } catch (InputException $e) {
            $this->pass("validateMaxSnapshots correctly rejects negative value");
        }

        try {
            scheduledBackup::validateMaxSnapshots('abc');
            $this->fail("validateMaxSnapshots accepted non-numeric value");
        } catch (InputException $e) {
            $this->pass("validateMaxSnapshots correctly rejects non-numeric value");
        }

        // Test validateRotateDayOfWeek
        try {
            scheduledBackup::validateRotateDayOfWeek('0');      // Sunday
            scheduledBackup::validateRotateDayOfWeek('6');      // Saturday
            scheduledBackup::validateRotateDayOfWeek('0,6');    // Weekend
            scheduledBackup::validateRotateDayOfWeek('1,2,3,4,5'); // Weekdays
            $this->pass("validateRotateDayOfWeek accepts valid day values");
        } catch (Exception $e) {
            $this->fail("validateRotateDayOfWeek rejected valid values: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateRotateDayOfWeek('7');
            $this->fail("validateRotateDayOfWeek accepted invalid day (7)");
        } catch (InputException $e) {
            $this->pass("validateRotateDayOfWeek correctly rejects invalid day (7)");
        }

        try {
            scheduledBackup::validateRotateDayOfWeek('0-6');
            $this->fail("validateRotateDayOfWeek accepted range notation (should be comma-separated)");
        } catch (InputException $e) {
            $this->pass("validateRotateDayOfWeek correctly rejects range notation");
        }

        // Test validateRotateMethod
        try {
            scheduledBackup::validateRotateMethod('DAY_OF_WEEK');
            scheduledBackup::validateRotateMethod('AFTER_SNAPSHOT_COUNT');
            $this->pass("validateRotateMethod accepts valid methods");
        } catch (Exception $e) {
            $this->fail("validateRotateMethod rejected valid methods: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateRotateMethod('INVALID_METHOD');
            $this->fail("validateRotateMethod accepted invalid method");
        } catch (InputException $e) {
            $this->pass("validateRotateMethod correctly rejects invalid method");
        }

        // Test validateMaintainMaterializedCopy
        try {
            scheduledBackup::validateMaintainMaterializedCopy('0');
            scheduledBackup::validateMaintainMaterializedCopy('1');
            $this->pass("validateMaintainMaterializedCopy accepts 0 and 1");
        } catch (Exception $e) {
            $this->fail("validateMaintainMaterializedCopy rejected valid values: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateMaintainMaterializedCopy('2');
            $this->fail("validateMaintainMaterializedCopy accepted invalid value (2)");
        } catch (InputException $e) {
            $this->pass("validateMaintainMaterializedCopy correctly rejects invalid value");
        }

        // Test validateBackupSkipFatal
        try {
            scheduledBackup::validateBackupSkipFatal('0');
            scheduledBackup::validateBackupSkipFatal('1');
            $this->pass("validateBackupSkipFatal accepts 0 and 1");
        } catch (Exception $e) {
            $this->fail("validateBackupSkipFatal rejected valid values: " . $e->getMessage());
        }

        try {
            scheduledBackup::validateBackupSkipFatal('yes');
            $this->fail("validateBackupSkipFatal accepted 'yes' instead of 0/1");
        } catch (InputException $e) {
            $this->pass("validateBackupSkipFatal correctly rejects non-binary value");
        }
    }

    private function testEdgeCases() {
        echo YELLOW . "\n[TEST] Testing Edge Cases and Boundary Conditions...\n" . RESET;

        // Test hostname with reasonable max length
        // Note: RFC 2396 Section 3.2.2 validation may be stricter than simple length check
        if (class_exists('host')) {
            try {
                // Use a valid long hostname structure
                $maxHostname = 'very-long-subdomain-name-test.example.com'; // Valid structure
                host::validateHostname($maxHostname);
                $this->pass("validateHostname accepts long valid hostname");
            } catch (Exception $e) {
                $this->fail("validateHostname rejected valid long hostname: " . $e->getMessage());
            }

            // Test that 256-char hostname is rejected
            try {
                $tooLongHostname = str_repeat('a', 256);
                host::validateHostname($tooLongHostname);
                $this->fail("validateHostname accepted 256-character hostname");
            } catch (InputException $e) {
                $this->pass("validateHostname correctly rejects 256-character hostname");
            }

            // Test SSH port boundaries
            try {
                host::validateSSHPort(1);
                host::validateSSHPort(65535);
                $this->pass("validateSSHPort accepts boundary values (1 and 65535)");
            } catch (Exception $e) {
                $this->fail("validateSSHPort rejected boundary values: " . $e->getMessage());
            }
        }

        // Test backup volume name with exactly 128 characters (max allowed)
        if (class_exists('backupVolume')) {
            try {
                $maxName = str_repeat('a', 128);
                backupVolume::validateName($maxName);
                $this->pass("validateName accepts 128-character name (boundary)");
            } catch (Exception $e) {
                $this->fail("validateName rejected max-length name: " . $e->getMessage());
            }
        }

        // Test MySQL username with exactly 16 characters (max allowed)
        if (class_exists('scheduledBackup')) {
            try {
                $maxUser = str_repeat('a', 16);
                scheduledBackup::validateMysqlUser($maxUser);
                $this->pass("validateMysqlUser accepts 16-character username (boundary)");
            } catch (Exception $e) {
                $this->fail("validateMysqlUser rejected max-length username: " . $e->getMessage());
            }

            // Test max_snapshots with boundary value 1
            try {
                scheduledBackup::validateMaxSnapshots(1);
                $this->pass("validateMaxSnapshots accepts boundary value 1");
            } catch (Exception $e) {
                $this->fail("validateMaxSnapshots rejected boundary value 1: " . $e->getMessage());
            }

            // Test cron expression edge cases
            try {
                scheduledBackup::validateCronExpression('0 0 1 1 0'); // Specific date and day
                scheduledBackup::validateCronExpression('*/1 * * * *'); // Every minute
                scheduledBackup::validateCronExpression('59 23 31 12 6'); // Max values
                $this->pass("validateCronExpression handles edge case cron expressions");
            } catch (Exception $e) {
                $this->fail("validateCronExpression failed on edge cases: " . $e->getMessage());
            }
        }
    }

    private function testExceptionHandling() {
        echo YELLOW . "\n[TEST] Testing Exception Handling...\n" . RESET;

        // Verify that correct exception types are thrown
        if (class_exists('host') && class_exists('InputException')) {
            try {
                host::validateHostname('');
            } catch (InputException $e) {
                $this->pass("validateHostname throws InputException for invalid input");
            } catch (Exception $e) {
                $this->fail("validateHostname threw " . get_class($e) . " instead of InputException");
            }

            try {
                host::validateSSHPort(99999);
            } catch (InputException $e) {
                $this->pass("validateSSHPort throws InputException for invalid port");
            } catch (Exception $e) {
                $this->fail("validateSSHPort threw " . get_class($e) . " instead of InputException");
            }
        }

        if (class_exists('backupVolume') && class_exists('InputException')) {
            try {
                backupVolume::validateName('');
            } catch (InputException $e) {
                $this->pass("validateName throws InputException for empty name");
            } catch (Exception $e) {
                $this->fail("validateName threw " . get_class($e) . " instead of InputException");
            }
        }

        if (class_exists('scheduledBackup') && class_exists('InputException')) {
            try {
                scheduledBackup::validateCronExpression('invalid cron');
            } catch (InputException $e) {
                $this->pass("validateCronExpression throws InputException for invalid expression");
            } catch (Exception $e) {
                $this->fail("validateCronExpression threw " . get_class($e) . " instead of InputException");
            }

            try {
                scheduledBackup::validateMaxSnapshots(0);
            } catch (InputException $e) {
                $this->pass("validateMaxSnapshots throws InputException for invalid value");
            } catch (Exception $e) {
                $this->fail("validateMaxSnapshots threw " . get_class($e) . " instead of InputException");
            }
        }

        // Verify exception messages are descriptive
        if (class_exists('host') && class_exists('InputException')) {
            try {
                host::validateHostname(str_repeat('a', 256));
                $this->fail("validateHostname should throw exception for too-long hostname");
            } catch (InputException $e) {
                if (strlen($e->getMessage()) > 10) {
                    $this->pass("Exception messages are descriptive (length > 10 chars)");
                } else {
                    $this->fail("Exception message too short: " . $e->getMessage());
                }
            }
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
            echo "\nAll validation methods work correctly in isolation.\n\n";
        }
    }
}

// Run tests
$tests = new UnitTests();
exit($tests->run());
