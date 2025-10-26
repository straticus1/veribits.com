# VeriBits

**Professional Verification & Developer Tools Platform**

VeriBits is a comprehensive API platform providing verification, validation, and developer tools for modern applications. Built with PHP 8.3, PostgreSQL, Redis, and Docker.

---

## 🚀 Features

### Verification Tools
- **Cryptocurrency Validation** - Validate Bitcoin, Ethereum addresses with auto-detection
- **JWT Tools** - Decode, validate, and sign JWT tokens
- **SSL/TLS Validation** - Check SSL certificates and generate CSRs
- **Code Signing** - Sign and verify code artifacts
- **Steganography Detection** - Detect hidden data in images
- **File Magic** - Identify file types and MIME types
- **Security Breach Checker** 🆕 - Check emails/passwords against known data breaches (Have I Been Pwned)
- **Cloud Storage Auditor** 🆕 - Analyze AWS S3, Azure, GCP storage security configurations

### Network Tools
- **DNS Validation** - DNS record checks and zone file validation
- **SMTP Relay Check** - Test SMTP relay configuration
- **RBL Check** - Check IP addresses against blacklists
- **IP Calculator** - CIDR calculations and subnet tools
- **Certificate Converter** - Convert between certificate formats
- **Visual Traceroute** 🆕 - Interactive network path visualization with geographic mapping
- **BGP Intelligence Portal** 🆕 - Advanced BGP route analysis and hijacking detection

### Developer Tools
- **Regex Tester** - Test and validate regular expressions
- **API Key Management** - Generate and manage API keys
- **Verification History** - Track all verifications with pagination
- **Search Tools** 🆕 - Full-text search across all tools and documentation

---

## 📋 Requirements

- **Docker** & Docker Compose (recommended)
- **PHP** 8.3+ with extensions: pdo_pgsql, redis, curl, mbstring, openssl
- **PostgreSQL** 15+
- **Redis** 7+ (optional, for caching and rate limiting)
- **Nginx** or Apache

---

## 🛠️ Installation

### Using Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/yourusername/veribits.com.git
cd veribits.com

# Copy environment file
cp .env.example .env

# Edit .env with your configuration
nano .env

# Build and start containers
docker-compose up -d

# Run database migrations
docker-compose exec app php db/migrate.php

# Check health
curl http://localhost:8080/api/v1/health
```

### Manual Installation

```bash
# Install dependencies
composer install

# Configure database
createdb veribits
psql veribits < db/migrations/001_initial_schema.sql
psql veribits < db/migrations/002_core_tables.sql
# ... run all migrations

# Configure web server (Nginx example)
cp nginx.conf /etc/nginx/sites-available/veribits
ln -s /etc/nginx/sites-available/veribits /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# Start PHP-FPM
systemctl start php8.3-fpm
```

---

## 🔧 Configuration

Create a `.env` file in the project root:

```env
# Database
DB_HOST=localhost
DB_PORT=5432
DB_NAME=veribits
DB_USER=veribits
DB_PASS=your_password

# Redis (optional)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASS=

# Application
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=your_jwt_secret_key_here
API_URL=https://api.veribits.com

# Rate Limiting
ANONYMOUS_SCANS_LIMIT=5
ANONYMOUS_SCANS_PERIOD=2592000

# File Upload
MAX_FILE_SIZE=52428800
UPLOAD_DIR=/var/www/uploads
```

---

## 📚 API Documentation

### Authentication

VeriBits supports two authentication methods:

1. **Bearer Token (JWT)**
   ```bash
   curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
        https://api.veribits.com/api/v1/user/profile
   ```

2. **API Key**
   ```bash
   curl -H "Authorization: Bearer YOUR_API_KEY" \
        https://api.veribits.com/api/v1/verifications
   ```

### Anonymous Access

Some endpoints support anonymous access with rate limiting (5 free scans per 30 days):
- `/api/v1/crypto/validate`
- `/api/v1/jwt/validate`
- `/api/v1/ssl/validate`

### Example Requests

#### Validate Cryptocurrency Address
```bash
curl -X POST https://api.veribits.com/api/v1/crypto/validate \
  -H "Content-Type: application/json" \
  -d '{"address": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"}'
```

#### Decode JWT Token
```bash
curl -X POST https://api.veribits.com/api/v1/jwt/decode \
  -H "Content-Type: application/json" \
  -d '{"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."}'
```

#### Check SSL Certificate
```bash
curl -X POST https://api.veribits.com/api/v1/ssl/validate \
  -H "Content-Type: application/json" \
  -d '{"domain": "google.com", "port": 443}'
```

#### Create API Key (requires authentication)
```bash
curl -X POST https://api.veribits.com/api/v1/api-keys \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "My API Key"}'
```

---

## 🗂️ Project Structure

```
veribits.com/
├── app/
│   ├── public/              # Web root
│   │   ├── index.php        # Main application entry point
│   │   ├── assets/          # CSS, JS, images
│   │   └── tool/            # Individual tool pages
│   └── src/
│       ├── Controllers/     # API controllers
│       └── Utils/           # Utility classes (Auth, DB, Redis, etc.)
├── cli/                     # Command-line interface
├── config/                  # Configuration files
├── db/
│   └── migrations/          # Database migration files
├── docker/                  # Docker configuration
├── infrastructure/
│   └── terraform/           # Infrastructure as Code
├── tests/                   # Test files
├── CHANGELOG.md            # Version history
└── README.md               # This file
```

---

## 🧪 Testing

```bash
# Run tests (if implemented)
./vendor/bin/phpunit

# Test specific endpoint
curl -X GET http://localhost:8080/api/v1/health

# Check anonymous rate limiting
for i in {1..6}; do
  curl -X POST http://localhost:8080/api/v1/crypto/validate \
    -H "Content-Type: application/json" \
    -d '{"address": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"}'
done
```

---

## 🚢 Deployment

### AWS ECS Deployment

```bash
# Build Docker image
docker build -t veribits:latest -f docker/Dockerfile .

# Tag for ECR
docker tag veribits:latest YOUR_ECR_REPO/veribits:latest

# Push to ECR
docker push YOUR_ECR_REPO/veribits:latest

# Deploy with Terraform
cd infrastructure/terraform
terraform init
terraform plan
terraform apply
```

See [COMPLETE_DEPLOYMENT_GUIDE.md](COMPLETE_DEPLOYMENT_GUIDE.md) for detailed instructions.

---

## 📊 Rate Limits

### Anonymous Users
- **Free scans:** 5 per IP address per 30 days
- **Max file size:** 50MB
- **Supported endpoints:** Crypto validation, JWT validation, SSL validation

### Authenticated Users
- **Basic Plan:** 1,000 scans/month
- **Pro Plan:** 10,000 scans/month
- **Enterprise:** Custom limits
- **Max file size:** 500MB

---

## 🔐 Security

- **Authentication:** JWT tokens and API keys
- **Rate Limiting:** Redis-based with PostgreSQL fallback
- **HTTPS:** TLS 1.2+ required in production
- **API Key Security:** Cryptographically secure generation, masked display
- **SQL Injection:** Prepared statements throughout
- **CORS:** Configurable origins
- **Input Validation:** Comprehensive validation on all endpoints

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📝 License

© After Dark Systems. All rights reserved.

---

## 📞 Support

- **Documentation:** [docs.veribits.com](https://docs.veribits.com)
- **Email:** support@veribits.com
- **Issues:** [GitHub Issues](https://github.com/yourusername/veribits.com/issues)

---

## 🗺️ Roadmap

### Version 1.4.0 (Planned)
- [ ] Additional cryptocurrency support (LTC, BCH, XRP)
- [ ] WebSocket support for real-time updates
- [ ] GraphQL API
- [ ] Enhanced documentation with OpenAPI/Swagger
- [ ] Mobile app (iOS/Android)

### Version 1.5.0 (Planned)
- [ ] Machine learning-based file analysis
- [ ] Advanced steganography detection
- [ ] Blockchain integration
- [ ] Multi-language support

---

## 📈 Status

**Current Version:** 1.3.0  
**Status:** Production Ready ✅  
**Last Updated:** October 26, 2025  

[![Health Check](https://img.shields.io/badge/status-healthy-success)](https://api.veribits.com/api/v1/health)
[![Version](https://img.shields.io/badge/version-1.3.0-blue)](https://github.com/yourusername/veribits.com/releases)
[![License](https://img.shields.io/badge/license-proprietary-red)](LICENSE)

---

**Built with ❤️ by After Dark Systems**
