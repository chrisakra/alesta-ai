#!/bin/bash
# =============================================================================
# Alesta AI v2.0 — Script de packaging des ZIPs Free + Pro
# =============================================================================
#
# Usage :
#   ./package-zips.sh                    # build avec version dans les .php headers
#   ./package-zips.sh --version 2.0.1    # override version
#   ./package-zips.sh --upload-azure     # build + upload Azure Blob (releases container)
#
# Produit :
#   dist/alesta-ai-2.X.Y.zip       (Free, à uploader sur wordpress.org SVN)
#   dist/alesta-ai-pro-2.X.Y.zip   (Pro, à uploader sur Azure Blob pour update-checker)
#
# Pré-requis :
#   - zip   (sudo apt install zip / brew install zip / Windows: choco install zip)
#   - az CLI si --upload-azure (https://learn.microsoft.com/cli/azure/install-azure-cli)
#
# Note Windows : préférez WSL/Git Bash + zip installé. Le fallback PowerShell
# fonctionne mais Compress-Archive a des bugs avec les chemins à accents/espaces.
# Sur Linux/macOS aucun souci.
#

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# =============================================================================
# Args parsing
# =============================================================================
VERSION=""
UPLOAD_AZURE=false
while [[ $# -gt 0 ]]; do
  case $1 in
    --version) VERSION="$2"; shift 2 ;;
    --upload-azure) UPLOAD_AZURE=true; shift ;;
    -h|--help)
      echo "Usage: $0 [--version X.Y.Z] [--upload-azure]"
      exit 0
      ;;
    *) echo "Unknown arg: $1"; exit 1 ;;
  esac
done

# =============================================================================
# Detect version depuis les .php si pas overridé
# =============================================================================
if [ -z "$VERSION" ]; then
  # Fallback portable (sed marche sur Git Bash Windows + Linux + macOS)
  VERSION=$(sed -n "s/.*ALESTA_AI_PRO_VERSION', '\([^']*\)'.*/\1/p" alesta-ai-pro/alesta-ai-pro.php | head -1)
  if [ -z "$VERSION" ]; then
    echo "✗ Impossible de détecter la version (ni --version ni dans alesta-ai-pro.php)"
    exit 1
  fi
fi

echo "════════════════════════════════════════════════════════════════"
echo "  Alesta AI v2.0 — Packaging version $VERSION"
echo "════════════════════════════════════════════════════════════════"

mkdir -p dist

# =============================================================================
# Helper portable : zip avec fallback PowerShell sur Windows si zip absent
# =============================================================================
make_zip() {
  local src_dir=$1
  local out_zip=$2

  if command -v zip &> /dev/null; then
    # Linux / macOS / Git Bash avec zip installé
    (cd "$src_dir" && zip -r9 "../$out_zip" . \
      -x "*.DS_Store" "*.git*" "node_modules/*" "vendor/*" "tests/*" "*.bak" \
      > /dev/null)
  elif command -v powershell.exe &> /dev/null; then
    # Windows : fallback PowerShell Compress-Archive
    local abs_src abs_out
    abs_src=$(cd "$src_dir" && pwd -W 2>/dev/null || cd "$src_dir" && pwd)
    abs_out=$(cd "$(dirname "$out_zip")" && pwd -W 2>/dev/null || cd "$(dirname "$out_zip")" && pwd)
    abs_out="$abs_out/$(basename "$out_zip")"
    rm -f "$out_zip"
    powershell.exe -NoProfile -Command "Compress-Archive -Path '${abs_src}/*' -DestinationPath '${abs_out}' -Force" > /dev/null
  else
    echo "✗ Ni zip ni PowerShell trouvés. Installer zip : sudo apt install zip / brew install zip"
    return 1
  fi
}

# =============================================================================
# Build Free ZIP
# =============================================================================
echo ""
echo "▸ Build alesta-ai-${VERSION}.zip (Free)"
FREE_ZIP="dist/alesta-ai-${VERSION}.zip"
rm -f "$FREE_ZIP"
make_zip "alesta-ai" "$FREE_ZIP"
FREE_SIZE=$(du -h "$FREE_ZIP" | cut -f1)
echo "  ✓ $FREE_ZIP ($FREE_SIZE)"

# =============================================================================
# Build Pro ZIP
# =============================================================================
echo ""
echo "▸ Build alesta-ai-pro-${VERSION}.zip (Pro)"
PRO_ZIP="dist/alesta-ai-pro-${VERSION}.zip"
rm -f "$PRO_ZIP"
make_zip "alesta-ai-pro" "$PRO_ZIP"
PRO_SIZE=$(du -h "$PRO_ZIP" | cut -f1)
echo "  ✓ $PRO_ZIP ($PRO_SIZE)"

# =============================================================================
# Upload Azure Blob (optionnel)
# =============================================================================
if [ "$UPLOAD_AZURE" = true ]; then
  echo ""
  echo "▸ Upload Azure Blob (container: alesta-releases)"

  if ! command -v az &> /dev/null; then
    echo "  ✗ az CLI non installé. Installer : https://learn.microsoft.com/cli/azure/install-azure-cli"
    exit 1
  fi

  : "${AZURE_STORAGE_ACCOUNT:?AZURE_STORAGE_ACCOUNT env var required}"
  : "${AZURE_STORAGE_KEY:?AZURE_STORAGE_KEY env var required}"

  # Upload Pro ZIP sous /alesta-ai-pro/X.Y.Z.zip
  az storage blob upload \
    --account-name "$AZURE_STORAGE_ACCOUNT" \
    --account-key "$AZURE_STORAGE_KEY" \
    --container-name alesta-releases \
    --name "alesta-ai-pro/${VERSION}.zip" \
    --file "$PRO_ZIP" \
    --overwrite \
    --output none
  echo "  ✓ Uploaded: alesta-releases/alesta-ai-pro/${VERSION}.zip"

  # Upload + alias "latest" pour pointer toujours sur la dernière
  az storage blob upload \
    --account-name "$AZURE_STORAGE_ACCOUNT" \
    --account-key "$AZURE_STORAGE_KEY" \
    --container-name alesta-releases \
    --name "alesta-ai-pro/latest.zip" \
    --file "$PRO_ZIP" \
    --overwrite \
    --output none
  echo "  ✓ Uploaded: alesta-releases/alesta-ai-pro/latest.zip"

  echo ""
  echo "  → Test download URL : https://${AZURE_STORAGE_ACCOUNT}.blob.core.windows.net/alesta-releases/alesta-ai-pro/${VERSION}.zip"
fi

# =============================================================================
# Récap final
# =============================================================================
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  ✅ Packaging terminé"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Fichiers produits dans dist/ :"
ls -lh dist/ | tail -n +2
echo ""
echo "Prochaines étapes :"
echo "  1. Free → upload sur wordpress.org SVN (svn ci avec tag v$VERSION)"
echo "  2. Pro  → upload sur Azure Blob (--upload-azure ci-dessus si pas fait)"
echo "  3. Update app/api/alesta-ai-pro/latest/route.ts avec LATEST_MANIFEST.version = '$VERSION'"
echo "  4. Push Galiance Cockpit → les clients verront l'update dans les 12h"
echo ""
