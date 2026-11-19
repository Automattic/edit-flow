/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Body', () => {
	test('published posts appear on calendar with published status', async ({ admin, page, requestUtils, createdPostIds }) => {
		const postTitle = `Published Post ${Date.now()}`;

		// Create a published post via REST API (more reliable than UI)
		const post = await requestUtils.createPost({
			title: postTitle,
			status: 'publish',
		});
		createdPostIds.push(post.id);

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the published post appears
		const publishedPost = page.locator('.day-item').filter({ hasText: postTitle }).first();
		await expect(publishedPost).toBeVisible();
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

	test('scheduled posts appear on calendar', async ({ admin, page, requestUtils, createdPostIds }) => {
		const postTitle = `Scheduled Post ${Date.now()}`;

		// Create a future-dated post via REST API
		const futureDate = new Date();
		futureDate.setDate(futureDate.getDate() + 2);

		const post = await requestUtils.createPost({
			title: postTitle,
			status: 'future',
			date: futureDate.toISOString(),
		});
		createdPostIds.push(post.id);

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the scheduled post appears
		const scheduledPost = page.locator('.day-item').filter({ hasText: postTitle }).first();
		await expect(scheduledPost).toBeVisible();
	});
});
