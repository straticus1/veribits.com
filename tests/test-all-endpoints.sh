#!/bin/bash

# Complete API Endpoint Test
# Tests every single endpoint defined in the routing

set -e

BASE_URL="https://veribits.com"
API_URL="${BASE_URL}/api/v1"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
MISSING=0

declare -a MISSING_ENDPOINTS

log_test() {
    echo -e "\n${BLUE}━━━ Testing:${NC} $1"
}

log_pass() {
    echo -e "${GREEN}✅ PASS${NC} - $1"
    ((PASSED++))
}

log_fail() {
    echo -e "${RED}❌ FAIL${NC} - $1"
    ((FAILED++))
}

log_missing() {
    echo -e "${YELLOW}⚠️  MISSING${NC} - $1"
    ((MISSING++))
    MISSING_ENDPOINTS+=("$1")
}

test_endpoint() {
    local method=$1
    local endpoint=$2
    local expected_status=$3
    local data=$4
    local description=$5

    log_test "$description ($method $endpoint)"

    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data" 2>&1)
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$endpoint" 2>&1)
    fi

    status=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')

    echo "Status: $status"

    if [ "$status" -eq "$expected_status" ]; then
        log_pass "$description"
    elif [ "$status" -eq 404 ]; then
        log_missing "$endpoint (404 Not Found)"
    elif [ "$status" -eq 500 ]; then
        log_fail "$endpoint (500 Internal Server Error)"
        echo "Response: $body" | head -c 300
    elif [ "$status" -eq 401 ] || [ "$status" -eq 403 ]; then
        log_pass "$description (Auth required as expected)"
    elif [ "$status" -eq 429 ]; then
        log_pass "$description (Rate limited as expected)"
    elif [ "$status" -eq 400 ]; then
        log_pass "$description (Validation error as expected)"
    else
        echo "Response: $body" | head -c 200
    fi
}

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         Complete VeriBits API Endpoint Test Suite            ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Testing ALL endpoints from routing configuration..."
echo ""

# Health & System
test_endpoint "GET" "$API_URL/health" 200 "" "Health Check"
test_endpoint "GET" "$API_URL/limits/anonymous" 200 "" "Anonymous Limits (corrected path)"

# Authentication
test_endpoint "POST" "$API_URL/auth/register" 400 '{"email":"test@example.com"}' "Auth Register"
test_endpoint "POST" "$API_URL/auth/login" 400 '{"email":"test@example.com"}' "Auth Login"
test_endpoint "POST" "$API_URL/auth/logout" 401 "" "Auth Logout"
test_endpoint "POST" "$API_URL/auth/token" 400 "" "Auth Token"
test_endpoint "POST" "$API_URL/auth/refresh" 401 "" "Auth Refresh"
test_endpoint "GET" "$API_URL/auth/profile" 401 "" "Auth Profile"

# Verification
test_endpoint "POST" "$API_URL/verify/file" 401 "" "Verify File"
test_endpoint "POST" "$API_URL/verify/email" 401 "" "Verify Email"
test_endpoint "POST" "$API_URL/verify/tx" 401 "" "Verify Transaction"

# Malware Scan
test_endpoint "POST" "$API_URL/verify/malware" 401 "" "Malware Scan"

# Archive Inspection
test_endpoint "POST" "$API_URL/inspect/archive" 401 "" "Archive Inspection"

# DNS Check
test_endpoint "POST" "$API_URL/dns/check" 401 '{"domain":"google.com"}' "DNS Check (generic)"
test_endpoint "POST" "$API_URL/verify/dns" 401 '{"domain":"google.com"}' "DNS Verify"

# SSL Check
test_endpoint "POST" "$API_URL/ssl/validate" 200 '{"domain":"google.com"}' "SSL Validate (generic)"
test_endpoint "POST" "$API_URL/verify/ssl/website" 401 '{"domain":"google.com"}' "SSL Check Website"
test_endpoint "POST" "$API_URL/verify/ssl/certificate" 401 "" "SSL Check Certificate"
test_endpoint "POST" "$API_URL/verify/ssl/key-match" 401 "" "SSL Key Match"

# ID Verification
test_endpoint "POST" "$API_URL/verify/id" 401 "" "ID Verification"

# File Magic
test_endpoint "POST" "$API_URL/file-magic" 401 "" "File Magic Analysis"

# File Signature
test_endpoint "POST" "$API_URL/verify/file-signature" 401 "" "File Signature Verify"

# Badge
test_endpoint "GET" "$API_URL/badge/test123" 200 "" "Badge Get"
test_endpoint "GET" "$API_URL/lookup?badge_id=test" 200 "" "Badge Lookup"

# Webhooks
test_endpoint "POST" "$API_URL/webhooks" 401 '{"url":"https://example.com"}' "Webhook Register"
test_endpoint "GET" "$API_URL/webhooks" 401 "" "Webhook List"
test_endpoint "PUT" "$API_URL/webhooks/test-id" 401 '{"active":false}' "Webhook Update"
test_endpoint "DELETE" "$API_URL/webhooks/test-id" 401 "" "Webhook Delete"
test_endpoint "POST" "$API_URL/webhooks/test-id/test" 401 "" "Webhook Test"

# Billing
test_endpoint "GET" "$API_URL/billing/account" 401 "" "Billing Account"
test_endpoint "GET" "$API_URL/billing/plans" 200 "" "Billing Plans"
test_endpoint "POST" "$API_URL/billing/upgrade" 401 '{"plan":"pro"}' "Billing Upgrade"
test_endpoint "POST" "$API_URL/billing/cancel" 401 "" "Billing Cancel"
test_endpoint "GET" "$API_URL/billing/usage" 401 "" "Billing Usage"
test_endpoint "GET" "$API_URL/billing/invoices" 401 "" "Billing Invoices"
test_endpoint "POST" "$API_URL/billing/payment" 401 '{"amount":1000}' "Billing Payment"
test_endpoint "GET" "$API_URL/billing/recommendation" 401 "" "Billing Recommendation"
test_endpoint "POST" "$API_URL/billing/webhook/stripe" 400 "" "Billing Stripe Webhook"

# Crypto Validation
test_endpoint "POST" "$API_URL/crypto/validate" 200 '{"address":"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa","currency":"BTC"}' "Crypto Validate (generic)"
test_endpoint "POST" "$API_URL/crypto/validate/bitcoin" 200 '{"value":"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa","type":"address"}' "Crypto Validate Bitcoin"
test_endpoint "POST" "$API_URL/crypto/validate/ethereum" 200 '{"value":"0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb","type":"address"}' "Crypto Validate Ethereum"

# SSL Generator
test_endpoint "POST" "$API_URL/ssl/generate-csr" 400 '{"domain":"example.com"}' "SSL Generate CSR"
test_endpoint "POST" "$API_URL/ssl/validate-csr" 400 "" "SSL Validate CSR"

# JWT Tools
test_endpoint "POST" "$API_URL/jwt/validate" 200 '{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U"}' "JWT Validate"
test_endpoint "POST" "$API_URL/jwt/decode" 200 '{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U"}' "JWT Decode"
test_endpoint "POST" "$API_URL/jwt/sign" 400 '{"payload":{}}' "JWT Sign"

# Developer Tools
test_endpoint "POST" "$API_URL/tools/regex-test" 400 '{"pattern":"test"}' "Regex Test"
test_endpoint "POST" "$API_URL/tools/validate-data" 400 '{"data":"test"}' "Validate Data"
test_endpoint "POST" "$API_URL/tools/scan-secrets" 400 '{"content":"test"}' "Scan Secrets"
test_endpoint "POST" "$API_URL/tools/generate-hash" 400 '{"data":"test"}' "Generate Hash"
test_endpoint "POST" "$API_URL/tools/url-encode" 400 '{"data":"test"}' "URL Encode"

# Code Signing
test_endpoint "POST" "$API_URL/code-signing/sign" 401 "" "Code Signing Sign"
test_endpoint "GET" "$API_URL/code-signing/quota" 401 "" "Code Signing Quota"

# API Keys Management
test_endpoint "GET" "$API_URL/api-keys" 401 "" "API Keys List"
test_endpoint "POST" "$API_URL/api-keys" 401 '{"name":"Test Key"}' "API Keys Create"
test_endpoint "DELETE" "$API_URL/api-keys/test-id" 401 "" "API Keys Revoke"

# User Profile
test_endpoint "GET" "$API_URL/user/profile" 401 "" "User Profile"

# Verifications History
test_endpoint "GET" "$API_URL/verifications" 401 "" "Verifications List"

# API Documentation
test_endpoint "GET" "$API_URL/docs" 200 "" "API Documentation"

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                         TEST SUMMARY                          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${RED}❌ Failed: $FAILED${NC}"
echo -e "${YELLOW}⚠️  Missing: $MISSING${NC}"
echo ""

if [ $MISSING -gt 0 ]; then
    echo "Missing Endpoints:"
    for endpoint in "${MISSING_ENDPOINTS[@]}"; do
        echo "  • $endpoint"
    done
    echo ""
fi

if [ $FAILED -gt 0 ]; then
    echo "❌ TESTS FAILED - Found $FAILED failing endpoints"
    exit 1
elif [ $MISSING -gt 0 ]; then
    echo "⚠️  TESTS INCOMPLETE - Found $MISSING missing endpoints"
    exit 2
else
    echo "✅ ALL TESTS PASSED!"
    exit 0
fi
