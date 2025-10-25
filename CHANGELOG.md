# Changelog

All notable changes to VeriBits will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-10-25

### Added
- New Controllers:
  - `ApiKeyController` - Complete API key management (list, create, revoke)
  - `VerificationsController` - View verification history with pagination
  - `CodeSigningController` - Code signing functionality
  - `CryptoValidationController` - Cryptocurrency address validation (Bitcoin, Ethereum)
  - `JWTController` - JWT token validation, decoding, and signing
  - `NetworkToolsController` - Network diagnostics (DNS, SMTP, RBL checks)
  - `SSLGeneratorController` - SSL certificate generation and validation
  - `SteganographyController` - Steganography detection
  - `DeveloperToolsController` - Regex testing and developer utilities
  - `AnonymousLimitsController` - Anonymous user limit tracking

- New API Endpoints:
  - `/api/v1/crypto/validate` - Generic crypto validation with auto-detection
  - `/api/v1/crypto/validate/bitcoin` - Bitcoin-specific validation
  - `/api/v1/crypto/validate/ethereum` - Ethereum-specific validation
  - `/api/v1/jwt/validate` - JWT token validation
  - `/api/v1/jwt/decode` - JWT token decoding
  - `/api/v1/jwt/sign` - JWT token signing
  - `/api/v1/ssl/validate` - SSL certificate validation
  - `/api/v1/ssl/generate-csr` - SSL CSR generation
  - `/api/v1/ssl/validate-csr` - SSL CSR validation
  - `/api/v1/api-keys` (GET/POST/DELETE) - API key management
  - `/api/v1/verifications` - Verification history
  - `/api/v1/user/profile` - User profile endpoint
  - `/api/v1/anonymous/limits` - Anonymous user limits
  - `/api/v1/tools/regex-test` - Regex testing
  - `/api/v1/tools/smtp-relay-check` - SMTP relay checking
  - `/api/v1/tools/cert-convert` - Certificate format conversion
  - `/api/v1/tools/rbl-check` - RBL blacklist checking
  - `/api/v1/tools/dns-validate` - DNS validation
  - `/api/v1/tools/ip-calculate` - IP address calculations
  - `/api/v1/zone-validate` - DNS zone file validation
  - `/api/v1/file-magic` - File type detection
  - `/api/v1/steganography-detect` - Steganography detection
  - `/api/v1/code-signing/sign` - Code signing
  - `/api/v1/code-signing/quota` - Code signing quota check

- Frontend Pages:
  - `index.html` - Modern landing page
  - `about.html` - About page
  - `pricing.html` - Pricing and plans
  - `tools.html` - Tools listing
  - `dashboard.html` - User dashboard
  - `login.html` - Login page
  - `signup.html` - Registration page
  - `settings.html` - User settings
  - 13 individual tool pages in `/tool/` directory

- Database Migrations:
  - `001_initial_schema.sql` - Core schema
  - `002_core_tables.sql` - Core tables (users, verifications, api_keys)
  - `003_file_magic_table.sql` - File magic detection table
  - `004_file_signature_table.sql` - File signature table
  - `005_code_signing_table.sql` - Code signing table
  - `006_performance_indexes.sql` - Performance indexes

- Infrastructure:
  - Complete Terraform configuration for AWS deployment
  - ECS task definitions
  - Docker multi-stage build configuration
  - Nginx configuration for production
  - GitHub Actions deployment workflow

- CLI Tool:
  - Python-based CLI tool for VeriBits API
  - Setup script with `setup.py`
  - Command-line interface in `cli/veribits/`

- Documentation:
  - `ANONYMOUS_USER_CONSTRAINTS.md` - Anonymous user limits
  - `CHANGES_SUMMARY.md` - Detailed change tracking
  - `COMPETITIVE_ANALYSIS_MATRIX.md` - Competitive analysis
  - `COMPLETE_DEPLOYMENT_GUIDE.md` - Deployment instructions
  - `DATABASE_SETUP.md` - Database setup guide
  - `DEPLOYMENT_COMPLETE.md` - Deployment completion report
  - `DEPLOYMENT_READY_REPORT.md` - Pre-deployment checklist
  - `DEPLOYMENT_STATUS.md` - Current deployment status
  - `EXECUTIVE_SUMMARY.md` - Project executive summary
  - `GODADDY_DNS_UPDATE.md` - DNS configuration guide
  - `NEW_ENDPOINTS_IMPLEMENTED.md` - New endpoints documentation
  - `QUICK_START_ROADMAP.md` - Quick start guide
  - `SSL_HTTPS_CONFIGURED.md` - SSL configuration guide
  - `TRIAL_AND_PAYMENT_MODEL.md` - Business model documentation
  - `VERIBITS_10X_ENHANCEMENT_PROPOSAL.md` - Future enhancements

### Enhanced
- **Authentication System**:
  - Added Bearer token authentication support
  - Added API key authentication
  - Added optional authentication for anonymous users
  - Improved JWT token handling
  - Added authentication state tracking

- **Rate Limiting**:
  - Comprehensive anonymous user rate limiting
  - Redis-based rate limiting with PostgreSQL fallback
  - Configurable scan limits (5 free scans per 30 days)
  - Database-based rate limit tracking
  - IP-based anonymous user tracking
  - Monthly quota enforcement for authenticated users

- **Health Check System**:
  - Enhanced health checks with detailed component status
  - Database connectivity checks
  - Redis connectivity checks
  - Filesystem write checks
  - PHP extension checks
  - Response time measurements

- **Database Utilities**:
  - Added `insert()` method with auto-generated UUIDs
  - Added `update()` method for simple updates
  - Added `fetch()` method for single row retrieval
  - Added `fetchAll()` method for multiple rows
  - Improved error handling and logging
  - Added connection pooling support

- **Configuration Management**:
  - Environment-based configuration loading
  - Support for `.env` files
  - Improved error handling for missing config
  - Added database and Redis configuration options

- **Response Handling**:
  - Standardized JSON response format
  - Improved error responses with structured error objects
  - Added timestamp to all responses
  - Consistent success/error patterns across all endpoints

### Fixed
- JavaScript error handling in web tools now properly displays error messages
- Fixed "object Object" error display issue
- Replaced browser `alert()` with styled `showAlert()` in code-signing tool
- Added missing alert-container to code-signing.php
- Standardized error handling pattern across all 13 tool pages
- Fixed error message extraction to properly parse `error.message` from API responses
- Fixed SMTP relay check error handling
- Fixed certificate converter error handling (2 locations)
- Fixed RBL check error handling

### Security
- API key generation uses cryptographically secure random bytes (64 hex chars)
- API keys masked in list view (shows only first 8 and last 4 characters)
- Full API key shown only once at creation
- Soft delete for API keys (revokes instead of deleting)
- Rate limiting prevents abuse of anonymous endpoints
- Proper authentication enforcement on sensitive endpoints

### Changed
- Updated Dockerfile with multi-stage build for production optimization
- Modified `index.php` to include 10+ new route handlers
- Updated migration scripts to use proper timestamps

## [1.1.0] - 2025-10-23

### Added
- Comprehensive verification tools
- Infrastructure updates
- Initial deployment to production

## [1.0.0] - 2025-10-20

### Added
- Initial release
- Core API functionality
- Basic authentication
- Health check endpoint
