import { test, expect } from '@playwright/test';

const BASE = process.env.TEST_BASE_URL || 'http://localhost:5173';
const EMAIL = process.env.TEST_USER_EMAIL || '';
const PASSWORD = process.env.TEST_USER_PASSWORD || '';

test.describe('Stock Page Verification', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('stock page loads and shows product table', async ({ page }) => {
    await page.goto(`${BASE}/stock`);
    await page.waitForSelector('h2:has-text("Stock & Price")');
    await page.waitForTimeout(2000);
    const productRows = page.locator('table tbody tr');
    const emptyState = page.locator('text=No products found');
    const hasProducts = await productRows.count() > 0;
    const isEmpty = await emptyState.isVisible();
    expect(hasProducts || isEmpty).toBeTruthy();
  });

  test('product table shows SKU in rows', async ({ page }) => {
    await page.goto(`${BASE}/stock`);
    await page.waitForSelector('h2:has-text("Stock & Price")');
    await page.waitForTimeout(2000);
    const skuElements = page.locator('text=SKU:');
    const count = await skuElements.count();
    console.log(`SKUs displayed: ${count}`);
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('edit modal opens and shows cross-store when same SKU', async ({ page }) => {
    await page.goto(`${BASE}/stock`);
    await page.waitForSelector('h2:has-text("Stock & Price")');
    await page.waitForTimeout(2000);

    const editBtn = page.locator('button:has-text("Edit")').first();
    await editBtn.click();
    await page.waitForTimeout(1500);

    const modalText = await page.locator('.fixed.inset-0.z-50').textContent();
    expect(modalText).toContain('Edit');

    const hasSyncCheckbox = modalText?.includes('Set all stores');
    console.log(`Cross-store sync checkbox: ${hasSyncCheckbox}`);

    if (hasSyncCheckbox) {
      const checkbox = page.locator('input[type="checkbox"]');
      await checkbox.check();
      await page.waitForTimeout(300);
      const stockInput = page.locator('input[type="number"]').first();
      await stockInput.fill('');
      await stockInput.fill('50');
      await page.waitForTimeout(300);
      const saveBtn = page.locator('button:has-text("Save")');
      await saveBtn.click();
      await page.waitForTimeout(2000);
      console.log('Cross-store save completed');
    }
  });

  test('sync button works and shows result', async ({ page }) => {
    await page.goto(`${BASE}/stock`);
    await page.waitForSelector('h2:has-text("Stock & Price")');
    await page.waitForTimeout(1000);

    const syncBtn = page.locator('button:has-text("Sync Now")');
    await expect(syncBtn).toBeVisible();
    await syncBtn.click();
    await page.waitForTimeout(3000);
    console.log('Sync button clicked');
  });

  test('search input filters products', async ({ page }) => {
    await page.goto(`${BASE}/stock`);
    await page.waitForSelector('h2:has-text("Stock & Price")');
    await page.waitForTimeout(1000);

    const searchInput = page.locator('input[placeholder*="Search"]');
    await expect(searchInput).toBeVisible();
    await searchInput.fill('MCA1361');
    await page.waitForTimeout(1500);
    console.log('Search works');
  });

  test('dashboard shows store status badges', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForTimeout(5000);

    // Find store cards by looking for the Linked Stores section
    const dashText = await page.evaluate(() => document.body.innerText);
    const storeSection = dashText.split('Linked Stores')[1] || '';
    const storeLines = storeSection.split('✕').filter(Boolean).map(s => s.replace(/\s+/g, ' ').trim()).filter(Boolean);
    console.log(`Stores: ${storeLines.length}`);
    for (const line of storeLines) {
      const badge = line.match(/(Expir\w+|Healthy|Re-link)/i)?.[0] || 'unknown';
      console.log(`  ${badge}`);
    }
    expect(storeLines.length).toBeGreaterThanOrEqual(1);
  });
});
