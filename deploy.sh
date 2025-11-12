#!/bin/bash

# ===============================================================
# 🚀 SyncFlow API - Automated Deployment Script (Full Auto Mode)
# ===============================================================
# Description:
# - Pull latest code from Git
# - Automatically install Laravel dependencies
# - Automatically install nxp/math-executor
# - Run Laravel optimization commands
# - Restart Docker container
# - Health check
# ===============================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SERVER_IP="103.236.140.19"
SERVER_USER="root"
SERVER_PATH="/root/SyncFlow"
CONTAINER_NAME="syncflow-api"

# SSH Configuration - Reuse connection to avoid multiple password prompts
SSH_CONTROL_PATH="/tmp/ssh-syncflow-deploy-%r@%h:%p"
SSH_OPTIONS="-o ControlMaster=auto -o ControlPath=$SSH_CONTROL_PATH -o ControlPersist=10m -o StrictHostKeyChecking=no"

echo -e "${BLUE}🚀 SyncFlow API - Automated Deployment${NC}"
echo "======================================"
echo -e "${BLUE}📋 Deployment Configuration:${NC}"
echo "Server: $SERVER_IP"
echo "Path: $SERVER_PATH"
echo "Container: $CONTAINER_NAME"
echo ""

# Function to run command on server (with connection reuse)
run_on_server() {
    ssh $SSH_OPTIONS $SERVER_USER@$SERVER_IP "$1"
}

# Function to cleanup SSH control connection
cleanup_ssh() {
    ssh -O exit $SSH_OPTIONS $SERVER_USER@$SERVER_IP 2>/dev/null || true
}

# Trap to cleanup on exit
trap cleanup_ssh EXIT

# Function to check if container is running
check_container() {
    if run_on_server "docker ps | grep -q $CONTAINER_NAME"; then
        echo -e "${GREEN}✅ Container $CONTAINER_NAME is running${NC}"
        return 0
    else
        echo -e "${RED}❌ Container $CONTAINER_NAME is not running${NC}"
        return 1
    fi
}

# Step 0: Check if SSH key is setup
echo -e "${YELLOW}🔑 Checking SSH authentication...${NC}"
if ! ssh-add -l &>/dev/null; then
    echo -e "${YELLOW}⚠️  SSH agent not running or no keys loaded${NC}"
    echo -e "${BLUE}💡 Tip: Setup SSH key for passwordless login:${NC}"
    echo "  1. Generate key: ssh-keygen -t rsa -b 4096"
    echo "  2. Copy to server: ssh-copy-id $SERVER_USER@$SERVER_IP"
    echo ""
fi

# Step 1: Check server connection
echo -e "${YELLOW}🔍 Establishing connection to server...${NC}"
if ! ssh $SSH_OPTIONS -o ConnectTimeout=10 $SERVER_USER@$SERVER_IP "echo 'Server connection OK'"; then
    echo -e "${RED}❌ Cannot connect to server $SERVER_IP${NC}"
    echo -e "${YELLOW}💡 Setup SSH key to avoid password prompts:${NC}"
    echo "   ssh-copy-id $SERVER_USER@$SERVER_IP"
    exit 1
fi
echo -e "${GREEN}✅ Server connection established (password only needed once)${NC}"

# Step 2: Check Docker container
echo -e "${YELLOW}🔍 Checking Docker container...${NC}"
if ! check_container; then
    echo -e "${YELLOW}⚠️  Container not running. Starting container...${NC}"
    run_on_server "cd $SERVER_PATH && docker-compose up -d"
    sleep 10
    if ! check_container; then
        echo -e "${RED}❌ Failed to start container${NC}"
        exit 1
    fi
fi

# Step 3: Pull latest code
echo -e "${YELLOW}📥 Pulling latest code from Git...${NC}"
run_on_server "cd $SERVER_PATH && git pull origin main"

# Step 4: Install/Update dependencies
echo -e "${YELLOW}📦 Installing/Updating Composer dependencies...${NC}"
run_on_server "cd $SERVER_PATH && docker exec $CONTAINER_NAME composer install --no-dev --optimize-autoloader"

# Step 5: Ensure nxp/math-executor is installed
echo -e "${YELLOW}➕ Ensuring nxp/math-executor is installed...${NC}"
run_on_server "cd $SERVER_PATH && docker exec $CONTAINER_NAME composer require nxp/math-executor --no-interaction --no-progress || true"

# Step 6: Clear composer cache & autoload
echo -e "${YELLOW}🧹 Regenerating Composer autoload...${NC}"
run_on_server "docker exec $CONTAINER_NAME composer dump-autoload --optimize"

# Step 7: Run Laravel maintenance commands
echo -e "${YELLOW}⚙️  Running Laravel optimization commands...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan config:clear"
run_on_server "docker exec $CONTAINER_NAME php artisan cache:clear"
run_on_server "docker exec $CONTAINER_NAME php artisan route:clear"
run_on_server "docker exec $CONTAINER_NAME php artisan package:discover --ansi"

# Step 8: Run migrations & seeders
echo -e "${YELLOW}🗃️  Running database migrations...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan migrate --force"

echo -e "${YELLOW}🌱 Running database seeders...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan db:seed --class=SuperAdminSeeder --force"

# Step 9: Optimize Laravel for production
echo -e "${YELLOW}🚀 Optimizing for production...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan config:cache"
run_on_server "docker exec $CONTAINER_NAME php artisan route:cache"

# Step 10: Fix permissions
echo -e "${YELLOW}🧰 Fixing permissions...${NC}"
run_on_server "docker exec $CONTAINER_NAME chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache"

# Step 11: Restart container
echo -e "${YELLOW}🔄 Restarting container...${NC}"
run_on_server "docker restart $CONTAINER_NAME"

# Step 12: Health check
echo -e "${YELLOW}🏥 Performing health check...${NC}"
sleep 5
if curl -f -s "http://$SERVER_IP:2020/api/v1/login" > /dev/null; then
    echo -e "${GREEN}✅ API is responding correctly${NC}"
else
    echo -e "${YELLOW}⚠️  API health check failed, but deployment completed${NC}"
fi

# Step 13: Cleanup SSH connection
echo -e "${YELLOW}🧹 Cleaning up SSH connection...${NC}"
cleanup_ssh

# Step 14: Summary
echo ""
echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
echo -e "${BLUE}📊 Deployment Summary:${NC}"
echo "• Server: $SERVER_IP"
echo "• API URL: http://$SERVER_IP:2020/api/v1"
echo "• phpMyAdmin: http://$SERVER_IP:8081"
echo "• Container: $CONTAINER_NAME"
echo ""
echo -e "${YELLOW}💡 Next steps:${NC}"
echo "• Test API endpoints with Postman"
echo "• Check logs: docker logs $CONTAINER_NAME"
echo "• Monitor container: docker ps"
echo ""
echo -e "${YELLOW}🔐 To avoid password prompt next time:${NC}"
echo "   Run: ssh-copy-id $SERVER_USER@$SERVER_IP"
echo ""
echo -e "${GREEN}✨ Your team can now test the updated API!${NC}"
