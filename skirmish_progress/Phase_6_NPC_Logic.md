# Phase 2: NPC Architecture & Logic

## Overview
This phase established the intelligence, behavior, and automation of the Non-Player Characters (NPCs). The system is designed to be efficient, scalable, and capable of simulating human-like strategies.

## Core Components

### 1. The Scheduler (`NpcScheduler.php`)
- **Role**: The heartbeat of the AI.
- **Function**: It runs every few seconds via `Jobs\Launcher.php`.
- **Logic**: It selects a batch of NPCs that haven't acted recently (based on `last_processed_time`) and triggers their "Tick".

### 2. The Brain (`NpcScriptEngine.php`)
- **Role**: The decision maker.
- **Function**: `executeTick($npcRow)`
- **Workflow**:
    1.  **Context**: Loads the NPC's `server_settings` (Difficulty) and `npc_personality_templates` (Personality).
    2.  **Mistake Check**: potentially skips the turn based on difficulty (simulating human inefficiency).
    3.  **Action Delegation**: Calls specific Managers to perform tasks defined in the template.

### 3. The Hands (Managers)
Scripts that perform specific category of actions:

-   **`NpcBuildingManager.php`**:
    -   Handles construction queues.
    -   **Fix**: Correctly registers building types in `fdata` (fixing "0.title" errors).
    -   **Logic**: Checks resource costs and prerequisites before adding to the queue.

-   **`NpcRaidManager.php` / `NpcAttackManager.php` / `NpcRetaliationManager.php`**:
    -   **Raids**: Finds nearby targets (lower population preferred) and sends farm raids.
    -   **Attacks**: Executes full-force attacks based on "War Village" logic.
    -   **Retaliation**: Stores a memory of who attacked the NPC. If attacked, the NPC prioritizes striking back.

### 4. Personality Templates
Stored in the `npc_personality_templates` table.
-   **Aggressive**: Prioritizes Barracks, Rally Point, and Offensive Troops.
-   **Economic**: Prioritizes Fields, Warehouses, and Granaries.
-   **Guardian**: High defense focus.
-   **Phase-Based**: Templates evolve as the server ages (Early -> Mid -> Late game).

## Key Data Structures
-   **`server_settings`**: Global configuration (Difficulty, Map Size).
-   **`users.npc_difficulty`**: Per-NPC setting (Easy/Medium/Hard).
-   **`users.npc_personality`**: The Archetype (Aggressive, Economic, etc.).
-   **`users.war_village_id`**: The designated village for launching military operations.

## Current State
-   NPCs successfully build, upgrade fields, and train troops.
-   Aggressive NPCs launch random raids.
-   Retaliation system is active (requires player interaction to trigger).
