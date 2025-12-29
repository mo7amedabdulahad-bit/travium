# NPC Training Fix - Deployment & Testing Guide

## 🔄 What Was Fixed

**Critical Bug**: NPCs were calling non-existent method `maxUnitsOf()` instead of `maxUnits()`
- This prevented ALL NPC training from working
- NPCs had no troops → Raids failed with `FARMLIST_NO_TROOPS`

**Solution**: 
- Fixed method name bug in 2 locations
- Added comprehensive logging for all NPC build/train decisions

---

## 📦 Step 1: Pull Changes on Ubuntu Server

```bash
# Navigate to the game directory
cd /home/travium/htdocs

# Pull latest changes from GitHub
sudo -u travium git pull origin main

# You should see:
# - src/Core/AI.php (modified - bug fix + logging)
# - diagnose_npc_build_train.php (new - diagnostic tool)
```

---

## 🔧 Step 2: Restart the Game Service

```bash
# Restart the Travium service to apply changes
sudo systemctl restart travium@s1.service

# Verify service is running
sudo systemctl status travium@s1.service

# Should show: "active (running)"
```

---

## 🔍 Step 3: Run Diagnostic Script (Optional but Recommended)

This will show you the current state of NPCs:

```bash
cd /home/travium/htdocs
sudo -u travium php diagnose_npc_build_train.php

# This will show:
# - How many NPCs exist
# - Their resources, buildings, troops
# - Training/building queues
# - Common issues detected
```

**What to look for:**
- ✅ NPCs exist (access=3)
- ✅ NPCs have villages
- ⚠️ NPCs may not have barracks yet (need to build them first)
- ⚠️ NPCs may have low resources (they accumulate over time)

---

## 📊 Step 4: Monitor NPC Activity Logs

Watch the logs in real-time to see NPCs making decisions:

```bash
# Monitor NPC activity logs
tail -f /home/travium/logs/npc_activity.log

# Press Ctrl+C to stop monitoring
```

### Log Types to Look For:

#### ✅ **Good Signs (NPCs Working)**

```
[CYCLE_START] Starting AI cycle | iterations: 3
[TRAIN_ATTEMPT] Attempting to train units | available_unit_types: [1,2,3]
[TRAIN_SUCCESS] Training 3 x unit 2 | unit_id: 2, count: 3
[BUILD] Upgrading Barracks to level 2
```

#### ⚠️ **Expected Early Issues (Normal)**

```
[TRAIN_FAIL] No training buildings available | barracks: 0
[TRAIN_NO_RES] Not enough resources for unit 1 | resources: [50, 40, 30, 80]
[BUILD_FAIL] AI Builder upgrade returned 0
```

These are normal if NPCs:
- Don't have barracks yet
- Have low resources (accumulating)
- All queues are full

---

## 🧪 Step 5: Test NPC Progress

### A. Check if NPCs are being processed

```bash
# Check if handleFakeUsers is running
grep "handleFakeUsers" /home/travium/logs/npc_activity.log | tail -5

# You should see recent entries every few minutes
```

### B. Check NPC troop counts

```bash
# Connect to MySQL
mysql -u travium -p travium

# Enter password when prompted, then run:
SELECT u.id, u.name, 
       (un.u1 + un.u2 + un.u3 + un.u4 + un.u5 + un.u6 + un.u7 + un.u8 + un.u9 + un.u10 + un.u11) as total_troops
FROM users u
LEFT JOIN vdata v ON v.owner = u.id
LEFT JOIN units un ON un.vref = v.kid
WHERE u.access = 3
LIMIT 10;

# Exit MySQL
exit;
```

**What to expect:**
- Initial: 0 troops (NPCs just starting)
- After 5-10 min: Some NPCs with barracks should start training
- After 30-60 min: NPCs should have accumulated troops

### C. Check training queue

```bash
mysql -u travium -p travium

# Run query:
SELECT t.*, v.owner, u.name 
FROM training t
JOIN vdata v ON v.kid = t.vref
JOIN users u ON u.id = v.owner
WHERE u.access = 3
LIMIT 10;

exit;
```

**What to expect:**
- Empty initially (no training queued)
- After fix: NPCs with barracks + resources should have training queued

---

## 📈 Step 6: Verify Building Progress

Check if NPCs are upgrading buildings:

```bash
mysql -u travium -p travium

# Check building queues for NPCs:
SELECT b.*, v.owner, u.name
FROM building_upgrade b
JOIN vdata v ON v.kid = b.kid
JOIN users u ON u.id = v.owner
WHERE u.access = 3;

exit;
```

---

## ⏱️ Expected Timeline

| Time After Deployment | Expected Behavior |
|----------------------|-------------------|
| **0-1 min** | Logs show `CYCLE_START`, `TRAIN_ATTEMPT`, `BUILD_*` entries |
| **1-5 min** | NPCs with resources start building/training |
| **5-15 min** | First buildings complete, first troops trained |
| **15-30 min** | NPCs building barracks if they don't have them |
| **30-60 min** | NPCs accumulating troops |
| **1-2 hours** | Raids starting to succeed (no more `FARMLIST_NO_TROOPS`) |

---

## 🐛 Troubleshooting

### Issue 1: No logs appearing

```bash
# Check if automation is running
sudo systemctl status travium@s1.service

# Check if log file exists and is writable
ls -la /home/travium/logs/npc_activity.log

# Check PHP error logs
sudo tail -50 /home/travium/logs/php_errors.log
```

### Issue 2: NPCs not training

Run the diagnostic:
```bash
sudo -u travium php diagnose_npc_build_train.php | grep -A 20 "DIAGNOSTIC SUMMARY"
```

Look for:
- ❌ No barracks → NPCs need to build them first (takes time)
- ❌ No resources → Wait for resources to accumulate
- ❌ No NPCs → Create NPCs via admin panel

### Issue 3: Still getting FARMLIST_NO_TROOPS

```bash
# Check if NPCs have troops now
mysql -u travium -p travium -e "
SELECT u.name, SUM(un.u1 + un.u2 + un.u3 + un.u4 + un.u5 + un.u6 + un.u7 + un.u8 + un.u9 + un.u10 + un.u11) as total
FROM users u
JOIN vdata v ON v.owner = u.id
JOIN units un ON un.vref = v.kid
WHERE u.access = 3
GROUP BY u.id
HAVING total > 0;"
```

If no troops yet, wait longer. NPCs need time to:
1. Accumulate resources
2. Build barracks (if missing)
3. Train units
4. Wait for training to complete

---

## 🎯 Quick Verification Checklist

Run these commands to quickly verify everything is working:

```bash
# 1. Service is running
sudo systemctl is-active travium@s1.service

# 2. Recent logs exist
tail -1 /home/travium/logs/npc_activity.log

# 3. NPCs exist
mysql -u travium -p travium -e "SELECT COUNT(*) as npc_count FROM users WHERE access=3;"

# 4. Check if any NPCs have troops
mysql -u travium -p travium -e "SELECT COUNT(DISTINCT v.owner) as npcs_with_troops FROM vdata v JOIN units un ON un.vref = v.kid JOIN users u ON u.id = v.owner WHERE u.access = 3 AND (un.u1 + un.u2 + un.u3 + un.u4 + un.u5 + un.u6 + un.u7 + un.u8 + un.u9 + un.u10 + un.u11) > 0;"
```

---

## 📝 Example Good Log Output

After successful deployment, you should see logs like:

```log
[2025-12-29 20:10:00] [NPC ID: 0] [SYSTEM] Starting raid interval processing
[2025-12-29 20:10:00] [NPC ID: 0] [SYSTEM] Found 10 NPC villages for raid check
[2025-12-29 20:10:01] [NPC ID: 25] [CYCLE_START] Starting AI cycle | iterations: 3
[2025-12-29 20:10:01] [NPC ID: 25] [TRAIN_ATTEMPT] Attempting to train units
[2025-12-29 20:10:01] [NPC ID: 25] [TRAIN_PREFS] Checking preferred units
[2025-12-29 20:10:01] [NPC ID: 25] [TRAIN_SUCCESS] Training 2 x unit 1 | count: 2
[2025-12-29 20:10:02] [NPC ID: 26] [CYCLE_START] Starting AI cycle | iterations: 4
[2025-12-29 20:10:02] [NPC ID: 26] [BUILD] Upgrading Barracks to level 3
```

---

## 🚀 Summary Commands

Copy-paste these commands for quick deployment:

```bash
# Pull changes
cd /home/travium/htdocs && sudo -u travium git pull origin main

# Restart service
sudo systemctl restart travium@s1.service

# Run diagnostic (optional)
sudo -u travium php diagnose_npc_build_train.php

# Watch logs
tail -f /home/travium/logs/npc_activity.log
```

---

## ✅ Success Criteria

You'll know the fix is working when:

1. ✅ Logs show `TRAIN_ATTEMPT` and `TRAIN_SUCCESS` entries
2. ✅ Database shows training queues for NPCs
3. ✅ NPCs accumulate troops over time
4. ✅ Raids succeed (no more `FARMLIST_NO_TROOPS` after NPCs have troops)
5. ✅ No PHP errors related to `maxUnitsOf`

---

**Need help?** Share the output of the diagnostic script or recent logs!
