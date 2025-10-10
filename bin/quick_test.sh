#!/bin/bash
#
# QUICK TEST RUNNER - IMMEDIATE VALIDATION
# Fast validation of critical components for immediate feedback
# 
# @package VapeshedTransfer
# @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
# @version 1.0.0
#

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer"

echo -e "${BLUE}⚡ QUICK TEST RUNNER - IMMEDIATE VALIDATION${NC}"
echo "==========================================="
echo ""

cd "$PROJECT_ROOT"

# Quick file existence check
echo -e "${YELLOW}📁 Quick File Structure Check${NC}"
echo "============================="

CRITICAL_FILES=(
    "transfer_engine/app/Controllers/DashboardController.php"
    "transfer_engine/app/Controllers/Api/WebhookLabController.php"
    "transfer_engine/app/Controllers/Api/VendTesterController.php"
    "transfer_engine/app/Controllers/Api/LightspeedTesterController.php"
    "transfer_engine/app/Controllers/Api/QueueJobTesterController.php"
    "transfer_engine/app/Controllers/Api/SuiteRunnerController.php"
    "transfer_engine/app/Controllers/Api/SnippetLibraryController.php"
    "transfer_engine/resources/views/admin/api-lab/main.php"
    "transfer_engine/resources/views/admin/api-lab/webhook.php"
    "transfer_engine/resources/views/admin/api-lab/vend.php"
    "transfer_engine/resources/views/admin/api-lab/lightspeed.php"
    "transfer_engine/resources/views/admin/api-lab/queue.php"
    "transfer_engine/resources/views/admin/api-lab/suite.php"
    "transfer_engine/resources/views/admin/api-lab/snippets.php"
    "routes/admin.php"
)

FILES_MISSING=0
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅ $(basename "$file")${NC}"
    else
        echo -e "${RED}❌ Missing: $file${NC}"
        ((FILES_MISSING++))
    fi
done

echo ""
echo -e "${YELLOW}🔧 Quick PHP Syntax Check${NC}"
echo "========================="

SYNTAX_ERRORS=0
for file in "${CRITICAL_FILES[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if php -l "$file" >/dev/null 2>&1; then
            echo -e "${GREEN}✅ $(basename "$file") - syntax OK${NC}"
        else
            echo -e "${RED}❌ $(basename "$file") - syntax ERROR${NC}"
            ((SYNTAX_ERRORS++))
        fi
    fi
done

echo ""
echo -e "${YELLOW}📊 Quick Line Count Check${NC}"
echo "========================="

total_lines=0
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        lines=$(wc -l < "$file")
        total_lines=$((total_lines + lines))
        echo -e "${GREEN}📄 $(basename "$file"): $lines lines${NC}"
    fi
done

echo ""
echo -e "${BLUE}📈 QUICK TEST SUMMARY${NC}"
echo "===================="
echo -e "Files checked: ${#CRITICAL_FILES[@]}"
echo -e "Missing files: ${RED}$FILES_MISSING${NC}"
echo -e "Syntax errors: ${RED}$SYNTAX_ERRORS${NC}"
echo -e "Total lines of code: ${GREEN}$total_lines${NC}"

if [ $FILES_MISSING -eq 0 ] && [ $SYNTAX_ERRORS -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 QUICK VALIDATION PASSED!${NC}"
    echo -e "${GREEN}✅ All critical files present${NC}"
    echo -e "${GREEN}✅ All PHP syntax valid${NC}"
    echo -e "${GREEN}✅ Ready for comprehensive testing${NC}"
    echo ""
    echo -e "${BLUE}Run full test suite with:${NC}"
    echo "  ./bin/master_test_runner.sh"
    exit 0
else
    echo ""
    echo -e "${RED}⚠️  QUICK VALIDATION ISSUES FOUND${NC}"
    [ $FILES_MISSING -gt 0 ] && echo -e "${RED}❌ $FILES_MISSING files missing${NC}"
    [ $SYNTAX_ERRORS -gt 0 ] && echo -e "${RED}❌ $SYNTAX_ERRORS syntax errors${NC}"
    echo -e "${YELLOW}🔧 Fix these issues before running full test suite${NC}"
    exit 1
fi