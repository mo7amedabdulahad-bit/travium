# Git Workflow for NPC System Phase 2

## Files to Commit

### Core Implementation
- `migrations/002_add_npc_columns.sql` - Database schema migration
- `migrations/002_verify.sql` - Migration verification script
- `src/Core/NpcConfig.php` - NPC configuration system
- `src/Core/AI/PersonalityAI.php` - Personality-driven building AI
- `src/Core/AI/RaidAI.php` - Raid AI system (THE CRITICAL FEATURE)

### Modified Files
- `src/Core/AI.php` - Updated doSomethingRandom() with NPC raid logic
- `src/Game/Buildings/AutoUpgradeAI.php` - Integrated PersonalityAI
- `src/Model/RegisterModel.php` - Auto-assign NPC config on creation

### Test Scripts
- `test_npc_config.php` - Test NPC configuration system
- `test_personality_ai.php` - Test personality AI
- `test_raid_ai.php` - Test raid AI
- `debug_npc_system.php` - Comprehensive debugger
- `update_existing_fake_users.php` - Update existing fake users
- `run_npc_tests.sh` - Test runner (Linux/Mac)
- `run_npc_tests.bat` - Test runner (Windows)

### Documentation
- `C:\Users\Mohamed\.gemini\antigravity\brain\889313bf-782b-451f-a2c7-188bcea54d62\task_2_1_database_schema_complete.md`
- `C:\Users\Mohamed\.gemini\antigravity\brain\889313bf-782b-451f-a2c7-188bcea54d62\task_2_2_npc_config_complete.md`
- `C:\Users\Mohamed\.gemini\antigravity\brain\889313bf-782b-451f-a2c7-188bcea54d62\task_2_3_personality_building_ai_complete.md`
- `C:\Users\Mohamed\.gemini\antigravity\brain\889313bf-782b-451f-a2c7-188bcea54d62\task_2_5_raid_ai_complete.md`
- `C:\Users\Mohamed\.gemini\antigravity\brain\889313bf-782b-451f-a2c7-188bcea54d62\TESTING_GUIDE.md`

## Git Commands

### Step 1: Check Status

```bash
cd "C:\Users\Mohamed\OneDrive\Desktop\NPC Project\Travium"
git status
```

### Step 2: Stage All NPC Files

```bash
# Add new files
git add migrations/002_add_npc_columns.sql
git add migrations/002_verify.sql
git add src/Core/NpcConfig.php
git add src/Core/AI/PersonalityAI.php
git add src/Core/AI/RaidAI.php
git add test_npc_config.php
git add test_personality_ai.php
git add test_raid_ai.php
git add debug_npc_system.php
git add update_existing_fake_users.php
git add run_npc_tests.sh
git add run_npc_tests.bat

# Add modified files
git add src/Core/AI.php
git add src/Game/Buildings/AutoUpgradeAI.php
git add src/Model/RegisterModel.php

# Or add all at once
git add -A
```

### Step 3: Commit Changes

```bash
git commit -m "Phase 2: NPC Foundation - Complete Implementation

Features Added:
- Database schema extension (5 new columns for NPCs)
- NPC Configuration System (personalities & difficulty levels)
- Personality-Driven Building AI (5 distinct behaviors)
- Raiding AI System (THE CRITICAL FEATURE)
  - Intelligent target selection
  - Alliance/NAP protection
  - Personality-based raid frequencies
  - Automatic troop management

New Files:
- migrations/002_add_npc_columns.sql
- src/Core/NpcConfig.php
- src/Core/AI/PersonalityAI.php
- src/Core/AI/RaidAI.php
- 7 test scripts for verification

Modified Files:
- src/Core/AI.php (integrated raid logic)
- src/Game/Buildings/AutoUpgradeAI.php (personality selection)
- src/Model/RegisterModel.php (auto-config new NPCs)

Impact:
- NPCs transform from passive farms to active opponents
- Real players get meaningful NPC interactions
- Game world feels alive and dynamic
- 5 personality types with distinct behaviors

Testing:
- Run: php update_existing_fake_users.php
- Then: php debug_npc_system.php
- Monitor raids over 24-48 hours

See TESTING_GUIDE.md for detailed instructions."
```

### Step 4: Push to GitHub

```bash
git push origin main
```

**Expected Output:**
```
Enumerating objects: 45, done.
Counting objects: 100% (45/45), done.
Delta compression using up to 8 threads
Compressing objects: 100% (30/30), done.
Writing objects: 100% (35/35), 125.50 KiB | 8.37 MiB/s, done.
Total 35 (delta 20), reused 0 (delta 0), pack-reused 0
To github.com:yourusername/NPC-Project.git
   a1b2c3d..e4f5g6h  main -> main
```

## For User to Pull on Ubuntu/WSL

```bash
cd ~/NPC\ Project/Travium  # Or your path

# Pull latest
git pull origin main

# Apply migration
mysql -u maindb -pmaindb maindb < migrations/002_add_npc_columns.sql

# Update existing fake users
php update_existing_fake_users.php

# Run comprehensive test
php debug_npc_system.php
```

## Quick Verification After Pull

```bash
# Check files exist
ls -la src/Core/NpcConfig.php
ls -la src/Core/AI/PersonalityAI.php
ls -la src/Core/AI/RaidAI.php
ls -la migrations/002_add_npc_columns.sql

# Count new/modified files
git diff HEAD~1 --stat | wc -l  # Should show ~15+ files

# Check last commit
git log -1 --oneline
```

## Troubleshooting Git

### If merge conflicts occur:

```bash
git stash  # Save local changes
git pull origin main
git stash pop  # Reapply local changes
```

### If push rejected (diverged):

```bash
git fetch origin
git rebase origin/main
git push origin main
```

## Branch Strategy (Optional)

If you want to test on a separate branch first:

```bash
# Create feature branch
git checkout -b phase-2-npc-foundation

# Commit changes
git add -A
git commit -m "Phase 2: NPC Foundation"

# Push branch
git push origin phase-2-npc-foundation

# On Ubuntu, pull branch
git fetch origin
git checkout phase-2-npc-foundation

# After testing, merge to main
git checkout main
git merge phase-2-npc-foundation
git push origin main
```

---

**Status**: Ready to commit and push!  
**Testing**: See TESTING_GUIDE.md for complete instructions
