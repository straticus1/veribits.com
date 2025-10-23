# API Notes
- Auth: JWT bearer (mints locally for MVP); replace with Cognito JWK validation in prod.
- Webhooks: `verification.completed`, `badge.issued` (DB schema included).
- Billing: usage-metered tables present; connect your PSP in a worker/service.

## Rate Limits & Quotas
- Use `api_keys`, `quotas`, and Redis for counters. Return `429` when exceeded.

## New Security Features

### Malware Scanning (POST /api/v1/verify/malware)
Scans uploaded files for malware using ClamAV.

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Auth: Bearer token required
- Body: `file` (binary upload, max 50MB)

**Response:**
```json
{
  "type": "malware_scan",
  "file_hash": "sha256_hash",
  "file_size_bytes": 1024,
  "scan_status": "clean|infected|error",
  "threats_found": [{"name": "Win.Test.EICAR", "type": "virus"}],
  "is_clean": true,
  "clamav_version": "1.0.0",
  "signature_version": "26999/2024-01-15",
  "scan_time_ms": 150,
  "badge_id": "mscan_abc123",
  "badge_url": "/api/v1/badge/mscan_abc123",
  "scanned_at": "2025-01-15T10:30:00Z"
}
```

### Archive Inspection (POST /api/v1/inspect/archive)
Lists contents of archive files without extraction. Detects suspicious patterns.

**Supported formats:** .zip, .tar, .tar.gz, .tar.bz2, .tar.xz, .tgz, .tbz, .txz

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Auth: Bearer token required
- Body: `file` (binary upload, max 100MB)

**Response:**
```json
{
  "type": "archive_inspection",
  "file_hash": "sha256_hash",
  "archive_type": "zip",
  "total_files": 42,
  "total_size_bytes": 15728640,
  "compressed_size_bytes": 5242880,
  "compression_ratio": 3.0,
  "contents": [
    {
      "path": "src/index.php",
      "size": 2048,
      "compressed_size": 512,
      "modified": "2025-01-15T10:30:00Z",
      "crc": "abc12345",
      "type": "file"
    }
  ],
  "suspicious_flags": [
    "Suspicious path pattern detected: ../ in ../etc/passwd"
  ],
  "integrity_status": "ok|corrupted|suspicious",
  "is_safe": true,
  "badge_id": "arch_abc123",
  "badge_url": "/api/v1/badge/arch_abc123",
  "inspected_at": "2025-01-15T10:30:00Z"
}
```

**Security checks:**
- Path traversal detection (../, etc.)
- Absolute path detection
- Suspicious file extensions (.exe, .dll, .bat, .sh)
- System path detection (/etc/, /var/, C:\)
- Zip bomb detection (compression ratio > 100)
- Archive integrity validation

## DNS Verification Suite (POST /api/v1/verify/dns)
Comprehensive DNS health check with NS, MX, PTR, A, DNSSEC validation, propagation verification, and blacklist checking.

**Request:**
- Method: POST
- Content-Type: application/json
- Auth: Bearer token required
- Body:
```json
{
  "domain": "example.com",
  "check_type": "full"
}
```

**Check Types:**
- `full` - Complete DNS audit (all checks below)
- `ns` - Nameserver verification only
- `records` - All DNS records (A, AAAA, MX, NS, TXT, CNAME, SOA, PTR)
- `security` - DNSSEC validation
- `email` - MX records + SPF/DMARC validation
- `propagation` - Check across 6 major DNS providers
- `blacklist` - RBL/DNSBL checking

**Response (full check):**
```json
{
  "type": "dns_check",
  "domain": "example.com",
  "check_type": "full",
  "results": {
    "dns_records": {
      "A": [{"ip": "93.184.216.34", "ttl": 86400}],
      "AAAA": [{"ipv6": "2606:2800:220:1:248:1893:25c8:1946", "ttl": 86400}],
      "MX": [{"target": "mail.example.com", "pri": 10, "ttl": 3600}],
      "NS": [
        {"target": "ns1.example.com", "ttl": 86400},
        {"target": "ns2.example.com", "ttl": 86400}
      ],
      "TXT": [{"txt": "v=spf1 include:_spf.example.com ~all"}],
      "SOA": {...},
      "PTR": [...]
    },
    "ns_verification": {
      "status": "healthy",
      "nameserver_count": 2,
      "nameservers": [
        {
          "hostname": "ns1.example.com",
          "responsive": true,
          "ip_addresses": ["192.0.2.1"],
          "response_time_ms": 45
        },
        {
          "hostname": "ns2.example.com",
          "responsive": true,
          "ip_addresses": ["192.0.2.2"],
          "response_time_ms": 52
        }
      ],
      "issues": []
    },
    "dnssec_status": "enabled",
    "email_config": {
      "mx_records": [
        {"host": "mail.example.com", "priority": 10}
      ],
      "spf_record": "v=spf1 include:_spf.example.com ~all",
      "spf_valid": true,
      "dmarc_record": "v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com",
      "dmarc_policy": "quarantine",
      "status": "fully_configured"
    },
    "propagation": {
      "servers": {
        "Google Primary": {
          "server": "8.8.8.8",
          "responsive": true,
          "records": ["93.184.216.34"],
          "response_time_ms": 23
        },
        "Google Secondary": {...},
        "Cloudflare Primary": {...},
        "Cloudflare Secondary": {...},
        "Quad9": {...},
        "OpenDNS": {...}
      },
      "consistent": true,
      "propagation_status": "complete"
    },
    "blacklist_status": {
      "status": "clean",
      "ips_checked": ["93.184.216.34"],
      "rbls_checked": 5,
      "listings": []
    }
  },
  "health_score": 95,
  "health_grade": "A",
  "issues_found": ["DNSSEC not enabled"],
  "check_time_ms": 1250,
  "badge_id": "dns_abc123def456",
  "badge_url": "/api/v1/badge/dns_abc123def456",
  "checked_at": "2025-01-15T10:30:00Z"
}
```

**NS Record Verification Features:**
- Validates minimum 2 nameservers
- Tests nameserver responsiveness
- Resolves NS hostnames to IPs
- Measures response times
- Detects unresponsive/unreachable nameservers

**MX Record Verification Features:**
- Lists all MX records with priorities
- Validates SPF records (v=spf1 syntax)
- Checks DMARC policy (none/quarantine/reject)
- Detects missing email security configurations
- Validates overall email deliverability setup

**PTR/A Record Features:**
- Full A record enumeration
- AAAA (IPv6) support
- PTR reverse DNS lookups
- IP address validation

**DNSSEC Validation:**
- Checks for DNSKEY, DS, RRSIG records
- Returns enabled/disabled/unknown status

**Propagation Verification:**
- Tests 6 major public DNS servers (Google, Cloudflare, Quad9, OpenDNS)
- Measures response times from each
- Detects inconsistent propagation
- Shows which servers have updated records

**RBL/Blacklist Checking:**
- Checks 5 major RBL services (Spamhaus, SpamCop, SORBS, CBL, Barracuda)
- Tests all domain A record IPs
- Reports specific listings found

**Health Scoring:**
- 100-point scale with letter grades (A-F)
- Deductions for missing NS records (-30)
- Deductions for insufficient nameservers (-10)
- Deductions for no SPF (-10) or DMARC (-10)
- Deductions for blacklist presence (-40)
- Deductions for propagation issues (-15)

## SSL/TLS Certificate Verification Suite

### 1. SSL Website Check (POST /api/v1/verify/ssl/website)
Retrieves and analyzes SSL certificate from a live website.

**Request:**
- Method: POST
- Content-Type: application/json
- Auth: Bearer token required
- Body:
```json
{
  "domain": "example.com",
  "port": 443
}
```

**Response:**
```json
{
  "type": "ssl_website_check",
  "domain": "example.com",
  "port": 443,
  "certificate": {
    "subject": {
      "CN": "example.com",
      "O": "Example Inc",
      "C": "US"
    },
    "issuer": {
      "CN": "Let's Encrypt Authority X3",
      "O": "Let's Encrypt",
      "C": "US"
    },
    "validity": {
      "valid_from": "2024-01-01T00:00:00Z",
      "valid_to": "2025-04-01T00:00:00Z",
      "is_valid": true,
      "days_until_expiry": 45
    },
    "serial_number": "ABC123",
    "signature_algorithm": "sha256WithRSAEncryption",
    "subject_key_identifier": "A1B2C3D4E5F6...",
    "authority_key_identifier": "F6E5D4C3B2A1...",
    "subject_alt_names": ["DNS:example.com", "DNS:www.example.com"],
    "version": 3
  },
  "security_score": 95,
  "security_grade": "A",
  "warnings": [],
  "check_time_ms": 234,
  "badge_id": "ssl_abc123",
  "badge_url": "/api/v1/badge/ssl_abc123",
  "checked_at": "2025-01-15T10:30:00Z"
}
```

### 2. SSL Certificate File Check (POST /api/v1/verify/ssl/certificate)
Analyzes an uploaded SSL certificate file.

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Auth: Bearer token required
- Body: `certificate` (file upload - .pem, .crt, .cer formats, max 10MB)

**Response:**
```json
{
  "type": "ssl_certificate_check",
  "certificate": {
    "subject": {...},
    "issuer": {...},
    "validity": {...},
    "subject_key_identifier": "A1B2C3D4E5F6...",
    "authority_key_identifier": "F6E5D4C3B2A1...",
    "signature_algorithm": "sha256WithRSAEncryption",
    "subject_alt_names": ["DNS:example.com"],
    "extensions": {...}
  },
  "security_score": 90,
  "security_grade": "A",
  "warnings": ["Certificate expires in 28 days"],
  "badge_id": "ssl_def456",
  "badge_url": "/api/v1/badge/ssl_def456",
  "checked_at": "2025-01-15T10:30:00Z"
}
```

### 3. SSL Certificate/Key Match Verification (POST /api/v1/verify/ssl/key-match)
Verifies that an SSL certificate matches a private key by comparing SubjectKeyIdentifier and public key modulus.

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Auth: Bearer token required
- Body:
  - `certificate` (file upload)
  - `private_key` (file upload)

**Response:**
```json
{
  "type": "ssl_key_match",
  "match": true,
  "verification_method": "modulus_comparison",
  "details": {
    "certificate_modulus": "A1B2C3D4E5F6...",
    "key_modulus": "A1B2C3D4E5F6...",
    "modulus_match": true,
    "public_key_match": true
  },
  "certificate_subject": {
    "CN": "example.com",
    "O": "Example Inc"
  },
  "subject_key_identifier": "A1B2C3D4E5F6...",
  "badge_id": "ssl_match_abc123",
  "badge_url": "/api/v1/badge/ssl_match_abc123",
  "checked_at": "2025-01-15T10:30:00Z"
}
```

**Certificate/Key Match Verification Methods:**
1. **Modulus Comparison** (Primary)
   - Extracts public key modulus from certificate using `openssl x509 -modulus`
   - Extracts modulus from private key using `openssl rsa -modulus`
   - Compares the two values for exact match

2. **Public Key Comparison** (Secondary)
   - Extracts public key from certificate
   - Derives public key from private key
   - Compares the public keys directly

**SSL Security Scoring:**
- Base score: 100 points
- Expired/invalid certificate: -50 points
- Expires in ≤30 days: -30 points
- Expires in ≤60 days: -15 points
- SHA1 signature algorithm: -20 points
- MD5 signature algorithm: -30 points
- Self-signed certificate: -10 points
- Final grade: A (90-100), B (80-89), C (70-79), D (60-69), F (<60)

**SSL Features:**
- Subject and Issuer details extraction
- Validity period checking with expiry countdown
- Subject Alternative Names (SAN) parsing
- SubjectKeyIdentifier extraction
- AuthorityKeyIdentifier extraction
- Certificate chain validation
- Signature algorithm analysis
- Security scoring and warnings

## Government ID Verification (POST /api/v1/verify/id)
Verifies government-issued identification documents using AI-powered face matching and document authentication via After Dark Systems ID Verification Service.

**Workflow:**
1. User uploads government ID document (front/back)
2. User captures selfie photo with camera
3. System submits to external verification API
4. Returns verification result with confidence scores

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Auth: Bearer token required
- Body:
  - `id_document` (file upload - ID card, driver's license, passport, max 20MB)
  - `selfie` (file upload - user photo, max 20MB)

**Supported Formats:**
- JPEG, PNG, HEIC, WebP

**Response:**
```json
{
  "type": "id_verification",
  "verification_status": "verified",
  "verified": true,
  "confidence_score": 94.5,
  "face_match_score": 96.8,
  "document_type": "drivers_license",
  "extracted_data": {
    "first_name": "JOHN",
    "last_name": "DOE",
    "date_of_birth": "1990-01-01",
    "document_number": "D1234567",
    "expiration_date": "2028-12-31",
    "issuing_country": "USA",
    "issuing_state": "CA",
    "address": "123 Main St, City, CA 90210"
  },
  "warnings": [
    "Document expires in less than 6 months"
  ],
  "verification_time_ms": 3420,
  "badge_id": "idverify_abc123def456",
  "badge_url": "/api/v1/badge/idverify_abc123def456",
  "verified_at": "2025-01-15T10:30:00Z"
}
```

**Verification Status Values:**
- `verified` - ID successfully verified, face match passed
- `failed` - Verification failed (low confidence or face mismatch)
- `pending` - Verification in progress (async processing)
- `error` - Technical error during verification

**Confidence Scores:**
- `confidence_score` (0-100) - Overall document authenticity confidence
- `face_match_score` (0-100) - Facial recognition match between ID and selfie

**Extracted Data Fields:**
Varies by document type, may include:
- Personal information (name, DOB, address)
- Document details (number, issue/expiry dates)
- Physical characteristics (height, weight, eye color)
- Security features verification results

**Security Features:**
- Document tampering detection
- Hologram/watermark verification
- Barcode/MRZ validation
- Face liveness detection
- Age verification
- Document expiry checking

**Privacy & Compliance:**
- Images are hashed and not stored permanently
- Extracted data stored encrypted
- GDPR/CCPA compliant
- PII redaction options available
- Audit trail maintained

**Integration:**
- Powered by After Dark Systems ID Verification Service
- External API: `https://idverify.aeims.app`
- Requires `ID_VERIFY_API_KEY` environment variable
- Fallback mock mode for testing when API unavailable

**Use Cases:**
- KYC (Know Your Customer) compliance
- Age verification for restricted content
- Identity verification for financial services
- Account security enhancement
- Fraud prevention
- Regulatory compliance (AML, etc.)
