#!/usr/bin/env bash
# =============================================================================
# Travium CDN Asset Downloader
# Downloads all missing Gpack images from the live Travian CDN.
#
# Usage: sudo bash tools/download_cdn_assets.sh [gpack_version] [local_cdn_dir] [css_file]
# Example: sudo bash tools/download_cdn_assets.sh 326.6
# =============================================================================
set -euo pipefail

# ---- Configuration ----------------------------------------------------------
GPACK_VERSION="${1:-326.6}"
LOCAL_CDN_DIR="${2:-/home/travium/htdocs/integrations/cdn/${GPACK_VERSION}/img_ltr}"
CSS_FILE="${3:-/home/travium/htdocs/integrations/cdn/${GPACK_VERSION}/css_ltr/imports_compressed.css}"
PARALLEL_JOBS="${4:-8}"

# Live Travian CDN - confirmed URL format from browser inspection of nys.x5.international.travian.com
# The real game uses gpack 347.6 even though our CSS is for 326.6
# We download FROM 347.6 and save TO our 326.6 folder since the assets are compatible
SOURCE_CDN_BASES=(
    "https://cdn.legends.travian.com/gpack/347.6"
    "https://cdn.legends.travian.com/gpack/${GPACK_VERSION}"
)

# Colors
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()  { echo -e "${BLUE}[INFO]${NC} $*"; }
ok()   { echo -e "${GREEN}[ OK ]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
err()  { echo -e "${RED}[ERR ]${NC} $*" >&2; }

# ---- Sanity checks ----------------------------------------------------------
[[ -f "$CSS_FILE" ]]   || { err "CSS not found: $CSS_FILE"; exit 1; }
[[ -d "$LOCAL_CDN_DIR" ]] || { err "CDN dir not found: $LOCAL_CDN_DIR"; exit 1; }
command -v curl >/dev/null || { err "curl required"; exit 1; }

log "========================================"
log " Travium CDN Asset Downloader"
log " Gpack   : $GPACK_VERSION"
log " CDN dir : $LOCAL_CDN_DIR"
log " Jobs    : $PARALLEL_JOBS parallel"
log "========================================"

# ---- Extract all image paths from CSS ---------------------------------------
log "Parsing CSS for image URLs..."
TMPDIR_WORK=$(mktemp -d)
trap "rm -rf $TMPDIR_WORK" EXIT

# Extract relative paths from url(../img_ltr/...) in the CSS
grep -oP "url\(['\"]?\.\./img_ltr/[^'\"\) ]+['\"]?\)" "$CSS_FILE" \
    | sed "s|url(['\"]\\?\\.\\.\/img_ltr\/||;s|['\"]*)$||" \
    | sort -u > "$TMPDIR_WORK/all_paths.txt"

TOTAL=$(wc -l < "$TMPDIR_WORK/all_paths.txt")
log "Found $TOTAL unique image references in CSS."

# ---- Find missing files -----------------------------------------------------
> "$TMPDIR_WORK/missing.txt"
while IFS= read -r rel_path; do
    [[ -z "$rel_path" ]] && continue
    [[ -f "$LOCAL_CDN_DIR/$rel_path" ]] || echo "$rel_path" >> "$TMPDIR_WORK/missing.txt"
done < "$TMPDIR_WORK/all_paths.txt"

MISSING=$(wc -l < "$TMPDIR_WORK/missing.txt")
PRESENT=$((TOTAL - MISSING))
log "Already present: $PRESENT | Missing: $MISSING"

if [[ $MISSING -eq 0 ]]; then
    ok "All assets already present! Nothing to download."
    exit 0
fi

# ---- Generate download commands ---------------------------------------------
> "$TMPDIR_WORK/download_cmds.sh"

# First find a working CDN base
WORKING_CDN=""
log "Testing CDN connectivity..."
for cdn_base in "${SOURCE_CDN_BASES[@]}"; do
    # Test with a known file from the live CDN
    TEST_URL="${cdn_base}/img_ltr/hud/sidebar/sidebarBox.png"
    HTTP=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 8 \
           -H "Referer: https://legends.travian.com/" "$TEST_URL" 2>/dev/null || echo "000")
    if [[ "$HTTP" == "200" ]]; then
        WORKING_CDN="$cdn_base"
        ok "CDN is reachable: $cdn_base"
        break
    else
        warn "CDN not reachable ($HTTP): $cdn_base"
    fi
done

if [[ -z "$WORKING_CDN" ]]; then
    err "No CDN is reachable. Check your internet connection."
    err "You can manually specify CDN: bash tools/download_cdn_assets.sh 326.6 /path/to/local/cdn"
    exit 1
fi

# Write a download function per file
while IFS= read -r rel_path; do
    [[ -z "$rel_path" ]] && continue
    local_file="$LOCAL_CDN_DIR/$rel_path"
    local_dir="$(dirname "$local_file")"
    url="${WORKING_CDN}/img_ltr/${rel_path}"
    echo "mkdir -p $(printf '%q' "$local_dir") && curl -fsSL --connect-timeout 10 --max-time 30 -H 'Referer: https://legends.travian.com/' -o $(printf '%q' "$local_file") $(printf '%q' "$url") && echo \"[OK] $rel_path\" || echo \"[FAIL] $rel_path\"" >> "$TMPDIR_WORK/download_cmds.sh"
done < "$TMPDIR_WORK/missing.txt"

# ---- Run downloads in parallel ----------------------------------------------
log "Downloading $MISSING missing assets ($PARALLEL_JOBS parallel jobs)..."
echo ""

DOWNLOADED=0
FAILED=0

# Run using xargs -P for parallelism (available everywhere)
bash_results=$(cat "$TMPDIR_WORK/download_cmds.sh" | xargs -P "$PARALLEL_JOBS" -I{} bash -c '{}' 2>&1)

while IFS= read -r line; do
    if [[ "$line" == "[OK]"* ]]; then
        DOWNLOADED=$((DOWNLOADED + 1))
        echo -e "${GREEN}${line}${NC}"
    elif [[ "$line" == "[FAIL]"* ]]; then
        FAILED=$((FAILED + 1))
        echo -e "${YELLOW}${line}${NC}"
    fi
done <<< "$bash_results"

# ---- Fix ownership ----------------------------------------------------------
SITE_USER=$(stat -c '%U' /home/travium/htdocs 2>/dev/null || echo "travium")
chown -R "$SITE_USER:$SITE_USER" "$LOCAL_CDN_DIR" 2>/dev/null || true

# ---- Summary ----------------------------------------------------------------
echo ""
log "========================================"
ok " Present before  : $PRESENT / $TOTAL"
ok " Downloaded now  : $DOWNLOADED"
[[ $FAILED -gt 0 ]] && warn " Failed (not on CDN) : $FAILED" || true
log "========================================"
ok "Done! Hard-refresh your browser (Ctrl+F5)."
