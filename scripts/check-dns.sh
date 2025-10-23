#!/usr/bin/env bash
# VeriBits DNS Status Checker
# Shows authoritative DNS servers and all records for veribits.com

set -euo pipefail

DOMAIN="veribits.com"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
cat << "EOF"
╦  ╦┌─┐┬─┐┬┌┐ ┬┌┬┐┌─┐  ╔╦╗╔╗╔╔═╗
╚╗╔╝├┤ ├┬┘│├┴┐│ │ └─┐   ║║║║║╚═╗
 ╚╝ └─┘┴└─┴└─┘┴ ┴ └─┘  ═╩╝╝╚╝╚═╝
DNS Status Checker
EOF
echo -e "${NC}"

# Function to run DNS query
query_dns() {
    local type=$1
    local target=$2
    dig +short "$target" "$type" 2>/dev/null
}

# Check if dig is installed
if ! command -v dig &> /dev/null; then
    echo -e "${RED}Error: 'dig' command not found. Please install dnsutils/bind-tools${NC}"
    exit 1
fi

echo -e "${CYAN}=== Authoritative Name Servers ===${NC}"
echo ""

# Get authoritative nameservers
NS_SERVERS=$(query_dns NS "$DOMAIN")

if [ -z "$NS_SERVERS" ]; then
    echo -e "${RED}❌ No nameservers found for $DOMAIN${NC}"
    echo -e "${YELLOW}This means the domain is not properly configured in DNS${NC}"
    echo ""
else
    echo -e "${GREEN}✓ Authoritative nameservers for $DOMAIN:${NC}"
    echo "$NS_SERVERS" | while read -r ns; do
        echo -e "  ${GREEN}→${NC} $ns"
        # Try to get IP of nameserver
        ns_ip=$(query_dns A "$ns" | head -1)
        if [ -n "$ns_ip" ]; then
            echo -e "    ${BLUE}IP:${NC} $ns_ip"
        fi
    done
    echo ""
fi

# Check if using Route53
if echo "$NS_SERVERS" | grep -q "awsdns"; then
    echo -e "${GREEN}✓ Using AWS Route53${NC}"
    echo ""
fi

echo -e "${CYAN}=== Current DNS Records ===${NC}"
echo ""

# A Records (IPv4)
echo -e "${MAGENTA}A Records (IPv4):${NC}"
A_RECORDS=$(query_dns A "$DOMAIN")
if [ -n "$A_RECORDS" ]; then
    echo "$A_RECORDS" | while read -r ip; do
        echo -e "  ${GREEN}$DOMAIN${NC} → ${YELLOW}$ip${NC}"
    done
else
    echo -e "  ${RED}No A records found${NC}"
fi
echo ""

# A Record for www
WWW_A=$(query_dns A "www.$DOMAIN")
if [ -n "$WWW_A" ]; then
    echo -e "  ${GREEN}www.$DOMAIN${NC} → ${YELLOW}$WWW_A${NC}"
    echo ""
fi

# AAAA Records (IPv6)
echo -e "${MAGENTA}AAAA Records (IPv6):${NC}"
AAAA_RECORDS=$(query_dns AAAA "$DOMAIN")
if [ -n "$AAAA_RECORDS" ]; then
    echo "$AAAA_RECORDS" | while read -r ip; do
        echo -e "  ${GREEN}$DOMAIN${NC} → ${YELLOW}$ip${NC}"
    done
else
    echo -e "  ${YELLOW}No AAAA records found${NC}"
fi
echo ""

# MX Records (Mail)
echo -e "${MAGENTA}MX Records (Mail):${NC}"
MX_RECORDS=$(query_dns MX "$DOMAIN")
if [ -n "$MX_RECORDS" ]; then
    echo "$MX_RECORDS" | while read -r mx; do
        echo -e "  ${GREEN}$DOMAIN${NC} → ${YELLOW}$mx${NC}"
    done
else
    echo -e "  ${YELLOW}No MX records found${NC}"
fi
echo ""

# TXT Records
echo -e "${MAGENTA}TXT Records:${NC}"
TXT_RECORDS=$(query_dns TXT "$DOMAIN")
if [ -n "$TXT_RECORDS" ]; then
    echo "$TXT_RECORDS" | while IFS= read -r txt; do
        # Check for SPF
        if echo "$txt" | grep -q "v=spf1"; then
            echo -e "  ${GREEN}SPF:${NC} $txt"
        # Check for DMARC
        elif echo "$txt" | grep -q "v=DMARC1"; then
            echo -e "  ${GREEN}DMARC:${NC} $txt"
        else
            echo -e "  ${YELLOW}$txt${NC}"
        fi
    done
else
    echo -e "  ${YELLOW}No TXT records found${NC}"
fi
echo ""

# DMARC Record
echo -e "${MAGENTA}DMARC Record:${NC}"
DMARC=$(query_dns TXT "_dmarc.$DOMAIN")
if [ -n "$DMARC" ]; then
    echo -e "  ${GREEN}$DMARC${NC}"
else
    echo -e "  ${YELLOW}No DMARC record found${NC}"
fi
echo ""

# CNAME Records
echo -e "${MAGENTA}Common CNAME Records:${NC}"
for subdomain in www api mail ftp; do
    CNAME=$(query_dns CNAME "$subdomain.$DOMAIN")
    if [ -n "$CNAME" ]; then
        echo -e "  ${GREEN}$subdomain.$DOMAIN${NC} → ${YELLOW}$CNAME${NC}"
    fi
done
echo ""

# SOA Record
echo -e "${MAGENTA}SOA Record (Start of Authority):${NC}"
SOA=$(dig +short SOA "$DOMAIN" 2>/dev/null)
if [ -n "$SOA" ]; then
    echo -e "  ${YELLOW}$SOA${NC}"
else
    echo -e "  ${YELLOW}No SOA record found${NC}"
fi
echo ""

# Check if domain resolves to ALB
echo -e "${CYAN}=== AWS ALB Check ===${NC}"
echo ""

if [ -n "$A_RECORDS" ]; then
    # Try to determine if it's an AWS ALB by checking the hostname
    PTR=$(dig +short -x "$(echo "$A_RECORDS" | head -1)" 2>/dev/null)
    if echo "$PTR" | grep -q "elb.amazonaws.com"; then
        echo -e "${GREEN}✓ Domain points to AWS Application Load Balancer${NC}"
        echo -e "  ${BLUE}PTR:${NC} $PTR"
    else
        echo -e "${YELLOW}⚠ Domain does not appear to point to AWS ALB${NC}"
    fi
else
    echo -e "${RED}❌ Cannot check - no A records found${NC}"
fi
echo ""

# Propagation check
echo -e "${CYAN}=== DNS Propagation Check ===${NC}"
echo ""

PUBLIC_DNS_SERVERS=(
    "8.8.8.8:Google"
    "8.8.4.4:Google_Secondary"
    "1.1.1.1:Cloudflare"
    "1.0.0.1:Cloudflare_Secondary"
    "9.9.9.9:Quad9"
    "208.67.222.222:OpenDNS"
)

echo "Checking DNS resolution across major public DNS servers..."
echo ""

ALL_MATCH=true
FIRST_IP=""

for server_info in "${PUBLIC_DNS_SERVERS[@]}"; do
    IFS=':' read -r server name <<< "$server_info"
    result=$(dig +short @"$server" "$DOMAIN" A 2>/dev/null | head -1)

    if [ -z "$FIRST_IP" ] && [ -n "$result" ]; then
        FIRST_IP="$result"
    fi

    if [ -n "$result" ]; then
        if [ "$result" = "$FIRST_IP" ]; then
            echo -e "  ${GREEN}✓${NC} $name ($server): ${GREEN}$result${NC}"
        else
            echo -e "  ${YELLOW}⚠${NC} $name ($server): ${YELLOW}$result${NC} (different!)"
            ALL_MATCH=false
        fi
    else
        echo -e "  ${RED}✗${NC} $name ($server): ${RED}No response${NC}"
        ALL_MATCH=false
    fi
done

echo ""

if [ "$ALL_MATCH" = true ] && [ -n "$FIRST_IP" ]; then
    echo -e "${GREEN}✓ DNS is fully propagated! All servers return: $FIRST_IP${NC}"
elif [ -n "$FIRST_IP" ]; then
    echo -e "${YELLOW}⚠ DNS propagation in progress. Some servers have different results.${NC}"
    echo -e "${YELLOW}  This is normal and can take up to 48 hours.${NC}"
else
    echo -e "${RED}✗ DNS not yet propagated. Domain is not resolving.${NC}"
fi

echo ""
echo -e "${CYAN}=== Summary ===${NC}"
echo ""

if [ -n "$NS_SERVERS" ]; then
    echo -e "${GREEN}✓ Domain has nameservers configured${NC}"
else
    echo -e "${RED}✗ Domain has no nameservers configured${NC}"
fi

if [ -n "$A_RECORDS" ]; then
    echo -e "${GREEN}✓ Domain has A records${NC}"
else
    echo -e "${RED}✗ Domain has no A records${NC}"
fi

if [ -n "$WWW_A" ]; then
    echo -e "${GREEN}✓ www subdomain configured${NC}"
else
    echo -e "${YELLOW}⚠ www subdomain not configured${NC}"
fi

if [ "$ALL_MATCH" = true ] && [ -n "$FIRST_IP" ]; then
    echo -e "${GREEN}✓ DNS fully propagated${NC}"
else
    echo -e "${YELLOW}⚠ DNS propagation in progress${NC}"
fi

echo ""
echo -e "${BLUE}To force DNS refresh on your system:${NC}"
echo "  macOS:   sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder"
echo "  Linux:   sudo systemd-resolve --flush-caches"
echo "  Windows: ipconfig /flushdns"
echo ""
