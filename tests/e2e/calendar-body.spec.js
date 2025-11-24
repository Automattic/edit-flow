/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Body', () => {
	test('published posts appear on calendar with published status', async ({ admin, page, requestUtils }) => {
		// Create a published post via REST API (more reliable than UI)
		await requestUtils.createPost({
			title: 'Published Post',
			status: 'publish',
		});

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the published post appears
		const publishedPost = page.locator('.day-item').filter({ hasText: 'Published Post' }).first();
		await expect(publishedPost).toBeVisible();

		// Cleanup
		await requestUtils.deleteAllPosts();
	});

	test('calendar has draggable functionality enabled', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify jQuery UI sortable is initialized on post lists
		const hasSortable = await page.evaluate(() => {
			const postLists = jQuery('.post-list');
			if (postLists.length === 0) return false;

			// Check if sortable is initialized
			const sortableInstance = postLists.first().sortable('instance');
			return sortableInstance !== undefined;
		});

		expect(hasSortable).toBe(true);
	});

	test('scheduled posts appear on calendar', async ({ admin, page, requestUtils }) => {
		// Create a future-dated post via REST API
		const futureDate = new Date();
		futureDate.setDate(futureDate.getDate() + 2);

		await requestUtils.createPost({
			title: 'Scheduled Post',
			status: 'future',
			date: futureDate.toISOString(),
		});

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the scheduled post appears
		const scheduledPost = page.locator('.day-item').filter({ hasText: 'Scheduled Post' }).first();
		await expect(scheduledPost).toBeVisible();

		// Cleanup
		await requestUtils.deleteAllPosts();
	});
});
