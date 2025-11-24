/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar', () => {
	test('calendar page loads and displays current month', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');

		// Verify calendar header is present
		await expect(page.locator('.ef-calendar-header')).toBeVisible();

		// Verify we have day units (calendar cells) - this indicates calendar is loaded
		const dayUnits = page.locator('.day-unit');
		await expect(dayUnits.first()).toBeVisible();
		const dayCount = await dayUnits.count();
		expect(dayCount).toBeGreaterThan(20); // Should have at least 4 weeks worth of days
	});

	test('calendar filters are functional', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Test status filter
		const statusSelect = page.locator('[name="post_status"]');
		await expect(statusSelect).toBeVisible();
		await statusSelect.selectOption('draft');
		await expect(statusSelect).toHaveValue('draft');

		// Test weeks selector
		const weeksSelect = page.locator('[name="num_weeks"]');
		await expect(weeksSelect).toBeVisible();
		await weeksSelect.selectOption('4');
		await expect(weeksSelect).toHaveValue('4');

		// Apply filters
		await page.locator('.ef-calendar-filters-buttons button[type="submit"]').click();
		await page.locator('.ef-calendar-header').waitFor();

		// Verify filters persisted
		await expect(statusSelect).toHaveValue('draft');
		await expect(weeksSelect).toHaveValue('4');

		// Test reset
		await page.locator('.ef-calendar-filters-buttons a[name="ef-calendar-reset-filters"]').click();
		await page.locator('.ef-calendar-header').waitFor();

		// Verify filters are cleared
		await expect(statusSelect).toHaveValue('');
		await expect(weeksSelect).toHaveValue('6'); // Default value
	});

	test('draft post appears on calendar', async ({ admin, editor, page, requestUtils }) => {
		const postTitle = `Draft Post ${Date.now()}`;

		// Create a draft post
		await admin.createNewPost({ title: postTitle });
		await editor.canvas.locator('role=textbox[name="Add title"i]').waitFor();
		await editor.saveDraft();

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the draft post appears on the calendar
		const postOnCalendar = page.locator(`.item-headline.post-title strong:has-text("${postTitle}")`);
		await expect(postOnCalendar).toBeVisible({ timeout: 10000 });

		// Cleanup
		await requestUtils.deleteAllPosts();
	});
});
