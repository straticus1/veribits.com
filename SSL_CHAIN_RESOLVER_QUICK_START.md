# SSL Certificate Chain Resolver - Quick Start Guide

## Installation Steps

### 1. Run Database Migration
```bash
cd /Users/ryan/development/veribits.com
psql -U postgres -d veribits -f db/migrations/008_ssl_chain_resolutions.sql
```

### 2. Verify Files are in Place

**Backend Files:**
- ✅ `/app/src/Controllers/SSLChainResolverController.php` - Main controller
- ✅ `/app/public/index.php` - Routes added (lines 283-295)

**Frontend Files:**
- ✅ `/frontend/app/tools/ssl-chain-resolver/page.jsx` - UI component

**Database:**
- ✅ `/db/migrations/008_ssl_chain_resolutions.sql` - Migration script

### 3. No Additional Dependencies Required
All functionality uses built-in PHP OpenSSL extension and standard libraries.

## Quick Test

### Test Backend API
```bash
# Test with google.com
curl -X POST http://localhost:8080/api/v1/ssl/resolve-chain \
  -F "input_type=url" \
  -F "url=google.com" \
  -F "port=443"
```

### Test Frontend
1. Start Next.js dev server: `cd frontend && npm run dev`
2. Open: `http://localhost:3000/tools/ssl-chain-resolver`
3. Enter `google.com` in the URL field
4. Click "Resolve Certificate Chain"

## API Endpoints Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/ssl/resolve-chain` | POST | Analyze certificate chain |
| `/api/v1/ssl/fetch-missing` | POST | Fetch missing certificates |
| `/api/v1/ssl/build-bundle` | POST | Create certificate bundle |

## Input Formats Supported

1. **URL** - `example.com` or `https://example.com:443`
2. **PEM** - `.pem`, `.crt`, `.cer` files
3. **PKCS12** - `.pfx`, `.p12` files (requires password)
4. **PKCS7** - `.p7b`, `.p7c` files

## Download Formats Available

1. **Individual Certificates** - Each cert as separate .pem file
2. **PEM Bundle** - All certs in one .pem file
3. **PKCS7 Bundle** - Certificate chain in .p7b format
4. **PKCS12 Bundle** - Certs + private key in .pfx (requires private key from input)

## Key Features

- ✅ Multiple input methods (URL, PEM, PKCS12, PKCS7)
- ✅ Automatic missing certificate detection
- ✅ AIA-based certificate fetching
- ✅ Chain validation and visualization
- ✅ Multiple download formats
- ✅ Anonymous user support with rate limiting
- ✅ Secure password/private key handling
- ✅ Automatic cleanup of temporary files

## Security Notes

⚠️ **IMPORTANT:**
- Passwords are NEVER saved to disk or database
- Private keys are deleted immediately after PKCS12 creation
- All processing in isolated `/tmp/veribits-ssl-chain` directory
- Automatic cleanup in error scenarios

## Rate Limits

- **Anonymous Users:** Uses existing anonymous scan limits
- **Authenticated Users:** No additional limits beyond standard monthly quota

## Troubleshooting

### Backend Issues

**Problem:** PHP errors about OpenSSL
```bash
# Verify OpenSSL extension is enabled
php -m | grep openssl
```

**Problem:** Can't connect to SSL websites
```bash
# Check OpenSSL version
php -r "echo OPENSSL_VERSION_TEXT;"
```

**Problem:** Temporary files not cleaning up
```bash
# Manually clean temp directory
rm -rf /tmp/veribits-ssl-chain/*
```

### Frontend Issues

**Problem:** File upload not working
- Check file size (max 10MB)
- Verify CORS settings in backend
- Check browser console for errors

**Problem:** Downloads not working
- Verify browser allows blob downloads
- Check popup blocker settings
- Clear browser cache

## Testing Checklist

- [ ] Database migration completed successfully
- [ ] Backend API responds to curl requests
- [ ] Frontend loads without errors
- [ ] Can resolve chain from URL (test with google.com)
- [ ] Can upload PEM file
- [ ] Can upload PKCS12 file with password
- [ ] Can download individual certificates
- [ ] Can download PEM bundle
- [ ] Can download PKCS7 bundle
- [ ] Error messages display correctly
- [ ] Rate limiting works for anonymous users

## Example Usage Scenarios

### Scenario 1: Web Developer Fixing SSL Chain Issues
1. Navigate to SSL Chain Resolver tool
2. Enter their domain name
3. View incomplete chain warning
4. Click "Fetch Now" to download missing intermediates
5. Download complete PEM bundle
6. Install on web server

### Scenario 2: Security Auditor Analyzing Certificate
1. Upload client's certificate file (PEM)
2. Review chain hierarchy
3. Check expiration dates
4. Identify missing intermediates
5. Download PKCS7 bundle for documentation

### Scenario 3: DevOps Converting Certificate Formats
1. Upload PKCS12 file with password
2. View extracted certificates
3. Download as PEM bundle for Nginx
4. Or download as PKCS7 for Windows Server

## Performance Expectations

- **URL Fetch:** 1-3 seconds (depends on remote server)
- **File Upload:** <1 second for files under 1MB
- **Chain Building:** <1 second for typical chains (2-4 certs)
- **AIA Fetching:** 2-10 seconds (depends on CA server response)
- **Bundle Creation:** <1 second for most formats

## Next Steps After Installation

1. **Add to Navigation:** Update site navigation to include link to `/tools/ssl-chain-resolver`
2. **Documentation:** Add user guide to help section
3. **Monitoring:** Set up logging alerts for errors
4. **Analytics:** Review `ssl_chain_resolutions` table for usage patterns
5. **Marketing:** Announce new tool to users

## Support

For issues or questions, refer to the main implementation guide:
`/Users/ryan/development/veribits.com/SSL_CHAIN_RESOLVER_IMPLEMENTATION.md`
