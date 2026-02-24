// @ts-check
const { test, expect } = require('@playwright/test');

const WP_URL = 'http://localhost:8080';
const WP_ADMIN = 'admin';
const WP_PASSWORD = 'admin123';
const WP_EMAIL = 'admin@example.com';
const WP_TITLE = 'Markdown Negotiation Sandbox';

/** Login and return the wp_rest nonce (required for cookie-based REST API calls).
 * @param {import('@playwright/test').Page} page
 */
async function loginAndGetNonce(page) {
  await page.goto(`${WP_URL}/wp-login.php`);
  await page.getByRole('textbox', { name: 'Username or Email Address' }).fill(WP_ADMIN);
  await page.getByRole('textbox', { name: 'Password' }).fill(WP_PASSWORD);
  await page.getByRole('button', { name: 'Log In' }).click();
  await page.waitForURL(/wp-admin/, { timeout: 15000 });

  // wpApiSettings.nonce is injected on every wp-admin page
  await page.goto(`${WP_URL}/wp-admin/`);
  const nonce = await page.evaluate(() => {
    // @ts-ignore
    return window.wpApiSettings?.nonce ?? '';
  });
  return nonce;
}

test.describe('WordPress Installation & Plugin Setup', () => {

  test('install WordPress', async ({ page }) => {
    await page.goto(`${WP_URL}/wp-admin/install.php`);

    // If WordPress is already installed, install.php redirects to wp-login.php
    if (page.url().includes('wp-login.php') || page.url().includes('wp-admin')) {
      console.log('ℹ️ WordPress already installed — skipping');
      return;
    }

    // Step 1: Language selection (may or may not appear)
    const hasSelect = await page.locator('select').isVisible({ timeout: 5000 }).catch(() => false);
    if (hasSelect) {
      await page.getByRole('button', { name: 'Continue' }).click();
    }

    // Step 2: Installation form
    await page.waitForSelector('[name="weblog_title"]', { timeout: 20000 });
    await page.getByRole('textbox', { name: 'Site Title' }).fill(WP_TITLE);
    await page.getByRole('textbox', { name: 'Username' }).fill(WP_ADMIN);

    const passField = page.getByRole('textbox', { name: 'Password' });
    await passField.clear();
    await passField.fill(WP_PASSWORD);

    await page.getByRole('textbox', { name: 'Your Email' }).fill(WP_EMAIL);

    const searchCheckbox = page.getByRole('checkbox', { name: /discourage search engines/i });
    if (await searchCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
      if (!(await searchCheckbox.isChecked())) await searchCheckbox.check();
    }

    const weakPassCheckbox = page.getByRole('checkbox', { name: /confirm use of weak password/i });
    if (await weakPassCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
      await weakPassCheckbox.check();
    }

    await page.getByRole('button', { name: 'Install WordPress' }).click();
    await expect(page.getByRole('heading', { name: 'Success!' })).toBeVisible({ timeout: 30000 });
    console.log('✅ WordPress installed successfully');
  });

  test('configure permalinks and activate plugin', async ({ page }) => {
    await loginAndGetNonce(page);

    // Set Post name permalinks
    await page.goto(`${WP_URL}/wp-admin/options-permalink.php`);
    await page.getByRole('radio', { name: /post name/i }).check();
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await page.waitForLoadState('networkidle');
    console.log('✅ Permalinks set to Post name');

    // Activate plugin (skip if already active)
    await page.goto(`${WP_URL}/wp-admin/plugins.php`);
    await page.waitForSelector('tr[data-slug="markdown-negotiation-for-agents"]', { timeout: 10000 });

    const pluginRow = page.locator('tr[data-slug="markdown-negotiation-for-agents"]');
    // WordPress generates id="activate-{slug}" — use this to avoid substring-matching "Deactivate".
    const activateLink = pluginRow.locator('a[id^="activate-"]');

    if (await activateLink.isVisible({ timeout: 3000 }).catch(() => false)) {
      await activateLink.click();
      await page.waitForURL(/plugins\.php/, { timeout: 15000 });
      await page.waitForLoadState('networkidle');
      console.log('✅ Plugin activated');
    } else {
      console.log('ℹ️ Plugin already active — skipping activation');
    }

    // Verify plugin row shows Deactivate (i.e. it is active)
    const deactivateLink = pluginRow.locator('a[id^="deactivate-"]');
    await expect(deactivateLink).toBeVisible({ timeout: 10000 });
    console.log('✅ Plugin is active');
  });

  test('create a sample post for testing', async ({ page }) => {
    const nonce = await loginAndGetNonce(page);

    // Check if post already exists
    const existing = await page.request.get(
      `${WP_URL}/wp-json/wp/v2/posts?slug=hello-markdown-world&status=publish`,
    );
    const posts = await existing.json();
    if (posts.length > 0) {
      console.log(`ℹ️ Sample post already exists: ID ${posts[0].id} — ${posts[0].link}`);
      return;
    }

    // Create via REST API using session cookie + nonce
    const response = await page.request.post(`${WP_URL}/wp-json/wp/v2/posts`, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
      },
      data: {
        title: 'Hello Markdown World',
        slug: 'hello-markdown-world',
        content: '<h2>Introduction</h2><p>This is a <strong>test post</strong> to verify Markdown negotiation.</p><ul><li>Item one</li><li>Item two</li><li>Item three</li></ul><h2>Code Example</h2><pre><code>function hello() {\n  return "world";\n}</code></pre>',
        status: 'publish',
      },
    });

    if (response.status() !== 201) {
      const body = await response.json();
      console.error('REST error:', JSON.stringify(body));
    }
    expect(response.status()).toBe(201);
    const post = await response.json();
    console.log(`✅ Sample post created: ID ${post.id} — ${post.link}`);
  });
});

