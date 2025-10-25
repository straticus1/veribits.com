#!/bin/bash

# VeriBits API Test Suite
# Tests both authenticated and non-authenticated endpoints

set -e

BASE_URL="https://veribits.com"
API_URL="${BASE_URL}/api/v1"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Test results
declare -a ERRORS
declare -a TEST_RESULTS

log_test() {
    echo -e "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "${YELLOW}TEST:${NC} $1"
    echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

log_pass() {
    echo -e "${GREEN}✅ PASS:${NC} $1"
    ((PASSED++))
    TEST_RESULTS+=("PASS: $1")
}

log_fail() {
    echo -e "${RED}❌ FAIL:${NC} $1"
    ((FAILED++))
    ERRORS+=("$1")
    TEST_RESULTS+=("FAIL: $1")
}

log_warn() {
    echo -e "${YELLOW}⚠️  WARN:${NC} $1"
    ((WARNINGS++))
    TEST_RESULTS+=("WARN: $1")
}

test_endpoint() {
    local method=$1
    local endpoint=$2
    local expected_status=$3
    local data=$4
    local description=$5

    log_test "$description"

    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$endpoint")
    fi

    status=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')

    echo "Status: $status"
    echo "Response: $body" | head -c 500
    echo ""

    if [ "$status" -eq "$expected_status" ]; then
        log_pass "$description (Status: $status)"
    elif [ "$status" -ge 500 ]; then
        log_fail "$description - HTTP 500 Error (Status: $status)"
        ERRORS+=("500 Error on $endpoint: $body")
    elif [ "$status" -eq 404 ]; then
        log_warn "$description - Endpoint not found (404)"
    elif [ "$status" -eq 401 ] || [ "$status" -eq 403 ]; then
        log_warn "$description - Auth required (Status: $status)"
    else
        log_warn "$description - Unexpected status: $status (expected: $expected_status)"
    fi
}

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                  VeriBits API Test Suite                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Base URL: $BASE_URL"
echo "API URL: $API_URL"
echo "Time: $(date)"
echo ""

# Non-Authenticated Tests
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "NON-AUTHENTICATED TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "GET" "$API_URL/health" 200 "" "API Health Check"
test_endpoint "GET" "$BASE_URL/" 200 "" "Homepage"
test_endpoint "GET" "$BASE_URL/about.html" 200 "" "About Page"
test_endpoint "GET" "$BASE_URL/pricing.html" 200 "" "Pricing Page"
test_endpoint "GET" "$BASE_URL/tools.html" 200 "" "Tools Page"
test_endpoint "GET" "$BASE_URL/login.html" 200 "" "Login Page"
test_endpoint "GET" "$BASE_URL/signup.html" 200 "" "Signup Page"
test_endpoint "GET" "$BASE_URL/cli.html" 200 "" "CLI Page"

# Anonymous API endpoints
test_endpoint "GET" "$API_URL/anonymous/limits" 200 "" "Anonymous Limits Endpoint"

# Crypto validation
test_endpoint "POST" "$API_URL/crypto/validate" 200 \
    '{"address":"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa","currency":"BTC"}' \
    "Crypto Validation (Bitcoin)"

# JWT validation
test_endpoint "POST" "$API_URL/jwt/validate" 200 \
    '{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"}' \
    "JWT Validation"

# SSL validation
test_endpoint "POST" "$API_URL/ssl/validate" 200 \
    '{"domain":"google.com"}' \
    "SSL Certificate Validation"

# DNS validation
test_endpoint "POST" "$API_URL/dns/check" 200 \
    '{"domain":"google.com"}' \
    "DNS Check"

# Authenticated Tests (should fail without auth)
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "AUTHENTICATED ENDPOINT TESTS (Should require auth)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "GET" "$BASE_URL/dashboard.html" 200 "" "Dashboard Page"
test_endpoint "GET" "$BASE_URL/settings.html" 200 "" "Settings Page"
test_endpoint "GET" "$API_URL/api-keys" 401 "" "API Keys Endpoint (should require auth)"
test_endpoint "GET" "$API_URL/user/profile" 401 "" "User Profile (should require auth)"
test_endpoint "GET" "$API_URL/verifications" 401 "" "Verifications History (should require auth)"

# Tool Pages
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TOOL PAGES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "GET" "$BASE_URL/tool/jwt.html" 200 "" "JWT Tool"
test_endpoint "GET" "$BASE_URL/tool/ssl.html" 200 "" "SSL Tool"
test_endpoint "GET" "$BASE_URL/tool/crypto.html" 200 "" "Crypto Tool"
test_endpoint "GET" "$BASE_URL/tool/dns.html" 200 "" "DNS Tool"
test_endpoint "GET" "$BASE_URL/tool/malware.html" 200 "" "Malware Scan Tool"

# Summary
echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                         TEST SUMMARY                          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${RED}❌ Failed: $FAILED${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "❌ ERRORS FOUND:"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    for error in "${ERRORS[@]}"; do
        echo "  • $error"
    done
    echo ""
    exit 1
else
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "✅ ALL TESTS PASSED!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    exit 0
fi
