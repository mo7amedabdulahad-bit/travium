# Quick Testing Guide for Ubuntu/WSL

## 1. Pull Latest Code

```bash
cd ~/travium  # Or your installation path
git pull origin main
```

**Expected**: 16 files changed, 2660 insertions

## 2. Apply Database Migration

```bash
mysql -u maindb -pmaindb maindb < migrations/002_add_npc_columns.sql
```

**Verify:**
```bash
mysql -u maindb -pmaindb maindb -e "SHOW COLUMNS FROM users LIKE 'npc_%';"
```

Should show 4 columns: npc_personality, npc_difficulty, npc_info, last_npc_action

## 3. Update Your 2 Existing Fake Users

```bash
php update_existing_fake_users.php
```

**Expected Output:**
```
Found 2 fake user(s)
Newly updated: 2
Failed: 0
```

## 4. Run Comprehensive Debugger

```bash
php debug_npc_system.php
```

**Look for:**
- ✅ All database columns exist
- ✅ All NPCs properly configured
- ✅ PersonalityAI integrated
- ✅ RaidAI integrated
- ✅ Code integration checks all green

## 5. Optional: Run All Tests

```bash
chmod +x run_npc_tests.sh
./run_npc_tests.sh
```

Or manually:
```bash
php test_npc_config.php
php test_personality_ai.php
php test_raid_ai.php
```

## 6. Monitor NPC Activity

### Check if NPCs are configured
```bash
mysql -u maindb -pmaindb maindb -e "
SELECT id, name, npc_personality, npc_difficulty 
FROM users 
WHERE access=3;"
```

### Watch for raids (updates every 5 seconds)
```bash
watch -n 5 'mysql -u maindb -pmaindb maindb -e "
SELECT COUNT(*) as ongoing_raids 
FROM movement m
JOIN vdata v ON m.kid = v.kid
JOIN users u ON v.owner = u.id
WHERE u.access=3 AND m.attack_type=4"'
```

### Check NPC statistics
```bash
mysql -u maindb -pmaindb maindb -e "
SELECT name, 
       npc_personality,
       JSON_EXTRACT(npc_info, '$.raids_sent') as raids_sent,
       FROM_UNIXTIME(last_npc_action) as last_action
FROM users 
WHERE access=3;"
```

## Quick Troubleshooting

### If migration fails:
```bash
# Check if already applied
mysql -u maindb -pmaindb maindb -e "SHOW COLUMNS FROM users LIKE 'npc_%';"

# If columns exist, migration already done
```

### If "No NPCs found":
```bash
# Create fake users via admin panel first
# Or check: SELECT * FROM users WHERE access=3;
```

### If tests fail:
```bash
# Ensure you're in the Travium directory
pwd  # Should show .../Travium

# Check PHP version
php -v  # Should be 8.4.x

# Check file exists
ls -la src/Core/NpcConfig.php
```

## Success Checklist

- [ ] Git pull successful (16 files)
- [ ] Migration applied (4 new columns)
- [ ] 2 fake users updated
- [ ] Debug report shows all green ✅
- [ ] No errors in any test

## What to Expect

**Immediately:**
- Both NPCs have personality + difficulty
- All tests pass
- Debug shows proper configuration

**After 1-2 hours:**
- NPCs start building/training
- Aggressive NPC may send first raid
- `last_npc_action` timestamps update

**After 24 hours:**
- Aggressive: 10-20 raids sent
- Economic: 1-3 raids sent
- Clear personality differences visible

## Need Help?

Run the comprehensive debugger:
```bash
php debug_npc_system.php
```

This shows everything: database, NPCs, raids, integration status.

---

**Commit**: 4a7cf45d63929c6cbd095d068ca784117c7b3b26  
**Files Changed**: 16 files, 2660 insertions  
**Status**: ✅ Pushed to GitHub, ready to test!
