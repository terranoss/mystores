import { test } from '@playwright/test';

const BASE = 'https://open.shopee.com/developer-guide/4';

test('Scrape Shopee token refresh documentation', async ({ page }) => {
  await page.goto(BASE, { timeout: 30000 });
  await page.waitForTimeout(3000);

  // Dump the main content
  const text = await page.evaluate(() => document.body.innerText);
  console.log(text);
});
