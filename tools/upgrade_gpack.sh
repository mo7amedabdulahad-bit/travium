#!/usr/bin/env bash
# =============================================================================
#  Travium Gpack Upgrade Tool
#  Downloads CSS + ALL image assets from the live Travian CDN (Gpack 347.6)
#  and configures the game to use the new version for correct positioning
#  and complete asset coverage.
#
#  Usage: sudo bash tools/upgrade_gpack.sh
#  Options:
#    --source-version  347.6         (default: auto-detect from live CDN)
#    --local-cdn       /path/to/cdn  (default: /home/travium/htdocs/integrations/cdn)
#    --jobs            8             (parallel download jobs)
#    --dry-run                       (show what would be downloaded, no changes)
# =============================================================================
set -euo pipefail

# ─── Defaults ────────────────────────────────────────────────────────────────
SOURCE_VERSION="${SOURCE_VERSION:-347.6}"
LOCAL_CDN_ROOT="${LOCAL_CDN_ROOT:-/home/travium/htdocs/integrations/cdn}"
HTDOCS="${HTDOCS:-/home/travium/htdocs}"
PARALLEL_JOBS="${PARALLEL_JOBS:-8}"
DRY_RUN=0

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-version) SOURCE_VERSION="$2"; shift 2 ;;
    --local-cdn)      LOCAL_CDN_ROOT="$2"; shift 2 ;;
    --jobs)           PARALLEL_JOBS="$2"; shift 2 ;;
    --dry-run)        DRY_RUN=1; shift ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ─── Constants ───────────────────────────────────────────────────────────────
CDN_BASE="https://cdn.legends.travian.com/gpack"
SOURCE_BASE="${CDN_BASE}/${SOURCE_VERSION}"
DEST_DIR="${LOCAL_CDN_ROOT}/${SOURCE_VERSION}"
SITE_USER=$(stat -c '%U' "$HTDOCS" 2>/dev/null || echo "travium")
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
REF="https://legends.travian.com/"
CURL_OPTS=(-fsSL --connect-timeout 10 --max-time 30 -H "Referer: $REF" -A "$UA")

# ─── Colors ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${BLUE}[INFO]${NC}  $*"; }
ok()   { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()  { echo -e "${RED}[ERR ]${NC}  $*" >&2; }
step() { echo -e "\n${CYAN}══ $* ${NC}"; }

# ─── Helper: download one file ─────────────────────────────────────────────
dl_file() {
  local src="$1" dest="$2"
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "[DRY] $src"
    return 0
  fi
  mkdir -p "$(dirname "$dest")"
  local code
  code=$(curl "${CURL_OPTS[@]}" -o "$dest" -w "%{http_code}" "$src" 2>/dev/null || echo "000")
  if [[ "$code" == "200" ]]; then
    echo "200 $dest"
    return 0
  else
    rm -f "$dest"
    echo "$code FAIL:$src"
    return 1
  fi
}
export -f dl_file
export CURL_OPTS DRY_RUN

# ─── Step 0: Test CDN reachability ───────────────────────────────────────────
step "Testing live Travian CDN"
TEST_URL="${SOURCE_BASE}/img_ltr/hud/sidebar/sidebarBox.png"
HTTP=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 8 -H "Referer: $REF" "$TEST_URL" 2>/dev/null || echo "000")
if [[ "$HTTP" != "200" ]]; then
  err "CDN not reachable (HTTP $HTTP): $SOURCE_BASE"
  err "Check internet, then retry: sudo bash tools/upgrade_gpack.sh"
  exit 1
fi
ok "CDN reachable: $SOURCE_BASE"

# ─── Step 1: Download CSS files ──────────────────────────────────────────────
step "Downloading CSS files (Gpack $SOURCE_VERSION)"

CSS_FILES=(
  "css_ltr/imports_compressed.css"
  "css_ltr/compact.css"
  "css_ltr/lang.css"
  "css_ltr/fixes.css"
  "css_ltr/compact-lowres.css"
  "css_rtl/imports_compressed.css"
  "css_rtl/compact.css"
)

CSS_DOWNLOADED=0
CSS_FAILED=0
for css_rel in "${CSS_FILES[@]}"; do
  dest="$DEST_DIR/$css_rel"
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "[DRY] CSS: $css_rel"
    continue
  fi
  mkdir -p "$(dirname "$dest")"
  code=$(curl "${CURL_OPTS[@]}" -o "$dest" -w "%{http_code}" "${SOURCE_BASE}/${css_rel}" 2>/dev/null || echo "000")
  if [[ "$code" == "200" ]]; then
    ok "$css_rel"
    CSS_DOWNLOADED=$((CSS_DOWNLOADED+1))
  else
    warn "SKIP ($code): $css_rel"
    CSS_FAILED=$((CSS_FAILED+1))
  fi
done

# ─── Step 2: Parse CSS for all image references ───────────────────────────────
step "Parsing CSS for image references"

WORK_DIR=$(mktemp -d)
trap "rm -rf '$WORK_DIR'" EXIT

IMG_PATHS_FILE="$WORK_DIR/all_img_paths.txt"
> "$IMG_PATHS_FILE"

for css_file in "$DEST_DIR"/css_ltr/*.css "$DEST_DIR"/css_rtl/*.css; do
  [[ -f "$css_file" ]] || continue
  # Extract url(...) patterns pointing to ../img_ltr/
  grep -oP "url\(['\"]?\.\./img_ltr/[^'\"\) ]+['\"]?\)" "$css_file" \
    | sed "s|url(['\"]\\?\\.\\.\/img_ltr\/||;s|['\"]*)$||" \
    >> "$IMG_PATHS_FILE" 2>/dev/null || true
done

sort -u "$IMG_PATHS_FILE" -o "$IMG_PATHS_FILE"
TOTAL_IMGS=$(wc -l < "$IMG_PATHS_FILE")
log "Found $TOTAL_IMGS unique image references across all CSS files"

# ─── Step 3: Find missing images ─────────────────────────────────────────────
step "Checking which images are already present"

MISSING_FILE="$WORK_DIR/missing.txt"
PRESENT=0
> "$MISSING_FILE"

while IFS= read -r rel; do
  [[ -z "$rel" ]] && continue
  if [[ -f "$DEST_DIR/img_ltr/$rel" ]]; then
    PRESENT=$((PRESENT+1))
  else
    echo "$rel" >> "$MISSING_FILE"
  fi
done < "$IMG_PATHS_FILE"

MISSING_COUNT=$(wc -l < "$MISSING_FILE")
log "Present : $PRESENT / $TOTAL_IMGS"
log "Missing : $MISSING_COUNT (will download)"

if [[ $MISSING_COUNT -eq 0 ]]; then
  ok "All images already present!"
else
  # ─── Step 4: Build download commands ─────────────────────────────────────
  step "Downloading $MISSING_COUNT images ($PARALLEL_JOBS parallel jobs)"

  CMDS_FILE="$WORK_DIR/cmds.txt"
  > "$CMDS_FILE"

  while IFS= read -r rel; do
    [[ -z "$rel" ]] && continue
    src="${SOURCE_BASE}/img_ltr/${rel}"
    dest="${DEST_DIR}/img_ltr/${rel}"
    printf '%s\t%s\n' "$src" "$dest" >> "$CMDS_FILE"
  done < "$MISSING_FILE"

  # Parallel download using xargs
  RESULTS_FILE="$WORK_DIR/results.txt"
  > "$RESULTS_FILE"

  cat "$CMDS_FILE" | xargs -P "$PARALLEL_JOBS" -L 1 bash -c '
    src="$1"; dest="$2"
    mkdir -p "$(dirname "$dest")"
    code=$(curl -fsSL --connect-timeout 10 --max-time 30 \
      -H "Referer: https://legends.travian.com/" \
      -A "Mozilla/5.0" \
      -o "$dest" -w "%{http_code}" "$src" 2>/dev/null || echo "000")
    if [[ "$code" == "200" ]]; then
      echo "OK $src"
    else
      rm -f "$dest"
      echo "FAIL:$code $src"
    fi
  ' -- >> "$RESULTS_FILE" 2>&1 || true

  DL_OK=$(grep -c "^OK" "$RESULTS_FILE" 2>/dev/null || echo 0)
  DL_FAIL=$(grep -c "^FAIL" "$RESULTS_FILE" 2>/dev/null || echo 0)

  ok "Downloaded : $DL_OK"
  [[ $DL_FAIL -gt 0 ]] && warn "Failed     : $DL_FAIL (not on CDN)"
fi

# ─── Step 5: Update game config to use new Gpack version ─────────────────────
step "Patching game config to use Gpack $SOURCE_VERSION"

CONFIG_FILE="$HTDOCS/src/config.php"

if [[ $DRY_RUN -eq 1 ]]; then
  log "[DRY] Would patch $CONFIG_FILE to set gpack default to $SOURCE_VERSION"
else
  if [[ ! -f "$CONFIG_FILE" ]]; then
    warn "config.php not found at $CONFIG_FILE — skipping config patch"
  else
    # Use PHP to safely patch the config file
    php -r "
      \$file = '$CONFIG_FILE';
      \$content = file_get_contents(\$file);
      \$version = '$SOURCE_VERSION';

      // 1. Add the new gpack version to the list if missing
      \$entry = \"'{\$version}' => ['hash' => '{\$version}', 'name' => 'Travian v{\$version} (SVG)', 'isNew' => true]\";
      if (strpos(\$content, \"'{\$version}'\") === false) {
          // Insert before 'TravianOld' entry or before closing of gpack list
          \$anchor = \"'TravianOld'\";
          if (strpos(\$content, \$anchor) !== false) {
              \$content = str_replace(\$anchor, \$entry . \",\n        \" . \$anchor, \$content);
          } else {
              // Fallback: append before closing bracket of gpacks list
              \$content = preg_replace(
                  \"/('326\.6' => \[.*?\])\s*\n(\s*\])/s\",
                  \"\\\$1,\n        \$entry\n\$2\",
                  \$content
              );
          }
          echo \"Added gpack {\$version} to list.\n\";
      } else {
          echo \"Gpack {\$version} already in list.\n\";
      }

      // 2. Update the default gpack version
      \$content = preg_replace(
          \"/('default'\s*=>\s*)'[^']+'/\",
          \"\\\${1}'{\$version}'\",
          \$content
      );
      echo \"Set default gpack to {\$version}.\n\";

      file_put_contents(\$file, \$content);
      echo \"Config patched: {\$file}\n\";
    " 2>&1 && ok "config.php patched" || warn "PHP config patch failed — check manually"
  fi
fi


# ─── Step 6: Fix symlinks and permissions ─────────────────────────────────────
step "Fixing permissions and CDN symlinks"
if [[ $DRY_RUN -eq 0 ]]; then
  # Ensure integrations symlink still points correctly
  for server_public in "$HTDOCS"/servers/*/public; do
    [[ -d "$server_public" ]] || continue
    ln -sfn "$HTDOCS/integrations" "$server_public/integrations" 2>/dev/null || true
    chown -h "$SITE_USER:$SITE_USER" "$server_public/integrations" 2>/dev/null || true
  done

  chown -R "$SITE_USER:$SITE_USER" "$DEST_DIR" 2>/dev/null || true
  ok "Permissions fixed for $SITE_USER"
fi

# ─── Summary ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Gpack $SOURCE_VERSION upgrade complete!${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
log "CSS files downloaded : $CSS_DOWNLOADED"
log "Images already had   : $PRESENT"
log "Images downloaded    : ${DL_OK:-0}"
[[ "${DL_FAIL:-0}" -gt 0 ]] && warn "Images not on CDN    : ${DL_FAIL}" || true
echo ""
ok "Gpack version: $SOURCE_VERSION"
ok "Assets stored: $DEST_DIR"
ok "Hard-refresh your browser (Ctrl+F5) to see all changes."
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
