# Playwright Setup Guide

This guide explains how to set up Playwright for end-to-end testing of the WordPress plugin.

## Prerequisites

- Node.js 18+ installed
- npm or yarn
- Access to a WordPress test site

## Installation

### 1. Initialize npm (if not done)
```bash
npm init -y
```

### 2. Install Playwright
```bash
npm install -D @playwright/test
```

### 3. Install Browsers
```bash
npx playwright install
```

This installs Chromium, Firefox, and WebKit browsers.

To install only specific browsers:
```bash
npx playwright install chromium
npx playwright install firefox
npx playwright install webkit
```

### 4. Create Configuration File

Create `playwright.config.js` in project root:

```javascript
// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',

  use: {
    // WordPress test site URL
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    // Uncomment for additional browsers
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },
  ],
});
```

### 5. Create Test Directory Structure
```bash
mkdir -p e2e
```

---

## Environment Variables

Create `.env.local` file (add to `.gitignore`):

```env
WP_BASE_URL=https://your-test-site.com
WP_ADMIN_USER=admin
WP_ADMIN_PASS=your-password
WP_EDITOR_USER=editor
WP_EDITOR_PASS=editor-password
```

---

## Writing Tests

### Example: WordPress Login Helper

Create `e2e/helpers/wordpress.js`:

```javascript
/**
 * WordPress test helpers
 */

/**
 * Login to WordPress admin
 * @param {import('@playwright/test').Page} page
 * @param {string} username
 * @param {string} password
 */
async function wpLogin(page, username, password) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**');
}

/**
 * Logout from WordPress
 * @param {import('@playwright/test').Page} page
 */
async function wpLogout(page) {
  await page.goto('/wp-login.php?action=logout');
  // Click logout confirmation if present
  const logoutLink = page.locator('a:has-text("log out")');
  if (await logoutLink.isVisible()) {
    await logoutLink.click();
  }
}

/**
 * Navigate to plugin settings
 * @param {import('@playwright/test').Page} page
 */
async function goToPluginSettings(page) {
  await page.goto('/wp-admin/users.php?page=secure-freelancer-access');
}

module.exports = { wpLogin, wpLogout, goToPluginSettings };
```

### Example: Basic Plugin Test

Create `e2e/plugin.spec.js`:

```javascript
// @ts-check
const { test, expect } = require('@playwright/test');
const { wpLogin, goToPluginSettings } = require('./helpers/wordpress');

// Load environment variables
require('dotenv').config({ path: '.env.local' });

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'password';
const EDITOR_USER = process.env.WP_EDITOR_USER || 'editor';
const EDITOR_PASS = process.env.WP_EDITOR_PASS || 'password';

test.describe('Secure Freelancer Access Plugin', () => {

  test.describe('Admin Access', () => {
    test.beforeEach(async ({ page }) => {
      await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    });

    test('admin can access plugin settings', async ({ page }) => {
      await goToPluginSettings(page);
      await expect(page.locator('h1')).toContainText('Secure Freelancer Access');
    });

    test('admin can see all pages in list', async ({ page }) => {
      await page.goto('/wp-admin/edit.php?post_type=page');
      // Admin should see all pages
      const rows = page.locator('table.wp-list-table tbody tr');
      await expect(rows).not.toHaveCount(0);
    });

    test('admin can configure editor permissions', async ({ page }) => {
      await goToPluginSettings(page);
      // Find editor user row
      const editorRow = page.locator(`tr:has-text("${EDITOR_USER}")`);
      await expect(editorRow).toBeVisible();
      // Click edit button
      await editorRow.locator('a:has-text("Edit")').click();
      await expect(page).toHaveURL(/action=edit/);
    });
  });

  test.describe('Editor Access Restrictions', () => {
    test.beforeEach(async ({ page }) => {
      await wpLogin(page, EDITOR_USER, EDITOR_PASS);
    });

    test('editor cannot access plugin settings', async ({ page }) => {
      await page.goto('/wp-admin/users.php?page=secure-freelancer-access');
      // Should see error or redirect
      await expect(page.locator('body')).not.toContainText('Secure Freelancer Access');
    });

    test('editor can only see allowed pages', async ({ page }) => {
      await page.goto('/wp-admin/edit.php?post_type=page');
      // Editor should only see pages they have access to
      // This depends on your plugin configuration
    });

    test('editor gets 403 on restricted page', async ({ page }) => {
      // Try to access a restricted page directly
      const restrictedPageId = 123; // Replace with actual restricted page ID
      const response = await page.goto(`/wp-admin/post.php?post=${restrictedPageId}&action=edit`);

      // Should be blocked
      expect(response?.status()).toBe(403);
    });
  });

});
```

### Example: Access Log Test

Create `e2e/access-log.spec.js`:

```javascript
// @ts-check
const { test, expect } = require('@playwright/test');
const { wpLogin, goToPluginSettings } = require('./helpers/wordpress');

require('dotenv').config({ path: '.env.local' });

test.describe('Access Logging', () => {

  test('access attempts are logged', async ({ page }) => {
    const EDITOR_USER = process.env.WP_EDITOR_USER;
    const EDITOR_PASS = process.env.WP_EDITOR_PASS;
    const ADMIN_USER = process.env.WP_ADMIN_USER;
    const ADMIN_PASS = process.env.WP_ADMIN_PASS;

    // 1. Login as editor and try to access restricted page
    await wpLogin(page, EDITOR_USER, EDITOR_PASS);
    await page.goto('/wp-admin/post.php?post=1&action=edit');

    // 2. Login as admin and check access log
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await goToPluginSettings(page);
    await page.click('a:has-text("Access Log")');

    // 3. Verify log entry exists
    const logTable = page.locator('table.access-log');
    await expect(logTable).toBeVisible();
    await expect(logTable).toContainText(EDITOR_USER);
  });

});
```

---

## Running Tests

### Run All Tests
```bash
npx playwright test
```

### Run Specific Test File
```bash
npx playwright test e2e/plugin.spec.js
```

### Run in Headed Mode (see browser)
```bash
npx playwright test --headed
```

### Run in Debug Mode
```bash
npx playwright test --debug
```

### Run with Specific Browser
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
```

### View Test Report
```bash
npx playwright show-report
```

---

## Package.json Scripts

Add to `package.json`:

```json
{
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "playwright test --debug",
    "test:ui": "playwright test --ui",
    "test:report": "playwright show-report"
  },
  "devDependencies": {
    "@playwright/test": "^1.40.0",
    "dotenv": "^16.3.1"
  }
}
```

---

## CI/CD Integration

### GitHub Actions

Create `.github/workflows/playwright.yml`:

```yaml
name: Playwright Tests
on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    timeout-minutes: 60
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v4

    - uses: actions/setup-node@v4
      with:
        node-version: 20

    - name: Install dependencies
      run: npm ci

    - name: Install Playwright Browsers
      run: npx playwright install --with-deps

    - name: Run Playwright tests
      run: npx playwright test
      env:
        WP_BASE_URL: ${{ secrets.WP_TEST_URL }}
        WP_ADMIN_USER: ${{ secrets.WP_ADMIN_USER }}
        WP_ADMIN_PASS: ${{ secrets.WP_ADMIN_PASS }}

    - uses: actions/upload-artifact@v4
      if: always()
      with:
        name: playwright-report
        path: playwright-report/
        retention-days: 30
```

---

## Tips for WordPress Testing

### 1. Use Test Site
Always test against a dedicated test WordPress installation, never production.

### 2. Database Seeding
Consider creating a script to reset test data before tests:
```javascript
test.beforeAll(async ({ request }) => {
  // Call WP-CLI or custom endpoint to reset data
  await request.post('/wp-json/test/v1/reset');
});
```

### 3. Authentication State
Save authentication state to speed up tests:
```javascript
// In global setup
const { chromium } = require('@playwright/test');

module.exports = async config => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  await page.goto('http://localhost:8080/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');

  await page.context().storageState({ path: 'auth/admin.json' });
  await browser.close();
};
```

Then use in tests:
```javascript
test.use({ storageState: 'auth/admin.json' });
```

### 4. Handle WordPress Popups
```javascript
// Dismiss any welcome panels or notices
await page.evaluate(() => {
  document.querySelectorAll('.notice-dismiss').forEach(el => el.click());
});
```

---

## Troubleshooting

### "Browser not found"
```bash
npx playwright install
```

### Tests timing out
Increase timeout in config:
```javascript
use: {
  actionTimeout: 30000,
  navigationTimeout: 30000,
}
```

### Login not working
- Check if cookies are being set correctly
- Verify no 2FA or CAPTCHA is enabled on test site
- Check for redirect issues

### Flaky tests
- Add explicit waits: `await page.waitForSelector('.element')`
- Use `expect().toBeVisible()` before interactions
- Check for AJAX requests completing

---

## Resources

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [Playwright API Reference](https://playwright.dev/docs/api/class-playwright)
- [Playwright Test Examples](https://github.com/microsoft/playwright/tree/main/examples)
