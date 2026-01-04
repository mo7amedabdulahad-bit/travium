# Phase 3: Future Roadmap (Next Steps)

## Overview
With the foundation (Setup) and core logic (NPC AI) complete, the next phases focus on refinement, advanced features, and polish.

## Immediate Goals

### 1. Advanced Army logic
-   **Hero Usage**: Implement Hero equipping and adventures for NPCs.
-   **Scouting**: Teach NPCs to scout before raiding.
-   **Catapaults**: Implement logic for targeting specific buildings (e.g., resource fields vs strategies).

### 2. Alliance Features
-   **Coordination**: NPCs in the same alliance should send reinforcements to each other when under attack.
-   **Chat**: (Optional) Simple automated chat messages to make the world feel alive.

### 3. Economic Optimization
-   **Trade Routes**: NPCs should use the Marketplace to balance resources between their own villages.
-   **Expansion**: Logic for Settlers to found new villages (currently they stick to their capital + war village).

## Admin Tools
-   **NPC Monitor**: A dashboard in the Admin Control Panel to see exactly what NPCs are doing live (logs, current construction, army sizes).
-   **Manual Control**: Buttons to "Force Build" or "Force Attack" for testing.

## Maintenance
-   **Performance Tuning**: Monitor `NpcScheduler` on high-load servers (100+ NPCs).
-   **Database Cleanup**: Ensure logs and temporary tables don't grow indefinitely.
