# Clean Install Verification - NPC System

## Current Status: ✅ FULLY AUTOMATED

### What's Already Automated

1. **`install.sh` (lines 273-280)** ✅
   - Automatically runs `migrations/002_add_npc_columns.sql`
   - Adds all 5 NPC columns during fresh install
   - No manual steps needed

2. **`RegisterModel::addFakeUser()` (line 66)** ✅
   - Automatically calls `NpcConfig::assignRandom($uid)`
   - Every new fake user gets personality + difficulty
   - No manual configuration needed

3. **Migration Script** ✅
   - Uses `IF NOT EXISTS` for columns
   - Safe to run multiple times (idempotent)
   - Won't fail if columns already exist

---

## For Fresh Installations (New Users)

### What Happens Automatically

```bash
# User runs install.sh
./install.sh

# Behind the scenes:
# 1. Creates database
# 2. Imports maindb.sql
# 3. ✅ Automatically runs 002_add_npc_columns.sql (NEW!)
# 4. NPC columns ready from the start
```

When they create fake users (via admin panel or RegisterModel):
- ✅ Personality assigned automatically
- ✅ Difficulty assigned automatically
- ✅ NPC info JSON initialized automatically

**ZERO manual steps required!**

---

## For Existing Installations (Upgrading)

Users who already have the game installed need to:

1. Pull latest code: `git pull`
2. Run migration: `mysql ... < migrations/002_add_npc_columns.sql`
3. Update existing fake users: `php update_existing_fake_users.php`

This is a **one-time upgrade** process.

---

## Current User's Situation (mo7amed on Ubuntu)

✅ Columns already exist (migration previously applied)  
⏭️ Just needs to run: `php update_existing_fake_users.php`

After that, everything will be automated going forward.

---

## Testing Fresh Install

To test fully automated fresh install:

```bash
# 1. Drop database
mysql -u root -p -e "DROP DATABASE IF EXISTS maindb; CREATE DATABASE maindb;"

# 2. Run install.sh
./install.sh

# 3. Create fake users via admin panel
# They will automatically get NPC config!

# 4. Verify
mysql -u maindb -p maindb -e "
SELECT name, npc_personality, npc_difficulty 
FROM users WHERE access=3;"
```

Should show personality and difficulty assigned automatically.

---

## Conclusion

✅ **Fresh installs**: 100% automated, zero manual steps  
✅ **New fake users**: Auto-configured via RegisterModel  
✅ **Existing installs**: One-time upgrade script  

**For mo7amed**: Just run `php update_existing_fake_users.php` and you're done!
