# Database Schema Fixes

## Overview
The game uses two separate databases:
- **Local database** (per server): Contains `users` table with `world` column
- **Global database**: Contains `activation`, `preregistration_keys` tables **WITHOUT** `world`/`worldId` columns

## Fix #9: LoginModel - users.world Column
**File**: `src/Model/LoginModel.php`  
**Line**: 132

### Issue
Query referenced non-existent `worldId` column in `users` table.

### Solution
```sql
-- Before ❌
SELECT COUNT(id) FROM users WHERE worldId=1 AND name='...'

-- After ✅
SELECT COUNT(id) FROM users WHERE world=1 AND name='...'
```

**Impact**: User login works correctly.

---

## Fix #13-15: SummaryModel - activation Table
**File**: `src/Model/SummaryModel.php`  
**Lines**: 68-69

### Issue
Query tried to filter `activation` table by `used=0` and `worldId` - both columns don't exist.

### Solution (3 iterations)
```sql
-- Original ❌
SELECT COUNT(id) FROM activation WHERE used=0 AND worldId=1

-- Attempt 1 ❌ (used column doesn't exist)
SELECT COUNT(id) FROM activation WHERE world=1

-- Final ✅ (activation is global - no world filtering)
SELECT COUNT(id) FROM activation
```

**Impact**: Statistics page loads without errors.

---

## Fix #16: Admin VerificationListCtrl - activation.used
**File**: `src/admin/include/Controllers/VerificationListCtrl.php`  
**Lines**: 15, 17

### Issue
Two queries referenced non-existent `used=0` column.

### Solution
```sql
-- Before ❌
SELECT * FROM activation WHERE used=0 ORDER BY id
SELECT COUNT(id) FROM activation WHERE used=0

-- After ✅
SELECT * FROM activation ORDER BY id
SELECT COUNT(id) FROM activation
```

**Impact**: Admin verification list accessible.

---

## Fix #18-19: API RegisterCtrl - activation Table
**File**: `integrations/api/include/Api/Ctrl/RegisterCtrl.php`  
**Lines**: 45, 103, 237, 268, 278, 301, 338

### Issue
7 queries tried to use `worldId` and `used` columns in global `activation` table.

### Solution
Removed both `worldId` and `used=0` filters from all queries:

```sql
-- getActivationByEmail ❌
SELECT * FROM activation WHERE worldId=:wid AND email=:email AND used=0
-- ✅
SELECT * FROM activation WHERE email=:email

-- getActivationByActivationCode ❌
SELECT * FROM activation WHERE worldId=:wid AND activationCode=:code AND used=0
-- ✅
SELECT * FROM activation WHERE activationCode=:code

-- Insert ❌
INSERT INTO activation (worldId, name, ...) VALUES (...)
-- ✅
INSERT INTO activation (name, ...) VALUES (...)

-- Check functions ❌
WHERE name=:name AND worldId=:wid AND used=0
-- ✅
WHERE name=:name
```

**Impact**: API registration fully functional!

---

## Fix #21: Admin VerificationListCtrl - worldId
**File**: `src/admin/include/Controllers/VerificationListCtrl.php`  
**Line**: 15

### Issue
Still had `worldId` filter (Fix #16 only removed `used=0`).

### Solution
```sql
-- Before ❌
SELECT * FROM activation WHERE worldId=1 ORDER BY id

-- After ✅
SELECT * FROM activation ORDER BY id
```

**Impact**: Admin panel fully accessible.

---

## Database Table Reference

### Local Database (per server)
```
users table:
- id
- name  
- world (INT) ✅ EXISTS
```

### Global Database
```
activation table:
- id
- name
- email
- activationCode
- (NO world column)
- (NO worldId column)  
- (NO used column)

preregistration_keys table:
- id
- pre_key
- (NO world column)
- (NO used column)
```

---

## Testing

Database fixes verified by:
1. ✅ User login
2. ✅ User registration via API
3. ✅ Statistics page
4. ✅ Admin verification list
5. ✅ Admin public infobox
6. ✅ No SQL errors in any admin panel

**Result**: All database queries execute successfully!
