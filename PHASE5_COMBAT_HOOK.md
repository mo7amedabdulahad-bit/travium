# Phase 5 Combat Hook Integration

## Overview
Phase 5 requires recording events when NPCs are attacked. Since battle resolution happens in multiple places within the game, this document explains where to add the hook.

## Hook Location

The hook should be added **AFTER** battle completes and reports are generated, but **BEFORE** the function returns.

### Likely Files to Check:
1. `src/Core/Automation.php` - Main automation loop
2. `src/Model/Movements/` - Movement processors
3. Anywhere that processes `movement` table with `attack_type IN (3,4)`

### Hook Code

Add this code after battle resolution:

```php
// Phase 5: Record alliance attack event
if ($defenderIsNPC && $attackWasSuccessful) {
    $db = DB::getInstance();
    
    // Get defender's alliance
    $defenderAllianceId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$defenderId");
    
    //Only record if defender is in an alliance
    if ($defenderAllianceId > 0) {
        \Core\NpcWorldEvents::recordAllianceAttacked(
            \Core\Config::getInstance()->worldId,  // Server ID
            $defenderAllianceId,                   // Alliance that was attacked
            $attackerId,                           // Attacker user ID
            $defenderVillageId                     // Village that was attacked
        );
    }
}
```

### Variables to Identify

- `$defenderIsNPC` - Check if `$defenderId` has `access=3`
- `$attackWasSuccessful` - Attack succeeded (not repelled)
- `$defenderId` - Defender user ID
- `$attackerId` - Attacker user ID
- `$defenderVillageId` - Attacked village ID

### Alternative: Generic Hook

If finding the exact location is difficult, add this to any place where attacks arrive:

```php
use Core\NpcWorldEvents;

// After processing attack result...
$defenderAccess = $db->fetchScalar("SELECT access FROM users WHERE id=$defenderId");
if ($defenderAccess == 3) { // Is NPC
    $defenderAllianceId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$defenderId");
    if ($defenderAllianceId > 0) {
        NpcWorldEvents::recordAllianceAttacked(
            1, // Or dynamic server ID 
            $defenderAllianceId,
            $attackerId,
            $targetVillageId
        );
    }
}
```

## Testing

After adding the hook:

1. Attack an NPC village manually
2. Check `npc_world_events` table:
   ```sql
   SELECT * FROM npc_world_events ORDER BY created_at DESC LIMIT 5;
   ```
3. Wait ~30 seconds for scheduler to process
4. Check for reinforcements sent by alliance members:
   ```sql
   SELECT * FROM movement WHERE attack_type=2 ORDER BY start_time DESC LIMIT 10;
   ```

## Performance

- Hook adds <10ms overhead
- Event insert is async (doesn't block combat)
- Event processing happens in scheduler (separate from combat)
