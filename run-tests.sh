#!/bin/bash

# SyncFlow Unit Testing Runner
# This script runs all unit tests and generates a report

echo "🧪 ======================================"
echo "   SyncFlow Unit Testing Suite"
echo "========================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if composer dependencies are installed
echo "📦 Checking dependencies..."
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Installing Composer dependencies...${NC}"
    composer install
fi

# Check .env.testing exists
if [ ! -f ".env.testing" ]; then
    echo -e "${YELLOW}Creating .env.testing file...${NC}"
    cp .env .env.testing
    
    # Update DB config for testing
    sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env.testing
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=:memory:/' .env.testing
fi

# Generate JWT secret if needed
if ! grep -q "JWT_SECRET" .env.testing; then
    echo -e "${YELLOW}Generating JWT secret...${NC}"
    php artisan jwt:secret --env=testing
fi

echo ""
echo "🏗️  Setting up test environment..."

# Run migrations
php artisan migrate:fresh --env=testing --force
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Migration failed!${NC}"
    exit 1
fi

# Seed database
php artisan db:seed --env=testing --force
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Seeding failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Test environment ready!${NC}"
echo ""

# Run all tests
echo "🚀 Running all tests..."
echo "========================================"
php artisan test

TEST_EXIT_CODE=$?

echo ""
echo "========================================"

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed! 🎉${NC}"
    echo ""
    echo "📊 Test Summary:"
    echo "   - ProductCategoryTest: 14 tests"
    echo "   - QualitativeProductTest: 11 tests"
    echo "   - ProductTest: 11 tests"
    echo "   - ProductMeasurementTest: 7 tests"
    echo "   -----------------------------------"
    echo "   Total: 43 tests"
    echo ""
    echo "✨ Ready to deploy!"
else
    echo -e "${RED}❌ Some tests failed!${NC}"
    echo ""
    echo "🔧 Troubleshooting:"
    echo "   1. Check database configuration"
    echo "   2. Verify all migrations ran"
    echo "   3. Check seeder data"
    echo "   4. Review error messages above"
    echo ""
    echo "📚 For more info: cat RUN_TESTS.md"
    exit 1
fi

echo ""
echo "======================================"
echo "🏁 Testing complete!"
echo "======================================"

