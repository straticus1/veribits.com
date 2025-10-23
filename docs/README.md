# VeriBits.com — Super Site (API + Dashboard + Trust Badges)
**VeriBits** verifies the smallest things at scale: files, emails, and micro-transactions.

## What's here
1) **Production-ready PHP JSON API** with JWT auth (AWS Cognito-ready)
2) **Complete Next.js Dashboard** with authentication and user management
3) **Advanced Verification Services**: malware scanning, DNS analysis, SSL checks, ID verification
4) **AWS Infrastructure**: Terraform (ECS Fargate + ALB + ECR + RDS + ElastiCache + Cognito)
5) **Docker Production Setup** with multi-stage builds
6) **Deployment Automation**: Ansible playbooks & super deploy script
7) **Developer Tools**: CLI utilities, security audit scripts, documentation

## Quick Start
1. `bash scripts/setup.sh` → http://127.0.0.1:8080
2. Get a token:
   `curl -s -X POST localhost:8080/api/v1/auth/token -H 'Content-Type: application/json' -d '{"user":"you"}' | jq -r .access_token`
3. Use the token:
   `curl -H "Authorization: Bearer $TOKEN" localhost:8080/api/v1/me`

## Endpoints

### Core API
- GET `/api/v1/health` - API health check
- POST `/api/v1/auth/token` `{ user }` - Generate JWT token
- GET `/api/v1/me` - Get current user profile

### File & Security Verification
- POST `/api/v1/verify/file` `{ sha256 }` - Verify file hash
- POST `/api/v1/verify/email` `{ email }` - Email address validation
- POST `/api/v1/verify/tx` `{ network, tx }` - Blockchain transaction verification
- POST `/api/v1/verify/malware` (multipart file upload) - ClamAV malware scanning
- POST `/api/v1/inspect/archive` (multipart file upload) - Archive content inspection (.zip, .tar, .tar.gz, .tar.bz2, .tar.xz)

### DNS & Network Analysis
- POST `/api/v1/verify/dns` `{ domain, check_type }` - DNS health check
  - **check_type**: `full` | `records` | `ns` | `security` | `email` | `propagation` | `blacklist`
  - Includes: NS verification, DNSSEC, SPF/DMARC, propagation analysis, blacklist checking

### SSL Certificate Services
- POST `/api/v1/verify/ssl/website` `{ domain, port }` - SSL certificate from website
- POST `/api/v1/verify/ssl/certificate` (multipart file upload) - SSL certificate analysis
- POST `/api/v1/verify/ssl/key-match` (multipart certificate + private_key) - Verify key pair match

### Identity Verification
- POST `/api/v1/verify/id` (multipart id_document + selfie) - Government ID verification via After Dark Systems

### Trust Badges & Lookup
- GET `/api/v1/badge/{id}` - Verification badge display
- GET `/api/v1/lookup?q=...` - Search verification records

### Webhooks & Integration
- POST `/api/v1/webhooks` - Register webhook endpoint
- GET `/api/v1/webhooks` - List registered webhooks

## Attribution
Built with ♥ by **After Dark Systems**.
