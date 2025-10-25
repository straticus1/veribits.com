# Anonymous User Constraints

## Overview

VeriBits implements tiered rate limiting to encourage user registration while still allowing anonymous access to basic features.

## Rate Limits

### Anonymous Users (No Authentication)

**Limits per IP Address:**
- **10 requests per hour**
- **50 requests per day**

**Restricted Features (Require Authentication):**
- Malware scanning
- Archive inspection
- ID verification
- File signature verification (PGP/JAR/macOS)
- Webhook registration
- Billing operations

**Allowed Features (Anonymous Access):**
- File hash verification
- Email verification
- Transaction verification
- DNS checks
- SSL certificate checks
- File magic number analysis
- Badge lookup
- Health check

### Authenticated Free Users

**Limits:**
- **100 requests per day**
- **1,000 requests per month**

**Access to All Features** except enterprise-only features

### Authenticated Pro Users

**Limits:**
- **1,000 requests per day**
- **10,000 requests per month**

**All Features Available**

### Enterprise Users

**Limits:**
- Unlimited requests
- Dedicated support
- SLA guarantees

## Implementation Details

### Rate Limiting by IP Address

Anonymous users are tracked by IP address with support for:
- Cloudflare (`CF-Connecting-IP`)
- Standard proxies (`X-Forwarded-For`)
- Nginx proxies (`X-Real-IP`)
- Direct connections (`REMOTE_ADDR`)

### Response Headers

All API responses include rate limit headers:

```http
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 7
X-RateLimit-Reset: 1234567890
X-RateLimit-Window: hour
```

### Error Responses

When rate limit is exceeded:

```json
{
  "error": "Rate limit exceeded",
  "message": "You have exceeded the hourly limit of 10 requests. Please register for higher limits.",
  "limit": 10,
  "reset_in_seconds": 3600,
  "upgrade_message": "Create a free account to get 100 requests per day and 1000 per month."
}
```

HTTP Status Code: `429 Too Many Requests`

## API Endpoints

### Check Anonymous Limits

**GET** `/api/v1/limits/anonymous`

Returns current rate limit status for the requesting IP.

**Response:**
```json
{
  "status": "success",
  "data": {
    "type": "anonymous_limits",
    "limits": {
      "hourly_limit": 10,
      "daily_limit": 50,
      "message": "Anonymous users are limited to 10 requests per hour and 50 requests per day. Please register for higher limits."
    },
    "current_usage": {
      "hourly_remaining": 7,
      "daily_remaining": 43,
      "hourly_limit": 10,
      "daily_limit": 50
    },
    "upgrade_benefits": {
      "free_account": "100 requests/day, 1,000 requests/month",
      "pro_account": "1,000 requests/day, 10,000 requests/month",
      "enterprise": "Unlimited requests with dedicated support"
    },
    "register_url": "/api/v1/auth/register"
  }
}
```

## Code Examples

### Backend - Checking Anonymous Limits

```php
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

// Optional authentication - allows anonymous with rate limits
$auth = Auth::optionalAuth();

if (!$auth['authenticated']) {
    // Anonymous user - rate limits already checked
    $ipAddress = $auth['ip_address'];
    $remaining = $auth['rate_limit']['hourly_remaining'];

    // Add rate limit headers to response
    header("X-RateLimit-Remaining: $remaining");
} else {
    // Authenticated user - use user quota
    $userId = $auth['user_id'];
    if (!RateLimit::checkUserQuota($userId, 'monthly')) {
        Response::error('Monthly quota exceeded', 429);
        return;
    }
}
```

### Frontend - Displaying Limits

```javascript
// Check anonymous limits
async function checkLimits() {
  const response = await fetch('/api/v1/limits/anonymous')
  const data = await response.json()

  console.log('Hourly remaining:', data.data.current_usage.hourly_remaining)
  console.log('Daily remaining:', data.data.current_usage.daily_remaining)

  if (data.data.current_usage.hourly_remaining < 3) {
    showUpgradePrompt()
  }
}

// Show upgrade message when nearing limit
function showUpgradePrompt() {
  alert('You have only ' + remaining + ' requests left this hour. ' +
        'Register for a free account to get 100 requests per day!')
}
```

### Handling Rate Limit Errors

```javascript
async function makeApiCall(endpoint) {
  try {
    const response = await fetch(endpoint)

    if (response.status === 429) {
      const error = await response.json()
      const resetIn = error.reset_in_seconds
      const minutes = Math.ceil(resetIn / 60)

      throw new Error(
        `Rate limit exceeded. Try again in ${minutes} minutes or ` +
        `register for higher limits.`
      )
    }

    return await response.json()
  } catch (error) {
    console.error('API Error:', error.message)
    throw error
  }
}
```

## Migration Path

### Encouraging Registration

1. **Warning at 80% limit:** Show banner when user has used 8/10 hourly requests
2. **Upgrade prompt on limit:** Show registration form when limit is hit
3. **Feature previews:** Show locked features with "Upgrade to access" message
4. **Social proof:** Display "Join 10,000+ verified users" messaging

### Conversion Funnel

```
Anonymous User (10/hour)
    ↓ Register
Free User (100/day, 1000/month)
    ↓ Upgrade
Pro User (1000/day, 10000/month)
    ↓ Contact Sales
Enterprise (Unlimited)
```

## Security Considerations

### IP-based Limitations

- Uses first IP in `X-Forwarded-For` chain (closest to user)
- Validates IPs to exclude private/reserved ranges
- Logs all rate limit violations for abuse detection

### Abuse Prevention

- Redis-based distributed rate limiting
- Sliding window algorithm for accurate counting
- Automatic blocking of suspicious patterns
- Honeypot endpoints for bot detection

## Testing

### Test Anonymous Limits

```bash
# Make 11 requests in quick succession
for i in {1..11}; do
  curl -s http://localhost:8080/api/v1/health
  echo "Request $i"
done

# 11th request should return 429
```

### Test with Authentication

```bash
# With bearer token - no anonymous limits
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/api/v1/verify/file

# Should use user quota instead of IP-based limits
```

## Monitoring

### Key Metrics

- Anonymous requests per hour/day
- Rate limit exceeded events
- Conversion rate (anonymous → registered)
- Average requests per anonymous IP
- Top IP addresses by request count

### Alerts

- Spike in 429 responses (may indicate bot attack)
- High conversion rate drop (may indicate limit too strict)
- Suspicious IP patterns (distributed attack)

## Configuration

### Adjusting Limits

Edit `/app/src/Utils/RateLimit.php`:

```php
// Anonymous user limits
private const ANONYMOUS_HOURLY_LIMIT = 10;  // Requests per hour
private const ANONYMOUS_DAILY_LIMIT = 50;   // Requests per day
```

### Feature Restrictions

Edit restricted features list in `/app/src/Utils/RateLimit.php`:

```php
public static function isFeatureAllowedAnonymous(string $feature): bool {
    $restrictedFeatures = [
        'malware_scan',
        'archive_inspection',
        // Add more features here
    ];

    return !in_array($feature, $restrictedFeatures);
}
```

## Best Practices

1. **Clear Messaging:** Always tell users their limit status
2. **Graceful Degradation:** Don't block completely, show alternatives
3. **Easy Upgrade Path:** Make registration frictionless
4. **Value Proposition:** Clearly show benefits of upgrading
5. **Fair Limits:** Balance business needs with user experience

## Future Enhancements

- [ ] Per-feature rate limits (different limits for different endpoints)
- [ ] Geographic-based limits (stricter for high-abuse regions)
- [ ] CAPTCHA challenge for anonymous users near limit
- [ ] Temporary burst allowance (allow 15 requests in 1 minute once per day)
- [ ] IP reputation scoring (trusted IPs get higher limits)
- [ ] Automatic limit increases for returning anonymous users
