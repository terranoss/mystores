import { test, expect } from '@playwright/test';

const BASE = process.env.TEST_BASE_URL || 'http://localhost:5173';
const EMAIL = process.env.TEST_USER_EMAIL || '';
const PASSWORD = process.env.TEST_USER_PASSWORD || '';

test('Edit stock across all stores, save, sync, then dashboard', async ({ page }) => {
  test.setTimeout(60000);

  // 1. Login
  await page.goto(`${BASE}/login`);
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  console.log('✓ Logged in');

  // 2. Go to stock page
  await page.goto(`${BASE}/stock`);
  await page.waitForSelector('h2:has-text("Stock & Price")');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-screenshots/e2e-01-stock-page.png', fullPage: true });
  console.log('✓ Stock page loaded');

  // 3. Click Edit on first product
  const editBtn = page.locator('button:has-text("Edit")').first();
  await editBtn.click();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-screenshots/e2e-02-edit-modal.png', fullPage: true });
  console.log('✓ Edit modal opened');

  // 4. Check for consolidated modal (cross-store)
  const modal = page.locator('.fixed.inset-0.z-50');
  const modalText = await modal.textContent();
  const hasSkuGroup = modalText?.includes('Set all stores');

  if (hasSkuGroup) {
    console.log('→ Cross-store consolidated modal detected');

    const checkbox = page.locator('input[type="checkbox"]');
    await checkbox.check();
    await page.waitForTimeout(300);

    const stockInput = page.locator('input[type="number"]').first();
    await stockInput.fill('');
    await stockInput.fill('200');
    await page.waitForTimeout(300);
    console.log('→ Set stock to 200 for all stores');

    await page.screenshot({ path: 'test-screenshots/e2e-03-before-save.png', fullPage: true });

    const saveBtn = page.locator('button:has-text("Save")');
    await saveBtn.click();
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'test-screenshots/e2e-04-after-save.png', fullPage: true });
    console.log('✓ Saved cross-store stock update');
  } else {
    console.log('→ Single product modal, setting stock...');
    const stockInput = page.locator('input[type="number"]').last();
    await stockInput.fill('');
    await stockInput.fill('200');
    await page.waitForTimeout(300);
    await page.click('button:has-text("Save")');
    await page.waitForTimeout(3000);
    console.log('✓ Saved stock update');
  }

  // 5. Sync - wait for the sync result toast
  const syncBtn = page.locator('button:has-text("Sync Now")');
  await expect(syncBtn).toBeVisible();
  await syncBtn.click();
  console.log('→ Syncing...');

  // Wait for toast to appear and capture it
  await page.waitForTimeout(5000);
  await page.screenshot({ path: 'test-screenshots/e2e-05-after-sync-toast.png', fullPage: true });
  console.log('✓ Sync screenshot captured');

  // 6. Go to Dashboard
  await page.goto(`${BASE}/dashboard`);
  await page.waitForTimeout(2000);
  console.log('✓ Dashboard loaded');

  // 7. Wait for store data to fully load (wait for skeletons to disappear)
  await page.waitForFunction(() => {
    const body = document.body.innerText || '';
    return body.includes('Healthy') || body.includes('Expir') || body.includes('Re-link');
  }, { timeout: 15000 }).catch(() => console.log('→ Timed out waiting for badge text'));

  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-screenshots/e2e-06-dashboard.png', fullPage: true });

  // Dump store text for logs
  const dashText = await page.evaluate(() => document.body.innerText);
  const storeSection = dashText.split('Linked Stores')[1]?.split('+ Link Store')[1] || '';
  console.log(`\nStores:\n${storeSection.trim().substring(0, 300)}`);

  console.log('\n✓ Complete - screenshots saved');
});
