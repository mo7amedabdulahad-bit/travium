# Admin Panel Fixes

## Fix #22: PublicInfoboxCtrl - autoType Column
**File**: `src/admin/include/Controllers/PublicInfoboxCtrl.php`  
**Lines**: 20, 29

### Issue
Queries referenced non-existent `autoType` column in `infobox` table.

### Solution
```sql
-- Delete query ❌
DELETE FROM infobox WHERE id=123 AND autoType=0

-- Delete query ✅
DELETE FROM infobox WHERE id=123

-- Select query ❌
SELECT * FROM infobox WHERE autoType=0 AND showTo>=12345

-- Select query ✅
SELECT * FROM infobox WHERE showTo>=12345
```

**Impact**: Public infobox management works.

---

## Fix #23: PaymentProductsCtrl - Empty locationId
**File**: `src/admin/include/Controllers/PaymentProductsCtrl.php`  
**Line**: 164

### Issue
SQL syntax error: session variable `locationId` could be undefined, causing:
```
WHERE location= ORDER BY goldProductId
```

### Solution
Ensure variable exists before query:
```php
// Before ❌
$locations = $db->query("SELECT * FROM goldProducts WHERE goldProductLocation={$_SESSION['locationId']} ORDER BY goldProductId ASC");

// After ✅
$locationId = isset($_SESSION['locationId']) ? (int)$_SESSION['locationId'] : 0;
$locations = $db->query("SELECT * FROM goldProducts WHERE goldProductLocation=$locationId ORDER BY goldProductId ASC");
```

**Impact**: Payment products page loads without SQL errors.

---

## Fix #24: PaymentProvidersCtrl - Empty locationId  
**File**: `src/admin/include/Controllers/PaymentProvidersCtrl.php`  
**Line**: 183

### Issue
Same as Fix #23 - undefined session variable causing SQL syntax error.

### Solution
```php
// Before ❌
$locations = $db->query("SELECT * FROM paymentProviders WHERE location={$_SESSION['locationId']} ORDER BY posId ASC");

// After ✅
$locationId = isset($_SESSION['locationId']) ? (int)$_SESSION['locationId'] : 0;
$locations = $db->query("SELECT * FROM paymentProviders WHERE location=$locationId ORDER BY posId ASC");
```

**Impact**: Payment providers page loads correctly.

---

## Admin Panel Testing Checklist

All admin panel pages tested:

- [x] Dashboard
- [x] Verification List (Fix #16, #21)
- [x] Public Infobox (Fix #22)
- [x] Payment Products (Fix #23)
- [x] Payment Providers (Fix #24)
- [x] All other admin functions

**Result**: ✅ Complete admin panel functionality restored!

---

## Common Pattern: Session Variable Checks

Several admin controllers follow the same pattern for location selection:

```php
// Always check if session variable exists before SQL query
if (!isset($_SESSION[WebService::fixSessionPrefix('locationId')])) {
    $_SESSION[WebService::fixSessionPrefix('locationId')] = $defaultValue;
}

// Then safely use in query
$locationId = (int)$_SESSION[WebService::fixSessionPrefix('locationId')];
$result = $db->query("SELECT * FROM table WHERE location=$locationId");
```

This prevents SQL syntax errors from empty/undefined variables.
