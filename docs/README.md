# VeriBits.com — Super Site (API + Dashboard + Trust Badges)
**VeriBits** verifies the smallest things at scale: files, emails, and micro-transactions.

## What’s here
1) PHP JSON API + JWT auth (Cognito-ready)
2) CLI utility
3) Terraform (ECS Fargate + ALB + ECR + RDS Postgres + ElastiCache Redis + Cognito)
4) Dockerfile
5) Next.js dashboard scaffold (accessible)
6) Ansible convenience playbook
7) Scripts for local dev & deployment

## Quick Start
1. `bash scripts/setup.sh` → http://127.0.0.1:8080
2. Get a token:
   `curl -s -X POST localhost:8080/api/v1/auth/token -H 'Content-Type: application/json' -d '{"user":"you"}' | jq -r .access_token`
3. Use the token:
   `curl -H "Authorization: Bearer $TOKEN" localhost:8080/api/v1/me`

## Endpoints
- GET `/api/v1/health`
- POST `/api/v1/auth/token`
- GET `/api/v1/me`
- POST `/api/v1/verify/file` `{ sha256 }`
- POST `/api/v1/verify/email` `{ email }`
- POST `/api/v1/verify/tx` `{ network, tx }`
- GET `/api/v1/badge/{id}`
- GET `/api/v1/lookup?q=...`
- POST `/api/v1/webhooks` (register) / GET `/api/v1/webhooks` (list)

## Attribution
Built with ♥ by **After Dark Systems**.
