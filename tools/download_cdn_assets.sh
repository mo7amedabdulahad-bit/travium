#!/usr/bin/env bash
# =============================================================================
# Travium CDN Asset Downloader
# Downloads all missing Gpack assets from the live Travian CDN.
# Run this on the Ubuntu server: sudo bash tools/download_cdn_assets.sh
# =============================================================================
set -euo pipefail

# ---- Configuration ----------------------------------------------------------
GPACK_VERSION="${1:-326.6}"
# Must try multiple source CDNs since the live CDN may vary by server
SOURCE_CDNS=(
    "https://cdn.legends.travian.com/img/${GPACK_VERSION}"
    "https://resources.travian.com/img/${GPACK_VERSION}"
)
LOCAL_CDN_DIR="${2:-/home/travium/htdocs/integrations/cdn/${GPACK_VERSION}/img_ltr}"
CSS_FILE="${3:-/home/travium/htdocs/integrations/cdn/${GPACK_VERSION}/css_ltr/imports_compressed.css}"

# Colors
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()   { echo -e "${BLUE}[INFO]${NC} $*"; }
ok()    { echo -e "${GREEN}[OK]${NC} $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
err()   { echo -e "${RED}[ERROR]${NC} $*"; }

# ---- Sanity checks ----------------------------------------------------------
[[ -f "$CSS_FILE" ]] || { err "CSS file not found: $CSS_FILE"; exit 1; }
[[ -d "$LOCAL_CDN_DIR" ]] || { err "CDN dir not found: $LOCAL_CDN_DIR"; exit 1; }
command -v curl >/dev/null 2>&1 || { err "curl not found"; exit 1; }
command -v grep >/dev/null 2>&1 || { err "grep not found"; exit 1; }

log "============================================="
log " Travium CDN Asset Downloader"
log " Gpack version : $GPACK_VERSION"
log " Local CDN dir : $LOCAL_CDN_DIR"
log " CSS file      : $CSS_FILE"
log "============================================="

# ---- Extract all image paths from the CSS -----------------------------------
log "Parsing CSS for image references..."

# Extract all url(...) values that point to ../img_ltr/ paths
IMG_PATHS=$(grep -oP "url\(['\"]?\.\./img_ltr/[^'\"\)]+['\"]?\)" "$CSS_FILE" \
    | sed "s|url(['\"]\\?\\.\\./img_ltr/||g" \
    | sed "s|['\"]*)$||g" \
    | sort -u)

TOTAL=$(echo "$IMG_PATHS" | wc -l)
log "Found $TOTAL unique image references in CSS."

# ---- Check and download missing assets --------------------------------------
DOWNLOADED=0
SKIPPED=0
FAILED=0
MISSING_LIST=()

while IFS= read -r rel_path; do
    [[ -z "$rel_path" ]] && continue

    local_file="$LOCAL_CDN_DIR/$rel_path"

    # Already exists — skip
    if [[ -f "$local_file" ]]; then
        SKIPPED=$((SKIPPED + 1))
        continue
    fi

    MISSING_LIST+=("$rel_path")
done <<< "$IMG_PATHS"

MISSING_COUNT=${#MISSING_LIST[@]}
log "Missing assets: $MISSING_COUNT (will attempt to download)"
echo ""

for rel_path in "${MISSING_LIST[@]}"; do
    local_file="$LOCAL_CDN_DIR/$rel_path"
    local_dir=$(dirname "$local_file")

    mkdir -p "$local_dir"

    SUCCESS=0
    for cdn_base in "${SOURCE_CDNS[@]}"; do
        url="${cdn_base}/img_ltr/${rel_path}"
        HTTP_CODE=$(curl -s -o "$local_file.tmp" -w "%{http_code}" \
            --connect-timeout 10 --max-time 30 \
            -H "Referer: https://legends.travian.com/" \
            -H "User-Agent: Mozilla/5.0 (compatible; TraviumAssetSync/1.0)" \
            "$url" 2>/dev/null || echo "000")

        if [[ "$HTTP_CODE" == "200" ]]; then
            mv "$local_file.tmp" "$local_file"
            ok "  [$((DOWNLOADED + 1))/$MISSING_COUNT] $rel_path"
            DOWNLOADED=$((DOWNLOADED + 1))
            SUCCESS=1
            break
        else
            rm -f "$local_file.tmp"
        fi
    done

    if [[ $SUCCESS -eq 0 ]]; then
        warn "  FAILED [$((FAILED + 1))] $rel_path"
        FAILED=$((FAILED + 1))
    fi
done

# ---- Summary ----------------------------------------------------------------
echo ""
log "============================================="
log " Download Summary"
log "============================================="
ok " Already present : $SKIPPED"
ok " Downloaded      : $DOWNLOADED"
if [[ $FAILED -gt 0 ]]; then
    warn " Failed          : $FAILED (not in live Travian CDN)"
fi
log "============================================="

# Fix ownership
SITE_USER=$(stat -c '%U' /home/travium/htdocs 2>/dev/null || echo "travium")
chown -R "$SITE_USER:$SITE_USER" "$LOCAL_CDN_DIR" 2>/dev/null || true

ok "Done! Hard-refresh your game browser to see the changes."
