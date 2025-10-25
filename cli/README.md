# VeriBits CLI

Professional security and developer tools in your terminal.

[![PyPI version](https://badge.fury.io/py/veribits.svg)](https://badge.fury.io/py/veribits)
[![Python 3.8+](https://img.shields.io/badge/python-3.8+-blue.svg)](https://www.python.org/downloads/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
# Install via pip
pip install veribits

# Or install from source
git clone https://github.com/afterdarksystems/veribits-cli.git
cd veribits-cli
pip install -e .
```

## Quick Start

```bash
# Check your usage limits
veribits limits

# Decode a JWT token
veribits jwt-decode "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

# Test a regex pattern
veribits regex "\\d{3}-\\d{3}-\\d{4}" "Call me at 555-123-4567"

# Scan for secrets in a file
veribits secrets ./config.yaml

# Generate hashes
veribits hash "password123" -a md5 -a sha256 -a sha512

# Validate Bitcoin address
veribits bitcoin 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa

# Validate Ethereum address
veribits ethereum 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb

# Detect file type
veribits file-magic ./unknown-file.bin
```

## Commands

### JWT Tools

```bash
# Decode JWT token
veribits jwt-decode TOKEN [--secret SECRET] [--verify]

# Generate JWT token
veribits jwt-sign --secret "my-secret" --payload '{"user_id": 123}' --expires 3600
```

### Developer Tools

```bash
# Test regular expressions
veribits regex PATTERN TEXT [--flags FLAGS]

# Scan for exposed secrets
veribits secrets FILE_PATH

# Generate cryptographic hashes
veribits hash TEXT [-a ALGORITHM]
# Algorithms: md5, sha1, sha256, sha512, bcrypt
```

### Cryptocurrency Validation

```bash
# Validate Bitcoin address or transaction
veribits bitcoin ADDRESS [--type address|transaction]

# Validate Ethereum address or transaction
veribits ethereum ADDRESS [--type address|transaction]
```

### File Analysis

```bash
# Detect file type by magic number
veribits file-magic FILE_PATH
```

### Configuration

```bash
# Show current configuration
veribits config

# Check usage limits
veribits limits
```

## Authentication

For unlimited usage and advanced features, set your API key:

```bash
export VERIBITS_API_KEY="your-api-key-here"
```

Get your API key at: https://veribits.com/dashboard

## Environment Variables

- `VERIBITS_API_URL` - Override API endpoint (default: https://veribits.com/api/v1)
- `VERIBITS_API_KEY` - Your API key for authenticated requests

## Usage Limits

**Anonymous (No API Key):**
- 5 free scans per 30-day period
- 50MB max file size
- All tools available

**Authenticated:**
- 50+ scans per month (Free tier)
- 200MB max file size
- Priority processing
- API access

See pricing: https://veribits.com/pricing.html

## Examples

### CI/CD Integration

```yaml
# GitHub Actions example
- name: Scan for secrets
  run: |
    pip install veribits
    veribits secrets ./src > secrets-report.txt
```

### Security Scanning

```bash
# Scan all JavaScript files for secrets
for file in $(find . -name "*.js"); do
    echo "Scanning $file..."
    veribits secrets "$file"
done
```

### JWT Debugging

```bash
# Decode and verify JWT from environment variable
veribits jwt-decode "$AUTH_TOKEN" --secret "$JWT_SECRET" --verify
```

### File Type Detection

```bash
# Batch process files
for file in ./uploads/*; do
    veribits file-magic "$file"
done
```

## Available Tools (18+)

### Security & Cryptography
- 🔍 File Magic Detector
- ✍️ File Signature Verifier (PGP, JAR, AIR, macOS)
- 🎭 Steganography Detector
- 🔑 PGP Key Validator
- 🔐 SSL CSR Generator & Validator
- ₿ Crypto Validator (Bitcoin, Ethereum)

### Developer Tools
- 🔑 JWT Debugger
- 🔤 Regex Tester
- 📋 JSON/YAML Validator
- 🔎 Secrets Scanner
- 🔐 Hash Generator
- 🔗 URL Encoder/Decoder

### Network & Validation
- 🌐 DNS Validator
- 🔢 IP Calculator
- 📝 Base64 Encoder/Decoder

## Contributing

Contributions welcome! Please read our [Contributing Guide](CONTRIBUTING.md).

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

- 📧 Email: support@veribits.com
- 🌐 Website: https://veribits.com
- 📚 Documentation: https://docs.veribits.com
- 🐛 Issues: https://github.com/afterdarksystems/veribits-cli/issues

## About

VeriBits is a service from [After Dark Systems, LLC](https://www.afterdarksys.com/)

---

Made with ❤️ by developers, for developers.
