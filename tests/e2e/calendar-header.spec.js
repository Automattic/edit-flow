/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Header', () => {
	test('user can select and reset calendar filters', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Select post status
		await page.locator('[name="post_status"]').selectOption('draft');

		// Select user
		await page.locator('[placeholder="Select a user"]').click();
		await page.locator('.ef-calendar-filter-author ul li[aria-label="admin"]').waitFor({ state: 'visible' });
		await page.locator('.ef-calendar-filter-author ul li[aria-label="admin"]').click();

		// Select number of weeks
		await page.locator('[name="num_weeks"]').selectOption('7');

		// Submit filters
		await page.locator('.ef-calendar-filters-buttons button[type="submit"]').click();
		await page.locator('.ef-calendar-header').waitFor();

		// Verify filter values are set
		await expect(page.locator('[name="post_status"]')).toHaveValue('draft');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('admin');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('7');

		// Click the reset button
		await page.locator('.ef-calendar-filters-buttons a[name="ef-calendar-reset-filters"]').click();
		await page.locator('.ef-calendar-header').waitFor();

		// Verify filters are reset
		await expect(page.locator('[name="post_status"]')).toHaveValue('');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('6');
	});
});
