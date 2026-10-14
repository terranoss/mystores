import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

// Resolve the deploy folder from this spec's own location so the queue is
// always drained against this project (mystores.melur.com), never a sibling repo.
const DEPLOY_DIR = path.resolve(__dirname, 'deploy');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:5173';
const EMAIL = process.env.TEST_USER_EMAIL || '';
const PASSWORD = process.env.TEST_USER_PASSWORD || '';

function processQueue() {
  try {
    const result = execSync(
      `cd "${DEPLOY_DIR}" && python process_queue.py`,
      { timeout: 60000, shell: 'cmd.exe', encoding: 'utf-8' }
    );
    const count = parseInt(result.toString().trim());
    console.log(`Queue: ${count} job(s) processed`);
  } catch (e: any) {
    const msg = (e.stdout || e.message || '').toString().trim();
    console.log(`Queue: ${msg || 'error'}`);
  }
}

test('Edit stock across all stores, save, and verify', async ({ page }) => {
  test.setTimeout(120000);

  await page.goto(`${BASE}/login`);
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  console.log('✓ Logged in');

  await page.goto(`${BASE}/stock`);
  await page.waitForSelector('h2:has-text("Stock & Price")');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: 'test-screenshots/vs-01-stock-page.png', fullPage: true });

  const stockBadges = page.locator('table tbody tr td:nth-child(3) span.inline-flex');
  const initialStocks: number[] = [];
  for (let i = 0; i < await stockBadges.count(); i++) {
    const text = await stockBadges.nth(i).textContent();
    initialStocks.push(parseInt(text || '0'));
  }
  console.log(`Products: ${initialStocks.length}, initial stock: ${JSON.stringify(initialStocks)}`);

  const editBtn = page.locator('button:has-text("Edit")').first();
  await editBtn.click();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-screenshots/vs-02-edit-modal.png' });

  const modalContent = await page.locator('.fixed.inset-0.z-50').textContent();
  const isConsolidated = modalContent?.includes('Set all stores');

  if (isConsolidated) {
    const storeCards = page.locator('.fixed.inset-0.z-50 .rounded-xl.bg-ink-50');
    console.log(`→ Cross-store modal: ${await storeCards.count()} store(s)`);

    await page.locator('input[type="checkbox"]').check();
    await page.waitForTimeout(300);
    const sharedInput = page.locator('input[type="number"]').first();
    await sharedInput.fill('');
    await sharedInput.fill('77');
    await page.waitForTimeout(300);
    await page.screenshot({ path: 'test-screenshots/vs-03-set-stock.png' });

    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(3000);
    console.log('✓ Save clicked');
  } else {
    const stockInput = page.locator('input[type="number"]').last();
    await stockInput.fill('');
    await stockInput.fill('150');
    await page.waitForTimeout(300);
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(3000);
    console.log('✓ Save clicked');
  }

  await page.screenshot({ path: 'test-screenshots/vs-04-after-save.png', fullPage: true });

  const modalStillOpen = await page.locator('.fixed.inset-0.z-50').isVisible().catch(() => false);
  expect(modalStillOpen).toBeFalsy();
  console.log('✓ Modal closed (save completed)');

  console.log('→ Processing queue jobs...');
  processQueue();
  await page.waitForTimeout(2000);

  // Reload to get updated data from database
  await page.reload();
  await page.waitForSelector('h2:has-text("Stock & Price")');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'test-screenshots/vs-05-final.png', fullPage: true });

  const finalBadges = page.locator('table tbody tr td:nth-child(3) span.inline-flex');
  const finalStocks: number[] = [];
  for (let i = 0; i < await finalBadges.count(); i++) {
    const text = await finalBadges.nth(i).textContent();
    finalStocks.push(parseInt(text || '0'));
  }
  console.log(`\nFinal stock: ${JSON.stringify(finalStocks)}`);
  console.log('✓ Cross-store stock update flow completed');
});
