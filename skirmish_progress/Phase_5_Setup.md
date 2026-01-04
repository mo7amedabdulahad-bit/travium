# Phase 1: Skirmish Mode Setup & Installation

## Overview
This phase focussed on implementing the "Skirmish Mode" within the Travium installer, allowing a single player to play privately against AI bots.

## Key Components

### 1. Installer UI Updates
Location: `integrations/install/index.php`

The installer now features a dedicated **Skirmish Configuration Panel**:
- **Installation Mode**: Toggle between "Multiplayer" and "Skirmish".
- **Player Setup**: Custom inputs for Username, Tribe, Email, and Password.
- **Quadrant Selection**: Interactive toggle to choose starting location (NE, NW, SE, SW).
- **NPC Controls**:
    - **Count**: Select from 0 to 100 NPCs.
    - **Difficulty**: Dropdown for **Easy**, **Medium**, or **Hard**.

### 2. Backend Logic
Location: `integrations/install/skirmish_setup.php`

This script is the brain of the skirmish initialization. It is triggered by the installer if "Skirmish Mode" is selected.

#### Features:
1.  **Auto-Validation**: Checks input integrity.
2.  **Database Auto-Repair**: Automatically executes critical migrations (`008_fix_npc_difficulty_enum.sql`, `010_populate_existing_personalities.sql`) to ensure the database schema can support NPCs, even on fresh installs.
3.  **Server Settings**: Inserts the user-selected **Difficulty** and **NPC Count** into the `server_settings` table immediately.
4.  **NPC Generation**:
    - **Leaders**: Creates 3 Alliance Leaders in the rival quadrants.
    - **Mass NPCs**: Fills the rest of the count.
    - **Personalities**: Assigns 'Aggressive' personalities to front-line NPCs and 'Economic' to back-line ones.

### 3. Server Integration
Location: `install.sh`

The main installation script ensures the underlying system (Systemd, MySQL/MariaDB, CloudPanel) is ready to host the game engine.

## Usage
1. Run the installer via the web interface.
2. Select **Skirmish Mode**.
3. Configure your Player and NPC settings.
4. Click **Install**.
5. Log in to your new private world!
