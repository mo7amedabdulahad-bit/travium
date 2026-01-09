# NPC Building Logic Documentation

This document outlines the logic used by the NPC AI to prioritize and select buildings for construction and upgrades. The system is designed to create varied and realistic behaviors based on assigned "Personalities".

## 1. Core Architecture
The logic is split between three main components:
- **`src/Game/Buildings/AutoUpgradeAI.php`**: The execution engine that handles resource checks, dependencies, and queue management.
- **`src/Core/AI/PersonalityAI.php`**: The decision engine that selects *which* building to upgrade next.
- **`src/Core/NpcConfig.php`**: The configuration file defining personality stats and preferences.

## 2. Decision Logic Flow
When an NPC (Access Level 3) attempts to upgrade a building, the following logic applies:

### Phase 1: Early Game (First 90 Minutes)
*   **Condition**: Village age < 5400 seconds (1.5 hours).
*   **Action**: ALWAYS selects a random Resource Field (IDs 1-18).
*   **Reason**: Ensures all NPCs establish a basic economy before branching out.

### Phase 2: Personality-Driven Selection
After the early game, a random percentage roll (1-100) is made against the NPC's `economy_focus` and `military_focus` stats.

1.  **Economy Roll** (`roll <= economy_focus`)
    *   **60% Chance**: Select a **Resource Field** (1-18).
    *   **40% Chance**: Select an **Economy Building** (e.g., Warehouse, Granary, Mill).

2.  **Military Roll** (`roll <= economy_focus + military_focus`)
    *   Selects a **Military Building** (e.g., Barracks, Stable, Academy).
    *   *Note*: Logic prioritizes filling empty slots with military buildings if none exist.

3.  **Preference/Fallback Roll** (Remaining probability)
    *   Selects a **Preferred Building** specific to the personality (e.g., Embassy for Diplomats).
    *   If no specific preference applies, falls back to a random selection.

## 3. Personalities

### Aggressive
*   **Focus**: 70% Military / 30% Economy
*   **Raid Frequency**: High (Every 1-3 hours)
*   **Preferred Buildings**: Barracks (19), Stable (20), Academy (22), Smithy (13)
*   **Strategy**: heavy troop production and continuous raiding.

### Economic
*   **Focus**: 20% Military / 80% Economy
*   **Raid Frequency**: Low (Every 12-24 hours)
*   **Preferred Buildings**: Resource Fields, Warehouse (10), Granary (11)
*   **Strategy**: Maximizing resource production and storage capacity.

### Balanced
*   **Focus**: 50% Military / 50% Economy
*   **Raid Frequency**: Medium (Every 4-8 hours)
*   **Preferred Buildings**: Mixed/Balanced set.
*   **Strategy**: Standard gameplay, growing economy and army in equal measure.

### Diplomat
*   **Focus**: 30% Military / 70% Economy
*   **Raid Frequency**: Very Low (Every 24-48 hours)
*   **Preferred Buildings**: Marketplace (17), Embassy (18), Residence (23)
*   **Strategy**: Joining alliances and trading.

### Assassin
*   **Focus**: 60% Military / 40% Economy
*   **Raid Frequency**: Medium (Every 2-5 hours)
*   **Preferred Buildings**: Academy (22), Smithy (13), Hideout (24)
*   **Strategy**: Specialized unit research (Scouts/Spies) and quick strikes.

## 4. Building Categories

### Military Buildings
*   Barracks (ID 19)
*   Stable (ID 20)
*   Workshop (ID 21)
*   Smithy (ID 13)
*   Academy (ID 22)
*   Tournament Square (ID 14)
*   Horse Drinking Trough (ID 41)

### Economy Buildings
*   Sawmill (ID 5), Brickyard (ID 6), Iron Foundry (ID 7)
*   Grain Mill (ID 8), Bakery (ID 9)
*   Warehouse (ID 10), Great Warehouse (ID 38)
*   Granary (ID 11), Great Granary (ID 39)

### Diplomat Buildings
*   Marketplace (ID 17)
*   Embassy (ID 18)
*   Residence (ID 23)
*   Palace (ID 26)
*   Main Building (ID 28)

### Assassin Buildings
*   Academy (ID 22)
*   Smithy (ID 13)
*   Hideout (ID 24)
