# VeriBits Trial & Payment Model

## Trial System

### Anonymous Users (No Registration)

**Free Trial:**
- **5 free scans** (lifetime, tracked by IP)
- **50MB maximum** file size per scan
- **30-day trial window** (resets after 30 days)
- No credit card required

**What Counts as a "Scan":**
- File hash verification
- Malware scanning
- File magic number analysis
- File signature verification
- Archive inspection
- Any operation that processes an uploaded file

**What Doesn't Count:**
- DNS lookups
- SSL certificate checks
- Email verification
- Transaction verification
- Badge lookups
- API documentation access

### After 5 Free Scans

Users must:
1. **Create an account** (email + password)
2. **Add payment method** (credit card or PayPal)
3. **Choose a plan** or pay-per-scan

## Payment Plans

### Pay-Per-Scan (No Subscription)

**$0.10 per scan**
- No monthly fees
- No commitment
- Pay only for what you use
- Charged immediately per scan
- Best for occasional users

### Monthly Plan - $9.99/month

**Includes:**
- 100 scans per month
- Up to 100MB file size
- Standard support
- API access
- Webhook notifications

**Overage:** $0.08 per additional scan

**Best for:** Regular users (2-3 scans per day)

### Annual Plan - $99/year

**Includes:**
- 1,500 scans per year (125/month average)
- Up to 100MB file size
- Priority support
- API access
- Webhook notifications
- Advanced reporting

**Overage:** $0.06 per additional scan

**Savings:** 17% compared to monthly ($120/year)

**Best for:** Power users and small businesses

### Enterprise Plan - Custom Pricing

**Includes:**
- Unlimited scans
- Up to 1GB file size
- Dedicated account manager
- 99.9% SLA guarantee
- Custom integrations
- Phone support
- Volume discounts for teams

**Contact:** sales@veribits.com

**Best for:** Large organizations and high-volume users

## Payment Methods

### Accepted Payment Methods:

1. **Credit Cards** (via Stripe)
   - Visa, Mastercard, American Express, Discover
   - Saved for recurring billing
   - PCI-compliant tokenization

2. **PayPal**
   - One-time payments
   - Subscription billing
   - No credit card required

3. **Invoice** (Enterprise only)
   - Net-30 terms
   - Purchase orders accepted
   - Wire transfer or ACH

## Implementation

### Backend - Checking Scan Limits

```php
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

// Check authentication and scan limits
$auth = Auth::optionalAuth();

if (!$auth['authenticated']) {
    // Anonymous user - check trial scans
    $clientIp = RateLimit::getClientIp();
    $fileSize = $_FILES['file']['size'] ?? 0;

    $scanCheck = RateLimit::checkAnonymousScan($clientIp, $fileSize);

    if (!$scanCheck['allowed']) {
        Response::error(
            $scanCheck['message'],
            $scanCheck['reason'] === 'file_too_large' ? 413 : 403,
            [
                'reason' => $scanCheck['reason'],
                'upgrade_required' => true,
                'register_url' => '/api/v1/auth/register'
            ]
        );
        return;
    }

    // Scan allowed - increment counter after successful scan
    // (done after scan completes)
    $incrementAfterScan = true;

} else {
    // Authenticated user - check payment status
    $userId = $auth['user_id'];

    // Check if user has active subscription or credits
    $billingStatus = Billing::checkStatus($userId);

    if (!$billingStatus['can_scan']) {
        Response::error(
            'Payment required. Please add a payment method or purchase credits.',
            402, // Payment Required
            [
                'billing_status' => $billingStatus,
                'upgrade_url' => '/api/v1/billing/plans'
            ]
        );
        return;
    }
}
```

### After Successful Scan

```php
// Scan completed successfully
if ($incrementAfterScan ?? false) {
    // Anonymous user - count this scan
    RateLimit::incrementAnonymousScan($clientIp);
}

if ($auth['authenticated']) {
    // Authenticated user - deduct from quota or charge
    Billing::recordScan($userId, $scanType, $fileSize);
}
```

### Frontend - Showing Trial Status

```javascript
// Check trial status on page load
async function checkTrialStatus() {
  const response = await fetch('/api/v1/limits/anonymous')
  const data = await response.json()

  const trial = data.data.trial

  // Show trial status banner
  if (trial.scans_remaining > 0) {
    showTrialBanner(
      `Free Trial: ${trial.scans_remaining} of ${trial.free_scans} scans remaining`
    )
  } else {
    showUpgradePrompt()
  }
}

// Handle trial expiration
function showUpgradePrompt() {
  const modal = `
    <div class="upgrade-modal">
      <h2>Trial Complete!</h2>
      <p>You've used all 5 free scans. Create an account to continue.</p>
      <div class="pricing">
        <div class="plan">
          <h3>Pay Per Scan</h3>
          <p class="price">$0.10/scan</p>
          <button onclick="redirectToRegister()">Get Started</button>
        </div>
        <div class="plan featured">
          <h3>Monthly</h3>
          <p class="price">$9.99/month</p>
          <p class="includes">100 scans included</p>
          <button onclick="redirectToRegister()">Best Value</button>
        </div>
        <div class="plan">
          <h3>Annual</h3>
          <p class="price">$99/year</p>
          <p class="includes">Save 17%</p>
          <button onclick="redirectToRegister()">Save Money</button>
        </div>
      </div>
    </div>
  `
  document.body.insertAdjacentHTML('beforeend', modal)
}
```

### Handling Scan Errors

```javascript
async function uploadFile(file) {
  if (!file) return

  // Check file size for anonymous users
  if (!isLoggedIn() && file.size > 50 * 1024 * 1024) {
    showError('File exceeds 50MB limit for free trial. Please register to scan larger files.')
    return
  }

  try {
    const formData = new FormData()
    formData.append('file', file)

    const response = await fetch('/api/v1/verify/file', {
      method: 'POST',
      body: formData
    })

    if (response.status === 403) {
      const error = await response.json()
      if (error.upgrade_required) {
        showUpgradePrompt()
      }
      return
    }

    if (response.status === 402) {
      // Payment required for authenticated user
      showPaymentPrompt()
      return
    }

    if (response.status === 413) {
      showError('File is too large. Please upgrade your plan for larger files.')
      return
    }

    const result = await response.json()
    displayResults(result)

  } catch (error) {
    showError('Upload failed: ' + error.message)
  }
}
```

## Conversion Funnel

```
Anonymous User
    ↓
Try First Scan (Free)
    ↓
Complete 2-3 Scans (Building Trust)
    ↓
Approaching Limit (Show Reminder)
    ↓
5th Scan Used (Require Registration)
    ↓
Create Account (Email + Password)
    ↓
Add Payment Method
    ↓
Choose Plan
    ↓
Paying Customer
```

## Pricing Strategy

### Why This Model Works:

1. **Low Barrier to Entry:** No signup required for first 5 scans
2. **Build Trust:** Users experience value before paying
3. **Flexible Pricing:** Options for every use case
4. **Fair Limits:** 5 scans is enough to evaluate, not enough to abuse
5. **Compelling Upgrade:** Monthly plan breaks even at 100 scans vs pay-per-scan

### Revenue Projections:

**Conservative Estimate (Month 1-3):**
- 1,000 anonymous users/month
- 10% convert to paid (100 users)
- Average $5/user/month (mix of pay-per-scan and monthly)
- **Revenue: $500/month**

**Growth Estimate (Month 6-12):**
- 10,000 anonymous users/month
- 15% convert to paid (1,500 users)
- Average $8/user/month
- **Revenue: $12,000/month**

**Mature Estimate (Year 2+):**
- 50,000 anonymous users/month
- 20% convert to paid (10,000 users)
- Average $10/user/month (more annual plans)
- **Revenue: $100,000/month**

## Best Practices

### Encouraging Conversion:

1. **Scans 1-2:** Welcome message, no pressure
2. **Scan 3:** "You're halfway through your trial"
3. **Scan 4:** "1 scan remaining - register to continue"
4. **Scan 5:** "Last free scan - upgrade now for instant access"
5. **After 5:** Paywall with clear pricing options

### Reducing Friction:

- One-click PayPal registration
- Social login (Google, GitHub)
- Save card for future use
- Annual plan discount
- Money-back guarantee

### Building Value:

- Show time/cost savings
- Display successful scans count
- "Join 10,000+ verified users"
- Testimonials from satisfied customers
- Security badges (SOC2, GDPR compliant)

## Database Schema

### Anonymous Scans Tracking

Stored in Redis for speed:
```
Key: anon_scans:{ip_address}
Value: integer (scan count)
Expiry: 30 days (2592000 seconds)
```

### User Billing Table

```sql
CREATE TABLE user_billing (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    plan_type VARCHAR(50), -- 'pay_per_scan', 'monthly', 'annual', 'enterprise'
    status VARCHAR(50), -- 'active', 'cancelled', 'past_due'
    scans_included INTEGER,
    scans_used INTEGER DEFAULT 0,
    overage_rate DECIMAL(5,2),
    billing_cycle_start TIMESTAMP,
    billing_cycle_end TIMESTAMP,
    last_payment_date TIMESTAMP,
    next_payment_date TIMESTAMP,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    paypal_subscription_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE scan_transactions (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    scan_type VARCHAR(100),
    file_size BIGINT,
    charged_amount DECIMAL(10,2),
    is_included BOOLEAN, -- true if within plan, false if overage
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Monitoring & Alerts

### Key Metrics:

- **Trial Conversion Rate:** Anonymous → Paid
- **Average Revenue Per User (ARPU)**
- **Churn Rate:** Monthly cancellations
- **Lifetime Value (LTV):** Total revenue per user
- **Trial Completion Rate:** % who use all 5 scans

### Alerts:

- Conversion rate drops below 10%
- Churn exceeds 5%/month
- Average scans per trial < 2 (users not engaged)
- Payment failures spike

## Future Enhancements

- [ ] Referral program (give friend 5 scans, get 10 yourself)
- [ ] Volume discounts for large files
- [ ] Team plans (shared quota)
- [ ] API-only plans for developers
- [ ] Credits that never expire (prepay)
- [ ] Gift cards for enterprise gifting
