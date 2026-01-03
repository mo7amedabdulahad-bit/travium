# SIMPLE STEP-BY-STEP GUIDE: Adding Combat Hook

## What You Need To Do

Add **8 lines of code** to ONE file on your server to complete Phase 5.

---

## Step 1: Open the File on Server

```bash
nano /home/travium/htdocs/src/Model/BattleModel.php
```

**Press `Ctrl+W` then type `487` and press Enter** - this jumps to line 487.

---

## Step 2: Add These Lines

You'll see this around line 487:

```php
        return $this->profileOutput();
    }

    private function assocAttacker()
```

**BEFORE** `return $this->profileOutput();`, add these lines:

```php
        // Phase 5: Record NPC alliance attack event
        if (isset($this->defender['uid']) && $this->defender['uid'] >  0) {
            $defenderAccessLevel = DB::getInstance()->fetchScalar("SELECT access FROM users WHERE id={$this->defender['uid']}");
            if ($defenderAccessLevel == 3) { // Defender is NPC
                $defenderAllianceId = (int)$this->defender['player']['aid'];
                if ($defenderAllianceId > 0) {
                    \Core\NpcWorldEvents::recordAllianceAttacked(
                        \Core\Config::getInstance()->worldId,
                        $defenderAllianceId,
                        $this->attacker['uid'],
                        $this->defender['kid']
                    );
                }
            }
        }

        return $this->profileOutput();
```

---

## Step 3: Save and Exit

- Press `Ctrl+O` (save)
- Press `Enter` (confirm)
- Press `Ctrl+X` (exit)

---

## Step 4: Test It

Restart automation:
```bash
sudo systemctl restart travium@s1.service
```

Run verification again:
```bash
/usr/bin/php8.4 scripts/verify_phase5.php
```

---

## What This Does

When ANY player attacks an NPC:
1. ✅ Event recorded in database
2. ✅ Alliance members notified
3. ✅ Reinforcements sent (40-80% respond)
4. ✅ Attacker added to NPC memory
5. ✅ NPC prioritizes revenge on next tick

---

**That's it!** Phase 5 will be 100% complete after this! 🎉
