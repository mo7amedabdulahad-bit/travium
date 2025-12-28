#!/bin/bash

# NPC Activity Log Viewer
# Shows real-time NPC activity logs

LOG_FILE="/home/travium/logs/npc_activity.log"
DETAILED_LOG="/home/travium/logs/npc_detailed.log"

echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║                    NPC ACTIVITY LOG VIEWER                                 ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Check if log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo "No log file found. NPCs haven't been processed yet."
    echo "Log will be created at: $LOG_FILE"
    echo ""
    echo "Run automation or wait for NPCs to take actions."
    exit 0
fi

# Show usage
echo "Usage:"
echo "  $0             - Show last 50 log entries"
echo "  $0 tail        - Monitor logs in real-time"
echo "  $0 all         - Show all logs"
echo "  $0 <npc_name>  - Filter by NPC name"
echo ""

case "$1" in
    tail)
        echo "Monitoring NPC activity (Ctrl+C to stop)..."
        echo "════════════════════════════════════════════════════════════════════════════"
        tail -f "$LOG_FILE"
        ;;
    all)
        echo "All NPC Activity:"
        echo "════════════════════════════════════════════════════════════════════════════"
        cat "$LOG_FILE"
        ;;
    "")
        echo "Last 50 NPC Actions:"
        echo "════════════════════════════════════════════════════════════════════════════"
        tail -50 "$LOG_FILE"
        ;;
    *)
        echo "Filtering for NPC: $1"
        echo "════════════════════════════════════════════════════════════════════════════"
        grep -i "$1" "$LOG_FILE"
        ;;
esac

echo ""
echo "════════════════════log════════════════════════════════════════════════════"
echo "Log file: $LOG_FILE"
echo "Total entries: $(wc -l < $LOG_FILE 2>/dev/null || echo 0)"
echo ""
