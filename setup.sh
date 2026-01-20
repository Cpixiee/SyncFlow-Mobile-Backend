#!/bin/bash

# ===============================================================
# 🚀 SyncFlow API - Local Setup Script (Laragon/Local)
# ===============================================================
# Runs the core commands from deploy.sh, but:
# - NO SSH
# - NO Docker
# - Uses your local .env (Laragon)
# ===============================================================

set -euo pipefail

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🚀 SyncFlow API - Local Setup${NC}"
echo "======================================"

# Ensure we are in project root (directory of this script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [ ! -f artisan ]; then
  echo -e "${RED}❌ artisan file not found. Run this script from the Laravel project root.${NC}"
  exit 1
fi

if [ ! -f .env ]; then
  echo -e "${YELLOW}⚠️  .env not found. Create it (e.g. copy .env.example) and configure DB before continuing.${NC}"
  echo -e "${BLUE}   Tip:${NC} cp .env.example .env"
  exit 1
fi

command -v php >/dev/null 2>&1 || { echo -e "${RED}❌ php not found in PATH${NC}"; exit 1; }
command -v composer >/dev/null 2>&1 || { echo -e "${RED}❌ composer not found in PATH${NC}"; exit 1; }

echo -e "${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --no-interaction

echo -e "${YELLOW}➕ Ensuring required packages are installed...${NC}"
composer require nxp/math-executor --no-interaction --no-progress || true
composer require phpoffice/phpspreadsheet --no-interaction --no-progress || true
composer require dompdf/dompdf --no-interaction --no-progress || true
composer require barryvdh/laravel-dompdf --no-interaction --no-progress || true

echo -e "${YELLOW}🧹 Regenerating Composer autoload...${NC}"
composer dump-autoload --optimize

echo -e "${YELLOW}⚙️  Clearing Laravel caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo -e "${YELLOW}🔎 Discovering packages...${NC}"
php artisan package:discover --ansi

echo -e "${YELLOW}🗃️  Running migrations...${NC}"
php artisan migrate --force

echo -e "${YELLOW}🌱 Running seeders...${NC}"
php artisan db:seed --class=QuarterSeeder --force
php artisan db:seed --class=ProductCategorySeeder --force
php artisan db:seed --class=MeasurementInstrumentSeeder --force
php artisan db:seed --class=ToolSeeder --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan db:seed --class=LoginUserSeeder --force

echo -e "${YELLOW}📅 Activating default quarter (Q4 2024)...${NC}"
php artisan tinker --execute="\$quarter = App\\Models\\Quarter::where('year', 2024)->where('name', 'Q4')->first(); if(\$quarter) { \$quarter->setAsActive(); echo 'Q4 2024 activated'; } else { echo 'Quarter not found'; }"

echo -e "${YELLOW}🔗 Creating storage link...${NC}"
php artisan storage:link || true

echo -e "${YELLOW}📁 Creating report storage directories...${NC}"
php artisan tinker --execute="if(!is_dir(storage_path('app/private/reports/master_files'))) { mkdir(storage_path('app/private/reports/master_files'), 0775, true); } if(!is_dir(storage_path('app/temp'))) { mkdir(storage_path('app/temp'), 0775, true); } echo 'Directories ensured';"

# Permissions are only relevant on Linux/macOS. Skip on Windows.
UNAME_OUT="$(uname -s 2>/dev/null || echo '')"
if [[ "$UNAME_OUT" == "Linux" || "$UNAME_OUT" == "Darwin" ]]; then
  echo -e "${YELLOW}🧰 Fixing permissions (Linux/macOS only)...${NC}"
  chmod -R 775 storage bootstrap/cache || true
fi

echo -e "${GREEN}✅ Local setup completed successfully!${NC}"
echo -e "${BLUE}Next:${NC} run your server (Laragon) and test the API."

