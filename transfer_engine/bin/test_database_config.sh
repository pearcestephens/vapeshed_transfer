#!/bin/bash
#################################################################################
# QUICK DATABASE CONNECTION TEST
#################################################################################
# Tests if the database is configured and accessible for testing
#################################################################################

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  DATABASE CONNECTION TEST                                 ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Extract database config from phpunit.xml
DB_HOST=$(grep 'DB_HOST' phpunit.xml | sed 's/.*value="\([^"]*\)".*/\1/')
DB_NAME=$(grep 'DB_NAME' phpunit.xml | sed 's/.*value="\([^"]*\)".*/\1/')
DB_USER=$(grep 'DB_USER' phpunit.xml | sed 's/.*value="\([^"]*\)".*/\1/')
DB_PASS=$(grep 'DB_PASSWORD' phpunit.xml | sed 's/.*value="\([^"]*\)".*/\1/')

echo -e "${YELLOW}Configuration from phpunit.xml:${NC}"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Test with PHP
echo -e "${BLUE}Testing database connection...${NC}"

php -r "
\$host = '$DB_HOST';
\$db = '$DB_NAME';
\$user = '$DB_USER';
\$pass = '$DB_PASS';

try {
    \$conn = new PDO(\"mysql:host=\$host;dbname=\$db\", \$user, \$pass);
    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo \"✓ Connection successful!\n\n\";
    
    // Test vend_outlets table
    \$stmt = \$conn->query('SELECT COUNT(*) FROM vend_outlets');
    \$count = \$stmt->fetchColumn();
    echo \"✓ vend_outlets table: \$count outlets found\n\";
    
    // Test vend_products table
    \$stmt = \$conn->query('SELECT COUNT(*) FROM vend_products');
    \$count = \$stmt->fetchColumn();
    echo \"✓ vend_products table: \$count products found\n\";
    
    echo \"\n\";
    echo \"Database is ready for testing! ✅\n\";
    exit(0);
    
} catch (PDOException \$e) {
    echo \"✗ Connection failed: \" . \$e->getMessage() . \"\n\";
    echo \"\n\";
    echo \"Please check:\n\";
    echo \"  1. Database exists: \$db\n\";
    echo \"  2. User has access: \$user\n\";
    echo \"  3. Host is reachable: \$host\n\";
    exit(1);
}
"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Database is configured and ready for advanced testing!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Run: bash bin/run_advanced_tests.sh"
echo "  2. Or run specific suite: vendor/bin/phpunit --testsuite=Integration --verbose"
echo ""
