/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Header', () => {
	test.skip('expects a user can select a value for all filters save them and reset them', async ({
		admin,
		editor,
		page,
	}) => {
		// TODO: This test requires publishing posts, but Edit Flow's custom statuses
		// may interfere with the standard WordPress publish flow in Playwright.
		// Need to investigate Edit Flow's status handling and adjust the test accordingly.

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.waitForSelector('.ef-calendar-header');

		// Select post status
		await page.selectOption('[name="post_status"]', 'publish');

		// Select user
		await page.click('[placeholder="Select a user"]');
		await page.waitForTimeout(200);
		await page.click('.ef-calendar-filter-author ul li[aria-label="admin"]');

		// Select category
		await page.click('[placeholder="Select a category"]');
		await page.waitForTimeout(200);
		await page.click('.ef-calendar-filter-cat ul li[aria-label="Category A"]');

		// Select number of weeks
		await page.selectOption('[name="num_weeks"]', '7');

		// Submit filters
		await page.click('.ef-calendar-filters-buttons button[type="submit"]');
		await page.waitForSelector('.ef-calendar-header');

		// Verify filter values are set
		await expect(page.locator('[name="post_status"]')).toHaveValue('publish');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('admin');
		await expect(page.locator('.ef-calendar-filter-cat input')).toHaveValue('Category A');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('7');

		// Click the reset button
		await page.click('.ef-calendar-filters-buttons a[name="ef-calendar-reset-filters"]');
		await page.waitForSelector('.ef-calendar-header');

		// Verify filters are reset
		await expect(page.locator('[name="post_status"]')).toHaveValue('');
		await expect(page.locator('[placeholder="Select a user"]')).toHaveValue('');
		await expect(page.locator('.ef-calendar-filter-cat input')).toHaveValue('');
		await expect(page.locator('[name="num_weeks"]')).toHaveValue('6');
	});
});
