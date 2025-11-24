/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Header', () => {
	test('user can select and reset calendar filters', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.waitForSelector('.ef-calendar-header');

		// Select post status
		await page.selectOption('[name="post_status"]', 'draft');

		// Select user
		await page.click('[placeholder="Select a user"]');
		await page.waitForTimeout(200);
		await page.click('.ef-calendar-filter-author ul li[aria-label="admin"]');

		// Select number of weeks
		await page.selectOption('[name="num_weeks"]', '7');

		// Submit filters
		await page.click('.ef-calendar-filters-buttons button[type="submit"]');
		await page.waitForSelector('.ef-calendar-header');

		// Verify filter values are set
		await expect(page.locator('[name="post_status"]')).toHaveValue('draft');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('admin');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('7');

		// Click the reset button
		await page.click('.ef-calendar-filters-buttons a[name="ef-calendar-reset-filters"]');
		await page.waitForSelector('.ef-calendar-header');

		// Verify filters are reset
		await expect(page.locator('[name="post_status"]')).toHaveValue('');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('6');
	});
});
