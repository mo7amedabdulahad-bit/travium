# NPC Raid Frequency & Logging - Quick Reference

## What Changed (Commit: 2eef27c)

### 1. Fixed NPC Logging ✅
- **Problem**: Logging failed silently (tried to query DB from automation context)
- **Solution**: Simplified to log just `[NPC:uid]` format without DB queries
- **Result**: Logs will now actually work!

### 2. Speed-Based Raid Frequency ✅
**Formula**: `Base (6 hours) ÷ Server Speed × Personality Multiplier`

**For Your 25x Server**:
| Personality | Multiplier | Min Cooldown | Max Cooldown |
|-------------|-----------|--------------|--------------|
| Aggressive  | 15-30%    | ~2 minutes   | ~4 minutes   |
| Economic    | 100-200%  | ~14 minutes  | ~28 minutes  |
| Balanced    | 35-70%    | ~5 minutes   | ~10 minutes  |
| Assassin    | 35-70%    | ~5 minutes   | ~10 minutes  |
| Diplomat    | 200-300%  | ~28 minutes  | ~43 minutes  |

**Absolute Minimum**: 5 minutes (prevents spam)

---

## Testing on Ubuntu

```bash
# 1. Pull latest code
cd /home/travium/htdocs
sudo -u travium git pull origin main

# 2. Reset NPC cooldowns to force immediate raid attempt
mysql -u maindb -pXBpC34uqd@Yj5TfuBMxaAMJW maindb -e "
UPDATE users 
SET npc_info = JSON_SET(npc_info, '$.last_raid_time', 1) 
WHERE access=3;"

# 3. Wait 2 minutes for automation to process
sleep 120

# 4. Check logs (should see entries now!)
sudo -u travium php view_npc_logs.php

# 5. Check if raids were sent
mysql -u maindb -pXBpC34uqd@Yj5TfuBMxaAMJW maindb -e "
SELECT COUNT(*) as raids_sent 
FROM movement m
JOIN vdata v ON m.kid = v.kid
JOIN users u ON v.owner = u.id
WHERE u.access=3 AND m.attack_type=4;"

# 6. Monitor live activity (Ctrl+C to stop)
watch -n 10 'cat /home/travium/logs/npc_activity.log | tail -20'
```

---

## Expected Log Output (Example)

```
[2025-12-28 18:15:22] [NPC:5] CYCLE: Starting AI cycle with 50 iterations | {"iterations":50}
[2025-12-28 18:15:23] [NPC:5] BUILD: Selected Cropland (ID: 1) | {"building_id":1,"reason":"economy_focus"}
[2025-12-28 18:16:45] [NPC:5] TRAIN: Training 10 x Legionnaire (ID: 1) | {"unit_id":1,"amount":10}
[2025-12-28 18:18:30] [NPC:5] TARGET: Selected target from 15 options: Enemy Village | {"available_targets":15,"selected_kid":523}
[2025-12-28 18:18:31] [NPC:5] RAID: Sent 25 troops to Enemy Village (Distance: 7.2 tiles) | {"total_troops":25}
```

---

## Troubleshooting

### No Logs Appearing?
```bash
# Check log file permissions
ls -la /home/travium/logs/npc_activity.log

# Ensure directory exists
sudo -u travium mkdir -p /home/travium/logs

# Check automation is running
sudo systemctl status travium@s1.service
```

### No Raids Sent?
Your NPCs are **Economic/Expert** - they raid every 14-28 minutes on 25x.
- Wait at least 30 minutes
- Check they have troops available
- Check for valid targets (non-protected players)

---

## Current NPC Status

**Ma7ame7o** & **Lion Hreart**:
- Personality: Economic
- Difficulty: Expert
- Server Speed: 25x
- **Raid Frequency**: Every 14-28 minutes
- **Actions per cycle**: 50 (very active!)

**They should start raiding within 30 minutes after you pull the update!**
