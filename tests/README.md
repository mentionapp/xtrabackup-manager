# XtraBackup Manager - Test Suite

This directory contains automated tests for validating PHP 8.2+ compatibility.

## Running Tests

### PHP 8.2+ Validation Suite

This test suite validates that the codebase works correctly with PHP 8.2 and above:

```bash
php tests/php82-validation.php
```

Or make it executable and run directly:

```bash
chmod +x tests/php82-validation.php
./tests/php82-validation.php
```

## What is Tested

The validation suite checks:

1. **PHP Version Compatibility** - Ensures PHP >= 8.2.0
2. **Syntax Validation** - Checks all `.php` files for syntax errors
3. **Class Loading** - Verifies classes can be loaded without fatal errors
4. **Validation Methods** - Tests static validation methods:
   - `host::validateHostname()`, `validateSSHPort()`, `validateActive()`, `validateStagingPath()`
   - `backupVolume::validateName()`, `validatePath()`
   - `backupStrategy::validateStrategyCode()`
5. **Deprecated Functions** - Scans for deprecated function usage:
   - `sizeOf()` (should be `count()`)
   - `ereg*()` functions (removed in PHP 7.0+)

## Test Output

The test suite provides colored output:
- ✓ Green: Test passed
- ✗ Red: Test failed
- ⊘ Yellow: Test skipped

Example output:
```
╔════════════════════════════════════════════════════════════╗
║  XtraBackup Manager - PHP 8.2+ Validation Test Suite      ║
╚════════════════════════════════════════════════════════════╝

PHP Version: 8.5.1
Date: 2026-01-21 15:04:32

[TEST] Checking PHP Version Compatibility...
  ✓ PHP version 8.5.1 is >= 8.2.0

[TEST] Checking PHP Syntax of All Files...
  ✓ Syntax OK: backupJob.class.php
  ✓ Syntax OK: backupRestorer.class.php
  ...

╔════════════════════════════════════════════════════════════╗
║  Test Summary                                              ║
╚════════════════════════════════════════════════════════════╝

Total tests: 39
Passed: 38
Failed: 0
Skipped: 1

✅ ALL TESTS PASSED!
```

## Exit Codes

- `0`: All tests passed
- `1`: One or more tests failed

## Notes

- Some tests are skipped if they require database configuration or constants from `init.php`
- The test suite is designed to run without a database connection
- Tests focus on PHP compatibility, not functional/integration testing
