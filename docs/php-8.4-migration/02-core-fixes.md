# PHP 8.4 Core Compatibility Fixes

## Fix #1: GdImage Type Support
**File**: `src/Controller/Map_blockCtrl.php`  
**Lines**: 35-37, 199, 291

### Issue
PHP 8.0+ changed `imagecreatetruecolor()` return type from `resource` to `GdImage` object.

### Solution
Changed type hints from `resource` to `\GdImage`:
```php
// Before
private function loadImage($file): resource

// After
private function loadImage($file): \GdImage
```

**Impact**: Prevents fatal type errors when creating/manipulating images.

---

## Fix #2: E_STRICT Constant Removal
**File**: `src/Core/ErrorHandler.php`  
**Line**: 9

### Issue
`E_STRICT` constant removed in PHP 8.0+.

### Solution
Removed from error reporting configuration:
```php
// Before
error_reporting(E_ALL | E_STRICT);

// After
error_reporting(E_ALL);
```

**Impact**: Fixes immediate startup error.

---

## Fix #3: Composer Autoloader
**File**: `composer.json`  
**Lines**: PSR-4 autoloading configuration

### Issue
Old namespace mapping causing autoload failures.

### Solution
Updated PSR-4 autoload paths to match actual directory structure.

**Impact**: Classes load correctly under PHP 8.4.

---

## Fix #6: Twig 3.x Compatibility (API)
**File**: `integrations/api/include/bootstrap.php`  
**Line**: 15

### Issue
Twig 2.x deprecated, incompatible with PHP 8.4.

### Solution
Updated Twig loader instantiation for Twig 3.x:
```php
// Before
$loader = new Twig_Loader_Filesystem(__DIR__ . '/locale');

// After  
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/locale');
```

**Impact**: API templates render correctly.

---

## Fix #7: Nested Ternary in Village.php
**File**: `src/Core/Village.php`  
**Line**: 1076

### Issue
Unparenthesized nested ternary deprecated in PHP 8.0+.

### Solution
```php
// Before
$a ? $b : $c ? $d : $e

// After
$a ? $b : ($c ? $d : $e)
```

**Impact**: Removes deprecation warnings.

---

## Fix #8: Null Check in AnyCtrl
**File**: Multiple controllers

### Issue
Direct property access on potentially null objects.

### Solution
Added null coalescing operator:
```php
// Before
$obj->property

// After
$obj->property ?? null
```

**Impact**: Prevents "null object" warnings.

---

## Fix #20: Nested Ternaries in premiumFeature.php
**File**: `src/Controller/Ajax/premiumFeature.php`  
**Lines**: 192, 353, 450, 1024, 1085, 1087

### Issue
5 nested ternary operators without parentheses.

### Solution
Added explicit parentheses to all nested ternaries:
```php
// Lines 192, 353, 450
$rate = Config::getProperty("useNanoseconds") ? 1e9 : (Config::getProperty("useMilSeconds") ? 1e3 : 1);

// Lines 1085, 1087
$prod[$i] = ($resources[$i] + $prod[$i] > $max) ? ($max - $resources[$i]) : $prod[$i];
```

**Impact**: Gold purchases work without warnings.

---

## Testing

All core fixes tested by:
1. Loading main game page
2. Accessing map
3. Premium feature purchases
4. API calls
5. Admin panel access

**Result**: ✅ No errors, no warnings
