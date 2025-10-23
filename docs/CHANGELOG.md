# CHANGELOG

## 2025-10-23 — v1.0.0
- Complete production-ready implementation
- **New Verification Services:**
  - Malware scanning with ClamAV integration
  - Archive inspection for ZIP, TAR, and compressed files
  - Comprehensive DNS health checks with propagation analysis
  - SSL certificate verification (websites and file uploads)
  - SSL private key matching
  - Government ID verification via After Dark Systems API
- **Enhanced Frontend:**
  - Complete Next.js dashboard with login/signup flows
  - Modern dark theme with brand gradient styling
  - Responsive navigation and user management
  - Real-time verification history and stats
- **Infrastructure & Deployment:**
  - Super deployment script for After Dark Systems VPC
  - Production Terraform configuration
  - Enhanced Ansible deployment playbooks
  - Docker production builds
- **Developer Experience:**
  - Comprehensive API documentation updates
  - Security audit scripts
  - Enhanced error handling and logging
  - Rate limiting and quota management

## 2025-09-16 — v0.2.0
- Full rebuild with JWT middleware + protected routes
- Postgres schema & Redis plan
- Webhooks + Billing tables
- **ECS Fargate + ALB + ECR + RDS + ElastiCache + Cognito** Terraform
- Next.js dashboard scaffold
- Ansible playbook & deployment scripts
- Attribution: After Dark Systems
