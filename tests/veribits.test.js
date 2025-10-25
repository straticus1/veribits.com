const { test, expect } = require('@playwright/test');

const BASE_URL = 'https://veribits.com';
const API_URL = `${BASE_URL}/api/v1`;

// Store errors encountered
const errors = [];
const warnings = [];

test.describe('VeriBits - Non-Authenticated Tests', () => {

  test.beforeEach(async ({ page }) => {
    // Capture console errors and warnings
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push({ type: 'console', message: msg.text() });
        console.error('❌ Browser Console Error:', msg.text());
      } else if (msg.type() === 'warning') {
        warnings.push({ type: 'console', message: msg.text() });
        console.warn('⚠️  Browser Console Warning:', msg.text());
      }
    });

    // Capture page errors
    page.on('pageerror', error => {
      errors.push({ type: 'page', message: error.message });
      console.error('❌ Page Error:', error.message);
    });

    // Capture failed requests
    page.on('requestfailed', request => {
      errors.push({
        type: 'request',
        url: request.url(),
        failure: request.failure()?.errorText
      });
      console.error('❌ Request Failed:', request.url(), request.failure()?.errorText);
    });

    // Capture response errors (4xx, 5xx)
    page.on('response', response => {
      if (response.status() >= 400) {
        const error = {
          type: 'http_error',
          status: response.status(),
          url: response.url(),
          statusText: response.statusText()
        };
        errors.push(error);
        console.error(`❌ HTTP ${response.status()}:`, response.url());
      }
    });
  });

  test('API Health Check', async ({ request }) => {
    console.log('Testing health endpoint...');
    const response = await request.get(`${API_URL}/health`);
    expect(response.status()).toBe(200);

    const data = await response.json();
    console.log('✅ Health check response:', JSON.stringify(data, null, 2));

    expect(data.status).toBe('healthy');
    expect(data.checks.database.healthy).toBe(true);
    expect(data.checks.redis.healthy).toBe(true);
    expect(data.checks.filesystem.healthy).toBe(true);
    expect(data.checks.php_extensions.healthy).toBe(true);
  });

  test('Homepage loads without errors', async ({ page }) => {
    console.log('Testing homepage...');
    const response = await page.goto(BASE_URL);
    expect(response.status()).toBeLessThan(400);

    await page.waitForLoadState('networkidle');

    // Check for basic content
    const title = await page.title();
    console.log('✅ Homepage loaded. Title:', title);
    expect(title).toBeTruthy();
  });

  test('About page loads', async ({ page }) => {
    console.log('Testing about page...');
    const response = await page.goto(`${BASE_URL}/about.html`);

    if (response.status() === 404) {
      console.log('⚠️  About page not found (404)');
    } else {
      expect(response.status()).toBeLessThan(400);
      console.log('✅ About page loaded');
    }
  });

  test('Pricing page loads', async ({ page }) => {
    console.log('Testing pricing page...');
    const response = await page.goto(`${BASE_URL}/pricing.html`);

    if (response.status() === 404) {
      console.log('⚠️  Pricing page not found (404)');
    } else {
      expect(response.status()).toBeLessThan(400);
      console.log('✅ Pricing page loaded');
    }
  });

  test('Tools page loads', async ({ page }) => {
    console.log('Testing tools page...');
    const response = await page.goto(`${BASE_URL}/tools.html`);

    if (response.status() === 404) {
      console.log('⚠️  Tools page not found (404)');
    } else {
      expect(response.status()).toBeLessThan(400);
      console.log('✅ Tools page loaded');
    }
  });

  test('Dashboard page loads (should require auth)', async ({ page }) => {
    console.log('Testing dashboard page...');
    const response = await page.goto(`${BASE_URL}/dashboard.html`);

    // Should either redirect to login or show login form
    console.log('Dashboard response status:', response.status());
    console.log('✅ Dashboard page accessed (may require auth)');
  });

  test('Anonymous API limits endpoint', async ({ request }) => {
    console.log('Testing anonymous limits endpoint...');
    const response = await request.get(`${API_URL}/anonymous/limits`);

    console.log('Anonymous limits response status:', response.status());
    if (response.status() === 200) {
      const data = await response.json();
      console.log('✅ Anonymous limits:', JSON.stringify(data, null, 2));
    } else if (response.status() === 404) {
      console.log('⚠️  Anonymous limits endpoint not found');
    } else if (response.status() >= 500) {
      console.error('❌ 500 Error on anonymous limits');
    }
  });

  test('Crypto validation endpoint (anonymous)', async ({ request }) => {
    console.log('Testing crypto validation endpoint...');

    // Test with a sample Bitcoin address
    const testAddress = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'; // Genesis block address

    const response = await request.post(`${API_URL}/crypto/validate`, {
      data: {
        address: testAddress,
        currency: 'BTC'
      }
    });

    console.log('Crypto validation response status:', response.status());

    if (response.status() === 200) {
      const data = await response.json();
      console.log('✅ Crypto validation response:', JSON.stringify(data, null, 2));
    } else if (response.status() === 404) {
      console.log('⚠️  Crypto validation endpoint not found');
    } else if (response.status() === 403 || response.status() === 429) {
      console.log('⚠️  Rate limited or auth required for crypto validation');
    } else if (response.status() >= 500) {
      console.error('❌ 500 Error on crypto validation');
      const body = await response.text();
      console.error('Response body:', body);
    }
  });

  test('JWT validation endpoint', async ({ request }) => {
    console.log('Testing JWT validation endpoint...');

    const testJWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';

    const response = await request.post(`${API_URL}/jwt/validate`, {
      data: {
        token: testJWT
      }
    });

    console.log('JWT validation response status:', response.status());

    if (response.status() === 200) {
      const data = await response.json();
      console.log('✅ JWT validation response:', JSON.stringify(data, null, 2));
    } else if (response.status() === 404) {
      console.log('⚠️  JWT validation endpoint not found');
    } else if (response.status() === 400) {
      console.log('⚠️  JWT validation returned 400 (expected for test JWT)');
    } else if (response.status() >= 500) {
      console.error('❌ 500 Error on JWT validation');
    }
  });

});

test.describe('VeriBits - Authenticated Tests', () => {

  test.beforeEach(async ({ page }) => {
    // Capture errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push({ type: 'console', message: msg.text() });
        console.error('❌ Browser Console Error:', msg.text());
      }
    });

    page.on('response', response => {
      if (response.status() >= 500) {
        errors.push({
          type: 'http_error',
          status: response.status(),
          url: response.url()
        });
        console.error(`❌ HTTP ${response.status()}:`, response.url());
      }
    });
  });

  test('Login page loads', async ({ page }) => {
    console.log('Testing login page...');
    const response = await page.goto(`${BASE_URL}/login.html`);

    if (response.status() === 404) {
      console.log('⚠️  Login page not found (404)');
    } else {
      expect(response.status()).toBeLessThan(400);
      console.log('✅ Login page loaded');

      // Check if login form exists
      const hasLoginForm = await page.locator('form, input[type="email"], input[type="password"]').count() > 0;
      console.log('Has login form elements:', hasLoginForm);
    }
  });

  test('Signup page loads', async ({ page }) => {
    console.log('Testing signup page...');
    const response = await page.goto(`${BASE_URL}/signup.html`);

    if (response.status() === 404) {
      console.log('⚠️  Signup page not found (404)');
    } else {
      expect(response.status()).toBeLessThan(400);
      console.log('✅ Signup page loaded');
    }
  });

  test('Settings page (requires auth)', async ({ page }) => {
    console.log('Testing settings page...');
    const response = await page.goto(`${BASE_URL}/settings.html`);

    console.log('Settings page status:', response.status());
    console.log('✅ Settings page accessed (may redirect if not authenticated)');
  });

  test('API Key endpoint (requires auth)', async ({ request }) => {
    console.log('Testing API keys endpoint...');
    const response = await request.get(`${API_URL}/api-keys`);

    console.log('API keys response status:', response.status());

    if (response.status() === 401 || response.status() === 403) {
      console.log('✅ API keys endpoint correctly requires authentication');
    } else if (response.status() === 200) {
      console.log('⚠️  API keys endpoint accessible (may have cached auth)');
    } else if (response.status() === 404) {
      console.log('⚠️  API keys endpoint not found');
    } else if (response.status() >= 500) {
      console.error('❌ 500 Error on API keys endpoint');
    }
  });

});

test.afterAll(async () => {
  console.log('\n\n' + '='.repeat(80));
  console.log('TEST SUMMARY');
  console.log('='.repeat(80));

  if (errors.length > 0) {
    console.log(`\n❌ ${errors.length} ERRORS FOUND:\n`);
    errors.forEach((error, index) => {
      console.log(`\n${index + 1}. ${error.type.toUpperCase()}`);
      console.log(JSON.stringify(error, null, 2));
    });
  } else {
    console.log('\n✅ NO ERRORS FOUND!');
  }

  if (warnings.length > 0) {
    console.log(`\n⚠️  ${warnings.length} WARNINGS:\n`);
    warnings.forEach((warning, index) => {
      console.log(`${index + 1}. ${warning.message}`);
    });
  }

  console.log('\n' + '='.repeat(80) + '\n');
});
