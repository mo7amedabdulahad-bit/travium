# Miscellaneous Fixes

## Fix #4-5: Missing Config Files
**Files**: `src/config.php`, `src/config.sample.php`

### Issue
Files were not tracked in git due to `.gitignore` rules, causing fresh installations to fail.

### Solution
1. Created `config.sample.php` template
2. Added proper git tracking
3. Updated installation documentation

**Impact**: Fresh installations work without manual file creation.

---

## Fix #10: round() Mode Parameter
**File**: `src/Game/Formulas.php`  
**Line**: Multiple

### Issue
`round()` function in PHP 8.0+ requires explicit rounding mode if precision is negative.

### Solution
```php
// Before ❌
round($value, -1)  // Ambiguous

// After ✅
round($value, -1, PHP_ROUND_HALF_UP)  // Explicit
```

**Impact**: No deprecation warnings for rounding calculations.

---

## Fix #11: BuildCtrl Parameter Order
**File**: `src/Controller/BuildCtrl.php`  
**Lines**: 242, 626-691, 701

### Issue
Function signature violated PHP 8.0+ rule: optional parameters must come last.

```php
// Before ❌
function getValuesTable(&$contract, $params = [], callable $callback)

// After ✅
function getValuesTable(&$contract, callable $callback, $params = [])
```

### Solution
1. Fixed function signature (line 242)
2. Updated all 13 call sites to match new order

**Impact**: Building page works without parameter order errors.

---

## Fix #12: MessageModel Parameter Order
**File**: `src/Model/MessageModel.php`  
**Line**: 242

### Issue
Same parameter ordering issue as BuildCtrl.

### Solution
```php
// Before ❌
function checkLastMessage($from_uid, $to_uid = null, $time)

// After ✅
function checkLastMessage($from_uid, $time, $to_uid = null)
```

**Impact**: Messaging system works correctly.

---

## PHP 8.0+ Parameter Rules

### The Rule
Optional parameters (with default values) MUST come after required parameters:

```php
// ❌ WRONG - Optional before required
function bad($optional = null, $required) { }

// ✅ CORRECT - Required before optional
function good($required, $optional = null) { }

// ❌ WRONG - Multiple optionals scattered
function bad2($req1, $opt1 = null, $req2, $opt2 = null) { }

// ✅ CORRECT - All optionals at end
function good2($req1, $req2, $opt1 = null, $opt2 = null) { }
```

### Impact
PHP 8.0+ throws `Fatal error` if this rule is violated. PHP 7.x only showed a deprecation notice.

---

## Testing Summary

All miscellaneous fixes verified:
- ✅ Config files present and tracked
- ✅ Rounding calculations work
- ✅ Building upgrades functional
- ✅ Message system operational
- ✅ No parameter order errors

**Result**: All auxiliary systems fully functional!
