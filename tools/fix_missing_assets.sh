#!/usr/bin/env bash
# Download remaining missing assets including report icons and nav icons
set -e

CDN='https://cdn.legends.travian.com/gpack/347.6/img_ltr'
DEST='/home/travium/htdocs/integrations/cdn/326.6/img_ltr'
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
REF='https://legends.travian.com/'

dl() {
  local rel="$1"
  local outfile="$DEST/$rel"
  mkdir -p "$(dirname "$outfile")"
  local code
  code=$(curl -s -o "$outfile" -w '%{http_code}' --max-time 20 \
    -H "Referer: $REF" -A "$UA" "$CDN/$rel" 2>/dev/null || echo "000")
  if [[ "$code" == "200" ]]; then
    echo "[OK] $rel"
  else
    rm -f "$outfile"
    echo "[FAIL $code] $rel"
  fi
}

echo "=== Downloading remaining missing assets ==="

# Report icons
dl report/mini_house-ltr.png
dl report/mini_house-rtl.png

# Navigation icons (the top bar navigation li items use sprite background images)
# These come from compact.css loaded from the local img/ folder, not CDN
# But let's try some CDN paths
dl hud/topBar/navigation/villages.png
dl hud/topBar/navigation/buildings.png
dl hud/topBar/navigation/map.png
dl hud/topBar/navigation/statistics.png
dl hud/topBar/navigation/reports.png
dl hud/topBar/navigation/messages.png
dl hud/topBar/navigation/dailyQuests.png
dl hud/topBar/navigation/plus.png

# Additional winter theme village centers
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid1.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid2.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid3.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid4.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid5.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid6.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid7.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid8.png
dl themes/winter/background/resourceFields/tribeSpecificCenter/villageCenter_vid9.png

# Default theme village centers
dl themes/default/background/resourceFields/tribeSpecificCenter/villageCenter_vid1.png
dl themes/default/background/resourceFields/tribeSpecificCenter/villageCenter_vid2.png
dl themes/default/background/resourceFields/tribeSpecificCenter/villageCenter_vid3.png
dl themes/default/background/resourceFields/tribeSpecificCenter/villageCenter_vid4.png
dl themes/default/background/resourceFields/tribeSpecificCenter/villageCenter_vid5.png

# Fix permissions
chown -R travium:travium "$DEST"
echo "=== DONE ==="
