#!/usr/bin/env bash
# =============================================================================
# Download all JavaScript files from the live Travian CDN (Gpack 347.6)
# and wire them into the local game server.
#
# Usage: sudo bash tools/download_js_files.sh
# =============================================================================
set -euo pipefail

GPACK_VERSION="${1:-347.6}"
CDN_BASE="https://cdn.legends.travian.com/gpack/${GPACK_VERSION}"
HTDOCS="${HTDOCS:-/home/travium/htdocs}"
DEST="${HTDOCS}/integrations/cdn/${GPACK_VERSION}"
SITE_USER=$(stat -c '%U' "$HTDOCS" 2>/dev/null || echo "travium")
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
REF="https://legends.travian.com/"

# ─── Colors ──────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'
log()  { echo -e "${BLUE}[INFO]${NC}  $*"; }
ok()   { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()  { echo -e "${RED}[ERR ]${NC}  $*"; }

dl() {
  local rel="$1"
  local dest_file="$DEST/$rel"
  mkdir -p "$(dirname "$dest_file")"
  local code
  code=$(curl -fsSL --connect-timeout 15 --max-time 120 \
    -H "Referer: $REF" -A "$UA" \
    -o "$dest_file" -w "%{http_code}" \
    "${CDN_BASE}/${rel}" 2>/dev/null || echo "000")
  if [[ "$code" == "200" ]]; then
    local size
    size=$(du -sh "$dest_file" 2>/dev/null | cut -f1)
    ok "${rel} (${size})"
    return 0
  else
    rm -f "$dest_file"
    warn "FAIL ($code): $rel"
    return 1
  fi
}

echo ""
log "========================================================"
log " Travian JS Downloader — Gpack ${GPACK_VERSION}"
log " CDN  : ${CDN_BASE}"
log " Dest : ${DEST}"
log "========================================================"
echo ""

# ─── Core Libraries ──────────────────────────────────────────────────────────
log "━━ Core Libraries"
dl js/jquery-3.5.1.min.js
dl js/jquery.md5.min.js
dl js/deepmerge.js
dl js/simplebar.min.js
dl js/popper.min.js
dl js/tippy.min.js

# ─── D3 & Chart ──────────────────────────────────────────────────────────────
log "━━ D3 / Charts"
dl js/d3/d3.min.js
dl js/d3/d3pie.min.js
dl js/ChartJs/Chart.min.js

# ─── GSAP Animation ──────────────────────────────────────────────────────────
log "━━ GSAP Animation"
dl js/gsap/TweenMax.min.js
dl js/gsap/plugins/MorphSVGPlugin.min.js

# ─── PixiJS (map rendering) ───────────────────────────────────────────────────
log "━━ PixiJS"
dl js/PixiJS/pixi.min.js

# ─── Core Bundles (main app) ─────────────────────────────────────────────────
log "━━ App Bundles (this may take a while — large files)"
dl js/bundle/vendor.js
dl js/bundle/runtime.js
dl js/bundle/main.js
dl js/bundle/crypt.js

# ─── Additional JS files (check for extras) ───────────────────────────────────
log "━━ Checking for additional bundle chunks..."
for i in $(seq 1 20); do
  rel="js/bundle/${i}.js"
  code=$(curl -sI --connect-timeout 5 --max-time 10 \
    -H "Referer: $REF" -A "$UA" \
    -o /dev/null -w "%{http_code}" \
    "${CDN_BASE}/${rel}" 2>/dev/null || echo "000")
  [[ "$code" == "200" ]] && dl "$rel" || true
done

# ─── Fix permissions ─────────────────────────────────────────────────────────
chown -R "$SITE_USER:$SITE_USER" "$DEST/js" 2>/dev/null || true

# ─── Create symlinks in server public dirs ───────────────────────────────────
log "━━ Setting up server symlinks..."
for server_public in "$HTDOCS"/servers/*/public; do
  [[ -d "$server_public" ]] || continue
  # Symlink the full CDN folder so JS can be served at /integrations/cdn/347.6/js/
  ln -sfn "$HTDOCS/integrations" "${server_public}/integrations" 2>/dev/null || true
  chown -h "$SITE_USER:$SITE_USER" "${server_public}/integrations" 2>/dev/null || true
  ok "Symlink: ${server_public}/integrations → ${HTDOCS}/integrations"
done

echo ""
log "========================================================"
ok " JS download complete!"
log "========================================================"
log " Files are at: ${DEST}/js/"
log " Served via  : /integrations/cdn/${GPACK_VERSION}/js/"
echo ""
ok "Hard-refresh (Ctrl+F5) your game to load the new JS."
